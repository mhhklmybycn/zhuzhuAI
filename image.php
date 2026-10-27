<?php
/**
 * 图像生成页：简单模式 / 专业模式，带计费预览
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_user();

$navPage = 'image';
$pageTitle = '图像生成';
require '_header.php';
?>
<h2 class="page-title">图像生成</h2>

<div class="gen-layout">
  <div class="panel gen-form">
    <!-- 模式切换 -->
    <div class="mode-tabs">
      <button type="button" class="tab on" data-mode="simple">简单模式</button>
      <button type="button" class="tab" data-mode="pro">专业模式</button>
    </div>

    <form id="imageForm">
      <label>模型</label>
      <select id="model"></select>
      <p class="hint" id="modelCaps"></p>

      <label>生成模式</label>
      <select id="genMode">
        <option value="text_to_image">文生图</option>
        <option value="image_to_image">图生图</option>
      </select>

      <label>提示词</label>
      <textarea id="prompt" rows="4" placeholder="描述你想要生成的画面（至少2个字符）" required></textarea>

      <div class="row">
        <div>
          <label>图片数量（1~4）</label>
          <input type="number" id="n" min="1" max="4" value="1">
        </div>
        <div>
          <label>图片比例</label>
          <select id="size">
            <option value="1:1">1:1（方形）</option>
            <option value="4:3">4:3（横版）</option>
            <option value="3:4">3:4（竖版）</option>
            <option value="16:9">16:9（宽屏）</option>
            <option value="9:16">9:16（竖屏）</option>
            <option value="3:2">3:2</option>
            <option value="2:3">2:3</option>
          </select>
        </div>
      </div>

      <!-- 专业模式扩展项 -->
      <div id="proFields" style="display:none">
        <div class="row">
          <div>
            <label>分辨率档位（可选）</label>
            <select id="resolution">
              <option value="">默认</option>
              <option value="1K">1K</option>
              <option value="2K">2K</option>
              <option value="4K">4K</option>
            </select>
          </div>
          <div>
            <label>质量（可选）</label>
            <input id="quality" placeholder="如 high / standard">
          </div>
        </div>
        <div class="row">
          <div><label>自定义宽度（可选）</label><input type="number" id="width" min="0" placeholder="px"></div>
          <div><label>自定义高度（可选）</label><input type="number" id="height" min="0" placeholder="px"></div>
        </div>
        <label>参考图（最多 4 张，每张 ≤12MiB）</label>
        <input type="file" id="references" accept="image/*" multiple>
        <div id="refPreview" class="preview-row"></div>
        <label>蒙版（可选，≤4MiB）</label>
        <input type="file" id="mask" accept="image/*">
      </div>

      <!-- 计费预览 -->
      <div class="billing-box" id="billingBox">
        <button type="button" class="btn" id="btnPreview">获取计费预览</button>
        <div id="billingInfo" class="billing-info"></div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" id="btnSubmit" disabled>提交生成</button>
      <p class="hint">提交前请先获取计费预览；部分模型（retail_pricing）必须携带报价单号。</p>
    </form>
  </div>

  <!-- 提交结果 -->
  <div class="panel gen-result">
    <h3>提交结果</h3>
    <div id="submitResult" class="empty">尚未提交任务</div>
  </div>
</div>

<script src="assets/js/common.js"></script>
<script src="assets/js/image.js"></script>
</main></body></html>
