<?php
/**
 * 后台 - 前端用户管理：添加 / 删除 / 启用禁用 / 重置密码
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$msg = '';
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($act === 'add') {
        // 添加用户
        $u = trim($_POST['username']);
        $p = (string)$_POST['password'];
        if (mb_strlen($u) < 3)      $msg = '用户名至少 3 个字符';
        elseif (strlen($p) < 6)     $msg = '密码至少 6 位';
        elseif (DB::one("SELECT id FROM users WHERE username = ?", array($u))) $msg = '用户名已存在';
        else {
            DB::insert("INSERT INTO users (username, password_hash, status, created_at) VALUES (?, ?, 1, NOW())",
                       array($u, password_hash($p, PASSWORD_DEFAULT)));
            $msg = '用户添加成功';
        }
    } elseif ($act === 'reset') {
        // 重置密码
        $id = (int)$_POST['id'];
        $p  = (string)$_POST['password'];
        if (strlen($p) < 6) $msg = '密码至少 6 位';
        else {
            DB::run("UPDATE users SET password_hash = ? WHERE id = ?", array(password_hash($p, PASSWORD_DEFAULT), $id));
            $msg = '密码已重置';
        }
    } elseif ($act === 'toggle') {
        // 启用 / 禁用
        DB::run("UPDATE users SET status = 1 - status WHERE id = ?", array((int)$_POST['id']));
        $msg = '状态已更新';
    }
} elseif ($act === 'del') {
    // 删除用户（GET + 确认）
    DB::run("DELETE FROM users WHERE id = ?", array((int)$_GET['id']));
    redirect('users.php?msg=' . urlencode('用户已删除'));
}
if (isset($_GET['msg'])) $msg = $_GET['msg'];

$users = DB::all("SELECT u.*, (SELECT COUNT(*) FROM tasks t WHERE t.user_id = u.id) AS task_cnt
                  FROM users u ORDER BY u.id DESC");

$adminPage = 'users';
$pageTitle = '用户管理';
require '_header.php';
?>
<h2 class="page-title">前端用户管理</h2>

<div class="panel" style="max-width:560px">
  <h3>添加用户</h3>
  <?php if ($msg): ?><div class="alert <?php echo mb_strpos($msg,'成功')!==false||mb_strpos($msg,'已')!==false?'alert-ok':'alert-error'; ?>"><?php echo e($msg); ?></div><?php endif; ?>
  <form method="post" action="?act=add" class="inline-form">
    <input name="username" placeholder="用户名（≥3字符）" required>
    <input type="password" name="password" placeholder="密码（≥6位）" required>
    <button class="btn btn-primary" type="submit">添加</button>
  </form>
</div>

<div class="panel">
  <h3>用户列表（<?php echo count($users); ?>）</h3>
  <table class="tbl">
    <tr><th>ID</th><th>用户名</th><th>状态</th><th>任务数</th><th>注册时间</th><th>操作</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?php echo (int)$u['id']; ?></td>
      <td><?php echo e($u['username']); ?></td>
      <td><span class="tag <?php echo $u['status']?'tag-green':'tag-red'; ?>"><?php echo $u['status']?'正常':'禁用'; ?></span></td>
      <td><?php echo (int)$u['task_cnt']; ?></td>
      <td><?php echo e($u['created_at']); ?></td>
      <td>
        <form method="post" action="?act=toggle" style="display:inline"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
          <button class="btn btn-sm" type="submit"><?php echo $u['status']?'禁用':'启用'; ?></button></form>
        <button class="btn btn-sm" onclick="resetPwd(<?php echo (int)$u['id']; ?>)">重置密码</button>
        <a class="btn btn-sm btn-danger" href="?act=del&id=<?php echo (int)$u['id']; ?>"
           onclick="return confirm('确定删除该用户？其历史任务记录将保留。')">删除</a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="6" class="empty">暂无用户</td></tr><?php endif; ?>
  </table>
</div>

<script>
// 重置密码弹窗
function resetPwd(id){
  var p = prompt('请输入新密码（至少6位）：');
  if(!p) return;
  if(p.length < 6){ alert('密码至少6位'); return; }
  var f = document.createElement('form');
  f.method = 'post'; f.action = '?act=reset';
  f.innerHTML = '<input type="hidden" name="id" value="'+id+'"><input type="hidden" name="password" value="'+p.replace(/"/g,'&quot;')+'">';
  document.body.appendChild(f); f.submit();
}
</script>
</main></body></html>
