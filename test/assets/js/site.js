(() => {
  const cart = new Map();
  const fmt = n => new Intl.NumberFormat('da-DK',{style:'currency',currency:'DKK',maximumFractionDigits:0}).format(n);
  const list = document.querySelector('[data-cart-lines]');
  const total = document.querySelector('[data-cart-total]');
  const payload = document.querySelector('[data-cart-payload]');
  const render = () => {
    if (!list) return;
    list.innerHTML = '';
    let sum = 0;
    [...cart.values()].forEach(item => {
      sum += item.price * item.qty;
      const row = document.createElement('div'); row.className='cart-line';
      row.innerHTML = `<span>${item.qty} × ${item.title}</span><strong>${fmt(item.price*item.qty)}</strong>`;
      list.appendChild(row);
    });
    if (!cart.size) list.innerHTML = '<p style="color:#736b65">Vælg retter fra menuen.</p>';
    if (total) total.textContent = fmt(sum);
    if (payload) payload.value = JSON.stringify([...cart.values()]);
  };
  document.querySelectorAll('[data-add-item]').forEach(btn => btn.addEventListener('click', async () => {
    const item = {id:+btn.dataset.id,title:btn.dataset.title,price:+btn.dataset.price,qty:1};
    if (cart.has(item.id)) cart.get(item.id).qty++; else cart.set(item.id,item);
    btn.textContent='Tilføjet ✓'; setTimeout(()=>btn.textContent='Tilføj',700); render();
    fetch(btn.dataset.trackUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`type=add_to_cart&item_id=${item.id}`}).catch(()=>{});
  }));
  document.querySelectorAll('[data-menu-track]').forEach(el => el.addEventListener('click',()=>fetch(el.dataset.trackUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`type=menu_click&item_id=${el.dataset.id}`}).catch(()=>{})));
  render();

  const panels=[...document.querySelectorAll('[data-booking-panel]')]; let step=0;
  const show=i=>{step=Math.max(0,Math.min(panels.length-1,i));panels.forEach((p,n)=>p.classList.toggle('is-active',n===step));document.querySelectorAll('[data-step-label]').forEach((p,n)=>p.classList.toggle('is-active',n===step));};
  document.querySelectorAll('[data-next]').forEach(b=>b.addEventListener('click',()=>show(step+1)));
  document.querySelectorAll('[data-prev]').forEach(b=>b.addEventListener('click',()=>show(step-1)));
})();
