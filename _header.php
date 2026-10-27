<?php
/** 前台公共头部（导航栏），需在 require_user() 之后引入 */
$navPage = isset($navPage) ? $navPage : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e(site_settings()['site_name']); ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="topbar">
  <div class="brand">🎨 <?php echo e(site_settings()['site_name']); ?></div>
  <div class="nav-links">
    <a href="index.php" class="<?php echo $navPage==='home'?'on':''; ?>">模型列表</a>
    <a href="image.php" class="<?php echo $navPage==='image'?'on':''; ?>">图像生成</a>
    <a href="video.php" class="<?php echo $navPage==='video'?'on':''; ?>">视频生成</a>
    <a href="tasks.php" class="<?php echo $navPage==='tasks'?'on':''; ?>">任务管理</a>
    <a href="logout.php">退出（<?php echo e(current_user()['username']); ?>）</a>
  </div>
</nav>
<main class="container">
