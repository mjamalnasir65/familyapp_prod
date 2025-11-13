(function(){
  const chat = document.getElementById('chat');
  const input = document.getElementById('reply');
  const sendBtn = document.getElementById('sendBtn');

  // Header session email
  fetch('/api/session_info.php').then(r=> r.ok ? r.json() : {ok:false}).then(u=>{
    const el=document.getElementById('sessionEmail'); if (el) el.textContent=(u&&u.ok&&u.user&&u.user.email)||'—';
  }).catch(()=>{});

  // Helpers
  function addMsg(html, who='bot'){ const d=document.createElement('div'); d.className='msg '+who; d.innerHTML=html; chat.appendChild(d); chat.scrollTop=chat.scrollHeight; return d; }
  function typing(on=true){ let t=chat.querySelector('.typing'); if(on && !t){ t=document.createElement('div'); t.className='typing'; t.textContent='…'; chat.appendChild(t);} if(!on && t){ t.remove(); } chat.scrollTop=chat.scrollHeight; }
  function qs(name){ const u=new URL(location.href); return u.searchParams.get(name); }

  let resolver=null; // current pending answer resolver
  function commitAnswer(){
    const val=(input.value||'').trim();
    if(!resolver) return; // no pending question
    addMsg(val||'—', 'user');
    const r=resolver; resolver=null; input.value=''; r(val);
  }
  input.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); commitAnswer(); } });
  sendBtn.addEventListener('click', commitAnswer);

  async function ask(prompt, choices){
    addMsg(prompt,'bot');
    if (Array.isArray(choices) && choices.length){
      const wrap=document.createElement('div'); wrap.className='choices';
      choices.forEach(c=>{
        const b=document.createElement('button'); b.type='button'; b.className='chip'; b.textContent=c;
        b.addEventListener('click', ()=>{ input.value=c; commitAnswer(); });
        wrap.appendChild(b);
      });
      chat.appendChild(wrap); chat.scrollTop=chat.scrollHeight;
    }
    return new Promise(resolve=>{ resolver=resolve; });
  }

  async function post(url, body){
    const r = await fetch(url, { method:'POST', body });
    try { return await r.json(); } catch { return {}; }
  }

  async function startExpand(pid){
    const fd = new FormData(); fd.append('person_id', pid);
  return post('/api/expand_start.php', fd);
  }
  async function prefillParents(pid){
  const r = await fetch(`/api/expand_step1_prefill_parents.php?person_id=${encodeURIComponent(pid)}`);
    try { return await r.json(); } catch { return {}; }
  }

  // NEW: fetch the focus person full_name by pid with fallbacks
  async function fetchPersonName(pid){
    // 1) person_get.php (if present)
    try {
  const r = await fetch(`/api/person_get.php?person_id=${encodeURIComponent(pid)}`, { credentials:'include' });
      if (r.ok) {
        const j = await r.json();
        const n = j?.person?.full_name || j?.person?.name;
        if (n && n.trim()) return n.trim();
      }
    } catch {}
    // 2) family_tree.php fallback (scan people list)
    try {
  const r = await fetch('/api/family_tree.php', { credentials:'include' });
      const j = await r.json();
      const list = j?.people || j?.persons || j?.data || [];
      // support both array and map
      if (Array.isArray(list)) {
        const hit = list.find(p => String(p.id) === String(pid));
        if (hit?.full_name) return hit.full_name;
        if (hit?.name) return hit.name;
      } else if (list && typeof list === 'object') {
        const p = list[pid] || Object.values(list).find(x => String(x.id)===String(pid));
        if (p?.full_name) return p.full_name;
        if (p?.name) return p.name;
      }
    } catch {}
    return '';
  }

  function esc(s){ return String(s||'').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // No date inputs in chat expand; only names, status, and gender.

  async function main(){
    const pid = qs('pid');
    if (!pid){ addMsg('Missing person id. Open this from the tree.', 'bot'); return; }

    typing(true);
    const st = await startExpand(pid).catch(()=>null);
    typing(false);
    if (!st || !st.ok){ addMsg('Unable to start expand for this person.', 'bot'); return; }

    // Optional prefill
    let father={}, mother={};
    typing(true);
    const pf = await prefillParents(pid).catch(()=>null);
    typing(false);
    if (pf && pf.ok){
      father = pf.father || {}; mother = pf.mother || {};
      if (father.name || mother.name){
        const fLine = father.name ? `Father on file: <b>${esc(father.name)}</b>` : '';
        const mLine = mother.name ? `Mother on file: <b>${esc(mother.name)}</b>` : '';
        addMsg([fLine,mLine].filter(Boolean).join('<br>') || 'No parents on file yet.');
      }
    }

    // CHANGED: show target person’s name
    const focusName = await fetchPersonName(pid);
    addMsg(`Let’s add parents to: <b>${esc(focusName || ('#'+pid))}</b>.`);

    // Father
    let fName = father.name || await ask('Father’s full name?');
    if (!fName) fName = '';
    let fStatus = (father.status||'').toLowerCase();
    if (!['living','deceased',''].includes(fStatus)){
      fStatus = await ask('Father status?', ['living','deceased','skip']);
    } else if (!fStatus) {
      fStatus = await ask('Father status?', ['living','deceased','skip']);
    }
  // Default father gender to male (no prompt)
  const fGender = 'male';

    // Mother
    let mName = mother.name || await ask('Mother’s full name?');
    if (!mName) mName = '';
    let mStatus = (mother.status||'').toLowerCase();
    if (!['living','deceased',''].includes(mStatus)){
      mStatus = await ask('Mother status?', ['living','deceased','skip']);
    } else if (!mStatus) {
      mStatus = await ask('Mother status?', ['living','deceased','skip']);
    }
  // Default mother gender to female (no prompt)
  const mGender = 'female';

    // Build payload
    const fd = new FormData();
    fd.append('person_id', pid);
    if (fName) fd.append('father_name', fName);
  if (fStatus && fStatus!=='skip') fd.append('father_status', fStatus);
  if (fGender) fd.append('father_gender', String(fGender).toLowerCase());
    fd.append('father_rel_type', 'biological');

    if (mName) fd.append('mother_name', mName);
  if (mStatus && mStatus!=='skip') fd.append('mother_status', mStatus);
  if (mGender) fd.append('mother_gender', String(mGender).toLowerCase());
    fd.append('mother_rel_type', 'biological');

    typing(true);
  const save = await post('/api/expand_step1_save_parents.php', fd);
    typing(false);
    if (!save || !save.ok){ addMsg('Save failed. Please try again.', 'bot'); return; }

    addMsg('✅ Parents saved. Moving to siblings…', 'bot');
    // Fix: case-sensitive path on production (S in Siblings)
    setTimeout(()=>{ window.location.href=`/pages/en/chatExpand_siblings.html?pid=${encodeURIComponent(pid)}`; }, 700);
  }

  window.addEventListener('load', main);
})();