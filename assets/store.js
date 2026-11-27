/* Soko Suite storefront behaviour */
(function () {
  'use strict';
  var S = window.SOKO || {};
  S.vat = parseFloat(S.vat) || 0; S.mpesa = S.mpesa === true || S.mpesa === '1' || S.mpesa === 1;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var money = function (n) { return (S.currency || 'KES') + ' ' + Math.round(n || 0).toLocaleString('en-KE'); };
  var byId = {};
  (S.products || []).forEach(function (p) { byId[p.id] = p; });
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function api(path, opts) {
    opts = opts || {};
    return fetch(S.api + path, {
      method: opts.method || 'GET',
      headers: { 'Content-Type': 'application/json' },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().then(function (j) { if (!r.ok) { throw new Error(j && j.message ? j.message : 'Something went wrong. Please try again.'); } return j; });
    });
  }

  /* ---------------- toast ---------------- */
  var toastT;
  function toast(msg, link) {
    var t = $('[data-toast]');
    if (!t) return;
    t.innerHTML = esc(msg) + (link ? ' <a href="' + esc(link[1]) + '">' + esc(link[0]) + '</a>' : '');
    t.classList.add('on');
    clearTimeout(toastT);
    toastT = setTimeout(function () { t.classList.remove('on'); }, 3200);
  }

  /* ---------------- cart ---------------- */
  var cart = [];
  try { cart = JSON.parse(localStorage.getItem('soko_cart') || '[]').filter(function (c) { return byId[c.id]; }); } catch (e) { cart = []; }
  function saveCart(bump) {
    try { localStorage.setItem('soko_cart', JSON.stringify(cart)); } catch (e) {}
    var n = cart.reduce(function (a, c) { return a + c.qty; }, 0);
    $$('[data-cart-count]').forEach(function (el) { el.textContent = n; if (bump) { el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); } });
    renderCartPage();
    renderCheckout();
  }
  function add(id, qty, install, silent) {
    id = +id;
    if (!byId[id]) return;
    var l = cart.filter(function (c) { return c.id === id && !!c.install === !!install; })[0];
    if (l) l.qty += qty || 1; else cart.push({ id: id, qty: qty || 1, install: install ? 1 : 0 });
    saveCart(true);
    if (!silent) toast(byId[id].name + ' added to your cart.', ['View cart', S.home + 'cart/']);
  }
  function lineTotal(c) { var p = byId[c.id]; return (p.price + (c.install ? p.install : 0)) * c.qty; }
  function subtotal() { return cart.reduce(function (a, c) { return a + lineTotal(c); }, 0); }
  function thumb(p) { return '<div class="thumb">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : (S.icons[p.icon] || '').replace(/width="100" height="100"/, 'width="38" height="38"')) + '</div>'; }
  function linesHTML(edit) {
    return cart.map(function (c, i) {
      var p = byId[c.id];
      return '<div class="line">' + thumb(p) + '<div><h4>' + esc(p.name) + '</h4><small>' + money(p.price) + (c.install ? ' + installation ' + money(p.install) : '') + '</small>' + (edit ? '<button class="rm" data-rm="' + i + '">Remove</button>' : '') + '</div>' +
        (edit ? '<span class="qty"><button data-cq="' + i + '" data-d="-1" aria-label="One less">−</button><b>' + c.qty + '</b><button data-cq="' + i + '" data-d="1" aria-label="One more">+</button></span>' : '<b>× ' + c.qty + '</b>') + '</div>';
    }).join('');
  }
  function sumHTML(fee) {
    var s = subtotal(), vat = S.vat ? Math.round((s + fee) * S.vat / (100 + S.vat)) : 0;
    return '<div><span>Items</span><span>' + money(s) + '</span></div><div><span>Delivery</span><span>' + (fee ? money(fee) : 'Free') + '</span></div>' + (vat ? '<div class="muted"><span>Includes VAT</span><span>' + money(vat) + '</span></div>' : '') + '<div class="grand"><span>Total</span><span>' + money(s + fee) + '</span></div>';
  }
  function emptyCart() { return '<h3>Your cart is empty</h3><p class="muted" style="margin:8px 0 18px">Start with the Power Planner or browse the shop.</p><div class="row"><a class="btn b-sun" href="' + S.home + 'planner/">Plan my system</a><a class="btn b-line" href="' + S.home + 'shop/">Go to the shop</a></div>'; }
  function cartDrawer() {
    var x = '<div class="dhead"><b>Your cart</b><button class="ico" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
    if (!cart.length) return openDrawer(x + '<div class="dbody">' + emptyCart() + '</div>');
    openDrawer(x + '<div class="dbody">' + linesHTML(true) + '<div class="sum" style="margin-top:12px">' + sumHTML(0) + '</div><p class="muted" style="font-size:14px;margin-top:8px">Delivery is chosen at checkout.</p></div><div class="buybar"><a class="btn b-line" href="' + S.home + 'cart/">View cart</a><a class="btn b-sun" href="' + S.home + 'checkout/">Checkout</a></div>');
  }
  function renderCartPage() {
    var el = $('[data-cart-page]');
    if (!el) return;
    if (!cart.length) { el.innerHTML = '<div class="panel">' + emptyCart() + '</div>'; return; }
    el.innerHTML = '<div class="panel">' + linesHTML(true) + '</div><aside class="panel summary"><h3>Summary</h3><div class="sum">' + sumHTML(0) + '</div><a class="btn b-sun wide" style="margin-top:14px" href="' + S.home + 'checkout/">Checkout with M-Pesa</a><a class="btn b-line wide" style="margin-top:10px" href="' + S.home + 'shop/">Keep shopping</a></aside>';
  }

  /* ---------------- drawer ---------------- */
  var lastFocus;
  function openDrawer(h) {
    var d = $('[data-drawer]');
    lastFocus = document.activeElement;
    d.innerHTML = h;
    d.classList.add('on');
    $('[data-scrim]').classList.add('on');
    var f = d.querySelector('button,a,input');
    if (f) f.focus();
  }
  function closeDrawer() {
    var d = $('[data-drawer]');
    if (!d) return;
    d.classList.remove('on');
    $('[data-scrim]').classList.remove('on');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function quick(p) {
    var hasInst = p.install > 0;
    openDrawer('<div class="dhead"><b>' + esc(p.cat) + '</b><button class="ico" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' +
      '<div class="dbody"><div class="art' + (p.img ? '' : ' ic') + '">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '<span class="ill">' + (S.icons[p.icon] || '').replace(/width="100" height="100"/, 'width="130" height="130"') + '</span>') + (p.tag ? '<span class="tag">' + esc(p.tag) + '</span>' : '') + '</div>' +
      '<h2>' + esc(p.name) + '</h2><div class="price"><b>' + money(p.price) + '</b>' + (p.was > p.price ? '<s>' + money(p.was) + '</s>' : '') + '</div>' +
      (p.kind === 'product' ? '<span class="stock' + (p.stock <= 0 ? ' out' : '') + '">' + (p.stock <= 0 ? 'Order in, about a week' : p.stock + ' in stock') + '</span>' : '') +
      '<div class="runsbox">' + (S.icons.bulb || '').replace(/width="100" height="100"/, 'width="30" height="30"') + '<div><b>What it powers</b><p>' + esc(p.runs) + '</p></div></div>' +
      '<table class="specs">' + Object.keys(p.specs || {}).map(function (k) { return '<tr><th>' + esc(k) + '</th><td>' + esc(p.specs[k]) + '</td></tr>'; }).join('') + (p.warranty ? '<tr><th>Warranty</th><td>' + esc(p.warranty) + '</td></tr>' : '') + '</table>' +
      (hasInst ? '<div class="opts"><label><input type="radio" name="qinst" value="0" checked><span>Supply only</span><b>Included</b></label><label><input type="radio" name="qinst" value="1"><span>Supply and install</span><b>+ ' + money(p.install) + '</b></label></div>' : '') +
      '<a class="btn b-line wide" href="' + esc(p.url) + '">See full details</a></div>' +
      '<div class="buybar"><button class="btn b-blue" data-q-add="' + p.id + '">Add to cart</button><button class="btn b-sun" data-q-buy="' + p.id + '">Buy now</button></div>');
  }

  /* ---------------- power planner ---------------- */
  var APPS = [
    ['LED bulbs', 10, 6], ['TV', 100, 5], ['Fridge', 150, 10], ['Wi-Fi router', 15, 24], ['Laptop', 60, 6], ['Phone charging', 10, 3],
    ['CCTV camera', 20, 24], ['Water pump', 750, 2], ['Iron box', 1000, 0.5], ['Microwave', 1000, 0.3], ['Freezer', 200, 12], ['Desktop computer', 150, 8],
    ['Borehole pump', 1500, 5], ['Hair salon dryer', 1200, 2], ['Electric kettle', 1500, 0.3], ['Posho mill', 3000, 3]
  ];
  var picks = {}, hours = {};
  try { var sv = JSON.parse(sessionStorage.getItem('soko_planner') || 'null'); if (sv) { picks = sv.p || {}; hours = sv.h || {}; } } catch (e) {}
  if (!Object.keys(picks).length) picks = { 0: 8, 1: 1, 2: 1, 3: 1 };
  function savePlan() { try { sessionStorage.setItem('soko_planner', JSON.stringify({ p: picks, h: hours })); } catch (e) {} }
  function pick(cat, test, sort) {
    var l = (S.products || []).filter(function (p) { return p.cat === cat && p.kind === 'product' && test(p); });
    l.sort(sort);
    return l[0];
  }
  function size() {
    var wh = 0, peak = 0, motor = 0;
    Object.keys(picks).forEach(function (i) {
      var a = APPS[i], n = picks[i], h = hours[i] != null ? hours[i] : a[2];
      wh += a[1] * h * n; peak += a[1] * n;
      if (/pump|Fridge|Freezer|mill/i.test(a[0])) motor = Math.max(motor, a[1] * 2);
    });
    if (!wh) return null;
    var kwh = wh / 1000, need = (peak + motor) * 1.2;
    var inv = pick('hybrid', function (p) { return p.watts >= need; }, function (a, b) { return a.price - b.price; }) || pick('hybrid', function () { return true; }, function (a, b) { return b.watts - a.watts; });
    var night = kwh * 0.6 / 0.9;
    var batt = pick('lithium', function (p) { return p.kwh >= night; }, function (a, b) { return a.kwh - b.kwh; }) || pick('lithium', function () { return true; }, function (a, b) { return b.kwh - a.kwh; });
    var bq = batt ? Math.max(1, Math.ceil(night / batt.kwh)) : 0;
    var pan = pick('panels', function (p) { return /585/.test(p.name) || p.watts >= 500; }, function (a, b) { return Math.abs(a.watts - 585) - Math.abs(b.watts - 585); }) || pick('panels', function () { return true; }, function (a, b) { return b.watts - a.watts; });
    var pq = pan ? Math.max(2, Math.ceil(kwh * 1.25 / (5 * pan.watts / 1000 * 0.8))) : 0;
    var prot = (S.products || []).filter(function (p) { return p.sku === 'ACC-PRO'; })[0];
    var items = [];
    if (pan) items.push([pan, pq]);
    if (inv) items.push([inv, 1]);
    if (batt) items.push([batt, bq]);
    if (prot) items.push([prot, 1]);
    var goods = items.reduce(function (a, x) { return a + x[0].price * x[1]; }, 0);
    var inst = items.reduce(function (a, x) { return a + (x[0].install || 0) * x[1]; }, 0);
    return { kwh: kwh, peak: peak, inv: inv, batt: batt, bq: bq, pan: pan, pq: pq, items: items, goods: goods, inst: inst };
  }
  function appBtn(a, i) {
    var n = picks[i] || 0, h = hours[i] != null ? hours[i] : a[2];
    return '<div class="app' + (n ? ' on' : '') + '" role="button" tabindex="0" data-app="' + i + '"><span>' + esc(a[0]) + '<small>' + a[1] + ' W' + (n ? '' : ' · tap to add') + '</small>' + (n ? '<label class="hrs" data-stop>Hours a day <input type="number" min="0.1" max="24" step="0.5" value="' + h + '" data-hrs="' + i + '" aria-label="Hours a day for ' + esc(a[0]) + '"></label>' : '') + '</span>' +
      (n ? '<span class="qty" data-stop><button type="button" data-dec="' + i + '" aria-label="One less">−</button><b>' + n + '</b><button type="button" data-inc="' + i + '" aria-label="One more">+</button></span>' : '<span class="qty"><span>+</span></span>') + '</div>';
  }
  function renderPlanner(keepFocus) {
    $$('[data-planner-apps]').forEach(function (el) { el.innerHTML = APPS.map(appBtn).join(''); });
    var r = size();
    $$('[data-planner-result]').forEach(function (el) {
      if (!r) { el.innerHTML = '<h3>Your recommended system</h3><p class="empty">Tap at least one appliance to see your system and price.</p>'; return; }
      el.innerHTML = '<h3>Your recommended system</h3><div class="meter"><div><b>' + r.kwh.toFixed(1) + ' kWh</b><small>used per day</small></div><div><b>' + (r.inv ? Math.round(r.inv.watts / 100) / 10 + ' kW' : '—') + '</b><small>inverter</small></div><div><b>' + (r.batt ? Math.round(r.batt.kwh * r.bq) + ' kWh' : '—') + '</b><small>battery</small></div></div>' +
        '<ul class="bom">' + r.items.map(function (x) { return '<li><span>' + (x[1] > 1 ? x[1] + ' × ' : '') + esc(x[0].name) + '</span><span>' + money(x[0].price * x[1]) + '</span></li>'; }).join('') + '<li><span>Installation and commissioning</span><span>' + money(r.inst) + '</span></li></ul>' +
        '<div class="total"><span>Estimated total</span><b>' + money(r.goods + r.inst) + '</b></div>' +
        '<div class="row"><button class="btn b-sun" data-plan="cart">Add system to cart</button><button class="btn b-line" data-plan="survey" style="color:var(--blue)">Book a free survey</button></div><p class="fine">Peak load about ' + Math.round(r.peak).toLocaleString() + ' W. An engineer confirms the final size after a site survey.</p>';
    });
    savePlan();
  }

  /* ---------------- hero carousel ---------------- */
  $$('.hero').forEach(function (h) {
    var sl = $('.slides', h), dots = $$('.dots i', h), n = dots.length, idx = 0, timer;
    if (!sl || n < 2) return;
    dots[0].classList.add('on');
    var go = function (i) { idx = (i + n) % n; sl.scrollTo({ left: idx * sl.clientWidth, behavior: reduce ? 'auto' : 'smooth' }); };
    sl.addEventListener('scroll', function () { var i = Math.round(sl.scrollLeft / sl.clientWidth); dots.forEach(function (d, k) { d.classList.toggle('on', k === i); }); idx = i; }, { passive: true });
    var stop = function () { clearInterval(timer); timer = null; };
    $('.hprev', h).addEventListener('click', function () { stop(); go(idx - 1); });
    $('.hnext', h).addEventListener('click', function () { stop(); go(idx + 1); });
    h.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { stop(); go(idx + 1); } if (e.key === 'ArrowLeft') { stop(); go(idx - 1); } });
    sl.addEventListener('pointerdown', stop);
    if (h.dataset.autoplay === '1' && !reduce) timer = setInterval(function () { if (!document.hidden) go(idx + 1); }, 6500);
  });

  /* ---------------- shop filters ---------------- */
  var grid = $('[data-shop-grid]');
  function filterShop() {
    if (!grid) return;
    var brand = ($('[data-f-brand]') || {}).value || '', inStock = ($('[data-f-stock]') || {}).checked, sort = ($('[data-f-sort]') || {}).value || '';
    var cards = $$('.card', grid);
    cards.forEach(function (c, i) { if (c.dataset.i == null) c.dataset.i = i; });
    var shown = 0;
    cards.forEach(function (c) {
      var p = JSON.parse(c.dataset.p);
      var ok = (!inStock || p.kind !== 'product' || p.stock > 0) && (!brand || p.brand === brand);
      c.hidden = !ok; if (ok) shown++;
    });
    cards.sort(function (a, b) {
      var pa = JSON.parse(a.dataset.p), pb = JSON.parse(b.dataset.p);
      if (sort === 'lo') return pa.price - pb.price;
      if (sort === 'hi') return pb.price - pa.price;
      if (sort === 'az') return pa.name.localeCompare(pb.name);
      return a.dataset.i - b.dataset.i;
    }).forEach(function (c) { grid.appendChild(c); });
    var cnt = $('[data-count]'); if (cnt) cnt.textContent = shown + ' product' + (shown === 1 ? '' : 's');
  }

  /* ---------------- live search ---------------- */
  var sT;
  $$('[data-search]').forEach(function (inp) {
    var box = $('[data-sugg]', inp.form);
    inp.addEventListener('input', function () {
      clearTimeout(sT);
      var q = inp.value.trim();
      if (q.length < 2) { box.hidden = true; return; }
      sT = setTimeout(function () {
        api('store/search?q=' + encodeURIComponent(q)).then(function (r) {
          if (!r.length) { box.innerHTML = '<p class="muted" style="padding:10px">No matches. Press Enter to search everything.</p>'; box.hidden = false; return; }
          box.innerHTML = r.map(function (x) { return '<a href="' + esc(x.url) + '"><span class="th">' + (x.img ? '<img src="' + esc(x.img) + '" alt="">' : x.icon) + '</span><span>' + esc(x.name) + '<small>' + esc(x.cat) + '</small></span><b>' + esc(x.price) + '</b></a>'; }).join('') + '<a class="all" href="' + S.home + 'shop/?q=' + encodeURIComponent(q) + '">See all results</a>';
          box.hidden = false;
        }).catch(function () {});
      }, 220);
    });
    inp.addEventListener('blur', function () { setTimeout(function () { box.hidden = true; }, 200); });
  });

  /* ---------------- forms ---------------- */
  function formData(f) { var o = {}; new FormData(f).forEach(function (v, k) { o[k] = v; }); return o; }
  function validPhone(p) { var d = String(p).replace(/\D/g, ''); return /^(0[17]\d{8}|254[17]\d{8}|[17]\d{8})$/.test(d); }
  function msg(f, text, ok) { var m = $('.formmsg', f); if (m) { m.textContent = text; m.className = 'formmsg ' + (ok ? 'ok' : 'err'); } }
  function check(f) {
    var bad = null;
    $$('[required]', f).forEach(function (i) { var v = i.value.trim(); var b = !v || (i.name === 'phone' && !validPhone(v)); i.classList.toggle('input-err', b); if (b && !bad) bad = i; });
    if (bad) { bad.focus(); msg(f, bad.name === 'phone' ? 'Enter a valid Safaricom or Airtel number, for example 0712 345 678.' : 'Please fill in the highlighted fields.'); return false; }
    return true;
  }
  $$('[data-lead-form]').forEach(function (f) {
    try { var pre = sessionStorage.getItem('soko_lead_msg'); if (pre) { f.message.value = pre; sessionStorage.removeItem('soko_lead_msg'); } } catch (e) {}
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!check(f)) return;
      var b = formData(f), btn = $('button[type=submit]', f);
      b.source = 'Website quote form';
      btn.disabled = true; btn.textContent = 'Sending…';
      api('store/lead', { method: 'POST', body: b }).then(function () {
        f.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Thank you, ' + esc(b.name.split(' ')[0]) + '</h3><p class="muted">An engineer will call you on ' + esc(b.phone) + ' within the working day.</p></div>';
      }).catch(function (err) { btn.disabled = false; btn.textContent = 'Send request'; msg(f, err.message); });
    });
  });

  /* ---------------- checkout ---------------- */
  function deliveryFee() { var r = $('[name=delivery]:checked'); return r ? +r.dataset.fee : 0; }
  function renderCheckout() {
    var co = $('[data-checkout]');
    if (!co || co.dataset.done) return;
    var lines = $('[data-co-lines]', co), sum = $('[data-co-sum]', co), payBtn = $('[data-pay]', co);
    if (!cart.length) { lines.innerHTML = emptyCart(); sum.innerHTML = ''; payBtn.disabled = true; return; }
    payBtn.disabled = false;
    lines.innerHTML = linesHTML(false);
    sum.innerHTML = sumHTML(deliveryFee());
    payBtn.textContent = 'Pay ' + money(subtotal() + deliveryFee()) + ' with M-Pesa';
    var inst = $('.inst-date', co); if (inst) inst.hidden = !cart.some(function (c) { return c.install; });
  }
  var coForm = $('[data-co-form]');
  if (coForm) {
    try { var me = JSON.parse(localStorage.getItem('soko_me') || 'null'); if (me) Object.keys(me).forEach(function (k) { if (coForm[k] && !coForm[k].value) coForm[k].value = me[k]; }); } catch (e) {}
    coForm.addEventListener('change', function (e) { if (e.target.name === 'delivery') renderCheckout(); });
    coForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!cart.length || !check(coForm)) return;
      var b = formData(coForm), btn = $('[data-pay]', coForm);
      try { localStorage.setItem('soko_me', JSON.stringify({ name: b.name, phone: b.phone, email: b.email, county: b.county, address: b.address })); } catch (x) {}
      btn.disabled = true; btn.textContent = 'Placing your order…';
      api('store/checkout', { method: 'POST', body: { customer: { name: b.name, phone: b.phone, email: b.email, county: b.county, address: b.address, kra: b.kra }, items: cart, delivery: +b.delivery, install_date: b.install_date, notes: b.notes, website: b.website } })
        .then(function (r) {
          var co = $('[data-checkout]'); co.dataset.done = 1;
          cart = []; saveCart();
          payFlow($('[data-co-form]'), { doc: r.number, t: r.token, total: r.total, phone: b.phone, url: r.url }, r.stk, r.pay);
        })
        .catch(function (err) { btn.disabled = false; renderCheckout(); msg(coForm, err.message); });
    });
  }

  /* Payment flow: STK prompt with live status, falling back to Paybill instructions. */
  function payFlow(host, d, stk, pay) {
    var instr = pay && pay.number ? '<div class="paybill"><div><span>M-Pesa ' + esc(pay.kind) + '</span><b>' + esc(pay.number) + '</b></div>' + (pay.kind === 'Paybill' ? '<div><span>Account number</span><b>' + esc(d.doc) + '</b></div>' : '') + '<div><span>Amount</span><b>' + money(d.balance != null ? d.balance : d.total) + '</b></div></div>' : '';
    var trackLink = d.url ? '<a class="btn b-line wide" href="' + esc(d.url) + '">View and track this order</a>' : '';
    if (stk && stk.ok) {
      host.innerHTML = '<div class="payflow"><div class="phone"><span class="mpesa" style="font-size:16px">M-Pesa</span></div><h3>Check your phone</h3><p class="muted">Enter your M-Pesa PIN to pay <b>' + money(d.balance != null ? d.balance : d.total) + '</b> for order <b>' + esc(d.doc) + '</b>.</p><div class="prog"><b data-pg></b></div><p class="muted" data-pmsg style="font-size:14px">Waiting for Safaricom to confirm…</p></div>';
      var t0 = Date.now(), pg = $('[data-pg]', host);
      var poll = function () {
        var el = Math.min(100, (Date.now() - t0) / 900);
        if (pg) pg.style.width = el + '%';
        api('store/status?doc=' + encodeURIComponent(d.doc) + '&t=' + encodeURIComponent(d.t) + '&checkout=' + encodeURIComponent(stk.checkout_id)).then(function (s) {
          if (s.state === 'paid' || (s.stk && s.stk.status === 'paid')) return paidView(host, d, s);
          if (s.stk && s.stk.status === 'failed') {
            host.innerHTML = '<div class="payflow"><h3>Payment not completed</h3><p class="muted">' + esc(s.stk.message || 'The request was cancelled or timed out.') + '</p><button class="btn b-sun wide" data-retry>Send the prompt again</button>' + (instr ? '<p class="muted" style="margin-top:14px">Or pay from your M-Pesa menu:</p>' + instr : '') + trackLink + '</div>';
            $('[data-retry]', host).onclick = function () { retry(host, d, pay); };
            return;
          }
          if (Date.now() - t0 < 90000) setTimeout(poll, 3000);
          else host.innerHTML = '<div class="payflow"><h3>Still waiting for M-Pesa</h3><p class="muted">If you completed the payment it will show shortly. Otherwise pay from your M-Pesa menu:</p>' + instr + '<button class="btn b-sun wide" data-recheck>I have paid, check again</button>' + trackLink + '</div>', $('[data-recheck]', host).onclick = function () { recheck(host, d, pay); };
        }).catch(function () { setTimeout(poll, 4000); });
      };
      setTimeout(poll, 4000);
    } else {
      host.innerHTML = '<div class="payflow"><div class="okc" style="background:var(--blue)"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12l5 5 9-10"/></svg></div><h3>Order ' + esc(d.doc) + ' placed</h3><p class="muted">' + esc(stk && stk.message ? stk.message : 'Complete payment from your M-Pesa menu.') + '</p>' + instr + '<button class="btn b-sun wide" data-recheck>I have paid, check now</button><div style="height:10px"></div>' + trackLink + '</div>';
      $('[data-recheck]', host).onclick = function () { recheck(host, d, pay); };
    }
  }
  function recheck(host, d, pay) {
    api('store/status?doc=' + encodeURIComponent(d.doc) + '&t=' + encodeURIComponent(d.t)).then(function (s) {
      if (s.state === 'paid') paidView(host, d, s); else toast('We have not received the payment yet. It can take a minute.');
    });
  }
  function retry(host, d, pay) {
    api('store/pay', { method: 'POST', body: { doc: d.doc, t: d.t, phone: d.phone } }).then(function (r) { payFlow(host, d, r, pay); }).catch(function (e) { toast(e.message); });
  }
  function paidView(host, d, s) {
    host.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Payment received</h3><p class="muted">Thank you. Order <b>' + esc(d.doc) + '</b> is paid' + (s.balance > 0 ? ', with ' + money(s.balance) + ' still due' : '') + '. Your receipt is on its way by email.</p><div class="row" style="justify-content:center;margin-top:14px">' + (s.receipts && s.receipts.length ? '<a class="btn b-sun" target="_blank" rel="noopener" href="' + esc(s.receipts[s.receipts.length - 1].pdf) + '">Download receipt</a>' : '') + (d.url ? '<a class="btn b-line" href="' + esc(d.url) + '">Track this order</a>' : '') + '</div></div>';
  }

  /* ---------------- track page ---------------- */
  var tr = $('[data-track]');
  if (tr) {
    var td = JSON.parse(tr.dataset.track), pp = $('[data-paypanel]');
    var refresh = function () {
      api('store/status?doc=' + encodeURIComponent(td.doc) + '&t=' + encodeURIComponent(td.t)).then(function (s) {
        var rc = $('[data-receipts]');
        if (rc && s.receipts.length) rc.innerHTML = '<h3 style="margin-top:20px">Receipts</h3>' + s.receipts.map(function (r) { return '<div class="line" style="grid-template-columns:minmax(0,1fr) auto auto"><span>' + esc(r.number) + ' · ' + esc(r.date) + '</span><b>' + money(r.total) + '</b><a class="btn b-line" style="min-height:38px" target="_blank" rel="noopener" href="' + esc(r.pdf) + '">PDF</a></div>'; }).join('');
        if (s.balance <= 0) { pp.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Fully paid</h3><p class="muted">Thank you. We will be in touch about delivery' + '.</p></div>'; return; }
        pp.innerHTML = '<h3>Pay ' + money(s.balance) + '</h3><form data-paybal novalidate><div class="f"><label for="pPhone">M-Pesa number</label><input id="pPhone" name="phone" inputmode="tel" value="' + esc(td.phone) + '" required></div><div class="f"><label for="pAmt">Amount</label><input id="pAmt" name="amount" type="number" min="1" max="' + s.balance + '" value="' + Math.ceil(s.balance) + '"></div><button class="btn b-sun wide">Send M-Pesa prompt</button><p class="formmsg"></p></form>' + (S.pay && S.pay.number ? '<p class="muted" style="margin-top:14px">Or pay from your M-Pesa menu:</p><div class="paybill"><div><span>' + esc(S.pay.kind) + '</span><b>' + esc(S.pay.number) + '</b></div>' + (S.pay.kind === 'Paybill' ? '<div><span>Account</span><b>' + esc(td.doc) + '</b></div>' : '') + '</div>' : '');
        var f = $('[data-paybal]', pp);
        f.onsubmit = function (e) {
          e.preventDefault();
          if (!check(f)) return;
          var b = formData(f);
          api('store/pay', { method: 'POST', body: { doc: td.doc, t: td.t, phone: b.phone, amount: b.amount } }).then(function (r) {
            payFlow(pp, { doc: td.doc, t: td.t, total: +b.amount, balance: +b.amount, phone: b.phone }, r, S.pay);
          }).catch(function (err) { msg(f, err.message); });
        };
      });
    };
    refresh();
  }
  var findF = $('[data-find-form]');
  if (findF) findF.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!check(findF)) return;
    api('store/find', { method: 'POST', body: formData(findF) }).then(function (r) { location.href = r.url; }).catch(function (err) { msg(findF, err.message); });
  });

  /* ---------------- product page ---------------- */
  var pdp = $('[data-product]');
  if (pdp) {
    var pd = JSON.parse(pdp.dataset.product), q = $('[data-qty] input', pdp);
    var inst = function () { var r = $('[name=inst]:checked', pdp); return r && r.value === '1'; };
    $$('[data-qty] button', pdp).forEach(function (b) { b.onclick = function () { q.value = Math.max(1, (+q.value || 1) + +b.dataset.d); }; });
    $('[data-add-pdp]', pdp).onclick = function () { add(pd.id, Math.max(1, +q.value || 1), inst()); };
    $('[data-buy-pdp]', pdp).onclick = function () { add(pd.id, Math.max(1, +q.value || 1), inst(), true); location.href = S.home + 'checkout/'; };
    $$('.thumbs button', pdp).forEach(function (b) { b.onclick = function () { $$('.thumbs button', pdp).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); var im = $('.gmain img', pdp); if (im) im.src = b.dataset.img; }; });
    var rt = $('[data-runtime]', pdp);
    if (rt) {
      var kwh = +rt.dataset.runtime, loads = [['LED bulbs (×6)', 60, true], ['TV', 100, true], ['Fridge', 60, true], ['Wi-Fi router', 15, true], ['Laptop', 60, false], ['CCTV (×2)', 40, false], ['POS and phone charging', 30, false], ['Desktop computer', 150, false]];
      $('.rt-list', rt).innerHTML = loads.map(function (l, i) { return '<label><input type="checkbox" data-rt="' + i + '"' + (l[2] ? ' checked' : '') + '><span>' + esc(l[0]) + '</span><small>' + l[1] + ' W</small></label>'; }).join('');
      var calc = function () {
        var w = 0; $$('[data-rt]', rt).forEach(function (c) { if (c.checked) w += loads[c.dataset.rt][1]; });
        var hrs = w ? kwh * 1000 * 0.85 / w : 0;
        $('.rt-out', rt).innerHTML = '<span>About ' + w + ' W running</span><b>' + (w ? (hrs >= 24 ? Math.floor(hrs / 24) + ' day' + (hrs >= 48 ? 's ' : ' ') + Math.round(hrs % 24) + ' h' : (Math.round(hrs * 10) / 10) + ' hours') : '—') + '</b>';
      };
      rt.addEventListener('change', calc); calc();
    }
  }

  /* ---------------- projects filter ---------------- */
  $$('[data-pj-filters]').forEach(function (bar) {
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('[data-f]'); if (!b) return;
      $$('.chip', bar).forEach(function (c) { c.classList.toggle('on', c === b); });
      $$('.pj', bar.parentNode).forEach(function (p) { p.hidden = !!b.dataset.f && p.dataset.sector !== b.dataset.f; });
    });
  });

  /* ---------------- global clicks ---------------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    var a = t.closest('[data-add]'); if (a) { e.preventDefault(); add(a.dataset.add, 1, 0); return; }
    var qk = t.closest('[data-quick]'); if (qk) { quick(JSON.parse(qk.closest('.card').dataset.p)); return; }
    var qa = t.closest('[data-q-add]'); if (qa) { var r = $('[name=qinst]:checked'); add(qa.dataset.qAdd, 1, r && r.value === '1'); closeDrawer(); return; }
    var qb = t.closest('[data-q-buy]'); if (qb) { var r2 = $('[name=qinst]:checked'); add(qb.dataset.qBuy, 1, r2 && r2.value === '1', true); location.href = S.home + 'checkout/'; return; }
    if (t.closest('[data-close]') || t.closest('[data-scrim]')) { closeDrawer(); return; }
    if (t.closest('[data-open-cart]')) { cartDrawer(); return; }
    var cq = t.closest('[data-cq]'); if (cq) { var c = cart[+cq.dataset.cq]; c.qty += +cq.dataset.d; if (c.qty <= 0) cart.splice(+cq.dataset.cq, 1); saveCart(); if ($('[data-drawer]').classList.contains('on')) cartDrawer(); return; }
    var rm = t.closest('[data-rm]'); if (rm) { cart.splice(+rm.dataset.rm, 1); saveCart(); if ($('[data-drawer]').classList.contains('on')) cartDrawer(); return; }
    if (t.closest('[data-burger]')) { $('[data-mnav]').classList.add('on'); return; }
    if (t.closest('[data-close-mnav]')) { $('[data-mnav]').classList.remove('on'); return; }
    if (t.closest('[data-stop]') && !t.closest('button')) return;
    var inc = t.closest('[data-inc]'), dec = t.closest('[data-dec]'), app = t.closest('[data-app]');
    if (inc) { picks[inc.dataset.inc] = (picks[inc.dataset.inc] || 0) + 1; renderPlanner(); return; }
    if (dec) { var k = dec.dataset.dec; picks[k] = (picks[k] || 0) - 1; if (picks[k] <= 0) delete picks[k]; renderPlanner(); return; }
    if (app) { var i = app.dataset.app; if (picks[i]) delete picks[i]; else picks[i] = 1; renderPlanner(); return; }
    var pl = t.closest('[data-plan]');
    if (pl) {
      var r3 = size(); if (!r3) return;
      if (pl.dataset.plan === 'cart') { r3.items.forEach(function (x) { add(x[0].id, x[1], 1, true); }); toast('Your system was added to the cart with installation.', ['View cart', S.home + 'cart/']); cartDrawer(); }
      else {
        var text = 'Power Planner result: ' + r3.kwh.toFixed(1) + ' kWh a day, peak about ' + Math.round(r3.peak) + ' W. Appliances: ' + Object.keys(picks).map(function (i) { return picks[i] + ' × ' + APPS[i][0]; }).join(', ') + '. Suggested: ' + r3.items.map(function (x) { return x[1] + ' × ' + x[0].name; }).join(', ') + '.';
        openDrawer('<div class="dhead"><b>Book a free site survey</b><button class="ico" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="dbody"><p class="muted" style="margin-bottom:16px">We will call to agree a time. Your planner result is attached.</p><form data-survey novalidate><div class="f"><label for="sName">Full name</label><input id="sName" name="name" required></div><div class="f"><label for="sPhone">Phone</label><input id="sPhone" name="phone" inputmode="tel" required></div><div class="f"><label for="sCounty">Where is the site?</label><input id="sCounty" name="county" placeholder="Estate and county"></div><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off"><button class="btn b-sun wide">Request the survey</button><p class="formmsg"></p></form></div>');
        var sf = $('[data-survey]');
        sf.onsubmit = function (ev) {
          ev.preventDefault(); if (!check(sf)) return;
          var b = formData(sf);
          api('store/lead', { method: 'POST', body: { name: b.name, phone: b.phone, county: b.county, website: b.website, source: 'Power Planner', interest: 'Site survey: ' + (r3.inv ? r3.inv.name : 'solar system'), value: r3.goods + r3.inst, message: text, data: { picks: picks, hours: hours } } })
            .then(function () { sf.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Request received</h3><p class="muted">An engineer will call you to book the survey.</p></div>'; })
            .catch(function (err) { msg(sf, err.message); });
        };
      }
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawer(); var m = $('[data-mnav]'); if (m) m.classList.remove('on'); }
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-app]')) { e.preventDefault(); e.target.click(); }
  });
  document.addEventListener('input', function (e) {
    if (e.target.matches('[data-hrs]')) { hours[e.target.dataset.hrs] = Math.max(0.1, Math.min(24, +e.target.value || 0.1)); var r = size(); savePlan(); var pos = e.target.selectionStart; renderPlanner(); var ni = $('[data-hrs="' + e.target.dataset.hrs + '"]'); if (ni) { ni.focus(); try { ni.setSelectionRange(pos, pos); } catch (x) {} } }
  });
  document.addEventListener('change', function (e) { if (e.target.matches('[data-f-brand],[data-f-stock],[data-f-sort]')) filterShop(); });

  renderPlanner();
  saveCart();
  filterShop();
})();
