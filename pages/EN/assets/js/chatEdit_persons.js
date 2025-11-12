// Chat editor for a single person; wires to person_get.php and person_update.php
(function(){
  const $ = (s, r=document) => r.querySelector(s);
  const url = new URL(location.href);
  const pid = Number(url.searchParams.get('pid')) || 0;
  const messages = $('#messages');
  const input = $('#userInput');
  const sendBtn = $('#sendBtn');
  // Hidden file input for photo upload (created once)
  const fileInput = (() => {
    const el = document.createElement('input');
    el.type = 'file';
    el.accept = 'image/*';
    el.style.display = 'none';
    document.body.appendChild(el);
    return el;
  })();

  const state = { resolver:null, locked:false };
  function scrollBottom(){ messages.scrollTop = messages.scrollHeight; }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, ch=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;' }[ch])); }
  function bot(html){ const div=document.createElement('div'); div.className='msg bot'; div.innerHTML=`<div class="bubble">${html}</div>`; messages.appendChild(div); scrollBottom(); return div; }
  function user(text){ const div=document.createElement('div'); div.className='msg user'; div.innerHTML=`<div class="bubble">${escapeHtml(text)}</div>`; messages.appendChild(div); scrollBottom(); }

  function ask(prompt, opts){
    // opts: { choices:[{label,value}], placeholder, allowEmpty, hintHtml }
    return new Promise(resolve=>{
      const msg = bot(escapeHtml(prompt));
      if (opts && opts.hintHtml){ const wrap = msg.querySelector('.bubble'); const hint = document.createElement('div'); hint.className='muted'; hint.style.marginTop='6px'; hint.innerHTML = opts.hintHtml; wrap.appendChild(hint); }
      if (opts && Array.isArray(opts.choices) && opts.choices.length){
        const chips = document.createElement('div'); chips.className='chips';
        opts.choices.forEach(ch=>{
          const b=document.createElement('button'); b.type='button'; b.className='chip'; b.textContent=ch.label;
          b.addEventListener('click', ()=>{ if (state.locked) return; state.locked=true; user(ch.label); chips.remove(); resolve(ch.value); state.locked=false; });
          chips.appendChild(b);
        });
        msg.querySelector('.bubble').appendChild(chips);
      }
      if (opts && opts.placeholder) input.placeholder = opts.placeholder;
      state.resolver = (text)=>{ if (state.locked) return; state.locked=true; const val=(text||'').trim(); if (!val && !(opts&&opts.allowEmpty)) { state.locked=false; return; } user(val||''); resolve(val); state.locked=false; };
    });
  }

  function bindInput(){
    sendBtn.addEventListener('click', ()=>{ const fn=state.resolver; if (fn){ const v=input.value; input.value=''; fn(v); }});
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ e.preventDefault(); sendBtn.click(); }});
  }

  const GENDERS = [ {label:'Male',value:'male'}, {label:'Female',value:'female'} ];
  const STATUS  = [ {label:'Living',value:'living'}, {label:'Deceased',value:'deceased'}, {label:'Skip', value:'__skip'} ];

  async function loadPerson(){
    if (!pid) return { ok:false };
  try { const r=await fetch(`/api/person_get.php?id=${encodeURIComponent(pid)}`); return await r.json(); } catch(_){ return { ok:false }; }
  }

  function toDMY(s){ try { return window.DateUtils?.toDMY(s) || ''; } catch(_){ return ''; } }
  function toISO(s){ try { return window.DateUtils?.toISODate(s) || ''; } catch(_){ return ''; } }
  function isISO(s){ try { return window.DateUtils?.isISODate(s) || false; } catch(_){ return false; } }

  async function run(){
    if (!pid){ bot('Missing person id.'); return; }
    const pre = await loadPerson();
    if (!pre || !pre.ok){ bot('Unable to load person.'); return; }
    const p = pre.person || {};
    // Header message
  bot(`Editing <strong>${escapeHtml(p.full_name||('#'+pid))}</strong>. You can keep existing values using the Skip/Keep options.`);

    // Photo preview + upload option
    (function renderPhotoRow(){
      const current = p.photo_site ? `<div style="margin-top:8px"><img src="${escapeHtml(p.photo_site)}" alt="Photo" style="max-width:160px;border-radius:10px;border:1px solid #e2e8f0;"/></div>` : '<div class="muted" style="margin-top:6px">No photo set.</div>';
      const m = bot(`Photo${current}`);
      const chips = document.createElement('div'); chips.className='chips';
      const up = document.createElement('button'); up.type='button'; up.className='chip'; up.textContent = p.photo_site ? 'Change photo' : 'Upload photo';
      const skip = document.createElement('button'); skip.type='button'; skip.className='chip'; skip.textContent='Skip';
      chips.appendChild(up); chips.appendChild(skip);
      m.querySelector('.bubble').appendChild(chips);

      function done(){ chips.remove(); }

      skip.addEventListener('click', ()=>{ if (state.locked) return; state.locked=true; user('Skip'); done(); state.locked=false; });
      up.addEventListener('click', ()=>{
        if (state.locked) return; state.locked=true;
        fileInput.value = '';
        fileInput.onchange = async () => {
          const file = fileInput.files && fileInput.files[0];
          if (!file){ state.locked=false; return; }
          user('Uploading photo…');
          try {
            const fd = new FormData();
            fd.append('person_id', String(pid));
            fd.append('photo', file);
            const res = await fetch('/api/upload_person_photo.php', { method:'POST', body: fd });
            const j = await res.json();
            if (j && j.ok && j.url){
              bot('Photo uploaded.');
              p.photo_site = j.url; // update local preview
              // Re-render thumbnail below
              const img = document.createElement('img');
              img.src = j.url; img.alt='Photo'; img.style.maxWidth='160px'; img.style.borderRadius='10px'; img.style.border='1px solid #e2e8f0';
              const holder = document.createElement('div'); holder.style.marginTop='8px'; holder.appendChild(img);
              m.querySelector('.bubble').appendChild(holder);
            } else {
              bot('<span class="muted" style="color:#b33;">Upload failed.</span>');
            }
          } catch(_){
            bot('<span class="muted" style="color:#b33;">Upload error.</span>');
          } finally {
            done(); state.locked=false; scrollBottom();
          }
        };
        fileInput.click();
      });
    })();

    const updates = { id: pid };

    // Full name
    const nameAction = await ask('Full name?', { hintHtml: `Current: <strong>${escapeHtml(p.full_name||'')}</strong>`, choices:[{label:'Keep', value:'__keep'}], placeholder:'Enter new full name' });
    if (nameAction !== '__keep' && nameAction !== '') { updates.full_name = nameAction; }

    // Gender
    const gender = await ask('Gender?', { choices:[...GENDERS, {label:'Skip', value:'__skip'}] });
    if (gender !== '__skip' && gender) { updates.gender = gender; }

    // Status
    const status = await ask('Status?', { choices: STATUS });
    let isDeceased = (p.is_alive===false);
    if (status !== '__skip') { isDeceased = (status === 'deceased'); updates.is_alive = !isDeceased; }

    //  Birth Year (YYYY) (optional)
  const curBDMY = toDMY(p.birth_date) || (p.birth_date||'');
  const bd = await ask(' Birth Year (YYYY)', { hintHtml:`Current: <strong>${escapeHtml(curBDMY||'—')}</strong>`, choices:[{label:'Skip', value:'__skip'}], placeholder:'e.g. 1990 or 05-07-1990' });
    if (bd !== '__skip') {
  const iso = toISO(bd);
  if (iso && isISO(iso)) updates.birth_date = iso; else if (bd==='') updates.birth_date = null; else bot('<span class="muted" style="color:#b33;">Ignored invalid date; use YYYY or DD-MM-YYYY.</span>');
    }

    // Birth place (optional)
    const bp = await ask('Birth place?', { hintHtml:`Current: <strong>${escapeHtml(p.birth_place||'—')}</strong>`, choices:[{label:'Skip', value:'__skip'}], placeholder:'City, Country' });
    if (bp !== '__skip') { updates.birth_place = bp || null; }

    // Death block (only if deceased)
    if (isDeceased){
  const curDDMY = toDMY(p.death_date) || (p.death_date||'');
  const dd = await ask('Death Year (YYYY)?', { hintHtml:`Current: <strong>${escapeHtml(curDDMY||'—')}</strong>`, choices:[{label:'Skip', value:'__skip'}], placeholder:'e.g. 2020' });
      if (dd !== '__skip'){
        const iso = toISO(dd);
  if (iso && isISO(iso)) updates.death_date = iso; else if (dd==='') updates.death_date = null; else bot('<span class="muted" style="color:#b33;">Ignored invalid date; use YYYY or DD-MM-YYYY.</span>');
      }
      const bur = await ask('Burial place?', { hintHtml:`Current: <strong>${escapeHtml(p.burial_place||'—')}</strong>`, choices:[{label:'Skip', value:'__skip'}], placeholder:'Cemetery / Place' });
      if (bur !== '__skip') { updates.burial_place = bur || null; }
    }

    // Contact and profession should be hidden for deceased
    if (!isDeceased){
      // Profession (optional)
      // Email (optional)
      const email = await ask('Email?', { hintHtml:`Current: <strong>${escapeHtml(p.email||'—')}</strong>`, choices:[{label:'Skip', value:'__skip'}], placeholder:'e.g. me@example.com' });
      if (email !== '__skip') { updates.email = email || null; }
      // Mobile (optional)
     
    } else {
      // Optional gentle note for deceased persons
      bot('<span class="muted">Contact fields are hidden for deceased persons.</span>');
    }

    // Nothing to save?
    const keys = Object.keys(updates).filter(k=>k!=='id');
    if (keys.length === 0){
      const nav = await ask('No changes made. Go back to edit tree?', { choices:[{label:'Yes', value:'yes'},{label:'Stay', value:'stay'}] });
  if (nav==='yes'){ location.href = '/pages/EN/edit_tree.html'; }
      return;
    }

    bot('Saving…');
    try {
  const res = await fetch('/api/person_update.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(updates) });
      const j = await res.json();
      if (!j || !j.ok){ bot('<span class="muted" style="color:#b33;">Save failed.</span>'); return; }
      bot('Saved. Returning to edit tree…');
  setTimeout(()=>{ location.href = '/pages/EN/edit_tree.html'; }, 900);
    } catch(_){ bot('<span class="muted" style="color:#b33;">Save error.</span>'); }
  }

  window.addEventListener('load', ()=>{ bindInput(); run(); });
})();
