<?php
/**
 * Ajax：提交视频生成任务（代理上游 /openapi/v1/ai/videos）
 * 前端以 multipart/form-data 提交：
 *   payload       —— JSON 字符串，字段同 VideoRequest
 *   first_frame / last_frame / keyframe_0~3 / reference_0~6 —— 图像文件
 * 文档限制：时长上限由平台限制 ≤15 秒；reference_images 2~7 张；图像总数 ≤9；
 *           keyframes ≤4 且时间严格递增、位于时长内、落在 1/3 秒网格。
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$payload = json_decode(isset($_POST['payload']) ? $_POST['payload'] : '', true);
if (!is_array($payload)) {
    json_response(array('ok' => false, 'msg' => '参数格式错误'), 422);
}

// 平台限制：所有时长上限均为 15 秒
if (isset($payload['duration']) && (int)$payload['duration'] > 15) {
    json_response(array('ok' => false, 'msg' => '视频时长上限为 15 秒'), 422);
}
// transitions 分段时长同样限制累计 ≤15
if (!empty($payload['transitions']) && is_array($payload['transitions'])) {
    $total = 0;
    foreach ($payload['transitions'] as $t) { $total += isset($t['duration']) ? (float)$t['duration'] : 0; }
    if ($total > 15) json_response(array('ok' => false, 'msg' => '分段转场累计时长不能超过 15 秒'), 422);
}

// 白名单字段（文档 VideoRequest）
$allowed = array('quote_id','workflow','transitions','reference_video_urls','reference_audio_urls',
                 'provider_id','model','prompt','aspect_ratio','resolution','duration',
                 'first_frame','last_frame','reference_images','keyframes','reference_audios');
$payload = array_intersect_key($payload, array_flip($allowed));

// 处理上传文件
$tmpDir = BASE_PATH . '/storage/tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
$files = array();
$tmpFiles = array();

// 通用文件接收函数
$recv = function($field, $maxBytes, $errTip) use (&$files, &$tmpFiles, $tmpDir) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$field]['size'] > $maxBytes) {
            json_response(array('ok' => false, 'msg' => $errTip), 413);
        }
        $tmp = $tmpDir . '/' . $field . '_' . uniqid();
        move_uploaded_file($_FILES[$field]['tmp_name'], $tmp);
        $tmpFiles[] = $tmp;
        $files[$field] = $tmp;
        return true;
    }
    return false;
};

// 首帧 / 尾帧
if ($recv('first_frame', 12*1024*1024, '首帧图片超过 12MiB')) {
    $payload['first_frame'] = array('source_type' => 'file', 'slot' => 0);
}
if ($recv('last_frame', 12*1024*1024, '尾帧图片超过 12MiB')) {
    $payload['last_frame'] = array('source_type' => 'file', 'slot' => 0);
}
// 关键帧 keyframe_0~3：slot 与 keyframes 数组顺序对应
$kfSlots = array();
for ($i = 0; $i < 4; $i++) {
    if ($recv('keyframe_' . $i, 12*1024*1024, "关键帧 {$i} 超过 12MiB")) $kfSlots[] = $i;
}
if ($kfSlots && !empty($payload['keyframes']) && is_array($payload['keyframes'])) {
    foreach ($payload['keyframes'] as $idx => $kf) {
        if (isset($kfSlots[$idx])) {
            $payload['keyframes'][$idx]['image'] = array('source_type' => 'file', 'slot' => $kfSlots[$idx]);
        }
    }
}
// 参考图 reference_0~6：slot 与 reference_images 数组 slot 对应
$refSlots = array();
for ($i = 0; $i < 7; $i++) {
    if ($recv('reference_' . $i, 12*1024*1024, "参考图 {$i} 超过 12MiB")) $refSlots[] = $i;
}
if ($refSlots) {
    $refs = isset($payload['reference_images']) && is_array($payload['reference_images']) ? $payload['reference_images'] : array();
    foreach ($refSlots as $slot) {
        $refs[] = array('source_type' => 'file', 'slot' => $slot);
    }
    $payload['reference_images'] = $refs;
}
// 文档限制：reference_images 一旦使用至少 2 张、最多 7 张
if (!empty($payload['reference_images'])) {
    $c = count($payload['reference_images']);
    if ($c < 2) { foreach ($tmpFiles as $f) @unlink($f); json_response(array('ok' => false, 'msg' => '视频参考图至少需要 2 张'), 422); }
    if ($c > 7) { foreach ($tmpFiles as $f) @unlink($f); json_response(array('ok' => false, 'msg' => '视频参考图最多 7 张'), 422); }
}

$idemKey = gen_idempotency_key();
$api  = new PigApi();
$resp = $api->createVideo($payload, $idemKey, $files ? $files : null);
foreach ($tmpFiles as $f) @unlink($f);

$http = isset($resp['__http']) ? $resp['__http'] : 0;
if (($http === 200 || $http === 202) && isset($resp['data']['operation'])) {
    $op = $resp['data']['operation'];
    $mode = !empty($payload['reference_images']) ? 'reference_to_video'
          : (!empty($payload['first_frame']) || !empty($payload['last_frame']) ? 'image_to_video' : 'text_to_video');
    $taskId = DB::insert(
        "INSERT INTO tasks (user_id, operation_id, kind, mode, model, provider_id, prompt, params,
                            idempotency_key, status, generation_status, settlement_status,
                            requested, completed, created_at, updated_at)
         VALUES (?, ?, 'video', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, NOW(), NOW())",
        array(current_user()['id'], $op['id'], $mode,
              isset($payload['model']) ? $payload['model'] : '',
              isset($payload['provider_id']) ? $payload['provider_id'] : '',
              isset($payload['prompt']) ? $payload['prompt'] : '',
              json_encode($payload, JSON_UNESCAPED_UNICODE), $idemKey,
              $op['status'],
              isset($op['generation_status']) ? $op['generation_status'] : '',
              isset($op['settlement_status']) ? $op['settlement_status'] : ''));
    json_response(array('ok' => true, 'task_id' => $taskId, 'operation' => $op));
}

$msg = isset($resp['message']) ? $resp['message'] : '任务提交失败';
if ($http === 409 && isset($resp['code']) && strpos($resp['code'], 'quote') !== false) {
    $msg = '报价已失效（未扣费），请重新获取计费预览后再提交';
}
json_response(array('ok' => false, 'msg' => $msg, 'code' => isset($resp['code'])?$resp['code']:''), $http ? $http : 500);
