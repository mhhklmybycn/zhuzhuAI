<?php
/**
 * 公共函数库：会话、权限、输出、配置等
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** 判断是否已安装（安装完成后会生成 config/config.php 与 install.lock） */
function is_installed()
{
    return file_exists(dirname(__DIR__) . '/config/config.php')
        && file_exists(dirname(__DIR__) . '/install/install.lock');
}

/** 获取站点配置（settings 表，单行） */
function site_settings()
{
    static $cache = null;
    if ($cache === null) {
        $cache = DB::one("SELECT * FROM settings WHERE id = 1");
        if (!$cache) {
            $cache = array('site_name' => 'AI 创作平台', 'api_key' => '');
        }
    }
    return $cache;
}

/** HTML 转义输出，防 XSS */
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** 跳转并终止脚本 */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** 输出 JSON 并终止脚本（供 Ajax 接口使用） */
function json_response($data, $httpCode = 200)
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- 前台用户会话 ---------------- */

/** 当前登录的前台用户，未登录返回 null */
function current_user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/** 要求前台用户已登录，否则跳登录页（页面）或返回401（接口） */
function require_user($isApi = false)
{
    if (!current_user()) {
        if ($isApi) {
            json_response(array('ok' => false, 'msg' => '未登录或会话已过期'), 401);
        }
        redirect('login.php');
    }
}

/* ---------------- 后台管理员会话 ---------------- */

/** 当前登录的管理员，未登录返回 null */
function current_admin()
{
    return isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
}

/** 要求管理员已登录 */
function require_admin()
{
    if (!current_admin()) {
        redirect('login.php');
    }
}

/** 生成幂等键（Idempotency-Key，8~128位） */
function gen_idempotency_key()
{
    return 'ik-' . bin2hex(random_bytes(16));
}

/** 格式化积分显示 */
function fmt_points($n)
{
    return number_format((int)$n);
}
