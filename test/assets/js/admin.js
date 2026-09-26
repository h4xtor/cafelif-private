(() => {
  const sidebar = document.querySelector('#admin-sidebar');
  const overlay = document.querySelector('[data-admin-overlay]');
  const toggle = document.querySelector('[data-admin-toggle]');
  const close = () => { sidebar?.classList.remove('is-open'); overlay?.classList.remove('is-open'); };
  toggle?.addEventListener('click', () => { sidebar?.classList.toggle('is-open'); overlay?.classList.toggle('is-open'); });
  overlay?.addEventListener('click', close);

  document.querySelectorAll('[data-confirm]').forEach(el => el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Er du sikker?')) e.preventDefault();
  }));

  const input = document.querySelector('[data-image-input]');
  const preview = document.querySelector('[data-image-preview]');
  input?.addEventListener('change', () => {
    const file = input.files?.[0]; if (!file || !preview) return;
    preview.src = URL.createObjectURL(file);
  });
})();


// Better mobile/admin experience
(() => {
  const sidebar = document.querySelector('#admin-sidebar');
  const overlay = document.querySelector('[data-admin-overlay]');
  document.querySelectorAll('.admin-nav a, .admin-sidebar__footer a').forEach(a => {
    a.addEventListener('click', () => {
      sidebar?.classList.remove('is-open');
      overlay?.classList.remove('is-open');
    });
  });

  let dirty = false;
  document.querySelectorAll('form input, form textarea, form select').forEach(el => {
    el.addEventListener('change', () => { dirty = true; });
    el.addEventListener('input', () => { dirty = true; });
  });
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => { dirty = false; }));
  window.addEventListener('beforeunload', e => {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
})();
