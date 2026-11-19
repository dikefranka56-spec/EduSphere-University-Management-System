
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-confirm]').forEach(b=>b.addEventListener('click',e=>{if(!confirm(b.dataset.confirm)) e.preventDefault();}));
  const q=document.getElementById('q'); if(q){q.addEventListener('input',()=>{const v=q.value.toLowerCase(); document.querySelectorAll('table tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(v)?'':'none'})})}
});
