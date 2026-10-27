<?php
/**
 * Ajax：查询单个任务状态（代理上游 GET /operations/{id}，并同步到本地库）
 * 任务结算后写入 usage_logs 供后台统计
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = DB::one("SELECT * FROM tasks WHERE id = ? AND user_id = ?", array($taskId, current_user()['id']));
if (!$task) json_response(array('ok' => false, 'msg' => '任务不存在'), 404);

// 终态任务直接返回本地数据，不再请求上游
if (in_array($task['status'], array('succeeded', 'failed'))) {
    json_response(array('ok' => true, 'task' => $task));
}

$api  = new PigApi();
$resp = $api->getOperation($task['operation_id']);
$http = isset($resp['__http']) ? $resp['__http'] : 0;

if ($http === 200 && isset($resp['data']['operation'])) {
    $op = $resp['data']['operation'];
    $oldStatus = $task['status'];
    $results   = isset($op['results']) ? json_encode($op['results'], JSON_UNESCAPED_UNICODE) : $task['results'];

    DB::run("UPDATE tasks SET status=?, generation_status=?, settlement_status=?, charge_points=?,
             refund_points=?, net_points=?, requested=?, completed=?, results=?, updated_at=NOW()
             WHERE id=?",
        array($op['status'],
              isset($op['generation_status']) ? $op['generation_status'] : '',
              isset($op['settlement_status']) ? $op['settlement_status'] : '',
              isset($op['charge_points']) ? (int)$op['charge_points'] : 0,
              isset($op['refund_points']) ? (int)$op['refund_points'] : 0,
              isset($op['net_points']) ? (int)$op['net_points'] : 0,
              isset($op['requested']) ? (int)$op['requested'] : 0,
              isset($op['completed']) ? (int)$op['completed'] : 0,
              $results, $taskId));

    // 任务进入成功态时写入用量日志（仅一次）
    if ($op['status'] === 'succeeded' && $oldStatus !== 'succeeded') {
        DB::insert("INSERT INTO usage_logs (user_id, task_id, kind, model, points, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'succeeded', NOW())",
                   array($task['user_id'], $taskId, $task['kind'], $task['model'],
                         isset($op['net_points']) ? (int)$op['net_points'] : 0));
    }
    $task = DB::one("SELECT * FROM tasks WHERE id = ?", array($taskId));
    json_response(array('ok' => true, 'task' => $task));
}

if ($http === 404) {
    DB::run("UPDATE tasks SET status='failed', error='上游任务不存在', updated_at=NOW() WHERE id=?", array($taskId));
    json_response(array('ok' => false, 'msg' => '上游任务不存在'), 404);
}
json_response(array('ok' => false, 'msg' => isset($resp['message']) ? $resp['message'] : '状态查询失败'),
              $http ? $http : 500);
