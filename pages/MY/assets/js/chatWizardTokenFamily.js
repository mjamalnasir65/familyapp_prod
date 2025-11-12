(function(){
  const $ = s=>document.querySelector(s);
  const unionSel = $('#union');
  const expected = $('#expected');
  const issueBtn = $('#issueBtn');
  const resBox = $('#result');
  const urlInput = $('#url');
  const copyBtn = $('#copy');

  async function getJSON(url, body){
    const r = await fetch(url, body ? { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) } : undefined);
    return r.json();
  }

  async function loadUnions(){
  unionSel.innerHTML = '<option value="">Memuatkan...</option>';
    try {
  const j = await getJSON('/api/token_family_unions.php');
  if (!j.ok){ unionSel.innerHTML = '<option value="">Gagal memuatkan pasangan</option>'; return; }
  if (!j.unions || j.unions.length === 0){ unionSel.innerHTML = '<option value="">Tiada pasangan ditemui. Cipta dahulu.</option>'; return; }
      unionSel.innerHTML = j.unions.map(u=>`<option value="${u.union_id}">${u.label}</option>`).join('');
  } catch (e){ unionSel.innerHTML = '<option value="">Ralat memuatkan</option>'; }
  }

  issueBtn.addEventListener('click', async ()=>{
    const union_id = parseInt(unionSel.value || '0', 10);
  if (!union_id){ alert('Sila pilih pasangan.'); return; }
    const payload = { union_id, expected_children: parseInt(expected.value||'0',10) };
  const j = await getJSON('/api/family_token_issue.php', payload);
  if (!j.ok){ alert('Gagal menjana token: '+ (j.error||'tidak diketahui')); return; }
    urlInput.value = j.code || j.url || '';
    resBox.style.display = '';
  });

  copyBtn.addEventListener('click', ()=>{ urlInput.select(); document.execCommand('copy'); copyBtn.textContent='Disalin'; setTimeout(()=>copyBtn.textContent='Salin', 1200); });

  loadUnions();
})();
