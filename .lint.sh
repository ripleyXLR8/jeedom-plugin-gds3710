#!/bin/bash
# Controles avant commit : syntaxe PHP 8.2 (via Docker) et coherence de la documentation.
cd "$(dirname "$0")"
fail=0

echo "== syntaxe PHP 8.2 =="
export MSYS_NO_PATHCONV=1
docker run --rm -v "$(pwd -W 2>/dev/null || pwd)":/app -w //app php:8.2-cli \
  bash -c 'f=0; for x in $(find core desktop plugin_info -name "*.php"); do
             out=$(php -l "$x" 2>&1); [ $? -ne 0 ] && { echo "$out"; f=1; };
           done; [ $f -eq 0 ] && echo "  OK sur tous les fichiers"; exit $f' || fail=1

# Le README et docs/ ont deja diverge deux fois : une fois le README seul mis a jour,
# une fois docs/ seul. Ces deux oublis ont laisse aux utilisateurs une documentation
# fausse la ou elle comptait. Ce controle rend l oubli impossible a manquer.
echo "== parite README / documentation =="
MARQUEURS=(
  "Configurer le portier"
  "Remonter les capteurs"
  "Conserver les captures"
  "contrôle d'adresse d'origine|contrôle de l'adresse d'origine"
  "1.0.11.18"
  "Dernière personne entrée"
  "Réglages du portier"
  "Client SIP"
  "DOOR_NUM"
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

[ $fail -eq 0 ] && echo "== tout est vert ==" || echo "== ECHEC =="
exit $fail
