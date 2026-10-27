<?php
/** 后台退出登录 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
unset($_SESSION['admin']);
redirect('login.php');
