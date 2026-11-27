/* Soko Suite storefront behaviour */
(function () {
  'use strict';
  var S = window.SOKO || {};
  S.vat = parseFloat(S.vat) || 0; S.mpesa = S.mpesa === true || S.mpesa === '1' || S.mpesa === 1;
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var phoneFmt = function (p) { var d = String(p || '').replace(/\D/g, ''); if (/^254\d{9}$/.test(d)) d = '0' + d.slice(3); return /^0\d{9}$/.test(d) ? d.slice(0, 4) + ' ' + d.slice(4, 7) + ' ' + d.slice(7) : String(p || ''); };
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
    t.innerHTML = '<span>' + esc(msg) + '</span>' + (link ? ' <a href="' + esc(link[1]) + '">' + esc(link[0]) + '</a>' : '');
    t.classList.toggle('up', !!document.querySelector('.sbuy.on') || (!!document.querySelector('.sbuy') && innerWidth < 900));
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
    var cap = maxQty(id), have = cart.filter(function (c) { return c.id === id; }).reduce(function (a, c) { return a + c.qty; }, 0);
    qty = Math.max(1, qty || 1);
    if (have + qty > cap) { qty = cap - have; if (qty <= 0) { toast(capMsg(id, cap)); return; } toast((backorder(id) ? 'Up to ' + cap + ' can be ordered in online, so ' : 'Only ' + cap + ' available, so ') + qty + ' added.'); silent = true; }
    if (l) l.qty += qty; else cart.push({ id: id, qty: qty, install: install ? 1 : 0 });
    saveCart(true);
    if (!silent) toast('Added to cart: ' + byId[id].name, ['View cart', S.home + 'cart/']);
  }
  function backorder(id) { var p = byId[id]; return !!(p && p.kind === 'product' && p.stock <= 0); }
  function maxQty(id) { var p = byId[id]; if (!p || p.kind !== 'product') return 50; return p.stock > 0 ? Math.min(500, Math.floor(p.stock)) : 20; }
  function capMsg(id, cap) { return backorder(id) ? 'We order this in for you (about a week). For more than ' + cap + ', please call us.' : 'Only ' + cap + ' available. Call us for larger orders.'; }
  function lineTotal(c) { var p = byId[c.id]; return (p.price + (c.install ? p.install : 0)) * c.qty; }
  function subtotal() { return cart.reduce(function (a, c) { return a + lineTotal(c); }, 0); }
  function thumb(p) { return '<div class="thumb">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : (S.icons[p.icon] || '').replace(/width="100" height="100"/, 'width="38" height="38"')) + '</div>'; }
  function linesHTML(edit) {
    return cart.map(function (c, i) {
      var p = byId[c.id];
      return '<div class="line">' + thumb(p) + '<div><h4>' + esc(p.name) + '</h4><small>' + money(p.price) + (c.install ? ' + installation ' + money(p.install) : '') + '</small>' + (backorder(c.id) ? '<small class="bo">Ordered in for you: about a week</small>' : '') + (edit ? '<button class="rm" data-rm="' + i + '">Remove</button>' : '') + '</div>' +
        (edit ? '<span class="qty"><button data-cq="' + i + '" data-d="-1" aria-label="One less">−</button><b>' + c.qty + '</b><button data-cq="' + i + '" data-d="1" aria-label="One more">+</button></span>' : '<b>× ' + c.qty + '</b>') + (c.qty > 1 ? '<b class="ltot">' + money(lineTotal(c)) + '</b>' : '') + '</div>';
    }).join('');
  }
  function sumHTML(fee) {
    var pick = fee === 'pick'; if (pick) fee = null;
    var s = subtotal(), vat = S.vat ? Math.round((s + (fee || 0)) * S.vat / (100 + S.vat)) : 0;
    return '<div><span>Items</span><span>' + money(s) + '</span></div>' + (!hasGoods() ? '' : installing() ? '<div><span>Delivery and installation</span><span>Included</span></div>' : fee === null ? '<div><span>Delivery</span><span>' + (pick ? 'Choose below' : 'Chosen at checkout') + '</span></div>' : '<div><span>' + (pickupChosen() ? 'Pick up' : 'Delivery') + '</span><span>' + (fee ? money(fee) : 'Free') + '</span></div>') + (vat ? '<div class="muted"><span>Includes VAT</span><span>' + money(vat) + '</span></div>' : '') + '<div class="grand"><span>Total</span><span>' + money(s + (fee || 0)) + '</span></div>';
  }
  function emptyCart() { return '<h3>Your cart is empty</h3><p class="muted" style="margin:8px 0 18px">Start with the solar calculator or browse the shop.</p><div class="row"><a class="btn b-sun" href="' + S.home + 'planner/">Calculate my system</a><a class="btn b-line" href="' + S.home + 'shop/">Go to the shop</a></div>'; }
  function upsell() {
    var inCart = cart.map(function (c) { return +c.id; });
    var hasSystem = cart.some(function (c) { var p = byId[c.id]; return p && /kits|hybrid|gridtie|panels|lithium/.test(p.cat); });
    if (!hasSystem) return '';
    var recs = (S.products || []).filter(function (p) { return (p.cat === 'acc' || p.cat === 'services') && inCart.indexOf(+p.id) < 0; }).slice(0, 2);
    if (!recs.length) return '';
    return '<div class="ups"><h4>Often added with solar systems</h4>' + recs.map(function (p) { return '<div class="line">' + thumb(p) + '<div><h4>' + esc(p.name) + '</h4><small>' + money(p.price) + '</small></div><button class="btn b-line" style="min-height:38px;padding:0 14px" data-add="' + p.id + '">Add</button></div>'; }).join('') + '</div>';
  }
  function cartDrawer() {
    var x = '<div class="dhead"><b>Your cart</b><button class="ico" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>';
    if (!cart.length) return openDrawer(x + '<div class="dbody">' + emptyCart() + '</div>');
    openDrawer(x + '<div class="dbody">' + linesHTML(true) + '<div class="sum" style="margin-top:12px">' + sumHTML(null) + '</div>' + upsell() + '</div><div class="buybar"><a class="btn b-line" href="' + S.home + 'cart/">View cart</a><a class="btn b-sun" href="' + S.home + 'checkout/">Checkout</a></div>');
  }
  function renderCartPage() {
    var el = $('[data-cart-page]');
    if (!el) return;
    if (!cart.length) { el.innerHTML = '<div class="panel">' + emptyCart() + '</div>'; return; }
    el.innerHTML = '<div class="panel">' + linesHTML(true) + '</div><aside class="panel summary"><h3>Summary</h3><div class="sum">' + sumHTML(null) + '</div><a class="btn b-sun wide" style="margin-top:14px" href="' + S.home + 'checkout/">Checkout</a><a class="btn b-line wide" style="margin-top:10px" href="' + S.home + 'shop/">Keep shopping</a></aside>';
  }

  /* ---------------- drawer ---------------- */
  var lastFocus;
  function openDrawer(h) {
    var tt = document.querySelector('[data-toast]'); if (tt) tt.classList.remove('on');
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
      (p.kind === 'product' ? '<span class="stock' + (p.stock <= 0 ? ' out' : '') + '">' + (p.stock <= 0 ? 'Order in, 1 week' : p.stock + ' in stock') + '</span>' : '') +
      '<div class="runsbox">' + (S.icons.bulb || '').replace(/width="100" height="100"/, 'width="30" height="30"') + '<div><b>What it powers</b><p>' + esc(p.runs) + '</p></div></div>' +
      '<table class="specs">' + Object.keys(p.specs || {}).map(function (k) { return '<tr><th>' + esc(k) + '</th><td>' + esc(p.specs[k]) + '</td></tr>'; }).join('') + (p.warranty ? '<tr><th>Warranty</th><td>' + esc(p.warranty) + '</td></tr>' : '') + '</table>' +
      (hasInst ? '<div class="opts"><label><input type="radio" name="qinst" value="0" checked><span>Supply only</span><b>No installation</b></label><label><input type="radio" name="qinst" value="1"><span>Supply and install</span><b>+ ' + money(p.install) + '</b></label></div>' : '') +
      '<a class="btn b-line wide" href="' + esc(p.url) + '">See full details</a></div>' +
      '<div class="buybar"><button class="btn b-blue" data-q-add="' + p.id + '">Add to cart</button><button class="btn b-sun" data-q-buy="' + p.id + '">Buy now</button></div>');
  }

  /* ---------------- power planner ---------------- */
  var APPS = [
    ['LED bulbs', 10, 6], ['TV', 100, 5], ['Fridge', 150, 10], ['Wi-Fi router', 15, 24], ['Laptop', 60, 6], ['Phone charging', 10, 3],
    ['CCTV camera', 20, 24], ['Water pump', 750, 2], ['Iron box', 1000, 0.5], ['Microwave', 1000, 0.3], ['Freezer', 200, 12], ['Desktop computer', 150, 8],
    ['Borehole pump', 1500, 5], ['Hair salon dryer', 1200, 2], ['Electric kettle', 1500, 0.3], ['Posho mill', 3000, 3]
  ];
  var AICO = {0: "M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3z", 1: "M3 5h18v11H3zM8 20h8M12 16v4", 2: "M6 2h12v20H6zM6 9h12M9 5v2M9 12v4", 3: "M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01", 4: "M4 5h16v10H4zM2 19h20", 5: "M7 2h10v20H7zM11 18h2", 6: "M3 7l13-3 2 6-13 3zM8 12l1 4M5 18h6", 7: "M4 14h10a3 3 0 0 0 3-3V7M17 4v3M6 14v6M10 14v6M3 20h10", 8: "M3 17h15l3-6H9a6 6 0 0 0-6 6zM12 7h4", 9: "M2 6h20v12H2zM15 6v12M18 10v.01M18 14v.01", 10: "M5 2h14v20H5zM5 8h14M8 4.5v1.5M8 11v4", 11: "M3 4h18v12H3zM9 20h6M12 16v4", 12: "M12 2v6M8 8h8v6a4 4 0 0 1-8 0zM12 18v4M6 22h12", 13: "M14 4a6 6 0 1 1-6 6M8 10l-5 9M10 14l-3 5", 14: "M6 8h12l-1 12H7zM9 8V5h6v3M18 11h2v4h-2", 15: "M4 20h16M6 20V9l6-5 6 5v11M10 20v-5h4v5"};
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
    return '<div class="app' + (n ? ' on' : '') + '" role="button" tabindex="0" data-app="' + i + '"><i class="aico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' + (AICO[i] || '') + '"/></svg></i><span>' + esc(a[0]) + '<small>' + a[1] + ' W' + (n ? '' : ' · add') + '</small>' + (n ? '<label class="hrs" data-stop>Hours a day <input type="number" min="0.1" max="24" step="0.5" value="' + h + '" data-hrs="' + i + '" aria-label="Hours a day for ' + esc(a[0]) + '"></label>' : '') + '</span>' +
      (n ? '<span class="qty" data-stop><button type="button" data-dec="' + i + '" aria-label="One less">−</button><b>' + n + '</b><button type="button" data-inc="' + i + '" aria-label="One more">+</button></span>' : '<span class="qty"><span>+</span></span>') + '</div>';
  }
  function renderPlanner(onlyResult) {
    if (!onlyResult) $$('[data-planner-apps]').forEach(function (el) { el.innerHTML = APPS.map(appBtn).join(''); });
    var r = size();
    $$('[data-planner-result]').forEach(function (el) {
      if (!r) { el.innerHTML = '<h3>Your recommended system</h3><p class="empty">Choose at least one appliance to see your system and price.</p>'; return; }
      el.innerHTML = '<h3>Your recommended system</h3><div class="meter"><div><b>' + r.kwh.toFixed(1) + ' kWh</b><small>used per day</small></div><div><b>' + (r.inv ? Math.round(r.inv.watts / 100) / 10 + ' kW' : '—') + '</b><small>inverter</small></div><div><b>' + (r.batt ? Math.round(r.batt.kwh * r.bq) + ' kWh' : '—') + '</b><small>battery</small></div></div>' +
        '<ul class="bom">' + r.items.map(function (x) { return '<li><span>' + (x[1] > 1 ? x[1] + ' × ' : '') + esc(x[0].name) + '</span><span>' + money(x[0].price * x[1]) + '</span></li>'; }).join('') + '<li><span>Installation and commissioning</span><span>' + money(r.inst) + '</span></li></ul>' +
        '<div class="total"><span>Estimated total</span><b>' + money(r.goods + r.inst) + '</b></div>' +
        '<div class="row"><button class="btn b-sun" data-plan="cart">Add system to cart</button><button class="btn b-line" data-plan="survey">Book a site survey</button></div><p class="fine">Peak load about ' + Math.round(r.peak).toLocaleString() + ' W. An engineer confirms the final size after a site survey.</p>';
    });
    savePlan();
  }

  /* ---------------- hero carousel ---------------- */
  var setHdr = function () { var hd = $('header.main'), an = $('.announce'); var hgt = (hd ? hd.offsetHeight : 0) + (an ? an.offsetHeight : 0); document.documentElement.style.setProperty('--hdr', hgt + 'px'); };
  setHdr(); window.addEventListener('resize', setHdr);
  $$('.hero').forEach(function (h) {
    var sl = $$('.hslide', h), tabs = $$('.htabs button', h), n = sl.length, idx = 0, timer, dur = 7000;
    if (n < 2) return;
    h.style.setProperty('--dur', dur / 1000 + 's');
    var go = function (i) {
      idx = (i + n) % n;
      sl.forEach(function (s, k) { s.classList.toggle('on', k === idx); s.setAttribute('aria-hidden', k === idx ? 'false' : 'true'); });
      tabs.forEach(function (t, k) { t.classList.remove('on'); t.classList.toggle('done', k < idx); t.setAttribute('aria-selected', k === idx ? 'true' : 'false'); });
      void (tabs[idx] && tabs[idx].offsetWidth); if (tabs[idx]) tabs[idx].classList.add('on');
    };
    var auto = h.dataset.autoplay === '1' && !reduce;
    var tick = function () { clearTimeout(timer); if (auto) timer = setTimeout(function () { if (!document.hidden && !h.classList.contains('paused')) go(idx + 1); tick(); }, dur); };
    tabs.forEach(function (t, k) { t.addEventListener('click', function () { go(k); tick(); }); });
    h.addEventListener('mouseenter', function () { h.classList.add('paused'); clearTimeout(timer); });
    h.addEventListener('mouseleave', function () { h.classList.remove('paused'); go(idx); tick(); });
    h.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') { go(idx + 1); tick(); } if (e.key === 'ArrowLeft') { go(idx - 1); tick(); } });
    var sx = null; h.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    h.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) { go(idx + (dx < 0 ? 1 : -1)); tick(); } sx = null; }, { passive: true });
    if (!auto) h.classList.add('paused');
    go(0); tick();
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
  var FMSG = { name: 'Please enter your full name.', phone: 'Enter a valid phone number, for example 0712 345 678.', email: 'Enter a valid email address, for example name@gmail.com.', county: 'Please enter your county or town.', address: 'Please enter the address for delivery, installation or the visit.', message: 'Tell us briefly what you need.', number: 'Enter your order or invoice number.' };
  /* clear a field's error, and the form message, as soon as the visitor fixes it */
  document.addEventListener('input', function (ev) {
    var i = ev.target; if (!i.classList || !i.classList.contains('input-err')) return;
    if (i.checkValidity && i.checkValidity() && String(i.value).trim()) {
      fieldErr(i, ''); var f = i.form;
      if (f && !f.querySelector('.input-err')) { var m = f.querySelector('.formmsg.err'); if (m) { m.textContent = ''; m.className = 'formmsg'; } }
    }
  });
  function fieldErr(i, text) {
    var box = i.closest('.f'), e = box ? box.querySelector('.ferr') : (i.nextElementSibling && i.nextElementSibling.classList.contains('ferr') ? i.nextElementSibling : null);
    i.classList.toggle('input-err', !!text); i.setAttribute('aria-invalid', text ? 'true' : 'false');
    if (!text) { if (e) e.remove(); return; }
    if (!e) { e = document.createElement('small'); e.className = 'ferr'; e.id = (i.id || i.name) + '-err-' + Math.random().toString(36).slice(2, 6); if (box) box.appendChild(e); else i.insertAdjacentElement('afterend', e); i.setAttribute('aria-describedby', e.id); }
    e.textContent = text;
  }
  function check(f) {
    var bad = null;
    $$('input,textarea,select', f).forEach(function (i) {
      if (i.type === 'hidden' || i.classList.contains('hp') || i.type === 'radio' || i.type === 'checkbox') return;
      var v = (i.value || '').trim(), err = '';
      if (i.required && !v) err = FMSG[i.name] || 'This field is needed.';
      else if (v && i.name === 'phone' && !validPhone(v)) err = FMSG.phone;
      else if (v && i.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) err = FMSG.email;
      fieldErr(i, err);
      if (err && !bad) bad = i;
    });
    if (bad) { bad.focus(); bad.scrollIntoView({ block: 'center', behavior: 'smooth' }); msg(f, 'Please check the highlighted field' + ($$('.input-err', f).length > 1 ? 's' : '') + '.'); return false; }
    msg(f, '');
    return true;
  }
  document.addEventListener('input', function (e) { if (e.target.classList && e.target.classList.contains('input-err')) fieldErr(e.target, ''); });
  $$('[data-lead-form]').forEach(function (f) {
    try { var pre = sessionStorage.getItem('soko_lead_msg'); if (pre) { f.message.value = pre; sessionStorage.removeItem('soko_lead_msg'); } } catch (e) {}
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!check(f)) return;
      var b = formData(f), btn = $('button[type=submit]', f);
      b.source = f.dataset.source || 'Website quote form';
      btn.disabled = true; btn.textContent = 'Sending…';
      api('store/lead', { method: 'POST', body: b }).then(function () {
        f.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Thank you, ' + esc(b.name.split(' ')[0]) + '</h3><p class="muted">We have your request. An engineer will call you on ' + esc(phoneFmt(b.phone)) + '.</p></div>';
      }).catch(function (err) { btn.disabled = false; btn.textContent = 'Send request'; msg(f, err.message); });
    });
  });

  /* ---------------- checkout ---------------- */
  function hasGoods() { return cart.some(function (c) { var p = byId[c.id]; return !p || p.kind === 'product'; }); }
  function installing() { return cart.some(function (c) { return c.install; }); }
  function pickupChosen() { var r = document.querySelector('[name=delivery]:checked'), l = r && r.closest('label'); return !!(l && /pick|collect/i.test(l.textContent)); }
  function deliveryFee() { if (!hasGoods() || installing()) return 0; var r = $('[name=delivery]:checked'); return r ? +r.dataset.fee : 0; }
  function renderCheckout() {
    var co = $('[data-checkout]');
    if (!co || co.dataset.done) return;
    var lines = $('[data-co-lines]', co), sum = $('[data-co-sum]', co), payBtn = $('[data-pay]', co);
    if (!cart.length) { lines.innerHTML = emptyCart(); sum.innerHTML = ''; payBtn.disabled = true; return; }
    payBtn.disabled = false;
    lines.innerHTML = linesHTML(false);
    sum.innerHTML = sumHTML(hasGoods() && !installing() && !$('[name=delivery]:checked') ? 'pick' : deliveryFee());
    payBtn.textContent = S.stk ? 'Pay ' + money(subtotal() + deliveryFee()) + '' : 'Place order · ' + money(subtotal() + deliveryFee());
    var svc = cart.some(function (c) { var p = byId[c.id]; return p && p.kind !== 'product'; });
    var inst = $('.inst-date', co); if (inst) { inst.hidden = !(svc || cart.some(function (c) { return c.install; })); var dl = $('[data-datelab]', co); if (dl) dl.textContent = cart.some(function (c) { return c.install; }) ? 'Preferred installation date' : 'Preferred visit date'; }
    var db = $('.delblock', co); if (db) {
      db.hidden = !hasGoods() || installing();
      var bo = $('.bo-note', db), any = cart.some(function (c) { return backorder(c.id); });
      if (any && !bo) { db.insertAdjacentHTML('beforeend', '<p class="bo-note">Some items are ordered in for you and arrive in about a week. We deliver them, or have them ready to collect, once they arrive.</p>'); }
      if (!any && bo) bo.remove();
      $$('.opts label', db).forEach(function (l) {
        if (!/pick|collect/i.test(l.textContent)) return;
        var sm = l.querySelector('small'); if (!sm) return;
        if (!sm.dataset.orig) sm.dataset.orig = sm.textContent;
        sm.textContent = any ? sm.dataset.orig.replace(/Ready in [^·]*/i, 'Ready when your order arrives (about a week) ') : sm.dataset.orig;
      });
    }
    var eo = $('.inst-only', co); if (eo) eo.hidden = !installing();
    var ins = $('.inst-note', co); if (installing() && !ins) { var anchor = $('.inst-date', co); if (anchor) anchor.insertAdjacentHTML('beforebegin', '<p class="inst-note">' + (cart.every(function (c) { return c.install || (byId[c.id] && byId[c.id].kind !== 'product'); }) ? 'Our team delivers and installs everything on this order, so there is no separate delivery choice.' : 'Everything on this order comes with your installation team, so there is no separate delivery choice.') + '</p>'); } else if (!installing() && ins) ins.remove();
  }
  var coForm = $('[data-co-form]');
  if (coForm) {
    try { var me = JSON.parse(localStorage.getItem('soko_me') || 'null'); if (me) Object.keys(me).forEach(function (k) { if (coForm[k] && !coForm[k].value) coForm[k].value = me[k]; }); } catch (e) {}
    coForm.addEventListener('change', function (e) { if (e.target.name === 'delivery') renderCheckout(); });
    coForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var dsel = coForm.querySelector('[name=delivery]:checked'), needAddr = (hasGoods() && dsel && +dsel.value > 0) || cart.some(function (c) { return c.install || (byId[c.id] && byId[c.id].kind !== 'product'); });
      ['address', 'county'].forEach(function (n) { if (coForm[n]) coForm[n].required = !!needAddr; });
      if (!cart.length || !check(coForm)) return;
      if (hasGoods() && !installing() && !dsel) { var dbx = $('.delblock', coForm.closest('[data-checkout]') || document); msg(coForm, 'Choose delivery or pick up.'); if (dbx) dbx.scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
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
    setTimeout(function () { try { var hd = document.querySelector('.hdr, header'); var y = host.getBoundingClientRect().top + scrollY - ((hd && getComputedStyle(hd).position.match(/sticky|fixed/)) ? hd.offsetHeight : 0) - 16; scrollTo({ top: Math.max(0, y), behavior: 'smooth' }); } catch (e) {} var st = document.querySelectorAll('.costeps li'); if (st[2]) st[2].classList.add('on'); }, 30);
    var instr = pay && pay.number ? '<div class="paybill"><div><span>M-Pesa ' + esc(pay.kind) + '</span><b>' + esc(pay.number) + '</b></div>' + (pay.kind === 'Paybill' ? '<div><span>Account number</span><b>' + esc(d.doc) + '</b></div>' : '') + '<div><span>Amount</span><b>' + money(d.balance != null ? d.balance : d.total) + '</b></div></div>' : '';
    var pk = pickupChosen() ? document.querySelector('[name=delivery]:checked').closest('label').querySelector('small') : null;
    var trackLink = (pk ? '<p class="pickup"><span><b>Pick up</b> ' + esc((pk.dataset.orig || pk.textContent).replace(/^.*?·\s*/, '')) + '. We call you when it is ready.</span></p>' : '') + (d.url ? '<a class="btn b-line wide" href="' + esc(d.url) + '">View and track this order</a>' : '');
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
    } else if (!instr) {
      host.innerHTML = '<div class="payflow"><div class="okc" style="background:var(--blue)"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="M5 12l5 5 9-10"/></svg></div><h3>Order ' + esc(d.doc) + ' received</h3><p class="muted">Thank you. Our team will call you shortly to confirm your order and how you would like to pay.</p>' + (S.phone ? '<a class="btn b-sun wide" href="tel:+' + esc(String(S.phone).replace(/^\+/, '')) + '">Call us now</a>' : '') + trackLink + '</div>';
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
  function paidView(host2) { var st = document.querySelectorAll('.costeps li'); for (var i = 0; i < st.length; i++) st[i].classList.add('on'); return paidView0.apply(this, arguments); }
  function paidView0(host, d, s) {
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
        if (s.void) { pp.innerHTML = '<div class="payflow"><h3>Order cancelled</h3><p class="muted">This order was cancelled and nothing is due. Call us if you would like to order again.</p><a class="btn b-sun wide" href="tel:+' + esc(String(S.phone || '').replace(/^\+/, '')) + '">Call us</a></div>'; return; }
        if (s.balance <= 0) { pp.innerHTML = '<div class="payflow"><div class="okc"><svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10"/></svg></div><h3>Fully paid</h3><p class="muted">Thank you. We will be in touch to arrange delivery' + (s.fulfil === 'dispatched' || s.fulfil === 'delivered' ? '' : ' and installation where you chose it') + '.</p></div>'; return; }
        if (!S.stk) {
          pp.innerHTML = '<h3>How to pay</h3>' + (S.pay && S.pay.number ? '<p class="muted">Pay from your M-Pesa menu:</p><div class="paybill"><div><span>' + esc(S.pay.kind) + '</span><b>' + esc(S.pay.number) + '</b></div>' + (S.pay.kind === 'Paybill' ? '<div><span>Account</span><b>' + esc(td.doc) + '</b></div>' : '') + '</div><p class="muted" style="margin-top:12px">Your receipt appears here once the payment is confirmed.</p>' : '<p class="muted">Our team will call you to confirm your order and how you would like to pay.</p>') + '<a class="btn b-sun wide" href="tel:+' + esc(String(S.phone || '').replace(/^\+/, '')) + '">Call us</a>';
          return;
        }
        pp.innerHTML = '<h3>Pay ' + money(s.balance) + '</h3><form data-paybal novalidate><div class="f"><label for="pPhone">Phone number to pay from</label><input id="pPhone" name="phone" inputmode="tel" value="' + esc(td.phone) + '" required></div><div class="f"><label for="pAmt">Amount</label><input id="pAmt" name="amount" type="number" min="1" max="' + s.balance + '" value="' + Math.ceil(s.balance) + '"></div><button class="btn b-sun wide">Send payment request</button><p class="formmsg"></p></form>' + (S.pay && S.pay.number ? '<p class="muted" style="margin-top:14px">Or pay from your M-Pesa menu:</p><div class="paybill"><div><span>' + esc(S.pay.kind) + '</span><b>' + esc(S.pay.number) + '</b></div>' + (S.pay.kind === 'Paybill' ? '<div><span>Account</span><b>' + esc(td.doc) + '</b></div>' : '') + '</div>' : '');
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
    api('store/find', { method: 'POST', body: formData(findF) }).then(function (r) { if (r.found === false) { msg(findF, r.message); return; } location.href = r.url; }).catch(function (err) { msg(findF, err.message); });
  });

  /* ---------------- product page ---------------- */
  var pdp = $('[data-product]');
  if (pdp) {
    var pd = JSON.parse(pdp.dataset.product), q = $('[data-qty] input', pdp);
    var inst = function () { var r = $('[name=inst]:checked', pdp); return r && r.value === '1'; };
    var pb = $('.price.big b', pdp) || $('.price.big b');
    var syncPrice = function () {
      if (!pb) return;
      var qn = Math.max(1, +q.value || 1), total = (pd.price + (inst() ? (pd.install || 0) : 0)) * qn;
      pb.textContent = money(total);
      var note = $('.pnote', pdp) || $('.pnote');
      if (!note) { note = document.createElement('small'); note.className = 'pnote'; pb.parentNode.appendChild(note); }
      note.textContent = (inst() ? 'Includes installation' : 'Supply only') + (qn > 1 ? ' · ' + qn + ' units' : '');
      pb.classList.remove('tick'); void pb.offsetWidth; pb.classList.add('tick');
      var sb = document.querySelector('.sbuy b'); if (sb) sb.textContent = money(total);
    };
    $$('[name=inst]', pdp).forEach(function (r) { r.addEventListener('change', syncPrice); });
    if (q) q.addEventListener('input', syncPrice);
    var capQ = function () { var mx = maxQty(pd.id), v = Math.round(+q.value || 1); if (v > mx) { v = mx; toast(capMsg(pd.id, mx)); } q.value = Math.max(1, v); };
    if (q) { q.max = maxQty(pd.id); q.min = 1; q.addEventListener('change', function () { capQ(); syncPrice(); }); }
    $$('[data-qty] button', pdp).forEach(function (b) { b.onclick = function () { q.value = Math.max(1, (+q.value || 1) + +b.dataset.d); capQ(); syncPrice(); }; });
    $('[data-add-pdp]', pdp).onclick = function () { add(pd.id, Math.max(1, +q.value || 1), inst()); };
    $('[data-buy-pdp]', pdp).onclick = function () { add(pd.id, Math.max(1, +q.value || 1), inst(), true); location.href = S.home + 'checkout/'; };
    $$('.thumbs button', pdp).forEach(function (b) { b.onclick = function () { $$('.thumbs button', pdp).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); var im = $('.gmain img', pdp); if (im) im.src = b.dataset.img; }; });
    var rt = $('[data-runtime]', pdp);
    if (rt) {
      var kwh = +rt.dataset.runtime, loads = [['LED bulbs (×6)', 60, true], ['TV', 100, true], ['Fridge (average draw)', 60, true], ['Wi-Fi router', 15, true], ['Laptop', 60, false], ['CCTV (×2)', 40, false], ['POS and phone charging', 30, false], ['Desktop computer', 150, false]];
      $('.rt-list', rt).innerHTML = loads.map(function (l, i) { return '<label><input type="checkbox" data-rt="' + i + '"' + (l[2] ? ' checked' : '') + '><span>' + esc(l[0]) + '</span><small>' + l[1] + ' W</small></label>'; }).join('');
      var calc = function () {
        var w = 0; $$('[data-rt]', rt).forEach(function (c) { if (c.checked) w += loads[c.dataset.rt][1]; });
        var hrs = w ? kwh * 1000 * 0.85 / w : 0;
        $('.rt-out', rt).innerHTML = (w ? '<span>About ' + w + ' W running</span>' : '<span>Tick what you would run</span>') + '<b>' + (w ? (hrs >= 24 ? Math.floor(hrs / 24) + ' day' + (hrs >= 48 ? 's ' : ' ') + Math.round(hrs % 24) + ' h' : (Math.round(hrs * 10) / 10) + ' hours') : '—') + '</b>';
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
    var a = t.closest('[data-add]'); if (a) { e.preventDefault(); a.classList.remove('added'); void a.offsetWidth; a.classList.add('added'); add(a.dataset.add, 1, 0, !!a.closest('.drawer')); if (a.closest('.drawer')) cartDrawer(); return; }
    var qk = t.closest('[data-quick]'); if (qk) { quick(JSON.parse(qk.closest('.card').dataset.p)); return; }
    var qa = t.closest('[data-q-add]'); if (qa) { var r = $('[name=qinst]:checked'); add(qa.dataset.qAdd, 1, r && r.value === '1'); closeDrawer(); return; }
    var qb = t.closest('[data-q-buy]'); if (qb) { var r2 = $('[name=qinst]:checked'); add(qb.dataset.qBuy, 1, r2 && r2.value === '1', true); location.href = S.home + 'checkout/'; return; }
    if (t.closest('[data-close]') || t.closest('[data-scrim]')) { closeDrawer(); return; }
    if (t.closest('[data-open-cart]')) { cartDrawer(); return; }
    var cq = t.closest('[data-cq]'); if (cq) { var c = cart[+cq.dataset.cq]; if (+cq.dataset.d > 0 && cart.filter(function (x) { return x.id === c.id; }).reduce(function (a, x) { return a + x.qty; }, 0) >= maxQty(c.id)) { toast('Only ' + maxQty(c.id) + ' available.'); return; } if (+cq.dataset.d < 0 && c.qty <= 1) { return; } c.qty += +cq.dataset.d; saveCart(); if ($('[data-drawer]').classList.contains('on')) cartDrawer(); return; }
    var rm = t.closest('[data-rm]'); if (rm) { cart.splice(+rm.dataset.rm, 1); saveCart(); if ($('[data-drawer]').classList.contains('on')) cartDrawer(); return; }
    if (t.closest('[data-msrch]')) { $('[data-mnav]').classList.add('on'); var mi = $('.msearch input'); if (mi) setTimeout(function () { mi.focus(); }, 60); return; }
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
        var text = 'Solar calculator result: ' + r3.kwh.toFixed(1) + ' kWh a day, peak about ' + Math.round(r3.peak) + ' W. Appliances: ' + Object.keys(picks).map(function (i) { return picks[i] + ' × ' + APPS[i][0]; }).join(', ') + '. Suggested: ' + r3.items.map(function (x) { return x[1] + ' × ' + x[0].name; }).join(', ') + '.';
        openDrawer('<div class="dhead"><b>Book a site survey</b><button class="ico" data-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div><div class="dbody"><p class="muted" style="margin-bottom:16px">We will call to agree a time. Your planner result is attached.</p><form data-survey novalidate><div class="f"><label for="sName">Full name</label><input id="sName" name="name" required></div><div class="f"><label for="sPhone">Phone</label><input id="sPhone" name="phone" inputmode="tel" required></div><div class="f"><label for="sCounty">Where is the site?</label><input id="sCounty" name="county" placeholder="Estate and county"></div><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off"><button class="btn b-sun wide">Request the survey</button><p class="formmsg"></p></form></div>');
        var sf = $('[data-survey]');
        sf.onsubmit = function (ev) {
          ev.preventDefault(); if (!check(sf)) return;
          var b = formData(sf);
          api('store/lead', { method: 'POST', body: { name: b.name, phone: b.phone, county: b.county, website: b.website, source: 'Solar calculator', interest: 'Site survey: ' + (r3.inv ? r3.inv.name : 'solar system'), value: r3.goods + r3.inst, message: text, data: { picks: picks, hours: hours } } })
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
    if (e.target.matches('[data-hrs]')) { var hv = +e.target.value; if (hv > 24) { e.target.value = 24; toast('A day has at most 24 hours, so we used 24.'); } hours[e.target.dataset.hrs] = Math.max(0.1, Math.min(24, +e.target.value || 0.1)); savePlan(); renderPlanner(true); }
  });
  document.addEventListener('change', function (e) { if (e.target.matches('[data-f-brand],[data-f-stock],[data-f-sort]')) filterShop(); });

  renderPlanner();
  saveCart();
  filterShop();
})();
/* collapsed header search: first tap opens the field */
document.querySelectorAll('form.search').forEach(function (f) {
  var i = f.querySelector('input');
  f.addEventListener('submit', function (e) { if (!i.value.trim()) { e.preventDefault(); i.focus(); } });
});

