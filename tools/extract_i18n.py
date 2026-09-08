#!/usr/bin/env python3
"""Extrait les chaines traduisibles du plugin et met a jour core/i18n/<lang>.json.

Deux formes sont reconnues, celles que Jeedom sait traduire :
  - __('texte', __FILE__)  dans le PHP
  - {{texte}}              dans les vues, les gabarits de widget et le JavaScript

Le fichier de langue conserve les traductions deja saisies : une chaine disparue du
code est retiree, une chaine nouvelle est ajoutee avec une valeur vide. Rien n'est
traduit automatiquement ici — ce script ne fait que tenir l'inventaire a jour.

  python tools/extract_i18n.py            controle et rapporte les manques
  python tools/extract_i18n.py --ecrire   met les fichiers de langue a jour
"""
import io
import json
import os
import re
import sys

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGIN = 'gds3710'
DOSSIERS = ('core', 'desktop', 'plugin_info')
EXTENSIONS = ('.php', '.html', '.js')
LANGUES = ('en_US', 'de_DE', 'es_ES')

# __('...', __FILE__) en guillemets simples ou doubles, apostrophes echappees comprises.
RE_GETTEXT = re.compile(r"__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*__FILE__\s*\)"
                        r"|__\(\s*\"((?:[^\"\\]|\\.)*)\"\s*,\s*__FILE__\s*\)")
RE_ACCOLADES = re.compile(r'\{\{(.+?)\}\}', re.DOTALL)


def fichiers():
    for dossier in DOSSIERS:
        for base, _, noms in os.walk(os.path.join(RACINE, dossier)):
            for nom in sorted(noms):
                if not nom.endswith(EXTENSIONS) or 'jssip' in nom:
                    continue
                chemin = os.path.join(base, nom)
                rel = os.path.relpath(chemin, RACINE).replace(os.sep, '/')
                yield chemin, 'plugins/' + PLUGIN + '/' + rel


def extraire():
    """Renvoie { 'plugins/gds3710/<fichier>': [chaines triees] }."""
    trouve = {}
    for chemin, clef in fichiers():
        texte = io.open(chemin, encoding='utf-8', errors='replace').read()
        chaines = set()
        for m in RE_GETTEXT.finditer(texte):
            brut = m.group(1) if m.group(1) is not None else m.group(2)
            # Le PHP a deja deshabille \' et \" a l'execution : la clef stockee est
            # la chaine reelle, pas sa forme echappee dans le source.
            chaines.add(brut.replace("\\'", "'").replace('\\"', '"').replace('\\\\', '\\'))
        for m in RE_ACCOLADES.finditer(texte):
            # Surtout pas de strip() : translate::exec() cherche le texte BRUT place
            # entre les accolades, espaces compris. « {{ :}} » se traduit sous la clef
            # « :» avec son espace, et une clef rognee ne correspondrait a rien.
            valeur = m.group(1)
            if not valeur.strip() or valeur.lstrip().startswith('#'):
                # « {{}} » vide, et les gabarits Jeedom du type #id# ne se traduisent pas.
                continue
            if '$' in valeur or "'." in valeur or ".'" in valeur:
                # Marqueur construit par concatenation PHP, par exemple
                # '{{'.$row['type'].' - '.$row['message'].' :}}'. La chaine reelle
                # n'existe qu'a l'execution et change a chaque evenement : elle ne
                # peut pas figurer dans un catalogue statique.
                continue
            if chemin.endswith('.php'):
                # Le marqueur vit dans un litteral PHP : « d\'image » y designe
                # « d'image ». C'est la chaine rendue que Jeedom cherchera a traduire.
                valeur = valeur.replace("\\'", "'").replace('\\"', '"')
            chaines.add(valeur)
        if chaines:
            trouve[clef] = sorted(chaines)
    return trouve


def charge(langue):
    chemin = os.path.join(RACINE, 'core', 'i18n', langue + '.json')
    if not os.path.exists(chemin):
        return {}
    return json.load(io.open(chemin, encoding='utf-8'))


def ecrit(langue, donnees):
    dossier = os.path.join(RACINE, 'core', 'i18n')
    if not os.path.isdir(dossier):
        os.makedirs(dossier)
    chemin = os.path.join(dossier, langue + '.json')
    # Jeedom ecrit ces fichiers avec des slash echappes et une indentation de 4.
    texte = json.dumps(donnees, ensure_ascii=False, indent=4, sort_keys=True)
    io.open(chemin, 'w', encoding='utf-8', newline='\n').write(texte + '\n')


def main():
    ecrire = '--ecrire' in sys.argv
    attendu = extraire()
    total = sum(len(v) for v in attendu.values())
    print('%d chaine(s) traduisible(s) dans %d fichier(s)' % (total, len(attendu)))

    code = 0
    for langue in LANGUES:
        actuel = charge(langue)
        nouveau = {}
        manquantes = 0
        for clef, chaines in sorted(attendu.items()):
            bloc = {}
            for s in chaines:
                bloc[s] = actuel.get(clef, {}).get(s, '')
                if bloc[s] == '':
                    manquantes += 1
            nouveau[clef] = bloc
        obsoletes = 0
        for clef, bloc in actuel.items():
            for s in bloc:
                if s not in attendu.get(clef, []):
                    obsoletes += 1
        etat = '%-6s : %d traduite(s), %d manquante(s), %d obsolete(s)' % (
            langue, total - manquantes, manquantes, obsoletes)
        print('  ' + etat)
        if ecrire:
            ecrit(langue, nouveau)
        elif manquantes or obsoletes:
            code = 1
    if ecrire:
        print('Fichiers de langue mis a jour.')
    return code


if __name__ == '__main__':
    sys.exit(main())
