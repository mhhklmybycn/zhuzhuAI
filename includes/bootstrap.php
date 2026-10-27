<?php
/**
 * 全局引导文件：所有页面/接口统一引入
 * 1. 检测安装状态 2. 载入依赖 3. 初始化会话 4. 数据库不可用时自动回退安装向导
 */

// 未安装时，除安装向导外的所有入口一律跳转安装程序
define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';

if (!is_installed()) {
    redirect_to_install();
}

// 额外容错：即使标记为已安装，尝试连接数据库；如果失败则视为安装损坏，删除 install.lock 并跳回安装向导
if (file_exists(BASE_PATH . '/config/config.php')) {
    try {
        @DB::pdo(); // 静默尝试，不抛出异常
    } catch (Throwable $e) {
        // 连不上 → 说明配置有问题（密码错、数据库没建等），回退到安装向导
        @unlink(BASE_PATH . '/install/install.lock');
        redirect_to_install();
    }
}

require_once BASE_PATH . '/includes/apiclient.php';

/** 根据当前脚本相对项目根目录的深度，计算到安装向导的相对路径并跳转 */
function redirect_to_install()
{
    $scriptDir = dirname(str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'])));
    $baseDir   = str_replace('\\', '/', BASE_PATH);
    $depth     = substr_count(trim(str_replace($baseDir, '', $scriptDir), '/'), '/') + (rtrim(str_replace($baseDir, '', $scriptDir), '/') !== '' ? 1 : 0);
    redirect(str_repeat('../', $depth) . 'install/index.php');
}
