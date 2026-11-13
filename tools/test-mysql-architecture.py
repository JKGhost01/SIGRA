"""Migrate and test an explicitly named disposable database; never use SIGRA's default DB."""
import argparse
import os
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--database', required=True)
parser.add_argument('--socket')
args = parser.parse_args()
if not (args.database.startswith('sigra_') and args.database.endswith('_test')):
    parser.error('Use a dedicated sigra_*_test database.')
env = dict(os.environ)
env.update({'SIGRA_TEST_DATABASE': args.database, 'database.default.database': args.database,
            'database.default.hostname': args.socket or '127.0.0.1',
            'database.default.username': os.environ.get('SIGRA_TEST_USER', 'root'),
            'database.default.password': os.environ.get('SIGRA_TEST_PASSWORD', ''),
            'database.default.port': os.environ.get('SIGRA_TEST_PORT', '3306')})
for command, marker in [(['php', '-d', 'variables_order=EGPCS', 'tests/Integration/MigrateTestDatabase.php'], 'Migrations complete'),
                        (['php', '-d', 'variables_order=EGPCS', 'tests/Integration/RepositoriesTest.php'], 'rollback passed'),
                        (['php', '-d', 'variables_order=EGPCS', 'tests/CitizenReportsTest.php'], 'checks passed'),
                        (['php', 'tests/Integration/AlertAtomicityTest.php'], 'cancellation passed')]:
    result = subprocess.run(command, cwd=root, env=env, capture_output=True, text=True)
    print(result.stdout, end='')
    print(result.stderr, end='')
    if result.returncode or marker.lower() not in result.stdout.lower():
        raise SystemExit(f'Failed: {command}')

for public_root in ['0', '1']:
    with tempfile.TemporaryDirectory(prefix='sigra-http-writable-') as writable:
        for directory in ['cache', 'logs', 'session', 'uploads', 'debugbar']:
            (Path(writable) / directory).mkdir()
        env['SIGRA_TEST_WRITEPATH'] = writable
        env['SIGRA_TEST_PUBLIC_ROOT'] = public_root
        subprocess.run(['php', 'tests/Integration/HttpFixture.php', 'seed'], cwd=root, env=env, check=True)
        try:
            subprocess.run(['python3', 'tests/Integration/HttpDatabaseTest.py'], cwd=root, env=env, check=True)
        finally:
            subprocess.run(['php', 'tests/Integration/HttpFixture.php', 'cleanup'], cwd=root, env=env, check=True)
