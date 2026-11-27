/* Dynamic Desk: core (shell, helpers, routing). Modules live in desk-modules.js. */
(function () {
  'use strict';
  var D = window.DESK;
  var K = window.K = {};
  var $ = K.$ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = K.$$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var esc = K.esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  K.cur = function () { return (K.me && K.me.settings && K.me.settings.currency) || 'KES'; };
  K.moneyx = function (n, bare) { n = Math.round((+n || 0) * 100) / 100; var f = Math.abs(n - Math.round(n)) > 0.004; var s = n.toLocaleString('en-KE', { minimumFractionDigits: f ? 2 : 0, maximumFractionDigits: f ? 2 : 0 }); return bare ? s : K.cur() + ' ' + s; };
  K.money = function (n, bare) { var s = Math.round(+n || 0).toLocaleString('en-KE'); return bare ? s : K.cur() + ' ' + s; };
  K.num = function (n) { return (+n || 0).toLocaleString('en-KE', { maximumFractionDigits: 2 }); };
  var MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  K.d = function (s) { if (!s) return ''; var p = String(s).slice(0, 10).split('-'); return +p[2] + ' ' + MON[+p[1] - 1] + ' ' + p[0]; };
  K.ds = function (s) { if (!s) return ''; var p = String(s).slice(0, 10).split('-'); return +p[2] + ' ' + MON[+p[1] - 1]; };
  K.t = function (s) { return s ? String(s).slice(11, 16) : ''; };
  K.iso = function (dt) { var z = new Date(dt.getTime() - dt.getTimezoneOffset() * 60000); return z.toISOString().slice(0, 10); };
  K.today = function () { return K.iso(new Date()); };
  K.add = function (iso, n) { var d = new Date(iso + 'T12:00:00'); d.setDate(d.getDate() + n); return K.iso(d); };
  K.phone = function (p) { var d = String(p || '').replace(/\D/g, ''); if (d.length === 12 && d.indexOf('254') === 0) { d = '0' + d.slice(3); return d.slice(0, 4) + ' ' + d.slice(4, 7) + ' ' + d.slice(7); } return p || ''; };
  K.icon = function (k, s) { return (D.icons[k] || D.icons.panel).replace(/width="24" height="24"/, 'width="' + (s || 24) + '" height="' + (s || 24) + '"'); };
  K.ic = function (path) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="' + path + '"/></svg>'; };
  K.ICONS = {
    accounts: 'M4 4h12a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3zM4 17a3 3 0 0 1 3-3h12M8 8h7M8 11h5',
    home: 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', sales: 'M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7', payments: 'M3 6h18v12H3zM3 10h18M7 15h3',
    crm: 'M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 20v-2a4 4 0 0 0-3-3.9M16 2.1a4 4 0 0 1 0 7.8', jobs: 'M14 6l4 4-9 9H5v-4zM12 8l4 4',
    schedule: 'M4 5h16v16H4zM4 10h16M9 3v4M15 3v4', stock: 'M3 7l9-4 9 4-9 4zM3 7v10l9 4 9-4V7M12 11v10', purchases: 'M3 3h2l3 12h11l2-8H7M10 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2zM18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
    expenses: 'M12 2v20M17 6H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', reports: 'M4 20V10M10 20V4M16 20v-8M22 20H2', compliance: 'M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6zM9 12l2 2 4-4',
    studio: 'M3 5h18v14H3zM3 9h18M7 13h5M7 16h8', blog: 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5', users: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c1.5-4 4.5-6 8-6s6.5 2 8 6', settings: 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M14 4v4M8 10v4M16 16v4',
    plus: 'M12 5v14M5 12h14', x: 'M6 6l12 12M18 6L6 18', search: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4', menu: 'M4 7h16M4 12h16M4 17h16', ext: 'M14 4h6v6M10 14L20 4M19 13v6H5V5h6',
    dl: 'M12 4v12M7 11l5 5 5-5M4 20h16', mail: 'M3 5h18v14H3zM3 7l9 6 9-6', wa: 'M3 21l1.6-4.6A8.5 8.5 0 1 1 8 20z', phone: 'M5 4h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 6a2 2 0 0 1 2-2',
    edit: 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4', copy: 'M8 8h12v12H8zM4 16V4h12', trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3', check: 'M5 12l5 5 9-10', up: 'M12 19V5M6 11l6-6 6 6', down: 'M12 5v14M6 13l6 6 6-6',
    eye: 'M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', cash: 'M3 7h18v10H3zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z', truck: 'M3 7h11v9H3zM14 10h4l3 3v3h-7M7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4zM17 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4z'
  };

  /* ---------------- api ---------------- */
  K.api = function (path, o) {
    o = o || {};
    return fetch(D.api + path, {
      method: o.method || (o.body ? 'POST' : 'GET'),
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce },
      body: o.body ? JSON.stringify(o.body) : undefined,
      credentials: 'same-origin'
    }).then(function (r) {
      if (r.status === 401) { K.signedOut(); }
      return r.text().then(function (t) {
        var j; try { j = JSON.parse(t); } catch (e) { j = { message: 'Something went wrong. Please try again.' }; }
        if (!r.ok && j && (j.code === 'rest_cookie_invalid_nonce' || j.code === 'rest_not_logged_in')) { K.signedOut(); var se = new Error('You have been signed out.'); se.status = 401; throw se; }
        if (!r.ok) { var err = new Error(j && j.message ? j.message : 'Something went wrong.'); err.status = r.status; err.code = j && j.code; throw err; }
        return j;
      });
    });
  };
  K.signedOut = function () { if (K._out) return; K._out = 1; K.toast('You have been signed out. Taking you to the sign-in page…', true); setTimeout(function () { location.href = D.site + 'desk/login/'; }, 1600); };
  K.fail = function (e) { if (K._out) return; K.toast(e && e.message ? e.message : 'Something went wrong.', true); if (e && e.status === 401) K.signedOut(); };
  /* text search that also finds phone numbers typed as 07…, +254… or 7… */
  K.qmatch = function (q, hay, phone) {
    q = String(q || '').trim().toLowerCase(); if (!q) return true;
    if (String(hay || '').toLowerCase().indexOf(q) > -1) return true;
    var qa = q.replace(/[^a-z0-9]/g, '');
    if (qa.length >= 3 && String(hay || '').toLowerCase().replace(/[^a-z0-9]/g, '').indexOf(qa) > -1) return true;
    if (/[a-z]/.test(q)) return false;
    var d = q.replace(/\D/g, ''); if (d.length < 3) return false;
    d = d.replace(/^254/, '').replace(/^0/, '');
    var ph = String(phone || '').replace(/\D/g, '').replace(/^254/, '').replace(/^0/, '');
    return !!d && ph.indexOf(d) > -1;
  };
  K.method = function (m) { var map = { mpesa: 'M-Pesa', 'm-pesa': 'M-Pesa', bank: 'Bank transfer', cash: 'Cash', card: 'Card', cheque: 'Cheque' }; m = String(m || ''); if (/^\d{4}$/.test(m)) { var bk = ((window.DESK && DESK.banks) || []).filter(function (b) { return String(b[0]) === m; })[0]; if (bk) return bk[1]; } return map[m.toLowerCase()] || (m.charAt(0).toUpperCase() + m.slice(1)); };
  K.waNum = function (p) { var d = String(p || '').replace(/\D/g, ''); if (/^0\d{9}$/.test(d)) d = '254' + d.slice(1); else if (/^[17]\d{8}$/.test(d)) d = '254' + d; return d; };
  K.url = function (path) { return D.api + path + (path.indexOf('?') > -1 ? '&' : '?') + '_wpnonce=' + D.nonce; };
  /* A website order counts as a sale once the team confirms it or it is paid. */
  K.searchHint = function () { var w = [['sales', 'invoices'], ['crm', 'customers'], ['payments', 'payments'], ['stock', 'products'], ['jobs', 'jobs'], ['purchases', 'orders']].filter(function (x) { return K.can(x[0]); }).map(function (x) { return x[1]; }).slice(0, 4); return w.length ? 'Search ' + (w.length > 1 ? w.slice(0, -1).join(', ') + ' and ' + w[w.length - 1] : w[0]) : 'Search'; };
  K.canProducts = function () { return K.can('settings') || (K.can('stock') && (K.can('studio') || K.can('purchases'))); };
  K.canBill = function () { return K.can('sales') && (K.can('crm') || K.can('payments') || K.can('reports') || K.can('settings')); };
  K.confirmed = function (r) { return !r || r.source !== 'web' || +r.paid > 0 || ['to_pack', 'packed', 'dispatched', 'delivered'].indexOf(r.fulfil) > -1; };
  K.withBanks = function (o) { return o.concat(((window.DESK && DESK.banks) || []).map(function (b) { return [b[0], b[1]]; })); };
  K.open = function (path) { window.open(K.url(path), '_blank', 'noopener'); };

  /* ---------------- toast ---------------- */
  var tt;
  K.toast = function (m, err) {
    var t = $('.toast'); if (!t) return;
    t.textContent = m; t.classList.toggle('err', !!err); t.classList.add('on');
    clearTimeout(tt); tt = setTimeout(function () { t.classList.remove('on'); }, err ? 5000 : 3200);
  };

  /* ---------------- drawer + modal ---------------- */
  K.drawer = function (title, body, foot, wide) {
    var d = $('.drawer');
    d.style.width = wide ? 'min(900px,100%)' : '';
    d.innerHTML = '<div class="dh"><h2>' + title + '</h2><button class="btn ghost" data-x aria-label="Close">' + K.ic(K.ICONS.x) + '</button></div><div class="db">' + body + '</div>' + (foot ? '<div class="df">' + foot + '</div>' : '');
    d.classList.add('on'); $('.scrim').classList.add('on');
    return d;
  };
  K.closeDrawer = function () { var d = $('.drawer'), sc = $('.scrim'); if (d) d.classList.remove('on'); if (sc) sc.classList.remove('on'); K._openDocId = 0; if (K.dirty) { K.dirty = false; if (K.route) K.route(); } };
  K._mstack = [];
  K.modal = function (title, body, foot, wide) {
    var m = $('.modal');
    if (m.classList.contains('on') && m.firstChild) { K._mstack.push(m.removeChild(m.firstChild)); } // a picker or confirm opened over a form keeps the form underneath
    m.innerHTML = '<div class="mbox' + (wide ? ' wide' : '') + '" role="dialog" aria-modal="true"><div class="dh"><h2>' + title + '</h2><button class="btn ghost" data-mx aria-label="Close">' + K.ic(K.ICONS.x) + '</button></div><div class="db">' + body + '</div>' + (foot ? '<div class="df">' + foot + '</div>' : '') + '</div>';
    m.classList.add('on');
    var box = m.firstChild;
    box.addEventListener('input', function (e) { if (e.target.matches('input,textarea,select') && e.target.type !== 'search') box.dataset.dirty = '1'; });
    var f = m.querySelector('input:not([type=hidden]),select,textarea'); if (f) setTimeout(function () { f.focus(); }, 30);
    return box;
  };
  /* Escape or a click outside: ask before throwing away typed work */
  K.softCloseModal = function () {
    var box = $('.modal').firstChild;
    if (box && box.dataset && box.dataset.dirty) { K.confirm('Close without saving? What you typed will be lost.', 'Close without saving', true).then(function (y) { if (y) K.closeModal(); }); return; }
    K.closeModal();
  };
  K.closeModal = function () {
    var m = $('.modal');
    if (K._mstack.length) { m.innerHTML = ''; m.appendChild(K._mstack.pop()); return; }
    m.classList.remove('on'); m.innerHTML = '';
  };
  K.confirm = function (text, ok, danger) {
    return new Promise(function (res) {
      var m = K.modal('Please confirm', '<p>' + text + '</p>', '<button class="btn" data-no>Go back</button><button class="btn ' + (danger ? 'danger' : 'pri') + '" data-yes>' + (ok || 'Yes') + '</button>');
      $('[data-no]', m).onclick = function () { K.closeModal(); res(false); };
      $('[data-yes]', m).onclick = function () { K.closeModal(); res(true); };
    });
  };
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-x]') || e.target.matches('.scrim')) K.closeDrawer();
    if (e.target.closest('[data-mx]')) K.closeModal(); else if (e.target.matches('.modal')) K.softCloseModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if ($('.modal').classList.contains('on')) K.softCloseModal(); else K.closeDrawer(); var g = $('.gres'); if (g) g.hidden = true; }
  });

  /* ---------------- forms ---------------- */
  K.field = function (label, name, val, type, opts) {
    opts = opts || {};
    var id = 'f_' + name.replace(/\W/g, '_') + '_' + Math.random().toString(36).slice(2, 6), v = val == null ? '' : val, inp;
    if (type === 'select') inp = '<select id="' + id + '" name="' + name + '">' + (opts.options || []).map(function (o) { var k = Array.isArray(o) ? o[0] : o, l = Array.isArray(o) ? o[1] : o; return '<option value="' + esc(k) + '"' + (String(k) === String(v) ? ' selected' : '') + '>' + esc(l) + '</option>'; }).join('') + '</select>';
    else if (type === 'textarea') inp = '<textarea id="' + id + '" name="' + name + '"' + (opts.ph ? ' placeholder="' + esc(opts.ph) + '"' : '') + '>' + esc(v) + '</textarea>';
    else if (type === 'toggle') return '<label class="tog"><span>' + esc(label) + (opts.hint ? '<br><small class="tiny">' + esc(opts.hint) + '</small>' : '') + '</span><input type="checkbox" class="sw" name="' + name + '"' + (+v ? ' checked' : '') + '></label>';
    else inp = '<input id="' + id + '" name="' + name + '" type="' + (type || 'text') + '" value="' + esc(v) + '"' + (opts.ph ? ' placeholder="' + esc(opts.ph) + '"' : '') + (opts.req ? ' required' : '') + (opts.step ? ' step="' + opts.step + '"' : '') + (opts.list ? ' list="' + opts.list + '"' : '') + (opts.min != null ? ' min="' + opts.min + '"' : '') + '>';
    return '<div class="f"><label for="' + id + '">' + esc(label) + '</label>' + inp + (opts.hint ? '<span class="hint">' + esc(opts.hint) + '</span>' : '') + '</div>';
  };
  /* Change your own password without leaving the Desk. */
  K.myPassword = function () {
    var m = K.modal('Change my password', K.field('Current password', 'current', '', 'password') + K.field('New password', 'password', '', 'password', { hint: 'At least 8 characters' }) + K.field('Type the new password again', 'again', '', 'password'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Save password</button>');
    $('[data-go]', m).onclick = function () {
      var f = K.read(m);
      if (!f.current) { K.toast('Enter your current password.', true); return; }
      if ((f.password || '').length < 8) { K.toast('Use at least 8 characters for the new password.', true); return; }
      if (f.password !== f.again) { K.toast('The two new passwords are not the same.', true); return; }
      K.api('me/password', { body: { current: f.current, password: f.password } }).then(function (r) { if (r.nonce) D.nonce = r.nonce; K.closeModal(); K.toast(r.message); }).catch(K.fail);
    };
  };
  K.read = function (root) {
    var o = {};
    $$('input[name],select[name],textarea[name]', root).forEach(function (i) {
      if (i.type === 'checkbox') o[i.name] = i.checked ? 1 : 0;
      else if (i.type === 'radio') { if (i.checked) o[i.name] = i.value; }
      else if (i.type === 'file') return;
      else o[i.name] = i.value;
    });
    return o;
  };
  K.badge = function (s, label) {
    var L = { paid: 'Paid', unpaid: 'Unpaid', partial: 'Part paid', overdue: 'Overdue', void: 'Cancelled', draft: 'Draft', sent: 'Sent', accepted: 'Accepted', declined: 'Declined', expired: 'Expired', issued: 'Issued', matched: 'Matched', unmatched: 'Unmatched', pending: 'Pending', signed: 'Signed', ordered: 'Ordered', received: 'Received', awaiting_payment: 'Awaiting payment', to_pack: 'To pack', packed: 'Packed', dispatched: 'Dispatched', delivered: 'Delivered' };
    return '<span class="st ' + esc(String(s).toLowerCase().replace(/\s+/g, '_')) + '">' + esc(label || L[s] || s) + '</span>';
  };

  /* table with click-to-sort headers */
  K.table = function (cols, rows, opt) {
    opt = opt || {};
    cols = cols.filter(Boolean);
    if (!rows.length) return '<div class="empty"><b>' + esc(opt.empty || 'Nothing here yet') + '</b>' + esc(opt.emptySub || '') + '</div>';
    var key = opt.sortKey, dir = opt.sortDir || 1;
    if (key) rows = rows.slice().sort(function (a, b) { var x = a[key], y = b[key]; if (!isNaN(+x) && !isNaN(+y) && x !== '' && y !== '') return (x - y) * dir; return String(x || '').localeCompare(String(y || '')) * dir; });
    return '<div class="scroll"><table class="' + (opt.stack === false ? '' : 'stack') + '"><thead><tr>' + cols.map(function (c) { return '<th' + (c.n ? ' class="n"' : '') + (c.k ? ' data-sort="' + c.k + '"' : '') + '>' + esc(c.label) + (key === c.k ? (dir > 0 ? ' ↑' : ' ↓') : '') + '</th>'; }).join('') + '</tr></thead><tbody>' +
      rows.map(function (r) { return '<tr' + (opt.click ? ' class="click" data-row="' + esc(r[opt.id || 'id']) + '"' : '') + '>' + cols.map(function (c) { var v = c.f ? c.f(r) : esc(r[c.k]); var plain = String(v == null ? '' : v).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim(); var cls = (c.n ? 'n' : '') + (plain.length && plain.length <= 18 && !/<br|<div|<ul/.test(String(v)) ? ' nw' : ''); return '<td' + (cls.trim() ? ' class="' + cls.trim() + '"' : '') + ' data-l="' + esc(c.label) + '">' + (v == null ? '' : v) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>';
  };
  K.sortable = function (host, state, redraw) {
    $$('th[data-sort]', host).forEach(function (th) {
      th.onclick = function () { var k = th.dataset.sort; if (state.sortKey === k) state.sortDir = -(state.sortDir || 1); else { state.sortKey = k; state.sortDir = 1; } redraw(); };
    });
  };

  /* ---------------- media ---------------- */
  K.upload = function (file) {
    return fetch(D.wpapi + 'media', { method: 'POST', headers: { 'X-WP-Nonce': D.nonce, 'Content-Disposition': 'attachment; filename="' + encodeURIComponent(file.name).replace(/%20/g, ' ') + '"', 'Content-Type': file.type || 'application/octet-stream' }, body: file, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Upload failed'); return j.source_url; }); });
  };
  K.pickImage = function () {
    return new Promise(function (res) {
      var m = K.modal('Choose a photo', '<div class="toolbar"><label class="btn sun">' + K.ic(K.ICONS.plus) + 'Upload new<input type="file" accept="image/*" hidden data-up multiple></label><span class="tiny">JPG or PNG. Wide photos work best for banners.</span></div><div class="medgrid" data-g><div class="empty">Loading your photos…</div></div>', '', true);
      var g = $('[data-g]', m);
      fetch(D.wpapi + 'media?per_page=60&media_type=image&_fields=id,source_url,media_details', { headers: { 'X-WP-Nonce': D.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (items) {
        g.innerHTML = (items || []).length ? items.map(function (i) { var th = i.media_details && i.media_details.sizes && (i.media_details.sizes.medium || i.media_details.sizes.thumbnail); return '<button data-u="' + esc(i.source_url) + '"><img src="' + esc(th ? th.source_url : i.source_url) + '" alt="" loading="lazy"></button>'; }).join('') : '<div class="empty"><b>No photos yet</b>Upload one to get started.</div>';
      }).catch(function () { g.innerHTML = '<div class="empty">Could not load photos.</div>'; });
      g.addEventListener('click', function (e) { var b = e.target.closest('[data-u]'); if (b) { K.closeModal(); res(b.dataset.u); } });
      $('[data-up]', m).onchange = function (e) {
        var files = Array.prototype.slice.call(e.target.files); if (!files.length) return;
        K.toast('Uploading…');
        Promise.all(files.map(K.upload)).then(function (urls) { K.closeModal(); res(urls.length > 1 ? urls : urls[0]); }).catch(K.fail);
      };
    });
  };

  /* ---------------- chart ---------------- */
  K.barChart = function (el, data, keys, colors) {
    var draw = function () {
      var W = el.clientWidth, H = el.clientHeight, ml = 48, mb = 26, mt = 10, n = data.length;
      if (!W || !n) return;
      var max = Math.max(1, Math.max.apply(null, data.map(function (d) { return Math.max.apply(null, keys.map(function (k) { return +d[k] || 0; })); })));
      var step = Math.pow(10, Math.floor(Math.log10(max))), top = Math.ceil(max / step) * step; if (top / step < 3) { step /= 2; top = Math.ceil(max / step) * step; }
      var bw = (W - ml) / n, y = function (v) { return mt + (H - mt - mb) * (1 - v / top); }, g = '';
      for (var v = 0; v <= top + 1; v += step) g += '<line x1="' + ml + '" x2="' + W + '" y1="' + y(v) + '" y2="' + y(v) + '" stroke="#e3e8f1"/><text x="' + (ml - 6) + '" y="' + (y(v) + 4) + '" text-anchor="end">' + (v >= 1e6 ? (v / 1e6).toFixed(v % 1e6 ? 1 : 0) + 'M' : v >= 1e3 ? Math.round(v / 1e3) + 'k' : v) + '</text>';
      data.forEach(function (d, i) {
        var w = Math.max(3, bw * 0.7 / keys.length);
        keys.forEach(function (k, j) { var val = +d[k] || 0; g += '<rect x="' + (ml + i * bw + bw * 0.15 + j * (w + 2)) + '" y="' + y(val) + '" width="' + w + '" height="' + Math.max(0, H - mb - y(val)) + '" rx="3" fill="' + colors[j] + '"><title>' + esc(d.label) + ': ' + K.money(val) + '</title></rect>'; });
        if (W >= 560 || i % 2 === 0) g += '<text x="' + (ml + i * bw + bw / 2) + '" y="' + (H - 7) + '" text-anchor="middle">' + esc(d.label) + '</text>';
      });
      el.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '">' + g + '</svg>';
    };
    draw();
    if (window.ResizeObserver) { var ro = new ResizeObserver(draw); ro.observe(el); }
  };
  K.bars = function (rows) {
    var max = Math.max.apply(null, rows.map(function (r) { return r[1]; }).concat([1]));
    return '<div class="bars">' + rows.map(function (r) { return '<div><span>' + esc(r[0]) + '</span><span class="b" style="width:' + (r[1] / max * 100) + '%;background:' + (r[2] || 'var(--brand)') + '"></span><span class="n">' + K.money(r[1]) + '</span></div>'; }).join('') + '</div>';
  };

  /* ---------------- shell + router ---------------- */
  K.V = {};
  K.can = function (m) { return (K.me.modules || []).some(function (x) { return x.id === m; }); };
  K.badges = {};
  function shell() {
    var groups = {}, order = [];
    K.me.modules.forEach(function (m) { if (!groups[m.group]) { groups[m.group] = []; order.push(m.group); } groups[m.group].push(m); });
    app.innerHTML = '<aside class="side" data-side><a class="brand" href="#home"><img src="' + esc(D.logo) + '" alt=""><span><b>Dynamic Desk</b><small>' + esc(K.me.settings.business) + '</small></span></a>' +
      order.map(function (g) { return '<h4>' + esc(g) + '</h4>' + groups[g].map(function (m) { return '<a href="#' + m.id + '" data-nav="' + m.id + '">' + K.ic(K.ICONS[m.id] || K.ICONS.home) + esc(m.label) + '<em data-badge="' + m.id + '" hidden></em></a>'; }).join(''); }).join('') +
      '<div class="foot">Signed in as ' + esc(K.me.role) + '<br><a href="' + esc(D.site) + '" target="_blank" rel="noopener">View website</a> · <a href="' + esc(D.logout) + '">Sign out</a></div></aside>' +
      '<main><div class="top"><button class="btn ghost mbtn" data-menu aria-label="Menu">' + K.ic(K.ICONS.menu) + '</button><button class="btn sun sm" data-install hidden>' + K.ic(K.ICONS.dl || K.ICONS.plus) + 'Install app</button><a class="mbrand" href="#home" aria-label="Dashboard"><img src="' + esc(D.logo) + '" alt="' + esc(K.me.settings.business || 'Home') + '"></a><button class="btn ghost msearch" data-msearch aria-label="Search">' + K.ic(K.ICONS.search) + '</button><div class="gsearch">' + K.ic(K.ICONS.search) + '<input type="search" placeholder="' + esc(K.searchHint()) + '" aria-label="Search" data-gs><div class="gres" hidden data-gr></div></div>' +
      '<div class="tright"><button class="btn ghost themebtn" data-themebtn aria-label="Light or dark mode" title="Light or dark mode"><svg class="sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 13A9 9 0 1 1 11 3a7 7 0 0 0 10 10z"/></svg></button><a class="btn sitebtn" href="' + esc(D.site) + '" target="_blank" rel="noopener" aria-label="Open the website">' + K.ic(K.ICONS.ext) + '<span>Website</span></a><button class="me" data-me><span class="av">' + esc((K.me.name || '?').split(' ').map(function (w) { return w[0]; }).join('').slice(0, 2).toUpperCase()) + '</span><span><b>' + esc(K.me.first) + '</b><small>' + esc(K.me.role) + '</small></span></button></div></div><div id="view"></div></main>' +
      '<div class="scrim"></div><aside class="drawer" role="dialog" aria-modal="true"></aside><div class="modal"></div><div class="toast" role="status"></div>';
    var sideScrim = document.createElement('div'); sideScrim.className = 'sidescrim'; document.body.appendChild(sideScrim);
    var setSide = function (on) { $('[data-side]').classList.toggle('on', on); sideScrim.classList.toggle('on', on); document.body.classList.toggle('side-open', on); };
    K.closeSide = function () { setSide(false); };
    $('[data-menu]').onclick = function (e) { e.stopPropagation(); setSide(!$('[data-side]').classList.contains('on')); };
    sideScrim.addEventListener('click', function () { setSide(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && $('[data-side]').classList.contains('on')) setSide(false); });
    $('[data-side]').addEventListener('click', function (e) { if (e.target.closest('a[href]')) setSide(false); });
    $('[data-themebtn]').onclick = function () { var d = document.documentElement, dk = d.getAttribute('data-theme') !== 'dark'; if (dk) d.setAttribute('data-theme', 'dark'); else d.removeAttribute('data-theme'); try { localStorage.setItem('soko_desk_theme', dk ? 'dark' : 'light'); } catch (e) {} };
    $('[data-me]').onclick = function (e) {
      e.stopPropagation();
      var b = $('[data-me]'), p = $('.menu-pop', b);
      if (p) { p.remove(); return; }
      b.insertAdjacentHTML('beforeend', '<div class="menu-pop"><a href="' + esc(D.site) + '" target="_blank" rel="noopener">View website</a>' + (K.me.admin ? '<a href="' + esc(D.site) + 'wp-admin/">WordPress admin</a>' : '') + '<button data-pw>Change my password</button><button data-thm class="mthm">Light or dark mode</button><a href="' + esc(D.logout) + '">Sign out</a></div>');
      $('[data-pw]', b).onclick = K.myPassword;
      $('[data-thm]', b).onclick = function (ev) { ev.stopPropagation(); var pp = $('.menu-pop'); if (pp) pp.remove(); $('[data-themebtn]').click(); };
      $('[data-pw]', b).onclick = function (ev) { ev.stopPropagation(); var pp = $('.menu-pop'); if (pp) pp.remove(); K.myPassword(); };
    };
    document.addEventListener('click', function (e) { if (!e.target.closest('[data-me]')) { var p = $('.menu-pop'); if (p) p.remove(); } if (!e.target.closest('.gsearch')) { var g = $('[data-gr]'); if (g) g.hidden = true; } });
    var gs = $('[data-gs]'), gr = $('[data-gr]'), gT;
    var msb = $('[data-msearch]'); if (msb) msb.onclick = function () { var box = gs.closest('.gsearch'); box.classList.toggle('mopen'); if (box.classList.contains('mopen')) gs.focus(); else { gr.hidden = true; } };
    document.addEventListener('click', function (e) { var pq = e.target.closest('.gres a[data-pq]'); if (pq && K.S && K.S.pay) { K.S.pay.f = ''; K.S.pay.q = pq.dataset.pq; if (location.hash === '#payments') { e.preventDefault(); K.route(); } } });
    document.addEventListener('click', function (e) { if (!e.target.closest('.gres') || !e.target.closest('a')) return; var box = gs.closest('.gsearch'); box.classList.remove('mopen'); });
    gs.addEventListener('input', function () {
      clearTimeout(gT); var q = gs.value.trim();
      if (q.length < 2) { gr.hidden = true; return; }
      gT = setTimeout(function () {
        K.api('search?q=' + encodeURIComponent(q)).then(function (r) {
          gr.innerHTML = r.length ? r.map(function (x) { var href = x.kind === 'product' ? '#stock/' + x.id : x.kind === 'customer' ? '#crm/c' + x.id : x.kind === 'lead' ? '#crm/l' + x.id : x.kind === 'payment' ? '#payments" data-pq="' + esc(String(x.title).split(' · ')[0]) : '#sales/' + x.id; return '<a href="' + href + '"><span class="k">' + esc(x.kind) + '</span><span style="flex:1">' + esc(x.title) + '</span><small>' + esc(x.sub) + '</small></a>'; }).join('') : '<div class="empty" style="padding:14px">No matches</div>';
          gr.hidden = false;
        }).catch(function () {});
      }, 220);
    });
    gr.addEventListener('click', function () { gr.hidden = true; gs.value = ''; });
  }
  K.setBadge = function (m, n) { var b = $('[data-badge="' + m + '"]'); if (b) { b.textContent = n; b.hidden = !n; } };
  K.refreshBadges = function () {
    if (!K.can('home')) return;
    K.api('dashboard').then(function (d) {
      K.setBadge('payments', (d.unmatched || []).length);
      K.setBadge('sales', (d.overdue || []).length + (d.to_pack || 0));
      K.setBadge('crm', d.leads_new || 0);
      K.setBadge('compliance', (d.etims && d.etims.pending) || 0);
    }).catch(function () {});
  };
  K.route = function () {
    var h = location.hash.replace(/^#/, ''), parts = h.split('/'), m = parts[0] || '', arg = parts.slice(1).join('/');
    if (!K.can(m)) m = K.me.modules.length ? K.me.modules[0].id : '';
    // Leaving Site Studio with unpublished changes: ask first, so nothing is lost by accident.
    if (K.mod === 'studio' && m !== 'studio' && K.studioDirty && K.studioDirty()) {
      var target = location.hash;
      history.replaceState(null, '', '#studio');
      K.confirm('You have changes in Site Studio that are not published yet. Leave and lose them?', 'Leave without publishing', true).then(function (y) { if (y) { K.studioDirty(false); location.hash = target; } });
      return;
    }
    $$('[data-nav]').forEach(function (a) { a.classList.toggle('on', a.dataset.nav === m); });
    if (K.closeSide) K.closeSide(); else $('[data-side]').classList.remove('on');
    K.dirty = false; K.closeDrawer();
    var old = $('#view'), v = old.cloneNode(false);
    old.parentNode.replaceChild(v, old); // late replies from the previous screen land in the detached copy, never on this one
    if (!m || !K.V[m]) { v.innerHTML = '<div class="empty"><b>No access</b>Ask the owner to give your account access to a module.</div>'; return; }
    K.mod = m;
    v.innerHTML = '<div class="boot" style="height:50vh"><div class="spin"></div></div>';
    try { K.V[m](v, arg); } catch (e) { v.innerHTML = '<div class="empty"><b>This screen hit a problem</b>' + esc(e.message) + '</div>'; if (window.console) console.error(e); }
    window.scrollTo(0, 0);
  };
  document.addEventListener('click', function (e) { var jd = e.target.closest && e.target.closest('[data-jdoc]'); if (jd && K.openDoc) { e.preventDefault(); if (K.closeModal) K.closeModal(); K.openDoc(+jd.dataset.jdoc); } });
  /* A link to a screen this person cannot open explains why, instead of landing somewhere else. */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href^="#"]');
    if (!a || !K.me) return;
    var mod = a.getAttribute('href').slice(1).split('/')[0];
    if (mod && mod !== 'home' && K.V && K.V[mod] && !K.can(mod)) { e.preventDefault(); K.toast('That screen is for the ' + (mod === 'settings' ? 'owner' : 'team that looks after ' + mod) + '. Ask the owner if you need it.', true); }
  }, true);
  K.go = function (h) { if (location.hash === '#' + h) K.route(); else location.hash = h; };
  K.head = function (title, sub, acts) { return '<div class="head"><div><h1>' + title + '</h1>' + (sub ? '<p>' + sub + '</p>' : '') + '</div><div class="acts">' + (acts || '') + '</div></div>'; };
  K.tabs = function (key, list, on) { return '<div class="tabs" data-tabs="' + key + '">' + list.map(function (t) { return '<button data-t="' + t[0] + '" class="' + (on === t[0] ? 'on' : '') + '">' + esc(t[1]) + (t[2] ? '<em>' + t[2] + '</em>' : '') + '</button>'; }).join('') + '</div>'; };

  var app = document.getElementById('app');
  K.boot = function () {
    K.api('me').then(function (me) {
      K.me = me; K.cats = me.cats || [];
      shell();
      window.addEventListener('hashchange', K.route);
      K.route();
      K.refreshBadges();
      setInterval(K.refreshBadges, 60000);
    }).catch(function (e) {
      if (e.status === 401 || e.status === 403) { location.href = D.site + 'desk/login/'; return; }
      app.innerHTML = '<div class="boot"><b>Dynamic Desk could not start</b><span>' + esc(e.message) + '</span><a class="btn" href="">Try again</a></div>';
    });
  };
})();

/* Installable app: register the service worker and offer "Install app" where the browser supports it */
(function () {
  if (!('serviceWorker' in navigator)) return;
  var base = (window.DESK && DESK.site ? DESK.site : '/') + 'desk/';
  window.addEventListener('load', function () { navigator.serviceWorker.register(base + 'sw.js', { scope: base }).catch(function () {}); });
  var deferred = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault(); deferred = e;
    var b = document.querySelector('[data-install]'); if (!b) return;
    b.hidden = false;
    b.onclick = function () { b.hidden = true; deferred.prompt(); deferred.userChoice.then(function () { deferred = null; }); };
  });
  window.addEventListener('appinstalled', function () { var b = document.querySelector('[data-install]'); if (b) b.hidden = true; if (window.K && K.toast) K.toast('Dynamic Desk is installed. Open it from your apps or home screen.'); });
})();

/* Every card that summarises something opens the screen behind it */
(function () {
  function follow(el) {
    var set = el.getAttribute('data-set');
    if (set && window.K && K.S) { try { var o = JSON.parse(set); Object.keys(o).forEach(function (k) { if (K.S[k]) Object.assign(K.S[k], o[k]); }); } catch (e) {} }
    if (el.dataset.scroll) {
      var hs = [].slice.call(document.querySelectorAll('#view h3')).filter(function (h) { return h.textContent.trim() === el.dataset.scroll; })[0];
      if (hs) hs.closest('.box').scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    var h = (el.dataset.go || '').replace(/^#/, '');
    if (h && window.K && K.go) K.go(h);
  }
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-go],[data-scroll]');
    if (!el || e.target.closest('a[href],button,input,select,textarea,label') && !e.target.closest('a[href],button,input,select,textarea,label').matches('[data-go],[data-scroll]')) return;
    if (el.closest('.drawer,.modal')) return;
    e.preventDefault(); e.stopPropagation(); follow(el);
  }, true);
  document.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('.kpi.link,.box.link')) { e.preventDefault(); follow(e.target); } });
  new MutationObserver(function () { document.querySelectorAll('.kpi.link:not([tabindex]),.box.link:not([tabindex])').forEach(function (x) { x.tabIndex = 0; x.setAttribute('role', 'link'); }); }).observe(document.documentElement, { childList: true, subtree: true });
})();

