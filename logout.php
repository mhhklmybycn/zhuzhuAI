<?php
/** 前台退出登录 */
require_once __DIR__ . '/includes/bootstrap.php';
unset($_SESSION['user']);
redirect('login.php');
