#!/usr/bin/env bash
set -euo pipefail

sigra_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
sigra_source="$sigra_root/mobile"
sigra_destination="$(dirname -- "$sigra_root")/sigra-mobile"
sigra_dry_run=false
if [[ "${1:-}" == "--dry-run" ]]; then sigra_dry_run=true; shift; fi
if [[ $# -gt 0 ]]; then sigra_destination="$1"; shift; fi
if [[ $# -gt 0 ]]; then echo "Uso: $0 [--dry-run] [destino]" >&2; exit 2; fi
[[ -d "$sigra_source/app" ]] || { echo "No existe la app en $sigra_source" >&2; exit 1; }
if [[ -e "$sigra_destination" || -L "$sigra_destination" ]]; then
    echo "El destino ya existe; no se sobrescribirá: $sigra_destination" >&2
    exit 1
fi
command -v python3 >/dev/null
echo "Origen:  $sigra_source"
echo "Destino: $sigra_destination"
if $sigra_dry_run; then exit 0; fi

mv --no-clobber --no-target-directory -- "$sigra_source" "$sigra_destination"
[[ ! -e "$sigra_source" && -d "$sigra_destination/app" ]] || { echo "El traslado no se completó." >&2; exit 1; }

# Only update backend references after the directory has actually moved.
python3 - "$sigra_root" "$sigra_destination" <<'PY'
from pathlib import Path
import os
import re
import sys

root, target = map(Path, sys.argv[1:])
relative = os.path.relpath(target, root)
files = ['README.md', 'README.es.md', 'README_GUIA.md', 'docs/RED_TELEFONOS.md', 'docs/API_RED.md']
for name in files:
    file = root / name
    with file.open(newline='') as handle:
        content = handle.read()
    content = re.sub(r'\bcd mobile(?=[/\s`]|$)', lambda _: 'cd ' + relative, content)
    content = re.sub(r'(?<![\w-])mobile/', lambda _: relative + '/', content)
    with file.open('w', newline='') as handle:
        handle.write(content)
ignore = root / '.gitignore'
ignore.write_text(''.join(line for line in ignore.read_text().splitlines(keepends=True) if not line.startswith('mobile/')))
PY
echo "App trasladada. Abre $sigra_destination como proyecto independiente."
