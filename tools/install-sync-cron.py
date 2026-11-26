#!/usr/bin/env python3
"""Print or install SIGRA's per-user catalog schedule; never contact providers."""
import argparse
import hashlib
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import sys


def cron_line(root, php):
    # Cron treats % as a newline even inside shell quotes.
    for value in (str(root), str(php)):
        if any(c in value for c in "\r\n%"):
            raise ValueError("Las rutas no pueden contener saltos de línea ni %.")
    marker = "# sigra-sync:" + hashlib.sha256(os.fsencode(root)).hexdigest()[:16]
    command = " ".join(shlex.quote(str(v)) for v in (
        "/bin/bash", root / "tools/sync-catalog.sh", php))
    return f"* * * * * {command} {marker}", marker


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--install", action="store_true", help="Instalar en el crontab del usuario actual")
    parser.add_argument("--php", default=shutil.which("php"), help="Ruta del PHP CLI del hosting (8.2+)")
    args = parser.parse_args()
    if not args.php:
        parser.error("Indica --php /ruta/al/php")
    php = Path(args.php).resolve()
    if not php.is_file() or not os.access(php, os.X_OK):
        parser.error("El PHP seleccionado no es un ejecutable")
    root = Path(__file__).resolve().parent.parent
    line, marker = cron_line(root, php)
    if not args.install:
        print(line)
        return
    # Fail before touching the schedule if CLI is missing required extensions.
    subprocess.run([str(php), "-r", "exit(PHP_VERSION_ID >= 80200 && "
                    "extension_loaded('curl') && extension_loaded('intl') && "
                    "extension_loaded('mbstring') && extension_loaded('mysqli') ? 0 : 1);"], check=True)
    if not (root / ".env").is_file() or not (root / "vendor/autoload.php").is_file():
        parser.error("Configura .env e instala vendor antes de programar SIGRA")
    result = subprocess.run(["crontab", "-l"], text=True, capture_output=True,
                            env={**os.environ, "LC_ALL": "C"})
    if result.returncode != 0:
        if result.returncode != 1 or "no crontab for" not in result.stderr.lower():
            raise RuntimeError("No se pudo leer el crontab; no se modificó: " + result.stderr.strip())
        previous = ""
    else:
        previous = result.stdout
    # Replace only this installation's managed entry; retain unrelated jobs.
    lines = [item for item in previous.splitlines() if not item.rstrip().endswith(marker)]
    subprocess.run(["crontab", "-"], input="\n".join(lines + [line]) + "\n", text=True, check=True)
    print("Sincronización instalada cada minuto; caché y pausas de fuentes respetadas.")
    print(line)


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Error: {error}", file=sys.stderr)
        sys.exit(1)
