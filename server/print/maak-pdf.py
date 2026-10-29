#!/usr/bin/env python3
"""Maakt de Engelse rider-PDF's en de festival press kit in de huisstijl van de bestaande PDF's.

Gebruik (vanuit de repo-root):  python3 server/print/maak-pdf.py
Schrijft naar downloads/. Heeft Google Chrome nodig (print via headless Chrome).
"""
import os, subprocess, tempfile, pathlib, html

ROOT = pathlib.Path(__file__).resolve().parents[2]
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
def img(p): return (ROOT / p).as_uri()

CSS = """
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
@page { size: A4; margin: 0; }
* { box-sizing: border-box; }
body { margin: 0; font-family: Poppins, Arial, sans-serif; color: #222; font-size: 10.5pt; line-height: 1.45; }
.page { position: relative; width: 210mm; height: 297mm; padding: 16mm 17mm 36mm; overflow: hidden; page-break-after: always; }
.page:last-child { page-break-after: auto; }
.fan { position: absolute; top: 0; right: 0; width: 46mm; height: 46mm; }
.logo { width: 40mm; display: block; margin-bottom: 9mm; }
.kicker { color: #3a9a3a; font-weight: 700; font-size: 8.5pt; letter-spacing: .4px; text-transform: uppercase; }
h1 { font-size: 26pt; margin: 1mm 0 2mm; line-height: 1.1; }
h1::after { content: ''; display: block; width: 16mm; height: 1.4mm; border-radius: 1mm; background: #f5c518; margin-top: 3mm; }
.intro { margin: 4mm 0 6mm; }
.photos { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; margin-bottom: 7mm; }
.photos img { width: 100%; height: 52mm; object-fit: cover; border-radius: 4mm; display: block; }
.photos .y { box-shadow: 2mm 2mm 0 #f5c518; } .photos .b { box-shadow: 2mm 2mm 0 #42b8d8; } .photos .o { box-shadow: 2mm 2mm 0 #d94f2b; }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm 10mm; }
h2 { font-size: 12.5pt; margin: 4mm 0 1.5mm; display: flex; align-items: center; gap: 3mm; }
h2 i { width: 4.2mm; height: 4.2mm; border-radius: 1mm; display: inline-block; }
ul { margin: 0 0 2mm; padding-left: 5mm; } li { margin: 0 0 1mm; font-size: 9.8pt; }
li::marker { color: #f5a623; }
.box { background: #fff7dc; border-left: 1.2mm solid #f5c518; border-radius: 0 4mm 4mm 0; padding: 3mm 6mm 4mm; margin-bottom: 4mm; }
.alert { background: #fbe9e5; border-left: 1.2mm solid #d94f2b; border-radius: 0 4mm 4mm 0; padding: 4mm 6mm; margin-top: 6mm; font-size: 9.8pt; }
.dark { background: #1f231e; color: #fff; border-radius: 4mm; padding: 5mm 7mm; margin-top: 7mm; }
.dark h3 { margin: 0 0 1mm; font-size: 14pt; } .dark p { margin: 0; color: #ddd; font-size: 9.5pt; }
.foot { position: absolute; left: 0; right: 0; bottom: 0; }
.foot .meta { display: flex; justify-content: space-between; padding: 0 17mm 3mm; font-size: 7.5pt; color: #666; }
.foot .stripe { height: 2mm; background: linear-gradient(90deg,#f5c518 25%,#42b8d8 25% 50%,#3a9a3a 50% 75%,#f5a623 75%); }
.foot .bar { background: #d94f2b; color: #fff; font-weight: 600; font-size: 10pt; display: flex; justify-content: center; gap: 12mm; padding: 5mm 0 6mm; }
.big { font-size: 30pt; } .lead { font-size: 11.5pt; }
.facts { display: grid; grid-template-columns: repeat(4,1fr); gap: 3mm; margin: 5mm 0; }
.facts div { background: #fff7dc; border-radius: 3mm; padding: 3mm 4mm; } .facts b { display: block; font-size: 15pt; } .facts span { font-size: 8.5pt; color: #555; }
.refs { columns: 2; font-size: 9.6pt; } a { color: #d94f2b; text-decoration: none; font-weight: 600; }
"""

