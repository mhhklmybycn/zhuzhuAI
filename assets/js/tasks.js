/**
 * 任务管理页逻辑：列表加载、进行中任务轮询、结果展示
 */
(function () {
  let filter = 'active';
  let pollTimer = null;
  const $ = id => document.getElementById(id);

  /* ---------- 筛选切换 ---------- */
  document.querySelectorAll('.mode-tabs .tab').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mode-tabs .tab').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
      filter = btn.dataset.filter;
      loadTasks();
    });
  });
  $('btnRefresh').addEventListener('click', loadTasks);

  /* ---------- 加载任务列表 ---------- */
  async function loadTasks() {
    const r = await apiGet('api/tasks.php?filter=' + filter);
    if (!r.ok) { $('taskList').innerHTML = '<p class="err">加载失败：' + esc(r.msg) + '</p>'; return; }
    renderTasks(r.tasks);
    // 有进行中任务时启动轮询
    const active = r.tasks.filter(t => t.status === 'accepted' || t.status === 'processing');
    if (pollTimer) clearInterval(pollTimer);
    if (active.length) {
      pollTimer = setInterval(() => pollActive(active.map(t => t.id)), 5000);
    }
  }

  /** 轮询进行中任务状态 */
  async function pollActive(ids) {
    for (const id of ids) {
      const r = await apiGet('api/task_status.php?id=' + id);
      if (r.ok) updateTaskCard(r.task);
    }
  }

  /* ---------- 渲染 ---------- */
  function renderTasks(tasks) {
    if (!tasks.length) { $('taskList').innerHTML = '<p class="empty">暂无任务</p>'; return; }
    $('taskList').innerHTML = tasks.map(t => taskCardHtml(t)).join('');
  }

  function updateTaskCard(t) {
    t.results = typeof t.results === 'string' ? JSON.parse(t.results || '[]') : (t.results || []);
    const el = document.getElementById('task-' + t.id);
    if (el) el.outerHTML = taskCardHtml(t);
    else loadTasks();
  }

  const STATUS_MAP = {
    accepted:   ['排队中', 'tag-blue'],
    processing: ['生成中', 'tag-blue'],
    succeeded:  ['成功',   'tag-green'],
    failed:     ['失败',   'tag-red']
  };

  function taskCardHtml(t) {
    const [stText, stCls] = STATUS_MAP[t.status] || [t.status, 'tag-blue'];
    const kindText = t.kind === 'image' ? '🖼 图像' : '🎬 视频';
    let resultsHtml = '';
    if (t.status === 'succeeded' && t.results && t.results.length) {
      resultsHtml = '<div class="result-grid">' + t.results.map(r => {
        const url = 'api/result.php?task=' + t.id + '&rid=' + encodeURIComponent(r.id);
        return t.kind === 'image'
          ? `<a href="${url}" target="_blank"><img src="${url}" class="result-thumb" loading="lazy"></a>`
          : `<video src="${url}" class="result-thumb" controls preload="metadata"></video>`;
      }).join('') + '</div>';
    }
    return `
    <div class="task-card" id="task-${t.id}">
      <div class="task-head">
        <span class="task-id">#${t.id}</span>
        <span>${kindText}</span>
        <span class="muted">${esc(t.model || '默认模型')}</span>
        <span class="tag ${stCls}">${stText}</span>
        ${t.settlement_status ? `<span class="muted">结算：${esc(t.settlement_status)}</span>` : ''}
        <span class="muted" style="margin-left:auto">${esc(t.created_at)}</span>
      </div>
      <div class="task-body">
        <div class="task-prompt">${esc((t.prompt || '').slice(0, 200))}</div>
        <div class="task-meta">
          进度：${t.completed}/${t.requested}
          ${t.net_points ? ' · 消耗积分：' + t.net_points : ''}
          ${t.error ? ' · <span class="err">' + esc(t.error) + '</span>' : ''}
        </div>
        ${resultsHtml}
      </div>
    </div>`;
  }

  // 首次加载
  loadTasks();
})();
