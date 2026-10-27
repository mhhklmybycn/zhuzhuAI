<?php
/**
 * 后台 - 站点设置：网站名称、API密钥
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = trim($_POST['site_name']);
    $apiKey   = trim($_POST['api_key']);
    if ($siteName === '') {
        $msg = '网站名称不能为空';
    } else {
        DB::run("UPDATE settings SET site_name = ?, api_key = ?, updated_at = NOW() WHERE id = 1",
                array($siteName, $apiKey));
        $msg = '保存成功';
    }
}
$settings = DB::one("SELECT * FROM settings WHERE id = 1");

$adminPage = 'settings';
$pageTitle = '站点设置';
require '_header.php';
?>
<h2 class="page-title">站点设置</h2>
<div class="panel" style="max-width:560px">
  <?php if ($msg): ?><div class="alert <?php echo $msg==='保存成功'?'alert-ok':'alert-error'; ?>"><?php echo e($msg); ?></div><?php endif; ?>
  <form method="post">
    <label>网站名称</label>
    <input name="site_name" value="<?php echo e($settings['site_name']); ?>" required>
    <label>猪猪 AI API 密钥（X-API-Key）</label>
    <input name="api_key" value="<?php echo e($settings['api_key']); ?>" placeholder="sk-...">
    <p class="hint">密钥仅保存在本站数据库，前端所有请求由服务器代理转发，不会暴露给用户浏览器。</p>
    <button class="btn btn-primary" type="submit">保存设置</button>
  </form>
</div>
</main></body></html>
