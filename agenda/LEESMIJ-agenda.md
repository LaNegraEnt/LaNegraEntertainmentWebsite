# Agenda koppelen aan Google Agenda

Klanten zien op `agenda.html` welke dagen en tijden vrij zijn en kunnen:
- **Optie 48 uur** – komt als geel blok "OPTIE 48u – act – naam" in de agenda **La Negra optredens** en verdwijnt vanzelf na 48 uur.
  Wil je hem houden? Haal het woord "OPTIE" uit de titel (bijv. "GEBOEKT – …") – dan blijft hij staan.
- **Direct boeken** – komt als geel blok "OPTIE 48u – wacht op aanbetaling – …". De klant gaat meteen naar de aanbetaling
  van € 50,- (Revolut). Zodra die binnen is, wordt het blok automatisch groen: "GEBOEKT – …". Zonder betaling verloopt hij na 48 uur.

Jij en de klant krijgen bij elke reservering een mail. Alles staat daarnaast in `agenda/data/reserveringen.csv` (afgeschermd).

## Je privé-agenda telt niet mee
De site leest en schrijft **alleen** de aparte agenda "La Negra optredens", nooit je privé-agenda.
- Alles in "La Negra optredens" telt als bezet: optredens die je zelf invoert én reserveringen van de site.
- Een **hele-dag-afspraak** in "La Negra optredens" (bijv. vakantie) = die dag is dicht voor klanten.
- Afspraken op "Beschikbaar" (in plaats van "Bezet") tellen niet mee.
- Er zit 60 minuten speling tussen optredens (`LNA_BUFFER_MIN` in `config.php`).

In de Google Agenda-app zie je beide agenda's gewoon samen, elk in een eigen kleur.

## Eenmalig instellen (± 20 minuten)

**A. Aparte agenda maken**
1. Open Google Agenda op de computer → links bij "Andere agenda's" op **+** → **Nieuwe agenda maken**.
2. Naam: `La Negra optredens` → **Agenda maken**.
3. Zet je bestaande optredens in deze agenda (niet in je privé-agenda).

**B. Google-sleutel maken**
4. Ga naar https://console.cloud.google.com (met hetzelfde Google-account) → maak een project aan, bijv. "La Negra Agenda".
5. **API's en services → Bibliotheek** → zoek **Google Calendar API** → **Inschakelen**.
6. **API's en services → Inloggegevens → Inloggegevens maken → Serviceaccount**. Naam: `agenda` → **Klaar**.
7. Klik het serviceaccount aan → tab **Sleutels → Sleutel toevoegen → Nieuwe sleutel → JSON**. Er wordt een `.json`-bestand gedownload.
   Bewaar dit bestand goed en deel het met niemand.

**C. De agenda delen met de sleutel**
8. Google Agenda → **Instellingen** (tandwiel) → links onder "Instellingen voor mijn agenda's" op **La Negra optredens**.
9. **Delen met bepaalde personen of groepen → Personen toevoegen**: het e-mailadres van het serviceaccount
   (staat in het JSON-bestand bij `client_email`, eindigt op `iam.gserviceaccount.com`), met recht
   **"Wijzigingen aanbrengen in afspraken"**.
10. Scroll op dezelfde pagina naar **Agenda integreren** → kopieer de **Agenda-ID** (eindigt op `@group.calendar.google.com`).

**D. In GitHub zetten** (Settings → Environments → production → Add secret)
11. `GOOGLE_CALENDAR_ID` = de Agenda-ID uit stap 10.
12. `GOOGLE_SERVICE_ACCOUNT_JSON` = de **volledige inhoud** van het JSON-bestand uit stap 7 (open het met Teksteditor, alles selecteren, kopiëren, plakken).

Bij de volgende deploy zet de pipeline de koppeling op de server (`agenda/config-google.php`, niet in de repo).
Open daarna `agenda.html`: de gele melding "Voorbeeldweergave" is weg en je ziet de echte beschikbaarheid.

## Optioneel: cronjob
Verlopen opties worden opgeruimd zodra iemand de agenda bekijkt. Wil je dat ze ook stipt verdwijnen, zet in je hosting een cronjob elk uur op:
`https://lanegra-entertainment.nl/agenda/agenda-api.php?action=opruimen`
