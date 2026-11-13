"""The installer initializes blank secrets without consuming subsequent dotenv lines."""
from pathlib import Path
import re
import subprocess
import tempfile

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='sigra-secret-test-') as directory:
    for newline in ['\n', '\r\n']:
        for empty in ['', "''"]:
            target = Path(directory) / 'private.env'
            target.write_bytes(('APP_API_TOKEN = ' + empty + newline + '# Keep this comment' + newline
                                + 'encryption.key = ' + empty + newline + '# And this one' + newline).encode())
            first_run = subprocess.run(['php', 'tools/setup-local.php', str(target)], cwd=root,
                                       capture_output=True, text=True, check=True)
            first = target.read_bytes()
            text = first.decode()
            for pattern in [r"APP_API_TOKEN = '([a-f0-9]{64})'", r"encryption.key = 'hex2bin:([a-f0-9]{64})'"]:
                match = re.search(pattern, text)
                assert match and match[1] not in first_run.stdout
            assert '# Keep this comment' in text and '# And this one' in text
            subprocess.run(['php', 'tools/setup-local.php', str(target)], cwd=root,
                           capture_output=True, check=True)
            assert first == target.read_bytes(), 'Existing real secrets changed'
print('Secret setup: blank/quoted LF/CRLF values, preserved comments, no key output and idempotency passed.')
