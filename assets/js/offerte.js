/* Offerte samenstellen – berekent het overzicht en stuurt de keuze naar stuur-offerte.php (zelfde velden als de oude pagina). */
(function () {
  var form = document.getElementById('offerte-form');
  if (!form) return;
  var $ = function (id) { return document.getElementById(id); };
  var PB = { 'Basic 2 uur': 195, 'Basic 4 uur': 250, 'Silver 2 uur': 250, 'Silver 4 uur': 300, 'Exclusive 2 uur': 550, 'Exclusive 4 uur': 750 };
  var EXTRAS = [
    ['extra-vuur', 'Vuurspuwer / Limboshow', 550],
    ['extra-steel', 'Steelband 45 min', 550],
    ['extra-latin-band', 'Latin Entertainment Band — 2x 45 min', 1500],
    ['extra-dj', 'DJ — 2,5 uur', 400]
  ];

  function euro(n) { return '€ ' + Math.round(Number(n) || 0).toLocaleString('nl-NL') + ',-'; }
  function num(id) { var el = $(id); return el && el.value !== '' ? (parseFloat(String(el.value).replace(',', '.')) || 0) : 0; }
  function checked(name) { return form.querySelector('input[name="' + name + '"]:checked'); }
  function val(id) { return ($(id).value || '').trim(); }

  function calc() {
    var pk = checked('pakket'), d = checked('danseressen'), pb = checked('photobooth'), huur = checked('pb-huur');
    var c = {
      pakket: pk ? pk.value : 'Niet gekozen', pakketPrijs: pk ? parseInt(pk.dataset.prijs, 10) || 0 : 0,
      extras: [], extrasPrijs: 0, flags: {},
      dans: d ? d.value : '0', dansPrijs: d ? parseInt(d.dataset.dansPrijs, 10) || 0 : 0,
      pb: pb ? pb.value : 'Geen', huur: huur ? huur.value : '2 uur',
      ws: Array.prototype.map.call(form.querySelectorAll('.workshop-type:checked'), function (w) { return w.value; }),
      km: num('f-km'), reis: num('f-reiskosten')
    };
    EXTRAS.forEach(function (x) {
      var on = $(x[0]).checked; c.flags[x[0]] = on;
      if (on) { c.extras.push(x[1] + ' (+' + euro(x[2]) + ')'); c.extrasPrijs += x[2]; }
    });
    c.pbPrijs = c.pb === 'Geen' ? 0 : (PB[c.pb + ' ' + c.huur] || 0);
    c.wsPrijs = c.ws.length ? 200 : 0;
    c.sub = c.pakketPrijs + c.extrasPrijs + c.dansPrijs + c.pbPrijs + c.wsPrijs + c.reis;
    c.btw = Math.round(c.sub * 0.09);
    c.totaal = c.sub + c.btw;
    return c;
  }

  function render() {
    var c = calc();
    var set = function (id, v) { $(id).textContent = v; };
    set('sum-pakket', c.pakket);
    set('sum-extras', c.extras.length ? c.extras.join(' + ') : 'Geen');
    set('sum-dans', c.dans === '0' ? 'Geen' : c.dans === '5+' ? '5+ (op aanvraag)' : c.dans + ' danseres' + (c.dans === '1' ? '' : 'sen'));
    set('sum-pb', c.pb === 'Geen' ? 'Geen' : c.pb + ' — ' + c.huur);
    set('sum-workshop', c.ws.length ? c.ws.join(', ') + ' — 1,5 uur' : 'Geen');
    set('sum-km', c.km ? c.km + ' km' : 'Nog niet ingevuld');
    set('sum-reis', euro(c.reis));
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
    if (!naam) return fail('Vul je voornaam in.', $('f-naam'));
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail('Vul een geldig e-mailadres in.', $('f-email'));
    if (!datum) return fail('Vul de datum van je evenement in.', $('f-datum'));

    var c = calc();
    var d = new FormData();
    d.append('naam', (naam + ' ' + lnaam).trim());
    d.append('email', email);
    d.append('telefoon', val('f-tel') || '-');
    d.append('datum_evenement', datum);
    d.append('locatie', val('f-locatie') || '-');
    d.append('kilometers', c.km ? c.km + ' km' : '-');
    d.append('reiskosten', euro(c.reis));
    d.append('pakket', c.pakket);
    d.append('danseressen', c.dans);
    d.append('vuurspuwer', c.flags['extra-vuur'] ? 'Ja (+€550,-)' : 'Nee');
    d.append('steelband', c.flags['extra-steel'] ? 'Ja (+€550,-)' : 'Nee');
    d.append('latin_entertainment_band', c.flags['extra-latin-band'] ? 'Ja (+€1.500,- / 2x 45 min)' : 'Nee');
    d.append('dj', c.flags['extra-dj'] ? 'Ja (+€400,- / 2,5 uur)' : 'Nee');
    d.append('photobooth', c.pb === 'Geen' ? 'Geen' : c.pb + ' ' + c.huur);
    d.append('workshop', c.ws.length ? c.ws.join(', ') + ' / 1.5 uur (+€200,-)' : 'Geen');
    var opm = val('f-opmerking') || '-';
    if (c.flags['extra-dj']) opm = 'DJ gekozen (2,5 uur, +€400,-). ' + (opm === '-' ? '' : opm);
    d.append('opmerkingen', opm.trim() || '-');
    d.append('subtotaal_excl_btw', euro(c.sub));
    d.append('btw_9pct', euro(c.btw));
    d.append('totaal_incl_btw', euro(c.totaal));
    d.append('website', val('f-web'));                                   // lokveld voor bots
    d.append('t', String(Math.round((Date.now() - geladen) / 1000)));   // seconden op de pagina
    d.append('_subject', 'Boeking: ' + (naam + ' ' + lnaam).trim() + ' — ' + datum);

    btn.disabled = true; btn.textContent = 'Bezig met versturen…';
    fetch('stuur-offerte.php', { method: 'POST', body: d })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) throw new Error('not ok');
        btn.hidden = true; ok.hidden = false;
        ok.scrollIntoView({ behavior: 'smooth', block: 'center' });
      })
      .catch(function () {
        btn.disabled = false; btn.textContent = 'Keuze doorgeven';
        fail('Versturen lukte niet. Probeer het opnieuw of mail ons via info@lanegra-entertainment.nl.');
      });
  });
})();
