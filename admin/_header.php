<?php
/** 后台公共头部（导航栏），需在 require_admin() 之后引入 */
$adminPage = isset($adminPage) ? $adminPage : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?>管理后台 - <?php echo e(site_settings()['site_name']); ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="topbar">
  <div class="brand">⚙ <?php echo e(site_settings()['site_name']); ?> · 管理后台</div>
  <div class="nav-links">
    <a href="index.php"    class="<?php echo $adminPage==='dashboard'?'on':''; ?>">用量概览</a>
    <a href="users.php"    class="<?php echo $adminPage==='users'?'on':''; ?>">用户管理</a>
    <a href="settings.php" class="<?php echo $adminPage==='settings'?'on':''; ?>">站点设置</a>
    <a href="../index.php" target="_blank">前台首页</a>
    <a href="logout.php">退出（<?php echo e(current_admin()['username']); ?>）</a>
  </div>
</nav>
<main class="container">
