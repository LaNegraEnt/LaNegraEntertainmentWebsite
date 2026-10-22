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
          } else { msg.className = 'form-msg err'; msg.textContent = f.dataset.err; }
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