FAN = """<svg class="fan" viewBox="0 0 100 100"><g transform="translate(100,0)"><path d="M0 0 L0.0 100.0 A100 100 0 0 1 -17.4 98.5 Z" fill="#f5c518"/><path d="M0 0 L-17.4 98.5 A100 100 0 0 1 -34.2 94.0 Z" fill="#42b8d8"/><path d="M0 0 L-34.2 94.0 A100 100 0 0 1 -50.0 86.6 Z" fill="#d94f2b"/><path d="M0 0 L-50.0 86.6 A100 100 0 0 1 -64.3 76.6 Z" fill="#3a9a3a"/><path d="M0 0 L-64.3 76.6 A100 100 0 0 1 -76.6 64.3 Z" fill="#f5a623"/><path d="M0 0 L-76.6 64.3 A100 100 0 0 1 -86.6 50.0 Z" fill="#42b8d8"/><path d="M0 0 L-86.6 50.0 A100 100 0 0 1 -94.0 34.2 Z" fill="#d94f2b"/><path d="M0 0 L-94.0 34.2 A100 100 0 0 1 -98.5 17.4 Z" fill="#f5c518"/><path d="M0 0 L-98.5 17.4 A100 100 0 0 1 -100.0 0.0 Z" fill="#3a9a3a"/></g></svg>"""

def foot(nl=False):
    return ('<div class="foot"><div class="meta"><span>KVK 67106951 &nbsp;·&nbsp; ' + ('BTW' if nl else 'VAT') + ' NL002420332B84</span><span>Feel the Rhythm. Live the Experience.</span></div>'
            '<div class="stripe"></div><div class="bar"><span>info@lanegra-entertainment.nl</span><span>+31 6 25 17 80 50</span><span>lanegra-entertainment.nl</span></div></div>')

def page(body, nl=False):
    return f'<section class="page">{FAN}<img class="logo" src="{img("assets/img/logo.png")}">{body}{foot(nl)}</section>'

def sect(title, color, items):
    return f'<h2><i style="background:{color}"></i>{title}</h2><ul>' + ''.join(f'<li>{x}</li>' for x in items) + '</ul>'

Y, B, O, G, A = '#f5c518', '#42b8d8', '#d94f2b', '#3a9a3a', '#f5a623'
QUESTIONS = ('<div class="dark"><h3>Any questions?</h3><p>Email info@lanegra-entertainment.nl or call +31 6 25 17 80 50 (also WhatsApp).<br>'
             'More photos and videos: lanegra-entertainment.nl</p></div>')
IMPORTANT = ('<div class="alert"><b>Important</b><br>If the facilities above are not available, the performance may be delayed or cannot go ahead. '
             'Any costs arising from this are borne by the organising party. In doubt or have questions about the rider? Please contact us in good time.</div>')

POWER = sect('Power', A, ['Sufficient power outlets near the stage', '<b>At least 2 grounded sockets</b>', 'Enough capacity for sound and lighting', 'Safe cabling, no extension cords across walkways'])
DRESS = sect('Dressing room', G, ['A <b>lockable dressing room</b> for the artists', 'Suitable for changing before the performance', 'A safe place for personal belongings', 'If possible: separate rooms for women and men'])
TRANS = sect('Transport &amp; logistics', O, ['Parking <b>as close to the venue as possible</b> for loading and unloading', 'Reserved parking for all band members\' vehicles', 'Easy access for moving instruments', 'Loading area kept free until after the performance'])
WEATHER = sect('Indoors or outdoors', B, ['Outdoors, a <b>covered area is always required</b>, such as a tent or canopy', 'All members must be able to stand and play in the dry', 'Woodwind and <b>brass instruments</b> in particular can be seriously damaged by moisture. Expensive equipment must be protected too.'])
FOOD = sect('Food &amp; drinks', Y, ['<b>Water and soft drinks</b> for all band members and crew', 'Available before and during the performance', 'Snacks for band members and crew', 'For long performances or rehearsals: a hot meal or catering'])

