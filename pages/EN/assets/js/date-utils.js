(function(){
  // Validator: accept 'YYYY' or ISO 'YYYY-MM-DD'
  function isISODate(s){ return typeof s==='string' && /^\d{4}(-\d{2}-\d{2})?$/.test(s); }

  // Convert inputs to server format, accepting:
  //  - 'YYYY' -> 'YYYY' (year-only)
  //  - 'DD-MM-YYYY' (and DD/MM/YYYY, DD.MM.YYYY) -> 'YYYY-MM-DD'
  //  - already ISO 'YYYY-MM-DD' -> unchanged
  function toISODate(s){
    if (s === null || s === undefined) return '';
    s = (''+s).trim();
    if (!s) return '';
    if (/^\d{4}$/.test(s)) return s; // year only
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s; // ISO already

    // Normalize separators to hyphen and parse DMY
    const norm = s.replace(/[\/.]/g,'-');
    const m = /^(\d{1,2})-(\d{1,2})-(\d{4})$/.exec(norm);
    if (!m) return '';
    const dd = parseInt(m[1],10), mm = parseInt(m[2],10), yyyy = parseInt(m[3],10);
    if (yyyy < 1000 || mm < 1 || mm > 12 || dd < 1 || dd > 31) return '';
    const dt = new Date(yyyy, mm-1, dd);
    if (dt.getFullYear() !== yyyy || (dt.getMonth()+1) !== mm || dt.getDate() !== dd) return '';
    const pad = n => (n<10?'0':'') + n;
    return `${yyyy}-${pad(mm)}-${pad(dd)}`;
  }

  // Display helper: ISO -> DMY; if year-only, return as-is
  function toDMY(s){
    if (typeof s !== 'string' || !s) return '';
    if (/^\d{4}$/.test(s)) return s; // year-only display
    if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return '';
    const [y,m,d] = s.split('-');
    return `${d}-${m}-${y}`;
  }

  // Optional helper to wire text inputs with placeholder
  function attachDateInputs(selector){
    try{
      document.querySelectorAll(selector).forEach(inp=>{
        if (!inp.getAttribute('placeholder')) inp.setAttribute('placeholder','YYYY or DD-MM-YYYY');
        inp.addEventListener('blur', ()=>{
          const iso = toISODate(inp.value);
          if (iso) inp.dataset.iso = iso; // can be YYYY or YYYY-MM-DD
        });
      });
    }catch(_){}
  }

  window.DateUtils = { isISODate, toISODate, toDMY, attachDateInputs };
})();
