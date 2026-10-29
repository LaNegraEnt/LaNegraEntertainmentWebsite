/* Interne offertetool: berekent totalen, maakt de akkoord-link en een HTML-mail in de huisstijl. */
(function () {
  var ACTS = {
    samba: { oms: 'Samba danseressen — show van 30 minuten', prijs: 400, aan: 50, extra: 'Professionele samba danseressen in kleurrijke kostuums, show van 30 minuten. Ideaal als spectaculaire binnenkomer, openingsact of feestelijke verrassing.' },
    brassband: { oms: 'Brassband — 1 à 2 sets van 20 à 30 minuten', prijs: 650, aan: 50, extra: 'Energieke brassband die lopend of op locatie optreedt. Perfect voor binnenkomst, parade, festival of feestelijke opening.' },
    salsaBrassband: { oms: 'Salsa Brassband — live Latin en Caribische ritmes', prijs: 1150, aan: 50, extra: 'Brassband met basgitaar, piano en zanger: salsa en Latin live, met een interactieve show.' },
    latinBand: { oms: 'Latin Entertainment Band — 2 x 45 minuten', prijs: 1500, aan: 50, extra: 'Live Latin band met zanger, piano, bas en ritmesectie: salsa, merengue en Latin pop.' },
    workshop: { oms: 'Workshop — 1,5 uur', prijs: 200, aan: 50, extra: 'Keuze uit salsa, bachata, konpa en/of percussie. Interactieve workshop als extra beleving.' },
    dj: { oms: 'DJ — 2,5 uur', prijs: 400, aan: 50, extra: 'DJ voor 2,5 uur, met muziek afgestemd op je gasten en programma.' },
    photobooth: { oms: 'Photobooth — interactieve fotobeleving', prijs: 450, aan: 50, extra: 'Photobooth als extra beleving voor gasten. Definitieve invulling afhankelijk van duur en locatie.' }
  };
  var ORIGIN = { lat: 51.9244, lon: 4.4777, naam: 'Rotterdam' };
  var $ = function (id) { return document.getElementById(id); };
  var v = function (id) { return ($(id).value || '').trim(); };

  function money(s) { if (!s) return 0; s = String(s).replace(/€|\s/g, ''); if (s.indexOf(',') > -1) s = s.replace(/\./g, '').replace(',', '.'); var n = parseFloat(s); return isNaN(n) ? 0 : n; }
  function eur(n) { var p = (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2).split('.'); p[0] = p[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.'); return '€ ' + p[0] + ',' + p[1]; }
  function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function datumNL() { var d = v('datum'); if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) return d; var p = d.split('-'); return p[2] + '-' + p[1] + '-' + p[0]; }

  function calc() {
    var sub = money(v('subtotaal')), reis = money(v('reiskosten')), pct = parseFloat(v('btwPercentage')) || 0, aan = v('aanbetaling') ? money(v('aanbetaling')) : 50;
    var base = sub + reis, btw = base * pct / 100, tot = base + btw;
    var half = Math.round(tot * 50) / 100, aanb = Math.min(aan, tot), voor = Math.max(half - aanb, 0);
    return { sub: sub, reis: reis, pct: pct, base: base, btw: btw, tot: tot, aan: aanb, voor: voor, rest: Math.max(tot - aanb - voor, 0) };
  }

  function params(c) {
    var p = new URLSearchParams();
    p.set('naam', v('naam')); p.set('email', v('email')); p.set('tel', v('telefoon')); p.set('datum', datumNL()); p.set('locatie', v('locatie'));
    p.set('afstandKm', v('afstandKm')); p.set('pakket', v('omschrijving')); p.set('extras', v('extra')); p.set('opmerking', v('extra'));
    p.set('reiskosten', v('reiskosten') ? eur(c.reis) : ''); p.set('subtotaal', v('subtotaal') ? eur(c.sub) : '');
    p.set('btw', eur(c.btw)); p.set('totaal', eur(c.tot)); p.set('aanbetaling', eur(c.aan)); p.set('voorschot', eur(c.voor)); p.set('restbetaling', eur(c.rest));
    return p;
  }
  function link(c) { return (v('basisurl') || 'https://www.lanegra-entertainment.nl/opdrachtbevestiging-akkoord.html') + '?' + params(c || calc()).toString(); }

  function row(k, val, strong) {
    return '<tr><td style="padding:9px 0;border-top:1px solid #ECEDEC;color:#777777;font-size:14px;width:44%;">' + k + '</td><td style="padding:9px 0;border-top:1px solid #ECEDEC;color:#1F231E;font-size:14px;">' +
      (strong ? '<b style="color:#D74B27;font-size:16px;">' + val + '</b>' : val) + '</td></tr>';
  }
  function box(label, inner, bg) {
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:' + (bg || '#F6F7F7') + ';border-radius:14px;"><tr><td style="padding:16px 20px;">' +
      '<p style="margin:0 0 8px;color:#1F231E;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">' + label + '</p>' + inner + '</td></tr></table>';
  }
  function mailHtml(c) {
    var l = esc(link(c));
    return '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Offerte La Negra Entertainment</title></head>' +
      '<body style="margin:0;padding:0;background:#F6F7F7;font-family:Arial,Helvetica,sans-serif;color:#1F231E;">' +
      '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F7F7;"><tr><td align="center" style="padding:24px 12px;">' +
      '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;">' +
      '<tr><td style="height:6px;background:#FDD22C;font-size:0;line-height:0;">&nbsp;</td></tr>' +
      '<tr><td align="center" style="padding:26px 36px 14px;"><img src="https://lanegra-entertainment.nl/assets/img/logo.png" width="170" alt="La Negra Entertainment" style="display:block;width:170px;height:auto;border:0;"></td></tr>' +
      '<tr><td style="padding:8px 36px 28px;">' +
      '<p style="margin:0 0 10px;color:#D74B27;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">Offerte op maat</p>' +
      '<h1 style="margin:0 0 16px;font-size:30px;line-height:1.15;color:#1F231E;">Je offerte staat klaar</h1>' +
      '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4B4D4B;">Beste ' + esc(v('naam') || 'relatie') + ',<br>Hierbij ontvang je je offerte van La Negra Entertainment.</p>' +
      box('Samenvatting', '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' + row('Datum', esc(datumNL() || '-')) + row('Locatie', esc(v('locatie') || '-')) +
        row('Entertainment', esc(v('omschrijving') || '-').replace(/\n/g, '<br>')) + row('Reiskosten', v('reiskosten') ? eur(c.reis) : '-') + row('Subtotaal excl. btw', eur(c.base)) +
        row('Btw (' + c.pct + '%)', eur(c.btw)) + row('Totaal incl. btw', eur(c.tot), true) + row('Reserveringsaanbetaling (direct)', eur(c.aan)) + row('Voorschot tot 50% (binnen 14 dagen)', eur(c.voor)) + row('Restbetaling (uiterlijk 7 dagen na het optreden)', eur(c.rest)) + '</table>', '#FFFBEA') +
      (v('extra') ? box('Toelichting', '<p style="margin:0;font-size:14px;line-height:1.7;color:#4B4D4B;">' + esc(v('extra')).replace(/\n/g, '<br>') + '</p>') : '') +
      '<p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4B4D4B;">Via de knop bekijk je je offerte, geef je akkoord en ga je door naar de aanbetaling.</p>' +
      '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;"><tr><td style="background:#FDD22C;border-radius:999px;"><a href="' + l + '" target="_blank" style="display:inline-block;padding:15px 30px;color:#1F231E;text-decoration:none;font-size:15px;font-weight:bold;">Bekijk offerte &amp; geef akkoord &rarr;</a></td></tr></table>' +
      '<p style="margin:0;font-size:14px;line-height:1.7;color:#4B4D4B;">Met vriendelijke groet,<br><b>La Negra Entertainment</b></p></td></tr>' +
      '<tr><td align="center" style="padding:24px 36px;background:#1F231E;"><p style="margin:0 0 6px;color:#FDD22C;font-size:14px;font-weight:bold;">Feel the Rhythm. Live the Experience.</p>' +
      '<p style="margin:0;color:#BFC1BE;font-size:12px;line-height:1.9;">lanegra-entertainment.nl · info@lanegra-entertainment.nl · +31 6 25 17 80 50<br>KvK 67106951 · Btw NL002420332B84</p></td></tr>' +
      '</table></td></tr></table></body></html>';
  }

  var timer;
  function render() {
    var c = calc();
    $('pv-base').textContent = eur(c.base); $('pv-btw').textContent = eur(c.btw) + ' (' + c.pct + '%)'; $('pv-totaal').textContent = eur(c.tot);
    $('pv-aan').textContent = eur(c.aan) + ' + voorschot ' + eur(c.voor); $('pv-rest').textContent = eur(c.rest);
    $('openLink').href = link(c);
    clearTimeout(timer);
    timer = setTimeout(function () { $('mailPreview').srcdoc = mailHtml(c); }, 150);
  }
  function notice(msg) { var n = $('notice'); n.textContent = msg; n.hidden = false; clearTimeout(n._t); n._t = setTimeout(function () { n.hidden = true; }, 2800); }
  function copy(text, ok) {
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { notice(ok); }, function () { notice('Kopiëren lukte niet automatisch.'); });
  }

  // Acts optellen
  document.querySelectorAll('.ao-act').forEach(function (b) {
    b.addEventListener('click', function () {
      var a = ACTS[b.dataset.act]; if (!a) return;
      var oms = v('omschrijving'), ex = v('extra');
      $('omschrijving').value = oms ? oms + ' + ' + a.oms : a.oms;
      $('subtotaal').value = eur(money(v('subtotaal')) + a.prijs);
      $('extra').value = ex ? ex + '\n' + a.extra : a.extra;
      if (!v('aanbetaling')) $('aanbetaling').value = eur(a.aan);
      render(); notice('Toegevoegd: ' + a.oms);
    });
  });
  $('pdfBtn').addEventListener('click', function () { window.open(link().replace('opdrachtbevestiging-akkoord.html', 'offerte-pdf.html'), '_blank'); });
  $('clearBtn').addEventListener('click', function () { ['omschrijving', 'subtotaal', 'extra', 'aanbetaling'].forEach(function (id) { $(id).value = ''; }); render(); });

  // Reiskosten
  try { var t = localStorage.getItem('lnTarief'); if (t) $('tarief').value = t; } catch (e) {}
  function reisUitKm() {
    var km = money(v('afstandKm')), tarief = money(v('tarief'));
    try { localStorage.setItem('lnTarief', v('tarief')); } catch (e) {}
    if (km && tarief) $('reiskosten').value = eur(km * tarief);
    render();
  }
  $('tarief').addEventListener('change', reisUitKm);
  $('afstandKm').addEventListener('change', reisUitKm);
  $('travelBtn').addEventListener('click', function () {
    var plaats = v('locatie'), msg = $('travelMsg');
    if (!plaats) { msg.textContent = 'Vul eerst een locatie in.'; $('locatie').focus(); return; }
    msg.textContent = 'Bezig met berekenen…';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=nl,be,de&q=' + encodeURIComponent(plaats))
      .then(function (r) { return r.json(); })
      .then(function (g) {
        if (!g.length) throw new Error('Plaats niet gevonden.');
        return fetch('https://router.project-osrm.org/route/v1/driving/' + ORIGIN.lon + ',' + ORIGIN.lat + ';' + g[0].lon + ',' + g[0].lat + '?overview=false');
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.routes || !d.routes.length) throw new Error('Geen route gevonden.');
        var km = Math.round(d.routes[0].distance / 1000) * 2;
        $('afstandKm').value = km;
        msg.textContent = km + ' km retour vanaf ' + ORIGIN.naam + (v('tarief') ? '.' : '. Vul een tarief per km in om de reiskosten te berekenen.');
        reisUitKm();
      })
      .catch(function (e) { msg.textContent = (e && e.message) || 'Berekenen lukte niet. Vul de km zelf in.'; });
  });

  // Acties
  $('copyLink').addEventListener('click', function () { copy(link(), 'Akkoord-link gekopieerd.'); });
  $('copyHtml').addEventListener('click', function () { copy(mailHtml(calc()), 'HTML-mail gekopieerd.'); });
  $('mailKlant').addEventListener('click', function () {
    if (!v('email')) { notice('Vul eerst het e-mailadres van de klant in.'); $('email').focus(); return; }
    var c = calc();
    var body = 'Beste ' + (v('naam') || 'relatie') + ',\n\nHierbij ontvang je je offerte van La Negra Entertainment.\n\nDatum: ' + (datumNL() || '-') + '\nLocatie: ' + (v('locatie') || '-') +
      '\nEntertainment: ' + (v('omschrijving') || '-') + '\nReiskosten: ' + (v('reiskosten') ? eur(c.reis) : '-') + '\nSubtotaal excl. btw: ' + eur(c.base) + '\nBtw: ' + eur(c.btw) +
      '\nTotaal incl. btw: ' + eur(c.tot) + '\nReserveringsaanbetaling (direct): ' + eur(c.aan) + '\nVoorschot tot 50% (binnen 14 dagen): ' + eur(c.voor) + '\nRestbetaling (uiterlijk 7 dagen na het optreden): ' + eur(c.rest) + '\n\nToelichting:\n' + (v('extra') || '-') +
      '\n\nVia deze link bekijk je je offerte, geef je akkoord en ga je door naar de aanbetaling:\n' + link(c) + '\n\nMet vriendelijke groet,\n\nLa Negra Entertainment\ninfo@lanegra-entertainment.nl\n+31 6 25 17 80 50';
    window.location.href = 'mailto:' + encodeURIComponent(v('email')) + '?subject=' + encodeURIComponent('Je offerte van La Negra Entertainment') + '&body=' + encodeURIComponent(body);
  });
  $('mailMij').addEventListener('click', function () {
    var body = 'Kopie van aangemaakte offerte\n\nKlant:\n' + (v('naam') || '-') + '\n' + (v('email') || '-') + '\n' + (v('telefoon') || '-') + '\n\nAkkoord-link:\n' + link();
    window.location.href = 'mailto:' + encodeURIComponent(v('jouwmail') || 'info@lanegra-entertainment.nl') + '?subject=' + encodeURIComponent('Kopie offerte - ' + (v('naam') || 'nieuwe klant')) + '&body=' + encodeURIComponent(body);
  });
  $('saveLead').addEventListener('click', function () {
    var fd = new FormData();
    fd.append('voornaam', v('naam')); fd.append('achternaam', ''); fd.append('email', v('email')); fd.append('telefoon', v('telefoon'));
    fd.append('type_evenement', 'Admin offerte'); fd.append('gewenste_act', v('omschrijving')); fd.append('bericht', v('extra'));
    if ($('mailing_toestemming').checked) fd.append('mailing_toestemming', 'ja');
    fetch('save-lead.php', { method: 'POST', body: fd })
      .then(function (r) { if (!r.ok) throw 0; notice('Lead opgeslagen.'); })
      .catch(function () { notice('Lead kon niet worden opgeslagen.'); });
  });

  document.querySelectorAll('input, textarea, select').forEach(function (el) { el.addEventListener('input', render); el.addEventListener('change', render); });
  render();
})();
