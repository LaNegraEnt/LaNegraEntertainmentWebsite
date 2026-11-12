/* La Negra Entertainment – menu en aanvraagformulier */
(function () {
  'use strict';

  // Mobiel menu
  var b = document.querySelector('.menu-toggle'), n = document.getElementById('hoofdmenu');
  if (b && n) {
    b.addEventListener('click', function () {
      var o = n.classList.toggle('open');
      b.setAttribute('aria-expanded', o);
      b.setAttribute('aria-label', o ? b.dataset.close : b.dataset.open);
    });
    n.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') { n.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); }
    });
  }

  // Datumvelden: alleen vandaag tot 3 jaar vooruit; telefoon alleen cijfers (de server controleert hetzelfde)
  var MELD = {
    nl: ['Kies een datum tussen vandaag en 3 jaar vooruit.', 'Vul een geldig telefoonnummer in (alleen cijfers), of laat het veld leeg.'],
    en: ['Please choose a date between today and 3 years from now.', 'Please enter a valid phone number (digits only), or leave it empty.'],
    es: ['Elige una fecha entre hoy y dentro de 3 años.', 'Introduce un número de teléfono válido (solo cifras) o deja el campo vacío.'],
    de: ['Bitte wählen Sie ein Datum zwischen heute und in 3 Jahren.', 'Bitte geben Sie eine gültige Telefonnummer ein (nur Ziffern) oder lassen Sie das Feld leer.']
  };
  var TT = MELD[(document.documentElement.lang || 'nl').slice(0, 2)] || MELD.nl;
  function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function telOk(v) { v = v.trim(); if (!v) return true; if (!/^[0-9+()\s.\/-]{8,25}$/.test(v)) return false; var n = v.replace(/\D/g, '').length; return n >= 8 && n <= 15; }
  var vandaag = new Date(), tot = new Date(); tot.setFullYear(tot.getFullYear() + 3);
  document.querySelectorAll('input[type=date]').forEach(function (el) {
    if (!el.min) el.min = iso(vandaag);
    if (!el.max) el.max = iso(tot);
    el.addEventListener('input', function () { el.setCustomValidity(''); });
  });
  document.querySelectorAll('input[type=tel]').forEach(function (el) {
    el.addEventListener('input', function () { el.setCustomValidity(''); });
  });

  // Aanvraagformulier → zelfde verwerking als de chat (lanegra-chat/chat-lead.php)
  var started = Date.now();
  function src() {
    try {
      var s = JSON.parse(sessionStorage.getItem('lnc_src') || '{}');
      if (!s.referrer) { s.referrer = document.referrer || 'direct'; s.landing = location.pathname; }
      return s;
    } catch (e) { return {}; }
  }
  document.querySelectorAll('form.lead-form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = f.querySelector('.form-msg'), btn = f.querySelector('button[type=submit]');
      var dEl = f.elements.datum, tEl = f.elements.telefoon;
      if (dEl && dEl.value) dEl.setCustomValidity(dEl.value < dEl.min || dEl.value > dEl.max ? TT[0] : '');
      if (tEl) tEl.setCustomValidity(telOk(tEl.value) ? '' : TT[1]);
      if (!f.checkValidity()) { f.reportValidity(); return; }
      var v = function (k) { var el = f.elements[k]; return el ? el.value.trim() : ''; };
      var payload = {
        lead: { type: v('type'), act: v('act'), datum: v('datum'), plaats: v('plaats'), gasten: v('gasten'),
                naam: v('naam'), email: v('email'), telefoon: v('telefoon'), opmerking: v('opmerking') },
        lang: document.documentElement.lang || 'nl', source: src(), page: location.href,
        transcript: [{ role: 'user', content: 'Aanvraag via het formulier op de website' }],
        website: v('website'), t: Math.round((Date.now() - started) / 1000)
      };
      btn.disabled = true; msg.className = 'form-msg'; msg.textContent = f.dataset.sending;
      fetch(f.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) {
            f.reset(); msg.className = 'form-msg ok'; msg.textContent = f.dataset.ok;
            if (window.gtag) try { window.gtag('event', 'generate_lead', { method: 'form' }); } catch (x) {}
            if (window.fbq) try { window.fbq('track', 'Lead'); } catch (x) {}
          } else { msg.className = 'form-msg err'; msg.textContent = (d && d.error) || f.dataset.err; }
        })
        .catch(function () { msg.className = 'form-msg err'; msg.textContent = f.dataset.err; })
        .then(function () { btn.disabled = false; });
    });
  });
})();

/* Video-band: pas laden/afspelen als hij in beeld komt, geluidsknop */
(function () {
  var vids = document.querySelectorAll('video[data-autoplay]');
  if (!vids.length) return;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  vids.forEach(function (v) {
    if (reduce) return;
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        es.forEach(function (e) {
          if (e.isIntersecting) { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
          else v.pause();
        });
      }, { threshold: 0.25 }).observe(v);
    } else { v.play(); }
    var btn = v.parentNode.querySelector('.bts-mute');
    if (!btn) return;
    btn.addEventListener('click', function () {
      v.muted = !v.muted;
      if (!v.muted && v.paused) v.play();
      btn.setAttribute('aria-pressed', v.muted ? 'false' : 'true');
      btn.setAttribute('aria-label', v.muted ? btn.dataset.on : btn.dataset.off);
    });
  });
})();

/* Dienstpagina's: "Bekijk met geluid" zet de YouTube-video aan met geluid en start hem vanaf het begin */
(function () {
  var link = document.querySelector('a[href="#video"]');
  var frame = document.querySelector('#video iframe');
  if (!link || !frame) return;
  function cmd(func, args) {
    frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: func, args: args || [] }), '*');
  }
  link.addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('video').scrollIntoView({ behavior: 'smooth', block: 'center' });
    cmd('unMute'); cmd('setVolume', [100]); cmd('seekTo', [0, true]); cmd('playVideo');
  });
})();
