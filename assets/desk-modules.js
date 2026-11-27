/* Dynamic Desk: modules */
(function () {
  'use strict';
  var K = window.K, $ = K.$, $$ = K.$$, esc = K.esc, money = K.money, I = K.ICONS, ic = K.ic, D = window.DESK;
  var greet = function () { var h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; };
  var opts = function (list) { return list.map(function (x) { return [x, x]; }); };
  var period = function (p) {
    var t = K.today(), y = +t.slice(0, 4), m = +t.slice(5, 7), pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var last = function (yy, mm) { return K.iso(new Date(yy, mm, 0)); };
    if (p === 'last') { var lm = m === 1 ? 12 : m - 1, ly = m === 1 ? y - 1 : y; return [ly + '-' + pad(lm) + '-01', last(ly, lm)]; }
    if (p === 'quarter') { var q = Math.floor((m - 1) / 3) * 3 + 1; return [y + '-' + pad(q) + '-01', last(y, q + 2)]; }
    if (p === 'year') return [y + '-01-01', y + '-12-31'];
    if (p === '30') return [K.add(t, -30), t];
    return [y + '-' + pad(m) + '-01', last(y, m)];
  };
  K.period = period;

  /* ================================================================ DASHBOARD */
  K.V.home = function (v) {
    K.api('dashboard').then(function (d) {
      var ch = d.sales_change, att = [];
      (d.overdue || []).forEach(function (o) { att.push(['var(--bad)', esc(o.number) + ' overdue · ' + esc(o.name), money(o.bal), '#sales/' + o.id]); });
      (d.unmatched || []).forEach(function (u) { att.push(['var(--warn)', 'Unmatched M-Pesa ' + esc(u.code) + ' from ' + esc(u.payer), money(u.amount), '#payments']); });
      if (d.to_pack) att.push(['var(--warn)', d.to_pack + ' paid website order' + (d.to_pack > 1 ? 's' : '') + ' to pack', 'Open', '#sales/web']);
      (d.low || []).forEach(function (p) { att.push(['var(--warn)', 'Low stock: ' + esc(p.name), (+p.stock) + ' left', '#stock/' + p.id]); });
      (d.licences || []).forEach(function (l) { att.push(['var(--brand)', esc(l.name) + ' renews ' + K.ds(l.expires), 'Renew', '#compliance']); });
      if (d.etims && d.etims.pending) att.push(['var(--brand)', d.etims.pending + ' invoice' + (d.etims.pending > 1 ? 's' : '') + ' waiting for eTIMS', 'Sign', '#compliance']);
      if (d.leads_new) att.push(['var(--ok)', d.leads_new + ' new lead' + (d.leads_new > 1 ? 's' : '') + ' to call', 'Call', '#crm']);
      var finding = 'Sales for ' + esc(d.month) + ' are ' + money(d.sales) + (ch != null ? ', ' + Math.abs(ch) + '% ' + (ch >= 0 ? 'ahead of' : 'behind') + ' last month' : '') + '.' + ((d.overdue || []).length ? ' ' + d.overdue.length + ' invoice' + (d.overdue.length > 1 ? 's need' : ' needs') + ' chasing.' : ' Nothing is overdue.');
      v.innerHTML = K.head(greet() + (/admin|^[a-z0-9_.-]+$/i.test(K.me.first || '') && !/ /.test(K.me.first) && /admin/i.test(K.me.first) ? '' : ', ' + esc(K.me.first)), finding, (K.can('reports') ? '<a class="btn" href="#reports">Reports</a>' : '') + (K.can('sales') ? '<button class="btn" data-nq>New quotation</button><button class="btn sun" data-ni>New invoice</button>' : '')) +
        '<div class="kpis"><div class="kpi"><small>Sales this month</small><b>' + money(d.sales) + '</b>' + (ch != null ? '<i class="' + (ch >= 0 ? 'up' : 'down') + '">' + (ch >= 0 ? '▲ ' : '▼ ') + Math.abs(ch) + '% vs last month</i>' : '<i class="mut">First month on the Desk</i>') + '</div>' +
        '<div class="kpi"><small>Collected this month</small><b>' + money(d.collected) + '</b><i class="up">' + d.collected_n + ' payment' + (d.collected_n === 1 ? '' : 's') + ' matched</i></div>' +
        '<div class="kpi"><small>Owed by customers</small><b>' + money(d.owed) + '</b><i class="' + ((d.overdue || []).length ? 'down' : 'mut') + '">' + (d.overdue || []).length + ' overdue</i></div>' +
        '<div class="kpi"><small>Gross margin</small><b>' + d.margin + '%</b><i class="' + (d.margin >= d.margin_prev ? 'up' : 'down') + '">' + (d.margin === d.margin_prev ? 'Same as last month' : (d.margin > d.margin_prev ? '▲ ' : '▼ ') + Math.abs(d.margin - d.margin_prev) + ' points vs last month') + '</i></div></div>' +
        '<div class="cols"><div class="box"><h3>Cash in and out</h3><p class="sub">Last 12 weeks</p><div class="fig" data-cash></div><div class="legend"><span><i style="background:var(--brand)"></i>Money in</span><span><i style="background:var(--sun)"></i>Money out</span></div></div>' +
        '<div class="box"><h3>Needs attention</h3><p class="sub">' + (att.length ? 'Open an item to deal with it' : 'All clear') + '</p><ul class="list">' + (att.length ? att.slice(0, 9).map(function (a) { return '<li class="click" data-go="' + a[3] + '"><span class="pip" style="background:' + a[0] + '"></span><span>' + a[1] + '</span><b class="mono">' + a[2] + '</b></li>'; }).join('') : '<li><span class="pip" style="background:var(--ok)"></span><span>Nothing overdue, unmatched or low.</span><span></span></li>') + '</ul></div></div>' +
        '<div class="cols three"><div class="box"><h3>Sales by category</h3><p class="sub">This month</p>' + ((d.cats || []).length ? K.bars(d.cats.map(function (c) { return [c.label, c.value]; })) : '<div class="empty">No sales yet this month</div>') + '</div>' +
        '<div class="box"><h3>Today</h3><p class="sub">' + K.d(K.today()) + '</p><ul class="list">' + ((d.today || []).length ? d.today.map(function (e) { return '<li class="click" data-go="#schedule"><b class="mono">' + K.t(e.start) + '</b><span>' + esc(e.title) + '<small>' + esc([e.who, e.location].filter(Boolean).join(' · ')) + '</small></span><span class="st">' + esc(e.kind) + '</span></li>'; }).join('') : '<li><span></span><span class="mut">Nothing scheduled</span><span></span></li>') + '</ul></div>' +
        '<div class="box"><h3>Latest payments</h3><p class="sub">M-Pesa, bank and cash</p><ul class="list">' + ((d.recent || []).length ? d.recent.map(function (p) { return '<li class="click" data-go="#payments"><span class="pip" style="background:' + (p.status === 'matched' ? 'var(--ok)' : 'var(--warn)') + '"></span><span>' + esc(p.payer || p.code) + '<small>' + esc(p.code) + ' · ' + (p.number ? esc(p.number) : 'unmatched') + '</small></span><b class="mono">' + money(p.amount) + '</b></li>'; }).join('') : '<li><span></span><span class="mut">No payments yet</span><span></span></li>') + '</ul></div></div>';
      K.barChart($('[data-cash]', v), d.weeks, ['in', 'out'], ['var(--brand)', 'var(--sun)']);
      $$('[data-go]', v).forEach(function (el) { el.onclick = function () { location.hash = el.dataset.go; }; });
      var nq = $('[data-nq]', v), ni = $('[data-ni]', v);
      if (nq) nq.onclick = function () { K.editDoc('quote'); };
      if (ni) ni.onclick = function () { K.editDoc('invoice'); };
    }).catch(function (e) { v.innerHTML = '<div class="empty"><b>Could not load the dashboard</b>' + esc(e.message) + '</div>'; });
  };

  /* ================================================================ SALES */
  var salesState = { tab: 'invoice', state: '', q: '', sortKey: '', sortDir: 1 };
  K.V.sales = function (v, arg) {
    if (arg === 'web') salesState.tab = 'web';
    else if (arg && /^\d+$/.test(arg)) setTimeout(function () { K.openDoc(+arg); }, 50);
    var t = salesState.tab;
    var q = t === 'web' ? 'docs?type=invoice&source=web' : 'docs?type=' + t;
    v.innerHTML = K.head('Sales and invoices', 'Quotations become invoices in one click. Every invoice has its own M-Pesa account number, so payments match themselves.', '<button class="btn" data-nq>' + ic(I.plus) + 'New quotation</button><button class="btn sun" data-ni>' + ic(I.plus) + 'New invoice</button>') +
      K.tabs('sales', [['invoice', 'Invoices'], ['quote', 'Quotations'], ['receipt', 'Receipts'], ['web', 'Website orders']], t) + '<div data-list><div class="boot" style="height:30vh"><div class="spin"></div></div></div>';
    $('[data-nq]', v).onclick = function () { K.editDoc('quote'); };
    $('[data-ni]', v).onclick = function () { K.editDoc('invoice'); };
    $$('[data-tabs] button', v).forEach(function (b) { b.onclick = function () { salesState.tab = b.dataset.t; salesState.state = ''; if (location.hash === '#sales') K.V.sales(v); else location.hash = 'sales'; }; });
    K.api(q).then(function (rows) {
      var host = $('[data-list]', v);
      var states = t === 'invoice' ? [['', 'All'], ['unpaid', 'Unpaid'], ['partial', 'Part paid'], ['overdue', 'Overdue'], ['paid', 'Paid'], ['void', 'Cancelled']] : t === 'quote' ? [['', 'All'], ['draft', 'Draft'], ['sent', 'Sent'], ['accepted', 'Accepted'], ['expired', 'Expired'], ['declined', 'Declined']] : t === 'web' ? [['', 'All'], ['awaiting_payment', 'Awaiting payment'], ['to_pack', 'To pack'], ['packed', 'Packed'], ['dispatched', 'Dispatched'], ['delivered', 'Delivered']] : [];
      var draw = function () {
        var list = rows.filter(function (r) {
          var st = t === 'web' ? (r.fulfil || 'awaiting_payment') : r.state;
          return (!salesState.state || st === salesState.state) && (!salesState.q || (r.number + ' ' + (r.customer_name || '') + ' ' + (r.customer_phone || '')).toLowerCase().indexOf(salesState.q.toLowerCase()) > -1);
        });
        var total = list.reduce(function (a, r) { return a + r.total; }, 0), bal = list.reduce(function (a, r) { return a + (r.type === 'invoice' ? r.balance : 0); }, 0);
        var cols = [{ k: 'number', label: t === 'receipt' ? 'Receipt' : t === 'quote' ? 'Quotation' : 'Invoice', f: function (r) { return '<b class="mono">' + esc(r.number) + '</b>'; } }, { k: 'customer_name', label: 'Customer', f: function (r) { return esc(r.customer_name || '—') + '<div class="tiny">' + esc(K.phone(r.customer_phone)) + '</div>'; } }, { k: 'date', label: 'Date', f: function (r) { return K.ds(r.date); } }];
        if (t === 'invoice' || t === 'web') cols.push({ k: 'due', label: 'Due', f: function (r) { return K.ds(r.due); } });
        if (t === 'quote') cols.push({ k: 'due', label: 'Valid to', f: function (r) { return K.ds(r.due); } });
        cols.push({ k: 'total', label: 'Total', n: 1, f: function (r) { return money(r.total); } });
        if (t === 'invoice' || t === 'web') cols.push({ k: 'balance', label: 'Balance', n: 1, f: function (r) { return r.balance > 0 ? money(r.balance) : '<span class="mut">—</span>'; } });
        if (t === 'receipt') cols.push({ k: 'x', label: 'For invoice', f: function (r) { return esc(r.meta.invoice || ''); } }, { k: 'y', label: 'Paid by', f: function (r) { return esc((r.meta.method === 'mpesa' ? 'M-Pesa ' : (r.meta.method || '') + ' ') + (r.meta.code || '')); } });
        else cols.push({ k: 'state', label: 'Status', f: function (r) { return t === 'web' ? K.badge(r.fulfil || 'awaiting_payment') + ' ' + K.badge(r.state) : K.badge(r.state); } });
        if (t === 'invoice') cols.push({ k: 'etims_status', label: 'eTIMS', f: function (r) { return r.etims_status ? K.badge(r.etims_status) : ''; } });
        host.innerHTML = '<div class="box"><div class="toolbar"><input type="search" placeholder="Search number, customer or phone" value="' + esc(salesState.q) + '" data-q><div class="chips">' + states.map(function (s) { return '<button class="chip' + (salesState.state === s[0] ? ' on' : '') + '" data-st="' + s[0] + '">' + s[1] + '</button>'; }).join('') + '</div><span class="tiny" style="margin-left:auto">' + list.length + ' · ' + money(total) + (bal ? ' · ' + money(bal) + ' owed' : '') + '</span></div>' +
          K.table(cols, list, { click: 1, sortKey: salesState.sortKey, sortDir: salesState.sortDir, empty: t === 'web' ? 'No website orders yet' : 'Nothing here yet', emptySub: t === 'web' ? 'Orders placed on the website appear here, already linked to their M-Pesa payment.' : '' }) + '</div>';
        $('[data-q]', host).oninput = function (e) { salesState.q = e.target.value; var p = e.target.selectionStart; draw(); var n = $('[data-q]', host); n.focus(); n.setSelectionRange(p, p); };
        $$('[data-st]', host).forEach(function (b) { b.onclick = function () { salesState.state = b.dataset.st; draw(); }; });
        $$('[data-row]', host).forEach(function (tr) { tr.onclick = function () { K.openDoc(+tr.dataset.row); }; });
        K.sortable(host, salesState, draw);
      };
      draw();
    }).catch(K.fail);
  };

  /* -------- document drawer -------- */
  K.openDoc = function (id) {
    K.api('docs/' + id).then(function (d) {
      var c = d.customer || {}, isInv = d.type === 'invoice', isQ = d.type === 'quote', isR = d.type === 'receipt';
      var waText = encodeURIComponent('Hello ' + (c.name || '') + ', here is your ' + (isQ ? 'quotation' : isInv ? 'invoice' : 'receipt') + ' ' + d.number + ' from ' + K.me.settings.business + ' for ' + money(d.total) + '.' + (isInv && d.balance > 0 ? ' Balance ' + money(d.balance) + '. Pay by M-Pesa using account number ' + d.number + ' or here: ' + d.pay_url : ' View it here: ' + d.public_pdf));
      var wa = 'https://wa.me/' + String(c.phone || '').replace(/\D/g, '') + '?text=' + waText;
      var acts = [];
      acts.push('<button class="btn" data-a="pdf">' + ic(I.eye) + 'View PDF</button>');
      acts.push('<button class="btn" data-a="dl">' + ic(I.dl) + 'Download PDF</button>');
      if (!isR) acts.push('<button class="btn" data-a="email">' + ic(I.mail) + 'Email</button>');
      acts.push('<a class="btn" target="_blank" rel="noopener" href="' + wa + '">' + ic(I.wa) + 'WhatsApp</a>');
      if (isInv && d.balance > 0 && d.state !== 'void') { acts.push('<button class="btn sun" data-a="stk">' + ic(I.phone) + 'Request M-Pesa</button>'); acts.push('<button class="btn" data-a="pay">' + ic(I.cash) + 'Record payment</button>'); }
      if (isQ && d.status !== 'accepted') { acts.push('<button class="btn sun" data-a="convert">' + ic(I.check) + 'Convert to invoice</button>'); }
      if (!isR && !(isInv && d.paid > 0)) acts.push('<button class="btn" data-a="edit">' + ic(I.edit) + 'Edit</button>');
      if (!isR) acts.push('<button class="btn" data-a="dup">' + ic(I.copy) + 'Duplicate</button>');
      if (isInv && K.can('jobs')) acts.push('<button class="btn" data-a="job">' + ic(I.truck) + 'Book installation</button>');
      if (isQ && d.status !== 'accepted' && d.status !== 'declined') acts.push('<button class="btn" data-a="decline">Mark declined</button>');
      if (isInv && !d.paid && d.state !== 'void') acts.push('<button class="btn danger" data-a="void">' + ic(I.trash) + 'Cancel invoice</button>');
      var fulfil = '';
      if (isInv && (d.source === 'web' || d.fulfil)) {
        var fs = [['to_pack', 'To pack'], ['packed', 'Packed'], ['dispatched', 'Dispatched'], ['delivered', 'Delivered']];
        fulfil = '<div class="box"><h3>Delivery</h3><p class="sub">Customers see this on their order tracking page.</p><div class="fsteps">' + fs.map(function (f) { return '<button class="chip' + (d.fulfil === f[0] ? ' on' : '') + '" data-ff="' + f[0] + '">' + f[1] + '</button>'; }).join('') + '</div></div>';
      } else if (isInv && d.state === 'paid' && !(d.meta && d.meta.stock_done)) {
        fulfil = '<div class="notice info">Paid. <button class="btn sm" data-a="deliver">Mark delivered and take out of stock</button></div>';
      }
      var etims = isInv ? '<div class="box"><h3>KRA eTIMS</h3>' + (d.etims_status === 'signed' ? '<p>' + K.badge('signed') + ' Control unit invoice <b class="mono">' + esc(d.etims_ref) + '</b></p>' : '<p class="sub">Sign this invoice on eTIMS and record the control unit number here.</p><div class="toolbar"><input placeholder="eTIMS invoice number" data-et style="max-width:260px"><button class="btn" data-a="etims">Save</button></div>') + '</div>' : '';
      var pays = (d.payments || []).length ? '<div class="box"><h3>Payments</h3>' + K.table([{ k: 'received', label: 'Date', f: function (p) { return K.ds(p.received); } }, { k: 'code', label: 'Code', f: function (p) { return '<span class="mono">' + esc(p.code) + '</span>'; } }, { k: 'method', label: 'Method', f: function (p) { return p.method === 'mpesa' ? 'M-Pesa' : esc(p.method); } }, { k: 'receipt', label: 'Receipt' }, { k: 'amount', label: 'Amount', n: 1, f: function (p) { return money(p.amount); } }], d.payments, { stack: false }) + '</div>' : '';
      var links = [];
      if (d.parent) links.push('From <a href="#" data-open="' + d.parent.id + '">' + esc(d.parent.number) + '</a>');
      (d.children || []).forEach(function (ch) { links.push((ch.type === 'receipt' ? 'Receipt ' : ch.type === 'invoice' ? 'Invoice ' : '') + '<a href="#" data-open="' + ch.id + '">' + esc(ch.number) + '</a>'); });
      var stks = (d.stk || []).length ? '<p class="tiny">Last M-Pesa prompt: ' + esc(d.stk[0].status) + ' · ' + esc(d.stk[0].message) + '</p>' : '';
      var body = '<div class="docv"><div class="dvh"><div><b>' + esc(d.number) + '</b><div>' + K.badge(d.state) + (d.source === 'web' ? ' <span class="st">Website</span>' : '') + '</div></div><div style="text-align:right"><div class="tiny">' + (isQ ? 'Valid to ' + K.d(d.due) : isInv ? 'Due ' + K.d(d.due) : K.d(d.date)) + '</div><b>' + money(isInv ? d.balance : d.total) + '</b><div class="tiny">' + (isInv ? (d.balance < d.total ? 'still to pay · ' + money(d.total - d.balance) + ' paid of ' + money(d.total) : 'to pay') : 'total') + '</div></div></div>' +
        '<div style="padding:14px 16px"><dl class="kv"><dt>Customer</dt><dd><b>' + esc(c.name || '—') + '</b></dd><dt>Phone</dt><dd>' + esc(K.phone(c.phone)) + '</dd>' + (c.email ? '<dt>Email</dt><dd>' + esc(c.email) + '</dd>' : '') + (c.kra ? '<dt>KRA PIN</dt><dd>' + esc(c.kra) + '</dd>' : '') + '<dt>Date</dt><dd>' + K.d(d.date) + '</dd>' + (links.length ? '<dt>Linked</dt><dd>' + links.join(' · ') + '</dd>' : '') + '</dl></div>' +
        '<table><thead><tr><th>Item</th><th class="n">Qty</th><th class="n">Price</th><th class="n">Amount</th></tr></thead><tbody>' + d.lines.map(function (l) { return '<tr><td>' + esc(l.description) + '</td><td class="n">' + K.num(l.qty) + '</td><td class="n">' + money(l.price, 1) + '</td><td class="n">' + money(l.qty * l.price, 1) + '</td></tr>'; }).join('') + '</tbody></table>' +
        '<div class="tot">' + (d.vat ? '<div><span>Excl. VAT</span><span>' + money(d.subtotal) + '</span></div><div><span>VAT</span><span>' + money(d.vat) + '</span></div>' : '') + '<div class="g"><span>Total</span><span>' + money(d.total) + '</span></div>' + (isInv && d.paid ? '<div><span>Paid</span><span>' + money(d.paid) + '</span></div><div><b>Balance</b><b>' + money(d.balance) + '</b></div>' : '') + '</div>' + (d.notes ? '<p style="padding:0 16px 14px;white-space:pre-line" class="tiny">' + esc(d.notes) + '</p>' : '') + '</div>' +
        '<div class="actgrid">' + acts.join('') + '</div>' + stks + fulfil + pays + etims;
      var dr = K.drawer(esc(isQ ? 'Quotation' : isInv ? 'Invoice' : 'Receipt') + ' ' + esc(d.number), body, '', true);
      var act = function (a, extra) {
        return K.api('docs/' + d.id + '/action', { body: Object.assign({ action: a }, extra || {}) }).then(function (r) { if (r.message) K.toast(r.message); if (r.job_id) { K.openJob = r.job_id; if (K.closeDrawer) K.closeDrawer(); document.querySelectorAll('.drawer,.overlay,.scrim').forEach(function (x) { x.remove(); }); location.hash = '#jobs'; } return r; });
      };
      $$('[data-open]', dr).forEach(function (a) { a.onclick = function (e) { e.preventDefault(); K.openDoc(+a.dataset.open); }; });
      $$('[data-ff]', dr).forEach(function (b) { b.onclick = function () { (b.dataset.ff === 'delivered' ? act('deliver') : act('fulfil', { fulfil: b.dataset.ff })).then(function () { K.openDoc(d.id); K.refreshBadges(); }); }; });
      $$('[data-a]', dr).forEach(function (b) {
        b.onclick = function () {
          var a = b.dataset.a;
          if (a === 'pdf') return K.open('docs/' + d.id + '/pdf');
          if (a === 'dl') return K.open('docs/' + d.id + '/pdf?download=1');
          if (a === 'edit') return K.editDoc(d.type, d);
          if (a === 'dup') return act('duplicate').then(function (r) { K.openDoc(r.doc.id); }).catch(K.fail);
          if (a === 'convert') {
            var m = K.modal('Convert ' + esc(d.number) + ' to an invoice', '<p>This creates an invoice for ' + money(d.total) + ' and marks the quotation accepted.</p>' + K.field('Ask for a ' + K.me.settings.deposit_percent + '% deposit to start the work', 'deposit', 1, 'toggle', { hint: 'The invoice shows the deposit amount. Any payment is matched to it.' }), '<button class="btn" data-mx>Cancel</button><button class="btn sun" data-go>Create invoice</button>');
            $('[data-go]', m).onclick = function () { var dep = K.read(m).deposit; K.closeModal(); act('convert', { deposit: dep }).then(function (r) { K.openDoc(r.doc.id); }).catch(K.fail); };
            return;
          }
          if (a === 'email') {
            var m2 = K.modal('Email ' + esc(d.number), K.field('To', 'to', c.email || '', 'email', { ph: 'customer@example.com' }) + K.field('Message (optional)', 'message', '', 'textarea', { ph: 'Leave blank for the standard message with the PDF attached.' }), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Send</button>');
            $('[data-go]', m2).onclick = function () { var f = K.read(m2); K.closeModal(); act('email', f).catch(K.fail); };
            return;
          }
          if (a === 'stk') {
            var m3 = K.modal('Request payment by M-Pesa', (K.me.mpesa_ready ? '' : '<div class="notice">M-Pesa prompts are not switched on yet. Until then, the customer pays to your Paybill using ' + esc(d.number) + ' as the account number.</div>') + K.field('Customer M-Pesa number', 'phone', K.phone(c.phone), 'tel') + K.field('Amount', 'amount', Math.ceil(d.balance), 'number', { hint: 'You can ask for part of the balance, for example a deposit.' }), '<button class="btn" data-mx>Cancel</button><button class="btn sun" data-go>Send prompt</button>');
            $('[data-go]', m3).onclick = function () { var f = K.read(m3); K.closeModal(); act('stk', f).then(function (r) { K.toast(r.message, !r.ok); }).catch(K.fail); };
            return;
          }
          if (a === 'pay') return K.recordPayment(d);
          if (a === 'void') return K.confirm('Cancel ' + esc(d.number) + '? It stays on record as cancelled.', 'Cancel invoice', true).then(function (y) { if (y) act('void').then(function () { K.openDoc(d.id); K.route(); }).catch(K.fail); });
          if (a === 'decline') return act('status', { status: 'declined' }).then(function () { K.openDoc(d.id); }).catch(K.fail);
          if (a === 'deliver') return act('deliver').then(function () { K.openDoc(d.id); }).catch(K.fail);
          if (a === 'job') return act('job').then(function () { K.toast('Installation job created.'); }).catch(K.fail);
          if (a === 'etims') return act('etims', { ref: $('[data-et]', dr).value }).then(function () { K.openDoc(d.id); K.refreshBadges(); }).catch(K.fail);
        };
      });
    }).catch(K.fail);
  };

  K.recordPayment = function (doc) {
    var m = K.modal('Record a payment', (doc ? '<p class="tiny">Against ' + esc(doc.number) + ', balance ' + money(doc.balance) + '</p>' : '') +
      '<div class="grid2">' + K.field('Method', 'method', 'mpesa', 'select', { options: [['mpesa', 'M-Pesa'], ['bank', 'Bank transfer'], ['cash', 'Cash'], ['card', 'Card'], ['cheque', 'Cheque']] }) + K.field('Amount', 'amount', doc ? Math.ceil(doc.balance) : '', 'number') + '</div>' +
      '<div class="grid2">' + K.field('Transaction code or reference', 'code', '', 'text', { ph: 'e.g. TKR4H2LQ9P' }) + K.field('Date received', 'date', K.today(), 'date') + '</div>' + (doc ? '' : K.field('Paid by', 'payer', '', 'text') + K.field('Account reference given', 'account_ref', '', 'text', { hint: 'If it matches an invoice number, it is matched automatically.' })),
      '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Save payment</button>');
    $('[data-go]', m).onclick = function () {
      var f = K.read(m); if (doc) f.doc_id = doc.id;
      K.api('payments', { body: f }).then(function (r) { K.closeModal(); K.toast(r.message); if (doc) K.openDoc(doc.id); K.refreshBadges(); if (K.mod === 'payments') K.route(); }).catch(K.fail);
    };
  };

  /* -------- quotation / invoice editor -------- */
  var productsCache = null;
  K.products = function (force) { if (productsCache && !force) return Promise.resolve(productsCache); return K.api('products').then(function (p) { productsCache = p; return p; }); };
  K.editDoc = function (type, doc) {
    Promise.all([K.products(), K.api('customers')]).then(function (res) {
      var prods = res[0], custs = res[1];
      var lines = doc ? doc.lines.map(function (l) { return { product_id: l.product_id, description: l.description, qty: l.qty, price: l.price }; }) : [];
      var cust = doc && doc.customer ? doc.customer : null;
      var title = (doc ? 'Edit ' : 'New ') + (type === 'quote' ? 'quotation' : 'invoice') + (doc ? ' ' + esc(doc.number) : '');
      var body = '<div class="grid2"><div class="f custpick"><label>Customer</label><input placeholder="Search by name or phone, or type a new name" data-cs autocomplete="off" value="' + esc(cust ? cust.name : '') + '"><div class="dd" hidden data-cd></div></div><div class="f"><label>Phone</label><input data-cp inputmode="tel" value="' + esc(cust ? K.phone(cust.phone) : '') + '" placeholder="07XX XXX XXX"></div></div>' +
        '<div class="grid3">' + K.field('Email', 'email', cust ? cust.email : '', 'email') + K.field('KRA PIN', 'kra', cust ? cust.kra : '', 'text') + K.field(type === 'quote' ? 'Valid until' : 'Due date', 'due', doc ? doc.due : K.add(K.today(), +(type === 'quote' ? K.me.settings.quote_valid : K.me.settings.invoice_due)), 'date') + '</div>' +
        '<div class="scroll"><table class="lines"><thead><tr><th>Item</th><th class="n">Qty</th><th class="n">Unit price</th><th class="n">Amount</th><th></th></tr></thead><tbody data-ls></tbody></table></div>' +
        '<div class="pickrow"><div class="custpick"><input placeholder="Add a product: type to search" data-ps autocomplete="off"><div class="dd" hidden data-pd></div></div><button class="btn" data-addc>' + ic(I.plus) + 'Custom line</button><button class="btn" data-addi>' + ic(I.plus) + 'Installation</button></div>' +
        K.field('Notes on the ' + (type === 'quote' ? 'quotation' : 'invoice'), 'notes', doc ? doc.notes : '', 'textarea', { ph: 'Scope, delivery terms, warranty notes' }) +
        '<div class="totbar"><span class="tiny" data-vat></span><span>Total <b data-tot>' + money(0) + '</b></span></div>';
      var m = K.modal(title, body, '<button class="btn" data-mx>Cancel</button><button class="btn" data-save="0">Save</button><button class="btn sun" data-save="1">' + (doc ? 'Save' : 'Save and email') + '</button>', true);
      var ls = $('[data-ls]', m);
      var draw = function () {
        ls.innerHTML = lines.length ? lines.map(function (l, i) { return '<tr><td><input value="' + esc(l.description) + '" data-ld="' + i + '"></td><td class="q"><input type="number" min="0" step="any" value="' + l.qty + '" data-lq="' + i + '"></td><td class="p"><input type="number" min="0" step="any" value="' + l.price + '" data-lp="' + i + '"></td><td class="a" data-la="' + i + '">' + money(l.qty * l.price, 1) + '</td><td><button class="btn ghost sm" data-lx="' + i + '" aria-label="Remove">' + ic(I.x) + '</button></td></tr>'; }).join('') : '<tr><td colspan="5" class="tiny" style="padding:16px">Add products below. Prices fill in from your catalogue and can be changed.</td></tr>';
        sum();
      };
      var sum = function () {
        var t = lines.reduce(function (a, l) { return a + l.qty * l.price; }, 0), r = +K.me.settings.vat_rate;
        $('[data-tot]', m).textContent = money(t);
        $('[data-vat]', m).textContent = K.me.settings.vat_registered ? 'Includes VAT ' + money(t * r / (100 + r)) : '';
        lines.forEach(function (l, i) { var a = $('[data-la="' + i + '"]', m); if (a) a.textContent = money(l.qty * l.price, 1); });
      };
      ls.addEventListener('input', function (e) {
        var x = e.target, i;
        if ((i = x.dataset.ld) != null) lines[i].description = x.value;
        if ((i = x.dataset.lq) != null) lines[i].qty = +x.value || 0;
        if ((i = x.dataset.lp) != null) lines[i].price = +x.value || 0;
        sum();
      });
      ls.addEventListener('click', function (e) { var b = e.target.closest('[data-lx]'); if (b) { lines.splice(+b.dataset.lx, 1); draw(); } });
      var ps = $('[data-ps]', m), pd = $('[data-pd]', m);
      var pdraw = function () {
        var q = ps.value.toLowerCase(), list = prods.filter(function (p) { return !q || (p.name + ' ' + p.sku + ' ' + p.cat_name).toLowerCase().indexOf(q) > -1; }).slice(0, 12);
        pd.innerHTML = list.map(function (p) { return '<button type="button" data-pp="' + p.id + '"><span>' + esc(p.name) + '<br><small>' + esc(p.sku) + ' · ' + (p.kind === 'product' ? K.num(p.stock) + ' in stock' : 'service') + '</small></span><b>' + money(p.price) + '</b></button>'; }).join('') || '<div class="tiny" style="padding:10px">No match. Use a custom line.</div>';
        pd.hidden = false;
      };
      ps.addEventListener('focus', pdraw); ps.addEventListener('input', pdraw);
      ps.addEventListener('blur', function () { setTimeout(function () { pd.hidden = true; }, 200); });
      pd.addEventListener('mousedown', function (e) {
        var b = e.target.closest('[data-pp]'); if (!b) return; e.preventDefault();
        var p = prods.filter(function (x) { return +x.id === +b.dataset.pp; })[0];
        var ex = lines.filter(function (l) { return +l.product_id === +p.id; })[0];
        if (ex) ex.qty++; else lines.push({ product_id: p.id, description: p.name, qty: 1, price: p.price });
        ps.value = ''; pd.hidden = true; draw();
      });
      $('[data-addc]', m).onclick = function () { lines.push({ product_id: 0, description: '', qty: 1, price: 0 }); draw(); var l = $$('[data-ld]', m).pop(); if (l) l.focus(); };
      $('[data-addi]', m).onclick = function () {
        var fee = lines.reduce(function (a, l) { var p = prods.filter(function (x) { return +x.id === +l.product_id; })[0]; return a + (p ? (p.install_fee || 0) * l.qty : 0); }, 0);
        lines.push({ product_id: 0, description: 'Installation and commissioning', qty: 1, price: fee || 21000 }); draw();
      };
      var cs = $('[data-cs]', m), cd = $('[data-cd]', m);
      var cdraw = function () {
        var q = cs.value.toLowerCase(), list = custs.filter(function (c) { return !q || (c.name + ' ' + c.phone).toLowerCase().indexOf(q) > -1; }).slice(0, 8);
        cd.innerHTML = list.map(function (c) { return '<button type="button" data-cc="' + c.id + '"><span>' + esc(c.name) + '<br><small>' + esc(K.phone(c.phone)) + ' · ' + esc(c.county || '') + '</small></span>' + (c.owes > 0 ? '<small>owes ' + money(c.owes) + '</small>' : '') + '</button>'; }).join('') + (q ? '<button type="button" data-cnew><span>New customer: <b>' + esc(cs.value) + '</b></span></button>' : '');
        cd.hidden = !cd.innerHTML;
      };
      cs.addEventListener('focus', cdraw); cs.addEventListener('input', function () { cust = null; cdraw(); });
      cs.addEventListener('blur', function () { setTimeout(function () { cd.hidden = true; }, 200); });
      cd.addEventListener('mousedown', function (e) {
        var b = e.target.closest('[data-cc]'); e.preventDefault();
        if (b) { cust = custs.filter(function (c) { return +c.id === +b.dataset.cc; })[0]; cs.value = cust.name; $('[data-cp]', m).value = K.phone(cust.phone); $('[name=email]', m).value = cust.email || ''; $('[name=kra]', m).value = cust.kra || ''; }
        cd.hidden = true;
      });
      draw();
      $$('[data-save]', m).forEach(function (b) {
        b.onclick = function () {
          var f = K.read(m), lns = lines.filter(function (l) { return l.description && l.qty > 0; });
          if (!cs.value.trim()) { K.toast('Choose or type a customer.', true); cs.focus(); return; }
          if (!lns.length) { K.toast('Add at least one item.', true); return; }
          var payload = { type: type, lines: lns, due: f.due, notes: f.notes, send: +b.dataset.save };
          if (cust) { payload.customer_id = +cust.id; }
          else payload.customer = { name: cs.value.trim(), phone: $('[data-cp]', m).value, email: f.email, kra: f.kra, type: f.kra ? 'Business' : 'Home' };
          if (cust && (f.email !== (cust.email || '') || f.kra !== (cust.kra || ''))) K.api('customers/' + cust.id, { body: { email: f.email, kra: f.kra } });
          b.disabled = true;
          K.api(doc ? 'docs/' + doc.id : 'docs', { body: payload }).then(function (r) {
            K.closeModal(); K.toast((doc ? 'Saved ' : 'Created ') + r.number + (payload.send && r.customer && r.customer.email ? ' and emailed to ' + r.customer.email : ''));
            salesState.tab = type; if (K.mod === 'sales') K.route(); K.openDoc(r.id);
          }).catch(function (e) { b.disabled = false; K.fail(e); });
        };
      });
    }).catch(K.fail);
  };

  /* ================================================================ PAYMENTS */
  var payState = { f: '', q: '' };
  K.V.payments = function (v) {
    Promise.all([K.api('payments'), K.api('docs?type=invoice')]).then(function (res) {
      var rows = res[0], open = res[1].filter(function (d) { return d.balance > 0 && d.state !== 'void'; });
      var mon = K.today().slice(0, 7), month = rows.filter(function (p) { return String(p.received).slice(0, 7) === mon; });
      var un = rows.filter(function (p) { return p.status !== 'matched'; });
      var mp = K.me.settings.mpesa || {};
      v.innerHTML = K.head('Payments', 'M-Pesa payments arrive here the moment Safaricom confirms them and match to the right invoice by account number. Bank and cash payments are recorded here too.', '<label class="btn" title="Statement downloaded from the M-Pesa business portal">' + ic(I.up || I.plus) + 'Import M-Pesa statement<input type="file" accept=".csv,text/csv" hidden data-stmt></label><button class="btn sun" data-rec>' + ic(I.plus) + 'Record a payment</button>') +
        (K.me.mpesa_ready ? '' : '<div class="notice">M-Pesa prompts at checkout are not switched on yet. Payments still match to invoices on their own when you forward M-Pesa SMS or import your statement (<a href="#settings/payments">set up in Settings</a>). You can also record any payment here with its M-Pesa code.</div>') +
        '<div class="kpis"><div class="kpi"><small>Received this month</small><b>' + money(month.reduce(function (a, p) { return a + p.amount; }, 0)) + '</b><i class="mut">' + month.length + ' payments</i></div>' +
        '<div class="kpi"><small>Matched automatically</small><b>' + (rows.length ? Math.round(rows.filter(function (p) { return p.status === 'matched'; }).length / rows.length * 100) : 0) + '%</b><i class="mut">by invoice number</i></div>' +
        '<div class="kpi"><small>Waiting to match</small><b>' + un.length + '</b><i class="' + (un.length ? 'down' : 'up') + '">' + (un.length ? money(un.reduce(function (a, p) { return a + p.amount; }, 0)) : 'all matched') + '</i></div>' +
        '<div class="kpi"><small>' + (mp.type === 'till' ? 'Till number' : 'Paybill') + '</small><b>' + esc((mp.type === 'till' ? mp.till : mp.shortcode) || 'Not set') + '</b><i class="mut">Account number = invoice number</i></div></div>' +
        '<div class="box"><div class="toolbar"><input type="search" placeholder="Search code, name or phone" data-q value="' + esc(payState.q) + '"><div class="chips"><button class="chip' + (!payState.f ? ' on' : '') + '" data-f="">All</button><button class="chip' + (payState.f === 'unmatched' ? ' on' : '') + '" data-f="unmatched">Unmatched</button><button class="chip' + (payState.f === 'matched' ? ' on' : '') + '" data-f="matched">Matched</button></div></div><div data-t></div></div>';
      var draw = function () {
        var list = rows.filter(function (p) { return (!payState.f || (payState.f === 'matched') === (p.status === 'matched')) && (!payState.q || (p.code + ' ' + p.payer + ' ' + p.phone + ' ' + p.account_ref).toLowerCase().indexOf(payState.q.toLowerCase()) > -1); });
        $('[data-t]', v).innerHTML = K.table([
          { k: 'received', label: 'Received', f: function (p) { return K.ds(p.received) + ' <span class="tiny">' + K.t(p.received) + '</span>'; } },
          { k: 'code', label: 'Code', f: function (p) { return '<b class="mono">' + esc(p.code) + '</b>'; } },
          { k: 'payer', label: 'From', f: function (p) { return esc(p.payer || '—') + '<div class="tiny">' + esc(K.phone(p.phone)) + '</div>'; } },
          { k: 'method', label: 'Method', f: function (p) { return p.method === 'mpesa' ? 'M-Pesa' : esc(p.method.charAt(0).toUpperCase() + p.method.slice(1)); } },
          { k: 'account_ref', label: 'Reference given', f: function (p) { return '<span class="mono">' + esc(p.account_ref) + '</span>'; } },
          { k: 'amount', label: 'Amount', n: 1, f: function (p) { return money(p.amount); } },
          { k: 'status', label: 'Matched to', f: function (p) { return p.status === 'matched' ? K.badge('matched', esc(p.invoice)) + (p.receipt ? '<div class="tiny">' + esc(p.receipt) + '</div>' : '') : '<div style="display:flex;gap:6px;align-items:center">' + K.badge('unmatched') + '<select data-m="' + p.id + '" style="width:auto;min-height:32px;font-size:13px"><option value="">Match to…</option>' + open.map(function (o) { return '<option value="' + o.id + '">' + esc(o.number) + ' · ' + esc(o.customer_name) + ' · ' + money(o.balance) + '</option>'; }).join('') + '</select></div>'; } }
        ], list, { empty: 'No payments yet', emptySub: 'M-Pesa confirmations, prompts and recorded payments appear here.' });
        $$('[data-m]', v).forEach(function (s) { s.onchange = function () { if (!s.value) return; K.api('payments/' + s.dataset.m + '/match', { body: { doc_id: +s.value } }).then(function (r) { K.toast(r.message); K.refreshBadges(); K.route(); }).catch(K.fail); }; });
      };
      draw();
      $('[data-q]', v).oninput = function (e) { payState.q = e.target.value; draw(); };
      $$('[data-f]', v).forEach(function (b) { b.onclick = function () { payState.f = b.dataset.f; $$('[data-f]', v).forEach(function (x) { x.classList.toggle('on', x === b); }); draw(); }; });
      var st = $('[data-stmt]', v); if (st) st.onchange = function () { var f = st.files[0]; if (!f) return; var fd = new FormData(); fd.append('file', f); K.toast('Reading statement…'); fetch(DESK.api + 'payments/statement', { method: 'POST', body: fd, headers: { 'X-WP-Nonce': DESK.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) { K.toast(r.message, !r.ok); if (r.ok) K.V.payments(v); K.refreshBadges(); }).catch(K.fail); };
      $('[data-rec]', v).onclick = function () { K.recordPayment(null); };
    }).catch(K.fail);
  };

  /* ================================================================ CRM */
  var crmState = { tab: 'pipe', q: '' };
  K.V.crm = function (v, arg) {
    if (arg && arg[0] === 'c') { crmState.tab = 'cust'; setTimeout(function () { K.openCustomer(+arg.slice(1)); }, 50); }
    var stages = K.me.settings.lead_stages || ['New', 'Contacted', 'Survey booked', 'Quoted', 'Won', 'Lost'];
    Promise.all([K.api('leads'), K.api('customers')]).then(function (res) {
      var leads = res[0], custs = res[1];
      v.innerHTML = K.head('Customers and leads', 'Every solar calculator result, quote request and enquiry lands here as a lead. Drag a card to move it along.', '<button class="btn" data-nc>' + ic(I.plus) + 'New customer</button><button class="btn sun" data-nl>' + ic(I.plus) + 'New lead</button>') +
        K.tabs('crm', [['pipe', 'Pipeline', leads.filter(function (l) { return l.stage !== 'Won' && l.stage !== 'Lost'; }).length], ['cust', 'Customers', custs.length]], crmState.tab) + '<div data-body></div>';
      var body = $('[data-body]', v);
      $$('[data-tabs] button', v).forEach(function (b) { b.onclick = function () { crmState.tab = b.dataset.t; K.V.crm(v); }; });
      $('[data-nl]', v).onclick = function () { K.editLead(null, stages); };
      $('[data-nc]', v).onclick = function () { K.editCustomer(null); };
      if (crmState.tab === 'pipe') {
        body.innerHTML = '<div class="board">' + stages.map(function (s) {
          var ls = leads.filter(function (l) { return l.stage === s; });
          return '<div class="lane" data-lane="' + esc(s) + '"><h4>' + esc(s) + '<span>' + ls.length + (ls.length ? ' · ' + money(ls.reduce(function (a, l) { return a + +l.value; }, 0)) : '') + '</span></h4>' + ls.map(function (l) { return '<div class="ticket" draggable="true" data-lead="' + l.id + '"><b>' + esc(l.name) + '</b><small>' + esc(l.interest || '') + '</small><div class="row"><span class="st">' + esc(l.source || 'Direct') + '</span><small>' + (+l.value ? money(l.value) : '') + '</small></div></div>'; }).join('') + '</div>';
        }).join('') + '</div>';
        $$('[data-lead]', body).forEach(function (t) {
          t.onclick = function () { K.editLead(leads.filter(function (l) { return l.id == t.dataset.lead; })[0], stages); };
          t.ondragstart = function (e) { e.dataTransfer.setData('text', t.dataset.lead); t.classList.add('drag'); };
          t.ondragend = function () { t.classList.remove('drag'); };
        });
        $$('[data-lane]', body).forEach(function (l) {
          l.ondragover = function (e) { e.preventDefault(); l.classList.add('over'); };
          l.ondragleave = function () { l.classList.remove('over'); };
          l.ondrop = function (e) { e.preventDefault(); l.classList.remove('over'); var id = e.dataTransfer.getData('text'); K.api('leads/' + id, { body: { stage: l.dataset.lane } }).then(function () { K.toast('Moved to ' + l.dataset.lane); K.V.crm(v); K.refreshBadges(); }).catch(K.fail); };
        });
      } else {
        var draw = function () {
          var list = custs.filter(function (c) { return !crmState.q || (c.name + ' ' + c.phone + ' ' + c.email + ' ' + c.county).toLowerCase().indexOf(crmState.q.toLowerCase()) > -1; });
          body.innerHTML = '<div class="box"><div class="toolbar"><input type="search" placeholder="Search customers" value="' + esc(crmState.q) + '" data-q></div>' + K.table([
            { k: 'name', label: 'Customer', f: function (c) { return '<b>' + esc(c.name) + '</b><div class="tiny">' + esc(c.type) + '</div>'; } },
            { k: 'phone', label: 'Phone', f: function (c) { return esc(K.phone(c.phone)); } }, { k: 'email', label: 'Email' }, { k: 'county', label: 'County' },
            { k: 'invoices', label: 'Invoices', n: 1 }, { k: 'ltv', label: 'Bought', n: 1, f: function (c) { return money(c.ltv); } }, { k: 'owes', label: 'Owes', n: 1, f: function (c) { return c.owes > 0 ? '<b class="down">' + money(c.owes) + '</b>' : '—'; } }
          ], list, { click: 1, sortKey: crmState.sortKey, sortDir: crmState.sortDir, empty: 'No customers yet' }) + '</div>';
          $('[data-q]', body).oninput = function (e) { crmState.q = e.target.value; var p = e.target.selectionStart; draw(); var n = $('[data-q]', body); n.focus(); n.setSelectionRange(p, p); };
          $$('[data-row]', body).forEach(function (tr) { tr.onclick = function () { K.openCustomer(+tr.dataset.row); }; });
          K.sortable(body, crmState, draw);
        };
        draw();
      }
    }).catch(K.fail);
  };
  K.editLead = function (l, stages) {
    l = l || { stage: 'New', source: 'Phone call' };
    var data = l.data && l.data.picks ? '<p class="tiny">Includes a solar calculator result.</p>' : '';
    var m = K.modal(l.id ? esc(l.name) : 'New lead', '<div class="grid2">' + K.field('Name', 'name', l.name, 'text') + K.field('Phone', 'phone', K.phone(l.phone), 'tel') + '</div><div class="grid2">' + K.field('Email', 'email', l.email, 'email') + K.field('County or area', 'county', l.county, 'text') + '</div>' +
      K.field('Interested in', 'interest', l.interest, 'text', { ph: 'e.g. 5 kW hybrid for a 4-bedroom house' }) + '<div class="grid3">' + K.field('Stage', 'stage', l.stage, 'select', { options: opts(stages) }) + K.field('Estimated value', 'value', l.value || '', 'number') + K.field('Source', 'source', l.source, 'select', { options: opts(['Website quote form', 'Solar calculator', 'Phone call', 'WhatsApp', 'Walk-in', 'Referral', 'Social media', 'Website', 'Quote form', 'Other']) }) + '</div>' +
      K.field('Next follow-up', 'next_action', l.next_action || '', 'date') + K.field('Notes', 'notes', l.notes, 'textarea') + data,
      (l.id ? '<button class="btn danger" data-del>Delete</button>' : '') + (l.phone ? '<a class="btn" href="https://wa.me/' + String(l.phone).replace(/\D/g, '') + '" target="_blank" rel="noopener">' + ic(I.wa) + 'WhatsApp</a>' : '') + (l.id && K.can('sales') ? '<button class="btn" data-q>Create quotation</button>' : '') + '<button class="btn pri" data-s>Save</button>');
    $('[data-s]', m).onclick = function () { K.api('leads' + (l.id ? '/' + l.id : ''), { body: K.read(m) }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); K.refreshBadges(); }).catch(K.fail); };
    var del = $('[data-del]', m); if (del) del.onclick = function () { K.confirm('Delete this lead?', 'Delete', true).then(function (y) { if (y) K.api('leads/' + l.id, { method: 'DELETE' }).then(function () { K.toast('Deleted'); K.route(); }); }); };
    var qb = $('[data-q]', m);
    if (qb) qb.onclick = function () {
      var f = K.read(m); K.closeModal();
      K.api('leads/' + l.id, { body: { stage: 'Quoted' } });
      K.editDoc('quote', { lines: [], customer: { name: f.name, phone: f.phone, email: f.email, kra: '' }, notes: f.interest, due: K.add(K.today(), +K.me.settings.quote_valid) });
    };
  };
  K.editCustomer = function (c) {
    c = c || { type: 'Home' };
    var m = K.modal(c.id ? 'Edit ' + esc(c.name) : 'New customer', '<div class="grid2">' + K.field('Name', 'name', c.name, 'text') + K.field('Type', 'type', c.type, 'select', { options: opts(['Home', 'Business', 'Institution', 'Farm', 'NGO', 'Government']) }) + '</div><div class="grid2">' + K.field('Phone', 'phone', K.phone(c.phone), 'tel') + K.field('Email', 'email', c.email, 'email') + '</div><div class="grid2">' + K.field('KRA PIN', 'kra', c.kra, 'text') + K.field('County', 'county', c.county, 'text') + '</div>' + K.field('Address or location', 'address', c.address, 'text') + K.field('Notes', 'notes', c.notes, 'textarea'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>');
    $('[data-s]', m).onclick = function () { K.api('customers' + (c.id ? '/' + c.id : ''), { body: K.read(m) }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); }).catch(K.fail); };
  };
  K.openCustomer = function (id) {
    Promise.all([K.api('customers'), K.api('docs?customer=' + id)]).then(function (res) {
      var c = res[0].filter(function (x) { return +x.id === id; })[0]; if (!c) return;
      var docs = res[1];
      var dr = K.drawer(esc(c.name), '<div class="kpis"><div class="kpi"><small>Bought</small><b>' + money(c.ltv) + '</b></div><div class="kpi"><small>Owes</small><b class="' + (c.owes > 0 ? 'down' : '') + '">' + money(c.owes) + '</b></div></div><dl class="kv"><dt>Phone</dt><dd>' + esc(K.phone(c.phone)) + '</dd><dt>Email</dt><dd>' + esc(c.email || '—') + '</dd><dt>KRA PIN</dt><dd>' + esc(c.kra || '—') + '</dd><dt>Location</dt><dd>' + esc([c.address, c.county].filter(Boolean).join(', ') || '—') + '</dd>' + (c.notes ? '<dt>Notes</dt><dd>' + esc(c.notes) + '</dd>' : '') + '</dl>' +
        '<div class="actgrid"><button class="btn" data-e>' + ic(I.edit) + 'Edit</button>' + (K.can('sales') ? '<button class="btn" data-nq>New quotation</button><button class="btn sun" data-ni>New invoice</button>' : '') + (c.phone ? '<a class="btn" target="_blank" rel="noopener" href="https://wa.me/' + String(c.phone).replace(/\D/g, '') + '">' + ic(I.wa) + 'WhatsApp</a><a class="btn" href="tel:' + esc(c.phone) + '">' + ic(I.phone) + 'Call</a>' : '') + '</div>' +
        '<div class="box"><h3>History</h3>' + K.table([{ k: 'number', label: 'Document', f: function (d) { return '<b class="mono">' + esc(d.number) + '</b>'; } }, { k: 'date', label: 'Date', f: function (d) { return K.ds(d.date); } }, { k: 'total', label: 'Total', n: 1, f: function (d) { return money(d.total); } }, { k: 'state', label: 'Status', f: function (d) { return K.badge(d.state); } }], docs, { click: 1, stack: false, empty: 'No documents yet' }) + '</div>', '', true);
      $('[data-e]', dr).onclick = function () { K.editCustomer(c); };
      var nq = $('[data-nq]', dr), ni = $('[data-ni]', dr);
      var pre = { lines: [], customer: c, due: '' };
      if (nq) nq.onclick = function () { K.closeDrawer(); K.editDoc('quote', null); setTimeout(function () { var cs = $('[data-cs]'); if (cs) { cs.value = c.name; cs.dispatchEvent(new Event('focus')); } }, 300); };
      if (ni) ni.onclick = function () { K.closeDrawer(); K.editDoc('invoice', null); setTimeout(function () { var cs = $('[data-cs]'); if (cs) { cs.value = c.name; cs.dispatchEvent(new Event('focus')); } }, 300); };
      $$('[data-row]', dr).forEach(function (tr) { tr.onclick = function () { K.openDoc(+tr.dataset.row); }; });
    }).catch(K.fail);
  };

  /* ================================================================ INSTALLATIONS */
  K.V.jobs = function (v) {
    var stages = K.me.settings.job_stages || ['Survey', 'Scheduled', 'Installing', 'Commissioned', 'Maintenance'];
    var checklist = K.me.settings.checklist || [];
    K.api('jobs').then(function (jobs) {
      v.innerHTML = K.head('Installations', 'From survey to handover to aftercare. Drag a job to move it; open it to tick the checklist.', '<button class="btn sun" data-nj>' + ic(I.plus) + 'New job</button>') +
        '<div class="board">' + stages.map(function (s) {
          var js = jobs.filter(function (j) { return j.stage === s; });
          return '<div class="lane" data-lane="' + esc(s) + '"><h4>' + esc(s) + '<span>' + js.length + '</span></h4>' + js.map(function (j) { var done = (j.checklist || []).length, pct = checklist.length ? Math.round(done / checklist.length * 100) : 0; return '<div class="ticket" draggable="true" data-job="' + j.id + '"><b>' + esc(j.customer || j.title) + '</b><small>' + esc(j.title) + (j.size ? ' · ' + esc(j.size) : '') + '</small><small>' + esc(j.location) + '</small><div class="row"><small>' + esc(j.techs || 'Unassigned') + '</small><span class="st">' + (j.date ? K.ds(j.date) : 'No date') + '</span></div><div class="prog"><i style="width:' + pct + '%"></i></div></div>'; }).join('') + '</div>';
        }).join('') + '</div>';
      $('[data-nj]', v).onclick = function () { K.editJob(null, stages, checklist); };
      if (K.openJob) { var oj = jobs.filter(function (j) { return j.id == K.openJob; })[0]; K.openJob = 0; if (oj) K.editJob(oj, stages, checklist); }
      $$('[data-job]', v).forEach(function (t) {
        t.onclick = function () { K.editJob(jobs.filter(function (j) { return j.id == t.dataset.job; })[0], stages, checklist); };
        t.ondragstart = function (e) { e.dataTransfer.setData('text', t.dataset.job); t.classList.add('drag'); };
        t.ondragend = function () { t.classList.remove('drag'); };
      });
      $$('[data-lane]', v).forEach(function (l) {
        l.ondragover = function (e) { e.preventDefault(); l.classList.add('over'); };
        l.ondragleave = function () { l.classList.remove('over'); };
        l.ondrop = function (e) { e.preventDefault(); l.classList.remove('over'); K.api('jobs/' + e.dataTransfer.getData('text'), { body: { stage: l.dataset.lane } }).then(function () { K.toast('Moved to ' + l.dataset.lane); K.V.jobs(v); }).catch(K.fail); };
      });
    }).catch(K.fail);
  };
  K.editJob = function (j, stages, checklist) {
    j = j || { stage: 'Survey', checklist: [] };
    Promise.all([K.api('customers'), K.api('team')]).then(function (res) {
      var custs = res[0], team = res[1];
      var done = j.checklist || [];
      var m = K.modal(j.id ? esc(j.customer || j.title) : 'New installation job',
        '<div class="grid2">' + K.field('Customer', 'customer_id', j.customer_id || '', 'select', { options: [['', 'Choose a customer']].concat(custs.map(function (c) { return [c.id, c.name]; })) }) + K.field('Work', 'title', j.title, 'text', { ph: 'e.g. 5 kW hybrid install' }) + '</div>' +
        '<div class="grid3">' + K.field('Stage', 'stage', j.stage, 'select', { options: opts(stages) }) + K.field('Date', 'date', j.date || '', 'date') + K.field('System size', 'size', j.size, 'text', { ph: 'e.g. 5 kW · 10 kWh' }) + '</div>' +
        '<div class="grid2">' + K.field('Team', 'techs', j.techs, 'text', { list: 'teamlist', ph: 'Who is going' }) + K.field('Location', 'location', j.location, 'text') + '</div><datalist id="teamlist">' + team.map(function (t) { return '<option value="' + esc(t.name) + '">'; }).join('') + '</datalist>' +
        '<div class="flabel">Checklist</div><div data-cl>' + checklist.map(function (c) { return '<label class="tog"><span>' + esc(c) + '</span><input type="checkbox" class="sw" data-ck="' + esc(c) + '"' + (done.indexOf(c) > -1 ? ' checked' : '') + '></label>'; }).join('') + '</div>' +
        K.field('Notes', 'notes', j.notes, 'textarea') + (j.doc_number ? '<p class="tiny">Linked invoice: <a href="#sales/' + j.doc_id + '">' + esc(j.doc_number) + '</a></p>' : ''),
        (j.id ? '<button class="btn danger" data-del>Delete</button>' : '') + (j.phone ? '<a class="btn" target="_blank" rel="noopener" href="https://wa.me/' + String(j.phone).replace(/\D/g, '') + '">' + ic(I.wa) + 'WhatsApp customer</a>' : '') + '<button class="btn" data-sch>Save and add to schedule</button><button class="btn pri" data-s>Save</button>', true);
      var save = function (thenSchedule) {
        var f = K.read(m); f.checklist = $$('[data-ck]', m).filter(function (x) { return x.checked; }).map(function (x) { return x.dataset.ck; });
        if (!f.customer_id && !f.title) { K.toast('Choose a customer or describe the work.', true); return; }
        K.api('jobs' + (j.id ? '/' + j.id : ''), { body: f }).then(function (r) {
          K.closeModal(); K.toast('Saved');
          if (thenSchedule) { var c = custs.filter(function (x) { return x.id == f.customer_id; })[0]; return K.api('events', { body: { title: (c ? c.name + ': ' : '') + f.title, kind: 'Install', start: (f.date || K.today()) + ' 09:00:00', who: f.techs, location: f.location, job_id: r.id } }).then(function () { K.toast('Added to the schedule'); K.route(); }); }
          K.route();
        }).catch(K.fail);
      };
      $('[data-s]', m).onclick = function () { save(false); };
      $('[data-sch]', m).onclick = function () { save(true); };
      var del = $('[data-del]', m); if (del) del.onclick = function () { K.confirm('Delete this job?', 'Delete', true).then(function (y) { if (y) K.api('jobs/' + j.id, { method: 'DELETE' }).then(function () { K.closeModal(); K.route(); }); }); };
    }).catch(K.fail);
  };

  /* ================================================================ SCHEDULE */
  var schedState = { start: null, who: '' };
  K.V.schedule = function (v) {
    var monday = function (iso) { var d = new Date(iso + 'T12:00:00'), w = (d.getDay() + 6) % 7; d.setDate(d.getDate() - w); return K.iso(d); };
    if (!schedState.start) schedState.start = monday(K.today());
    var from = schedState.start, to = K.add(from, 13);
    Promise.all([K.api('events?from=' + from + '&to=' + to), K.can('jobs') ? K.api('jobs').catch(function () { return []; }) : Promise.resolve([])]).then(function (res) {
      var evs = res[0], jobs = res[1] || [];
      var linked = {}; evs.forEach(function (e) { if (+e.job_id) linked[e.job_id] = 1; });
      jobs.forEach(function (j) { if (j.date && j.date >= from && j.date <= to && !linked[j.id] && !evs.some(function (e) { return String(e.start).slice(0, 10) === j.date && (String(e.title).toLowerCase().indexOf(String(j.title).toLowerCase()) > -1 || (j.location && e.location === j.location)); })) evs.push({ id: 'j' + j.id, job: j.id, title: j.title + (j.customer ? ' · ' + j.customer : ''), kind: 'Install', start: j.date + ' 08:00:00', who: j.techs || '', location: j.location || '', done: j.stage === 'Commissioned' ? 1 : 0 }); });
      var people = Array.from(new Set(evs.map(function (e) { return e.who; }).filter(Boolean)));
      var list = evs.filter(function (e) { return !schedState.who || e.who === schedState.who; });
      var days = [];
      for (var i = 0; i < 14; i++) days.push(K.add(from, i));
      var wd = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      var kc = function (k) { k = String(k || '').toLowerCase(); return k.indexOf('install') > -1 ? 'k-ins' : k.indexOf('survey') > -1 ? 'k-sur' : k.indexOf('maint') > -1 ? 'k-mnt' : k.indexOf('call') > -1 ? 'k-call' : 'k-oth'; };
      v.innerHTML = K.head('Schedule', 'Installs, surveys, calls and maintenance for the next two weeks. The team gets an email reminder the evening before.', '<button class="btn" data-prev>← Earlier</button><button class="btn" data-now>This week</button><button class="btn" data-next>Later →</button><button class="btn sun" data-ne>' + ic(I.plus) + 'Add</button>') +
        '<div class="box"><div class="toolbar"><select data-who><option value="">Everyone</option>' + people.map(function (p) { return '<option' + (p === schedState.who ? ' selected' : '') + '>' + esc(p) + '</option>'; }).join('') + '</select><span class="tiny">' + K.d(from) + ' to ' + K.d(to) + '</span><span class="legend"><i class="k-ins"></i>Install<i class="k-sur"></i>Survey<i class="k-mnt"></i>Maintenance<i class="k-call"></i>Call</span></div>' +
        '<div class="cal">' + wd.map(function (w) { return '<div class="cal-h">' + w + '</div>'; }).join('') +
        days.map(function (d) {
          var es = list.filter(function (e) { return String(e.start).slice(0, 10) === d; });
          var past = d < K.today();
          return '<div class="cal-d' + (d === K.today() ? ' today' : '') + (past ? ' past' : '') + '" data-day="' + d + '"><span class="cal-n">' + K.ds(d) + '</span>' + es.map(function (e) { return '<button class="chip-ev ' + kc(e.kind) + (+e.done ? ' done' : '') + '" ' + (e.job ? 'data-oj="' + e.job + '"' : 'data-ee="' + e.id + '"') + ' title="' + esc(e.title) + '"><time>' + K.t(e.start) + '</time><b>' + esc(e.title) + '</b>' + (e.who ? '<small>' + esc(e.who) + '</small>' : '') + '</button>'; }).join('') + '<button class="cal-add" data-addday="' + d + '" aria-label="Add on ' + K.ds(d) + '">+</button></div>';
        }).join('') + '</div></div>';
      $$('[data-addday]', v).forEach(function (b) { b.onclick = function () { K.editEvent({ kind: 'Install', start: b.dataset.addday + ' 09:00:00', remind: 1 }); }; });
      $$('[data-oj]', v).forEach(function (b) { b.onclick = function () { K.openJob = +b.dataset.oj; location.hash = '#jobs'; }; });
      $('[data-prev]', v).onclick = function () { schedState.start = K.add(from, -7); K.V.schedule(v); };
      $('[data-next]', v).onclick = function () { schedState.start = K.add(from, 7); K.V.schedule(v); };
      $('[data-now]', v).onclick = function () { schedState.start = monday(K.today()); K.V.schedule(v); };
      $('[data-who]', v).onchange = function (e) { schedState.who = e.target.value; K.V.schedule(v); };
      $('[data-ne]', v).onclick = function () { K.editEvent(null); };
      $$('[data-ee]', v).forEach(function (b) { b.onclick = function () { K.editEvent(evs.filter(function (e) { return e.id == b.dataset.ee; })[0]); }; });
      $$('[data-done]', v).forEach(function (b) { b.onclick = function () { K.api('events/' + b.dataset.done, { body: { done: +b.dataset.v } }).then(function () { K.V.schedule(v); }); }; });
    }).catch(K.fail);
  };
  K.editEvent = function (e) {
    e = e || { kind: 'Install', start: K.today() + ' 09:00:00', remind: 1 };
    K.api('team').then(function (team) {
      var m = K.modal(e.id ? 'Edit' : 'Add to schedule', K.field('What', 'title', e.title, 'text', { ph: 'e.g. Site survey at Greenleaf Academy' }) + '<div class="grid3">' + K.field('Date', 'd', String(e.start).slice(0, 10), 'date') + K.field('Time', 't', K.t(e.start), 'time') + K.field('Type', 'kind', e.kind, 'select', { options: opts(['Install', 'Survey', 'Maintenance', 'Delivery', 'Call', 'Meeting', 'Task']) }) + '</div>' +
        '<div class="grid2">' + K.field('Who', 'who', e.who, 'text', { list: 'tl' }) + K.field('Where', 'location', e.location, 'text') + '</div><datalist id="tl">' + team.map(function (t) { return '<option value="' + esc(t.name) + '">'; }).join('') + '</datalist>' + K.field('Email a reminder the evening before', 'remind', e.remind, 'toggle'),
        (e.id ? '<button class="btn danger" data-del>Delete</button>' : '') + '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>');
      $('[data-s]', m).onclick = function () { var f = K.read(m); f.start = f.d + ' ' + (f.t || '09:00') + ':00'; delete f.d; delete f.t; if (!f.title) { K.toast('Say what it is.', true); return; } K.api('events' + (e.id ? '/' + e.id : ''), { body: f }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); }).catch(K.fail); };
      var del = $('[data-del]', m); if (del) del.onclick = function () { K.api('events/' + e.id, { method: 'DELETE' }).then(function () { K.closeModal(); K.route(); }); };
    });
  };

  /* ================================================================ PRODUCTS AND STOCK */
  var stockState = { tab: 'items', q: '', cat: '', low: 0 };
  K.V.stock = function (v, arg) {
    if (arg && /^\d+$/.test(arg)) setTimeout(function () { K.editProduct(+arg); }, 60);
    K.products(true).then(function (ps) {
      var goods = ps.filter(function (p) { return p.kind === 'product'; });
      var val = goods.reduce(function (a, p) { return a + p.stock * (p.cost || 0); }, 0);
      var low = goods.filter(function (p) { return p.stock <= p.reorder; });
      v.innerHTML = K.head('Products and stock', 'Your catalogue drives the website shop. Stock drops when an invoice is paid or delivered, and rises when a purchase order is received.', (K.can('purchases') ? '<a class="btn" href="#purchases">Purchase orders</a>' : '') + '<button class="btn sun" data-np>' + ic(I.plus) + 'Add product</button>') +
        '<div class="kpis"><div class="kpi"><small>Stock value at cost</small><b>' + money(val) + '</b></div><div class="kpi"><small>Products</small><b>' + ps.length + '</b><i class="mut">' + ps.filter(function (p) { return +p.visible; }).length + ' on the website</i></div><div class="kpi"><small>At or below reorder level</small><b>' + low.length + '</b><i class="' + (low.length ? 'down' : 'up') + '">' + (low.length ? 'order soon' : 'all good') + '</i></div></div>' +
        K.tabs('stock', [['items', 'Products'], ['cats', 'Categories']], stockState.tab) + '<div data-b></div>';
      $('[data-np]', v).onclick = function () { K.editProduct(0); };
      $$('[data-tabs] button', v).forEach(function (b) { b.onclick = function () { stockState.tab = b.dataset.t; K.V.stock(v); }; });
      var b = $('[data-b]', v);
      if (stockState.tab === 'cats') return K.catEditor(b);
      var draw = function () {
        var list = ps.filter(function (p) { return (!stockState.cat || p.cat === stockState.cat) && (!stockState.low || (p.kind === 'product' && p.stock <= p.reorder)) && (!stockState.q || (p.name + ' ' + p.sku + ' ' + p.brand).toLowerCase().indexOf(stockState.q.toLowerCase()) > -1); });
        b.innerHTML = '<div class="box"><div class="toolbar"><input type="search" placeholder="Search name, code or brand" value="' + esc(stockState.q) + '" data-q><select data-c><option value="">All categories</option>' + K.cats.map(function (c) { return '<option value="' + esc(c.id) + '"' + (stockState.cat === c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') + '</select><label class="tog" style="border:0;padding:0;gap:6px"><input type="checkbox" data-l' + (stockState.low ? ' checked' : '') + '> Low stock only</label></div>' +
          K.table([
            { k: 'name', label: 'Product', f: function (p) { return '<div style="display:flex;gap:10px;align-items:center"><span class="thumbc">' + (p.images && p.images[0] ? '<img src="' + esc(p.images[0]) + '" alt="">' : K.icon(p.icon, 28)) + '</span><span><b>' + esc(p.name) + '</b><div class="tiny">' + esc(p.sku) + (p.brand ? ' · ' + esc(p.brand) : '') + '</div></span></div>'; } },
            { k: 'cat_name', label: 'Category' },
            { k: 'cost', label: 'Cost', n: 1, f: function (p) { return p.cost != null ? money(p.cost, 1) : ''; } },
            { k: 'price', label: 'Price', n: 1, f: function (p) { return money(p.price, 1); } },
            { k: 'stock', label: 'In stock', n: 1, f: function (p) { return p.kind !== 'product' ? '<span class="tiny">Service</span>' : (p.stock <= p.reorder ? K.badge('low', K.num(p.stock)) : K.num(p.stock)); } },
            { k: 'reorder', label: 'Reorder at', n: 1, f: function (p) { return p.kind === 'product' ? K.num(p.reorder) : ''; } },
            { k: 'visible', label: 'On website', f: function (p) { return '<input type="checkbox" class="sw" data-vis="' + p.id + '"' + (+p.visible ? ' checked' : '') + ' aria-label="Show on website">'; } }
          ], list, { click: 1, sortKey: stockState.sortKey, sortDir: stockState.sortDir, empty: 'No products match' }) + '</div>';
        $('[data-q]', b).oninput = function (e) { stockState.q = e.target.value; var p = e.target.selectionStart; draw(); var n = $('[data-q]', b); n.focus(); n.setSelectionRange(p, p); };
        $('[data-c]', b).onchange = function (e) { stockState.cat = e.target.value; draw(); };
        $('[data-l]', b).onchange = function (e) { stockState.low = e.target.checked ? 1 : 0; draw(); };
        $$('[data-vis]', b).forEach(function (s) { s.onclick = function (e) { e.stopPropagation(); }; s.onchange = function () { K.api('products/' + s.dataset.vis, { body: { visible: s.checked ? 1 : 0 } }).then(function () { K.toast(s.checked ? 'Shown on the website' : 'Hidden from the website'); }).catch(K.fail); }; });
        $$('[data-row]', b).forEach(function (tr) { tr.onclick = function (e) { if (e.target.closest('[data-vis]')) return; tr.classList.add('loading'); K.editProduct(+tr.dataset.row); setTimeout(function () { tr.classList.remove('loading'); }, 2500); }; });
        K.sortable(b, stockState, draw);
      };
      draw();
    }).catch(K.fail);
  };
  K.editProduct = function (id) {
    var load = id ? K.products().then(function (ps) { return ps.filter(function (p) { return +p.id === +id; })[0]; }) : Promise.resolve(null);
    Promise.all([load, id ? K.api('products/' + id + '/moves') : Promise.resolve([])]).then(function (res) {
      var p = res[0] || { kind: 'product', visible: 1, vatable: 1, specs: {}, images: [], cat: (K.cats[0] || {}).id, unit: 'pc', warranty: '1 year' }, moves = res[1];
      var specs = Object.keys(p.specs || {}).map(function (k) { return [k, p.specs[k]]; });
      var images = (p.images || []).slice();
      var body = '<div class="grid2">' + K.field('Name', 'name', p.name, 'text') + K.field('Product code', 'sku', p.sku, 'text') + '</div>' +
        '<div class="grid3">' + K.field('Category', 'cat', p.cat, 'select', { options: K.cats.map(function (c) { return [c.id, c.name]; }) }) + K.field('Brand', 'brand', p.brand, 'text') + K.field('Type', 'kind', p.kind, 'select', { options: [['product', 'Product (counted in stock)'], ['service', 'Service']] }) + '</div>' +
        '<div class="grid3">' + K.field('Selling price (incl. VAT)', 'price', p.price, 'number') + K.field('Was price (shows a saving)', 'was', p.was || '', 'number') + K.field('Cost price', 'cost', p.cost, 'number', { hint: 'Used for profit reports. Staff without stock access cannot see it.' }) + '</div>' +
        '<div class="grid3">' + (id ? '<div class="f"><label>In stock</label><div style="display:flex;gap:6px"><input value="' + K.num(p.stock) + '" disabled><button class="btn" data-adj type="button">Adjust</button></div></div>' : K.field('Opening stock', 'stock', 0, 'number')) + K.field('Reorder when at or below', 'reorder', p.reorder, 'number') + K.field('Installation fee', 'install_fee', p.install_fee || '', 'number', { hint: 'Offered at checkout. Leave blank to estimate.' }) + '</div>' +
        K.field('What it powers (one line shown on the shop)', 'runs', p.runs, 'text', { ph: 'e.g. Lights, TV, fridge and Wi-Fi, day and night' }) +
        '<div class="flabel">Photos</div><div class="imgs" data-imgs></div><p class="tiny">The first photo is the main one. Products without photos show a clean illustration.</p>' +
        '<div class="flabel" style="margin-top:12px">Specifications</div><div class="specrows" data-specs></div><button class="btn sm" type="button" data-addspec>' + ic(I.plus) + 'Add specification</button>' +
        '<div style="height:12px"></div>' + K.field('Description', 'description', p.description, 'textarea') +
        '<div class="grid3">' + K.field('Warranty', 'warranty', p.warranty, 'text') + K.field('Badge', 'tag', p.tag, 'text', { ph: 'e.g. Best seller' }) + K.field('Unit', 'unit', p.unit, 'text') + '</div>' +
        '<div class="grid2">' + K.field('Power (W) for the solar calculator', 'watts', p.watts || '', 'number', { hint: 'Panels and inverters' }) + K.field('Storage (kWh) for the solar calculator', 'kwh', p.kwh || '', 'number', { hint: 'Batteries and power stations' }) + '</div>' +
        K.field('Show on the website', 'visible', p.visible, 'toggle') + K.field('Feature on the home page', 'featured', p.featured, 'toggle') + K.field('Charge VAT', 'vatable', p.vatable, 'toggle') +
        (moves.length ? '<div class="box" style="margin-top:14px"><h3>Stock movements</h3>' + K.table([{ k: 'created', label: 'Date', f: function (x) { return K.ds(x.created); } }, { k: 'reason', label: 'Reason' }, { k: 'ref', label: 'Ref' }, { k: 'user', label: 'By' }, { k: 'qty', label: 'Qty', n: 1, f: function (x) { return (x.qty > 0 ? '+' : '') + K.num(x.qty); } }], moves.slice(0, 20), { stack: false }) + '</div>' : '');
      var m = K.modal(id ? esc(p.name) : 'New product', body, (id ? '<a class="btn" target="_blank" rel="noopener" href="' + esc(p.url) + '">' + ic(I.ext) + 'View on site</a><button class="btn danger" data-del>Delete</button>' : '') + '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>', true);
      var drawImgs = function () { $('[data-imgs]', m).innerHTML = images.map(function (u, i) { return '<div class="im"><img src="' + esc(u) + '" alt=""><button type="button" data-rmi="' + i + '" aria-label="Remove">✕</button></div>'; }).join('') + '<button type="button" class="add" data-addimg>+ Add photo</button>'; };
      var drawSpecs = function () { $('[data-specs]', m).innerHTML = specs.map(function (s, i) { return '<div class="r"><input value="' + esc(s[0]) + '" data-sk="' + i + '" placeholder="e.g. Capacity"><input value="' + esc(s[1]) + '" data-sv="' + i + '" placeholder="e.g. 5.12 kWh"><button type="button" class="btn ghost sm" data-sx="' + i + '" aria-label="Remove">' + ic(I.x) + '</button></div>'; }).join(''); };
      drawImgs(); drawSpecs();
      m.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-addimg]')) K.pickImage().then(function (u) { images = images.concat(u); drawImgs(); });
        var r = t.closest('[data-rmi]'); if (r) { images.splice(+r.dataset.rmi, 1); drawImgs(); }
        if (t.closest('[data-addspec]')) { specs.push(['', '']); drawSpecs(); }
        var sx = t.closest('[data-sx]'); if (sx) { specs.splice(+sx.dataset.sx, 1); drawSpecs(); }
      });
      m.addEventListener('input', function (e) { var t = e.target; if (t.dataset.sk != null) specs[t.dataset.sk][0] = t.value; if (t.dataset.sv != null) specs[t.dataset.sv][1] = t.value; });
      var adj = $('[data-adj]', m);
      if (adj) adj.onclick = function () {
        var m2 = K.modal('Adjust stock', '<p class="tiny">Currently ' + K.num(p.stock) + ' in stock.</p><div class="grid2">' + K.field('Change by', 'qty', '', 'number', { hint: 'Use a minus for items out, e.g. -2' }) + K.field('Reason', 'reason', 'Stock count', 'select', { options: opts(['Stock count', 'Damaged', 'Returned by customer', 'Used on a job', 'Found', 'Other']) }) + '</div>' + K.field('Reference (optional)', 'ref', '', 'text'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Save</button>');
        $('[data-go]', m2).onclick = function () { K.api('products/' + id + '/adjust', { body: K.read(m2) }).then(function () { K.closeModal(); K.toast('Stock updated'); productsCache = null; K.route(); }).catch(K.fail); };
      };
      $('[data-s]', m).onclick = function () {
        var f = K.read(m); f.images = images; f.specs = specs.filter(function (s) { return s[0]; }).map(function (s) { return { k: s[0], v: s[1] }; });
        if (!f.name) { K.toast('Give the product a name.', true); return; }
        K.api('products' + (id ? '/' + id : ''), { body: f }).then(function () { K.closeModal(); K.toast('Saved. The website shop is updated.'); productsCache = null; if (K.mod === 'stock') K.route(); }).catch(K.fail);
      };
      var del = $('[data-del]', m);
      if (del) del.onclick = function () { K.confirm('Delete ' + esc(p.name) + '? If it is on past invoices it will be hidden instead.', 'Delete', true).then(function (y) { if (y) K.api('products/' + id, { method: 'DELETE' }).then(function (r) { K.closeModal(); K.toast(r.message || 'Deleted'); productsCache = null; K.route(); }); }); };
    }).catch(K.fail);
  };
  K.catEditor = function (host) {
    var cats = JSON.parse(JSON.stringify(K.cats)), names = Object.keys(D.icons);
    var draw = function () {
      host.innerHTML = '<div class="box"><p class="sub">Categories group the shop, the mega menu and the reports. Groups become the columns in the menu.</p>' + cats.map(function (c, i) { return '<div class="grid3" style="grid-template-columns:44px minmax(0,1fr) minmax(0,1fr) auto;align-items:end;border-bottom:1px solid var(--line);padding:10px 0"><button class="thumbc" data-ico="' + i + '" title="Change icon" style="border:0">' + K.icon(c.icon, 28) + '</button>' + K.field('Name', 'n' + i, c.name, 'text') + K.field('Menu group', 'g' + i, c.group, 'text') + '<div class="acts" style="margin-bottom:12px"><button class="btn sm ghost" data-up="' + i + '">' + ic(I.up) + '</button><button class="btn sm ghost" data-dn="' + i + '">' + ic(I.down) + '</button><button class="btn sm ghost" data-rm="' + i + '">' + ic(I.trash) + '</button></div></div>'; }).join('') +
        '<div class="acts" style="margin-top:12px"><button class="btn" data-add>' + ic(I.plus) + 'Add category</button><button class="btn pri" data-save>Save categories</button></div></div>';
    };
    var pull = function () { cats.forEach(function (c, i) { var n = $('[name=n' + i + ']', host), g = $('[name=g' + i + ']', host); if (n) c.name = n.value; if (g) c.group = g.value; }); };
    draw();
    host.onclick = function (e) {
      var t = e.target.closest('button'); if (!t) return; pull();
      if (t.dataset.add != null) { cats.push({ id: '', name: 'New category', icon: 'panel', group: 'More' }); draw(); }
      if (t.dataset.up) { var i = +t.dataset.up; if (i) { cats.splice(i - 1, 0, cats.splice(i, 1)[0]); draw(); } }
      if (t.dataset.dn) { var j = +t.dataset.dn; if (j < cats.length - 1) { cats.splice(j + 1, 0, cats.splice(j, 1)[0]); draw(); } }
      if (t.dataset.rm) { cats.splice(+t.dataset.rm, 1); draw(); }
      if (t.dataset.ico) {
        var k = +t.dataset.ico, m = K.modal('Choose an icon', '<div class="iconpick">' + names.map(function (n) { return '<button data-pick="' + n + '" class="' + (cats[k].icon === n ? 'on' : '') + '">' + K.icon(n, 28) + '</button>'; }).join('') + '</div>');
        m.addEventListener('click', function (ev) { var b = ev.target.closest('[data-pick]'); if (b) { cats[k].icon = b.dataset.pick; K.closeModal(); draw(); } });
      }
      if (t.dataset.save != null) K.api('categories', { body: cats }).then(function (r) { K.cats = r; K.toast('Categories saved'); draw(); }).catch(K.fail);
    };
  };

  /* ================================================================ PURCHASES */
  K.V.purchases = function (v) {
    Promise.all([K.api('purchases'), K.api('suppliers')]).then(function (res) {
      var pos = res[0], sup = res[1];
      v.innerHTML = K.head('Purchases', 'Order stock from suppliers. When goods arrive, receive the order and stock goes up automatically.', '<button class="btn" data-ns>' + ic(I.plus) + 'Supplier</button><button class="btn sun" data-np>' + ic(I.plus) + 'Purchase order</button>') +
        '<div class="kpis"><div class="kpi"><small>On order</small><b>' + money(pos.filter(function (p) { return p.status === 'ordered'; }).reduce(function (a, p) { return a + +p.total; }, 0)) + '</b></div><div class="kpi"><small>Received this month</small><b>' + money(pos.filter(function (p) { return p.status === 'received' && String(p.received).slice(0, 7) === K.today().slice(0, 7); }).reduce(function (a, p) { return a + +p.total; }, 0)) + '</b></div><div class="kpi"><small>Suppliers</small><b>' + sup.length + '</b></div></div>' +
        '<div class="box"><h3>Purchase orders</h3>' + K.table([{ k: 'number', label: 'Order', f: function (p) { return '<b class="mono">' + esc(p.number) + '</b>'; } }, { k: 'supplier', label: 'Supplier' }, { k: 'date', label: 'Date', f: function (p) { return K.ds(p.date); } }, { k: 'x', label: 'Items', f: function (p) { return esc((p.items || []).map(function (l) { return K.num(l.qty) + ' × ' + l.name; }).join(', ')); } }, { k: 'total', label: 'Total', n: 1, f: function (p) { return money(p.total); } }, { k: 'status', label: 'Status', f: function (p) { return K.badge(p.status) + (p.status === 'ordered' ? ' <button class="btn sm" data-rc="' + p.id + '">Receive</button>' : ''); } }], pos, { empty: 'No purchase orders yet' }) + '</div>' +
        '<div class="box"><h3>Suppliers</h3>' + K.table([{ k: 'name', label: 'Supplier', f: function (s) { return '<b>' + esc(s.name) + '</b>'; } }, { k: 'contact', label: 'Contact' }, { k: 'phone', label: 'Phone', f: function (s) { return esc(K.phone(s.phone)); } }, { k: 'email', label: 'Email' }, { k: 'kra', label: 'KRA PIN' }], sup, { click: 1, empty: 'No suppliers yet' }) + '</div>';
      $('[data-ns]', v).onclick = function () { K.editSupplier(null); };
      $$('[data-row]', v).forEach(function (tr) { tr.onclick = function () { K.editSupplier(sup.filter(function (s) { return s.id == tr.dataset.row; })[0]); }; });
      $$('[data-rc]', v).forEach(function (b) { b.onclick = function () { var po = pos.filter(function (p) { return p.id == b.dataset.rc; })[0]; var m = K.modal('Receive ' + esc(po.number), '<p>Stock goes up by the quantities on this order.</p>' + K.field('Amount paid to the supplier', 'paid', po.total, 'number') + K.field('Update product cost prices from this order', 'update_cost', 1, 'toggle'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Receive into stock</button>'); $('[data-go]', m).onclick = function () { K.api('purchases/' + po.id + '/receive', { body: K.read(m) }).then(function (r) { K.closeModal(); K.toast(r.message); productsCache = null; K.route(); }).catch(K.fail); }; }; });
      $('[data-np]', v).onclick = function () {
        if (!sup.length) { K.toast('Add a supplier first.', true); return; }
        K.products().then(function (ps) {
          var lines = [{ product_id: '', qty: 1, cost: '' }];
          var m = K.modal('New purchase order', K.field('Supplier', 'supplier_id', sup[0].id, 'select', { options: sup.map(function (s) { return [s.id, s.name]; }) }) + '<div class="grid2">' + K.field('Order date', 'date', K.today(), 'date') + K.field('Expected', 'expected', '', 'date') + '</div><div data-pl></div><button class="btn sm" data-al>' + ic(I.plus) + 'Add line</button>' + K.field('Prices include VAT', 'vat_included', 1, 'toggle') + K.field('Notes', 'notes', '', 'textarea') + '<div class="totbar"><span></span><span>Total <b data-pt>' + money(0) + '</b></span></div>', '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Create order</button>', true);
          var goods = ps.filter(function (p) { return p.kind === 'product'; });
          var draw = function () {
            $('[data-pl]', m).innerHTML = lines.map(function (l, i) { return '<div class="grid3" style="grid-template-columns:minmax(0,2fr) 90px 140px 40px;align-items:end"><div class="f"><label>Product</label><select data-lp="' + i + '"><option value="">Choose</option>' + goods.map(function (p) { return '<option value="' + p.id + '"' + (+l.product_id === +p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>'; }).join('') + '</select></div>' + '<div class="f"><label>Qty</label><input type="number" min="1" value="' + l.qty + '" data-lq="' + i + '"></div><div class="f"><label>Unit cost</label><input type="number" value="' + l.cost + '" data-lc="' + i + '"></div><button class="btn ghost sm" style="margin-bottom:12px" data-lx="' + i + '">' + ic(I.x) + '</button></div>'; }).join('');
            $('[data-pt]', m).textContent = money(lines.reduce(function (a, l) { return a + (+l.qty || 0) * (+l.cost || 0); }, 0));
          };
          draw();
          m.addEventListener('change', function (e) { var t = e.target; if (t.dataset.lp != null) { lines[t.dataset.lp].product_id = t.value; var p = goods.filter(function (x) { return +x.id === +t.value; })[0]; if (p && !lines[t.dataset.lp].cost) lines[t.dataset.lp].cost = p.cost || ''; draw(); } });
          m.addEventListener('input', function (e) { var t = e.target; if (t.dataset.lq != null) lines[t.dataset.lq].qty = t.value; if (t.dataset.lc != null) lines[t.dataset.lc].cost = t.value; $('[data-pt]', m).textContent = money(lines.reduce(function (a, l) { return a + (+l.qty || 0) * (+l.cost || 0); }, 0)); });
          m.addEventListener('click', function (e) { if (e.target.closest('[data-al]')) { lines.push({ product_id: '', qty: 1, cost: '' }); draw(); } var x = e.target.closest('[data-lx]'); if (x) { lines.splice(+x.dataset.lx, 1); draw(); } });
          $('[data-s]', m).onclick = function () { var f = K.read(m); f.lines = lines.filter(function (l) { return l.product_id; }); if (!f.lines.length) { K.toast('Add at least one product.', true); return; } K.api('purchases', { body: f }).then(function (r) { K.closeModal(); K.toast(r.number + ' created'); K.route(); }).catch(K.fail); };
        });
      };
    }).catch(K.fail);
  };
  K.editSupplier = function (s) {
    s = s || {};
    var m = K.modal(s.id ? esc(s.name) : 'New supplier', '<div class="grid2">' + K.field('Name', 'name', s.name, 'text') + K.field('Contact person', 'contact', s.contact, 'text') + '</div><div class="grid3">' + K.field('Phone', 'phone', K.phone(s.phone), 'tel') + K.field('Email', 'email', s.email, 'email') + K.field('KRA PIN', 'kra', s.kra, 'text') + '</div>' + K.field('Notes', 'notes', s.notes, 'textarea'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>');
    $('[data-s]', m).onclick = function () { K.api('suppliers' + (s.id ? '/' + s.id : ''), { body: K.read(m) }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); }).catch(K.fail); };
  };

  /* ================================================================ EXPENSES */
  var expState = { p: 'month' };
  K.V.expenses = function (v) {
    var pr = period(expState.p);
    K.api('expenses?from=' + pr[0] + '&to=' + pr[1]).then(function (rows) {
      var by = {}; rows.forEach(function (r) { by[r.category] = (by[r.category] || 0) + +r.amount; });
      var arr = Object.keys(by).map(function (k) { return [k, by[k], 'var(--sun)']; }).sort(function (a, b) { return b[1] - a[1]; });
      v.innerHTML = K.head('Expenses', 'Record a cost in seconds: amount, category, how it was paid and a photo of the receipt.', '<select data-p style="width:auto">' + [['month', 'This month'], ['last', 'Last month'], ['quarter', 'This quarter'], ['year', 'This year']].map(function (o) { return '<option value="' + o[0] + '"' + (expState.p === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select><button class="btn sun" data-ne>' + ic(I.plus) + 'Add expense</button>') +
        '<div class="cols"><div class="box">' + K.table([{ k: 'date', label: 'Date', f: function (r) { return K.ds(r.date); } }, { k: 'category', label: 'Category' }, { k: 'description', label: 'Description', f: function (r) { return esc(r.description) + (r.receipt ? ' <a href="' + esc(r.receipt) + '" target="_blank" rel="noopener" class="tiny">receipt</a>' : ''); } }, { k: 'method', label: 'Paid by' }, { k: 'amount', label: 'Amount', n: 1, f: function (r) { return money(r.amount); } }], rows, { click: 1, empty: 'No expenses in this period' }) + '</div>' +
        '<div class="box"><h3>Where the money went</h3><p class="sub">' + K.d(pr[0]) + ' to ' + K.d(pr[1]) + ' · ' + money(rows.reduce(function (a, r) { return a + +r.amount; }, 0)) + '</p>' + (arr.length ? K.bars(arr) : '<div class="empty">Nothing yet</div>') + '</div></div>';
      $('[data-p]', v).onchange = function (e) { expState.p = e.target.value; K.V.expenses(v); };
      $('[data-ne]', v).onclick = function () { K.editExpense(null); };
      $$('[data-row]', v).forEach(function (tr) { tr.onclick = function () { K.editExpense(rows.filter(function (r) { return r.id == tr.dataset.row; })[0]); }; });
    }).catch(K.fail);
  };
  K.editExpense = function (x) {
    x = x || { date: K.today(), method: 'M-Pesa', category: (K.me.settings.expense_cats || [])[0] };
    var receipt = x.receipt || '';
    var m = K.modal(x.id ? 'Edit expense' : 'Add expense', '<div class="grid2">' + K.field('Amount', 'amount', x.amount || '', 'number') + K.field('Date', 'date', x.date, 'date') + '</div><div class="grid2">' + K.field('Category', 'category', x.category, 'select', { options: opts(K.me.settings.expense_cats || ['Other']) }) + K.field('Paid by', 'method', x.method, 'select', { options: opts(['M-Pesa', 'Bank', 'Cash', 'Card', 'Cheque']) }) + '</div>' + K.field('Description', 'description', x.description, 'text') + '<div class="grid2">' + K.field('Reference or M-Pesa code', 'reference', x.reference, 'text') + K.field('VAT included (for the VAT return)', 'vat', x.vat || '', 'number', { hint: 'Only when you have a VAT receipt' }) + '</div><div class="flabel">Receipt photo</div><div class="imgs" data-r></div>',
      (x.id ? '<button class="btn danger" data-del>Delete</button>' : '') + '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>');
    var dr = function () { $('[data-r]', m).innerHTML = receipt ? '<div class="im"><img src="' + esc(receipt) + '" alt=""><button type="button" data-rr>✕</button></div>' : '<button type="button" class="add" data-ar>+ Add photo</button>'; };
    dr();
    m.addEventListener('click', function (e) { if (e.target.closest('[data-ar]')) K.pickImage().then(function (u) { receipt = Array.isArray(u) ? u[0] : u; dr(); }); if (e.target.closest('[data-rr]')) { receipt = ''; dr(); } });
    $('[data-s]', m).onclick = function () { var f = K.read(m); f.receipt = receipt; if (!+f.amount) { K.toast('Enter the amount.', true); return; } K.api('expenses' + (x.id ? '/' + x.id : ''), { body: f }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); }).catch(K.fail); };
    var del = $('[data-del]', m); if (del) del.onclick = function () { K.confirm('Delete this expense?', 'Delete', true).then(function (y) { if (y) K.api('expenses/' + x.id, { method: 'DELETE' }).then(function () { K.closeModal(); K.route(); }); }); };
  };

  /* ================================================================ REPORTS */
  var repState = { type: 'pl', p: 'month', from: '', to: '' };
  var CAT = { pl: 'Profit and loss', cashflow: 'Cash flow', sales: 'Sales by product and category', customers: 'Sales by customer', receivables: 'Money owed', vat: 'VAT summary', stock: 'Stock valuation', payments: 'Payments received', expenses: 'Expenses', jobs: 'Installations' };
  K.V.reports = function (v) {
    var pr = repState.p === 'custom' ? [repState.from || K.today(), repState.to || K.today()] : period(repState.p);
    v.innerHTML = K.head('Reports', 'Every report downloads as Excel or PDF, ready for your accountant or KRA.', '') +
      '<div class="box"><div class="toolbar"><select data-t style="width:auto">' + Object.keys(CAT).map(function (k) { return '<option value="' + k + '"' + (repState.type === k ? ' selected' : '') + '>' + CAT[k] + '</option>'; }).join('') + '</select><select data-p style="width:auto">' + [['month', 'This month'], ['last', 'Last month'], ['30', 'Last 30 days'], ['quarter', 'This quarter'], ['year', 'This year'], ['custom', 'Custom dates']].map(function (o) { return '<option value="' + o[0] + '"' + (repState.p === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' + (repState.p === 'custom' ? '<input type="date" data-f value="' + pr[0] + '" style="width:auto"><input type="date" data-to value="' + pr[1] + '" style="width:auto">' : '') +
      '<span style="margin-left:auto" class="acts"><button class="btn" data-x="xlsx">' + ic(I.dl) + 'Excel</button><button class="btn" data-x="pdf">' + ic(I.dl) + 'PDF</button><button class="btn" data-x="csv">CSV</button></span></div><div data-r><div class="boot" style="height:30vh"><div class="spin"></div></div></div></div>' +
      '<div class="cols even"><div class="box" data-sched></div><div class="box" data-act></div></div>';
    $('[data-t]', v).onchange = function (e) { repState.type = e.target.value; K.V.reports(v); };
    $('[data-p]', v).onchange = function (e) { repState.p = e.target.value; K.V.reports(v); };
    var f = $('[data-f]', v), t = $('[data-to]', v);
    if (f) f.onchange = t.onchange = function () { repState.from = f.value; repState.to = t.value; K.V.reports(v); };
    $$('[data-x]', v).forEach(function (b) { b.onclick = function () { location.href = K.url('reports/' + repState.type + '?from=' + pr[0] + '&to=' + pr[1] + '&format=' + b.dataset.x); }; });
    K.api('reports/' + repState.type + '?from=' + pr[0] + '&to=' + pr[1]).then(function (r) {
      var h = '<h2 style="font-size:24px;margin:6px 0 2px">' + esc(r.title) + '</h2><p class="tiny" style="margin-bottom:12px">' + esc(r.period) + '</p>';
      if (r.kpis.length) h += '<div class="kpis">' + r.kpis.map(function (k) { return '<div class="kpi"><small>' + esc(k[0]) + '</small><b>' + esc(k[1]) + '</b></div>'; }).join('') + '</div>';
      if (r.chart && r.chart.length && r.chart[0].in != null) h += '<div class="fig" data-ch></div><div class="legend"><span><i style="background:var(--brand)"></i>Money in</span><span><i style="background:var(--sun)"></i>Money out</span></div>';
      else if (r.chart && r.chart.length) h += '<div style="margin-bottom:14px">' + K.bars(r.chart.slice(0, 8).map(function (c) { return [c.label, c.value]; })) + '</div>';
      r.sections.forEach(function (s) {
        h += '<h3 style="margin:16px 0 6px">' + esc(s.title) + '</h3><div class="scroll"><table><thead><tr>' + s.head.map(function (x, i) { return '<th' + (i ? ' class="n"' : '') + '>' + esc(x) + '</th>'; }).join('') + '</tr></thead><tbody>' + s.rows.map(function (row) { var b = row._b; var cells = Object.keys(row).filter(function (k) { return k !== '_b'; }).map(function (k) { return row[k]; }); return '<tr' + (b ? ' style="font-weight:700;border-top:2px solid var(--ink)"' : '') + '>' + cells.map(function (c, i) { var num = i && typeof c === 'number'; return '<td' + (i && (num || !isNaN(+c) && c !== '' && c !== null) ? ' class="n"' : '') + '>' + (num ? (c < 0 ? '(' + money(-c, 1) + ')' : money(c, 1)) : esc(c)) + '</td>'; }).join('') + '</tr>'; }).join('') + (s.rows.length ? '' : '<tr><td class="tiny" colspan="' + s.head.length + '">Nothing in this period</td></tr>') + '</tbody></table></div>';
      });
      $('[data-r]', v).innerHTML = h;
      var ch = $('[data-ch]', v); if (ch) K.barChart(ch, r.chart, ['in', 'out'], ['var(--brand)', 'var(--sun)']);
    }).catch(function (e) { $('[data-r]', v).innerHTML = '<div class="empty">' + esc(e.message) + '</div>'; });
    // scheduled reports
    var rs = K.me.settings.reports || {};
    var defs = [['daily', 'Daily summary', 'Every morning at 7:00: yesterday\'s sales, payments and what needs attention'], ['weekly', 'Weekly sales report', 'Mondays at 7:00, with the Excel file attached'], ['monthly', 'Month-end pack', '1st of the month: profit and loss, VAT summary and money owed'], ['lowstock', 'Low stock alert', 'Every morning when items reach their reorder level'], ['overdue', 'Payment reminders to customers', 'Emails the invoice 3 days after it falls due'], ['licences', 'Licence renewal reminders', '60, 30 and 7 days before expiry'], ['schedule', 'Schedule reminders to the team', 'At 18:00, the next day\'s jobs']];
    var sh = $('[data-sched]', v);
    if (K.can('settings')) {
      sh.innerHTML = '<h3>Scheduled reports and reminders</h3><p class="sub">Sent by email automatically. Leave "To" blank to use ' + esc(K.me.settings.notify_email) + '.</p>' + defs.map(function (d) { var x = rs[d[0]] || {}; return '<div class="tog"><span><b>' + d[1] + '</b><br><small class="tiny">' + d[2] + '</small><input placeholder="To (optional, comma-separated)" value="' + esc(x.to || '') + '" data-rt="' + d[0] + '" style="margin-top:6px;min-height:34px;font-size:14px"></span><input type="checkbox" class="sw" data-ro="' + d[0] + '"' + (+x.on ? ' checked' : '') + '></div>'; }).join('') + '<button class="btn pri" data-rsave style="margin-top:10px">Save schedule</button>';
      $('[data-rsave]', sh).onclick = function () { var out = {}; defs.forEach(function (d) { out[d[0]] = { on: $('[data-ro="' + d[0] + '"]', sh).checked ? 1 : 0, to: $('[data-rt="' + d[0] + '"]', sh).value }; }); K.api('settings', { body: { reports: out } }).then(function (s) { K.me.settings = s; K.toast('Schedule saved'); }).catch(K.fail); };
      K.api('activity').then(function (a) { $('[data-act]', v).innerHTML = '<h3>Activity log</h3><p class="sub">Who did what, most recent first</p><ul class="list">' + a.slice(0, 25).map(function (x) { return '<li><span class="tiny">' + K.ds(x.created) + ' ' + K.t(x.created) + '</span><span>' + esc(x.action) + '</span><small>' + esc(x.user || 'System') + '</small></li>'; }).join('') + '</ul>'; });
    } else { sh.remove(); $('[data-act]', v).remove(); }
  };

  /* ================================================================ COMPLIANCE */
  K.V.compliance = function (v) {
    var pr = period('month');
    Promise.all([K.api('docs?type=invoice'), K.api('licences'), K.api('reports/vat?from=' + pr[0] + '&to=' + pr[1])]).then(function (res) {
      var inv = res[0].filter(function (d) { return d.state !== 'void'; }), lic = res[1], vat = res[2];
      var pend = inv.filter(function (d) { return d.etims_status === 'pending'; });
      var t = K.today(), y = +t.slice(0, 4), m = +t.slice(5, 7), d = +t.slice(8, 10);
      var next = function (day) { var mm = d > day ? m + 1 : m, yy = y; if (mm > 12) { mm = 1; yy++; } return yy + '-' + (mm < 10 ? '0' : '') + mm + '-' + (day < 10 ? '0' : '') + day; };
      var cal = [[next(9), 'PAYE, NSSF, SHIF and Housing Levy for last month'], [next(20), 'VAT return for last month on iTax']].concat(lic.map(function (l) { return [l.expires, 'Renew: ' + l.name + (l.holder ? ' (' + l.holder + ')' : '')]; })).filter(function (c) { return c[0] >= t; }).sort(function (a, b) { return a[0] < b[0] ? -1 : 1; }).slice(0, 8);
      var et = K.me.settings.etims || {};
      v.innerHTML = K.head('Tax and compliance', 'KRA eTIMS, VAT and the licences the business depends on, in one place.', '<button class="btn sun" data-nl>' + ic(I.plus) + 'Add licence or permit</button>') +
        '<div class="kpis"><div class="kpi"><small>Invoices waiting for eTIMS</small><b>' + pend.length + '</b><i class="' + (pend.length ? 'down' : 'up') + '">' + (pend.length ? 'sign and record the number' : 'all signed') + '</i></div>' + vat.kpis.map(function (k) { return '<div class="kpi"><small>' + esc(k[0]) + ' (this month)</small><b>' + esc(k[1]) + '</b></div>'; }).join('') + '</div>' +
        (et.mode === 'off' ? '<div class="notice info">Sign each invoice on KRA eTIMS, then type its eTIMS number on the invoice here. Sending invoices to KRA automatically can be switched on later, once KRA approves it.</div>' : '') +
        '<div class="cols even"><div class="box"><h3>Invoices to sign on eTIMS</h3>' + (pend.length ? '<ul class="list">' + pend.map(function (p) { return '<li><b class="mono">' + esc(p.number) + '</b><span>' + esc(p.customer_name) + ' · ' + money(p.total) + '</span><span class="acts"><input placeholder="eTIMS number" data-en="' + p.id + '" style="width:150px;min-height:34px"><button class="btn sm" data-es="' + p.id + '">Save</button></span></li>'; }).join('') + '</ul>' : '<div class="empty"><b>All signed</b>Nothing waiting.</div>') + '</div>' +
        '<div class="box"><h3>Coming up</h3><ul class="sched">' + cal.map(function (c) { return '<li><time style="font-size:16px">' + K.ds(c[0]) + '<small>' + c[0].slice(0, 4) + '</small></time><span>' + esc(c[1]) + '</span><span></span></li>'; }).join('') + '</ul></div></div>' +
        '<div class="box"><h3>Licences and permits</h3><p class="sub">Reminders go out 60, 30 and 7 days before expiry.</p>' + K.table([{ k: 'name', label: 'Licence or permit', f: function (l) { return '<b>' + esc(l.name) + '</b>'; } }, { k: 'holder', label: 'Holder' }, { k: 'number', label: 'Number' }, { k: 'expires', label: 'Expires', f: function (l) { var days = Math.round((new Date(l.expires) - new Date(t)) / 864e5); return K.d(l.expires) + ' ' + K.badge(days < 0 ? 'overdue' : days <= 60 ? 'due' : 'ok', days < 0 ? 'Expired' : days <= 60 ? days + ' days' : 'Valid'); } }], lic, { click: 1, empty: 'No licences recorded', emptySub: 'Add EPRA licences, county permits and registrations.' }) + '</div>';
      $('[data-nl]', v).onclick = function () { K.editLicence(null); };
      $$('[data-row]', v).forEach(function (tr) { tr.onclick = function () { K.editLicence(lic.filter(function (l) { return l.id == tr.dataset.row; })[0]); }; });
      $$('[data-es]', v).forEach(function (b) { b.onclick = function () { var val = $('[data-en="' + b.dataset.es + '"]', v).value; if (!val) return; K.api('docs/' + b.dataset.es + '/action', { body: { action: 'etims', ref: val } }).then(function () { K.toast('Saved'); K.refreshBadges(); K.route(); }).catch(K.fail); }; });
    }).catch(K.fail);
  };
  K.editLicence = function (l) {
    l = l || {};
    var m = K.modal(l.id ? esc(l.name) : 'Add licence or permit', K.field('Name', 'name', l.name, 'text', { ph: 'e.g. EPRA solar PV licence (T2)' }) + '<div class="grid2">' + K.field('Holder', 'holder', l.holder, 'text') + K.field('Number', 'number', l.number, 'text') + '</div>' + K.field('Expires', 'expires', l.expires, 'date') + K.field('Notes', 'notes', l.notes, 'textarea'), (l.id ? '<button class="btn danger" data-del>Delete</button>' : '') + '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>Save</button>');
    $('[data-s]', m).onclick = function () { K.api('licences' + (l.id ? '/' + l.id : ''), { body: K.read(m) }).then(function () { K.closeModal(); K.toast('Saved'); K.route(); }).catch(K.fail); };
    var del = $('[data-del]', m); if (del) del.onclick = function () { K.api('licences/' + l.id, { method: 'DELETE' }).then(function () { K.closeModal(); K.route(); }); };
  };

  /* ================================================================ TEAM */
  K.V.users = function (v) {
    K.api('users').then(function (r) {
      v.innerHTML = K.head('Team and access', 'Give each person their own sign-in. They see only the parts of the Desk their role allows.', '<button class="btn sun" data-nu>' + ic(I.plus) + 'Add team member</button>') +
        '<div class="box">' + K.table([{ k: 'name', label: 'Name', f: function (u) { return '<b>' + esc(u.name) + '</b>' + (u.disabled ? ' ' + K.badge('void', 'Switched off') : ''); } }, { k: 'label', label: 'Role' }, { k: 'email', label: 'Email' }, { k: 'phone', label: 'Phone' }, { k: 'last', label: 'Last seen' }], r.users, { click: 1 }) + '</div>' +
        '<div class="box"><h3>What each role can open</h3><div class="scroll"><table class="matrix"><thead><tr><th>Area</th>' + r.roles.map(function (x) { return '<th>' + esc(x.label) + '</th>'; }).join('') + '</tr></thead><tbody>' + r.modules.map(function (m) { return '<tr><td>' + esc(m.label) + '</td>' + r.roles.map(function (x) { return '<td>' + (x.caps.indexOf(m.cap) > -1 ? '<span class="yes">✓</span>' : '<span class="no">—</span>') + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div></div>';
      var edit = function (u) {
        u = u || { role: 'soko_sales' };
        if (u.role === 'administrator') { K.toast('This is the web team support account and cannot be changed here.', true); return; }
        var m = K.modal(u.id ? esc(u.name) : 'Add team member', '<div class="grid2">' + K.field('Full name', 'name', u.name, 'text') + K.field('Email', 'email', u.email, 'email') + '</div><div class="grid2">' + K.field('Role', 'role', u.role, 'select', { options: r.roles.map(function (x) { return [x.id, x.label]; }) }) + K.field('Phone', 'phone', u.phone, 'tel') + '</div>' + (u.id ? K.field('Switch off this account', 'disabled', u.disabled ? 1 : 0, 'toggle', { hint: 'They can no longer sign in. Their records stay.' }) + K.field('Email them a password reset link', 'reset', 0, 'toggle') : K.field('Password (optional)', 'password', '', 'text', { hint: 'Leave blank to create one. It is emailed to them and shown once here.' })), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-s>' + (u.id ? 'Save' : 'Create account') + '</button>');
        $('[data-s]', m).onclick = function () { K.api('users' + (u.id ? '/' + u.id : ''), { body: K.read(m) }).then(function (res) { K.closeModal(); if (res.password) K.modal('Account created', '<p>Share these details with ' + esc(K.read(m).name || 'them') + ' if the email does not arrive.</p><div class="codebox">Sign in: ' + esc(D.site) + 'desk/<br>Email: ' + esc(res.login) + '<br>Password: ' + esc(res.password) + '</div>', '<button class="btn pri" data-mx>Done</button>'); else K.toast(res.message); K.route(); }).catch(K.fail); };
      };
      $('[data-nu]', v).onclick = function () { edit(null); };
      $$('[data-row]', v).forEach(function (tr) { tr.onclick = function () { edit(r.users.filter(function (u) { return u.id == tr.dataset.row; })[0]); }; });
    }).catch(K.fail);
  };

  /* ================================================================ SETTINGS */
  K.V.settings = function (v, arg) {
    var tab = arg || 'business', s = K.me.settings;
    v.innerHTML = K.head('Settings', 'Business details, payments, documents, shop options and system tools.', '') + K.tabs('set', [['business', 'Business'], ['payments', 'Payments'], ['documents', 'Documents and tax'], ['shop', 'Shop'], ['lists', 'Lists'], ['system', 'System']], tab) + '<div data-b class="box"></div>';
    $$('[data-tabs] button', v).forEach(function (b) { b.onclick = function () { location.hash = 'settings/' + b.dataset.t; }; });
    var b = $('[data-b]', v), save = function (data) { return K.api('settings', { body: data }).then(function (n) { K.me.settings = n; K.toast('Saved'); return n; }).catch(K.fail); };
    if (tab === 'business') {
      b.innerHTML = '<div class="grid2">' + K.field('Business name', 'business', s.business) + K.field('Tagline', 'tagline', s.tagline) + '</div><div class="grid3">' + K.field('Phone', 'phone', s.phone) + K.field('Second phone', 'phone2', s.phone2) + K.field('WhatsApp number', 'whatsapp', s.whatsapp, 'text', { hint: 'International format, e.g. 254712936685' }) + '</div><div class="grid2">' + K.field('Email', 'email', s.email) + K.field('Send alerts and new orders to', 'notify_email', s.notify_email) + '</div>' + K.field('Address', 'address', s.address) + '<div class="grid2">' + K.field('Opening hours', 'hours', s.hours) + K.field('KRA PIN', 'kra_pin', s.kra_pin) + '</div><button class="btn pri" data-s>Save</button>';
      $('[data-s]', b).onclick = function () { save(K.read(b)); };
    }
    if (tab === 'payments') {
      var mp = s.mpesa || {}, cb = K.me.callbacks || {};
      b.innerHTML = '<h3>How customers pay you</h3><p class="sub">Printed on every invoice and shown at checkout. The invoice number is the account number, so each payment finds its invoice.</p>' +
        '<div class="grid3">' + K.field('Customers pay to', 'type', mp.type, 'select', { options: [['paybill', 'Paybill'], ['till', 'Buy Goods Till']] }) + K.field('Paybill number', 'shortcode', mp.shortcode, 'text', { hint: 'For a Till, the head office number' }) + K.field('Till number', 'till', mp.till, 'text', { hint: 'Only if customers pay to a Till' }) + '</div>' +
        '<h3 style="margin-top:22px">Payments match to invoices on their own</h3><p class="sub">Two free ways. Use either or both.</p>' +
        '<div class="box"><b>1. Forward M-Pesa messages from the business phone</b><ol class="sub" style="margin:8px 0 10px;padding-left:18px"><li>On the Android phone that receives the M-Pesa messages, install a free SMS forwarding app, such as "SMS Forwarder".</li><li>Set it to forward messages from <b>MPESA</b> to the address below.</li><li>Send a small test payment. It shows in Payments within seconds.</li></ol><div class="codebox" data-smsurl>Loading…</div><p class="tiny" data-smslast></p><button class="btn sm" data-copysms>Copy address</button></div>' +
        '<div class="box"><b>2. Upload your M-Pesa statement</b><p class="sub" style="margin-top:6px">Download your statement from the M-Pesa business portal as an Excel (CSV) file, then choose <b>Import M-Pesa statement</b> on the Payments page. Every payment is matched in one go, and none is counted twice.</p></div>' +
        '<h3 style="margin-top:22px">M-Pesa prompt at checkout</h3><p class="sub">Customers get a payment request on their phone when they order, and the invoice is marked paid the moment they enter their PIN. This needs a free Safaricom developer account, which your web team can set up with you.</p>' +
        K.field('Send M-Pesa prompts at checkout', 'stk', mp.stk, 'toggle') +
        '<details class="tech"><summary>Safaricom connection details (for your web team)</summary>' +
        '<ol class="steps-mini"><li>Create an account at <a href="https://developer.safaricom.co.ke" target="_blank" rel="noopener">developer.safaricom.co.ke</a>.</li><li>Choose Go Live, enter the Paybill or Till and confirm with the code sent to the M-Pesa admin phone.</li><li>Paste the Consumer Key, Consumer Secret and Passkey below, save, test, then register payment notifications.</li></ol>' +
        '<div class="grid2">' + K.field('Mode', 'env', mp.env, 'select', { options: [['sandbox', 'Testing'], ['live', 'Live']] }) + '<div></div></div>' +
        '<div class="grid3">' + K.field('Consumer Key', 'key', mp.key) + K.field('Consumer Secret', 'secret', mp.secret) + K.field('Passkey', 'passkey', mp.passkey) + '</div>' +
        '<p class="tiny">Payment notification addresses:</p><div class="codebox">' + esc(cb.confirm || '') + '</div><div class="codebox" style="margin-top:6px">' + esc(cb.stk || '') + '</div>' +
        '<div class="acts" style="margin-top:10px"><button class="btn" data-test>Test connection</button><button class="btn" data-reg>Register payment notifications</button></div></details>' +
        '<div class="acts" style="margin-top:16px"><button class="btn pri" data-s>Save</button></div>';
      $('[data-s]', b).onclick = function () { var f = K.read(b); save({ mpesa: f }).then(function () { K.api('me').then(function (me) { K.me = me; }); }); };
      $('[data-test]', b).onclick = function (e) { e.target.disabled = true; K.api('mpesa/test', { body: {} }).then(function (r) { K.toast(r.message, !r.ok); e.target.disabled = false; }).catch(function (er) { e.target.disabled = false; K.fail(er); }); };
      K.api('payments/sms-info').then(function (r) { $('[data-smsurl]', b).textContent = r.url; $('[data-smslast]', b).textContent = r.last ? 'Last SMS received ' + r.last : 'No forwarded SMS yet.'; $('[data-copysms]', b).onclick = function () { try { navigator.clipboard.writeText(r.url); K.toast('Copied'); } catch (e) { K.toast(r.url); } }; }).catch(function () {});
      $('[data-reg]', b).onclick = function () { K.api('mpesa/register', { body: {} }).then(function (r) { K.toast(r.message, !r.ok); }).catch(K.fail); };
    }
    if (tab === 'documents') {
      var et = s.etims || {};
      b.innerHTML = '<div class="grid3">' + K.field('VAT registered', 'vat_registered', s.vat_registered, 'select', { options: [[1, 'Yes'], [0, 'No']] }) + K.field('VAT rate %', 'vat_rate', s.vat_rate, 'number') + K.field('Prices include VAT', 'prices_incl_vat', s.prices_incl_vat, 'select', { options: [[1, 'Yes'], [0, 'No, add VAT on top']] }) + '</div>' +
        '<div class="grid3">' + K.field('Quotation prefix', 'prefix_quote', s.prefix_quote) + K.field('Invoice prefix', 'prefix_invoice', s.prefix_invoice) + K.field('Receipt prefix', 'prefix_receipt', s.prefix_receipt) + '</div>' +
        '<div class="grid3">' + K.field('Next quotation number', 'next_quote', s.next_quote, 'number') + K.field('Next invoice number', 'next_invoice', s.next_invoice, 'number') + K.field('Next receipt number', 'next_receipt', s.next_receipt, 'number') + '</div>' +
        '<div class="grid3">' + K.field('Quotations valid for (days)', 'quote_valid', s.quote_valid, 'number') + K.field('Invoices due in (days)', 'invoice_due', s.invoice_due, 'number') + K.field('Deposit to start work %', 'deposit_percent', s.deposit_percent, 'number') + '</div>' +
        K.field('Footer note on documents', 'invoice_note', s.invoice_note, 'textarea') + K.field('Bank details (printed on invoices)', 'bank_details', s.bank_details, 'textarea', { ph: 'Bank, branch, account name and number' }) +
        '<h3 style="margin-top:10px">KRA eTIMS</h3><div class="grid3">' + K.field('Mode', 'et_mode', et.mode, 'select', { options: [['off', 'Type eTIMS numbers myself'], ['oscu', 'Send to KRA automatically (after KRA approval)']] }) + K.field('Branch ID', 'et_branch', et.branch) + K.field('Device serial', 'et_serial', et.serial) + '</div><button class="btn pri" data-s>Save</button>';
      $('[data-s]', b).onclick = function () { var f = K.read(b); f.etims = { mode: f.et_mode, branch: f.et_branch, serial: f.et_serial, env: et.env, pin: et.pin, cmc: et.cmc }; delete f.et_mode; delete f.et_branch; delete f.et_serial; save(f); };
    }
    if (tab === 'shop') {
      var del = (s.delivery || []).slice();
      var dr = function () { $('[data-del]', b).innerHTML = del.map(function (x, i) { return '<div class="grid3" style="grid-template-columns:minmax(0,1.2fr) minmax(0,1fr) 120px 40px;align-items:end"><div class="f"><label>Option</label><input value="' + esc(x.label) + '" data-dl="' + i + '"></div><div class="f"><label>Note</label><input value="' + esc(x.note) + '" data-dn="' + i + '"></div><div class="f"><label>Fee</label><input type="number" value="' + esc(x.fee) + '" data-df="' + i + '"></div><button class="btn ghost sm" style="margin-bottom:12px" data-dx="' + i + '">' + ic(I.x) + '</button></div>'; }).join(''); };
      b.innerHTML = '<h3>Delivery options at checkout</h3><div data-del></div><button class="btn sm" data-da>' + ic(I.plus) + 'Add option</button><div style="height:14px"></div>' + K.field('Installation estimate when a product has no fee (% of price)', 'install_rate', s.install_rate, 'number') + '<button class="btn pri" data-s>Save</button>';
      dr();
      b.addEventListener('input', function (e) { var t = e.target; if (t.dataset.dl != null) del[t.dataset.dl].label = t.value; if (t.dataset.dn != null) del[t.dataset.dn].note = t.value; if (t.dataset.df != null) del[t.dataset.df].fee = +t.value; });
      b.addEventListener('click', function (e) { if (e.target.closest('[data-da]')) { del.push({ label: '', note: '', fee: 0 }); dr(); } var x = e.target.closest('[data-dx]'); if (x) { del.splice(+x.dataset.dx, 1); dr(); } });
      $('[data-s]', b).onclick = function () { save({ delivery: del.filter(function (d) { return d.label; }), install_rate: +K.read(b).install_rate }); };
    }
    if (tab === 'lists') {
      var lists = [['expense_cats', 'Expense categories'], ['lead_stages', 'Lead stages'], ['job_stages', 'Installation stages'], ['checklist', 'Installation checklist']];
      b.innerHTML = '<p class="sub">One item per line.</p><div class="grid2">' + lists.map(function (l) { return K.field(l[1], l[0], (s[l[0]] || []).join('\n'), 'textarea'); }).join('') + '</div><button class="btn pri" data-s>Save</button>';
      $('[data-s]', b).onclick = function () { var f = K.read(b), out = {}; lists.forEach(function (l) { out[l[0]] = f[l[0]].split('\n').map(function (x) { return x.trim(); }).filter(Boolean); }); save(out); };
    }
    if (tab === 'system') {
      b.innerHTML = '<h3>Email sending</h3><p class="sub">Receipts, invoices and reports go out from this mailbox, for example info@dynamicsmartsystems.com. Your email provider gives you these details.</p><div class="grid2">' + K.field('Mailbox address', 'user', (s.smtp || {}).user || '') + K.field('Mailbox password', 'pass', (s.smtp || {}).pass || '', 'password') + '</div>' +
        '<details class="tech"><summary>Mail server details (for your web team)</summary><div class="grid3">' + K.field('Outgoing mail server', 'host', (s.smtp || {}).host || '', 'text', { ph: 'e.g. mail.dynamicsmartsystems.com' }) + K.field('Port', 'port', (s.smtp || {}).port || 587, 'number') + K.field('Encryption', 'secure', (s.smtp || {}).secure || 'tls', 'select', { options: [['tls', 'Standard (port 587)'], ['ssl', 'Secure (port 465)'], ['none', 'None']] }) + '</div></details>' +
        '<div class="acts"><button class="btn pri" data-sm>Save</button><button class="btn" data-te>Send a test email</button></div>' +
        '<h3 style="margin-top:22px">Sample data</h3><p class="sub">Load sample customers, invoices, payments, jobs and expenses to explore the Desk. Remove them, together with any test orders, in one click before going live. Your products and website are not touched.</p><div class="acts"><button class="btn" data-dl>Load sample data</button><button class="btn danger" data-dr>Remove sample data</button></div>' +
        (K.me.admin ? '<h3 style="margin-top:22px">Software</h3><p class="sub">Version ' + esc(K.me.version) + '. Updates are applied automatically.</p><div class="acts"><button class="btn" data-up>Check for updates</button></div>' : '');
      if ($('[data-up]', b)) $('[data-up]', b).onclick = function (e) { e.target.disabled = true; e.target.textContent = 'Updating…'; K.api('system/update', { body: {} }).then(function (r) { K.toast(r.ok ? 'Updated to version ' + r.now + '. Reloading…' : 'You already have the latest version, or the update could not finish. Please try again later.', !r.ok); if (r.ok) setTimeout(function () { location.reload(); }, 1200); else e.target.disabled = false; }).catch(function (er) { e.target.disabled = false; K.fail(er); }); };
      $('[data-sm]', b).onclick = function () { var f = K.read(b); save({ smtp: { host: f.host, port: +f.port, user: f.user, pass: f.pass, secure: f.secure } }); };
      $('[data-te]', b).onclick = function () { K.api('system/test-email', { body: {} }).then(function (r) { K.toast(r.message, !r.ok); }).catch(K.fail); };
      $('[data-dl]', b).onclick = function () { K.confirm('Load sample data? Any existing sample data is replaced.', 'Load').then(function (y) { if (y) K.api('system/demo', { body: {} }).then(function (r) { K.toast(r.message); K.refreshBadges(); }).catch(K.fail); }); };
      $('[data-dr]', b).onclick = function () { K.confirm('Remove all sample data? Real records stay.', 'Remove', true).then(function (y) { if (y) K.api('system/demo', { body: { remove: 1 } }).then(function (r) { K.toast(r.message); K.refreshBadges(); }).catch(K.fail); }); };
    }
  };
})();

/* Blog */
(function () {
  var $ = K.$, esc = K.esc;
  K.V.blog = function (v) {
    v.innerHTML = K.head('Blog', 'Buying guides and news for your customers. Write an article here, add a cover photo and publish it to the website.', '<button class="btn sun" data-new>' + K.ic(K.ICONS.plus) + 'New article</button>') + '<div class="box" data-list><div class="empty">Loading…</div></div>';
    var load = function () {
      K.api('blog').then(function (rows) {
        var l = $('[data-list]', v);
        if (!rows.length) { l.innerHTML = '<div class="empty">No articles yet. Write your first one.</div>'; return; }
        l.innerHTML = K.table([
          { label: 'Article', f: function (r) { return '<div class="blrow">' + (r.image ? '<img src="' + esc(r.image) + '" alt="">' : '<span class="blph"></span>') + '<div><b>' + esc(r.title) + '</b><br><span class="tiny">' + esc(String(r.excerpt || '').slice(0, 90)) + '</span></div></div>'; } },
          { label: 'Topic', k: 'topic' }, { label: 'Date', f: function (r) { return K.d(String(r.date).slice(0, 10)); } },
          { label: 'Status', f: function (r) { return r.status === 'publish' ? K.badge('paid', 'Published') : K.badge('draft', 'Draft'); } },
          { label: '', f: function (r) { return '<button class="btn sm" data-ed="' + r.id + '">Edit</button> ' + (r.status === 'publish' ? '<a class="btn sm ghost" target="_blank" href="' + esc(r.url) + '">View</a>' : ''); } }
        ], rows);
      }).catch(K.fail);
    };
    var edit = function (p) {
      p = p || { title: '', topic: '', excerpt: '', content: '', image: '', status: 'draft' };
      var d = K.drawer(p.id ? 'Edit article' : 'New article',
        K.field('Title', 'title', p.title, 'text', { req: 1 }) +
        '<div class="grid2">' + K.field('Topic', 'topic', p.topic, 'text', { ph: 'e.g. Buying guide' }) + '<div class="f"><label>Cover photo</label><div class="row"><img data-img src="' + esc(p.image || '') + '" alt="" style="width:96px;height:64px;object-fit:cover;border-radius:8px;background:#eef1f6' + (p.image ? '' : ';display:none') + '"><button type="button" class="btn sm" data-pick>Choose photo</button></div></div></div>' +
        K.field('Short summary (shown on the blog page)', 'excerpt', p.excerpt, 'textarea') +
        '<div class="f"><label>Article</label><div class="row" style="gap:6px;margin-bottom:6px"><button type="button" class="btn sm" data-fmt="h2">Heading</button><button type="button" class="btn sm" data-fmt="b">Bold</button><button type="button" class="btn sm" data-fmt="li">List item</button><button type="button" class="btn sm" data-fmt="a">Link</button></div><textarea name="content" style="min-height:340px">' + esc(p.content) + '</textarea><span class="hint">Leave an empty line between paragraphs.</span></div>',
        '<button class="btn" data-draft>Save draft</button><button class="btn pri" data-pub>' + (p.status === 'publish' ? 'Update' : 'Publish') + '</button>' + (p.id ? '<button class="btn danger" data-del style="margin-right:auto">Delete</button>' : ''));
      var img = p.image || '';
      $('[data-pick]', d).onclick = function () { K.pickImage().then(function (u) { if (u) { img = u; var i = $('[data-img]', d); i.src = u; i.style.display = ''; } }); };
      var ta = $('textarea[name=content]', d);
      d.addEventListener('click', function (e) {
        var b = e.target.closest('[data-fmt]'); if (!b) return;
        var s = ta.selectionStart, en = ta.selectionEnd, sel = ta.value.slice(s, en) || 'text', t = b.dataset.fmt, out;
        if (t === 'h2') out = '\n<h2>' + sel + '</h2>\n'; else if (t === 'b') out = '<strong>' + sel + '</strong>'; else if (t === 'li') out = '<ul><li>' + sel + '</li></ul>'; else { var u = prompt('Link address'); if (!u) return; out = '<a href="' + u + '">' + sel + '</a>'; }
        ta.setRangeText(out, s, en, 'end'); ta.focus();
      });
      var save = function (status) {
        var f = K.read(d); if (!f.title) { K.toast('Add a title', true); return; }
        K.api('blog', { body: { id: p.id, title: f.title, topic: f.topic, excerpt: f.excerpt, content: f.content, image: img, status: status } }).then(function () { K.toast(status === 'publish' ? 'Published' : 'Draft saved'); K.closeDrawer(); load(); }).catch(K.fail);
      };
      $('[data-draft]', d).onclick = function () { save('draft'); };
      $('[data-pub]', d).onclick = function () { save('publish'); };
      if (p.id) $('[data-del]', d).onclick = function () { K.confirm('Delete this article?', 'Delete', true).then(function (y) { if (y) K.api('blog/' + p.id, { method: 'DELETE' }).then(function () { K.toast('Deleted'); K.closeDrawer(); load(); }).catch(K.fail); }); };
    };
    $('[data-new]', v).onclick = function () { edit(); };
    v.addEventListener('click', function (e) { var b = e.target.closest('[data-ed]'); if (b) K.api('blog/' + b.dataset.ed).then(edit).catch(K.fail); });
    load();
  };
})();