def rider_salsa():
    p1 = ('<div class="kicker">Technical rider</div><h1>Salsa Brassband</h1>'
          '<p class="intro">To make it a great show together, we ask the organisation to provide the following. The Salsa Brassband plays with horns, percussion, piano, bass guitar and vocals.</p>'
          f'<div class="photos"><img class="y" src="{img("assets/img/salsa-brassband.jpg")}"><img class="b" src="{img("images/salsa-5.jpg")}"></div>'
          '<div class="box"><h2 style="margin-top:1mm">Sound</h2><div class="cols"><div>'
          + sect('On stage', Y, ['<b>7 microphones</b> for the trumpets and trombones', '<b>1 microphone</b> for the singer', '<b>2 overheads</b> for the drummers', '<b>1 monitor</b> for the piano', '<b>1 bass amplifier</b> for the bass guitar'])
          + '</div><div>' + sect('Front of house', A, ['Microphones for solo instruments, conductor, etc.', 'Enough speakers for the audience', 'Sound system suited to the number of guests'])
          + '</div></div></div><div class="cols"><div>'
          + sect('Lighting', B, ['Basic stage lighting', 'Extra lighting for specific effects (optional)', 'Good visibility for artists and audience'])
          + '</div><div>' + sect('Stage', O, ['Solid stage area with enough room for <b>all members of the Salsa Brassband</b> (depending on line-up size)', 'Stage riser, if needed', 'Stable, level floor without obstacles']) + '</div></div>')
    p2 = ('<div class="kicker">Technical rider · Salsa Brassband</div><div class="cols" style="margin-top:4mm"><div>'
          + POWER + DRESS + TRANS + '</div><div>' + WEATHER + FOOD + '</div></div>' + IMPORTANT + QUESTIONS)
    return page(p1) + page(p2)

def rider_algemeen():
    body = ('<div class="kicker">Technical rider</div><h1>What we need</h1>'
            '<p class="intro">To make it a great show together, we ask the organisation to provide the following.</p>'
            f'<div class="photos"><img class="y" src="{img("assets/img/brassband.jpg")}" style="object-position:50% 30%;height:36mm"><img class="b" src="{img("assets/img/samba.jpg")}" style="object-position:50% 20%;height:36mm"></div>'
            '<div class="cols"><div>'
            + sect('Sound', Y, ['Microphones for solo instruments, conductor, etc.', 'Speakers for the audience'])
            + sect('Lighting', B, ['Basic stage lighting', 'Extra lighting for specific effects (optional)'])
            + sect('Stage', O, ['Solid stage area with enough room for all members of the brass band (depending on line-up size)', 'Stage riser, if needed'])
            + sect('Dressing room', G, ['A lockable dressing room where the artists can change and store their personal belongings safely'])
            + '<div class="dark" style="margin-top:4mm;padding:4mm 6mm"><h3>Any questions?</h3><p>info@lanegra-entertainment.nl<br>+31 6 25 17 80 50 (also WhatsApp)</p></div>'
            + '</div><div>'
            + sect('Indoors or outdoors', B, ['Outdoors, a covered area such as a tent or canopy is always required, where all members can stand and play. This keeps instruments and musicians dry.', 'Woodwind and brass instruments and expensive equipment in particular can be seriously damaged by moisture. This facility is the responsibility of the organisation and essential for the performance to go ahead.'])
            + sect('Food &amp; drinks', A, ['Water, soft drinks and snacks for band members and crew, before and during the performance', 'For long performances or rehearsals: catering'])
            + sect('Transport &amp; logistics', O, ['Parking for loading and unloading, as close to the venue as possible', 'Reserved parking for all band members\' vehicles'])
            + '</div></div>')
    return page(body)

def presskit():
    p1 = ('<div class="kicker">Press kit · Festivals</div><h1 class="big">La Negra Salsa Brassband</h1>'
          '<p class="lead">The power of a brass band with bass guitar, piano and a lead singer: live salsa and Latin music that pulls every crowd onto the dance floor. '
          'Not just a brass band, but a big band experience, made for festival stages, from Latin and Caribbean festivals to carnival and summer events.</p>'
          f'<div class="photos"><img class="y" src="{img("images/salsa-2.jpg")}" style="height:62mm"><img class="o" src="{img("images/salsa-5.jpg")}" style="height:62mm"></div>'
          '<div class="facts"><div><b>1000+</b><span>events</span></div><div><b>30+</b><span>years of experience</span></div><div><b>4.8</b><span>Google rating</span></div><div><b>NL · BE · DE</b><span>and beyond</span></div></div>'
          '<div class="cols"><div>'
          + sect('The show', Y, ['A full brass band with bass guitar, piano and lead vocals', 'Hot salsa and Latin rhythms that fill the dance floor', 'Set length in consultation, tailored to your programme', 'Combine a brass band set with a Salsa Brassband set', 'Optional: samba dancers or a salsa workshop'])
          + '</div><div>'
          + sect('Perfect for', B, ['Latin and salsa festivals', 'Caribbean and Antillean festivals', 'Carnival, city and summer festivals', 'Festival openings and parades (brass band as walking act)'])
          + '</div></div>')
    p2 = ('<div class="kicker">Press kit · La Negra Entertainment</div><h1>About us</h1>'
          '<p class="intro">La Negra Entertainment from Rotterdam brings Caribbean and Latin entertainment to festivals, city events and parties across the Netherlands, Belgium, Germany and beyond. '
          'Led by Elgin Felipa, with more than 30 years of experience and over 1000 events on stage.</p>'
          f'<div class="photos"><img class="b" src="{img("images/brassband-danseressen-podium.jpg")}"><img class="y" src="{img("assets/img/samba.jpg")}" style="object-position:50% 20%"></div>'
          '<div class="cols"><div>'
          + sect('Also available', O, ['Brass band (also as walking act)', 'Brass band with samba dancers', 'Samba dancers', 'Steel band', 'Latin Entertainment Band', 'Limbo show and fire breathers'])
          + '</div><div>'
          + sect('For organisers', G, ['Technical rider: lanegra-entertainment.nl/downloads/rider-salsa-brassband-en.pdf', 'Photos and info: lanegra-entertainment.nl/en/occasions/latin-festivals.html', 'Fee on request, depending on line-up, set length and travel', 'International bookings in consultation'])
          + '</div></div>'
          + '<div class="dark"><h3>Booking &amp; contact</h3><p><b>Elgin Felipa</b> · Manager<br>info@lanegra-entertainment.nl · +31 6 25 17 80 50 (WhatsApp)<br>lanegra-entertainment.nl · Instagram @lanegraentertainment</p></div>')
    return page(p1) + page(p2)

