<?php
/**
 * 视频生成页：简单模式 / 专业模式，带计费预览
 * 平台限制：所有时长上限均为 15 秒
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_user();

$navPage = 'video';
$pageTitle = '视频生成';
require '_header.php';
?>
<h2 class="page-title">视频生成</h2>

<div class="gen-layout">
  <div class="panel gen-form">
    <div class="mode-tabs">
      <button type="button" class="tab on" data-mode="simple">简单模式</button>
      <button type="button" class="tab" data-mode="pro">专业模式</button>
    </div>

    <form id="videoForm">
      <label>模型</label>
      <select id="model"></select>
      <p class="hint" id="modelCaps"></p>

      <label>提示词</label>
      <textarea id="prompt" rows="4" placeholder="描述视频内容（至少2个字符）" required></textarea>

      <div class="row">
        <div>
          <label>画面比例</label>
          <select id="aspectRatio">
            <option value="16:9">16:9（宽屏）</option>
            <option value="9:16">9:16（竖屏）</option>
            <option value="1:1">1:1（方形）</option>
            <option value="4:3">4:3</option>
            <option value="3:4">3:4</option>
          </select>
        </div>
        <div>
          <label>时长（秒，上限15秒）</label>
          <input type="number" id="duration" min="1" max="15" value="5">
        </div>
      </div>
      <div class="row">
        <div>
          <label>分辨率</label>
          <select id="resolution">
            <option value="">默认</option>
            <option value="480p">480p</option>
            <option value="720p">720p</option>
            <option value="1080p">1080p</option>
          </select>
        </div>
      </div>

      <!-- 专业模式扩展项 -->
      <div id="proFields" style="display:none">
        <div class="row">
          <div><label>首帧图片（可选）</label><input type="file" id="firstFrame" accept="image/*"></div>
          <div><label>尾帧图片（可选）</label><input type="file" id="lastFrame" accept="image/*"></div>
        </div>
        <label>参考图（2~7 张，每张 ≤12MiB）</label>
        <input type="file" id="references" accept="image/*" multiple>
        <div id="refPreview" class="preview-row"></div>

        <label>关键帧（最多 4 个，时间须严格递增且在时长内，落在 1/3 秒网格）</label>
        <div id="keyframes"></div>
        <button type="button" class="btn btn-sm" id="btnAddKf">+ 添加关键帧</button>

        <label>预设音色（最多 3 个 voice_id，对应提示词中 AUDIO_0/1/2）</label>
        <div class="row">
          <input id="voice0" placeholder="AUDIO_0 voice_id（可选）">
          <input id="voice1" placeholder="AUDIO_1 voice_id（可选）">
          <input id="voice2" placeholder="AUDIO_2 voice_id（可选）">
        </div>

        <label>分段转场（最多 19 段，累计时长 ≤15 秒，可选）</label>
        <div id="transitions"></div>
        <button type="button" class="btn btn-sm" id="btnAddTrans">+ 添加转场段</button>
      </div>

      <div class="billing-box" id="billingBox">
        <button type="button" class="btn" id="btnPreview">获取计费预览</button>
        <div id="billingInfo" class="billing-info"></div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" id="btnSubmit" disabled>提交生成</button>
      <p class="hint">提交前请先获取计费预览；部分模型（retail_pricing）必须携带报价单号。</p>
    </form>
  </div>

  <div class="panel gen-result">
    <h3>提交结果</h3>
    <div id="submitResult" class="empty">尚未提交任务</div>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script src="assets/js/video.js"></script>
</main></body></html>