/* light / dark mode */
document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
  b.addEventListener('click', function () {
    var d = document.documentElement, dark = d.getAttribute('data-theme') !== 'dark';
    if (dark) d.setAttribute('data-theme', 'dark'); else d.removeAttribute('data-theme');
    try { localStorage.setItem('soko_theme', dark ? 'dark' : 'light'); } catch (e) {}
  });
});

/* ===== interactive sections ===== */
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var kes = function (n) { return 'KES ' + Math.round(n).toLocaleString('en-KE'); };

  /* product tabs */
  $$('[data-ptabs]').forEach(function (t) {
    var grid = t.parentNode.querySelector('[data-tabgrid]'); if (!grid) return;
    var lim = +grid.dataset.limit || 8;
    var show = function (cats) {
      var list = cats ? cats.split(',') : null, n = 0;
      /* fill whole rows: 3 columns on tablets, 2 on phones, 4 on desktop */
      var cols = getComputedStyle(grid).gridTemplateColumns.split(' ').length || 4;
      var avail = $$('.card', grid).filter(function (c) { return !list || list.indexOf(c.dataset.cat) > -1; }).length;
      var cap = Math.min(lim, avail); if (cap > cols) cap = cap - (cap % cols);
      $$('.card', grid).forEach(function (c) {
        var ok = !list || list.indexOf(c.dataset.cat) > -1;
        ok = ok && n < cap; if (ok) n++;
        c.hidden = !ok; if (ok) { c.style.animation = 'none'; void c.offsetWidth; c.style.animation = ''; c.style.animationDelay = (n * 0.04) + 's'; }
      });
    };
    t.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      $$('button', t).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-selected', x === b ? 'true' : 'false'); });
      show(b.dataset.c);
    });
    var on0 = t.querySelector('button.on');
    if ((getComputedStyle(grid).gridTemplateColumns.split(' ').length || 4) !== 4) show(on0 ? on0.dataset.c : null);
  });

  /* savings calculator */
  $$('[data-savings]').forEach(function (s) {
    var c = JSON.parse(s.dataset.savings), r = $('[data-sv-range]', s), mode = 'home', raf;
    var count = function (el, to, fmt) {
      var from = el._v || 0, t0 = performance.now();
      var step = function (t) { var k = Math.min(1, (t - t0) / 450), v = from + (to - from) * (1 - Math.pow(1 - k, 3)); el.textContent = fmt(v); if (k < 1) requestAnimationFrame(step); };
      el._v = to; requestAnimationFrame(step);
    };
    var calc = function () {
      var bill = +r.value, pct = (bill - r.min) / (r.max - r.min) * 100;
      r.style.setProperty('--p', pct + '%');
      var kwhDay = bill / c.tariff / 30, share = mode === 'home' ? 0.75 : 0.7;
      var kw = Math.max(1, Math.round(kwhDay * share / 4.5 * 2) / 2);
      var batt = mode === 'home' ? Math.max(2.5, Math.round(kwhDay * 0.45 / 2.5) * 2.5) : Math.round(kwhDay * 0.15 / 5) * 5;
      var cost = kw * c.kwCost + batt * c.kwhCost, save = bill * share, years = cost / (save * 12);
      $('[data-sv-bill]', s).textContent = kes(bill);
      count($('[data-sv-size]', s), kw, function (v) { return (Math.round(v * 2) / 2) + ' kW'; });
      $('[data-sv-batt]', s).textContent = batt ? batt + ' kWh battery' : 'no battery needed';
      count($('[data-sv-save]', s), save, kes);
      count($('[data-sv-pay]', s), years, function (v) { return v < 1.5 ? 'about a year' : (Math.round(v * 2) / 2) + ' years'; });
      $('[data-sv-now]', s).textContent = kes(bill);
      $('[data-sv-after]', s).textContent = kes(bill - save);
      var wA = Math.max(12, (1 - share) * 100), bA = $('[data-sv-barafter]', s); bA.style.setProperty('--w', wA + '%'); bA.classList.toggle('out', wA < 60);
      var kit = $('[data-sv-kit]', s); if (kit) kit.href = kit.href.replace(/\/shop\/[^/]+\/$/, mode === 'home' ? '/shop/kits/' : '/shop/hybrid/');
    };
    r.addEventListener('input', function () { cancelAnimationFrame(raf); raf = requestAnimationFrame(calc); });
    $$('[data-m]', s).forEach(function (b) { b.addEventListener('click', function () { mode = b.dataset.m; $$('[data-m]', s).forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', x === b ? 'true' : 'false'); }); if (mode === 'biz' && +r.value < 20000) r.value = 40000; calc(); }); });
    calc();
  });

  /* scroll reveal + timeline */
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var io = ('IntersectionObserver' in window) && !reduce ? new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px 15% 0px', threshold: 0 }) : null;
  function watch(el) { if (io) io.observe(el); else el.classList.add('in'); }
  /* section headings: kicker line draws, title rises word by word, intro follows */
  $$('main .sec-head, main .pcopy, main .phero h1, main .cta-copy').forEach(function (h) {
    var t = h.matches('h1') ? h : h.querySelector('h2, h1');
    if (t && !t.dataset.split) {
      t.dataset.split = 1;
      var words = t.textContent.trim().split(/\s+/);
      t.innerHTML = words.map(function (w, i) { return '<span class="w"><span style="--i:' + Math.min(i, 5) + '">' + w.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span></span>'; }).join(' ');
    }
    var host = h.matches('h1') ? h : h; host.classList.add('hd'); watch(host);
  });
  /* items: every card, tile, step and stat rises in turn */
  var groups = '.cats, .grid, .svc, .proj, .steps, .strip .wrap, .bgrid, .cols, .mzstats, .fband .wrap, .tsteps, .meter, .trust, .gal, .apps, .faq, .shpills, .qa, .tiles, .sv-cards';
  $$(groups).forEach(function (g) {
    Array.prototype.forEach.call(g.children, function (el, i) {
      if (el.classList.contains('ri')) return;
      el.classList.add('ri'); el.style.setProperty('--d', Math.min(i % 8, 4) * 30 + 'ms'); watch(el);
    });
  });
  /* larger single blocks */
  $$('main .sv-grid > *, main .cta .wrap > *, main .contact > *, main .ti > *, main .rich, main .cartpage > *, main .co > *').forEach(function (el, i) { if (!el.classList.contains('ri')) { el.classList.add('ri', 'ri-b'); el.style.setProperty('--d', (i % 2) * 40 + 'ms'); watch(el); } });
  $$('.tl').forEach(watch);
  /* jumping to a section reveals it straight away, so nothing is blank on arrival */
  function revealAt(id) { var t = id && document.getElementById(id); if (!t) return; [t].concat([].slice.call(t.querySelectorAll('.ri,.hd'))).forEach(function (x) { x.classList.add('in'); }); var r = t.closest('.ri'); if (r) r.classList.add('in'); }
  document.addEventListener('click', function (e) { var a = e.target.closest('a[href^="#"]'); if (a && a.getAttribute('href').length > 1) revealAt(a.getAttribute('href').slice(1)); });
  if (location.hash.length > 1) revealAt(location.hash.slice(1));
  /* numbers count up when they come into view */
  function countUp(el) {
    if (/\d\s*[–-]\s*\d/.test(el.textContent)) return; // a range such as "3–10 kW" is shown as it is
    var m = el.textContent.match(/^([^0-9]*)([0-9][0-9,]*\.?[0-9]*)(.*)$/); if (!m) return;
    var end = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, t0 = null, comma = m[2].indexOf(',') > -1;
    function f(n) { var s = n.toFixed(dec); return comma ? Number(s).toLocaleString('en-GB', { minimumFractionDigits: dec, maximumFractionDigits: dec }) : s; }
    function step(t) { t0 = t0 || t; var k = Math.min(1, (t - t0) / 450), e = 1 - Math.pow(1 - k, 3); el.textContent = m[1] + f(end * e) + m[3]; if (k < 1) requestAnimationFrame(step); }
    requestAnimationFrame(step);
  }
  var cio = ('IntersectionObserver' in window) && !reduce ? new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { countUp(e.target); cio.unobserve(e.target); } }); }, { threshold: 0.6 }) : null;
  if (cio) $$('.mzstats b, .stats b, .pj .size, .hfact b, .meter b').forEach(function (el) { cio.observe(el); });
})();
/* product page: sticky buy bar on phones once the main buttons scroll away */
(function () {
  var row = document.querySelector('.buyrow'), pdp = document.querySelector('[data-product]');
  if (!row || !pdp || !('IntersectionObserver' in window)) return;
  var d = {}; try { d = JSON.parse(pdp.dataset.product); } catch (e) {}
  var priceEl = document.querySelector('.buy .price b');
  var bar = document.createElement('div'); bar.className = 'sbuy';
  bar.innerHTML = '<div><small>' + (d.name || '') + '</small><b>' + (priceEl ? priceEl.textContent : '') + '</b></div><button class="btn b-sun" type="button">Add to cart</button>';
  document.body.appendChild(bar);
  bar.querySelector('button').addEventListener('click', function () { var b = document.querySelector('[data-add-pdp]'); if (b) b.click(); });
  new IntersectionObserver(function (es) { var on = !es[0].isIntersecting && es[0].boundingClientRect.top < 0; bar.classList.toggle('on', on); var t = document.querySelector('.toast'); if (t) t.classList.toggle('up', on); }).observe(row);
})();
/* phones: keep the WhatsApp button off banners and titles until the visitor scrolls */
(function () {
  var wa = document.querySelector('.wa'); if (!wa) return;
  var hero = document.querySelector('.hero'), inHero = false;
  var busy = new Set();
  function upd() { var ph = innerWidth < 1100; wa.classList.toggle('wa-hide', ph && (inHero || scrollY < 300 || busy.size > 0)); }
  /* also keep it off buttons and sliders it would cover */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) busy.add(e.target); else busy.delete(e.target); }); upd(); }, { rootMargin: '0px 0px -0px 0px' });
    document.querySelectorAll('.buyrow, .wa-ask, .sv-grid, .co form button[type=submit], .sticky-buy').forEach(function (el) { io.observe(el); });
  }
  if (hero && 'IntersectionObserver' in window) new IntersectionObserver(function (es) { inHero = es[0].intersectionRatio >= 0.35; upd(); }, { threshold: [0, 0.35] }).observe(hero);
  addEventListener('scroll', upd, { passive: true }); addEventListener('resize', upd); upd();
})();
/* category chips on phones and tablets: bring the active one into view */
(function () {
  var side = document.querySelector('.side'), on = side && side.querySelector('a.on');
  if (!on || side.scrollWidth <= side.clientWidth + 4) return;
  side.scrollLeft = Math.max(0, on.offsetLeft - (side.clientWidth - on.offsetWidth) / 2);
})();

