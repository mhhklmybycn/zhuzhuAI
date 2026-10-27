<?php
/**
 * Ajax：提交图像生成任务（代理上游 /openapi/v1/ai/images）
 * 前端以 multipart/form-data 提交：
 *   payload      —— JSON 字符串，字段同 ImageRequest（mode/prompt/n 必填，retail 模型须含 quote_id）
 *   reference_0~3 —— 参考图文件（每张 ≤12MiB，与 references 合计 ≤4 张）
 *   mask         —— 可选蒙版（≤4MiB）
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$payload = json_decode(isset($_POST['payload']) ? $_POST['payload'] : '', true);
if (!is_array($payload) || empty($payload['mode']) || empty($payload['prompt']) || empty($payload['n'])) {
    json_response(array('ok' => false, 'msg' => '参数不完整（mode、prompt、n 必填）'), 422);
}

// 白名单字段（文档 ImageRequest）
$allowed = array('provider_id','model','mode','quote_id','workflow','resolution',
                 'width','height','prompt','size','quality','n','references');
$payload = array_intersect_key($payload, array_flip($allowed));
$payload['n'] = min(4, max(1, (int)$payload['n']));

// 处理上传文件：校验大小并暂存
$tmpDir = BASE_PATH . '/storage/tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
$files = array();      // 传给上游的 multipart 文件
$tmpFiles = array();   // 暂存文件，用完删除
$references = isset($payload['references']) && is_array($payload['references']) ? $payload['references'] : array();
$fileCount = count($references);

foreach (array('reference_0','reference_1','reference_2','reference_3') as $slot => $field) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$field]['size'] > 12 * 1024 * 1024) {
            json_response(array('ok' => false, 'msg' => "参考图 {$slot} 超过 12MiB 限制"), 413);
        }
        $tmp = $tmpDir . '/ref_' . uniqid() . '_' . $slot;
        move_uploaded_file($_FILES[$field]['tmp_name'], $tmp);
        $tmpFiles[] = $tmp;
        $files[$field] = $tmp;
        // 在 references 中登记 slot，与 multipart 字段对应
        $references[] = array('source_type' => 'file', 'slot' => $slot);
        $fileCount++;
    }
}
if (isset($_FILES['mask']) && $_FILES['mask']['error'] === UPLOAD_ERR_OK) {
    if ($_FILES['mask']['size'] > 4 * 1024 * 1024) {
        json_response(array('ok' => false, 'msg' => '蒙版超过 4MiB 限制'), 413);
    }
    $tmp = $tmpDir . '/mask_' . uniqid();
    move_uploaded_file($_FILES['mask']['tmp_name'], $tmp);
    $tmpFiles[] = $tmp;
    $files['mask'] = $tmp;
    $payload['mask'] = array('source_type' => 'file', 'slot' => 0);
}
// 文档限制：references 与上传参考图合计 ≤4
if ($fileCount > 4) {
    foreach ($tmpFiles as $f) @unlink($f);
    json_response(array('ok' => false, 'msg' => '参考图数量超过上限（最多 4 张）'), 422);
}
if ($references) $payload['references'] = $references;

$idemKey = gen_idempotency_key();
$api  = new PigApi();
$resp = $api->createImage($payload, $idemKey, $files ? $files : null);
foreach ($tmpFiles as $f) @unlink($f); // 清理暂存文件

$http = isset($resp['__http']) ? $resp['__http'] : 0;
if (($http === 200 || $http === 202) && isset($resp['data']['operation'])) {
    $op = $resp['data']['operation'];
    // 任务入库
    $taskId = DB::insert(
        "INSERT INTO tasks (user_id, operation_id, kind, mode, model, provider_id, prompt, params,
                            idempotency_key, status, generation_status, settlement_status,
                            requested, completed, created_at, updated_at)
         VALUES (?, ?, 'image', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())",
        array(current_user()['id'], $op['id'], $payload['mode'],
              isset($payload['model']) ? $payload['model'] : '',
              isset($payload['provider_id']) ? $payload['provider_id'] : '',
              $payload['prompt'], json_encode($payload, JSON_UNESCAPED_UNICODE), $idemKey,
              $op['status'],
              isset($op['generation_status']) ? $op['generation_status'] : '',
              isset($op['settlement_status']) ? $op['settlement_status'] : '',
              isset($op['requested']) ? (int)$op['requested'] : (int)$payload['n']));
    json_response(array('ok' => true, 'task_id' => $taskId, 'operation' => $op));
}

// 失败：409 quote_stale 等特殊提示
$msg = isset($resp['message']) ? $resp['message'] : '任务提交失败';
if ($http === 409 && isset($resp['code']) && strpos($resp['code'], 'quote') !== false) {
    $msg = '报价已失效（未扣费），请重新获取计费预览后再提交';
}
json_response(array('ok' => false, 'msg' => $msg, 'code' => isset($resp['code'])?$resp['code']:''), $http ? $http : 500);
