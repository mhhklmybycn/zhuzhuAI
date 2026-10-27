<?php
/**
 * 前台用户登录
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username']);
    $p = (string)$_POST['password'];
    $row = DB::one("SELECT * FROM users WHERE username = ?", array($u));
    if ($row && $row['status'] == 1 && password_verify($p, $row['password_hash'])) {
        $_SESSION['user'] = array('id' => $row['id'], 'username' => $row['username']);
        redirect('index.php');
    }
    $error = $row && $row['status'] == 0 ? '账号已被禁用，请联系管理员' : '账号或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>登录 - <?php echo e(site_settings()['site_name']); ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
<div class="login-card">
  <h1><?php echo e(site_settings()['site_name']); ?></h1>
  <p class="sub">请使用管理员分配的账号登录</p>
  <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <form method="post">
    <label>账号</label>
    <input name="username" required autocomplete="username">
    <label>密码</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button class="btn btn-primary btn-block" type="submit">登 录</button>
  </form>
</div>
</body>
</html>