/* project cards: one tap opens the details */
(function () {
  function open(f) {
    var x; try { x = JSON.parse(f.dataset.pj); } catch (e) { return; }
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    var home = (window.SOKO && SOKO.home) || '/';
    var d = document.createElement('div'); d.className = 'pjmodal'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-modal', 'true'); d.setAttribute('aria-label', x.name);
    d.innerHTML = '<div class="pjbox"><button class="ico pjx" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button><img src="' + esc(x.img) + '" alt="' + esc(x.name) + '"><div class="pjb"><span class="kick">' + esc(x.sector) + '</span><h3>' + esc(x.name) + '</h3><dl><dt>System</dt><dd>' + esc(x.size) + '</dd><dt>Type</dt><dd>' + esc(x.type) + '</dd><dt>Location</dt><dd>' + esc(x.place) + '</dd></dl>' + (x.details ? '<p>' + esc(x.details) + '</p>' : '') + '<div class="row"><a class="btn b-sun" href="' + home + 'contact/?topic=' + encodeURIComponent('A system like ' + x.name) + '#quote-form">Get a similar system</a><a class="btn b-line" href="' + home + 'planner/">Size my system</a></div></div></div>';
    document.body.appendChild(d); document.body.style.overflow = 'hidden';
    var close = function () { d.remove(); document.body.style.overflow = ''; f.focus(); document.removeEventListener('keydown', key); };
    var key = function (e) { if (e.key === 'Escape') close(); };
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.pjx')) close(); });
    document.addEventListener('keydown', key); d.querySelector('.pjx').focus();
  }
  document.addEventListener('click', function (e) { var f = e.target.closest && e.target.closest('[data-pj]'); if (f) open(f); });
  document.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('[data-pj]')) { e.preventDefault(); open(e.target); } });
})();
/* "Get a similar system": carry the project into the quote form */
(function () {
  var t = new URLSearchParams(location.search).get('topic'), f = document.getElementById('quote-form');
  if (!t || !f || !f.message || f.message.value) return;
  f.message.value = t.slice(0, 200) + '. ';
})();

