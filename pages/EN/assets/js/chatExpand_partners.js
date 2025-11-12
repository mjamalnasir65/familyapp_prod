// Chat flow to add partners, wired to:
// GET  /api/expand_step3_prefill_partners.php?person_id={pid}
// POST /api/expand_step3_save_partners.php  { person_id, partners:[{name,gender,status,rel_type}] }
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

  // chat helpers
  const state = { resolver: null, locked:false };
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

  const GENDERS = [ {label:'Male',value:'male'}, {label:'Female',value:'female'}];
  const STATUS = [ {label:'Living', value:'living'}, {label:'Deceased', value:'deceased'}, {label:'Unknown', value:''} ];
  const REL_TYPES = [ {label:'Marriage', value:'marriage'}, {label:'Divorced', value:'divorced'}, {label:'Separated', value:'separated'}, {label:'Widowed', value:'widowed'} ];

  async function prefill(){
    if (!pid) return { ok:false };
  try { const r=await fetch(`/api/expand_step3_prefill_partners.php?person_id=${encodeURIComponent(pid)}`); return await r.json(); } catch(_){ return { ok:false }; }
  }

  function renderExisting(partners){
    if (!partners || !partners.length){ pushBot('Existing partners: <span class="muted">None yet</span>'); return; }
    const lines = partners.map(p=> `• ${escapeHtml(p.name||'')} <span class="muted">(${escapeHtml(p.gender||'–')} · ${escapeHtml(p.rel_type||'marriage')}${p.is_current?' · current':''})</span>`);
    pushBot('Existing partners:<br>'+lines.join('<br>'));
  }

  async function run(){
    if (!pid){ pushBot('Missing person id.'); return; }
    const pre = await prefill();
    const tgt = $('#personTarget'); if (tgt) tgt.textContent = (pre && pre.person_name) ? pre.person_name : `#${pid}`;
    pushBot(`Let’s add partners for <strong>${escapeHtml(pre && pre.person_name || `#${pid}`)}</strong>. We’ll create a union automatically.`);
    renderExisting(pre && pre.partners || []);

    const collected = [];
    while (true){
      const action = await ask('What would you like to do next?', { choices:[ {label:'Add a partner', value:'add'}, {label:'Finish', value:'finish'} ] });
      if (action === 'finish') break;

      const name = await ask('Partner’s full name?', { placeholder:'e.g. Jane Doe' });
      if (!name) continue;
      const gender = await ask('Gender?', { choices: GENDERS });
      const status = await ask('Status?', { choices: STATUS });
      const rel = await ask('Relationship type?', { choices: REL_TYPES });

      collected.push({ name: String(name).trim(), gender: gender || 'other', status: status || '', rel_type: rel || 'marriage' });
      pushBot('Added.');
    }

    if (collected.length === 0){
      const nav = await ask('No partners added. Go back to add child?', { choices:[ {label:'Go to add child', value:'tree'}, {label:'Stay here', value:'stay'} ] });
      if (nav==='tree') {
        // If we already know an existing single partner, carry it along
        const existing = (pre && Array.isArray(pre.partners)) ? pre.partners : [];
        let coId = 0;
        if (existing.length === 1 && existing[0] && existing[0].partner_id) coId = Number(existing[0].partner_id)||0;
        const url = coId
          ? `/pages/EN/chatExpand_children.html?pid=${encodeURIComponent(pid)}&co=${encodeURIComponent(coId)}`
          : `/pages/EN/chatExpand_children.html?pid=${encodeURIComponent(pid)}`;
        location.href = url;
      }
      return;
    }

    pushBot('Saving partners…');
    try {
  const res = await fetch('/api/expand_step3_save_partners.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ person_id: Number(pid), partners: collected }) });
      const j = await res.json();
      if (!j || !j.ok){ pushBot('<span class="muted" style="color:#b33;">Save failed.</span>'); return; }
      pushBot('Saved. Redirecting to children…');
      // Try to derive the co-parent from the last union returned by the API
      let coId = 0;
      try {
        const unions = Array.isArray(j.unions) ? j.unions : [];
        if (unions.length) {
          const last = unions[unions.length - 1];
          const p1 = Number(last.p1||last.person1_id||0);
          const p2 = Number(last.p2||last.person2_id||0);
          const me = Number(pid);
          coId = (p1 === me) ? p2 : (p2 === me) ? p1 : 0;
        }
      } catch(_){}
      // Store soft prefill for the next step as a convenience
      try {
        const obj = {
          parent1_id: Number(pid),
          parent2_id: coId || undefined,
          parent1_name: (pre && pre.person_name) || undefined
        };
        sessionStorage.setItem('prefill_children', JSON.stringify(obj));
      } catch(_){}
      const url = coId
  ? `/pages/EN/chatExpand_children.html?pid=${encodeURIComponent(pid)}&co=${encodeURIComponent(coId)}`
  : `/pages/EN/chatExpand_children.html?pid=${encodeURIComponent(pid)}`;
      setTimeout(()=>{ location.href = url; }, 900);
    } catch(_){ pushBot(`<span class="muted" style="color:#b33;">Save error.</span>`); }
  }

  window.addEventListener('load', ()=>{ document.querySelector('.card-enter')?.classList.add('in'); bindInput(); run(); });
})();
