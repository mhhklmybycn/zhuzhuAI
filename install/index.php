<?php
/**
 * 安装向导
 * 步骤1：环境检测 → 步骤2：填写数据库/站点/管理员/API密钥 → 步骤3：执行安装
 * 安装成功后生成 config/config.php 与 install/install.lock，此后本向导自动失效
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
session_start();

$basePath = dirname(__DIR__);
$lockFile = $basePath . '/install/install.lock';
$confFile = $basePath . '/config/config.php';

// 已安装且配置有效则禁止再次进入安装向导（损坏的配置会被 bootstrap 删除锁文件后跳回这里）
if (file_exists($lockFile) && file_exists($confFile)) {
    // 额外检测：尝试连接数据库，成功才视为"真正已安装"
    $cfg = @require $confFile;
    $dbOk = false;
    if (is_array($cfg) && !empty($cfg['db'])) {
        try {
            @new PDO("mysql:host={$cfg['db']['host']};port={$cfg['db']['port']};charset=utf8mb4",
                     $cfg['db']['user'], $cfg['db']['pass'] ?? '',
                     array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $dbOk = true;
        } catch (Throwable $e) { $dbOk = false; }
    }
    if ($dbOk) { header('Location: ../index.php'); exit; }
    // 配置损坏但锁文件还在 → 允许重装（向导内的 step=3 会重建所有表和数据）
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = array();
$ok = false;

/* ============ 步骤1：环境检测 ============ */
// 检测项：名称 => array(是否通过, 提示)
$checks = array();
$checks['PHP 版本 ≥ 7.4（当前 ' . PHP_VERSION . '）'] = array(version_compare(PHP_VERSION, '7.4.0', '>='), '请升级 PHP');
$checks['PDO 扩展']      = array(extension_loaded('pdo'), '请启用 pdo 扩展');
$checks['PDO MySQL 驱动'] = array(extension_loaded('pdo_mysql'), '请启用 pdo_mysql 扩展');
$checks['cURL 扩展']     = array(extension_loaded('curl'), '请启用 curl 扩展（调用上游API必需）');
$checks['JSON 扩展']     = array(extension_loaded('json'), '请启用 json 扩展');
$checks['mbstring 扩展'] = array(extension_loaded('mbstring'), '请启用 mbstring 扩展');
$checks['fileinfo 扩展'] = array(extension_loaded('fileinfo'), '请启用 fileinfo 扩展（文件类型识别）');
$checks['config 目录可写']  = array(is_writable($basePath . '/config'), '请给 config 目录写入权限');
$checks['install 目录可写'] = array(is_writable($basePath . '/install'), '请给 install 目录写入权限');
$checks['storage 目录可写'] = array(is_writable($basePath . '/storage'), '请给 storage 目录写入权限');
$envPass = true;
foreach ($checks as $c) { if (!$c[0]) { $envPass = false; break; } }

