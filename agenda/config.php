<?php
// ============================================================
// La Negra Agenda – instellingen (zie LEESMIJ-agenda.md)
// ============================================================

// Agenda-ID en Google-sleutel komen uit config-google.php, dat de deploy-pipeline op de server zet
// uit de GitHub-secrets GOOGLE_CALENDAR_ID en GOOGLE_SERVICE_ACCOUNT_JSON. Ze staan nooit in de code.
if (is_file(__DIR__ . '/config-google.php')) require_once __DIR__ . '/config-google.php';
defined('LNA_CALENDAR_ID') || define('LNA_CALENDAR_ID', '');
// Alternatief zonder pipeline: een sleutelbestand op de server (bij voorkeur buiten public_html)
defined('LNA_SERVICE_JSON') || define('LNA_SERVICE_JSON', __DIR__ . '/google-service-account.json');

const LNA_TIMEZONE   = 'Europe/Amsterdam';
const LNA_BUFFER_MIN = 60;      // minuten speling tussen twee boekingen (reistijd, opbouw)
const LNA_OPTION_HOURS = 48;    // hoe lang een optie (en een onbetaalde directe boeking) geldig blijft
const LNA_MIN_DAYS_AHEAD = 2;   // vanaf hoeveel dagen vooruit klanten kunnen reserveren
const LNA_MAX_MONTHS_AHEAD = 18;

// Opslag (tokens, rate limits, CSV-backup). Wordt afgeschermd met .htaccess.
const LNA_DATA_DIR = __DIR__ . '/data';
