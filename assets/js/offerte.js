/* Offerte samenstellen – berekent het overzicht en stuurt de keuze naar stuur-offerte.php (zelfde velden als de oude pagina). */
(function () {
  var form = document.getElementById('offerte-form');
  if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var EN = document.documentElement.lang === 'en';   // en/quote.html gebruikt hetzelfde script
  var L = EN ? {
    none: 'None', notChosen: 'Not chosen', dancer: ' dancer', dancers: ' dancers', onRequest: '5+ (on request)', hours: ' hours', ws: ' — 1.5 hours',
    notFilled: 'Not filled in yet', yes: 'Yes', no: 'No', djNote: 'DJ chosen (2.5 hours, +€400). ', name: 'Please enter your first name.',
    mail: 'Please enter a valid email address.', date: 'Please enter the date of your event.', busy: 'Sending…', btn: 'Send my choice',
    fail: 'Sending failed. Please try again or email us at info@lanegra-entertainment.nl.', post: '../stuur-offerte.php',
    extras: ['Fire breather / Limbo show', 'Steel band 45 min', 'Latin Entertainment Band — 2x 45 min', 'DJ — 2.5 hours']
  } : {
    none: 'Geen', notChosen: 'Niet gekozen', dancer: ' danseres', dancers: ' danseressen', onRequest: '5+ (op aanvraag)', hours: ' uur', ws: ' — 1,5 uur',
    notFilled: 'Nog niet ingevuld', yes: 'Ja', no: 'Nee', djNote: 'DJ gekozen (2,5 uur, +€400,-). ', name: 'Vul je voornaam in.',
    mail: 'Vul een geldig e-mailadres in.', date: 'Vul de datum van je evenement in.', busy: 'Bezig met versturen…', btn: 'Keuze doorgeven',
    fail: 'Versturen lukte niet. Probeer het opnieuw of mail ons via info@lanegra-entertainment.nl.', post: 'stuur-offerte.php',
    extras: ['Vuurspuwer / Limboshow', 'Steelband 45 min', 'Latin Entertainment Band — 2x 45 min', 'DJ — 2,5 uur']
  };
  var PB = { 'Basic 2 uur': 195, 'Basic 4 uur': 250, 'Silver 2 uur': 250, 'Silver 4 uur': 300, 'Exclusive 2 uur': 550, 'Exclusive 4 uur': 750 };
  var EXTRAS = [
    ['extra-vuur', L.extras[0], 550],
    ['extra-steel', L.extras[1], 550],
    ['extra-latin-band', L.extras[2], 1500],
    ['extra-dj', L.extras[3], 400]
  ];

  function euro(n) {
    var c = Math.round((Number(n) || 0) * 100);
    return c % 100 ? '€ ' + (c / 100).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '€ ' + (c / 100).toLocaleString('nl-NL') + ',-';
  }
  function num(id) { var el = $(id); return el && el.value !== '' ? (parseFloat(String(el.value).replace(',', '.')) || 0) : 0; }
  function checked(name) { return form.querySelector('input[name="' + name + '"]:checked'); }
  function val(id) { return ($(id).value || '').trim(); }

  var KM_TARIEF = 0.23;   // € per km

  function calc() {
    var pk = checked('pakket'), d = checked('danseressen'), pb = checked('photobooth'), huur = checked('pb-huur');
    var c = {
      pakket: pk ? pk.value : L.notChosen, pakketPrijs: pk ? parseInt(pk.dataset.prijs, 10) || 0 : 0,
      extras: [], extrasPrijs: 0, flags: {},
      dans: d ? d.value : '0', dansPrijs: d ? parseInt(d.dataset.dansPrijs, 10) || 0 : 0,
      pb: pb ? pb.value : 'Geen', huur: huur ? huur.value : '2 uur',
      ws: Array.prototype.map.call(form.querySelectorAll('.workshop-type:checked'), function (w) { return w.value; }),
      kmEnkel: Math.max(0, Math.round(num('f-km')))
    };
    c.km = c.kmEnkel * 2;                                    // heen en terug
    c.reis = Math.round(c.km * KM_TARIEF * 100) / 100;   // reiskosten altijd berekend, nooit zelf in te vullen
    EXTRAS.forEach(function (x) {
      var on = $(x[0]).checked; c.flags[x[0]] = on;
      if (on) { c.extras.push(x[1] + ' (+' + euro(x[2]) + ')'); c.extrasPrijs += x[2]; }
    });
    c.pbPrijs = c.pb === 'Geen' ? 0 : (PB[c.pb + ' ' + c.huur] || 0);
    c.wsPrijs = c.ws.length ? 200 : 0;
    c.sub = c.pakketPrijs + c.extrasPrijs + c.dansPrijs + c.pbPrijs + c.wsPrijs + c.reis;
    c.btw = Math.round(c.sub * 9) / 100;                 // 9% btw, op de cent
    c.totaal = Math.round((c.sub + c.btw) * 100) / 100;
    return c;
  }

  // De huurduur-waarden blijven "2 uur"/"4 uur" (sleutels van PB); alleen de tekst wordt vertaald
  function huurTekst(h) { return h.replace(' uur', L.hours); }

  function render() {
    var c = calc();
    var set = function (id, v) { $(id).textContent = v; };
    set('sum-pakket', c.pakket);
    set('sum-extras', c.extras.length ? c.extras.join(' + ') : L.none);
    set('sum-dans', c.dans === '0' ? L.none : c.dans === '5+' ? L.onRequest : c.dans + (c.dans === '1' ? L.dancer : L.dancers));
    set('sum-pb', c.pb === 'Geen' ? L.none : c.pb + ' — ' + huurTekst(c.huur));
    set('sum-workshop', c.ws.length ? c.ws.join(', ') + L.ws : L.none);
    set('sum-km', c.km ? c.kmEnkel + ' km × 2 = ' + c.km + ' km × € 0,23' : L.notFilled);
    set('sum-reis', euro(c.reis));
    var rk = $('f-reiskosten'); if (rk) rk.value = c.km ? euro(c.reis) : '';
    set('sum-sub', euro(c.sub));
    set('sum-btw', euro(c.btw));
    set('sum-total', euro(c.totaal));
  }

  form.addEventListener('change', render);
  form.addEventListener('input', render);
  render();

  var geladen = Date.now();
  var btn = $('offerte-btn'), err = $('form-err'), ok = $('offerte-success');
  function fail(msg, el) {
    err.textContent = msg; err.hidden = false;
    if (el) el.focus();
  }

  btn.addEventListener('click', function () {
    var naam = val('f-naam'), lnaam = val('f-achternaam'), email = val('f-email'), datum = val('f-datum');
    err.hidden = true;
    if (!naam) return fail(L.name, $('f-naam'));
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail(L.mail, $('f-email'));
    if (!datum) return fail(L.date, $('f-datum'));

    var c = calc();
    var d = new FormData();
    d.append('naam', (naam + ' ' + lnaam).trim());
    d.append('email', email);
    d.append('telefoon', val('f-tel') || '-');
    d.append('datum_evenement', datum);
    d.append('locatie', val('f-locatie') || '-');
    d.append('kilometers', c.km ? c.km + (EN ? ' km return (2 × ' : ' km heen en terug (2 × ') + c.kmEnkel + ' km)' : '-');
    d.append('reiskosten', euro(c.reis));
    d.append('pakket', c.pakket);
    d.append('danseressen', c.dans === '0' ? '0' : c.dans + (c.dans === '1' ? L.dancer : L.dancers));
    d.append('vuurspuwer', c.flags['extra-vuur'] ? L.yes + ' (+€550,-)' : L.no);
    d.append('steelband', c.flags['extra-steel'] ? L.yes + ' (+€550,-)' : L.no);
    d.append('latin_entertainment_band', c.flags['extra-latin-band'] ? L.yes + ' (+€1.500,- / 2x 45 min)' : L.no);
    d.append('dj', c.flags['extra-dj'] ? L.yes + ' (+€400,-)' : L.no);
    d.append('photobooth', c.pb === 'Geen' ? L.none : c.pb + ' ' + huurTekst(c.huur));
    d.append('workshop', c.ws.length ? c.ws.join(', ') + ' / 1.5' + L.hours + ' (+€200,-)' : L.none);
    d.append('lang', EN ? 'en' : 'nl');
    var opm = val('f-opmerking') || '-';
    if (c.flags['extra-dj']) opm = L.djNote + (opm === '-' ? '' : opm);
    d.append('opmerkingen', opm.trim() || '-');
    d.append('subtotaal_excl_btw', euro(c.sub));
    d.append('btw_9pct', euro(c.btw));
    d.append('totaal_incl_btw', euro(c.totaal));
    d.append('website', val('f-web'));                                   // lokveld voor bots
    d.append('t', String(Math.round((Date.now() - geladen) / 1000)));   // seconden op de pagina
    d.append('_subject', 'Boeking: ' + (naam + ' ' + lnaam).trim() + ' — ' + datum);

    btn.disabled = true; btn.textContent = L.busy;
    fetch(L.post, { method: 'POST', body: d })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) throw new Error('not ok');
        btn.hidden = true; ok.hidden = false;
        ok.scrollIntoView({ behavior: 'smooth', block: 'center' });
      })
      .catch(function () {
        btn.disabled = false; btn.textContent = L.btn;
        fail(L.fail);
      });
  });
})();
