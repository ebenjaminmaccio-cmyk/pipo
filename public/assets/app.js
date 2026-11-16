(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  // --- Markdown mínimo y seguro: primero se escapa TODO, después se aplica formato ---
  const esc = (s) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function md(text) {
    const lines = esc(text).split('\n');
    let html = '', inList = false;
    for (let line of lines) {
      line = line
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*(?!\s)(.+?)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');
      const li = line.match(/^\s*(?:[-•*]|\d+\.)\s+(.*)$/);
      const h = line.match(/^#{1,4}\s+(.*)$/);
      if (li) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + li[1] + '</li>'; continue; }
      if (inList) { html += '</ul>'; inList = false; }
      if (h) html += '<h4>' + h[1] + '</h4>';
      else if (line.trim() === '') html += '<br>';
      else html += '<p>' + line + '</p>';
    }
    if (inList) html += '</ul>';
    return html;
  }
  const renderMd = (el) => { el.innerHTML = md(el.textContent || ''); el.removeAttribute('data-markdown'); };
  document.querySelectorAll('[data-markdown]').forEach(renderMd);

  // --- Confirmaciones y estados de carga ---
  document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  document.querySelectorAll('[data-confirm-click]').forEach((b) => b.addEventListener('click', (e) => { if (!confirm(b.dataset.confirmClick)) e.preventDefault(); }));
  document.querySelectorAll('form[data-loading]').forEach((f) => f.addEventListener('submit', () => {
    const b = f.querySelector('button'); if (b) { b.disabled = true; b.textContent = f.dataset.loading; }
  }));
  document.querySelectorAll('[data-copy]').forEach((i) => i.addEventListener('click', () => { i.select(); navigator.clipboard?.writeText(i.value); }));

  // --- Chat con el asistente ---
  const form = document.getElementById('chat-form');
  if (!form) return;
  const log = document.getElementById('chat-log');
  const input = document.getElementById('chat-input');
  const send = document.getElementById('chat-send');
  const scroll = () => { log.scrollTop = log.scrollHeight; };
  scroll();

  function bubble(role, text, isMd) {
    const wrap = document.createElement('div');
    wrap.className = 'msg ' + role;
    const b = document.createElement('div');
    b.className = 'bubble';
    b.textContent = text;
    if (isMd) renderMd(b);
    wrap.appendChild(b);
    log.appendChild(wrap);
    scroll();
    return b;
  }

  async function submit(text) {
    if (!text.trim()) return;
    bubble('user', text, false);
    input.value = ''; input.style.height = '';
    send.disabled = true;
    const thinking = bubble('assistant', 'Pensando…', false);
    thinking.classList.add('thinking');
    try {
      const r = await fetch('/asistente/enviar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ message: text }),
        credentials: 'same-origin',
      });
      const data = await r.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
      thinking.parentElement.remove();
      if (data.error) { bubble('assistant error', data.error, false); return; }
      bubble('assistant', data.reply, true);
      (data.actions || []).forEach((a) => {
        const el = bubble('action', '✓ ' + a.text, false);
        if (a.link) { const l = document.createElement('a'); l.href = a.link; l.textContent = ' Ver'; el.appendChild(l); }
      });
    } catch (e) {
      thinking.parentElement.remove();
      bubble('assistant error', 'No me pude conectar. Revisá tu conexión.', false);
    } finally {
      send.disabled = false; input.focus();
    }
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); submit(input.value); });
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(input.value); } });
  input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 200) + 'px'; });
  document.querySelectorAll('[data-suggest]').forEach((s) => s.addEventListener('click', () => submit(s.textContent)));
  const q = new URLSearchParams(location.search).get('q');
  if (q) { input.value = q; input.focus(); }
})();
