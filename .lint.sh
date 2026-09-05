#!/bin/bash
# Controle de syntaxe PHP 8.2 de tout le plugin, via Docker.
cd "$(dirname "$0")"
export MSYS_NO_PATHCONV=1
docker run --rm -v "$(pwd -W 2>/dev/null || pwd)":/app -w //app php:8.2-cli \
  bash -c 'fail=0; for f in $(find core desktop plugin_info -name "*.php"); do
             out=$(php -l "$f" 2>&1); [ $? -ne 0 ] && { echo "$out"; fail=1; };
           done; [ $fail -eq 0 ] && echo "OK - syntaxe PHP 8.2 valide sur tous les fichiers"; exit $fail'
