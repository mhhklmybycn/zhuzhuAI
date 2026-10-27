<?php
/**
 * 任务管理页：查看进行中 / 已完成的任务
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_user();

$navPage = 'tasks';
$pageTitle = '任务管理';
require '_header.php';
?>
<h2 class="page-title">任务管理</h2>

<div class="panel">
  <div class="mode-tabs">
    <button type="button" class="tab on" data-filter="active">进行中</button>
    <button type="button" class="tab" data-filter="done">已完成</button>
    <button type="button" class="tab" data-filter="all">全部</button>
    <button type="button" class="btn btn-sm" id="btnRefresh" style="margin-left:auto">刷新</button>
  </div>
  <div id="taskList"></div>
</div>

<script src="assets/js/common.js"></script>
<script src="assets/js/tasks.js"></script>
</main></body></html>
