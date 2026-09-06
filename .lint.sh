#!/bin/bash
# Controles avant commit : syntaxe, catalogue de traduction, coherence de la documentation.
#
# Le meme script sert en local et en integration continue. En local il n'y a ni php ni
# node installes : il retombe alors sur Docker. En CI ils sont fournis par le runner et
# sont utilises directement. La CI execute donc exactement les memes controles au lieu
# de les recopier — ce plugin a deja paye cher deux listes recopiees a la main.
#
#   ./.lint.sh              tout
#   ./.lint.sh --syntaxe    syntaxe PHP et JavaScript seulement
#   ./.lint.sh --i18n       catalogue de traduction seulement
#   ./.lint.sh --docs       README, documentation et langues seulement
cd "$(dirname "$0")"
fail=0
export MSYS_NO_PATHCONV=1

ARGS="$*"
tout=1
for a in $ARGS; do
  case "$a" in --syntaxe|--i18n|--docs) tout=0;; esac
done
veut() {
  [ $tout -eq 1 ] && return 0
  for a in $ARGS; do [ "$a" = "$1" ] && return 0; done
  return 1
}

MONTAGE="$(pwd -W 2>/dev/null || pwd)"
PHP_NATIF=$(command -v php || true)
NODE_NATIF=$(command -v node || true)
PYTHON=$(command -v python3 || command -v python || true)

php_lint() {
  if [ -n "$PHP_NATIF" ]; then
    local f code=0
    for f in "$@"; do php -l "$f" >/dev/null || code=1; done
    return $code
  fi
  docker run --rm -v "$MONTAGE":/app -w //app php:8.2-cli \
    bash -c 'f=0; for x in "$@"; do out=$(php -l "$x" 2>&1) || { echo "$out"; f=1; }; done; exit $f' _ "$@"
}

node_check() {
  if [ -n "$NODE_NATIF" ]; then
    node --check "$1" 2>/tmp/gdsjs.err
  else
    docker run --rm -v "$MONTAGE":/w -w //w node:22-alpine node --check "$1" 2>/tmp/gdsjs.err
  fi
}

if veut --syntaxe; then
  if [ -n "$PHP_NATIF" ]; then
    echo "== syntaxe PHP $(php -r 'echo PHP_VERSION;') =="
  else
    echo "== syntaxe PHP 8.2 (Docker) =="
  fi
  FICHIERS=$(find core desktop plugin_info -name "*.php" | sort)
  if php_lint $FICHIERS; then
    echo "  OK sur $(echo "$FICHIERS" | wc -l) fichier(s)"
  else
    fail=1
  fi

  # Le JavaScript du widget SIP est long et vit dans un fichier .html : rien ne le
  # verifie autrement. Un vrai analyseur, pas un comptage d accolades — celui-ci se
  # faisait piéger par une simple apostrophe dans un commentaire.
  echo "== syntaxe du widget SIP =="
  TPL=core/template/dashboard/cmd.info.string.sipclient.html
  if [ -f "$TPL" ] && [ -n "$PYTHON" ]; then
    "$PYTHON" -c "
import io, sys
s = io.open(sys.argv[1], encoding='utf-8').read()
i = s.index('<script>', s.index('jssip'))
io.open(sys.argv[2], 'w', encoding='utf-8', newline='\n').write(s[i+len('<script>'):s.rindex('</script>')])
" "$TPL" .widget.tmp.js
    if node_check .widget.tmp.js; then
      echo "  OK"
    else
      echo "  ERREUR DE SYNTAXE :"; sed -n "1,6p" /tmp/gdsjs.err | sed "s/^/    /"; fail=1
    fi
    rm -f .widget.tmp.js /tmp/gdsjs.err
  fi

  # Le JavaScript de la page d equipement echappait au controle : une erreur y rend la
  # page muette, sans rien dans le log du plugin.
  echo "== syntaxe du JavaScript de la page d equipement =="
  for JS in desktop/js/*.js; do
    case "$JS" in *jssip*) continue;; esac
    [ -f "$JS" ] || continue
    if node_check "$JS"; then
      echo "  OK  $JS"
    else
      echo "  ERREUR DE SYNTAXE dans $JS :"; sed -n "1,6p" /tmp/gdsjs.err | sed "s/^/    /"; fail=1
    fi
    rm -f /tmp/gdsjs.err
  done
fi

if veut --i18n; then
  # Une chaine ajoutee au code sans entree dans les fichiers de langue s'affiche en
  # francais pour tout le monde, sans que rien ne le signale. Ce controle rend l oubli
  # visible ; « python tools/extract_i18n.py --ecrire » met le catalogue a jour.
  echo "== catalogue de traduction =="
  if [ -n "$PYTHON" ]; then
    if "$PYTHON" tools/extract_i18n.py; then
      echo "  OK, aucune chaine sans traduction"
    else
      echo "  Lancez « python tools/extract_i18n.py --ecrire », puis traduisez les entrees vides."
      fail=1
    fi
  else
    echo "  ignore : python introuvable"
  fi
fi

if veut --docs; then
  # Le README et docs/ ont deja diverge deux fois : une fois le README seul mis a jour,
  # une fois docs/ seul. Ces deux oublis ont laisse aux utilisateurs une documentation
  # fausse la ou elle comptait. Ce controle rend l oubli impossible a manquer.
  echo "== parite README / documentation =="
  # Le README est en anglais et la documentation en francais : chaque marqueur doit donc
  # reconnaitre les deux formulations, sans quoi le controle signalerait une divergence
  # sur chaque concept.
  MARQUEURS=(
    "Configurer le portier"
    "Remonter les capteurs|Poll the door station"
    "Conserver les captures|Keep snapshots"
    "contrôle d'adresse d'origine|contrôle de l'adresse d'origine|source address check"
    "1.0.11.18"
    "Dernière personne entrée|last person in"
    "Réglages du portier|Door station settings"
    "Client SIP|SIP client"
    "connect-src|Politique de sécurité"
    "DOOR_NUM"
    "core/i18n|traduite|translated"
  )
  for m in "${MARQUEURS[@]}"; do
    in_readme=0; in_doc=0
    grep -qiE "$m" README.md 2>/dev/null && in_readme=1
    grep -qiE "$m" docs/fr_FR/index.md 2>/dev/null && in_doc=1
    if [ $in_readme -ne $in_doc ]; then
      printf "  DIVERGENCE : « %s » -> README=%s doc=%s\n" "$m" "$in_readme" "$in_doc"
      fail=1
    fi
  done
  [ $fail -eq 0 ] && echo "  OK, aucun marqueur present d un seul cote"

  echo "== les 4 langues sont identiques =="
  n=$(md5sum docs/*/index.md | awk '{print $1}' | sort -u | wc -l)
  if [ "$n" -ne 1 ]; then echo "  DIVERGENCE : $n versions differentes de index.md"; fail=1; else echo "  OK"; fi
  n=$(md5sum docs/*/changelog.md | awk '{print $1}' | sort -u | wc -l)
  if [ "$n" -ne 1 ]; then echo "  DIVERGENCE : $n versions differentes de changelog.md"; fail=1; else echo "  OK"; fi
fi

[ $fail -eq 0 ] && echo "== tout est vert ==" || echo "== ECHEC =="
exit $fail
