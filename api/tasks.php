<?php
/**
 * Ajax：当前用户的任务列表
 * 参数：filter = active（进行中）| done（已完成/失败），默认全部
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_user(true);

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where  = "user_id = ?";
if ($filter === 'active') $where .= " AND status IN ('accepted','processing')";
if ($filter === 'done')   $where .= " AND status IN ('succeeded','failed')";

$rows = DB::all("SELECT id, operation_id, kind, mode, model, prompt, status, generation_status,
                        settlement_status, net_points, requested, completed, results, error, created_at, updated_at
                 FROM tasks WHERE {$where} ORDER BY id DESC LIMIT 100",
                array(current_user()['id']));

foreach ($rows as &$r) {
    $r['results'] = $r['results'] ? json_decode($r['results'], true) : array();
}
json_response(array('ok' => true, 'tasks' => $rows));
