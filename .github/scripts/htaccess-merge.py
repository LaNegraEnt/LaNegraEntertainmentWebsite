#!/usr/bin/env python3
"""Voegt het beveiligingsblok uit server/beveiliging.htaccess toe aan de bestaande .htaccess van de server.

Gebruik: htaccess-merge.py <huidige .htaccess> <blok> [<absoluut pad naar .htpasswd-beheer>]
Alles buiten de BEGIN/END-markeringen (bijv. de doorverwijzingen) blijft precies zoals het was;
een eerder geplaatst blok wordt vervangen. Schrijft het resultaat naar stdout.
"""
import re
import sys

BEGIN = '# BEGIN La Negra beveiliging'
END = '# END La Negra beveiliging'

huidig = open(sys.argv[1], encoding='utf-8', errors='surrogateescape').read()
blok = open(sys.argv[2], encoding='utf-8').read().strip('\n')
htpasswd = sys.argv[3] if len(sys.argv) > 3 else ''

if htpasswd:
    auth = ('# Beheerpagina\'s achter een wachtwoord\n'
            '<FilesMatch "^(admin-offerte\\.html|leads-overzicht\\.html|save-lead\\.php)$">\n'
            '  AuthType Basic\n'
            '  AuthName "La Negra beheer"\n'
            f'  AuthUserFile "{htpasswd}"\n'
            '  Require valid-user\n'
            '</FilesMatch>')
else:
    auth = '# (geen beheerwachtwoord ingesteld)'
blok = blok.replace('# {{BEHEER_AUTH}}', auth)

zonder = re.sub(re.escape(BEGIN) + r'.*?' + re.escape(END) + r'[^\n]*\n?', '', huidig, flags=re.S).rstrip('\n')
sys.stdout.write((zonder + '\n\n' if zonder else '') + blok + '\n')
