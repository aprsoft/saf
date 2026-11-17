#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f routes/web.php || ! -f routes/sales-counter-v2.php ]]; then
  echo 'Ejecutar desde la raíz de SAF, después de copiar routes/sales-counter-v2.php' >&2
  exit 1
fi

if grep -Fq "require __DIR__.'/sales-counter-v2.php';" routes/web.php; then
  echo 'Rutas del mesón v2 ya registradas.'
  exit 0
fi

printf "\nrequire __DIR__.'/sales-counter-v2.php';\n" >> routes/web.php
echo 'Rutas del mesón v2 registradas.'
