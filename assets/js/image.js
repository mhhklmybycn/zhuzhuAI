/**
 * 图像生成页逻辑：简单/专业模式、计费预览、任务提交
 */
(function () {
  let models = [];        // 图像模型列表
  let quoteId = '';       // 计费预览返回的报价单号
  let isPro = false;      // 当前是否为专业模式

  const $ = id => document.getElementById(id);

  /* ---------- 初始化：加载模型 ---------- */
  (async function init() {
    models = await loadModels('image');
    if (!models.length) { $('model').innerHTML = '<option value="">无可用模型</option>'; return; }
    fillModelSelect($('model'), models);
    showCaps();
  })();

  /** 显示所选模型的能力信息 */
  function showCaps() {
    const m = models[$('model').value];
    if (!m) { $('modelCaps').textContent = ''; return; }
    const c = m.caps || {};
    const parts = [];
    if (c.max_reference_images != null) parts.push('参考图上限 ' + c.max_reference_images + '（本接口限 4）');
    if (c.max_n != null) parts.push('数量上限 ' + c.max_n);
    if (c.sizes) parts.push('支持尺寸 ' + JSON.stringify(c.sizes));
    $('modelCaps').textContent = parts.join('；');
  }
  $('model').addEventListener('change', () => { showCaps(); invalidateQuote(); });

  /* ---------- 简单/专业模式切换 ---------- */
  document.querySelectorAll('.mode-tabs .tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mode-tabs .tab').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
      isPro = btn.dataset.mode === 'pro';
      $('proFields').style.display = isPro ? 'block' : 'none';
      invalidateQuote();
    });
  });

  /* ---------- 参考图预览 ---------- */
  $('references').addEventListener('change', function () {
    if (this.files.length > 4) { alert('参考图最多 4 张'); this.value = ''; }
    const pv = $('refPreview');
    pv.innerHTML = '';
    [...this.files].forEach(f => {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.className = 'preview-thumb';
      pv.appendChild(img);
    });
    invalidateQuote();
  });

  /** 参数变化后使旧报价失效 */
  function invalidateQuote() {
    quoteId = '';
    $('btnSubmit').disabled = true;
    $('billingInfo').innerHTML = '<span class="muted">参数已变化，请重新获取计费预览</span>';
  }
  ['genMode', 'prompt', 'n', 'size', 'resolution', 'quality', 'width', 'height', 'mask']
    .forEach(id => $(id) && $(id).addEventListener('change', invalidateQuote));

  /* ---------- 组装请求参数 ---------- */
  function buildPayload(forBilling) {
    const m = models[$('model').value];
    if (!m) { alert('请选择模型'); return null; }
    const p = {
      provider_id: m.provider_id,
      model: m.model_id,
      mode: $('genMode').value,
      prompt: $('prompt').value.trim(),
      n: Math.min(4, Math.max(1, parseInt($('n').value) || 1)),
      size: $('size').value
    };
    if (p.prompt.length < 2) { alert('提示词至少 2 个字符'); return null; }
    if (isPro) {
      // 专业模式附加参数
      if ($('resolution').value) p.resolution = $('resolution').value;
      if ($('quality').value.trim()) p.quality = $('quality').value.trim();
      const w = parseInt($('width').value), h = parseInt($('height').value);
      if (w > 0) p.width = w;
      if (h > 0) p.height = h;
      if (forBilling) {
        // 计费预览用 reference_image_count 表示参考图数量
        p.reference_image_count = $('references').files.length;
      }
    }
    return p;
  }

  /* ---------- 计费预览 ---------- */
  $('btnPreview').addEventListener('click', async () => {
    const p = buildPayload(true);
    if (!p) return;
    $('billingInfo').innerHTML = '<span class="muted">查询中…</span>';
    const r = await apiPost('api/billing.php', p);
    if (!r.ok) { $('billingInfo').innerHTML = '<span class="err">预览失败：' + esc(r.msg) + '</span>'; return; }
    quoteId = renderBilling($('billingInfo'), r.data);
    $('btnSubmit').disabled = false; // 拿到报价（或无需报价）后可提交
  });

  /* ---------- 提交生成 ---------- */
  $('imageForm').addEventListener('submit', async e => {
    e.preventDefault();
    const p = buildPayload(false);
    if (!p) return;
    if (quoteId) p.quote_id = quoteId; // retail_pricing 模型必须携带报价

    const fd = new FormData();
    fd.append('payload', JSON.stringify(p));
    if (isPro) {
      // 参考图：按 reference_0~3 字段上传
      [...$('references').files].slice(0, 4).forEach((f, i) => fd.append('reference_' + i, f));
      if ($('mask').files[0]) fd.append('mask', $('mask').files[0]);
    }

    $('btnSubmit').disabled = true;
    $('submitResult').innerHTML = '<span class="muted">提交中…</span>';
    try {
      const r = await fetch('api/generate_image.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        $('submitResult').innerHTML =
          '<p>✅ 任务已接受！任务号：<b>' + j.task_id + '</b>（上游 ' + esc(j.operation.id) + '）</p>' +
          '<p>状态：' + esc(j.operation.status) + '</p>' +
          '<p><a class="btn btn-sm" href="tasks.php">前往任务管理查看进度 →</a></p>';
        toast('任务提交成功');
        invalidateQuote();
      } else {
        $('submitResult').innerHTML = '<p class="err">提交失败：' + esc(j.msg) + '</p>';
        $('btnSubmit').disabled = false;
        // quote_stale 时提示重新预览
        if (j.code && j.code.indexOf('quote') >= 0) quoteId = '';
      }
    } catch (err) {
      $('submitResult').innerHTML = '<p class="err">网络错误：' + esc(err.message) + '</p>';
      $('btnSubmit').disabled = false;
    }
  });
})();
