// Chat flow to add children for a couple, wired to:
// GET  /api/children_by_parents.php?person_id={pid}&partner_id={co}
// POST /api/expand_step4_save_children.php { parent1_id, parent2_id, children:[{name,gender,status}] }
(function(){
  const $ = (s, r=document) => r.querySelector(s);
  const url = new URL(location.href);
  const pid = Number(url.searchParams.get('pid')) || 0; // parent1
  const co  = Number(url.searchParams.get('co'))  || 0; // parent2 (co-parent)
  const messages = $('#messages');
  const input = $('#userInput');
  const sendBtn = $('#sendBtn');

  const state = { resolver:null, locked:false };
  function scrollBottom(){ messages.scrollTop = messages.scrollHeight; }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, ch=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;' }[ch])); }
  function pushBot(html){ const div=document.createElement('div'); div.className='msg bot'; div.innerHTML=`<div class="bubble">${html}</div>`; messages.appendChild(div); scrollBottom(); return div; }
  function pushUser(text){ const div=document.createElement('div'); div.className='msg user'; div.innerHTML=`<div class="bubble">${escapeHtml(text)}</div>`; messages.appendChild(div); scrollBottom(); }

  function ask(prompt, opts){
    // opts: { choices:[{label,value}], placeholder, allowEmpty }
    return new Promise(resolve=>{
      const msg = pushBot(escapeHtml(prompt));
      if (opts && Array.isArray(opts.choices) && opts.choices.length){
        const chips = document.createElement('div'); chips.className='chips';
        opts.choices.forEach(ch=>{
          const b=document.createElement('button'); b.type='button'; b.className='chip'; b.textContent=ch.label;
          b.addEventListener('click', ()=>{ if (state.locked) return; state.locked=true; pushUser(ch.label); chips.remove(); resolve(ch.value); state.locked=false; });
          chips.appendChild(b);
        });
        msg.querySelector('.bubble').appendChild(chips);
      }
      if (opts && opts.placeholder) input.placeholder = opts.placeholder;
      state.resolver = (text)=>{ if (state.locked) return; state.locked=true; const val=(text||'').trim(); if (!val && !(opts&&opts.allowEmpty)) { state.locked=false; return; } pushUser(val||''); resolve(val); state.locked=false; };
    });
  }
  function bindInput(){
    sendBtn.addEventListener('click', ()=>{ const fn=state.resolver; if (fn){ const v=input.value; input.value=''; fn(v); }});
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); sendBtn.click(); }});
  }

  const GENDERS = [ {label:'Lelaki',value:'male'}, {label:'Perempuan',value:'female'} ];
  const STATUS  = [ {label:'Hidup',value:'living'}, {label:'Meninggal',value:'deceased'}, {label:'Tidak pasti', value:''} ];

  // Helper: fetch a person's display name by id with fallbacks
  async function fetchPersonName(id){
    if (!id) return '';
    // 1) Try person_get.php
    try {
  const r = await fetch(`/api/person_get.php?person_id=${encodeURIComponent(id)}`, { credentials:'include' });
      if (r.ok){ const j = await r.json(); const n = j?.person?.full_name || j?.person?.name; if (n && n.trim()) return n.trim(); }
    } catch(_){ }
    // 2) Fallback to family_tree
    try {
  const r = await fetch('/api/family_tree.php', { credentials:'include' });
      const j = await r.json();
      const list = j?.people || j?.persons || j?.data || [];
      if (Array.isArray(list)){
        const hit = list.find(p => String(p.id) === String(id));
        if (hit?.full_name) return hit.full_name;
        if (hit?.name) return hit.name;
      } else if (list && typeof list === 'object'){
        const p = list[id] || Object.values(list).find(x => String(x.id)===String(id));
        if (p?.full_name) return p.full_name; if (p?.name) return p.name;
      }
    } catch(_){ }
    return '';
  }

  async function prefill(){
    // First try sessionStorage prefill set by partners step
    try {
      const raw = sessionStorage.getItem('prefill_children');
      if (raw){
        const j = JSON.parse(raw);
        if (j && j.parent1_id === pid && j.parent2_id === co) return { ok:true, from:'session', ...j };
      }
    } catch(_){}
    // Fallback to API
    try {
  const r = await fetch(`/api/children_by_parents.php?person_id=${encodeURIComponent(pid)}&partner_id=${encodeURIComponent(co)}`);
      const j = await r.json();
      if (j && j.ok) return { ok:true, from:'api', parent1_id:j.parent1_id, parent2_id:j.parent2_id, parent1_name:j.parent1_name, parent2_name:j.parent2_name, children:j.children||[] };
    } catch(_){ }
    return { ok:false };
  }

  function renderExisting(children){
  if (!children || !children.length){ pushBot('Anak sedia ada: <span class="muted">Tiada lagi</span>'); return; }
    const lines = children.map(c=> `• ${escapeHtml(c.name||'')} <span class="muted">(${escapeHtml(c.gender||'–')})</span>`);
  pushBot('Anak sedia ada:<br>' + lines.join('<br>'));
  }

  async function run(){
  if (!pid || !co){ pushBot('Ibu bapa tidak lengkap.'); return; }
  const pre = await prefill();
  // Resolve names with fallback if API prefill didn't return them
  let p1name = (pre && pre.parent1_name) ? pre.parent1_name : '';
  let p2name = (pre && pre.parent2_name) ? pre.parent2_name : '';
  if (!p1name) { p1name = await fetchPersonName(pid); }
  if (!p2name) { p2name = await fetchPersonName(co); }
  $('#parent1').textContent = p1name || ('#'+pid);
  $('#parent2').textContent = p2name || ('#'+co);
  pushBot(`Mari tambah anak untuk <strong>${escapeHtml($('#parent1').textContent)}</strong> dan <strong>${escapeHtml($('#parent2').textContent)}</strong>.`);
    renderExisting(pre && pre.children || []);

    const collected = [];
    while (true){
  const action = await ask('Apa yang anda ingin lakukan seterusnya?', { choices:[ {label:'Tambah anak', value:'add'}, {label:'Selesai', value:'finish'} ] });
      if (action === 'finish') break;
  const name = await ask('Nama penuh anak?', { placeholder:'cth. Adam bin Ali' }); if (!name) continue;
  const gender = await ask('Jantina?', { choices: GENDERS });
  const status = await ask('Status?', { choices: STATUS });
      collected.push({ name: String(name).trim(), gender: gender || 'other', status: status || '' });
  pushBot('Ditambah.');
    }

    if (collected.length === 0){
  const nav = await ask('Tiada anak ditambah. Kembali ke pokok?', { choices:[ {label:'Pergi ke pokok', value:'tree'}, {label:'Kekal di sini', value:'stay'} ] });
  if (nav==='tree') location.href = '/pages/my/tree.html';
      return;
    }

  pushBot('Menyimpan anak…');
    try {
  const res = await fetch('/api/expand_step4_save_children.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ parent1_id: pid, parent2_id: co, children: collected }) });
      const j = await res.json();
  if (!j || !j.ok){ pushBot('<span class="muted" style="color:#b33;">Simpan gagal.</span>'); return; }
  pushBot('Disimpan. Kembali ke pokok…');
  setTimeout(()=>{ location.href = '/pages/my/tree.html'; }, 900);
  } catch(_){ pushBot('<span class="muted" style="color:#b33;">Ralat simpan.</span>'); }
  }

  window.addEventListener('load', ()=>{ bindInput(); run(); });
})();