def partners():
    """Partnerprogramma (NL) zonder prijzen; de versie met prijzen staat in server/print/partnerprogramma-met-prijzen.pdf."""
    voor = ''.join(f'<div class="pcard"><i style="background:{c}"></i><b>{t}</b><span>{d}</span></div>' for c, t, d in [
        (Y, 'Trouwlocaties', 'Een vaste partner voor Caribische sfeer, van ceremonie tot feest.'),
        (B, 'Weddingplanners', 'Eén aanspreekpunt voor steelband, samba-entree, brassband en DJ.'),
        (G, 'Eventbureaus', 'Onze acts doorboeken voor bedrijfsfeesten en festivals, met jouw marge.'),
        (O, 'Feest- en eventlocaties', 'Arrangementen en themafeesten compleet met live entertainment.')])
    p1 = ('<div class="kicker">Partnerprogramma</div><h1>Samen meer feest</h1>'
          '<p class="intro lead">Werk je met bruidsparen, bedrijven of evenementen? Als partner van La Negra Entertainment bied je jouw klanten Caribisch en Latin '
          'live-entertainment, zonder gedoe. Je boekt onze acts voor je klanten met <b>10% partnerkorting</b> (vanaf je 5e boeking per jaar <b>15%</b>) en bepaalt zelf je verkoopprijs.</p>'
          f'<div class="photos"><img src="{img("assets/img/steelband.jpg")}" style="height:60mm"><img src="{img("assets/img/brassband-danseressen.jpg")}" style="height:60mm;object-position:50% 40%"></div>'
          f'<h2 style="font-size:16pt;margin-top:2mm">Voor wie</h2><div class="pgrid">{voor}</div>')
    pk = ''.join(f'<div class="prow" style="background:{bg}"><b>{n}</b><span>{d}</span><em>offerte op maat</em></div>' for n, d, bg in [
        ('Caribbean Wedding', 'Steelband, samba-entree met 2 danseressen en DJ', '#fff7dc'),
        ('Fiesta Bedrijfsfeest', 'Brassband (8), 2 samba danseressen en DJ', '#e3f4f9'),
        ('Tropisch Zomerfeest', 'Steelband en limboshow met vuurspuwers', '#f3f4f3'),
        ('Carnaval &amp; Optocht', 'Lopende brassband (7) met 2 samba danseressen', '#fff7dc')])
    stappen = ''.join(f'<div class="pstep"><span>{i}</span><div><b>{t}</b><p>{d}</p></div></div>' for i, t, d in [
        (1, 'Meld je aan', 'We maken kennis en spreken af hoe je klanten bij ons terechtkomen.'),
        (2, 'Boek ons voor je klant', 'Je vraagt een offerte aan en krijgt direct je partnerprijs; aan je klant bied je ons aan voor jouw eigen prijs.'),
        (3, 'Wij verzorgen het feest', 'Wij treden op en stemmen alles af met jou of rechtstreeks met je klant, zoals jij wilt.')])
    p2 = ('<h2 style="font-size:16pt;margin-top:0">Wat je krijgt</h2><ul class="vinkjes">'
          + ''.join(f'<li>{x}</li>' for x in ['10% partnerkorting op al onze acts en pakketten (excl. btw en reiskosten) als je ons boekt voor je klanten',
              '15% korting vanaf je 5e boeking in een kalenderjaar', 'Jij bepaalt de prijs voor je klant: je boekt en factureert zelf, dus de marge is voor jou',
              'Binnen één werkdag een voorstel op maat voor jouw klant', 'Voorrang bij drukke data zoals carnaval en het trouwseizoen',
              "Foto's, video's en teksten om onze acts op je eigen kanalen aan te bieden", 'Eén vaste contactpersoon die je locatie en werkwijze kent'])
          + '</ul><h2 style="font-size:16pt">Zo werkt het</h2>' + stappen
          + '<h2 style="font-size:16pt">Populaire pakketten voor jouw klanten</h2>' + pk
          + '<p class="klein">Je partnerprijs ontvang je binnen één werkdag in een voorstel op maat: 10% onder onze standaardprijs (15% vanaf je 5e boeking per jaar), '
            'excl. 9% btw en reiskosten. We leggen de afspraken vast in een korte partnerovereenkomst.</p>'
          + '<div class="dark pdark"><div><h3>Partner worden?</h3><p>Mail info@lanegra-entertainment.nl of bel/app <span style="white-space:nowrap">+31 6 25 17 80 50</span><br>Meer info: lanegra-entertainment.nl/partners.html</p></div>'
            '<span class="pill">Elgin Felipa · Manager</span></div>')
    return page(p1, True) + page(p2, True)

