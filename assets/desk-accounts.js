/* Dynamic Desk: Accounts (chart of accounts, journals, opening balances, reconciliation, accounts pack). */
(function () {
  'use strict';
  var K = window.K, $ = K.$, $$ = K.$$, esc = K.esc, money = K.money, I = K.ICONS, ic = K.ic;
  var st = K.S.acc = K.S.acc || { tab: 'chart', p: 'year', rc: '1010', stmt: '' };
  var TYPES = [['asset', 'Assets: what the business owns'], ['liability', 'Liabilities: what the business owes'], ['equity', "Owner's equity"], ['income', 'Income'], ['expense', 'Expenses']];
  var SRC = { manual: 'Manual entry', opening: 'Opening balances', supplier_pay: 'Supplier payment' };

  function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function period(p) {
    var t = new Date(), y = t.getFullYear(), m = t.getMonth();
    if (p === 'month') return [ymd(new Date(y, m, 1)), ymd(new Date(y, m + 1, 0))];
    if (p === 'last') return [ymd(new Date(y, m - 1, 1)), ymd(new Date(y, m, 0))];
    if (p === 'quarter') { var q = Math.floor(m / 3) * 3; return [ymd(new Date(y, q, 1)), ymd(new Date(y, q + 3, 0))]; }
    if (p === 'lastyear') return [(y - 1) + '-01-01', (y - 1) + '-12-31'];
    if (p === 'custom') return [st.pf || ymd(new Date(y, m, 1)), st.pt || ymd(t)];
    return [y + '-01-01', y + '-12-31'];
  }
  var PERIODS = [['month', 'This month'], ['last', 'Last month'], ['quarter', 'This quarter'], ['year', 'This year'], ['lastyear', 'Last year'], ['custom', 'Choose dates']];

  K.V.accounts = function (v) {
    K.api('accounts').then(function (r) {
      var acc = r.accounts, by = {}; acc.forEach(function (a) { by[a.code] = a; });
      var bal = function (c) { return by[c] ? by[c].balance : 0; };
      var cash = bal('1000') + bal('1010') + bal('1020');
      var pr = period(st.p);
      v.innerHTML = K.head('Accounts', 'Your books are kept automatically from invoices, payments, expenses and purchases. Add opening balances and manual entries here, reconcile your money accounts, and download the full accounts pack.',
        '<label class="tiny" for="accpp" style="align-self:center">Pack period</label><select id="accpp" data-pp style="width:auto">' + PERIODS.map(function (o) { return '<option value="' + o[0] + '"' + (st.p === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select><span data-pcd' + (st.p === 'custom' ? '' : ' hidden') + ' style="display:inline-flex;gap:6px;align-items:center"><input type="date" data-pf value="' + period('custom')[0] + '" aria-label="From"><span class="tiny">to</span><input type="date" data-pt value="' + period('custom')[1] + '" aria-label="To"></span><button class="btn sun" data-pack="pdf">' + ic(I.dl) + 'Accounts pack (PDF)</button><button class="btn" data-pack="xlsx">Excel</button>') +
        '<div class="kpis">' +
        '<div class="kpi link" data-acc-tab="recon"><small>Cash, M-Pesa and bank</small><b>' + money(cash) + '</b><i class="mut">Reconcile</i></div>' +
        '<div class="kpi link" data-go="#sales" data-set=\'{"sales": {"tab": "invoice", "state": "owed", "q": ""}}\'><small>Owed by customers</small><b>' + money(r.owed) + '</b></div>' +
        '<div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "payables"}}\'><small>Owed to suppliers</small><b>' + money(bal('2000')) + '</b></div>' +
        '<div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "vat", "p": "month"}}\'><small>' + (r.vat_month >= 0 ? 'VAT due for this month' : 'VAT to reclaim this month') + '</small><b>' + money(Math.abs(r.vat_month)) + '</b></div></div>' +
        '<div class="kpis"><div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "bs"}}\'><small>Balance sheet</small><b style="font-size:16px">Open</b></div><div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "pl", "p": "year"}}\'><small>Profit and loss</small><b style="font-size:16px">Open</b></div><div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "tb"}}\'><small>Trial balance</small><b style="font-size:16px">Open</b></div><div class="kpi link" data-go="#reports" data-set=\'{"rep": {"type": "gl", "p": "month"}}\'><small>General ledger</small><b style="font-size:16px">Open</b></div></div>' +
        K.tabs('acc', [['chart', 'Chart of accounts'], ['journal', 'Journal entries'], ['opening', 'Opening balances'], ['recon', 'Reconcile']], st.tab) + '<div data-ab></div>';
      $('[data-pp]', v).onchange = function (e) { st.p = e.target.value; $('[data-pcd]', v).hidden = st.p !== 'custom'; };
      $('[data-pf]', v).onchange = function (e) { st.pf = e.target.value; };
      $('[data-pt]', v).onchange = function (e) { st.pt = e.target.value; };
      $$('[data-pack]', v).forEach(function (b) { b.onclick = function () { var p = period(st.p); K.open('reports/pack?format=' + b.dataset.pack + '&from=' + p[0] + '&to=' + p[1]); K.toast('Preparing the accounts pack…'); }; });
      $$('[data-tabs] button', v).forEach(function (b) { b.onclick = function () { st.tab = b.dataset.t; K.V.accounts(v); }; });
      $$('[data-acc-tab]', v).forEach(function (b) { b.onclick = function () { st.tab = b.dataset.accTab; K.V.accounts(v); }; });
      var host = $('[data-ab]', v);
      if (st.tab === 'journal') return journal(host, acc);
      if (st.tab === 'opening') return opening(host, acc, r.opening);
      if (st.tab === 'recon') return recon(host, acc);
      chart(host, acc);
    }).catch(K.fail);
  };

  function chart(host, acc) {
    host.innerHTML = '<div class="toolbar" style="margin-bottom:10px"><span class="sub" style="margin:0">Click an account to see its entries. Add your own accounts for things like equipment, loans or prepayments.</span><button class="btn sun" data-na style="margin-left:auto">' + ic(I.plus) + 'Add account</button></div>' + TYPES.map(function (t) {
      var list = acc.filter(function (a) { return a.type === t[0]; });
      var tot = list.reduce(function (s, a) { return s + a.balance; }, 0);
      return '<div class="box"><h3>' + esc(t[1]) + '</h3>' + K.table([
        { k: 'code', label: 'Code', f: function (a) { return '<span class="mono">' + esc(a.code) + '</span>'; } },
        { k: 'name', label: 'Account', f: function (a) { return esc(a.name) + (a.money ? ' <button class="btn sm ghost" data-rec="' + a.code + '">Reconcile</button>' : '') + (a.custom && !a.balance ? ' <button class="btn sm ghost" data-rma="' + a.code + '" aria-label="Remove account">' + ic(I.trash) + '</button>' : ''); } },
        { k: 'balance', label: 'Balance today', n: 1, f: function (a) { return a.balance ? K.moneyx(a.balance, 1) : '<span class="mut">—</span>'; } }
      ], list, { click: 1, id: 'code' }) + '<div class="tot"><div class="g"><span>Total</span><span>' + money(tot) + '</span></div></div></div>';
    }).join('');
    $$('[data-row]', host).forEach(function (tr) { tr.addEventListener('click', function (e) { if (e.target.closest('button')) return; ledger(tr.dataset.row, acc.filter(function (a) { return a.code === tr.dataset.row; })[0]); }); });
    $$('[data-rec]', host).forEach(function (b) { b.onclick = function () { st.tab = 'recon'; st.rc = b.dataset.rec; K.route(); }; });
    $('[data-na]', host).onclick = function () {
      var m = K.modal('Add an account', '<div class="grid3">' + K.field('Type', 'type', 'asset', 'select', { options: [['asset', 'Asset (owned)'], ['liability', 'Liability (owed)'], ['equity', "Owner's equity"], ['income', 'Income'], ['expense', 'Expense']] }) + K.field('Code', 'code', '', 'text', { ph: 'e.g. 1510', hint: 'Four digits: assets 1…, liabilities 2…, equity 3…, income 4…, expenses 5–7…' }) + K.field('Name', 'name', '', 'text', { ph: 'e.g. Delivery van' }) + '</div>', '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-sa>Add account</button>');
      $('[data-sa]', m).onclick = function () { K.api('accounts', { body: K.read(m) }).then(function (r) { K.closeModal(); K.toast(r.message); K.route(); }).catch(K.fail); };
    };
    $$('[data-rma]', host).forEach(function (b) { b.onclick = function (e) { e.stopPropagation(); K.confirm('Remove account ' + esc(b.dataset.rma) + '?', 'Remove', true).then(function (y) { if (y) K.api('accounts/' + b.dataset.rma, { method: 'DELETE' }).then(function (r) { K.toast(r.message); K.route(); }).catch(K.fail); }); }; });
  }

  /* One account's entries this year, with a running balance. */
  function ledger(code, a) {
    var y = new Date().getFullYear(), flip = a && ['liability', 'equity', 'income'].indexOf(a.type) > -1 ? -1 : 1;
    K.api('reports/gl?from=' + y + '-01-01&to=' + K.today()).then(function (r) {
      var sec = (r.sections || []).filter(function (s) { return s.code === code; })[0];
      var rows = sec ? sec.rows.map(function (row) { var v = Object.keys(row).filter(function (k) { return k !== '_b'; }).map(function (k) { return row[k]; }); return { date: v[0], memo: v[1], dr: v[2], cr: v[3], bal: v[4] * flip, b: !v[0] }; }) : [];
      K.modal(esc(sec ? sec.title : code), (rows.length ? K.table([
        { k: 'date', label: 'Date', f: function (x) { return x.date ? K.d(x.date) : ''; } },
        { k: 'memo', label: 'Details', f: function (x) { return x.b ? '<b>' + esc(x.memo) + '</b>' : esc(x.memo); } },
        { k: 'dr', label: 'Debit', n: 1, f: function (x) { return x.dr !== '' ? K.moneyx(x.dr, 1) : ''; } },
        { k: 'cr', label: 'Credit', n: 1, f: function (x) { return x.cr !== '' ? K.moneyx(x.cr, 1) : ''; } },
        { k: 'bal', label: 'Balance', n: 1, f: function (x) { return x.b ? '<b>' + K.moneyx(x.bal, 1) + '</b>' : K.moneyx(x.bal, 1); } }
      ], rows) : '<div class="empty"><b>No entries this year</b>Entries appear when invoices, payments, expenses or journals use this account.</div>'), '<button class="btn" data-mx>Close</button>', true);
    }).catch(K.fail);
  }

  function opts(acc, sel, filter) { return acc.filter(filter || function () { return true; }).map(function (a) { return '<option value="' + a.code + '"' + (String(sel) === a.code ? ' selected' : '') + '>' + esc(a.code + '  ' + a.name) + '</option>'; }).join(''); }

  function journal(host, acc) {
    K.api('journal').then(function (rows) {
      host.innerHTML = '<div class="box"><div class="toolbar"><span class="sub" style="margin:0">Use a journal entry for anything that is not an invoice, payment, expense or purchase: money the owner puts in or takes out, loans, equipment, VAT paid to KRA, corrections.</span><button class="btn" data-mv style="margin-left:auto">' + ic(I.cash) + 'Move money between accounts</button><button class="btn sun" data-nj>' + ic(I.plus) + 'New journal entry</button></div>' +
        K.table([
          { k: 'date', label: 'Date', f: function (j) { return K.ds(j.date); } },
          { k: 'memo', label: 'Details', f: function (j) { return '<b>' + esc(j.memo) + '</b><br><small class="tiny">' + (j.lines || []).map(function (l) { var a = acc.filter(function (x) { return x.code === String(l.code); })[0]; return esc((a ? a.name : l.code) + ' ' + (l.debit > 0 ? 'Dr ' + K.moneyx(l.debit, 1) : 'Cr ' + K.moneyx(l.credit, 1))); }).join(' · ') + '</small>'; } },
          { k: 'source', label: 'Type', f: function (j) { return '<span class="st">' + esc(SRC[j.source] || j.source) + '</span>'; } },
          { k: 'amount', label: 'Amount', n: 1, f: function (j) { return money(j.amount); } },
          { k: 'x', label: '', f: function (j) { return j.source === 'manual' ? '<button class="btn sm ghost" data-jd="' + j.id + '" aria-label="Delete entry">' + ic(I.trash) + '</button>' : ''; } }
        ], rows, { empty: 'No journal entries yet', emptySub: 'Invoices, payments, expenses and purchases are posted automatically. Manual entries appear here.' }) + '</div>';
      $('[data-nj]', host).onclick = function () { newJournal(acc); };
      $('[data-mv]', host).onclick = function () {
        var mon = acc.filter(function (a) { return a.money; }).map(function (a) { return [a.code, a.name]; });
        var m = K.modal('Move money between accounts', '<p class="sub">For example banking the day\'s cash, or moving M-Pesa to the bank. It is not income or spending.</p><div class="grid2">' + K.field('From', 'from', mon[0] ? mon[0][0] : '', 'select', { options: mon }) + K.field('To', 'to', mon[2] ? mon[2][0] : (mon[1] ? mon[1][0] : ''), 'select', { options: mon }) + '</div><div class="grid3">' + K.field('Amount', 'amount', '', 'number') + K.field('Charges (optional)', 'fee', '', 'number') + K.field('Date', 'date', K.today(), 'date') + '</div>', '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Move</button>');
        $('[data-go]', m).onclick = function () { K.api('accounts/transfer', { body: K.read(m) }).then(function (r) { K.closeModal(); K.toast(r.message); K.route(); }).catch(K.fail); };
      };
      $$('[data-jd]', host).forEach(function (b) { b.onclick = function () { K.confirm('Delete this journal entry? The accounts will update straight away.', 'Delete', true).then(function (y) { if (y) K.api('journal/' + b.dataset.jd, { method: 'DELETE' }).then(function (r) { K.toast(r.message); K.route(); }).catch(K.fail); }); }; });
    }).catch(K.fail);
  }

  function newJournal(acc) {
    var lines = [{ code: '1020', debit: '', credit: '' }, { code: '3000', debit: '', credit: '' }];
    var m = K.modal('New journal entry', '<div class="grid3">' + K.field('Date', 'date', K.today(), 'date') + K.field('What is this for?', 'memo', '', 'text', { ph: 'e.g. Owner added capital' }) + K.field('Reference (optional)', 'ref', '', 'text') + '</div>' +
      '<p class="tiny">Each line moves money into (debit) or out of (credit) an account. Debits and credits must be equal. Example: the owner puts KES 50,000 into the bank → Bank debit 50,000, Owner\'s capital credit 50,000.</p>' +
      '<div data-jl></div><button class="btn sm" data-ja>' + ic(I.plus) + 'Add line</button><div class="tot" data-jt></div>',
      '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-js>Save entry</button>', true);
    var draw = function () {
      $('[data-jl]', m).innerHTML = '<table class="lines jl"><thead><tr><th>Account</th><th class="n">Debit</th><th class="n">Credit</th><th></th></tr></thead><tbody>' + lines.map(function (l, i) { return '<tr><td><select data-jc="' + i + '">' + opts(acc, l.code) + '</select></td><td class="jdr"><input type="number" min="0" step="any" value="' + l.debit + '" data-jdr="' + i + '" aria-label="Debit"></td><td class="jcr"><input type="number" min="0" step="any" value="' + l.credit + '" data-jcr="' + i + '" aria-label="Credit"></td><td>' + (lines.length > 2 ? '<button class="btn ghost sm" data-jx="' + i + '" aria-label="Remove line">' + ic(I.x) + '</button>' : '') + '</td></tr>'; }).join('') + '</tbody></table>';
      tot();
    };
    var tot = function () { var d = lines.reduce(function (s, l) { return s + (+l.debit || 0); }, 0), c = lines.reduce(function (s, l) { return s + (+l.credit || 0); }, 0); $('[data-jt]', m).innerHTML = '<div><span>Debits</span><span>' + K.moneyx(d) + '</span></div><div><span>Credits</span><span>' + K.moneyx(c) + '</span></div><div class="g"><span>' + (Math.abs(d - c) < 0.01 ? 'Balanced' : 'Difference') + '</span><span class="' + (Math.abs(d - c) < 0.01 ? 'up' : 'down') + '">' + K.moneyx(Math.abs(d - c)) + '</span></div>'; };
    draw();
    m.addEventListener('input', function (e) { var t = e.target; if (t.dataset.jdr != null) { lines[t.dataset.jdr].debit = t.value; if (+t.value) { lines[t.dataset.jdr].credit = ''; var o = $('[data-jcr="' + t.dataset.jdr + '"]', m); if (o) o.value = ''; } } if (t.dataset.jcr != null) { lines[t.dataset.jcr].credit = t.value; if (+t.value) { lines[t.dataset.jcr].debit = ''; var o2 = $('[data-jdr="' + t.dataset.jcr + '"]', m); if (o2) o2.value = ''; } } tot(); });
    m.addEventListener('change', function (e) { if (e.target.dataset.jc != null) lines[e.target.dataset.jc].code = e.target.value; });
    m.addEventListener('click', function (e) { var x = e.target.closest('[data-jx]'); if (x) { lines.splice(+x.dataset.jx, 1); draw(); } });
    $('[data-ja]', m).onclick = function () { lines.push({ code: '6000', debit: '', credit: '' }); draw(); };
    $('[data-js]', m).onclick = function () { var f = K.read(m); f.lines = lines.map(function (l) { return { code: l.code, debit: +l.debit || 0, credit: +l.credit || 0 }; }); K.api('journal', { body: f }).then(function (r) { K.closeModal(); K.toast(r.message); K.route(); }).catch(K.fail); };
  }

  function opening(host, acc, op) {
    var a = (op && op.amounts) || {};
    var list = acc.filter(function (x) { return ['asset', 'liability', 'equity'].indexOf(x.type) > -1 && x.code !== '3050'; });
    host.innerHTML = '<div class="box"><h3>Opening balances</h3><p class="sub">What the business had and owed on the day you started using Dynamic Desk. Enter amounts as positive numbers; the difference is kept in the Opening balances equity account. For customers and suppliers, enter only what was owed before you started; new invoices and supplier bills are added automatically.</p>' +
      '<div class="grid3">' + K.field('Balances as at', 'date', op && op.date ? op.date : (new Date().getFullYear()) + '-01-01', 'date') + '</div>' +
      '<div class="grid3">' + list.map(function (x) { return K.field(x.code + '  ' + x.name, 'a' + x.code, a[x.code] || '', 'number'); }).join('') + '</div>' +
      '<div class="acts" style="margin-top:12px"><button class="btn pri" data-os>Save opening balances</button></div></div>';
    $('[data-os]', host).onclick = function () {
      var f = K.read(host), amounts = {};
      list.forEach(function (x) { if (+f['a' + x.code]) amounts[x.code] = +f['a' + x.code]; });
      K.confirm('Save these opening balances as at ' + esc(K.d(f.date)) + '? They replace any opening balances saved before.', 'Save').then(function (y) { if (y) K.api('accounts/opening', { body: { date: f.date, amounts: amounts } }).then(function (r) { K.toast(r.message); K.route(); }).catch(K.fail); });
    };
  }

  function recon(host, acc, r0) {
    var money_acc = acc.filter(function (a) { return a.money; });
    var load = r0 ? Promise.resolve(r0) : K.api('accounts/' + st.rc + '/recon' + (st.rto ? '?to=' + st.rto : ''));
    load.then(function (r) {
      st.rto = r.to; st.rcache = r;
      var stmt = r.stmt === null ? '' : r.stmt;
      var diff = stmt === '' ? null : Math.round((stmt - r.cleared) * 100) / 100;
      var ok = diff !== null && Math.abs(diff) < 0.01;
      var rows = st.rall ? r.rows : r.rows.filter(function (x) { return !x.done; });
      var last = r.history && r.history[0];
      var where = st.rc === '1010' ? 'M-Pesa' : st.rc === '1000' ? 'cash count' : 'bank';
      host.innerHTML = '<div class="box"><div class="toolbar" style="flex-wrap:wrap;gap:10px"><select data-ra style="width:auto" aria-label="Account">' + money_acc.map(function (a) { return '<option value="' + a.code + '"' + (a.code === st.rc ? ' selected' : '') + '>' + esc(a.name) + '</option>'; }).join('') + '</select>' +
        '<label class="tiny" style="display:flex;gap:8px;align-items:center">Statement date <input type="date" data-rd value="' + esc(r.to) + '" max="' + K.today() + '"></label>' +
        '<label class="tiny" style="display:flex;gap:8px;align-items:center">Closing balance on the ' + where + ' statement <input type="number" step="any" data-rs value="' + esc(stmt) + '" style="width:160px"></label>' +
        '<button class="btn sm" data-rch>' + ic(I.plus) + 'Charges or interest</button></div>' +
        '<p class="tiny">' + (last ? 'Last reconciled to ' + K.d(last.date) + ' by ' + esc(last.by) + '. Beginning balance ' + K.moneyx(r.begin) + '.' : 'First reconciliation for this account: tick everything on the statement up to its date.') + '</p>' +
        '<div class="kpis"><div class="kpi"><small>Balance in the books at ' + K.d(r.to) + '</small><b>' + K.moneyx(r.book) + '</b></div><div class="kpi"><small>Ticked as on the statement</small><b>' + K.moneyx(r.cleared) + '</b></div><div class="kpi"><small>Difference</small><b class="' + (diff === null ? '' : ok ? 'up' : 'down') + '">' + (diff === null ? '—' : K.moneyx(diff)) + '</b><i class="mut">' + (diff === null ? 'Enter the statement balance' : ok ? 'Matches the statement' : 'Tick entries that appear on the statement') + '</i></div></div>' +
        '<div class="toolbar"><label class="tiny" style="display:flex;gap:6px;align-items:center"><input type="checkbox" data-rall' + (st.rall ? ' checked' : '') + '> Show items already ticked</label>' + (rows.some(function (x) { return !x.done; }) ? '<button class="btn sm" data-rtall>Tick all shown</button>' : '') + '<button class="btn sm" data-rprint>' + ic(I.dl) + 'Print</button>' + (ok ? '<button class="btn pri sm" data-rfin style="margin-left:auto">' + ic(I.check) + 'Finish reconciliation</button>' : '') + '</div>' +
        K.table([
          { k: 'done', label: 'On statement', f: function (x) { return '<input type="checkbox" data-rk="' + esc(x.key) + '"' + (x.done ? ' checked' : '') + ' aria-label="On statement">'; } },
          { k: 'date', label: 'Date', f: function (x) { return K.ds(x.date); } },
          { k: 'memo', label: 'Details', f: function (x) { return esc(x.memo) + (x.ref ? ' <span class="mono tiny">' + esc(x.ref) + '</span>' : ''); } },
          { k: 'amount', label: 'In', n: 1, f: function (x) { return x.amount > 0 ? K.moneyx(x.amount, 1) : ''; } },
          { k: 'out', label: 'Out', n: 1, f: function (x) { return x.amount < 0 ? K.moneyx(-x.amount, 1) : ''; } }
        ], rows, { empty: st.rall ? 'Nothing in this account up to this date' : 'Everything up to this date is ticked' }) +
        (r.history && r.history.length ? '<h3 style="margin-top:16px">Past reconciliations</h3>' + K.table([{ k: 'date', label: 'Statement date', f: function (x) { return K.d(x.date); } }, { k: 'balance', label: 'Balance', n: 1, f: function (x) { return K.moneyx(x.balance); } }, { k: 'by', label: 'Done by', f: function (x) { return esc(x.by); } }], r.history, {}) : '') + '</div>';
      var post = function (body) { return K.api('accounts/' + st.rc + '/recon', { body: body }).then(function (x) { recon(host, acc, x); }); };
      var saveStmt = function () { post({ do: 'statement', date: $('[data-rd]', host).value, balance: $('[data-rs]', host).value }).catch(K.fail); };
      $('[data-ra]', host).onchange = function (e) { st.rc = e.target.value; st.rto = ''; recon(host, acc); };
      $('[data-rs]', host).onchange = saveStmt;
      $('[data-rd]', host).onchange = saveStmt;
      $('[data-rall]', host).onchange = function (e) { st.rall = e.target.checked; recon(host, acc, r); };
      var ta = $('[data-rtall]', host);
      if (ta) ta.onclick = function () { post({ keys: rows.filter(function (x) { return !x.done; }).map(function (x) { return x.key; }), on: 1 }).catch(K.fail); };
      $('[data-rprint]', host).onclick = function () { window.print(); };
      var fin = $('[data-rfin]', host);
      if (fin) fin.onclick = function () { post({ do: 'finish' }).then(function () { K.toast('Reconciliation saved.'); }).catch(K.fail); };
      $('[data-rch]', host).onclick = function () {
        var m = K.modal('Charges or interest on the statement', '<div class="grid2">' + K.field('What is it?', 'kind', 'charge', 'select', { options: [['charge', 'Charges or fees'], ['interest', 'Interest earned']] }) + K.field('Amount', 'amount', '', 'number') + '</div>' + K.field('Date', 'date', r.to, 'date'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Add</button>');
        $('[data-go]', m).onclick = function () { var f = K.read(m); f.do = 'charge'; post(f).then(function () { K.closeModal(); K.toast('Added and ticked.'); }).catch(K.fail); };
      };
      $$('[data-rk]', host).forEach(function (c) { c.onchange = function () { post({ keys: [c.dataset.rk], on: c.checked ? 1 : 0 }).catch(K.fail); }; });
    }).catch(K.fail);
  }
})();
