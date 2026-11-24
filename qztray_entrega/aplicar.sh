#!/usr/bin/env bash
set -euo pipefail

PROJECT="${1:-$HOME/Proyectos/saf}"
SOURCE="$(cd "$(dirname "$0")" && pwd)"
cd "$PROJECT"

if [[ ! -d .git ]]; then echo 'ERROR: no es el directorio raíz del repositorio SAF.' >&2; exit 1; fi
if [[ -n "$(git status --porcelain)" ]]; then
  echo 'ERROR: existen cambios locales. No se cambia de rama ni se sobrescriben archivos.' >&2
  git status --short
  exit 1
fi
if ! git remote get-url origin >/dev/null 2>&1; then
  echo 'ERROR: el remoto origin no existe. No se modifica el repositorio.' >&2
  exit 1
fi

git fetch origin qztray
if git show-ref --verify --quiet refs/heads/qztray; then
  git switch qztray
else
  git switch -c qztray --track origin/qztray
fi

python3 - "$SOURCE" <<'PY'
import pathlib, sys
source = pathlib.Path(sys.argv[1])
repo = pathlib.Path.cwd()
p = repo / 'resources/js/app.js'
s = p.read_text()
old = "import printerSettings from './printing/qz.js';"
if old not in s:
    raise SystemExit('ERROR: no se encuentra el import original; revisar app.js manualmente.')
if "Alpine.data('printerSettings', printerSettings);" not in s:
    raise SystemExit('ERROR: falta registro Alpine original; revisar app.js manualmente.')
if 'Livewire.start();' not in s:
    raise SystemExit('ERROR: falta Livewire.start(); revisar app.js manualmente.')
s = s.replace(old, "import { registerQzPrinting } from './printing/qz.js';", 1)
s = s.replace("Alpine.data('printerSettings', printerSettings);", "registerQzPrinting(Livewire);", 1)

files = [
    'resources/js/printing/qz.js',
    'resources/views/livewire/printing/printer-settings.blade.php',
    'tests/Unit/Printing/qz.test.js',
]
for name in files:
    if not (repo / name).is_file():
        raise SystemExit(f'ERROR: falta archivo base {name}; no se aplica el cambio.')

p.write_text(s)
for name in files:
    (repo / name).write_bytes((source / name).read_bytes())
PY

printf '\nCambios preparados en rama: '; git branch --show-current
printf '\n'; git status --short
printf '\nValidación recomendada:\n'
echo 'node --test tests/Unit/Printing/qz.test.js'
echo 'php artisan test --compact tests/Feature/Printing/QzPrintingTest.php tests/Feature/Sales/ModalidadMesonTest.php'
echo 'npm run build'
echo 'No se ha creado ningún commit ni ejecutado push.'
