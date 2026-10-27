(function () {
  var API = 'agenda/agenda-api.php';
  var BUFFER = 60, MIN_AHEAD = 2, DEMO = /[?&]demo=1/.test(location.search);
  var $ = function (i) { return document.getElementById(i); };
  var today = new Date(); today.setHours(0, 0, 0, 0);
  var view = new Date(today.getFullYear(), today.getMonth(), 1), sel = null, days = {}, busy = [], t0 = Date.now();
  var cap = function (t) { return t.charAt(0).toUpperCase() + t.slice(1); };
  var pad = function (n) { return String(n).padStart(2, '0'); };
  var key = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
  var fmtM = new Intl.DateTimeFormat('nl-NL', { month: 'long', year: 'numeric' });
  var fmtD = new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  // Voorbeelddata als de agenda nog niet gekoppeld is
  var DEMO_DAYS = {}, DEMO_BUSY = {};
  (function () {
    for (var i = 3; i < 400; i++) {
      var d = new Date(today); d.setDate(d.getDate() + i);
      if (d.getDay() === 6 && i % 3 === 0) DEMO_DAYS[key(d)] = 'vol';
      else if (d.getDay() === 6 || (d.getDay() === 5 && i % 2)) { DEMO_DAYS[key(d)] = 'deels'; DEMO_BUSY[key(d)] = [{ start: '20:00', end: '23:00' }]; }
    }
  })();
  function demoMode() { DEMO = true; $('calDemo').hidden = false; }

  function api(q) {
    if (DEMO) return Promise.reject();
    return fetch(API + '?' + q, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (d) { if (!d.ok) throw d; return d; });
  }
  function loadMonth() {
    var ym = view.getFullYear() + '-' + pad(view.getMonth() + 1);
    render();
    api('action=month&ym=' + ym).then(function (d) { days = d.days || {}; MIN_AHEAD = d.minDaysAhead || MIN_AHEAD; render(); })
      .catch(function (d) {
        if (d && d.error && !/nog niet gekoppeld/.test(d.error)) { var n = $('calDemo'); n.textContent = d.error; n.hidden = false; days = {}; render(); return; }
        demoMode(); days = DEMO_DAYS; render();
      });
  }
  function render() {
    $('calTitle').textContent = cap(fmtM.format(view));
    $('calPrev').disabled = view <= new Date(today.getFullYear(), today.getMonth(), 1);
    var g = $('calDays'); g.innerHTML = '';
    var first = (view.getDay() + 6) % 7, n = new Date(view.getFullYear(), view.getMonth() + 1, 0).getDate();
    var minDate = new Date(today); minDate.setDate(minDate.getDate() + MIN_AHEAD);
    for (var i = 0; i < first; i++) g.appendChild(document.createElement('span'));
    for (var d = 1; d <= n; d++) {
      var dt = new Date(view.getFullYear(), view.getMonth(), d), k = key(dt), b = document.createElement('button'), st = days[k];
      b.type = 'button'; b.className = 'cal-day'; b.textContent = d; b.dataset.k = k;
      var label = fmtD.format(dt);
      if (dt < minDate) { b.classList.add('past'); b.disabled = true; }
      else if (st === 'vol') { b.classList.add('busy'); b.disabled = true; label += ', volgeboekt'; }
      else if (st === 'deels') { b.classList.add('option'); label += ', al een optreden, andere tijden vrij'; }
      else label += ', vrij';
      if (+dt === +today) b.classList.add('today');
      if (sel && k === key(sel)) b.classList.add('sel');
      b.setAttribute('aria-label', label);
      g.appendChild(b);
    }
  }

  // Tijden in stappen van 30 min, van 10:00 tot 02:00
  var SLOTS = []; for (var m = 10 * 60; m <= 26 * 60; m += 30) SLOTS.push(m);
  var hm = function (m) { return pad(Math.floor(m / 60) % 24) + ':' + pad(m % 60); };
  var toMin = function (s) { var p = s.split(':'); var v = +p[0] * 60 + +p[1]; return v < 6 * 60 ? v + 1440 : v; };
  function clash(s, e) {
    return busy.some(function (x) { var bs = toMin(x.start), be = toMin(x.end); if (be <= bs) be += 1440; return s < be + BUFFER && e > bs - BUFFER; });
  }
  function fillTimes() {
    var cs = $('cStart'), prev = cs.value; cs.innerHTML = '';
    SLOTS.slice(0, -1).forEach(function (m) {
      var o = new Option(hm(m), hm(m)); if (clash(m, m + 30)) { o.disabled = true; o.text += ' (bezet)'; } cs.add(o);
    });
    var ok = function (o, from) { var v = toMin(o.value); return !o.disabled && v >= from && v <= 23 * 60 && !clash(v, v + 120); };
    var pick = [].find.call(cs.options, function (o) { return o.value === prev && !o.disabled; }) || [].find.call(cs.options, function (o) { return ok(o, 20 * 60); }) || [].find.call(cs.options, function (o) { return ok(o, 14 * 60); }) || [].find.call(cs.options, function (o) { return ok(o, 0); }) || [].find.call(cs.options, function (o) { return !o.disabled; });
    if (pick) cs.value = pick.value;
    fillEnd();
  }
  function fillEnd() {
    var s = toMin($('cStart').value || '20:00'), ce = $('cEind'), prev = ce.value; ce.innerHTML = '';
    SLOTS.forEach(function (m) {
      if (m <= s || m - s > 12 * 60) return;
      var o = new Option(hm(m), hm(m)); if (clash(s, m)) { o.disabled = true; o.text += ' (bezet)'; } ce.add(o);
    });
    var pick = [].find.call(ce.options, function (o) { return o.value === prev && !o.disabled; }) || [].find.call(ce.options, function (o) { return toMin(o.value) >= s + 120 && !o.disabled; }) || [].find.call(ce.options, function (o) { return !o.disabled; });
    if (pick) ce.value = pick.value;
  }
  $('cStart').addEventListener('change', fillEnd);

  function showDay(k) {
    var bx = $('calBusy'), s = $('calStatus');
    busy = []; bx.hidden = true;
    var done = function (list) {
      busy = list || [];
      if (busy.length) {
        bx.innerHTML = '<b>Al bezet op deze dag:</b><br>' + busy.map(function (x) { return x.start + ' – ' + x.end; }).join('<br>') + '<br><small>We houden ' + BUFFER + ' minuten speling tussen optredens.</small>';
        bx.hidden = false; s.textContent = 'Er staat al een optreden, andere tijden zijn nog vrij'; s.className = 'cal-status warn';
      } else { s.textContent = 'Deze datum is nog helemaal vrij'; s.className = 'cal-status'; }
      fillTimes();
    };
    s.textContent = 'Beschikbaarheid ophalen…'; s.className = 'cal-status';
    if (DEMO) return done(DEMO_BUSY[k]);
    api('action=day&date=' + k).then(function (d) { BUFFER = d.buffer || BUFFER; done(d.busy); }).catch(function () { done(DEMO_BUSY[k]); });
  }

  $('calDays').addEventListener('click', function (e) {
    var b = e.target.closest('.cal-day'); if (!b || b.disabled) return;
    var p = b.dataset.k.split('-'); sel = new Date(+p[0], +p[1] - 1, +p[2]); render();
    $('calEmpty').hidden = true; $('calDone').hidden = true; $('calForm').hidden = false;
    $('calDate').textContent = cap(fmtD.format(sel));
    showDay(b.dataset.k);
    if (window.innerWidth < 900) $('calForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  $('calPrev').onclick = function () { view = new Date(view.getFullYear(), view.getMonth() - 1, 1); loadMonth(); };
  $('calNext').onclick = function () { view = new Date(view.getFullYear(), view.getMonth() + 1, 1); loadMonth(); };
  [].forEach.call(document.querySelectorAll('input[name=keuze]'), function (r) {
    r.addEventListener('change', function () { $('calBtn').textContent = this.value === 'direct' ? 'Boek en ga naar aanbetaling' : 'Houd deze datum 48 uur vast'; });
  });

  $('calForm').addEventListener('submit', function (e) {
    e.preventDefault(); var err = $('calErr'), btn = $('calBtn'); err.hidden = true;
    var fail = function (t, el) { err.textContent = t; err.hidden = false; if (el) el.focus(); };
    var n = $('cNaam').value.trim(), m = $('cMail').value.trim();
    if (!$('cStart').value || !$('cEind').value) return fail('Kies een begin- en eindtijd.', $('cStart'));
    if (!n) return fail('Vul je naam in.', $('cNaam'));
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(m)) return fail('Vul een geldig e-mailadres in.', $('cMail'));
    var keuze = document.querySelector('input[name=keuze]:checked').value;
    var data = { datum: key(sel), start: $('cStart').value, eind: $('cEind').value, act: $('cAct').value, naam: n, email: m,
      telefoon: $('cTel').value.trim(), plaats: $('cPlaats').value.trim(), opmerking: $('cOpm').value.trim(), keuze: keuze,
      website: $('cWeb').value, t: Math.round((Date.now() - t0) / 1000) };
    var label = btn.textContent; btn.disabled = true; btn.textContent = 'Even geduld…';
    var finish = function (res) {
      if (res.mode === 'direct' && res.redirect) { location.href = res.redirect; return; }
      $('calDoneH').textContent = res.mode === 'direct' ? 'Bijna klaar!' : 'Datum vastgehouden!';
      $('calDoneTxt').textContent = res.mode === 'direct'
        ? 'In het echt ga je nu direct door naar de aanbetaling van € 50,-.'
        : 'We houden ' + fmtD.format(sel) + ' van ' + data.start + ' tot ' + data.eind + ' voor je vast' + (res.expires ? ' tot ' + res.expires : ' (48 uur)') + '. Je krijgt een bevestiging per mail en we nemen snel contact met je op.';
      days[data.datum] = days[data.datum] || 'deels';
      $('calForm').hidden = true; $('calDone').hidden = false; render();
      btn.disabled = false; btn.textContent = label;
    };
    if (DEMO) return setTimeout(function () { finish({ mode: keuze }); }, 500);
    fetch(API + '?action=reserve', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (!d.ok) throw d; finish(d); })
      .catch(function (d) {
        btn.disabled = false; btn.textContent = label;
        fail((d && d.error) || 'Reserveren lukte niet. Mail ons via info@lanegra-entertainment.nl of bel +31 6 25 17 80 50.');
        if (sel) showDay(key(sel));
      });
  });
  $('calAgain').onclick = function () { sel = null; $('calDone').hidden = true; $('calEmpty').hidden = false; $('calForm').reset(); render(); };
  loadMonth();
})();
