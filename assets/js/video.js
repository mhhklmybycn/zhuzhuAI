/**
 * 视频生成页逻辑：简单/专业模式、关键帧/转场动态行、计费预览、任务提交
 * 平台限制：所有时长上限 15 秒
 */
(function () {
  let models = [];
  let quoteId = '';
  let isPro = false;
  const MAX_DURATION = 15; // 时长上限（秒）

  const $ = id => document.getElementById(id);

  /* ---------- 初始化 ---------- */
  (async function init() {
    models = await loadModels('video');
    if (!models.length) { $('model').innerHTML = '<option value="">无可用模型</option>'; return; }
    fillModelSelect($('model'), models);
    showCaps();
  })();

  function showCaps() {
    const m = models[$('model').value];
    if (!m) { $('modelCaps').textContent = ''; return; }
    const c = m.caps || {};
    const parts = [];
    if (c.durations) parts.push('支持时长 ' + JSON.stringify(c.durations));
    if (c.resolutions) parts.push('支持分辨率 ' + JSON.stringify(c.resolutions));
    if (c.aspect_ratios) parts.push('支持比例 ' + JSON.stringify(c.aspect_ratios));
    $('modelCaps').textContent = parts.join('；');
  }
  $('model').addEventListener('change', () => { showCaps(); invalidateQuote(); });

  /* ---------- 模式切换 ---------- */
  document.querySelectorAll('.mode-tabs .tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mode-tabs .tab').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
      isPro = btn.dataset.mode === 'pro';
      $('proFields').style.display = isPro ? 'block' : 'none';
      invalidateQuote();
    });
  });

  /* ---------- 参考图预览（2~7张） ---------- */
  $('references').addEventListener('change', function () {
    if (this.files.length > 7) { alert('视频参考图最多 7 张'); this.value = ''; return; }
    if (this.files.length === 1) { alert('视频参考图至少需要 2 张（或不上传）'); this.value = ''; return; }
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

  /* ---------- 关键帧动态行（最多4个） ---------- */
  let kfCount = 0;
  $('btnAddKf').addEventListener('click', () => {
    if (kfCount >= 4) { alert('最多 4 个关键帧'); return; }
    const idx = kfCount++;
    const div = document.createElement('div');
    div.className = 'dyn-row';
    div.dataset.kf = idx;
    div.innerHTML =
      `<input type="file" accept="image/*" class="kf-file">` +
      `<input type="number" class="kf-time" min="0" max="${MAX_DURATION}" step="0.333" placeholder="时间点(秒)" style="width:130px">` +
      `<button type="button" class="btn btn-sm btn-danger" onclick="this.parentNode.remove()">删除</button>`;
    $('keyframes').appendChild(div);
    invalidateQuote();
  });

  /* ---------- 转场动态行（最多19段，累计≤15秒） ---------- */
  let transCount = 0;
  $('btnAddTrans').addEventListener('click', () => {
    if (transCount >= 19) { alert('最多 19 段转场'); return; }
    transCount++;
    const div = document.createElement('div');
    div.className = 'dyn-row';
    div.innerHTML =
      `<input class="trans-prompt" placeholder="转场提示词" style="flex:1">` +
      `<input type="number" class="trans-duration" min="0" max="${MAX_DURATION}" step="0.1" placeholder="时长(秒)" style="width:110px">` +
      `<button type="button" class="btn btn-sm btn-danger" onclick="this.parentNode.remove()">删除</button>`;
    $('transitions').appendChild(div);
    invalidateQuote();
  });

  /** 参数变化后使报价失效 */
  function invalidateQuote() {
    quoteId = '';
    $('btnSubmit').disabled = true;
    $('billingInfo').innerHTML = '<span class="muted">参数已变化，请重新获取计费预览</span>';
  }
  ['prompt', 'aspectRatio', 'duration', 'resolution', 'firstFrame', 'lastFrame', 'voice0', 'voice1', 'voice2']
    .forEach(id => $(id) && $(id).addEventListener('change', invalidateQuote));

  /* ---------- 组装请求参数 ---------- */
  function buildPayload(forBilling) {
    const m = models[$('model').value];
    if (!m) { alert('请选择模型'); return null; }
    const dur = parseInt($('duration').value) || 0;
    if (dur <= 0) { alert('请填写时长'); return null; }
    if (dur > MAX_DURATION) { alert('视频时长上限为 ' + MAX_DURATION + ' 秒'); return null; }

    const p = {
      provider_id: m.provider_id,
      model: m.model_id,
      prompt: $('prompt').value.trim(),
      aspect_ratio: $('aspectRatio').value,
      duration: dur
    };
    if (p.prompt.length < 2) { alert('提示词至少 2 个字符'); return null; }
    if ($('resolution').value) p.resolution = $('resolution').value;

    // 计费预览需要 mode 与 n（视频固定 n=1）
    if (forBilling) {
      const hasRef = isPro && $('references').files.length > 0;
      const hasFrame = isPro && ($('firstFrame').files[0] || $('lastFrame').files[0]);
      p.mode = hasRef ? 'reference_to_video' : (hasFrame ? 'image_to_video' : 'text_to_video');
      p.n = 1;
    }

    if (isPro) {
      // 参考图数量（计费预览用）
      if (forBilling) p.reference_image_count = $('references').files.length;
      // 关键帧时间（文件在提交时附加）；每行必须同时有文件和时间
      const kfs = [];
      let kfBad = false;
      document.querySelectorAll('#keyframes .dyn-row').forEach(row => {
        const hasFile = !!row.querySelector('.kf-file').files[0];
        const t = parseFloat(row.querySelector('.kf-time').value);
        if (hasFile !== undefined && (hasFile !== !isNaN(t))) kfBad = true; // 文件与时间必须成对
        if (!isNaN(t)) {
          if (t >= dur) { alert('关键帧时间必须在视频时长内'); throw 'kf'; }
          kfs.push({ timestamp_s: t });
        }
      });
      if (kfBad) { alert('每个关键帧需要同时选择图片并填写时间点'); return null; }
      if (kfs.length) {
        // 校验严格递增
        for (let i = 1; i < kfs.length; i++) {
          if (kfs[i].timestamp_s <= kfs[i-1].timestamp_s) { alert('关键帧时间必须严格递增'); return null; }
        }
        p.keyframes = kfs;
      }
      // 预设音色 AUDIO_0/1/2
      const voices = [$('voice0').value.trim(), $('voice1').value.trim(), $('voice2').value.trim()].filter(v => v);
      if (voices.length) p.reference_audios = voices.map(v => ({ voice_id: v }));
      // 分段转场
      const trans = [];
      let totalTrans = 0;
      document.querySelectorAll('#transitions .dyn-row').forEach(row => {
        const tp = row.querySelector('.trans-prompt').value.trim();
        const td = parseFloat(row.querySelector('.trans-duration').value);
        if (tp && !isNaN(td)) { trans.push({ prompt: tp, duration: td }); totalTrans += td; }
      });
      if (totalTrans > MAX_DURATION) { alert('转场累计时长不能超过 ' + MAX_DURATION + ' 秒'); return null; }
      if (trans.length) p.transitions = trans;
    }
    return p;
  }

  /* ---------- 计费预览 ---------- */
  $('btnPreview').addEventListener('click', async () => {
    let p;
    try { p = buildPayload(true); } catch (e) { return; }
    if (!p) return;
    $('billingInfo').innerHTML = '<span class="muted">查询中…</span>';
    const r = await apiPost('api/billing.php', p);
    if (!r.ok) { $('billingInfo').innerHTML = '<span class="err">预览失败：' + esc(r.msg) + '</span>'; return; }
    quoteId = renderBilling($('billingInfo'), r.data);
    $('btnSubmit').disabled = false;
  });

  /* ---------- 提交生成 ---------- */
  $('videoForm').addEventListener('submit', async e => {
    e.preventDefault();
    let p;
    try { p = buildPayload(false); } catch (err) { return; }
    if (!p) return;
    if (quoteId) p.quote_id = quoteId;

    const fd = new FormData();
    fd.append('payload', JSON.stringify(p));
    if (isPro) {
      if ($('firstFrame').files[0]) fd.append('first_frame', $('firstFrame').files[0]);
      if ($('lastFrame').files[0])  fd.append('last_frame', $('lastFrame').files[0]);
      [...$('references').files].slice(0, 7).forEach((f, i) => fd.append('reference_' + i, f));
      // 关键帧文件：slot 与 keyframes 数组顺序对应
      let kfIdx = 0;
      document.querySelectorAll('#keyframes .dyn-row').forEach(row => {
        const f = row.querySelector('.kf-file').files[0];
        if (f) fd.append('keyframe_' + (kfIdx++), f);
      });
    }

    $('btnSubmit').disabled = true;
    $('submitResult').innerHTML = '<span class="muted">提交中…</span>';
    try {
      const r = await fetch('api/generate_video.php', { method: 'POST', body: fd });
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
        if (j.code && j.code.indexOf('quote') >= 0) quoteId = '';
      }
    } catch (err) {
      $('submitResult').innerHTML = '<p class="err">网络错误：' + esc(err.message) + '</p>';
      $('btnSubmit').disabled = false;
    }
  });
})();
