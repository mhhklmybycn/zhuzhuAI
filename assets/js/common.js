/**
 * 公共 JS 工具函数
 */

/** 发起 JSON POST 请求 */
async function apiPost(url, data) {
  const r = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  });
  return r.json();
}

/** 发起 GET 请求 */
async function apiGet(url) {
  const r = await fetch(url);
  return r.json();
}

/** HTML 转义 */
function esc(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

/** 加载模型列表；kind 过滤：'image' 取 models，'video' 取 video_models
 *  返回扁平化数组：[{provider_id, provider_name, model_id, model_name, caps}] */
async function loadModels(kind) {
  const r = await apiGet('api/models.php');
  if (!r.ok) { alert('模型列表加载失败：' + r.msg); return []; }
  const groups = kind === 'image' ? (r.data.models || []) : (r.data.video_models || []);
  const list = [];
  groups.forEach(g => {
    if (g.enabled === false) return; // 跳过未启用提供商
    (g.models || []).forEach(m => {
      list.push({
        provider_id: g.id || '',
        provider_name: g.name || g.id || '',
        model_id: m.id || m.name || '',
        model_name: m.name || m.id || '',
        caps: m
      });
    });
  });
  return list;
}

/** 填充模型下拉框 */
function fillModelSelect(sel, models) {
  sel.innerHTML = '';
  models.forEach((m, i) => {
    const o = document.createElement('option');
    o.value = i;
    o.textContent = m.provider_name + ' / ' + (m.model_name || m.model_id);
    sel.appendChild(o);
  });
}

/** 渲染计费预览结果到指定容器，返回 quote_id（无则空串） */
function renderBilling(box, data) {
  let html = '<table class="tbl">';
  if (data.points_cost != null)     html += row('预估积分消耗', data.points_cost);
  if (data.charge_points != null)   html += row('实际扣费积分', data.charge_points);
  if (data.free_available) {
    html += row('免费额度', `可用（今日剩余 ${data.free_remaining}/${data.daily_free_limit}）`);
  } else if (data.daily_free_limit != null) {
    html += row('免费额度', `不可用（今日剩余 ${data.free_remaining}/${data.daily_free_limit}）`);
  }
  if (data.points_balance != null)  html += row('当前积分余额', data.points_balance);
  if (data.vip_level != null)       html += row('VIP 等级', data.vip_level);
  if (data.retail_quote) {
    const q = data.retail_quote;
    html += row('报价单号', esc(q.quote_id || ''));
    if (q.amount) html += row('单价', esc(q.amount) + ' ' + esc(q.currency || '') + ' / ' + esc(q.unit || ''));
    if (q.discount_bps != null && q.discount_bps > 0) html += row('折扣', (q.discount_bps / 100) + '%');
    if (q.quoted_at) html += row('报价时间', esc(q.quoted_at));
  }
  html += '</table>';
  box.innerHTML = html;
  function row(k, v) { return `<tr><th style="width:130px">${k}</th><td>${v}</td></tr>`; }
  return data.quote_id || (data.retail_quote && data.retail_quote.quote_id) || '';
}

/** 简易提示条 */
function toast(msg, ok) {
  const d = document.createElement('div');
  d.className = 'toast ' + (ok === false ? 'toast-err' : 'toast-ok');
  d.textContent = msg;
  document.body.appendChild(d);
  setTimeout(() => d.remove(), 3500);
}
