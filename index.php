<?php
/**
 * 前台首页：模型列表与能力信息
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_user();

$api = new PigApi();
$resp = $api->getModels();
$modelsOk = (isset($resp['__http']) && $resp['__http'] === 200 && isset($resp['data']));
$imageModels = $modelsOk && isset($resp['data']['models']) ? $resp['data']['models'] : array();
$videoModels = $modelsOk && isset($resp['data']['video_models']) ? $resp['data']['video_models'] : array();
$apiError = $modelsOk ? '' : (isset($resp['message']) ? $resp['message'] : '模型列表获取失败');

/** 渲染单个模型能力详情（键值对） */
function renderCaps($m) {
    foreach ($m as $k => $v) {
        if (is_array($v)) { $v = json_encode($v, JSON_UNESCAPED_UNICODE); }
        if (is_bool($v))  { $v = $v ? '支持' : '不支持'; }
        echo '<div class="cap-row"><span class="cap-k">' . e($k) . '</span><span class="cap-v">' . e($v) . '</span></div>';
    }
}

$navPage = 'home';
$pageTitle = '模型列表';
require '_header.php';
?>
<h2 class="page-title">模型列表与能力信息</h2>
<?php if ($apiError): ?>
  <div class="alert alert-error">API 调用失败：<?php echo e($apiError); ?>（请检查后台 API 密钥配置）</div>
<?php endif; ?>

<div class="panel">
  <h3>🖼 图像模型（<?php echo count($imageModels); ?> 个提供商）</h3>
  <?php foreach ($imageModels as $p): ?>
  <div class="provider">
    <div class="provider-head">
      <span class="provider-name"><?php echo e(isset($p['name'])?$p['name']:$p['id']); ?></span>
      <span class="tag <?php echo !empty($p['enabled'])?'tag-green':'tag-red'; ?>"><?php echo !empty($p['enabled'])?'已启用':'未启用'; ?></span>
      <span class="muted"><?php echo e($p['id']); ?></span>
    </div>
    <?php if (!empty($p['models'])): foreach ($p['models'] as $m): ?>
    <details class="model-item">
      <summary><?php echo e(isset($m['id'])?$m['id']:(isset($m['name'])?$m['name']:'model')); ?>
        <?php if (isset($m['name'])): ?><span class="muted">— <?php echo e($m['name']); ?></span><?php endif; ?></summary>
      <div class="model-caps"><?php renderCaps($m); ?></div>
    </details>
    <?php endforeach; endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$imageModels && $modelsOk): ?><p class="empty">暂无图像模型</p><?php endif; ?>
</div>

<div class="panel">
  <h3>🎬 视频模型（<?php echo count($videoModels); ?> 个提供商）</h3>
  <?php foreach ($videoModels as $p): ?>
  <div class="provider">
    <div class="provider-head">
      <span class="provider-name"><?php echo e(isset($p['name'])?$p['name']:$p['id']); ?></span>
      <span class="tag <?php echo !empty($p['enabled'])?'tag-green':'tag-red'; ?>"><?php echo !empty($p['enabled'])?'已启用':'未启用'; ?></span>
      <span class="muted"><?php echo e($p['id']); ?></span>
    </div>
    <?php if (!empty($p['models'])): foreach ($p['models'] as $m): ?>
    <details class="model-item">
      <summary><?php echo e(isset($m['id'])?$m['id']:(isset($m['name'])?$m['name']:'model')); ?>
        <?php if (isset($m['name'])): ?><span class="muted">— <?php echo e($m['name']); ?></span><?php endif; ?></summary>
      <div class="model-caps"><?php renderCaps($m); ?></div>
    </details>
    <?php endforeach; endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if (!$videoModels && $modelsOk): ?><p class="empty">暂无视频模型</p><?php endif; ?>
</div>
</main></body></html>
