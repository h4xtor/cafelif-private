/**
 * Café LIF v3 — Editorial Nordic
 */
(() => {
  'use strict';

  const qs = (sel, scope = document) => scope.querySelector(sel);
  const qsa = (sel, scope = document) => [...scope.querySelectorAll(sel)];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'\"]/g, ch => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    "'": '&#39;',
    '"': '&quot;'
  }[ch]));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const SCROLL_HEADS = {
    oplevelse: '#oplevelse-head',
    eva: '#eva-head',
    galleri: '#galleri-head',
    menu: '#menu-head',
    moedeforplejning: '#moedeforplejning-head',
    sport: '#sport-head',
    selskaber: '#selskaber-head',
    anmeldelser: '#anmeldelser-head',
    faq: '#faq-head',
    kontakt: '#kontakt-head'
  };

  const getScrollOffset = () => {
    const header = qs('#header');
    return (header?.getBoundingClientRect().height || 72) + 12;
  };

  const scrollToTarget = (target, behavior = reduced ? 'auto' : 'smooth') => {
    if (!target) return;
    const scroll = (anim) => {
      const top = target.getBoundingClientRect().top + window.scrollY - getScrollOffset();
      window.scrollTo({ top: Math.max(0, top), behavior: anim });
    };
    scroll(behavior);
    if (behavior === 'smooth' && !reduced) {
      const correct = () => scroll('auto');
      if ('onscrollend' in window) {
        window.addEventListener('scrollend', correct, { once: true });
      } else {
        setTimeout(correct, 700);
      }
    }
  };

  /* Header */
  function initHeader() {
    const header = qs('#header');
    const onScroll = () => header?.classList.toggle('is-scrolled', window.scrollY > 20);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* Desktop Nav Dropdowns — hover open, close on leave & link click */
  function initNavDropdowns() {
    const dropdowns = qsa('[data-nav-dropdown]');
    if (!dropdowns.length) return;

    const canHover = window.matchMedia('(hover: hover)').matches;
    const timers = new Map();
    const CLOSE_DELAY = 180;

    const setOpen = (dd, open) => {
      dd.classList.toggle('is-open', open);
      qs('.nav-dropdown__trigger', dd)?.setAttribute('aria-expanded', String(open));
    };

    const closeAll = () => dropdowns.forEach(dd => setOpen(dd, false));

    const scheduleClose = (dd) => {
      clearTimeout(timers.get(dd));
      timers.set(dd, setTimeout(() => setOpen(dd, false), CLOSE_DELAY));
    };

    dropdowns.forEach(dd => {
      const trigger = qs('.nav-dropdown__trigger', dd);

      if (canHover) {
        dd.addEventListener('mouseenter', () => {
          clearTimeout(timers.get(dd));
          closeAll();
          setOpen(dd, true);
        });
        dd.addEventListener('mouseleave', () => scheduleClose(dd));
      } else if (trigger) {
        trigger.addEventListener('click', e => {
          e.preventDefault();
          e.stopPropagation();
          const open = !dd.classList.contains('is-open');
          closeAll();
          setOpen(dd, open);
        });
      }

      qsa('.nav-dropdown__link', dd).forEach(link => {
        link.addEventListener('click', () => {
          clearTimeout(timers.get(dd));
          setOpen(dd, false);
          closeAll();
        });
      });
    });

    document.addEventListener('click', e => {
      if (e.target.closest('[data-nav-dropdown]')) return;
      closeAll();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeAll();
    });
  }

  /* Food gallery */
  function initFoodSlider() {
    const root = qs('[data-food-slider]');
    if (!root) return;

    const track = qs('.food-gallery__track', root);
    const slides = qsa('[data-slide]', root);
    const dotsWrap = qs('[data-slider-dots]', root);
    const progressEl = qs('[data-slider-progress]', root);
    const counterEl = qs('[data-slider-counter]', root);
    if (!track || !slides.length) return;

    const INTERVAL = 5000;
    let index = 0;
    let timer;

    const updateCounter = () => {
      if (counterEl) counterEl.textContent = `${index + 1} / ${slides.length}`;
    };

    const restartProgress = () => {
      if (!progressEl || reduced) return;
      progressEl.classList.remove('is-animating');
      void progressEl.offsetWidth;
      progressEl.classList.add('is-animating');
    };

    const go = (i) => {
      index = (i + slides.length) % slides.length;
      track.style.transform = `translateX(-${index * 100}%)`;
      slides.forEach((s, n) => s.classList.toggle('is-active', n === index));
      qsa('.food-gallery__dot', root).forEach((d, n) => d.classList.toggle('is-active', n === index));
      updateCounter();
      restartProgress();
    };

    slides.forEach((_, i) => {
      if (!dotsWrap) return;
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = `food-gallery__dot${i === 0 ? ' is-active' : ''}`;
      dot.setAttribute('aria-label', `Gå til billede ${i + 1}`);
      dot.addEventListener('click', () => { go(i); resetAuto(); });
      dotsWrap.appendChild(dot);
    });

    const resetAuto = () => {
      clearInterval(timer);
      if (!reduced) timer = setInterval(() => go(index + 1), INTERVAL);
      restartProgress();
    };

    qs('[data-slider-prev]', root)?.addEventListener('click', () => { go(index - 1); resetAuto(); });
    qs('[data-slider-next]', root)?.addEventListener('click', () => { go(index + 1); resetAuto(); });

    go(0);
    resetAuto();
  }

  const applyMenuFilter = (filter) => {
    const btn = qs(`[data-menu-category="${filter}"]`);
    btn?.click();
  };

  /* Mobile Nav Accordion */
  function initMobileAccordion() {
    qsa('[data-mobile-accordion]').forEach(group => {
      const trigger = qs('.mobile-nav__group-trigger', group);
      trigger?.addEventListener('click', () => {
        const open = group.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded', String(open));
      });
    });
  }

  /* Mobile Nav */
  function initMobileNav() {
    const nav = qs('#mobile-nav');
    const toggle = qs('#nav-toggle');
    if (!nav || !toggle) return;

    const closeEls = qsa('[data-nav-close]', nav);

    const open = () => {
      nav.classList.add('is-open');
      nav.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    };

    const close = () => {
      nav.classList.remove('is-open');
      nav.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    };

    toggle.addEventListener('click', () => {
      nav.classList.contains('is-open') ? close() : open();
    });
    closeEls.forEach(el => el.addEventListener('click', close));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) close();
    });
  }

  /* Active Nav */
  const NAV_SECTIONS = ['top', 'oplevelse', 'eva', 'galleri', 'menu', 'moedeforplejning', 'sport', 'selskaber', 'anmeldelser', 'faq', 'kontakt'];

  const SECTION_TO_DROPDOWN = {
    oplevelse: 0,
    eva: 0,
    anmeldelser: 0,
    menu: 1,
    galleri: 2,
    sport: 0,
    moedeforplejning: 3,
    selskaber: 3,
    kontakt: 4,
    faq: 4
  };

  function setActiveNav(sectionId) {
    const id = sectionId || 'top';

    qsa('[data-nav-section]').forEach(link => {
      link.classList.toggle('is-active', link.dataset.navSection === id);
    });

    qsa('[data-nav-dropdown]').forEach((dd, i) => {
      const match = SECTION_TO_DROPDOWN[id] === i;
      dd.classList.toggle('is-active', match && id !== 'top');
    });
  }

  function initActiveNav() {
    const heads = Object.entries(SCROLL_HEADS)
      .map(([id, sel]) => ({ id, el: qs(sel) }))
      .filter(h => h.el);

    const update = () => {
      const offset = getScrollOffset();
      let current = 'top';

      heads.forEach(({ id, el }) => {
        if (el.getBoundingClientRect().top <= offset + 24) {
          current = id;
        }
      });

      setActiveNav(current);
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
  }

  /* Reveal */
  function initReveal() {
    const els = qsa('[data-reveal]');
    els.forEach(el => {
      const d = el.dataset.revealDelay;
      if (d) el.style.setProperty('--reveal-delay', `${d}ms`);
    });

    if (reduced || !('IntersectionObserver' in window)) {
      els.forEach(el => el.classList.add('is-visible'));
      return;
    }

    const obs = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });

    els.forEach(el => obs.observe(el));
  }

  /* Menu Filter */
  function initMenuFilter() {
    const root = qs('[data-order-menu]');
    if (!root) return;

    const buttons = qsa('[data-menu-category]', root);
    const items = qsa('[data-menu-item]', root);
    const sections = qsa('[data-menu-section]', root);

    const apply = (filter) => {
      buttons.forEach(btn => {
        const active = btn.dataset.menuCategory === filter;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', String(active));
      });
      items.forEach(card => {
        const show = filter === 'all' || card.dataset.category === filter;
        card.classList.toggle('is-hidden', !show);
      });
      sections.forEach(section => {
        const cat = section.dataset.category;
        const show = filter === 'all' || cat === filter;
        section.classList.toggle('is-hidden', !show);
      });
    };

    buttons.forEach(btn => btn.addEventListener('click', () => apply(btn.dataset.menuCategory)));
    apply('all');
  }

  /* Order Builder */
  function initOrderBuilder() {
    const root = qs('[data-order-app]') || qs('[data-order-menu]');
    if (!root) return;

    const cart = new Map();
    const fmt = new Intl.NumberFormat('da-DK', { style: 'currency', currency: 'DKK', maximumFractionDigits: 0 });

    const lines = qs('[data-cart-lines]', root);
    const empty = qs('[data-cart-empty]', root);
    const countEl = qs('[data-cart-count]', root);
    const fabCount = qs('[data-cart-fab-count]');
    const fabLabel = qs('[data-cart-fab-label]');
    const totalEl = qs('[data-cart-total]', root);
    const noteEl = qs('[data-cart-note]', root);
    const panel = qs('#cart-panel');
    const fab = qs('#cart-fab');

    // Andre sider uden kurv skal fortsat kunne bruge samme script.
    if (!lines || !empty || !qsa('[data-cart-add]', root).length) return;

    const getData = (card, trigger = null) => {
      const itemId = card.dataset.id || '';
      const baseName = card.dataset.name || 'Ret';
      const normalPrice = Number(card.dataset.price || 0);
      const xlPrice = Number(card.dataset.xlPrice || 0);
      const requestedSize = trigger?.dataset.size === 'xl' ? 'xl' : 'normal';
      const size = requestedSize === 'xl' && xlPrice > 0 ? 'xl' : 'normal';
      const hasSizes = xlPrice > 0;
      const sizeLabel = hasSizes ? (size === 'xl' ? 'XL' : 'Alm.') : '';
      const price = size === 'xl' ? xlPrice : normalPrice;
      const name = sizeLabel ? `${baseName} – ${sizeLabel}` : baseName;
      const priceLabel = price > 0 ? fmt.format(price) : (card.dataset.priceLabel || 'Efter aftale');
      const unit = card.dataset.unit || 'item';
      return { itemId, baseName, name, size, sizeLabel, price, priceLabel, unit };
    };

    const getId = (d) => d.itemId ? `${String(d.itemId)}:${d.size}` : `${d.name}-${d.price}`.toLowerCase().replace(/\s+/g, '-');

    const updateUI = () => {
      lines.innerHTML = '';
      const entries = [...cart.values()];
      window.cafelifCartEntries = entries;
      empty.hidden = entries.length > 0;

      entries.forEach(item => {
        const line = document.createElement('div');
        line.className = 'order-line';
        line.innerHTML = `
          <div class="order-line__description">
            <strong>${escapeHtml(item.name)}</strong>
            <small>${item.price > 0 ? `${fmt.format(item.price)} pr. ${item.unit === 'person' ? 'person' : 'stk.'}` : escapeHtml(item.priceLabel)}</small>
          </div>
          <strong class="order-line__subtotal">${item.price > 0 ? fmt.format(item.price * item.qty) : escapeHtml(item.priceLabel)}</strong>
          <div class="order-line__controls">
            <button type="button" data-dec="${item.id}" aria-label="Fjern én">−</button>
            <output>${item.qty}</output>
            <button type="button" data-inc="${item.id}" aria-label="Tilføj én">+</button>
          </div>`;
        lines.appendChild(line);
      });

      const qty = entries.reduce((s, i) => s + i.qty, 0);
      const label = qty === 1 ? '1 valg' : `${qty} valg`;
      if (countEl) countEl.textContent = label;

      if (fabCount) {
        fabCount.textContent = qty;
        fabCount.hidden = qty === 0;
      }

      const sum = entries.reduce((s, i) => s + i.price * i.qty, 0);
      const totalLabel = sum > 0 ? fmt.format(sum) : '0 kr.';
      if (totalEl) totalEl.textContent = entries.length ? totalLabel : '0 kr.';
      const buttonTotalEl = qs('[data-cart-button-total]', root);
      if (buttonTotalEl) buttonTotalEl.textContent = entries.length ? totalLabel : '0 kr.';
      if (fabLabel) fabLabel.textContent = entries.length ? `${label} · ${totalLabel}` : 'Vælg retter med plus';
      if (fab) fab.classList.toggle('has-items', qty > 0);
    };

    const addItem = (card, trigger) => {
      if (!card) return;
      const data = getData(card, trigger);
      const id = getId(data);
      const existing = cart.get(id);
      existing ? existing.qty++ : cart.set(id, { id, ...data, qty: 1 });
      updateUI();

      const btn = trigger || card.querySelector('[data-cart-add]');
      if (btn) {
        const orig = btn.innerHTML;
        btn.classList.add('is-added');
        btn.innerHTML = 'Tilføjet ✓';
        setTimeout(() => { btn.innerHTML = orig; btn.classList.remove('is-added'); }, 900);
      }

      if (window.innerWidth < 1024 && fab) {
        fab.classList.add('is-pulsing');
        setTimeout(() => fab.classList.remove('is-pulsing'), 600);
      }
    };

    const openCart = () => {
      panel?.classList.add('is-open');
      document.body.classList.add('cart-open');
    };

    const closeCart = () => {
      panel?.classList.remove('is-open');
      document.body.classList.remove('cart-open');
    };

    root.addEventListener('click', e => {
      const add = e.target.closest('[data-cart-add]');
      if (add) { addItem(add.closest('[data-order-item], [data-menu-item]'), add); return; }

      const inc = e.target.closest('[data-inc]');
      if (inc) { const i = cart.get(inc.dataset.inc); if (i) { i.qty++; updateUI(); } return; }

      const dec = e.target.closest('[data-dec]');
      if (dec) {
        const i = cart.get(dec.dataset.dec);
        if (!i) return;
        i.qty--;
        if (i.qty <= 0) cart.delete(dec.dataset.dec);
        updateUI();
      }
    });

    fab?.addEventListener('click', () => {
      panel?.classList.contains('is-open') ? closeCart() : openCart();
    });

    document.addEventListener('click', e => {
      if (e.target.closest('[data-cart-close]')) { closeCart(); return; }
      if (!document.body.classList.contains('cart-open')) return;
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeCart();
    });

    qs('[data-cart-clear]', root)?.addEventListener('click', () => {
      cart.clear();
      if (noteEl) noteEl.value = '';
      updateUI();
    });

    const orderForm = qs('[data-cart-order-form]', root);
    const orderStatus = qs('[data-cart-status]', root);
    const confirmationChoice = qs('[data-confirmation-choice]', orderForm);
    const confirmationEmail = qs('[data-confirmation-email]', orderForm);
    const syncConfirmationEmail = () => { if (confirmationEmail) confirmationEmail.required = !!confirmationChoice?.checked; };
    confirmationChoice?.addEventListener('change', syncConfirmationEmail);
    syncConfirmationEmail();
    const setOrderStatus = (type, html) => {
      if (!orderStatus) return;
      orderStatus.innerHTML = html;
      orderStatus.classList.toggle('is-error', type === 'error');
      orderStatus.classList.toggle('is-success', type === 'success');
    };

    orderForm?.addEventListener('submit', async e => {
      syncConfirmationEmail();
      if (confirmationChoice?.checked && !confirmationEmail?.value.trim()) {
        e.preventDefault();
        setOrderStatus('error', 'Skriv din e-mailadresse for at få ordrebekræftelse, eller fjern fluebenet.');
        confirmationEmail?.focus();
        return;
      }
      if (confirmationEmail?.value.trim() && !confirmationEmail.checkValidity()) {
        e.preventDefault();
        setOrderStatus('error', 'Skriv en gyldig e-mailadresse.');
        confirmationEmail.focus();
        return;
      }
      if (!orderForm.checkValidity()) {
        e.preventDefault();
        setOrderStatus('error', 'Udfyld navn og telefon, og kontrollér dato og tidspunkt.');
        orderForm.querySelector(':invalid')?.focus();
        return;
      }
      const entries = [...cart.values()].filter(i => i.itemId);
      const cartInput = qs('#cart-json', orderForm);
      if (!entries.length) {
        e.preventDefault();
        setOrderStatus('error', 'Vælg mindst én ret eller mødeforplejning, før du sender bestillingen.');
        openCart();
        return;
      }
      if (cartInput) cartInput.value = JSON.stringify(entries.map(i => ({ id: i.itemId, qty: i.qty, size: i.size })));

      if (!window.fetch || !window.FormData) {
        setOrderStatus('success', 'Bestillingen sendes direkte til Café LIF...');
        return;
      }

      e.preventDefault();
      const submitBtn = qs('button[type="submit"]', orderForm);
      const originalText = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Sender...';
      }
      setOrderStatus('success', 'Bestillingen sendes sikkert til Café LIF...');

      try {
        const response = await fetch(orderForm.action, {
          method: 'POST',
          body: new FormData(orderForm),
          credentials: 'same-origin',
          cache: 'no-store',
          headers: {
            'X-Requested-With': 'fetch',
            'Accept': 'application/json'
          }
        });
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch (_) {}
        if (!json || !response.ok || !json.ok) {
          throw new Error(json?.message || text || 'Bestillingen kunne ikke sendes lige nu.');
        }

        const reference = escapeHtml(json.reference || json.phone || '');
        const phone = escapeHtml(json.phone || 'dit mobilnummer');
        const mailText = json.confirmation_email_requested
          ? (json.customer_mail_sent
            ? (json.test_mode ? `Test-mail er sendt til Café LIFs testmodtager. Oprindelig kunde-email: ${escapeHtml(confirmationEmail?.value || '')}.` : `Vi har sendt en mailbekræftelse til ${escapeHtml(confirmationEmail?.value || '')}.`)
            : 'Din ordre er gemt, men mailbekræftelsen kunne ikke sendes. Café LIF har stadig modtaget bestillingen.')
          : 'Du har valgt ikke at få mailbekræftelse.';
        setOrderStatus('success', `
          <strong>Tak — bestillingen er sendt til Café LIF.</strong><br>
          Dit ordrenummer er <strong>${reference}</strong><br>
          ${mailText}<br>
          Du kan følge status med mobilnummeret <strong>${phone}</strong> på knappen <strong>Tjek ordre</strong> på forsiden.
        `);
        cart.clear();
        orderForm.reset();
        syncConfirmationEmail();
        updateUI();
        if (orderStatus) {
          orderStatus.setAttribute('role', 'status');
          orderStatus.setAttribute('tabindex', '-1');
          orderStatus.focus({ preventScroll: true });
          orderStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } catch (err) {
        setOrderStatus('error', escapeHtml(err.message || 'Bestillingen kunne ikke sendes. Prøv igen eller ring til Café LIF.'));
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
          updateUI();
        }
      }
    });

    qs('[data-cart-to-form]', root)?.addEventListener('click', () => {
      const entries = [...cart.values()];
      const note = noteEl?.value.trim() || '';
      const form = qs('#inquiry-form');
      const msg = qs('#message', form);
      const type = qs('#type', form);

      let text = entries.length
        ? 'Bestilling fra hjemmesiden:\n' + entries.map(i =>
            `- ${i.qty} × ${i.name} ${i.price > 0 ? `(${fmt.format(i.price)})` : `(${i.priceLabel})`}`
          ).join('\n')
        : 'Jeg vil gerne have hjælp til en bestilling.';

      if (note) text += `\n\nBesked: ${note}`;
      if (type) type.value = 'Menuforespørgsel';
      if (msg) msg.value = text;

      closeCart();
      const formTarget = qs('#kontakt') || form;
      scrollToTarget(formTarget);
      setTimeout(() => msg?.focus(), reduced ? 0 : 500);
    });

    updateUI();
  }


  /* Order status lookup */
  function initOrderStatusLookup() {
    const modal = qs('#order-status-modal');
    const form = qs('[data-order-status-form]');
    const result = qs('[data-order-status-result]');
    if (!modal || !form || !result) return;

    const input = qs('input[name="phone"]', form);

    const open = () => {
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('order-status-open');
      setTimeout(() => input?.focus(), 80);
    };

    const close = () => {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('order-status-open');
    };

    qsa('[data-order-status-open]').forEach(btn => btn.addEventListener('click', e => {
      e.preventDefault();
      open();
    }));

    qsa('[data-order-status-close]', modal).forEach(btn => btn.addEventListener('click', close));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });

    try {
      const url = new URL(window.location.href);
      if (url.searchParams.get('ordrestatus') === '1' || url.hash === '#tjek-ordre') {
        setTimeout(open, 250);
      }
    } catch (_) {}

    const statusClass = status => `order-status-card order-status-card--${String(status || 'new').replace(/[^a-z-]/g, '')}`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[ch]));

    form.addEventListener('submit', async e => {
      e.preventDefault();
      const data = new FormData(form);
      const phone = String(data.get('phone') || '').trim();
      if (!phone) {
        result.innerHTML = '<div class="order-status-message is-error">Skriv dit mobilnummer først.</div>';
        return;
      }

      result.innerHTML = '<div class="order-status-message">Henter ordrestatus...</div>';

      try {
        // Brug altid en relativ URL, så funktionen virker både i /test/ og når siden er flyttet live til roden.
        const endpoint = new URL('actions/order-status.php', window.location.href).toString();
        const response = await fetch(endpoint, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          cache: 'no-store',
          headers: { 'X-Requested-With': 'fetch' }
        });

        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch (_) {}

        if (!json || !response.ok || !json.ok) {
          const message = json?.message || 'Vi fandt ingen ordre på nummeret. Tjek nummeret og prøv igen.';
          result.innerHTML = `<div class="order-status-message is-error">${escapeHtml(message)}</div>`;
          return;
        }

        const orders = json.orders || [];
        const cards = orders.map((order, index) => `
          <article class="${statusClass(order.status)}">
            <div class="order-status-card__top">
              <span>${index === 0 ? 'Seneste bestilling' : 'Tidligere bestilling'}</span>
              <strong>${escapeHtml(order.status_label)}</strong>
            </div>
            <p>${escapeHtml(order.status_note || '')}</p>
            <dl>
              <div><dt>Reference</dt><dd>${escapeHtml(order.reference || '-')}</dd></div>
              <div><dt>Modtaget</dt><dd>${escapeHtml(order.created_at || '-')}</dd></div>
              <div><dt>Ønsket afhentning</dt><dd>${escapeHtml(order.desired || 'Ikke angivet')}</dd></div>
              <div><dt>Estimeret beløb</dt><dd>${escapeHtml(order.total || '-')}</dd></div>
              <div><dt>Senest opdateret</dt><dd>${escapeHtml(order.updated_at || '-')}</dd></div>
            </dl>
          </article>
        `).join('');

        result.innerHTML = `
          <div class="order-status-success">
            <p class="order-status-success__number">Mobilnummer til statusopslag: <strong>${escapeHtml(json.phone)}</strong></p>
            <p class="order-status-success__hint">Vi viser de seneste ${orders.length} bestilling${orders.length === 1 ? '' : 'er'} på dette mobilnummer, så flere ordrer efter hinanden kan følges hver for sig.</p>
            ${cards}
          </div>
        `;
      } catch (err) {
        result.innerHTML = '<div class="order-status-message is-error">Ordrestatus kunne ikke hentes lige nu. Prøv igen om lidt.</div>';
      }
    });
  }

  /* Form */
  function initForm() {
    const form = qs('#inquiry-form');
    if (!form) return;

    const status = qs('[data-form-status]', form);
    const setStatus = (type, text) => {
      status.textContent = text;
      status.className = `form-status is-${type}`;
    };

    form.addEventListener('submit', e => {
      e.preventDefault();
      const data = new FormData(form);
      const name = (data.get('name') || '').trim();
      const email = (data.get('email') || '').trim();
      const phone = (data.get('phone') || '').trim();
      const type = data.get('type') || 'Forespørgsel';
      const persons = data.get('persons') || '';
      const date = data.get('date') || '';
      const message = (data.get('message') || '').trim();

      if (!name || !email || !phone || !message) {
        setStatus('error', 'Udfyld venligst navn, telefon, e-mail og besked.');
        return;
      }

      const cartEntries = (window.cafelifCartEntries || []).filter(i => i.itemId);
      if (cartEntries.length > 0 && type === 'Menuforespørgsel') {
        const cartInput = qs('#cart-json', form);
        if (cartInput) cartInput.value = JSON.stringify(cartEntries.map(i => ({ id: i.itemId, qty: i.qty, size: i.size })));
        setStatus('success', 'Bestillingen sendes sikkert til Eva...');
        setTimeout(() => form.submit(), 250);
        return;
      }

      const subject = `Forespørgsel fra cafelif.dk: ${type}`;
      const body = [
        'Hej Eva,', '',
        'Jeg vil gerne sende en forespørgsel til Café LIF.', '',
        `Navn: ${name}`,
        `E-mail: ${email}`,
        `Telefon: ${phone}`,
        `Type: ${type}`,
        `Antal personer: ${persons || 'Ikke angivet'}`,
        `Dato: ${date || 'Ikke angivet'}`, '',
        'Besked:', message, '',
        'Venlig hilsen', name
      ].join('\n');

      setStatus('success', 'Din e-mail er klar. Mailprogrammet åbner nu...');
      setTimeout(() => {
        window.location.href = `mailto:cafelif@lystrup-if.dk?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        setTimeout(() => { form.reset(); status.textContent = ''; status.className = 'form-status'; }, 1200);
      }, 600);
    });
  }

  /* Back to top */
  function initBackTop() {
    const btn = qs('#back-top');
    if (!btn) return;

    const update = () => btn.classList.toggle('is-visible', window.scrollY > 600);
    update();
    window.addEventListener('scroll', update, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' }));
  }

  /* Smooth scroll */
  function resolveScrollTarget(href) {
    if (!href || !href.startsWith('#')) return null;
    const id = href.slice(1);
    if (!id) return null;

    const headSel = SCROLL_HEADS[id];
    if (headSel) {
      const head = qs(headSel);
      if (head) return head;
    }

    const section = qs(`#${id}`);
    if (section) {
      return qs('[data-scroll-head]', section) || qs('.section-head, .experience-head', section) || section;
    }

    return qs(href);
  }

  function scrollToTop(behavior = reduced ? 'auto' : 'smooth') {
    window.scrollTo({ top: 0, behavior });
    setActiveNav('top');
  }

  function initSmoothScroll() {
    qs('[data-scroll-top]')?.addEventListener('click', e => {
      e.preventDefault();
      scrollToTop();
    });

    qsa('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const href = a.getAttribute('href');
        if (!href || href === '#') return;

        if (href === '#top') {
          e.preventDefault();
          scrollToTop();
          return;
        }

        const target = resolveScrollTarget(href);
        if (!target) return;
        e.preventDefault();

        const sectionId = href.slice(1);
        if (NAV_SECTIONS.includes(sectionId)) {
          setActiveNav(sectionId);
        }

        scrollToTarget(target);

        const menuFilter = a.dataset.menuFilter;
        if (menuFilter) {
          setTimeout(() => applyMenuFilter(menuFilter), reduced ? 50 : 650);
        }

        const mobileNav = qs('#mobile-nav');
        if (mobileNav?.classList.contains('is-open')) {
          mobileNav.classList.remove('is-open');
          mobileNav.setAttribute('aria-hidden', 'true');
          qs('#nav-toggle')?.setAttribute('aria-expanded', 'false');
          document.body.style.overflow = '';
        }

        qsa('[data-nav-dropdown]').forEach(dd => {
          dd.classList.remove('is-open');
          qs('.nav-dropdown__trigger', dd)?.setAttribute('aria-expanded', 'false');
        });
      });
    });
  }

  /* Year */
  function initYear() {
    qsa('[data-year]').forEach(el => { el.textContent = new Date().getFullYear(); });
  }

  function boot() {
    initHeader();
    initNavDropdowns();
    initFoodSlider();
    initMobileAccordion();
    initMobileNav();
    initActiveNav();
    initReveal();
    initMenuFilter();
    initOrderBuilder();
    initOrderStatusLookup();
    initForm();
    initBackTop();
    initSmoothScroll();
    initYear();
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', boot)
    : boot();
})();
