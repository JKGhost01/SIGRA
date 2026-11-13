"""Check SIGRA source visibility and ignored runtime artifacts before committing."""
from pathlib import Path
import re
import subprocess
import sys

root = Path(__file__).resolve().parents[1]


def ignored(path):
    result = subprocess.run(
        ['git', 'check-ignore', '--quiet', '--no-index', '--', path], cwd=root)
    if result.returncode not in (0, 1):
        raise RuntimeError(f'git check-ignore failed for {path}')
    return result.returncode == 0


private_or_generated = [
    '.env', '.env.production', 'vendor/autoload.php',
    'tools/node_modules/example/index.js',
    'writable/cache/catalog-refresh.json', 'writable/cache/ingestion.lock',
    'writable/logs/log-example.log', 'writable/session/ci_session_example',
    'writable/test-web/example.png', 'dist/sigra-modular-sin-cron.zip',
    'docs/deployment/archivos-incluidos.txt', 'backups/example.sql',
    'database-private.sql', 'tests/__pycache__/example.pyc',
    'writable/cache/phpstan/resultCache.php',
]
source_files = [
    '.env.example', 'env', 'composer.lock', 'tools/package-lock.json',
    'writable/cache/.gitkeep', 'writable/.htaccess',
    'app/Modules/SeismicCatalog/Application/Queries/ListEvents/ListEventsHandler.php',
    'app/Shared/Domain/Clock.php',
    'app/Database/Migrations/20261112000002_CreateArchitectureAudit.php',
    'public/assets/js/modules/seismic-map/catalog.js',
    'tests/Support/cgi.php', 'tests/Contract/fixtures/legacy-event.json',
    'docs/api/openapi.yaml', 'docs/architecture/README.md',
    'deploy/auditoria-modular.sql', 'deploy/LEEME-SUBIDA.md',
    'deploy/corregir-coordenadas-usgs.sql',
    'app/Database/Migrations/20261113000001_RepairUsgsCoordinates.php',
    'tests/Unit/FeedTransportTest.php', 'tools/test-map-coordinates.js',
    'tools/build-upload.py', 'tools/check-git.py',
    'phpstan.neon', 'tools/setup-local.php', 'tests/SetupLocalTest.py',
    'tests/Contract/fixtures/providers/usgs-public-event.json',
    'docs/RECOMMENDATIONS_STATUS.md',
]
errors = [f'Not ignored: {path}' for path in private_or_generated if not ignored(path)]
errors += [f'Source incorrectly ignored: {path}' for path in source_files if ignored(path)]
tracked_ignored = subprocess.check_output(
    ['git', 'ls-files', '-ci', '--exclude-standard'], cwd=root, text=True).splitlines()
errors += [f'Already tracked despite ignore rules: {path}' for path in tracked_ignored]

# The SQL exception must stay a schema-only deployment artifact.
schema = (root / 'deploy/auditoria-modular.sql').read_text()
statements = [line for line in schema.splitlines() if not line.lstrip().startswith('--')]
if re.search(r'\b(?:INSERT\s+INTO|REPLACE\s+INTO|DROP\s+TABLE|TRUNCATE\s+TABLE)\b',
             '\n'.join(statements), re.I):
    errors.append('Deployment SQL contains data or destructive statements')

if errors:
    print('\n'.join(errors), file=sys.stderr)
    sys.exit(1)
print(f'Git checks passed: {len(private_or_generated)} private/generated paths ignored; '
      f'{len(source_files)} source paths visible; no ignored files are tracked.')
