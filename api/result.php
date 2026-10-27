<?php
/**
 * 结果内容代理：浏览器通过本接口查看/下载结果，避免暴露 API 密钥
 * GET 参数：task（本地任务ID）、rid（上游 result_id）
 * 结果会缓存到 storage/results/，再次访问直接读本地
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$taskId = isset($_GET['task']) ? (int)$_GET['task'] : 0;
$rid    = isset($_GET['rid']) ? $_GET['rid'] : '';
if (!$taskId || !preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $rid)) {
    http_response_code(422); exit('参数错误');
}

$task = DB::one("SELECT * FROM tasks WHERE id = ? AND user_id = ?", array($taskId, current_user()['id']));
if (!$task) { http_response_code(404); exit('任务不存在'); }

// 本地缓存命中则直接输出
$ext  = $task['kind'] === 'video' ? 'mp4' : 'png';
$dir  = BASE_PATH . '/storage/results';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$file = $dir . '/' . $taskId . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $rid) . '.' . $ext;

if (!file_exists($file)) {
    $api = new PigApi();
    $r = $api->downloadResult($task['operation_id'], $rid, $file);
    if (!$r['ok']) { http_response_code(404); exit('结果暂不可用：' . $r['msg']); }
    // 修正扩展名（根据真实 MIME）
    if (strpos($r['mime'], 'jpeg') !== false || strpos($r['mime'], 'jpg') !== false) {
        rename($file, $file . '.jpg'); $file .= '.jpg';
    } elseif (strpos($r['mime'], 'webp') !== false) {
        rename($file, $file . '.webp'); $file .= '.webp';
    }
}

// 输出文件流
$mime = $task['kind'] === 'video' ? 'video/mp4' : 'image/' . pathinfo($file, PATHINFO_EXTENSION);
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . basename($file) . '"');
readfile($file);
exit;
