// Chat Wizard - Conversational onboarding compatible with wizard.js steps (1..6)
(function(){
  const chat = document.getElementById('chat');
  const input = document.getElementById('reply');
  const sendBtn = document.getElementById('sendBtn');
  const stateKey = 'wizard_state_v1';
  let state = loadState() || {
    step: 1,
    family: { name: '', desc: '' },
    self: { name: '', gender: '', status: 'living', birth_date: '' },
    parents: { father: {}, mother: {} },
    siblings: [], partners: [], children: []
  };

  // Session email for header
  try { fetch('/api/session_info.php').then(r=> r.ok ? r.json() : { ok:false }).then(j=>{
    const el=document.getElementById('sessionEmail'); if (el) el.textContent=(j&&j.ok&&j.user&&j.user.email)||'—';
  }); } catch(_){ }

  // Respect server-provided next step via URL (?step=2) to resume correctly after login
  (function syncStepFromURL(){
    try {
      const url = new URL(window.location.href);
      const s = parseInt(url.searchParams.get('step')||'',10);
      if (Number.isFinite(s) && s>=1 && s<=6){
        state.step = s;
        saveState();
      }
    } catch(_){}
  })();

  function addMsg(text, who='bot', meta){
    const div = document.createElement('div');
    div.className = `msg ${who}`;
    div.innerHTML = text + (meta?`<span class="meta">${meta}</span>`:'');
    chat.appendChild(div);
    chat.scrollTop = chat.scrollHeight;
  }
  function showTyping(show=true){
    let t = document.getElementById('typing');
    if (show){ if (!t){ t=document.createElement('div'); t.id='typing'; t.className='typing'; t.textContent='typing…'; chat.appendChild(t);} }
    else { if (t) t.remove(); }
    chat.scrollTop = chat.scrollHeight;
  }
  function sleep(ms){ return new Promise(r=>setTimeout(r, ms)); }

  function loadState(){ try{ const s=localStorage.getItem(stateKey); return s?JSON.parse(s):null; }catch(_){ return null; } }
  function saveState(){ try{ localStorage.setItem(stateKey, JSON.stringify(state)); }catch(_){ } }
  function setStep(n){ state.step = n; saveState(); }

  function userResponse(){
    return new Promise(resolve=>{
      const commit = () => {
        const valRaw = input.value || '';
        const val = valRaw.trim();
        if (!val) return; // ignore empty
        input.value='';
        addMsg(val, 'user');
        cleanup();
        resolve(val);
      };
      const keyHandler = e => { if (e.key === 'Enter') { commit(); } };
      const clickHandler = () => commit();
      const cleanup = () => {
        input.removeEventListener('keydown', keyHandler);
        if (sendBtn) sendBtn.removeEventListener('click', clickHandler);
      };
      input.addEventListener('keydown', keyHandler);
      if (sendBtn) sendBtn.addEventListener('click', clickHandler);
    });
  }
  async function ask(q, choices, opts){
    showTyping(true); await sleep(400); showTyping(false);
    addMsg(q, 'bot');
    if ((opts && opts.skip) || (Array.isArray(choices) && choices.length)){
      let resolvePromise;
      const wrap = document.createElement('div'); wrap.className='choices';
      if (opts && opts.skip){
        const b=document.createElement('button'); b.type='button'; b.className='chip'; b.textContent='Skip';
        b.addEventListener('click',()=>{ addMsg('skip','user'); wrap.remove(); resolvePromise('skip'); });
        wrap.appendChild(b);
      }
      (choices||[]).forEach(c=>{ const b=document.createElement('button'); b.type='button'; b.className='chip'; b.textContent=c; b.addEventListener('click',()=>{ addMsg(c,'user'); wrap.remove(); resolvePromise(c); }); wrap.appendChild(b); });
      chat.appendChild(wrap); chat.scrollTop = chat.scrollHeight;
      return new Promise(res=>{ resolvePromise=res; });
    }
    return await userResponse();
  }

  async function postForm(url, data){
    const form = new URLSearchParams();
    Object.keys(data||{}).forEach(k=>form.append(k, data[k]));
    const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form });
    if (!r.ok) {
      return { ok: false, error: 'server_error', status: r.status };
    }
    try {
      return await r.json();
    } catch (e) {
      return { ok: false, error: 'invalid_response' };
    }
  }

  async function start(){
  addMsg('👋 Selamat datang! Kita akan tetapkan pokok keluarga anda melalui sembang.');

    // Resume to the next required step if state.step > 1
    if (state.step <= 1){ await step1(); }
    if (state.step <= 2){ await step2(); }
    if (state.step <= 3){ await step3(); }
    if (state.step <= 4){ await step4(); }
    if (state.step <= 5){ await step5(); }
    await step6();
  }

  // STEP 1 - Family
  async function step1(){
  addMsg('Mari cipta keluarga anda.');
    while(true){
  const name = await ask('Apakah nama keluarga anda?');
  if (!name){ addMsg('Sila masukkan nama.'); continue; }
  const check = await postForm('/api/family_check_name.php', { name });
  if (!check.ok){ addMsg('Pelayan sibuk. Cuba lagi.'); continue; }
  if (!check.unique){ addMsg('⚠️ Nama keluarga itu sudah wujud. Cuba yang lain.'); continue; }
  const create = await postForm('/api/family_create.php', { name });
  if (!create.ok){ addMsg('Tidak dapat menyimpan buat masa ini. Cuba lagi.'); continue; }
      state.family.name = name; state.family.desc = ''; setStep(2);
  addMsg(`✅ Keluarga <b>${name}</b> dicipta.`);
      break;
    }
  }

  // STEP 2 - Self
  async function step2(){
    // Use the registered full name from the current session where possible
  addMsg('Ini ialah nama penuh berdaftar anda');
    let selfName = '';
    try {
      const r = await fetch('/api/session_info.php');
      const j = await (r.ok ? r.json() : Promise.resolve({ ok:false }));
      selfName = ((j && j.ok && (j.user && (j.user.full_name || j.user.name))) || '').trim();
      if (selfName) {
  addMsg(`Nama dalam rekod: <b>${selfName}</b>`);
      }
    } catch(_) { /* fallback to prompt */ }
    if (!selfName) {
      // Fallback to prompt if not available from session
  selfName = await ask('Nama penuh anda?');
    }
  let gender = await ask('Jantina?', ['male','female']);
    gender = (['male','female'].includes((gender||'').toLowerCase())?gender.toLowerCase():'other');
    const payload = { name: selfName, gender, status: 'living' };
  const save = await postForm('/api/step2_self_save.php', payload);
  if (!save.ok){ addMsg('Tidak dapat menyimpan maklumat anda. Cuba lagi.'); return; }
    state.self = { name:selfName, gender, status:'living' }; setStep(3);
  addMsg(`✅ Disimpan ${selfName}.`);
  }

  // STEP 3 - Parents
  async function step3(){
  addMsg('Mari tambah ibu bapa anda.');
  const father = await ask('Nama penuh bapa?');
  const fatherStatus = await ask('Status bapa?', ['living','deceased','skip']);

  const mother = await ask('Nama penuh ibu?');
  const motherStatus = await ask('Status ibu?', ['living','deceased','skip']);

    const fd = new FormData();
    fd.append('father_name', father);
    if (fatherStatus && fatherStatus!=='skip') fd.append('father_status', fatherStatus);
    fd.append('father_rel_type', 'biological');

    fd.append('mother_name', mother);
    if (motherStatus && motherStatus!=='skip') fd.append('mother_status', motherStatus);
    fd.append('mother_rel_type', 'biological');

  const res = await fetch('/api/step3_parents_save.php', { method:'POST', body: fd });
    const j = await res.json().catch(()=>({ok:false}));
  if (!j.ok){ addMsg('Tidak dapat menyimpan ibu bapa. Cuba lagi.'); return; }
    state.parents.father = { name: father, status: fatherStatus };
    state.parents.mother = { name: mother, status: motherStatus }; setStep(4);
  addMsg('✅ Ibu bapa disimpan.');
  }

  // STEP 4 - Siblings
  async function step4(){
  const has = await ask('Adakah anda mempunyai adik-beradik?', ['yes','no']);
    if ((has||'').toLowerCase().startsWith('y')){
  let countStr = await ask('Berapa orang adik-beradik? (nombor)');
      let count = parseInt(countStr, 10); if (!Number.isFinite(count) || count<1) count = 1;
      const siblings = [];
      for (let i=0;i<count;i++){
  const n = await ask(`Nama adik-beradik ${i+1}:`);
  const g = await ask(`Jantina adik-beradik ${i+1}:`, ['male','female']);
  const st = await ask(`Status adik-beradik ${i+1}:`, ['living','deceased','skip']);
        siblings.push({
          name:n,
          gender:(g||'').toLowerCase(),
          status: st==='skip'?'':st
        });
      }
  const r = await fetch('/api/step4_siblings_save.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ siblings }) });
      const j = await r.json().catch(()=>({ok:false}));
  if (!j.ok){ addMsg('Tidak dapat menyimpan adik-beradik. Cuba lagi kemudian.'); return; }
  state.siblings = siblings; addMsg('✅ Adik-beradik disimpan.');
  } else { addMsg('Baik, langkau adik-beradik.'); }
    setStep(5);
  }

  // STEP 5 - Partners
  async function step5(){
  const has = await ask('Adakah anda mempunyai pasangan?', ['yes','no']);
    let partners = [];
    if ((has||'').toLowerCase().startsWith('y')){
  const n = await ask('Nama pasangan:');
  const g = await ask('Jantina pasangan:', ['male','female']);
  const st = await ask('Status pasangan:', ['living','deceased','skip']);
      partners.push({
        name:n, gender:(g||'').toLowerCase(), status: st==='skip'?'':st,
        rel_type:'marriage', is_current:true
      });
  const r = await fetch('/api/step5_partners_save.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ partners }) });
      const j = await r.json().catch(()=>({ok:false}));
  if (!j.ok){ addMsg('Tidak dapat menyimpan pasangan. Cuba lagi kemudian.'); return; }
  addMsg('✅ Pasangan disimpan.');
    } else {
  await fetch('/api/wizard_complete_no_partners.php', { method:'POST' });
  addMsg('Langkau bahagian pasangan.');
    }
    state.partners = partners; setStep(6);
  }

  // STEP 6 - Children
  async function step6(){
    // Try to get parent context for payload
    try {
  const r = await fetch('/api/step6_parents_context.php');
      const j = await r.json();
      if (j && j.ok){ window.__step6_parentA = j.primary?.id||0; window.__step6_parentB = j.partner?.id||0; addMsg((j.partner?`Parents: ${j.primary?.name||'You'} + ${j.partner?.name}`:`Parent: ${j.primary?.name||'You'}`),'bot'); }
    } catch(_){ }

    const has = await ask('Do you have children?', ['yes','no']);
  if (!(has||'').toLowerCase().startsWith('y')){
  await fetch('/api/wizard_complete_step6.php', { method:'POST' });
  addMsg('🎉 Wizard siap! Mengalih ke papan pemuka…'); await sleep(800); window.location.href='/pages/my/dashboard.html'; return;
    }

  let countStr = await ask('Berapa orang anak? (nombor)');
    let count = parseInt(countStr, 10); if (!Number.isFinite(count) || count<1) count = 1;
    const children = [];
    for (let i=0;i<count;i++){
  const n = await ask(`Nama anak ${i+1}:`);
  const g = await ask(`Jantina anak ${i+1}:`, ['male','female']);
  const st = await ask(`Status anak ${i+1}:`, ['living','deceased','skip']);
      children.push({
        name:n, gender:(g||'').toLowerCase(), status: st==='skip'?'':st,
        rel_type:'biological'
      });
    }

    const payload = { children };
    if (window.__step6_parentA) payload.parent_a_id = window.__step6_parentA;
    if (window.__step6_parentB) payload.parent_b_id = window.__step6_parentB;

  const r = await fetch('/api/step6_children_save.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const j = await r.json().catch(()=>({ok:false}));
  if (!j.ok){ addMsg('Tidak dapat menyimpan anak buat masa ini. Cuba lagi.'); return; }
    state.children = children; saveState();
  addMsg('🎉 Wizard siap! Mengalih ke papan pemuka…'); await sleep(800); window.location.href='/pages/my/dashboard.html';
  }

  // Start the conversation
  start();
})();
