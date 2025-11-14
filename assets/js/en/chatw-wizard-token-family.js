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
    unionSel.innerHTML = '<option value="">Loading...</option>';
    try {
  const j = await getJSON('/api/token_family_unions.php');
      if (!j.ok){ unionSel.innerHTML = '<option value="">Failed to load couples</option>'; return; }
      if (!j.unions || j.unions.length === 0){ unionSel.innerHTML = '<option value="">No couples found. Create one first.</option>'; return; }
      unionSel.innerHTML = j.unions.map(u=>`<option value="${u.union_id}">${u.label}</option>`).join('');
    } catch (e){ unionSel.innerHTML = '<option value="">Error loading</option>'; }
  }

  issueBtn.addEventListener('click', async ()=>{
    const union_id = parseInt(unionSel.value || '0', 10);
    if (!union_id){ alert('Please choose a couple.'); return; }
    const payload = { union_id, expected_children: parseInt(expected.value||'0',10) };
  const j = await getJSON('/api/family_token_issue.php', payload);
    if (!j.ok){ alert('Failed to issue token: '+ (j.error||'unknown')); return; }
    urlInput.value = j.code || j.url || '';
    resBox.style.display = '';
  });

  copyBtn.addEventListener('click', ()=>{ urlInput.select(); document.execCommand('copy'); copyBtn.textContent='Copied'; setTimeout(()=>copyBtn.textContent='Copy', 1200); });

  loadUnions();
})();
