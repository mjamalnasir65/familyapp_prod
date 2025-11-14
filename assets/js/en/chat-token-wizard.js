(function(){
  const $ = s=>document.querySelector(s);
  const chat = $('#chat');
  const sendBtn = $('#sendBtn');
  const childInput = $('#childName');
  const partnerInput = $('#partnerName');
  const tok = new URL(location.href).searchParams.get('tok') || '';
  let ctx = { family_id:null, family_name:null, union_id:null, expected:0, added:0 };

  function say(html){ const b=document.createElement('div'); b.className='bubble bot'; b.innerHTML=html; chat.appendChild(b); chat.scrollTop = chat.scrollHeight; }
  function you(text){ const b=document.createElement('div'); b.className='bubble me'; b.textContent=text; chat.appendChild(b); chat.scrollTop = chat.scrollHeight; }

  async function getJSON(url, body){
    const r = await fetch(url, body ? { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) } : undefined);
    return r.json();
  }

  async function boot(){
    if (!tok){ say('Missing or invalid link.'); return; }
  const j = await getJSON('/api/family_token_validate.php?tok='+encodeURIComponent(tok));
    if (!j.ok){ say('This link is invalid or expired.'); return; }
    ctx.family_id=j.family_id; ctx.family_name=j.family_name; ctx.union_id=j.union_id; ctx.expected=j.expected_children||0; ctx.added=j.children_added||0;

    const names = (j.parents && (j.parents.a_name || j.parents.b_name)) ? `${j.parents.a_name||'Parent A'} + ${j.parents.b_name||'Parent B'}` : 'the selected parents';
    say(`We’re adding children for <b>${names}</b> in <b>${ctx.family_name || 'this family'}</b>.`);
    if (ctx.expected>0){ say(`Expected children: ${ctx.expected}. Added so far: ${ctx.added}.`); }
    say(`Enter a child’s full name and optional partner. Type <b>skip</b> when done.`);
  }

  async function handle(){
    const name = (childInput.value || '').trim();
    const partner = (partnerInput.value || '').trim();
    if (!name) return;
    childInput.value=''; partnerInput.value='';

    if (name.toLowerCase()==='skip'){ say('All set. Redirecting to tree...'); setTimeout(()=>{ location.href = './tree.html'; }, 800); return; }

    you(`${name}${partner? ' + '+partner : ''}`);

    const payload = { tok, child_name:name, child_gender:null, with_partner: !!partner, partner_name: partner };
  const res = await getJSON('/api/token_family_save_child.php', payload);
    if (!res.ok){ say('Save failed. Please try again.'); return; }

    ctx.added = res.children_added ?? ctx.added;
    if (res.new_union_id){
      say(`Created union for <b>${name}</b>.`);
      // Ask to create nested family D with same expected children
      const wantNested = confirm('Create a nested family (Descendants) for this couple and allow them to manage their branch?');
      if (wantNested){
  const nf = await getJSON('/api/nested_family_create.php', { union_id: res.new_union_id, kind:'D', expected_children: ctx.expected });
        if (nf && nf.ok){ say(`Nested family ${nf.label} created.`); } else { say('Could not create nested family right now.'); }
      }
    } else {
      say(`Added child <b>${name}</b>.`);
    }

    const remaining = res.remaining ?? (ctx.expected>0 ? Math.max(0, ctx.expected - ctx.added) : null);
    if (remaining !== null){
      say(`Remaining target: ${remaining}. Enter another child or type <b>skip</b>.`);
    } else {
      say('Enter another child or type <b>skip</b>.');
    }
  }

  sendBtn.addEventListener('click', handle);
  childInput.addEventListener('keydown', e=>{ if (e.key==='Enter'){ handle(); }});
  partnerInput.addEventListener('keydown', e=>{ if (e.key==='Enter'){ handle(); }});

  boot();
})();