/* back to top: appears after the first screen, sits above the WhatsApp button */
(function () {
  var b = document.querySelector('[data-totop]'); if (!b) return;
  var tick = false;
  function upd() { tick = false; var on = scrollY > innerHeight * 0.9; if (on) b.hidden = false; b.classList.toggle('on', on); }
  addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(upd); } }, { passive: true });
  b.addEventListener('click', function () { var r = matchMedia('(prefers-reduced-motion: reduce)').matches; scrollTo({ top: 0, behavior: r ? 'auto' : 'smooth' }); });
  upd();
})();
/* compare up to 3 products side by side */
(function () {
  var cards = document.querySelectorAll('.card[data-p]'); if (!cards.length) return;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var S = window.SOKO || {}, money = function (n) { return (S.currency || 'KES') + ' ' + Math.round(n || 0).toLocaleString('en-KE'); };
  var sel = []; try { sel = JSON.parse(sessionStorage.getItem('soko_cmp') || '[]'); } catch (e) {}
  var data = {};
  var save = function () { try { sessionStorage.setItem('soko_cmp', JSON.stringify(sel)); } catch (e) {} };
  var tray = document.createElement('div'); tray.className = 'cmptray'; tray.hidden = true; document.body.appendChild(tray);
  function draw() {
    sel = sel.filter(function (s) { return s && s.id; });
    document.querySelectorAll('[data-cmp]').forEach(function (b) { var on = sel.some(function (s) { return String(s.id) === b.dataset.cmp; }); b.classList.toggle('on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.lastChild.textContent = on ? 'Comparing' : 'Compare'; });
    tray.hidden = !sel.length;
    tray.innerHTML = '<span>' + sel.map(function (s) { return '<b>' + esc(s.name) + '</b>'; }).join('') + '</span><button class="btn b-sun" data-cmpgo' + (sel.length < 2 ? ' disabled' : '') + '>' + (sel.length < 2 ? 'Pick one more to compare' : 'Compare ' + sel.length) + '</button><button class="cmpx" data-cmpclear aria-label="Clear comparison">Clear</button>';
  }
  cards.forEach(function (c) {
    var p; try { p = JSON.parse(c.dataset.p); } catch (e) { return; }
    if (p.kind !== 'product') return;
    data[p.id] = p;
    var m = c.querySelector('.media'); if (!m) return;
    var b = document.createElement('button'); b.type = 'button'; b.className = 'cmpbtn'; b.dataset.cmp = p.id; b.setAttribute('aria-label', 'Compare ' + p.name);
    b.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M8 3v18M16 3v18M3 8h5M16 16h5"/></svg><span>Compare</span>';
    m.appendChild(b);
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-cmp]');
    if (b) {
      e.preventDefault(); e.stopPropagation();
      var id = b.dataset.cmp, i = sel.findIndex(function (s) { return String(s.id) === id; });
      if (i > -1) sel.splice(i, 1); else { if (sel.length >= 3) sel.shift(); sel.push(data[id]); }
      save(); draw(); return;
    }
    if (e.target.closest && e.target.closest('[data-cmpclear]')) { sel = []; save(); draw(); return; }
    if (e.target.closest && e.target.closest('[data-cmpgo]') && sel.length > 1) open();
  }, true);
  function open() {
    var keys = []; sel.forEach(function (p) { Object.keys(p.specs || {}).forEach(function (k) { if (keys.indexOf(k) < 0) keys.push(k); }); });
    var row = function (label, f) { return '<tr><th scope="row">' + esc(label) + '</th>' + sel.map(function (p) { return '<td>' + f(p) + '</td>'; }).join('') + '</tr>'; };
    var html = '<table class="cmptable"><thead><tr><th></th>' + sel.map(function (p) { return '<th scope="col">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '') + '<a href="' + esc(p.url) + '">' + esc(p.name) + '</a></th>'; }).join('') + '</tr></thead><tbody>' +
      row('Price', function (p) { return '<b>' + money(p.price) + '</b>' + (p.was > p.price ? ' <s>' + money(p.was) + '</s>' : ''); }) +
      row('Brand', function (p) { return esc(p.brand || '—'); }) + row('Category', function (p) { return esc(p.cat); }) +
      keys.map(function (k) { return row(k, function (p) { return esc((p.specs || {})[k] || '—'); }); }).join('') +
      row('What it powers', function (p) { return esc(p.runs || '—'); }) + row('Warranty', function (p) { return esc(p.warranty || '—'); }) +
      row('Installation', function (p) { return p.install > 0 ? money(p.install) : '—'; }) + row('Availability', function (p) { return p.stock > 0 ? 'In stock' : 'Order in, 1 week'; }) +
      row('', function (p) { return '<button class="btn b-sun" data-add="' + p.id + '">Add to cart</button>'; }) + '</tbody></table>';
    var d = document.createElement('div'); d.className = 'pjmodal'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-modal', 'true'); d.setAttribute('aria-label', 'Compare products');
    d.innerHTML = '<div class="pjbox cmpbox"><button class="ico pjx" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg></button><div class="pjb"><h3>Compare products</h3><div class="cmpscroll">' + html + '</div></div></div>';
    document.body.appendChild(d); document.body.style.overflow = 'hidden';
    var close = function () { d.remove(); document.body.style.overflow = ''; document.removeEventListener('keydown', key); };
    var key = function (e) { if (e.key === 'Escape') close(); };
    d.addEventListener('click', function (e) { if (e.target === d || e.target.closest('.pjx')) close(); });
    document.addEventListener('keydown', key); d.querySelector('.pjx').focus();
  }
  draw();
})();
