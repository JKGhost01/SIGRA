"""Package runtime changes from SIGRA's pre-modular baseline, excluding server data."""
import argparse
import hashlib
from pathlib import Path
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--base', default='2f7b173708544da95a5868da6068dec4322e2d54')
args = parser.parse_args()


def paths(*arguments):
    raw = subprocess.check_output(['git', *arguments], cwd=root)
    return {path.decode() for path in raw.split(b'\0') if path}


files = paths('diff', '--name-only', '--diff-filter=ACMRT', '-z', args.base, '--', 'app', 'public', 'sw.js', 'offline.html', 'composer.json', 'composer.lock', 'tools/setup-remoto.php')
files |= paths('ls-files', '--others', '--exclude-standard', '-z', '--', 'app', 'public', 'sw.js', 'offline.html', 'composer.json', 'composer.lock', 'tools/setup-remoto.php')
files = sorted(path for path in files if (root / path).is_file())
removed = sorted(paths('diff', '--name-only', '--diff-filter=D', '-z', args.base, '--', 'app', 'public', 'sw.js', 'offline.html', 'composer.json', 'composer.lock', 'tools/setup-remoto.php'))
for path in files:
    assert (path.startswith(('app/', 'public/')) or path in {'sw.js', 'offline.html', 'composer.json', 'composer.lock', 'tools/setup-remoto.php'}) and '..' not in Path(path).parts
    assert Path(path).name != '.env'

output = root / 'dist' / 'sigra-modular-sin-cron.zip'
output.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(root / path, path)
    archive.write(root / 'deploy/LEEME-SUBIDA.md', 'docs/deployment/LEEME-SUBIDA.md')
    archive.write(root / 'deploy/auditoria-modular.sql', 'docs/deployment/auditoria-modular.sql')
    archive.writestr('docs/deployment/archivos-retirados.txt', '\n'.join(removed) + '\n')
    archive.writestr('docs/deployment/archivos-incluidos.txt', '\n'.join(files) + '\n')
    archive.writestr('docs/deployment/sha256.txt', ''.join(
        hashlib.sha256((root / path).read_bytes()).hexdigest() + '  ' + path + '\n' for path in files))
with zipfile.ZipFile(output) as archive:
    assert archive.testzip() is None
    assert all(not name.startswith(('writable/', 'vendor/', '.git/')) and Path(name).name != '.env'
               for name in archive.namelist())
print(f'{output}: {len(files)} runtime files, {len(removed)} obsolete paths; archive verified.')
