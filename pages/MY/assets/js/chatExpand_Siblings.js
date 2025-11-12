// Chat flow to add siblings, wired to:
// GET  /api/expand_step2_prefill_siblings.php?person_id={pid}
// POST /api/expand_step2_save_siblings.php  { person_id, siblings:[{name,gender,status}] }

(function(){
  const $ = (s, r=document) => r.querySelector(s);
  const pid = (()=>{ try { return new URL(location.href).searchParams.get('pid'); } catch(_) { return null; } })();
  const messages = $('#messages');
  const input = $('#userInput');
  const sendBtn = $('#sendBtn');

  // session header
  fetch('/api/session_info.php').then(r=> r.ok ? r.json() : {ok:false}).then(j=>{
    const el=$('#sessionEmail'); if (el) el.textContent = (j&&j.ok&&j.user&&j.user.email)||'—';
  }).catch(()=>{});

  // simple chat engine
  const state = { resolver: null, choicesResolver: null, locked: false };
  function scrollBottom(){ messages.scrollTop = messages.scrollHeight; }
  function pushBot(html){ const div = document.createElement('div'); div.className='msg bot'; div.innerHTML = `<div class="bubble">${html}</div>`; messages.appendChild(div); scrollBottom(); return div; }
  function pushUser(text){ const div = document.createElement('div'); div.className='msg user'; div.innerHTML = `<div class="bubble">${escapeHtml(text)}</div>`; messages.appendChild(div); scrollBottom(); }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, ch=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;' }[ch])); }

  function ask(prompt, opts){
    // opts: { choices: [{label,value}], placeholder, allowEmpty:boolean }
    return new Promise(resolve => {
      const m = pushBot(escapeHtml(prompt));
      const chipsWrap = document.createElement('div'); chipsWrap.className='chips';
      if (opts && Array.isArray(opts.choices) && opts.choices.length){
        opts.choices.forEach(ch => {
          const b = document.createElement('button'); b.type='button'; b.className='chip'; b.textContent = ch.label;
          b.addEventListener('click', ()=>{ if (state.locked) return; state.locked = true; pushUser(ch.label); chipsWrap.remove(); resolve(ch.value); state.locked = false; });
          chipsWrap.appendChild(b);
        });
        m.querySelector('.bubble').appendChild(chipsWrap);
      }
      if (opts && opts.placeholder){ input.placeholder = opts.placeholder; }
      state.resolver = (text)=>{ if (state.locked) return; state.locked=true; const val=(text||'').trim(); if (!val && !(opts&&opts.allowEmpty)) { state.locked=false; return; } pushUser(val||''); resolve(val); state.locked=false; };
    });
  }

  function bindInput(){
    sendBtn.addEventListener('click', ()=>{ const fn = state.resolver; if (fn){ const v=input.value; input.value=''; fn(v); }});
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); sendBtn.click(); }});
  }

  const ALLOWED_GENDERS = [
    {label:'Lelaki', value:'male'},
    {label:'Perempuan', value:'female'},
    {label:'Lain', value:'other'},
    {label:'Tidak ingin nyatakan', value:'prefer_not_to_say'}
  ];
  const STATUS_CHOICES = [
    {label:'Hidup', value:'living'},
    {label:'Meninggal', value:'deceased'},
    {label:'Tidak pasti', value:''}
  ];

  async function prefill(){
    if (!pid) return { ok:false };
    try {
  const r = await fetch(`/api/expand_step2_prefill_siblings.php?person_id=${encodeURIComponent(pid)}`);
      return await r.json();
    } catch(_){ return { ok:false }; }
  }

  function renderExisting(list){
  if (!list || !list.length){ pushBot('Adik-beradik sedia ada: <span class="muted">Tiada lagi</span>'); return; }
    const names = list.map(s=> escapeHtml(s.name) + (s.gender? ` <span class="muted">(${escapeHtml(s.gender)})</span>` : '') );
  pushBot('Adik-beradik sedia ada:<br>' + names.map(n=>`• ${n}`).join('<br>'));
  }

  async function run(){
  if (!pid){ pushBot('ID individu tiada.'); return; }
    const pre = await prefill();
    const tgt = $('#personTarget'); if (tgt) tgt.textContent = (pre && pre.person_name) ? pre.person_name : `#${pid}`;
  pushBot(`Mari tambah adik-beradik untuk <strong>${escapeHtml(pre && pre.person_name || `#${pid}`)}</strong>. Mereka akan dipaut kepada ibu bapa yang sama. Anda boleh menambah berbilang adik-beradik. Apabila selesai, pilih Selesai.`);
    renderExisting(pre && pre.siblings || []);

    const collected = [];

    // Main loop
    while (true){
  const action = await ask('Apa tindakan seterusnya?', { choices:[ {label:'Tambah adik-beradik', value:'add'}, {label:'Selesai', value:'finish'} ] });
      if (action === 'finish') break;

      // name
  const name = await ask('Nama penuh adik/abang/kakak?', { placeholder:'cth. Ahmad bin Ali' });
      if (!name){ continue; }
      // gender
  const gender = await ask('Jantina?', { choices: ALLOWED_GENDERS });
      // status
  const status = await ask('Status?', { choices: STATUS_CHOICES });

      collected.push({ name: String(name).trim(), gender: gender || 'other', status: status || '' });
  pushBot('Ditambah.');
    }

    if (collected.length === 0){
  const nav = await ask('Tiada adik-beradik baharu ditambah. Teruskan ke langkah pasangan?', { choices:[ {label:'Teruskan', value:'go'}, {label:'Kekal di sini', value:'stay'} ] });
  if (nav === 'go') location.href = `/pages/MY/chatExpand_partners.html?pid=${encodeURIComponent(pid)}`;
      return;
    }

  pushBot('Menyimpan adik-beradik…');
    try {
  const res = await fetch('/api/expand_step2_save_siblings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ person_id: Number(pid), siblings: collected })
      });
      const j = await res.json();
  if (!j || !j.ok){ pushBot(`<span class="muted" style="color:#b33;">Simpan gagal.</span>`); return; }
  pushBot('Disimpan. Mengalih ke pasangan…');
  setTimeout(()=>{ location.href = `/pages/MY/chatExpand_partners.html?pid=${encodeURIComponent(pid)}`; }, 900);
  } catch(_){ pushBot(`<span class="muted" style="color:#b33;">Ralat simpan.</span>`); }
  }

  window.addEventListener('load', ()=>{ document.querySelector('.card-enter')?.classList.add('in'); bindInput(); run(); });
})();
