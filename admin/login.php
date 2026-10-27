<?php
/**
 * 后台管理员登录
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// 已登录直接进入后台
if (current_admin()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username']);
    $p = (string)$_POST['password'];
    $row = DB::one("SELECT * FROM admins WHERE username = ?", array($u));
    if ($row && password_verify($p, $row['password_hash'])) {
        $_SESSION['admin'] = array('id' => $row['id'], 'username' => $row['username']);
        redirect('index.php');
    }
    $error = '账号或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>管理员登录 - <?php echo e(site_settings()['site_name']); ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="login-page">
<div class="login-card">
  <h1>管理后台</h1>
  <p class="sub"><?php echo e(site_settings()['site_name']); ?></p>
  <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <form method="post">
    <label>管理员账号</label>
    <input name="username" required autocomplete="username">
    <label>密码</label>
    <input type="password" name="password" required autocomplete="current-password">
    <button class="btn btn-primary btn-block" type="submit">登 录</button>
  </form>
  <p style="margin-top:16px;font-size:13px"><a href="../index.php">← 返回前台</a></p>
</div>
</body>
</html>
