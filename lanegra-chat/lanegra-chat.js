/*!
 * La Negra Entertainment – Website Assistant
 * Eén regel in je HTML, vlak voor de afsluitende body-tag:
 *   <script src="/lanegra-chat/lanegra-chat.js"
 *           data-lead-url="/lanegra-chat/chat-lead.php"
 *           data-ai-url="/lanegra-chat/ai-chat.php" defer></script>
 * Laat data-ai-url weg om zonder AI (alleen begeleide aanvraag) te draaien.
 * Afbeeldingen (launcher.png, avatar.png, wordmark.png) worden uit dezelfde map geladen.
 */
(function () {
  'use strict';
  if (window.__lanegraChat) return;
  window.__lanegraChat = true;

  var S = document.currentScript || {};
  var ds = S.dataset || {};
  var base = (S.src || '').replace(/[^\/]*$/, '');
  var CFG = {
    leadUrl: ds.leadUrl || base + 'chat-lead.php',
    aiUrl: ds.aiUrl || '',
    launcher: ds.launcher || base + 'launcher.png',
    avatar: ds.avatar || base + 'avatar.png',
    wordmark: ds.wordmark || base + 'wordmark.png',
    teaserDelay: parseInt(ds.teaserDelay || '20000', 10), // ms; 0 = uit
    email: 'info@lanegra-entertainment.nl',
    phone: '+31 6 25 17 80 50'
  };

  /* ---------- Iconen (eigen lijn-iconen, geen emoji) ---------- */
  var I = {
    note: '<svg viewBox="0 0 24 24"><path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/></svg>',
    euro: '<svg viewBox="0 0 24 24"><path d="M18 6.5A7 7 0 1 0 18 17.5"/><path d="M4 10h9M4 14h9"/></svg>',
    cal: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M7 14h2M11 14h2M15 14h2M7 17h2M11 17h2"/></svg>',
    mail: '<svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    phone: '<svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>',
    chev: '<svg viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>',
    send: '<svg viewBox="0 0 24 24"><path d="M7 4.5v15l12-7.5z" fill="currentColor" stroke="none"/></svg>',
    check: '<svg viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg>',
    redo: '<svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>'
  };

  /* ---------- Stijl ---------- */
  var CSS = '' +
  ':root{--lnc-bg:#0c0a07;--lnc-bg2:#1a140c;--lnc-gold:#fcb00a;--lnc-gold2:#fca208;--lnc-ink:#0c0a07;--lnc-text:#ffffff;--lnc-muted:#bdb4a6;--lnc-line:rgba(252,176,10,.85);--lnc-font:"Poppins",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}' +
  '#lnc-launch{position:fixed;right:14px;bottom:12px;z-index:99998;width:60px;height:73px;border:0;padding:0;background:url(' + JSON.stringify(CFG.launcher) + ') center/contain no-repeat;cursor:pointer;filter:drop-shadow(0 8px 18px rgba(0,0,0,.4));transition:transform .2s;animation:lnc-bob 3.2s ease-in-out infinite}' +
  '#lnc-launch:hover{transform:scale(1.06)}#lnc-launch.open{animation:none;transform:scale(.88)}' +
  '@keyframes lnc-bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}' +
  '@media (prefers-reduced-motion:reduce){#lnc-launch{animation:none}}' +
  '#lnc-teaser{position:fixed;right:24px;bottom:94px;z-index:99998;max-width:250px;background:var(--lnc-bg);color:var(--lnc-text);font:14px/1.45 var(--lnc-font);padding:12px 34px 12px 14px;border:1.5px solid var(--lnc-line);border-radius:16px 16px 4px 16px;box-shadow:0 10px 28px rgba(0,0,0,.35);cursor:pointer;display:none}' +
  '#lnc-teaser b{color:var(--lnc-gold);display:block;font-size:15px}#lnc-teaser .x{position:absolute;top:6px;right:10px;color:var(--lnc-muted);font-size:18px;line-height:1}' +
  '#lnc-box{position:fixed;right:18px;bottom:96px;z-index:99999;width:410px;max-width:calc(100vw - 32px);height:620px;max-height:calc(100vh - 150px);background:radial-gradient(120% 70% at 100% 0%,var(--lnc-bg2) 0%,var(--lnc-bg) 55%);color:var(--lnc-text);border:2px solid var(--lnc-line);border-radius:24px;box-shadow:0 18px 50px rgba(0,0,0,.5),0 0 28px rgba(252,176,10,.18);display:none;flex-direction:column;overflow:hidden;font:14.5px/1.5 var(--lnc-font)}' +
  '#lnc-box.open{display:flex;animation:lnc-in .22s ease-out}@keyframes lnc-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}' +
  '#lnc-head{position:relative;padding:16px 48px 10px;text-align:center;flex:none}' +
  '#lnc-head img{display:block;margin:0 auto;width:min(250px,74%);height:auto;filter:drop-shadow(0 4px 10px rgba(0,0,0,.4))}' +
  '#lnc-head .t{font-weight:700;font-size:20px;color:var(--lnc-gold)}' +
  '#lnc-close{position:absolute;top:10px;right:12px;width:34px;height:34px;background:none;border:0;color:#fff;cursor:pointer;padding:6px}#lnc-close svg{width:22px;height:22px;stroke:#fff;stroke-width:2.4;fill:none}' +
  '#lnc-msgs{flex:1;overflow-y:auto;padding:6px 16px 14px;display:flex;flex-direction:column;gap:10px;scrollbar-width:thin;scrollbar-color:rgba(252,176,10,.4) transparent}' +
  '.lnc-row{display:flex;gap:8px;align-items:flex-end;max-width:92%}' +
  '.lnc-row img{width:30px;height:30px;border-radius:50%;flex:none}' +
  '.lnc-m{padding:10px 14px;border-radius:16px;white-space:pre-wrap;word-wrap:break-word}' +
  '.lnc-m.bot{background:rgba(255,255,255,.04);border:1.5px solid var(--lnc-line);border-bottom-left-radius:4px}' +
  '.lnc-m.user{background:linear-gradient(135deg,var(--lnc-gold),var(--lnc-gold2));color:var(--lnc-ink);align-self:flex-end;border-bottom-right-radius:4px;font-weight:500;max-width:80%}' +
  '.lnc-m h4{margin:0 0 2px;font-size:22px;line-height:1.2;color:var(--lnc-gold);font-weight:700}.lnc-m strong{display:block;font-size:15.5px;margin-bottom:8px}' +
  '.lnc-m p{margin:0 0 8px}.lnc-m p:last-child{margin:0}.lnc-m a{color:var(--lnc-gold)}' +
  '.lnc-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}' +
  '.lnc-grid button,.lnc-opts button{display:flex;align-items:center;gap:9px;background:var(--lnc-bg);border:1.5px solid var(--lnc-line);color:#fff;border-radius:14px;padding:10px 11px;font:600 13.5px/1.25 var(--lnc-font);cursor:pointer;text-align:left;transition:background .15s,transform .1s}' +
  '.lnc-grid button:hover,.lnc-opts button:hover{background:rgba(252,176,10,.12)}.lnc-grid button:active,.lnc-opts button:active{transform:scale(.98)}' +
  '.lnc-grid button span{flex:1}' +
  '.lnc-grid svg,.lnc-opts svg,.lnc-contact svg{width:22px;height:22px;stroke:var(--lnc-gold);stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round;flex:none}' +
  '.lnc-grid svg.c{width:18px;height:18px;stroke:#fff}' +
  '.lnc-opts{display:flex;flex-wrap:wrap;gap:7px}.lnc-opts button{border-radius:999px;padding:8px 14px;font-weight:500}' +
  '.lnc-contact{display:flex;flex-direction:column;gap:8px}.lnc-contact a{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;border:1.5px solid var(--lnc-line);border-radius:14px;padding:10px 12px;font-weight:500}' +
  '.lnc-typing{display:flex;gap:5px;padding:12px 14px}.lnc-typing i{width:7px;height:7px;border-radius:50%;background:var(--lnc-gold);animation:lnc-dot 1s infinite}.lnc-typing i:nth-child(2){animation-delay:.15s}.lnc-typing i:nth-child(3){animation-delay:.3s}' +
  '@keyframes lnc-dot{0%,80%,100%{opacity:.3;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}' +
  '#lnc-form{display:flex;align-items:center;gap:8px;margin:0 14px 14px;padding:5px 5px 5px 16px;border:1.5px solid rgba(255,255,255,.28);border-radius:999px;background:rgba(255,255,255,.06);flex:none}' +
  '#lnc-form:focus-within{border-color:var(--lnc-gold)}' +
  '#lnc-in{flex:1;min-width:0;background:none;border:0;color:#fff;font:15px var(--lnc-font);outline:none;padding:8px 0}#lnc-in::placeholder{color:var(--lnc-muted)}' +
  '#lnc-send{width:42px;height:42px;border-radius:50%;border:0;background:linear-gradient(135deg,var(--lnc-gold),var(--lnc-gold2));color:var(--lnc-ink);cursor:pointer;display:flex;align-items:center;justify-content:center;flex:none}#lnc-send svg{width:20px;height:20px}' +
  '#lnc-hp{position:absolute;left:-9999px;opacity:0}' +
  '@media (max-width:480px){#lnc-box{right:8px;left:8px;width:auto;bottom:84px;height:calc(100vh - 100px);max-height:none}#lnc-launch{width:52px;height:64px}}';

  /* ---------- Teksten per taal (nl / en / es / de) ---------- */
  var LANGS = {
    nl: {
      welcome: '<h4>Bon Bini!</h4><strong>Welkom bij La Negra Entertainment!</strong><p>Ik ben jouw digitale entertainmentassistente!</p><p>Wil je meer weten over onze brassbands, danseressen, Latin bands of andere diensten?</p><p>Stel me gerust al je vragen. Ik help je graag!</p>',
      welcomeTxt: 'Bon Bini! Welkom bij La Negra Entertainment! Ik ben jouw digitale entertainmentassistente. Stel me gerust al je vragen.',
      menu: ['Onze diensten', 'Prijzen', 'Boeking aanvragen', 'Contact'],
      back: 'Terug naar menu', send: 'Klopt, versturen', redo: 'Opnieuw', retry: 'Opnieuw proberen',
      placeholder: 'Stel hier je vraag…', close: 'Sluiten', sendLbl: 'Verstuur', launch: 'Chat met La Negra Entertainment',
      teaser: '<b>Bon Bini!</b>Feestje gepland? Check direct of jouw datum nog vrij is.',
      contact: 'Je kunt ons altijd direct bereiken:',
      already: 'Je aanvraag is al verstuurd. We nemen snel contact met je op!',
      fill: 'Wil je dit nog even invullen?',
      check: 'Even checken:', guests: 'gasten', in: 'in',
      thanks: function (n) { return 'Dank je, ' + n + '! Je aanvraag is binnen. Je krijgt een bevestiging per mail en we sturen je snel een offerte op maat.'; },
      err: 'Er ging iets mis bij het versturen. Probeer het opnieuw of mail ons via ',
      conn: 'Verbinding mislukt. Mail ons gerust via ',
      unknown: 'Goeie vraag! Die beantwoorden we het liefst persoonlijk. Zal ik direct een vrijblijvende aanvraag voor je klaarzetten?',
      txt: {
        diensten: 'Wij brengen de Caribbean naar jouw feest: samba danseressen, brassband (ook met danseressen), salsa brassband, steelband, Latin Entertainment Band, limboshow en vuurspuwers, salsa- en percussie-workshops en een photobooth. Voor bruiloften, verjaardagen, bedrijfsfeesten, festivals en meer.',
        prijzen: 'De prijs hangt af van de act, de duur en de locatie. Vertel me kort wat je zoekt, dan krijg je een vrijblijvende offerte op maat.',
        datum: 'Populaire data, vooral zaterdagen in het seizoen, gaan snel. Geef je datum door, dan checken we het direct voor je.'
      },
      re: { prijs: /prijs|kost|tarief|budget|€|euro/i, datum: /beschikba|vrij|datum|agenda/i },
      flow: [
        { key: 'type', q: 'Leuk! Wat voor feest of event organiseer je?', options: ['Bruiloft', 'Verjaardag / jubileum', 'Bedrijfsfeest / teambuilding', 'Festival', 'Opening & launch', 'Themafeest', 'Anders'] },
        { key: 'act', q: 'Waar ben je naar op zoek?', options: ['Samba danseressen', 'Brassband', 'Brassband + danseressen', 'Salsa brassband', 'Steelband', 'Latin band', 'Limbo & vuurspuwers', 'Workshop', 'Photobooth', 'Advies / combinatie'] },
        { key: 'datum', q: 'Op welke datum is het? (bijv. 14-06-2027)', input: 'text' },
        { key: 'plaats', q: 'In welke plaats of op welke locatie?', input: 'text' },
        { key: 'gasten', q: 'Ongeveer hoeveel gasten verwacht je?', options: ['Minder dan 50', '50 – 100', '100 – 250', '250+'] },
        { key: 'naam', q: 'Top. Wat is je naam?', input: 'text' },
        { key: 'email', q: 'Op welk e-mailadres mogen we je de offerte sturen?', input: 'email', bad: 'Dat lijkt geen geldig e-mailadres. Probeer het nog eens.' },
        { key: 'telefoon', q: 'En je telefoonnummer? Dan kunnen we sneller schakelen.', input: 'tel', skip: 'Liever niet' },
        { key: 'opmerking', q: 'Nog wensen of vragen die we moeten weten?', input: 'text', skip: 'Nee, dat was het' }
      ]
    },
    en: {
      welcome: '<h4>Bon Bini!</h4><strong>Welcome to La Negra Entertainment!</strong><p>I\'m your digital entertainment assistant!</p><p>Want to know more about our brass bands, dancers, Latin bands or other services?</p><p>Feel free to ask me anything. I\'m happy to help!</p>',
      welcomeTxt: 'Bon Bini! Welcome to La Negra Entertainment! I am your digital entertainment assistant. Feel free to ask me anything.',
      menu: ['Our services', 'Prices', 'Request a booking', 'Contact'],
      back: 'Back to menu', send: 'Correct, send it', redo: 'Start over', retry: 'Try again',
      placeholder: 'Ask your question here…', close: 'Close', sendLbl: 'Send', launch: 'Chat with La Negra Entertainment',
      teaser: '<b>Bon Bini!</b>Planning a party? Check right away if your date is still available.',
      contact: 'You can always reach us directly:',
      already: 'Your request has already been sent. We will be in touch soon!',
      fill: 'Could you fill this in?',
      check: 'Let\'s double-check:', guests: 'guests', in: 'in',
      thanks: function (n) { return 'Thank you, ' + n + '! We\'ve received your request. You\'ll get a confirmation by email and we\'ll send you a tailor-made quote soon.'; },
      err: 'Something went wrong while sending. Please try again or email us at ',
      conn: 'Connection failed. Feel free to email us at ',
      unknown: 'Good question! We\'d love to answer that personally. Shall I set up a free, no-obligation request for you?',
      txt: {
        diensten: 'We bring the Caribbean to your party: samba dancers, brass band (also with dancers), salsa brass band, steel band, Latin Entertainment Band, limbo show and fire breathers, salsa and percussion workshops and a photo booth. For weddings, birthdays, corporate events, festivals and more.',
        prijzen: 'The price depends on the act, the duration and the location. Tell me briefly what you are looking for and you\'ll get a free, tailor-made quote.',
        datum: 'Popular dates, especially Saturdays in season, go fast. Share your date and we\'ll check it for you right away.'
      },
      re: { prijs: /price|cost|rate|budget|€|euro|fee/i, datum: /availab|free|date|calendar/i },
      flow: [
        { key: 'type', q: 'Great! What kind of party or event are you planning?', options: ['Wedding', 'Birthday / anniversary', 'Corporate event / team building', 'Festival', 'Opening & launch', 'Theme party', 'Other'] },
        { key: 'act', q: 'What are you looking for?', options: ['Samba dancers', 'Brass band', 'Brass band + dancers', 'Salsa brass band', 'Steel band', 'Latin band', 'Limbo & fire breathers', 'Workshop', 'Photo booth', 'Advice / combination'] },
        { key: 'datum', q: 'What is the date? (e.g. 14-06-2027)', input: 'text' },
        { key: 'plaats', q: 'In which city or at which venue?', input: 'text' },
        { key: 'gasten', q: 'Roughly how many guests do you expect?', options: ['Fewer than 50', '50 – 100', '100 – 250', '250+'] },
        { key: 'naam', q: 'Great. What is your name?', input: 'text' },
        { key: 'email', q: 'Which email address should we send the quote to?', input: 'email', bad: 'That doesn\'t look like a valid email address. Please try again.' },
        { key: 'telefoon', q: 'And your phone number? That helps us respond faster.', input: 'tel', skip: 'I\'d rather not' },
        { key: 'opmerking', q: 'Any other wishes or questions we should know about?', input: 'text', skip: 'No, that\'s all' }
      ]
    },
    es: {
      welcome: '<h4>Bon Bini!</h4><strong>¡Bienvenido a La Negra Entertainment!</strong><p>¡Soy tu asistente digital de entretenimiento!</p><p>¿Quieres saber más sobre nuestras brass bands, bailarinas, bandas latinas u otros servicios?</p><p>Pregúntame lo que quieras. ¡Estaré encantada de ayudarte!</p>',
      welcomeTxt: '¡Bon Bini! Bienvenido a La Negra Entertainment. Soy tu asistente digital de entretenimiento. Pregúntame lo que quieras.',
      menu: ['Nuestros servicios', 'Precios', 'Solicitar reserva', 'Contacto'],
      back: 'Volver al menú', send: 'Correcto, enviar', redo: 'Empezar de nuevo', retry: 'Intentar de nuevo',
      placeholder: 'Escribe aquí tu pregunta…', close: 'Cerrar', sendLbl: 'Enviar', launch: 'Chatea con La Negra Entertainment',
      teaser: '<b>Bon Bini!</b>¿Preparas una fiesta? Comprueba ahora si tu fecha está disponible.',
      contact: 'Siempre puedes contactarnos directamente:',
      already: 'Tu solicitud ya ha sido enviada. ¡Pronto nos pondremos en contacto contigo!',
      fill: '¿Puedes completar este dato?',
      check: 'Vamos a comprobarlo:', guests: 'invitados', in: 'en',
      thanks: function (n) { return '¡Gracias, ' + n + '! Hemos recibido tu solicitud. Recibirás una confirmación por correo y pronto te enviaremos un presupuesto a medida.'; },
      err: 'Algo salió mal al enviar. Inténtalo de nuevo o escríbenos a ',
      conn: 'Error de conexión. Escríbenos a ',
      unknown: '¡Buena pregunta! Preferimos responderla personalmente. ¿Preparo una solicitud sin compromiso para ti?',
      txt: {
        diensten: 'Llevamos el Caribe a tu fiesta: bailarinas de samba, brass band (también con bailarinas), salsa brass band, steel band, Latin Entertainment Band, show de limbo y escupefuegos, talleres de salsa y percusión y un fotomatón. Para bodas, cumpleaños, eventos de empresa, festivales y más.',
        prijzen: 'El precio depende del acto, la duración y el lugar. Cuéntame brevemente qué buscas y recibirás un presupuesto a medida sin compromiso.',
        datum: 'Las fechas populares, sobre todo los sábados de temporada, se reservan rápido. Dinos tu fecha y la comprobamos enseguida.'
      },
      re: { prijs: /precio|cuesta|coste|tarifa|presupuesto|€|euro/i, datum: /disponib|libre|fecha|agenda/i },
      flow: [
        { key: 'type', q: '¡Genial! ¿Qué tipo de fiesta o evento organizas?', options: ['Boda', 'Cumpleaños / aniversario', 'Evento de empresa / team building', 'Festival', 'Inauguración y lanzamiento', 'Fiesta temática', 'Otro'] },
        { key: 'act', q: '¿Qué estás buscando?', options: ['Bailarinas de samba', 'Brass band', 'Brass band + bailarinas', 'Salsa brass band', 'Steel band', 'Banda latina', 'Limbo y escupefuegos', 'Taller', 'Fotomatón', 'Consejo / combinación'] },
        { key: 'datum', q: '¿Qué fecha es? (p. ej. 14-06-2027)', input: 'text' },
        { key: 'plaats', q: '¿En qué ciudad o lugar?', input: 'text' },
        { key: 'gasten', q: '¿Cuántos invitados esperas aproximadamente?', options: ['Menos de 50', '50 – 100', '100 – 250', '250+'] },
        { key: 'naam', q: 'Perfecto. ¿Cómo te llamas?', input: 'text' },
        { key: 'email', q: '¿A qué correo electrónico te enviamos el presupuesto?', input: 'email', bad: 'Ese correo no parece válido. Inténtalo de nuevo.' },
        { key: 'telefoon', q: '¿Y tu número de teléfono? Así podemos responder más rápido.', input: 'tel', skip: 'Prefiero no darlo' },
        { key: 'opmerking', q: '¿Algún otro deseo o pregunta que debamos saber?', input: 'text', skip: 'No, eso es todo' }
      ]
    },
    de: {
      welcome: '<h4>Bon Bini!</h4><strong>Willkommen bei La Negra Entertainment!</strong><p>Ich bin Ihre digitale Entertainment-Assistentin!</p><p>Möchten Sie mehr über unsere Brassbands, Tänzerinnen, Latin-Bands oder andere Leistungen erfahren?</p><p>Fragen Sie mich gern alles. Ich helfe Ihnen gern!</p>',
      welcomeTxt: 'Bon Bini! Willkommen bei La Negra Entertainment! Ich bin Ihre digitale Entertainment-Assistentin. Fragen Sie mich gern alles.',
      menu: ['Unsere Leistungen', 'Preise', 'Buchung anfragen', 'Kontakt'],
      back: 'Zurück zum Menü', send: 'Stimmt, absenden', redo: 'Neu beginnen', retry: 'Erneut versuchen',
      placeholder: 'Ihre Frage hier…', close: 'Schließen', sendLbl: 'Senden', launch: 'Chat mit La Negra Entertainment',
      teaser: '<b>Bon Bini!</b>Feier geplant? Prüfen Sie gleich, ob Ihr Termin noch frei ist.',
      contact: 'Sie erreichen uns jederzeit direkt:',
      already: 'Ihre Anfrage wurde bereits gesendet. Wir melden uns bald!',
      fill: 'Können Sie das noch ausfüllen?',
      check: 'Kurz zur Kontrolle:', guests: 'Gäste', in: 'in',
      thanks: function (n) { return 'Vielen Dank, ' + n + '! Ihre Anfrage ist eingegangen. Sie erhalten eine Bestätigung per E-Mail und wir schicken Ihnen bald ein individuelles Angebot.'; },
      err: 'Beim Senden ist etwas schiefgelaufen. Versuchen Sie es erneut oder schreiben Sie uns an ',
      conn: 'Verbindung fehlgeschlagen. Schreiben Sie uns gern an ',
      unknown: 'Gute Frage! Die beantworten wir am liebsten persönlich. Soll ich direkt eine unverbindliche Anfrage für Sie vorbereiten?',
      txt: {
        diensten: 'Wir bringen die Karibik auf Ihre Feier: Sambatänzerinnen, Brassband (auch mit Tänzerinnen), Salsa-Brassband, Steelband, Latin Entertainment Band, Limboshow und Feuerspucker, Salsa- und Percussion-Workshops und eine Fotobox. Für Hochzeiten, Geburtstage, Firmenfeiern, Festivals und mehr.',
        prijzen: 'Der Preis hängt vom Act, der Dauer und dem Ort ab. Erzählen Sie mir kurz, was Sie suchen, dann erhalten Sie ein unverbindliches, individuelles Angebot.',
        datum: 'Beliebte Termine, vor allem Samstage in der Saison, sind schnell vergeben. Nennen Sie uns Ihr Datum, dann prüfen wir es sofort.'
      },
      re: { prijs: /preis|kost|tarif|budget|€|euro|gage/i, datum: /verfügbar|frei|datum|termin|kalender/i },
      flow: [
        { key: 'type', q: 'Schön! Was für eine Feier oder Veranstaltung planen Sie?', options: ['Hochzeit', 'Geburtstag / Jubiläum', 'Firmenfeier / Teambuilding', 'Festival', 'Eröffnung & Launch', 'Mottoparty', 'Sonstiges'] },
        { key: 'act', q: 'Wonach suchen Sie?', options: ['Sambatänzerinnen', 'Brassband', 'Brassband + Tänzerinnen', 'Salsa-Brassband', 'Steelband', 'Latin-Band', 'Limbo & Feuerspucker', 'Workshop', 'Fotobox', 'Beratung / Kombination'] },
        { key: 'datum', q: 'An welchem Datum? (z. B. 14-06-2027)', input: 'text' },
        { key: 'plaats', q: 'In welcher Stadt oder an welchem Ort?', input: 'text' },
        { key: 'gasten', q: 'Mit wie vielen Gästen rechnen Sie ungefähr?', options: ['Weniger als 50', '50 – 100', '100 – 250', '250+'] },
        { key: 'naam', q: 'Prima. Wie ist Ihr Name?', input: 'text' },
        { key: 'email', q: 'An welche E-Mail-Adresse dürfen wir das Angebot senden?', input: 'email', bad: 'Das scheint keine gültige E-Mail-Adresse zu sein. Bitte versuchen Sie es erneut.' },
        { key: 'telefoon', q: 'Und Ihre Telefonnummer? Dann können wir schneller reagieren.', input: 'tel', skip: 'Lieber nicht' },
        { key: 'opmerking', q: 'Gibt es noch Wünsche oder Fragen, die wir wissen sollten?', input: 'text', skip: 'Nein, das war alles' }
      ]
    }
  };
  var LANG = (ds.lang || document.documentElement.lang || 'nl').slice(0, 2).toLowerCase();
  if (!LANGS[LANG]) LANG = 'nl';
  var T = LANGS[LANG];
  var FLOW = T.flow.map(function (f) {
    if (f.input === 'email') f.validate = function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? '' : f.bad; };
    return f;
  });
  var TXT = T.txt;
  var FAQ = [
    { re: T.re.prijs, a: TXT.prijzen },
    { re: T.re.datum, a: TXT.datum },
    { re: /brass|band|steel|live|samba|limbo|workshop|percus|photo|foto|latin|carib|salsa|danser|dancer|bailar|dienst|service|servicio|act/i, a: TXT.diensten }
  ];

  /* ---------- State ---------- */
  var state = { open: false, step: -1, lead: {}, history: [], busy: false, sent: false, started: Date.now() };
  var el = {};

  function ss(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }

  // Bron bijhouden (UTM / referrer) zodat je ziet waar leads vandaan komen
  (function trackSource() {
    try {
      var p = new URLSearchParams(location.search), src = {};
      ['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'fbclid'].forEach(function (k) { if (p.get(k)) src[k] = p.get(k); });
      if (!ss('lnc_src')) {
        src.referrer = document.referrer || 'direct';
        src.landing = location.pathname;
        ss('lnc_src', JSON.stringify(src));
      }
    } catch (e) {}
  })();

  function h(tag, attrs, kids) {
    var n = document.createElement(tag);
    for (var k in (attrs || {})) { if (k === 'text') n.textContent = attrs[k]; else if (k === 'html') n.innerHTML = attrs[k]; else n.setAttribute(k, attrs[k]); }
    (kids || []).forEach(function (c) { n.appendChild(c); });
    return n;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function scroll() { el.msgs.scrollTop = el.msgs.scrollHeight; }
  function clearOpts() { el.msgs.querySelectorAll('.lnc-opts,.lnc-grid').forEach(function (o) { o.remove(); }); }
  function remember(role, text) {
    state.history.push({ role: role, content: text });
    if (state.history.length > 30) state.history = state.history.slice(-30);
  }

  function botRow(node) {
    var row = h('div', { 'class': 'lnc-row' }, [h('img', { src: CFG.avatar, alt: '' }), node]);
    el.msgs.appendChild(row); scroll(); return row;
  }
  function say(text, who) {
    if ((who || 'bot') === 'bot') { botRow(h('div', { 'class': 'lnc-m bot', text: text })); remember('assistant', text); }
    else { el.msgs.appendChild(h('div', { 'class': 'lnc-m user', text: text })); scroll(); remember('user', text); }
  }

  function opts(list) {
    clearOpts();
    var wrap = h('div', { 'class': 'lnc-opts' });
    list.forEach(function (o) {
      var b = h('button', { type: 'button', html: (o.icon ? I[o.icon] : '') + '<span></span>' });
      b.querySelector('span').textContent = o.label;
      b.onclick = function () { clearOpts(); o.fn(); };
      wrap.appendChild(b);
    });
    el.msgs.appendChild(wrap); scroll();
  }

  function typing(on) {
    var t = el.msgs.querySelector('.lnc-typing-row');
    if (on && !t) { var r = botRow(h('div', { 'class': 'lnc-m bot lnc-typing', html: '<i></i><i></i><i></i>' })); r.classList.add('lnc-typing-row'); }
    if (!on && t) t.remove();
  }

  function botSay(text, after) {
    typing(true);
    setTimeout(function () { typing(false); say(text); if (after) after(); }, Math.min(450 + text.length * 8, 1300));
  }

  /* ---------- Welkom en menu ---------- */
  function welcome() {
    var m = h('div', { 'class': 'lnc-m bot', html: T.welcome });
    typing(true);
    setTimeout(function () { typing(false); botRow(m); remember('assistant', T.welcomeTxt); menu(); el.msgs.scrollTop = 0; }, 700);
  }

  function menu() {
    clearOpts();
    var items = [
      { icon: 'note', label: T.menu[0], fn: function () { say(T.menu[0], 'user'); botSay(TXT.diensten, afterInfo); } },
      { icon: 'euro', label: T.menu[1], fn: function () { say(T.menu[1], 'user'); botSay(TXT.prijzen, afterInfo); } },
      { icon: 'cal', label: T.menu[2], fn: function () { say(T.menu[2], 'user'); startFlow(); } },
      { icon: 'mail', label: T.menu[3], fn: function () { say(T.menu[3], 'user'); contact(); } }
    ];
    var grid = h('div', { 'class': 'lnc-grid' });
    items.forEach(function (o) {
      var b = h('button', { type: 'button', html: I[o.icon] + '<span></span>' + I.chev.replace('<svg', '<svg class="c"') });
      b.querySelector('span').textContent = o.label;
      b.onclick = function () { clearOpts(); o.fn(); };
      grid.appendChild(b);
    });
    el.msgs.appendChild(grid); scroll();
  }

  function afterInfo() {
    opts([
      { icon: 'cal', label: T.menu[2], fn: function () { say(T.menu[2], 'user'); startFlow(); } },
      { label: T.back, fn: menu }
    ]);
  }

  function contact() {
    typing(true);
    setTimeout(function () {
      typing(false);
      var tel = CFG.phone.replace(/\s/g, '');
      var box = h('div', { 'class': 'lnc-m bot', html: '<p>' + T.contact + '</p><div class="lnc-contact">' +
        '<a href="mailto:' + esc(CFG.email) + '">' + I.mail + esc(CFG.email) + '</a>' +
        '<a href="tel:' + esc(tel) + '">' + I.phone + esc(CFG.phone) + '</a></div>' });
      botRow(box); remember('assistant', 'Contact: ' + CFG.email + ', ' + CFG.phone);
      afterInfo();
    }, 600);
  }

  /* ---------- Flow ---------- */
  function startFlow() {
    if (state.sent) { botSay(T.already, function () { opts([{ label: T.back, fn: menu }]); }); return; }
    state.step = 0; ask();
  }

  function ask() {
    var s = FLOW[state.step];
    botSay(s.q, function () {
      if (s.options) opts(s.options.map(function (o) { return { label: o, fn: function () { say(o, 'user'); answer(o); } }; }));
      else if (s.skip) opts([{ label: s.skip, fn: function () { say(s.skip, 'user'); answer(''); } }]);
      el.input.type = s.input === 'email' ? 'email' : (s.input === 'tel' ? 'tel' : 'text');
      el.input.focus();
    });
  }

  function answer(v) {
    var s = FLOW[state.step];
    if (s.validate && v) { var err = s.validate(v); if (err) { botSay(err); return; } }
    if (!v && !s.skip) { botSay(T.fill); return; }
    state.lead[s.key] = v;
    state.step++;
    el.input.type = 'text';
    if (state.step < FLOW.length) ask(); else confirm();
  }

  function confirm() {
    var L = state.lead;
    var sum = T.check + '\n' +
      '• ' + L.type + ' – ' + L.act + '\n' +
      '• ' + L.datum + ' ' + T.in + ' ' + L.plaats + '\n' +
      '• ' + L.gasten + ' ' + T.guests + '\n' +
      '• ' + L.naam + ' (' + L.email + (L.telefoon ? ', ' + L.telefoon : '') + ')' +
      (L.opmerking ? '\n• ' + L.opmerking : '');
    state.step = -1;
    botSay(sum, function () {
      opts([
        { icon: 'check', label: T.send, fn: function () { say(T.send, 'user'); submit(); } },
        { icon: 'redo', label: T.redo, fn: function () { say(T.redo, 'user'); state.lead = {}; startFlow(); } }
      ]);
    });
  }

  function submit() {
    if (state.busy) return; state.busy = true; typing(true);
    var payload = {
      lead: state.lead,
      lang: LANG,
      source: JSON.parse(ss('lnc_src') || '{}'),
      page: location.href,
      transcript: state.history.slice(-20),
      website: el.hp.value,                       // honeypot
      t: Math.round((Date.now() - state.started) / 1000)
    };
    fetch(CFG.leadUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        typing(false); state.busy = false;
        if (d && d.ok) {
          state.sent = true; ss('lnc_sent', '1');
          botSay(T.thanks(state.lead.naam), function () { opts([{ label: T.back, fn: menu }]); });
          if (window.gtag) try { window.gtag('event', 'generate_lead', { method: 'chatbot' }); } catch (e) {}
          if (window.fbq) try { window.fbq('track', 'Lead'); } catch (e) {}
        } else {
          botSay((d && d.error) || (T.err + CFG.email + '.'));
          opts([{ icon: 'redo', label: T.retry, fn: submit }]);
        }
      })
      .catch(function () {
        typing(false); state.busy = false;
        botSay(T.conn + CFG.email + '.');
        opts([{ icon: 'redo', label: T.retry, fn: submit }]);
      });
  }

  /* ---------- Vrije vragen ---------- */
  function answerFree(text) {
    if (CFG.aiUrl) return askAI();
    var hit = FAQ.filter(function (f) { return f.re.test(text); })[0];
    botSay(hit ? hit.a : T.unknown, afterInfo);
  }

  function askAI() {
    if (state.busy) return; state.busy = true; typing(true);
    fetch(CFG.aiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ messages: state.history.slice(-16), page: location.pathname, lang: LANG }) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        typing(false); state.busy = false;
        var reply = (d && d.reply) || '';
        reply = reply.replace('[[OFFERTE]]', '').trim();
        if (!reply) return answerFreeFallback();
        say(reply);
        if (!state.sent) afterInfo();
      })
      .catch(function () { typing(false); state.busy = false; answerFreeFallback(); });
  }
  function answerFreeFallback() { var u = CFG.aiUrl; CFG.aiUrl = ''; answerFree(state.history.length ? state.history[state.history.length - 1].content : ''); CFG.aiUrl = u; }

  function onSend(e) {
    e.preventDefault();
    var v = el.input.value.trim(); if (!v) return;
    el.input.value = ''; clearOpts(); say(v, 'user');
    if (state.step >= 0) answer(v); else answerFree(v);
  }

  /* ---------- UI opbouw ---------- */
  function openBox() {
    state.open = true; el.box.classList.add('open'); el.launch.classList.add('open'); el.teaser.style.display = 'none'; ss('lnc_teased', '1');
    if (!el.msgs.childNodes.length) { if (ss('lnc_sent')) state.sent = true; welcome(); }
    setTimeout(function () { el.input.focus(); }, 200);
  }
  function closeBox() { state.open = false; el.box.classList.remove('open'); el.launch.classList.remove('open'); }

  function build() {
    document.head.appendChild(h('style', { text: CSS }));
    el.launch = h('button', { id: 'lnc-launch', type: 'button', 'aria-label': T.launch });
    el.launch.onclick = function () { state.open ? closeBox() : openBox(); };

    el.teaser = h('div', { id: 'lnc-teaser', role: 'button', html: '<span class="x" aria-label="' + T.close + '">×</span>' + T.teaser });
    el.teaser.onclick = function (e) { if (e.target.className === 'x') { el.teaser.style.display = 'none'; ss('lnc_teased', '1'); return; } openBox(); };

    el.msgs = h('div', { id: 'lnc-msgs', 'aria-live': 'polite' });
    el.input = h('input', { id: 'lnc-in', type: 'text', placeholder: T.placeholder, autocomplete: 'off', maxlength: '500' });
    el.hp = h('input', { id: 'lnc-hp', type: 'text', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true' });
    var send = h('button', { id: 'lnc-send', type: 'submit', 'aria-label': T.sendLbl, html: I.send });
    var form = h('form', { id: 'lnc-form' }, [el.input, el.hp, send]);
    form.onsubmit = onSend;

    var head = h('div', { id: 'lnc-head' });
    var wm = h('img', { src: CFG.wordmark, alt: 'La Negra Entertainment' });
    wm.onerror = function () { wm.replaceWith(h('div', { 'class': 't', text: 'La Negra Entertainment' })); };
    var close = h('button', { id: 'lnc-close', type: 'button', 'aria-label': T.close, html: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>' });
    close.onclick = closeBox;
    head.appendChild(wm); head.appendChild(close);

    el.box = h('div', { id: 'lnc-box', role: 'dialog', 'aria-label': 'Chat La Negra Entertainment' }, [head, el.msgs, form]);
    [el.teaser, el.box, el.launch].forEach(function (n) { document.body.appendChild(n); });

    if (CFG.teaserDelay > 0 && !ss('lnc_teased')) {
      setTimeout(function () { if (!state.open) el.teaser.style.display = 'block'; }, CFG.teaserDelay);
    }
    // Open chat via elke link/knop met data-lnc-open of href="#chat"
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-lnc-open],a[href="#chat"]');
      if (t) { e.preventDefault(); openBox(); }
    });
    window.LaNegraChat = { open: openBox, close: closeBox, offerte: function () { openBox(); setTimeout(function () { clearOpts(); startFlow(); }, 1000); } };
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
})();