PCSS = """
.photos img { box-shadow: none; }
.pgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
.pcard { background: #f3f4f3; border-radius: 3mm; padding: 4mm 5mm 4mm 11mm; position: relative; font-size: 9.5pt; }
.pcard i { position: absolute; left: 5mm; top: 5.2mm; width: 3.2mm; height: 3.2mm; border-radius: 50%; }
.pcard b { display: block; font-size: 11pt; margin-bottom: 1mm; } .pcard span { color: #444; }
.vinkjes { list-style: none; padding: 0; } .vinkjes li { position: relative; padding-left: 7mm; margin-bottom: 1mm; font-size: 9.8pt; }
.vinkjes li::before { content: '✓'; position: absolute; left: 0; color: #d94f2b; font-weight: 700; }
.pstep { display: flex; gap: 4mm; align-items: flex-start; margin-bottom: 2mm; }
.pstep > span { flex: none; width: 7mm; height: 7mm; border-radius: 50%; background: #f5c518; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 9.5pt; }
.pstep b { font-size: 10.5pt; } .pstep p { margin: .5mm 0 0; font-size: 9.6pt; color: #444; }
.prow { display: grid; grid-template-columns: 42mm 1fr auto; gap: 4mm; align-items: center; border-radius: 3mm; padding: 3.2mm 5mm; margin-bottom: 2mm; font-size: 9.6pt; }
.prow em { font-style: normal; font-weight: 700; color: #d94f2b; white-space: nowrap; }
.klein { font-size: 8.6pt; color: #666; margin-top: 2mm; }
.pdark { display: flex; justify-content: space-between; align-items: center; gap: 6mm; margin-top: 4mm; padding: 4mm 7mm; }
.pill { background: #f5c518; color: #222; font-weight: 700; border-radius: 6mm; padding: 2.5mm 6mm; white-space: nowrap; font-size: 10pt; }
"""

def maak(naam, inhoud, titel, lang='en', extra_css=''):
    doc = f'<!doctype html><html lang="{lang}"><head><meta charset="utf-8"><title>{html.escape(titel)}</title><style>{CSS}{extra_css}</style></head><body>{inhoud}</body></html>'
    with tempfile.NamedTemporaryFile('w', suffix='.html', delete=False) as f:
        f.write(doc); tmp = f.name
    out = ROOT / 'downloads' / naam
    subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', f'--print-to-pdf={out}', '--virtual-time-budget=8000', 'file://' + tmp],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    os.unlink(tmp)
    print('gemaakt:', out.relative_to(ROOT))

if __name__ == '__main__':
    maak('rider-en.pdf', rider_algemeen(), 'Technical rider – La Negra Entertainment')
    maak('rider-salsa-brassband-en.pdf', rider_salsa(), 'Technical rider Salsa Brassband – La Negra Entertainment')
    maak('presskit-salsa-brassband.pdf', presskit(), 'Press kit Salsa Brassband – La Negra Entertainment')
    maak('partnerprogramma.pdf', partners(), 'Partnerprogramma – La Negra Entertainment', 'nl', PCSS)
