/* Leads-overzicht: leest data/leads.csv + data/mailinglijst.csv, en met sleutel ook de chat/formulier-leads. */
(function () {
  var leads = [], chat = [], mailing = [];
  var $ = function (id) { return document.getElementById(id); };

  function parseCsv(text) {
    var rows = [], row = [], v = '', q = false;
    for (var i = 0; i < text.length; i++) {
      var c = text[i], n = text[i + 1];
      if (c === '"') { if (q && n === '"') { v += '"'; i++; } else q = !q; }
      else if (c === ',' && !q) { row.push(v); v = ''; }
      else if ((c === '\n' || c === '\r') && !q) {
        if (c === '\r' && n === '\n') i++;
        row.push(v); if (row.some(function (x) { return x !== ''; })) rows.push(row); row = []; v = '';
      } else v += c;
    }
    if (v !== '' || row.length) { row.push(v); rows.push(row); }
    return rows;
  }
  function toObjects(rows) {
    if (!rows.length) return [];
    var h = rows[0];
    return rows.slice(1).map(function (r) { var o = {}; h.forEach(function (k, i) { o[k] = r[i] || ''; }); return o; });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(t) { if (!t) return '-'; var d = new Date(t.replace(' ', 'T')); return isNaN(d) ? t : d.toLocaleString('nl-NL', { dateStyle: 'short', timeStyle: 'short' }); }
  function time(t) { var d = new Date(String(t || '').replace(' ', 'T')); return isNaN(d) ? 0 : d.getTime(); }

  // Alles naar één vorm
  function fromOld(x) {
    return { t: x.timestamp, src: x.source === 'admin-offerte' ? 'admin-offerte' : 'website-formulier', naam: ((x.voornaam || '') + ' ' + (x.achternaam || '')).trim(),
      email: x.email, tel: x.telefoon, type: x.type_evenement, act: x.gewenste_act, datum: x.datum_evenement || '', msg: x.bericht, mail: x.mailing_toestemming || 'nee' };
  }
  function fromChat(x) {
    var extra = [x['Plaats'] && 'Plaats: ' + x['Plaats'], x['Gasten'] && 'Gasten: ' + x['Gasten'], x['Taal'] && 'Taal: ' + x['Taal']].filter(Boolean).join(' · ');
    return { t: x['Tijd'], src: 'chat', naam: x['Naam'], email: x['E-mail'], tel: x['Telefoon'], type: x['Type event'], act: x['Act'], datum: x['Datum'],
      msg: [x['Opmerking'], extra].filter(Boolean).join(' — '), mail: 'nee', pagina: x['Pagina'] };
  }
  function all() { return leads.map(fromOld).concat(chat.map(fromChat)); }

  var BADGE = { 'admin-offerte': ['b-admin', 'Offertetool'], 'website-formulier': ['b-web', 'Website'], 'chat': ['b-chat', 'Chat / formulier'] };

  function filtered() {
    var q = $('searchInput').value.trim().toLowerCase(), s = $('sourceFilter').value, m = $('mailingFilter').value, sort = $('sortFilter').value;
    return all().filter(function (x) {
      if (s && x.src !== s) return false;
      if (m && x.mail !== m) return false;
      if (q && !Object.keys(x).some(function (k) { return String(x[k] || '').toLowerCase().indexOf(q) > -1; })) return false;
      return true;
    }).sort(function (a, b) { return sort === 'oud' ? time(a.t) - time(b.t) : time(b.t) - time(a.t); });
  }

  function render() {
    var a = all();
    $('stat-leads').textContent = a.length;
    $('stat-web').textContent = a.filter(function (x) { return x.src !== 'admin-offerte'; }).length;
    $('stat-admin').textContent = a.filter(function (x) { return x.src === 'admin-offerte'; }).length;
    $('stat-mailing').textContent = mailing.length;
    var d = filtered();
    $('leadsBody').innerHTML = d.length ? d.map(function (x) {
      var b = BADGE[x.src];
      return '<tr><td>' + esc(fmt(x.t)) + '</td><td><span class="badge ' + b[0] + '">' + b[1] + '</span></td><td><b>' + esc(x.naam || '-') + '</b></td>' +
        '<td>' + (x.email ? '<a href="mailto:' + esc(x.email) + '">' + esc(x.email) + '</a>' : '-') + '</td><td>' + (x.tel ? '<a href="tel:' + esc(x.tel.replace(/\s/g, '')) + '">' + esc(x.tel) + '</a>' : '-') + '</td>' +
        '<td>' + esc(x.type || '-') + '</td><td>' + esc(x.act || '-') + '</td><td>' + esc(x.datum || '-') + '</td><td class="msg">' + esc(x.msg || '-') + '</td>' +
        '<td><span class="badge' + (x.mail === 'ja' ? ' b-ja">Ja' : '">Nee') + '</span></td></tr>';
    }).join('') : '<tr><td colspan="10" class="small">Geen leads gevonden.</td></tr>';
    $('mailingList').innerHTML = mailing.length ? mailing.map(function (x) {
      return '<div class="admin-item"><b>' + esc(x.naam || '-') + '</b><span>' + esc(x.email || '-') + (x.telefoon ? ' · ' + esc(x.telefoon) : '') + '<br>Bron: ' + esc(x.source || '-') + ' · ' + esc(fmt(x.timestamp)) + '</span></div>';
    }).join('') : '<div class="admin-empty">Nog geen contacten in de mailinglijst.</div>';
  }

  function csvOut(rows, headers, name) {
    var e = function (v) { var s = String(v == null ? '' : v); return /[",\n;]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
    var txt = '﻿' + [headers].concat(rows).map(function (r) { return r.map(e).join(','); }).join('\n');
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' }));
    a.download = name; document.body.appendChild(a); a.click(); a.remove();
  }
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-dl]'); if (!t) return;
    if (t.dataset.dl === 'mailing') {
      var h = ['timestamp', 'naam', 'email', 'telefoon', 'source'];
      csvOut(mailing.map(function (x) { return h.map(function (k) { return x[k] || ''; }); }), h, 'mailinglijst-export.csv');
    } else {
      var k = ['t', 'src', 'naam', 'email', 'tel', 'type', 'act', 'datum', 'msg', 'mail'];
      csvOut(filtered().map(function (x) { return k.map(function (f) { return x[f] || ''; }); }),
        ['tijd', 'bron', 'naam', 'email', 'telefoon', 'type_evenement', 'act', 'datum_evenement', 'bericht', 'mailing'], 'leads-export.csv');
    }
  });
  ['searchInput', 'sourceFilter', 'mailingFilter', 'sortFilter'].forEach(function (id) { $(id).addEventListener('input', render); $(id).addEventListener('change', render); });

  function loadCsv(url) {
    return fetch(url, { cache: 'no-store' }).then(function (r) { if (!r.ok) throw 0; return r.text(); })
      .then(function (t) { return toObjects(parseCsv(t)); }).catch(function () { return []; });
  }

  var KEY = 'lnLeadsKey';
  function loadChat(key) {
    var msg = $('keyMsg');
    if (!key) return Promise.resolve();
    msg.textContent = 'Laden…';
    return fetch('lanegra-chat/leads-export.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ key: key }) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Mislukt');
        chat = d.rows || []; msg.textContent = chat.length + ' chat/formulier-leads geladen.';
        try { sessionStorage.setItem(KEY, key); } catch (e) {}
        render();
      })
      .catch(function (e) { msg.textContent = e.message || 'Laden mislukt.'; });
  }
  $('keyBtn').addEventListener('click', function () { loadChat($('keyInput').value.trim()); });
  $('keyInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadChat(this.value.trim()); });

  Promise.all([loadCsv('data/leads.csv'), loadCsv('data/mailinglijst.csv')]).then(function (r) {
    leads = r[0]; mailing = r[1]; render();
    var k = ''; try { k = sessionStorage.getItem(KEY) || ''; } catch (e) {}
    if (k) { $('keyInput').value = k; loadChat(k); }
  });
})();
