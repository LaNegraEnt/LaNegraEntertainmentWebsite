# Nieuwe website La Negra Entertainment

## Wat zit erin
- 51 pagina's in 3 talen: Nederlands (root), Engels (`/en/`) en Spaans (`/es/`)
- Per taal: homepage, 9 act-pagina's en 7 gelegenheid-pagina's (bruiloft, bedrijfsfeest, verjaardag & jubileum, festival, teambuilding, opening & launch, themafeest)
- Aanvraagformulier op elke pagina; komt binnen via dezelfde mail en `leads.csv` als de chat
- SEO: titels, beschrijvingen, hreflang-taalkoppelingen, structured data (bedrijf, diensten, kruimelpad) en een sitemap met alle taalversies
- `assets/`: stylesheet, Gallant-lettertype, foto's, logo, favicon
- `lanegra-chat/`: de chatbot (zit al op elke pagina)
- `downloads/rider.pdf`: technische rider in de nieuwe huisstijl
- `sitemap.xml` en `robots.txt`: voor Google

## Online zetten
1. Maak eerst een backup van je huidige site.
2. Upload de inhoud van deze map naar de root van je hosting (public_html).
3. `algemene-voorwaarden.html` staat al op je huidige site; laat die gewoon staan.
4. Chatbot instellen: zie `lanegra-chat/config.php` (mailadres staat al goed; AI-key optioneel).
5. Meld `https://lanegra-entertainment.nl/sitemap.xml` aan in Google Search Console.

## Nog aan te vullen
Zoek in de act-pagina's op `[` en `Foto volgt` / `Video volgt`:
- **Video**: vervang in `.video` de afbeelding door je YouTube-embed (voorbeeld staat als commentaar in de code).
- **Foto's**: vervang de blokjes `<div class="ph">Foto volgt</div>` door `<img src="../assets/img/JOUW-FOTO.jpg" alt="...">`.
- **Duur**: `[ aantal sets × minuten ]` per act.
- **Logobalk** (homepage, "Zij boekten ons al"): vervang de namen door `<img src="assets/img/logo-ing.png" alt="ING">` zodra je de logobestanden hebt.

## Talen
Taalwissel staat rechtsboven op elke pagina. De chatbot en de bevestigingsmail volgen automatisch de taal van de pagina. Laat de Engelse en Spaanse teksten bij voorkeur nog even nalezen door iemand die de taal goed spreekt.

## Nieuw (okt 2026)
- `offerte-rider.html`: offerte samenstellen. Verstuurt naar je bestaande `stuur-offerte.php` (zelfde velden).
- `algemene-voorwaarden.html`, `opdrachtbevestiging-akkoord.html` (post naar `betaling-aanmaken-live.php`, Mollie).
- `opdracht-mail.html` en `definitieve-opdrachtbevestiging-mail.html`: mail-sjablonen, alle {{PLACEHOLDERS}} zijn gelijk gebleven.
- `leads-overzicht.html`: leest `data/leads.csv` + `data/mailinglijst.csv`. Zet in `lanegra-chat/config.php` een eigen `LNC_ADMIN_KEY` (min. 12 tekens) om ook chat-/formulierleads te zien.
- `.htaccess` in de root: 301-redirects van de oude pagina's (salsa-brassband-huren.html enz.) naar de nieuwe.
- Act-galerijen gebruiken je bestaande foto's op de server (`images/salsa-1.jpg`, `images/danseressen-1.jpg`, `images/entband-1.jpg`, steelband `IMG_….jpeg`). Laat die bestanden staan.
- Je PHP-bestanden (`stuur-offerte.php`, `betaling-aanmaken-live.php`) en de mappen `images/` en `data/` laat je gewoon staan.
- `admin-offerte.html`: interne offertetool (noindex). Slaat leads op via je bestaande `save-lead.php`. Reiskosten: vul je tarief per km in (wordt onthouden), afstand retour wordt berekend vanaf Rotterdam.
- `bedankt.html` en `404.html` (de .htaccess toont de 404-pagina automatisch bij een verkeerd adres).