/* ============ 步骤3：执行安装 ============ */
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 收集并校验表单
    $dbHost   = trim($_POST['db_host']);
    $dbPort   = (int)trim($_POST['db_port']);
    $dbName   = trim($_POST['db_name']);
    $dbUser   = trim($_POST['db_user']);
    $dbPass   = (string)$_POST['db_pass'];
    $siteName = trim($_POST['site_name']);
    $admUser  = trim($_POST['admin_user']);
    $admPass  = (string)$_POST['admin_pass'];
    $admPass2 = (string)$_POST['admin_pass2'];
    $apiKey   = trim($_POST['api_key']);

    if ($dbHost === '' || $dbPort <= 0 || $dbName === '' || $dbUser === '') $errors[] = '数据库信息填写不完整';
    if ($siteName === '') $errors[] = '请填写网站名称';
    if (mb_strlen($admUser) < 3) $errors[] = '管理员账号至少 3 个字符';
    if (strlen($admPass) < 6)  $errors[] = '管理员密码至少 6 位';
    if ($admPass !== $admPass2) $errors[] = '两次输入的管理员密码不一致';
    if ($apiKey === '') $errors[] = '请填写 API 密钥（X-API-Key）';

    if (!$errors) {
        // 1. 连接数据库（不存在则自动创建）
        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (Exception $ex) {
            $errors[] = '数据库连接失败：' . $ex->getMessage();
        }
    }

    if (!$errors) {
        // 2. 建表
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT PRIMARY KEY DEFAULT 1,
  site_name VARCHAR(100) NOT NULL DEFAULT 'AI 创作平台',
  api_key   VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  status TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  operation_id VARCHAR(64) NOT NULL DEFAULT '' COMMENT '上游任务ID',
  kind ENUM('image','video') NOT NULL,
  mode VARCHAR(40) NOT NULL DEFAULT '',
  model VARCHAR(160) NOT NULL DEFAULT '',
  provider_id VARCHAR(120) NOT NULL DEFAULT '',
  prompt TEXT,
  params TEXT COMMENT '请求参数JSON',
  idempotency_key VARCHAR(128) NOT NULL DEFAULT '',
  status VARCHAR(20) NOT NULL DEFAULT 'accepted' COMMENT 'accepted/processing/succeeded/failed',
  generation_status VARCHAR(40) NOT NULL DEFAULT '',
  settlement_status VARCHAR(20) NOT NULL DEFAULT '',
  charge_points INT NOT NULL DEFAULT 0,
  refund_points INT NOT NULL DEFAULT 0,
  net_points INT NOT NULL DEFAULT 0,
  requested INT NOT NULL DEFAULT 0,
  completed INT NOT NULL DEFAULT 0,
  results TEXT COMMENT '结果列表JSON',
  local_files TEXT COMMENT '已下载到本地的结果JSON',
  error VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_user (user_id),
  INDEX idx_status (status),
  INDEX idx_created (created_at),
  INDEX idx_model (model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  task_id INT NOT NULL DEFAULT 0,
  kind ENUM('image','video') NOT NULL,
  model VARCHAR(160) NOT NULL DEFAULT '',
  points INT NOT NULL DEFAULT 0 COMMENT '净消耗积分',
  status VARCHAR(20) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX idx_created (created_at),
  INDEX idx_model (model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
        try {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            // 3. 写入初始数据
            $now = date('Y-m-d H:i:s');
            $st = $pdo->prepare("INSERT INTO settings (id, site_name, api_key, created_at, updated_at)
                                 VALUES (1, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE site_name=VALUES(site_name), api_key=VALUES(api_key), updated_at=VALUES(updated_at)");
            $st->execute(array($siteName, $apiKey, $now, $now));
            $st = $pdo->prepare("INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, ?)");
            $st->execute(array($admUser, password_hash($admPass, PASSWORD_DEFAULT), $now));

            // 4. 写入配置文件与安装锁
            $conf = "<?php\n// 本文件由安装向导生成，请勿手动修改数据库配置\nreturn " . var_export(array(
                'db' => array(
                    'host' => $dbHost, 'port' => $dbPort, 'name' => $dbName,
                    'user' => $dbUser, 'pass' => $dbPass,
                ),
            ), true) . ";\n";
            file_put_contents($confFile, $conf);
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $ok = true;
        } catch (Exception $ex) {
            $errors[] = '安装失败：' . $ex->getMessage();
        }
    }
    if ($errors) { $step = 2; } // 失败回到表单页
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装向导 - AI 创作平台</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:"Microsoft YaHei",sans-serif;background:#f0f2f5;color:#333;display:flex;justify-content:center;padding:40px 16px}
  .box{background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);width:640px;padding:32px}
  h1{font-size:22px;margin-bottom:6px}
  .sub{color:#888;font-size:13px;margin-bottom:24px}
  .steps{display:flex;gap:8px;margin-bottom:24px}
  .steps span{flex:1;text-align:center;padding:8px 0;border-radius:6px;background:#f0f2f5;font-size:13px;color:#999}
  .steps span.on{background:#4f7cff;color:#fff}
  table{width:100%;border-collapse:collapse;font-size:14px}
  td,th{padding:10px;border-bottom:1px solid #f0f0f0;text-align:left}
  .pass{color:#18a058;font-weight:700}.fail{color:#d03050;font-weight:700}
  label{display:block;margin:14px 0 6px;font-size:14px;font-weight:600}
  input{width:100%;padding:10px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px}
  .btn{display:inline-block;margin-top:24px;padding:10px 32px;border:none;border-radius:6px;background:#4f7cff;color:#fff;font-size:15px;cursor:pointer;text-decoration:none}
  .btn.dis{background:#c0c4cc;pointer-events:none}
  .alert{background:#fff1f0;border:1px solid #ffa39e;color:#d03050;border-radius:6px;padding:12px 16px;margin-bottom:16px;font-size:14px}
  .okbox{text-align:center;padding:30px 0}
  .okbox .icon{font-size:52px;color:#18a058}
  fieldset{border:1px solid #eee;border-radius:8px;padding:16px;margin-bottom:16px}
  legend{font-size:14px;font-weight:700;padding:0 8px}
</style>
</head>
<body>
<div class="box">
  <h1>AI 创作平台 · 安装向导</h1>
  <div class="sub">猪猪 AI 统一图像与视频 API 调用平台</div>
  <div class="steps">
    <span class="<?php echo $step===1?'on':''; ?>">1 环境检测</span>
    <span class="<?php echo $step===2?'on':''; ?>">2 信息配置</span>
    <span class="<?php echo $step===3?'on':''; ?>">3 完成安装</span>
  </div>

  <?php if ($step === 1): ?>
    <!-- 步骤1：环境检测 -->
    <table>
      <tr><th>检测项</th><th style="width:90px">结果</th></tr>
      <?php foreach ($checks as $name => $c): ?>
      <tr>
        <td><?php echo htmlspecialchars($name); ?><?php if(!$c[0]) echo ' <span class="fail">（'.$c[1].'）</span>'; ?></td>
        <td class="<?php echo $c[0]?'pass':'fail'; ?>"><?php echo $c[0]?'通过':'未通过'; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <a class="btn <?php echo $envPass?'':'dis'; ?>" href="?step=2">下一步</a>

  <?php elseif ($step === 2): ?>
    <!-- 步骤2：信息配置 -->
    <?php foreach ($errors as $err): ?><div class="alert"><?php echo htmlspecialchars($err); ?></div><?php endforeach; ?>
    <form method="post" action="?step=3">
      <fieldset><legend>数据库信息</legend>
        <label>数据库主机</label><input name="db_host" value="<?php echo htmlspecialchars(isset($_POST['db_host'])?$_POST['db_host']:'127.0.0.1'); ?>" required>
        <label>端口</label><input name="db_port" value="<?php echo htmlspecialchars(isset($_POST['db_port'])?$_POST['db_port']:'3306'); ?>" required>
        <label>数据库名（不存在将自动创建）</label><input name="db_name" value="<?php echo htmlspecialchars(isset($_POST['db_name'])?$_POST['db_name']:'ai_platform'); ?>" required>
        <label>数据库用户名</label><input name="db_user" value="<?php echo htmlspecialchars(isset($_POST['db_user'])?$_POST['db_user']:'root'); ?>" required>
        <label>数据库密码</label><input type="password" name="db_pass" value="<?php echo htmlspecialchars(isset($_POST['db_pass'])?$_POST['db_pass']:''); ?>">
      </fieldset>
      <fieldset><legend>站点信息</legend>
        <label>网站名称</label><input name="site_name" value="<?php echo htmlspecialchars(isset($_POST['site_name'])?$_POST['site_name']:'AI 创作平台'); ?>" required>
      </fieldset>
      <fieldset><legend>管理员账号</legend>
        <label>管理员账号</label><input name="admin_user" value="<?php echo htmlspecialchars(isset($_POST['admin_user'])?$_POST['admin_user']:''); ?>" required>
        <label>管理员密码（≥6位）</label><input type="password" name="admin_pass" required>
        <label>确认密码</label><input type="password" name="admin_pass2" required>
      </fieldset>
      <fieldset><legend>API 配置</legend>
        <label>猪猪 AI API 密钥（X-API-Key）</label><input name="api_key" value="<?php echo htmlspecialchars(isset($_POST['api_key'])?$_POST['api_key']:''); ?>" required>
      </fieldset>
      <button class="btn" type="submit">开始安装</button>
    </form>

  <?php else: ?>
    <!-- 步骤3：安装结果 -->
    <?php if (!$ok): ?>
    <div class="alert">非法访问，请从第一步开始。</div>
    <a class="btn" href="?step=1">返回环境检测</a>
    <?php endif; ?>
    <?php if ($ok): ?>
    <div class="okbox">
      <div class="icon">✔</div>
      <h2 style="margin:12px 0">安装成功！</h2>
      <p style="color:#888">为了安全，请删除或限制访问 install 目录。</p>
      <a class="btn" href="../index.php">进入前台首页</a>
      <a class="btn" style="background:#18a058" href="../admin/login.php">进入管理后台</a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
