<?php
/**
 * 后台首页：今日 / 历史模型用量记录
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$today = date('Y-m-d');

// 今日汇总
$todaySummary = DB::one(
    "SELECT COUNT(*) AS tasks, COALESCE(SUM(net_points),0) AS points,
            SUM(kind='image') AS images, SUM(kind='video') AS videos
     FROM tasks WHERE DATE(created_at) = ?", array($today));

// 今日按模型统计
$todayByModel = DB::all(
    "SELECT model, kind, COUNT(*) AS cnt, COALESCE(SUM(net_points),0) AS points
     FROM tasks WHERE DATE(created_at) = ? GROUP BY model, kind ORDER BY points DESC", array($today));

// 历史按天统计（最近30天）
$history = DB::all(
    "SELECT DATE(created_at) AS day, COUNT(*) AS tasks, COALESCE(SUM(net_points),0) AS points,
            SUM(kind='image') AS images, SUM(kind='video') AS videos
     FROM tasks GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 30");

// 历史按模型累计
$allByModel = DB::all(
    "SELECT model, kind, COUNT(*) AS cnt, COALESCE(SUM(net_points),0) AS points
     FROM tasks GROUP BY model, kind ORDER BY points DESC");

$adminPage = 'dashboard';
$pageTitle = '用量概览';
require '_header.php';
?>
<h2 class="page-title">用量概览</h2>

<!-- 今日统计卡片 -->
<div class="stat-cards">
  <div class="stat-card"><div class="num"><?php echo (int)$todaySummary['tasks']; ?></div><div class="lbl">今日任务总数</div></div>
  <div class="stat-card"><div class="num"><?php echo (int)$todaySummary['images']; ?></div><div class="lbl">今日图像任务</div></div>
  <div class="stat-card"><div class="num"><?php echo (int)$todaySummary['videos']; ?></div><div class="lbl">今日视频任务</div></div>
  <div class="stat-card"><div class="num"><?php echo fmt_points($todaySummary['points']); ?></div><div class="lbl">今日消耗积分</div></div>
</div>

<div class="panel">
  <h3>今日模型用量（<?php echo $today; ?>）</h3>
  <table class="tbl">
    <tr><th>模型</th><th>类型</th><th>任务数</th><th>消耗积分</th></tr>
    <?php foreach ($todayByModel as $r): ?>
    <tr><td><?php echo e($r['model'] ?: '（未知）'); ?></td><td><?php echo $r['kind']==='image'?'图像':'视频'; ?></td>
        <td><?php echo (int)$r['cnt']; ?></td><td><?php echo fmt_points($r['points']); ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$todayByModel): ?><tr><td colspan="4" class="empty">今日暂无记录</td></tr><?php endif; ?>
  </table>
</div>

<div class="panel">
  <h3>历史用量（按天，最近30天）</h3>
  <table class="tbl">
    <tr><th>日期</th><th>任务数</th><th>图像任务</th><th>视频任务</th><th>消耗积分</th></tr>
    <?php foreach ($history as $r): ?>
    <tr><td><?php echo e($r['day']); ?></td><td><?php echo (int)$r['tasks']; ?></td>
        <td><?php echo (int)$r['images']; ?></td><td><?php echo (int)$r['videos']; ?></td>
        <td><?php echo fmt_points($r['points']); ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$history): ?><tr><td colspan="5" class="empty">暂无历史记录</td></tr><?php endif; ?>
  </table>
</div>

<div class="panel">
  <h3>模型用量累计排行</h3>
  <table class="tbl">
    <tr><th>模型</th><th>类型</th><th>任务总数</th><th>累计消耗积分</th></tr>
    <?php foreach ($allByModel as $r): ?>
    <tr><td><?php echo e($r['model'] ?: '（未知）'); ?></td><td><?php echo $r['kind']==='image'?'图像':'视频'; ?></td>
        <td><?php echo (int)$r['cnt']; ?></td><td><?php echo fmt_points($r['points']); ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$allByModel): ?><tr><td colspan="4" class="empty">暂无记录</td></tr><?php endif; ?>
  </table>
</div>
</main></body></html>
