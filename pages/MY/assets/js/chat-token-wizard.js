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
  if (!tok){ say('Pautan tiada atau tidak sah.'); return; }
  const j = await getJSON('/api/family_token_validate.php?tok='+encodeURIComponent(tok));
  if (!j.ok){ say('Pautan ini tidak sah atau telah tamat tempoh.'); return; }
    ctx.family_id=j.family_id; ctx.family_name=j.family_name; ctx.union_id=j.union_id; ctx.expected=j.expected_children||0; ctx.added=j.children_added||0;

  const names = (j.parents && (j.parents.a_name || j.parents.b_name)) ? `${j.parents.a_name||'Ibu/Bapa A'} + ${j.parents.b_name||'Ibu/Bapa B'}` : 'ibu bapa terpilih';
  say(`Kita sedang menambah anak bagi <b>${names}</b> dalam <b>${ctx.family_name || 'keluarga ini'}</b>.`);
  if (ctx.expected>0){ say(`Bilangan anak disasarkan: ${ctx.expected}. Ditambah setakat ini: ${ctx.added}.`); }
  say(`Masukkan nama penuh anak dan pasangan (jika ada). Taip <b>skip</b> apabila selesai.`);
  }

  async function handle(){
    const name = (childInput.value || '').trim();
    const partner = (partnerInput.value || '').trim();
    if (!name) return;
    childInput.value=''; partnerInput.value='';

  if (name.toLowerCase()==='skip'){ say('Selesai. Mengalih ke pokok...'); setTimeout(()=>{ location.href = './tree.html'; }, 800); return; }

    you(`${name}${partner? ' + '+partner : ''}`);

    const payload = { tok, child_name:name, child_gender:null, with_partner: !!partner, partner_name: partner };
  const res = await getJSON('/api/token_family_save_child.php', payload);
  if (!res.ok){ say('Simpan gagal. Sila cuba lagi.'); return; }

    ctx.added = res.children_added ?? ctx.added;
    if (res.new_union_id){
      say(`Penyatuan dicipta untuk <b>${name}</b>.`);
      // Tanya untuk cipta keluarga bersarang
      const wantNested = confirm('Cipta keluarga bersarang (Keturunan) bagi pasangan ini dan benarkan mereka urus cabang?');
      if (wantNested){
  const nf = await getJSON('/api/nested_family_create.php', { union_id: res.new_union_id, kind:'D', expected_children: ctx.expected });
        if (nf && nf.ok){ say(`Keluarga bersarang ${nf.label} dicipta.`); } else { say('Tidak dapat mencipta keluarga bersarang sekarang.'); }
      }
    } else {
      say(`Anak <b>${name}</b> ditambah.`);
    }

    const remaining = res.remaining ?? (ctx.expected>0 ? Math.max(0, ctx.expected - ctx.added) : null);
    if (remaining !== null){
      say(`Baki sasaran: ${remaining}. Masukkan anak lain atau taip <b>skip</b>.`);
    } else {
      say('Masukkan anak lain atau taip <b>skip</b>.');
    }
  }

  sendBtn.addEventListener('click', handle);
  childInput.addEventListener('keydown', e=>{ if (e.key==='Enter'){ handle(); }});
  partnerInput.addEventListener('keydown', e=>{ if (e.key==='Enter'){ handle(); }});

  boot();
})();
