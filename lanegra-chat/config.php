<?php
// ============================================================
// La Negra Chat – instellingen
// Tip: zet dit bestand bij voorkeur BUITEN je public_html en
// pas het pad aan in chat-lead.php en ai-chat.php.
// ============================================================

// --- E-mail ---
const LNC_MAIL_TO        = 'info@lanegra-entertainment.nl';   // waar leads binnenkomen
const LNC_MAIL_FROM      = 'info@lanegra-entertainment.nl';   // moet een adres op je eigen domein zijn
const LNC_MAIL_FROM_NAME = 'La Negra Entertainment';
const LNC_SEND_AUTOREPLY = true;                              // bevestigingsmail naar klant

// --- Opslag (CSV-backup van elke lead) ---
const LNC_DATA_DIR = __DIR__ . '/data';                       // map wordt beschermd met .htaccess

// --- Leads-overzicht ---
// Kies een eigen geheime sleutel (minstens 12 tekens). Daarmee toont leads-overzicht.html ook de chat- en formulier-leads. Leeg = uit.
const LNC_ADMIN_KEY = '';

// --- AI (optioneel) ---
// Maak een key aan op console.anthropic.com en zet hem hier. Leeg = AI uit.
const LNC_ANTHROPIC_KEY  = '';
const LNC_MODEL          = 'claude-haiku-5-5';                // snel en goedkoop
const LNC_MAX_TOKENS     = 350;
const LNC_AI_PER_HOUR    = 40;                                // max AI-berichten per bezoeker (IP) per uur

// Alleen verzoeken van je eigen site accepteren
const LNC_ALLOWED_HOSTS  = ['lanegra-entertainment.nl', 'www.lanegra-entertainment.nl', 'localhost'];

// --- Kennis voor de AI ---
// Vul dit aan met je échte acts, prijsindicaties en regio. Wat hier niet staat, verzint de bot niet.
const LNC_KNOWLEDGE = <<<TXT
Bedrijf: La Negra Entertainment, Nederland. Specialist in Caribische & Latin entertainment.
Diensten (zoals op de website):
- Samba Danseressen (€ 200 per danseres voor een show van 30 minuten, excl. btw en reiskosten)
- Brassband (pakketten met 6, 7, 8 of 10 personen; altijd 1 à 2 sets van 20 à 30 minuten)
- Brassband met Danseressen
- Steelband
- Salsa Brassband (brassband met basgitaar, piano en zanger)
- Latin Entertainment Band
- Limboshow & Vuurspuwers
- Workshops: salsa workshop, percussie workshop, combinatiepakket (ook als teambuilding)
- Photobooth
- DJ: € 400 voor 2,5 uur (excl. btw en reiskosten), los of in combinatie met live acts.
Gelegenheden: bedrijfsfeest, verjaardag, festival, bruiloft, jubileum, teambuilding, opening & launch, themafeest (Caribbean, Latin, Brazil).
Werkgebied: heel Nederland (reiskosten afhankelijk van locatie).
Boeken: offerte op maat (ook zelf samen te stellen op /offerte-rider.html); na akkoord online opdrachtbevestiging, aanbetaling € 50 bij bevestiging, restbetaling uiterlijk 7 dagen na het optreden.
Prijzen: noem GEEN bedragen tenzij hieronder vermeld; verwijs naar een offerte op maat.
Contact: info@lanegra-entertainment.nl, +31 6 25 17 80 50.
TXT;
