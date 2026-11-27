/* Dynamic Desk: Site Studio (edit the website without code) */
(function () {
  'use strict';
  var K = window.K, $ = K.$, $$ = K.$$, esc = K.esc, I = K.ICONS, ic = K.ic, D = window.DESK;
  var S = { site: null, page: 'home', sec: null, tab: 'pages', dirty: false, device: 'desktop', timer: null };
  var FONTS_H = ['Outfit', 'Poppins', 'Montserrat', 'Plus Jakarta Sans', 'Manrope', 'Sora', 'DM Sans', 'Lexend', 'Nunito', 'Raleway', 'Urbanist'];
  var FONTS_B = ['Inter', 'DM Sans', 'Nunito Sans', 'Source Sans 3', 'Work Sans', 'Open Sans', 'Lato', 'Roboto'];
  var PAGE_NAMES = { home: 'Home', services: 'Services', projects: 'Projects', about: 'About', contact: 'Contact' };

  var get = function (o, path) { return path.split('.').reduce(function (a, k) { return a == null ? a : a[k]; }, o); };
  var set = function (o, path, val) { var ks = path.split('.'), last = ks.pop(), t = ks.reduce(function (a, k) { if (a[k] == null) a[k] = /^\d+$/.test(k) ? [] : {}; return a[k]; }, o); t[last] = val; };
  var uid = function () { return Math.random().toString(36).slice(2, 10); };
  var schema = function (t) { return D.schema[t] || { label: t, fields: [] }; };
  var pageUrl = function (p) { return D.site + (p === 'home' ? '' : p + '/'); };

  function changed() {
    S.dirty = true;
    var b = $('[data-pub]'); if (b) b.textContent = 'Publish changes';
    var d = $('[data-dirty]'); if (d) d.hidden = false;
    clearTimeout(S.timer);
    S.timer = setTimeout(preview, 650);
  }
  function preview() {
    var f = $('[data-pf]'); if (!f) return;
    fitFrame();
    var body = JSON.parse(JSON.stringify(S.site)); body._draft = 1;
    K.api('site', { body: body }).then(function () {
      var y = 0; try { y = f.contentWindow.scrollY; } catch (e) {}
      f.onload = function () {
        try {
          f.contentWindow.scrollTo(0, y);
          if (S.sec) { var el = f.contentDocument.querySelector('[data-sec="' + S.sec.id + '"]'); if (el) { el.scrollIntoView({ block: 'start' }); el.style.outline = '3px solid #f7941d'; el.style.outlineOffset = '-3px'; } }
          f.contentDocument.body.addEventListener('click', clickToEdit, true);
        } catch (e) {}
      };
      f.src = pageUrl(S.page) + '?soko_preview=1&r=' + Date.now();
    }).catch(K.fail);
  }
  /* Click a section in the preview to edit it. */
  function clickToEdit(e) {
    var s = e.target.closest('[data-sec]');
    if (!s) return;
    var a = e.target.closest('a,button'); if (a) { e.preventDefault(); e.stopPropagation(); }
    var pg = S.site.pages[S.page], sec = (pg.sections || []).filter(function (x) { return x.id === s.dataset.sec; })[0];
    if (sec) { S.tab = 'pages'; S.sec = sec; drawPanel(); var f = $('[data-pf]'); try { $$('[data-sec]', f.contentDocument).forEach(function (x) { x.style.outline = ''; }); s.style.outline = '3px solid #f7941d'; s.style.outlineOffset = '-3px'; } catch (er) {} }
  }

  /* ---------------- field renderers ---------------- */
  function linkList() {
    return '<datalist id="sklinks">' + Object.keys(S.site.pages).map(function (p) { return '<option value="' + (p === 'home' ? '/' : '/' + p + '/') + '">' + esc(S.site.pages[p].title) + '</option>'; }).join('') + '<option value="/shop/">Shop</option><option value="/planner/">Solar calculator</option><option value="/track/">Track an order</option>' + K.cats.map(function (c) { return '<option value="/shop/' + esc(c.id) + '/">' + esc(c.name) + '</option>'; }).join('') + '</datalist>';
  }
  function field(f, obj, path) {
    var v = get(obj, path), p = ' data-p="' + esc(path) + '"', id = 'sf_' + path.replace(/\W/g, '_');
    var lab = '<label for="' + id + '">' + esc(f.label) + '</label>';
    switch (f.type) {
      case 'textarea': return '<div class="f">' + lab + '<textarea id="' + id + '"' + p + '>' + esc(v || '') + '</textarea></div>';
      case 'richtext': return '<div class="f"><span class="flabel">' + esc(f.label) + '</span><div class="rte"><div class="tb"><button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="insertUnorderedList">•</button><button type="button" data-cmd="createLink">🔗</button></div><div contenteditable="true"' + p + '>' + (v || '') + '</div></div></div>';
      case 'number': return '<div class="f">' + lab + '<input type="number" id="' + id + '"' + p + ' value="' + esc(v == null ? '' : v) + '"></div>';
      case 'toggle': return '<label class="tog"><span>' + esc(f.label) + '</span><input type="checkbox" class="sw"' + p + (+v ? ' checked' : '') + '></label>';
      case 'select': return '<div class="f">' + lab + '<select id="' + id + '"' + p + '>' + Object.keys(f.options).map(function (k) { return '<option value="' + esc(k) + '"' + (String(v || '') === k ? ' selected' : '') + '>' + esc(f.options[k]) + '</option>'; }).join('') + '</select></div>';
      case 'category': return '<div class="f">' + lab + '<select id="' + id + '"' + p + '><option value="">Choose</option>' + K.cats.map(function (c) { return '<option value="' + esc(c.id) + '"' + (v === c.id ? ' selected' : '') + '>' + esc(c.name) + '</option>'; }).join('') + '</select></div>';
      case 'link': return '<div class="f">' + lab + '<input id="' + id + '"' + p + ' value="' + esc(v || '') + '" list="sklinks" placeholder="/contact/ or https://…"></div>';
      case 'image': return '<div class="f"><span class="flabel">' + esc(f.label) + '</span><div class="imgf"><span class="pv" style="background-image:url(\'' + esc(v || '') + '\')"></span><button type="button" class="btn sm" data-img="' + esc(path) + '">' + (v ? 'Change' : 'Choose photo') + '</button>' + (v ? '<button type="button" class="btn sm ghost" data-imgx="' + esc(path) + '">Remove</button>' : '') + '</div></div>';
      case 'items':
        var arr = Array.isArray(v) ? v : [];
        return '<div class="f"><span class="flabel">' + esc(f.label) + ' (' + arr.length + ')</span><div class="items">' + arr.map(function (it, i) {
          var first = f.fields.filter(function (x) { return x.type === 'text'; })[0], tl = first ? it[first.k] : '';
          return '<details class="it"' + (arr.length < 4 || i === 0 ? ' open' : '') + '><summary class="ith"><b>' + esc(tl || ('Item ' + (i + 1))) + '</b><span class="acts"><button type="button" class="btn sm ghost" data-iu="' + esc(path) + '" data-i="' + i + '" aria-label="Move up">↑</button><button type="button" class="btn sm ghost" data-id="' + esc(path) + '" data-i="' + i + '" aria-label="Move down">↓</button><button type="button" class="btn sm ghost" data-ix="' + esc(path) + '" data-i="' + i + '" aria-label="Remove">✕</button></span></summary>' + f.fields.map(function (sf) { return field(sf, obj, path + '.' + i + '.' + sf.k); }).join('') + '</details>';
        }).join('') + '</div><button type="button" class="btn sm" data-ia="' + esc(path) + '">' + ic(I.plus) + 'Add ' + esc(f.label.replace(/s$/, '').toLowerCase()) + '</button></div>';
      default: return '<div class="f">' + lab + '<input id="' + id + '"' + p + ' value="' + esc(v || '') + '"></div>';
    }
  }

  /* ---------------- panel ---------------- */
  function drawPanel() {
    var p = $('[data-panel]'); if (!p) return;
    var pg = S.site.pages[S.page];
    var tabs = '<div class="sttabs">' + [['pages', 'Pages'], ['header', 'Header'], ['brand', 'Brand'], ['projects', 'Projects'], ['footer', 'Footer']].map(function (t) { return '<button data-tab="' + t[0] + '" class="' + (S.tab === t[0] ? 'on' : '') + '">' + t[1] + '</button>'; }).join('') + '</div>';
    var body = '';
    if (S.tab === 'pages' && S.sec) {
      var sc = schema(S.sec.type);
      body = '<button class="btn sm ghost" data-back>← All sections</button><h3 style="margin:10px 0 2px">' + esc(sc.label) + '</h3><p class="tiny" style="margin-bottom:12px">' + esc(sc.desc || '') + '</p>' + sc.fields.map(function (f) { return field(f, S.sec, 'props.' + f.k); }).join('');
    } else if (S.tab === 'pages') {
      var custom = !PAGE_NAMES[S.page];
      body = '<div class="f"><label for="pgsel">Page</label><div style="display:flex;gap:6px"><select id="pgsel" data-page>' + Object.keys(S.site.pages).map(function (k) { return '<option value="' + esc(k) + '"' + (k === S.page ? ' selected' : '') + '>' + esc(S.site.pages[k].title) + '</option>'; }).join('') + '</select><button class="btn" data-addpage title="Add a page">' + ic(I.plus) + '</button></div></div>' +
        '<ul class="secs" data-secs>' + (pg.sections || []).map(function (s, i) { var sc = schema(s.type), pr = s.props || {}, sub = pr.title || (pr.slides && pr.slides[0] && pr.slides[0].title) || (pr.items && pr.items[0] && pr.items[0].title) || ''; return '<li draggable="true" data-i="' + i + '" class="' + (s.on ? '' : 'off') + '"><span class="grip" title="Drag to reorder">⋮⋮</span><span class="nm" data-edit="' + i + '"><b>' + esc(sc.label) + '</b><small>' + esc(sub) + '</small></span><input type="checkbox" class="sw" data-on="' + i + '"' + (s.on ? ' checked' : '') + ' title="Show or hide" aria-label="Show ' + esc(sc.label) + '"><button class="ib" data-dup="' + i + '" title="Duplicate">⧉</button><button class="ib" data-del="' + i + '" title="Delete">✕</button></li>'; }).join('') + '</ul>' +
        '<button class="btn" data-addsec style="width:100%">' + ic(I.plus) + 'Add a section</button>' +
        '<details style="margin-top:16px"><summary class="flabel" style="cursor:pointer">Page settings and Google listing</summary><div style="margin-top:10px">' + field({ k: 'title', label: 'Page name', type: 'text' }, pg, 'title') + field({ k: 'seo', label: 'Title in Google and browser tab', type: 'text' }, pg, 'seo') + field({ k: 'desc', label: 'Description in Google', type: 'textarea' }, pg, 'desc') + (custom ? '<p class="tiny">Address: ' + esc(pageUrl(S.page)) + '</p><button class="btn danger sm" data-delpage>Delete this page</button>' : '') + '</div></details>';
    } else if (S.tab === 'header') {
      var h = S.site.header;
      body = field({ label: 'Show the offer bar', type: 'toggle' }, h, 'announce_on') + field({ label: 'Offer bar text', type: 'text' }, h, 'announce') +
        '<div class="flabel" style="margin-top:8px">Menu</div><div class="items">' + (h.menu || []).map(function (m, i) { return '<div class="it"><div class="ith"><b>' + esc(m.label) + '</b><span class="acts"><button class="btn sm ghost" data-iu="header.menu" data-i="' + i + '">↑</button><button class="btn sm ghost" data-id="header.menu" data-i="' + i + '">↓</button><button class="btn sm ghost" data-ix="header.menu" data-i="' + i + '">✕</button></span></div><div class="grid2">' + field({ label: 'Label', type: 'text' }, S.site, 'header.menu.' + i + '.label') + field({ label: 'Link', type: 'link' }, S.site, 'header.menu.' + i + '.link') + '</div>' + field({ label: 'Open the full shop menu', type: 'toggle' }, S.site, 'header.menu.' + i + '.mega') + field({ label: 'Or show these categories in a dropdown (codes, comma separated: ' + (K.cats || []).map(function (c) { return c.id; }).join(', ') + ')', type: 'text' }, S.site, 'header.menu.' + i + '.cats') + field({ label: 'Dropdown side card', type: 'select', options: { survey: 'Book a site survey', planner: 'Size my system' } }, S.site, 'header.menu.' + i + '.promo') + '</div>'; }).join('') + '</div><button class="btn sm" data-ia="header.menu">' + ic(I.plus) + 'Add menu item</button>' +
        '<div class="flabel" style="margin-top:14px">Top bar links</div><div class="items">' + (h.utility || []).map(function (m, i) { return '<div class="it"><div class="ith"><b>' + esc(m.label) + '</b><span class="acts"><button class="btn sm ghost" data-ix="header.utility" data-i="' + i + '">✕</button></span></div><div class="grid2">' + field({ label: 'Label', type: 'text' }, S.site, 'header.utility.' + i + '.label') + field({ label: 'Link', type: 'link' }, S.site, 'header.utility.' + i + '.link') + '</div></div>'; }).join('') + '</div><button class="btn sm" data-ia="header.utility">' + ic(I.plus) + 'Add top bar link</button>' +
        '<div class="flabel" style="margin-top:14px">Header button</div><div class="grid2">' + field({ label: 'Button text', type: 'text' }, S.site, 'header.cta_label') + field({ label: 'Button link', type: 'link' }, S.site, 'header.cta_link') + '</div>';
      body = body.replace(/data-p="announce/g, 'data-p="header.announce');
    } else if (S.tab === 'brand') {
      var b = S.site.brand;
      body = field({ label: 'Logo', type: 'image' }, S.site, 'brand.logo') +
        '<div class="grid3"><div class="f"><label>Main colour</label><input type="color" data-p="brand.primary" value="' + esc(b.primary) + '"></div><div class="f"><label>Action colour</label><input type="color" data-p="brand.accent" value="' + esc(b.accent) + '"></div><div class="f"><label>Deep colour</label><input type="color" data-p="brand.deep" value="' + esc(b.deep) + '"></div></div>' +
        field({ label: 'Heading font', type: 'select', options: FONTS_H.reduce(function (o, f) { o[f] = f; return o; }, {}) }, S.site, 'brand.head_font') + field({ label: 'Text font', type: 'select', options: FONTS_B.reduce(function (o, f) { o[f] = f; return o; }, {}) }, S.site, 'brand.body_font') + field({ label: 'Corner roundness (px)', type: 'number' }, S.site, 'brand.radius') +
        '<p class="tiny">Colours and fonts apply across the whole site, including product pages and checkout.</p>';
    } else if (S.tab === 'projects') {
      body = '<p class="tiny" style="margin-bottom:10px">Projects appear in every Projects section. Use real installations only.</p>' + field({ label: 'Projects', type: 'items', fields: [{ k: 'name', label: 'Client or project', type: 'text' }, { k: 'place', label: 'Town', type: 'text' }, { k: 'size', label: 'System size', type: 'text' }, { k: 'type', label: 'What was installed', type: 'text' }, { k: 'sector', label: 'Sector (for filters)', type: 'text' }, { k: 'img', label: 'Photo', type: 'image' }] }, S.site, 'projects');
    } else if (S.tab === 'footer') {
      body = field({ label: 'About text', type: 'textarea' }, S.site, 'footer.about') + field({ label: 'Closing line', type: 'text' }, S.site, 'footer.note') + '<p class="tiny">Contact details in the footer come from Settings → Business.</p>';
    }
    p.innerHTML = tabs + '<div class="stbody">' + body + linkList() + '</div><div class="stfoot"><span class="tiny" data-dirty' + (S.dirty ? '' : ' hidden') + '>Unpublished changes</span><span class="acts"><button class="btn sm ghost" data-hist>History</button><button class="btn sm" data-discard>Discard</button><button class="btn sun" data-pub>' + (S.dirty ? 'Publish changes' : 'Published') + '</button></span></div>';
    bindPanel(p);
  }

  function bindPanel(p) {
    var pg = S.site.pages[S.page], target = function () { return S.tab === 'pages' && S.sec ? S.sec : S.site; };
    var root = function (path) { return S.tab === 'pages' && S.sec && path.indexOf('props.') === 0 ? S.sec : (S.tab === 'pages' && !S.sec && /^(title|seo|desc)$/.test(path) ? pg : S.site); };
    p.oninput = function (e) {
      var t = e.target, path = t.dataset.p; if (!path) return;
      var val = t.type === 'checkbox' ? (t.checked ? 1 : 0) : t.isContentEditable ? t.innerHTML : t.type === 'number' ? +t.value : t.value;
      set(root(path), path, val); changed();
      if (t.isContentEditable) return;
      if (t.closest('.it') && /\.(title|label|name)$/.test(path)) { var s = t.closest('.it').querySelector('.ith b'); if (s) s.textContent = t.value; }
    };
    p.onchange = function (e) {
      var t = e.target;
      if (t.dataset.page != null) { S.page = t.value; S.sec = null; drawPanel(); preview(); return; }
      if (t.dataset.on != null) { pg.sections[+t.dataset.on].on = t.checked ? 1 : 0; changed(); drawPanel(); return; }
      if (t.dataset.p) p.oninput(e);
    };
    p.onclick = function (e) {
      var t = e.target, b = t.closest('button,[data-edit],[data-tab]'); if (!b) return;
      if (b.dataset.tab) { S.tab = b.dataset.tab; S.sec = null; drawPanel(); return; }
      if (b.dataset.edit != null) { S.sec = pg.sections[+b.dataset.edit]; drawPanel(); preview(); return; }
      if (b.dataset.back != null) { S.sec = null; drawPanel(); return; }
      if (b.dataset.cmd) { e.preventDefault(); var c = b.dataset.cmd; if (c === 'createLink') { var u = window.prompt ? window.prompt('Link address') : ''; if (u) document.execCommand(c, false, u); } else document.execCommand(c, false, null); var ed = b.closest('.rte').querySelector('[contenteditable]'); set(root(ed.dataset.p), ed.dataset.p, ed.innerHTML); changed(); return; }
      if (b.dataset.dup != null) { var i = +b.dataset.dup, cp = JSON.parse(JSON.stringify(pg.sections[i])); cp.id = uid(); pg.sections.splice(i + 1, 0, cp); changed(); drawPanel(); return; }
      if (b.dataset.del != null) { K.confirm('Delete this section from the page?', 'Delete', true).then(function (y) { if (y) { pg.sections.splice(+b.dataset.del, 1); changed(); drawPanel(); } }); return; }
      if (b.dataset.addsec != null) return library();
      if (b.dataset.addpage != null) return addPage();
      if (b.dataset.delpage != null) { K.confirm('Delete the page "' + esc(pg.title) + '"? Remove it from the menu too.', 'Delete page', true).then(function (y) { if (y) { delete S.site.pages[S.page]; S.page = 'home'; changed(); drawPanel(); } }); return; }
      if (b.dataset.img) { K.pickImage().then(function (u) { set(root(b.dataset.img), b.dataset.img, Array.isArray(u) ? u[0] : u); changed(); drawPanel(); }); return; }
      if (b.dataset.imgx) { set(root(b.dataset.imgx), b.dataset.imgx, ''); changed(); drawPanel(); return; }
      var arrOp = function (path, fn) { var o = root(path), a = get(o, path) || []; fn(a); set(o, path, a); changed(); drawPanel(); };
      if (b.dataset.ia) { e.preventDefault(); arrOp(b.dataset.ia, function (a) { a.push({}); }); return; }
      if (b.dataset.ix) { e.preventDefault(); arrOp(b.dataset.ix, function (a) { a.splice(+b.dataset.i, 1); }); return; }
      if (b.dataset.iu) { e.preventDefault(); arrOp(b.dataset.iu, function (a) { var i = +b.dataset.i; if (i) a.splice(i - 1, 0, a.splice(i, 1)[0]); }); return; }
      if (b.dataset.id) { e.preventDefault(); arrOp(b.dataset.id, function (a) { var i = +b.dataset.i; if (i < a.length - 1) a.splice(i + 1, 0, a.splice(i, 1)[0]); }); return; }
      if (b.dataset.pub != null) return publish();
      if (b.dataset.hist != null) return history();
      if (b.dataset.discard != null) return K.confirm('Discard unpublished changes?', 'Discard', true).then(function (y) { if (y) load(true); });
    };
    /* drag to reorder sections */
    var list = $('[data-secs]', p), from = null;
    if (list) $$('li', list).forEach(function (li) {
      li.ondragstart = function () { from = +li.dataset.i; li.classList.add('drag'); };
      li.ondragend = function () { li.classList.remove('drag'); };
      li.ondragover = function (e) { e.preventDefault(); li.classList.add('over'); };
      li.ondragleave = function () { li.classList.remove('over'); };
      li.ondrop = function (e) { e.preventDefault(); var to = +li.dataset.i; if (from == null || from === to) return; pg.sections.splice(to, 0, pg.sections.splice(from, 1)[0]); changed(); drawPanel(); };
    });
  }

  function library() {
    var types = Object.keys(D.schema);
    var m = K.modal('Add a section', '<div class="lib">' + types.map(function (t) { return '<button data-t="' + t + '"><b>' + esc(D.schema[t].label) + '</b><small>' + esc(D.schema[t].desc || '') + '</small></button>'; }).join('') + '</div>', '', true);
    m.addEventListener('click', function (e) {
      var b = e.target.closest('[data-t]'); if (!b) return;
      var t = b.dataset.t, props = { title: D.schema[t].label, text: '' };
      if (t === 'hero_carousel') props = { autoplay: 1, slides: [{ title: 'Your headline', text: 'A short line about the offer.', cta_label: 'Shop now', cta_link: '/shop/', scene: 'home' }] };
      if (t === 'products') props.source = 'featured', props.limit = 8;
      if (t === 'projects') props.limit = 4;
      if (t === 'cta_band') { props.cta_label = 'Get a quote'; props.cta_link = '/contact/'; props.color = 'sun'; }
      if (t === 'page_hero') props.style = 'sky';
      var pg = S.site.pages[S.page], sec = { id: uid(), type: t, on: 1, props: props };
      pg.sections.push(sec); S.sec = sec; K.closeModal(); changed(); drawPanel();
    });
  }
  function addPage() {
    var m = K.modal('Add a page', K.field('Page name', 'title', '', 'text', { ph: 'e.g. Financing' }) + K.field('Add it to the menu', 'menu', 1, 'toggle'), '<button class="btn" data-mx>Cancel</button><button class="btn pri" data-go>Create page</button>');
    $('[data-go]', m).onclick = function () {
      var f = K.read(m), slug = String(f.title).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      if (!slug) { K.toast('Name the page.', true); return; }
      if (S.site.pages[slug] || ['shop', 'product', 'planner', 'cart', 'checkout', 'track', 'desk', 'wp-admin'].indexOf(slug) > -1) { K.toast('That name is taken. Choose another.', true); return; }
      S.site.pages[slug] = { title: f.title, seo: f.title + ' | ' + K.me.settings.business, desc: '', sections: [{ id: uid(), type: 'page_hero', on: 1, props: { title: f.title, text: '', style: 'sky' } }, { id: uid(), type: 'rich_text', on: 1, props: { title: '', text: '<p>Write your content here.</p>' } }] };
      if (+f.menu) S.site.header.menu.splice(Math.max(1, S.site.header.menu.length - 1), 0, { label: f.title, link: '/' + slug + '/' });
      S.page = slug; S.sec = null; K.closeModal(); K.toast('Page created. Publish to make it live.'); changed(); drawPanel();
    };
  }
  function history() {
    K.api('site/history').then(function (h) {
      var m = K.modal('Published versions', h.length ? '<p class="sub">Restore puts that version back live. What is live now is kept here too, so you can switch back.</p><ul class="list">' + h.map(function (x) { return '<li style="display:flex;align-items:center;gap:10px;justify-content:space-between"><span><b>' + esc(K.d(String(x.time).slice(0, 10)) + ' at ' + String(x.time).slice(11, 16)) + '</b><br><small class="tiny">' + (x.note ? 'The site as it was before ' + esc(x.note) : 'Earlier version') + (x.user ? ' · by ' + esc(x.user) : '') + '</small></span><button class="btn sm" data-ri="' + x.i + '">Restore</button></li>'; }).join('') + '</ul>' : '<div class="empty"><b>No earlier versions yet</b>Each time you publish, the previous version is kept here.</div>', '<button class="btn" data-mx>Close</button>');
      $$('[data-ri]', m).forEach(function (b) { b.onclick = function () { K.confirm('Put this version back live on the website?', 'Restore').then(function (y) { if (!y) return; K.api('site/restore', { body: { i: +b.dataset.ri } }).then(function () { K.closeModal(); K.toast('Restored. The website is updated.'); load(true); }).catch(K.fail); }); }; });
    }).catch(K.fail);
  }
  function publish() {
    var b = $('[data-pub]'); if (b) { b.disabled = true; b.textContent = 'Publishing…'; }
    K.api('site', { body: S.site }).then(function (r) { if (r && r._ver) S.site._ver = r._ver; S.dirty = false; K.toast('Published. The website is updated.'); drawPanel(); preview(); }).catch(function (e) { K.fail(e); drawPanel(); });
  }
  function load(reset) {
    K.api('site').then(function (s) { S.site = s; S.dirty = false; if (!S.site.pages[S.page]) S.page = 'home'; S.sec = null; drawPanel(); if (reset) preview(); }).catch(K.fail);
  }

  /* Desktop preview renders at a real desktop width (1280px) and is scaled to fit, so it shows the desktop layout. */
  function fitFrame() {
    var fr = document.querySelector('[data-frame]'), ifr = fr && fr.querySelector('iframe');
    if (!ifr) return;
    if (fr.classList.contains('mobile')) { ifr.style.width = ''; ifr.style.transform = ''; ifr.style.height = ''; fr.style.height = ''; return; }
    var w = fr.clientWidth, sc = Math.min(1, w / 1280), h = Math.max(420, window.innerHeight - 170);
    ifr.style.width = '1280px'; ifr.style.maxWidth = 'none'; ifr.style.transformOrigin = '0 0'; ifr.style.transform = 'scale(' + sc + ')'; ifr.style.height = (h / sc) + 'px'; ifr.style.margin = '0';
    var bar = fr.querySelector('.pbar'); fr.style.height = (h + (bar ? bar.offsetHeight : 0)) + 'px';
  }
  window.addEventListener('resize', function () { if (K.mod === 'studio') fitFrame(); });
  K.V.studio = function (v) {
    v.innerHTML = K.head('Site Studio', 'Edit the website without code. Click any part of the preview to edit it, or pick a section from the list. Changes show in the preview straight away and go live when you publish.', '<div class="chips"><button class="chip on" data-dev="desktop">Desktop</button><button class="chip" data-dev="mobile">Phone</button></div><a class="btn" target="_blank" rel="noopener" data-live href="' + esc(D.site) + '">' + ic(I.ext) + 'Open live site</a>') +
      '<div class="studio"><aside class="stpanel" data-panel><div class="boot" style="height:40vh"><div class="spin"></div></div></aside><div class="pframe" data-frame><div class="pbar"><i></i><i></i><i></i><span class="url" data-url>' + esc(D.site) + '</span></div><iframe data-pf title="Website preview"></iframe></div></div>';
    $$('[data-dev]', v).forEach(function (b) { b.onclick = function () { S.device = b.dataset.dev; $$('[data-dev]', v).forEach(function (x) { x.classList.toggle('on', x === b); }); $('[data-frame]', v).classList.toggle('mobile', S.device === 'mobile'); fitFrame(); }; });
    K.api('site').then(function (s) {
      S.site = s; S.dirty = false; S.sec = null;
      if (!S.site.pages[S.page]) S.page = 'home';
      drawPanel();
      $('[data-url]', v).textContent = pageUrl(S.page);
      preview();
    }).catch(K.fail);
    K.studioDirty = function (v) { if (v === false) S.dirty = false; return S.dirty; };
    window.onbeforeunload = function () { return S.dirty && K.mod === 'studio' ? 'You have unpublished changes.' : undefined; };
  };
})();
K.boot();
