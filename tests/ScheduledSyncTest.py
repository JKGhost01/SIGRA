"""Exercise the installed cron command without a web request or live providers."""
import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parent.parent


class ScheduledSyncTest(unittest.TestCase):
    def test_install_and_execute_without_browser(self):
        with tempfile.TemporaryDirectory(prefix="sigra scheduler '") as tmp:
            root = Path(tmp)
            (root / "tools").mkdir()
            (root / "vendor").mkdir()
            (root / "vendor/autoload.php").touch()
            (root / ".env").touch()
            (root / "spark").touch()
            for name in ("install-sync-cron.py", "sync-catalog.sh"):
                shutil.copy(ROOT / "tools" / name, root / "tools" / name)
            php = root / "fake php"
            php.write_text("#!/bin/sh\n[ \"$1\" = '-r' ] && exit 0\n"
                           "[ \"$PWD\" = \"$TEST_ROOT\" ] || exit 99\n"
                           "[ \"$1 $2\" = 'spark sigra:sync' ] || exit 98\n"
                           "echo 'catalog refreshed without HTTP'\nexit \"${SYNC_EXIT:-0}\"\n")
            php.chmod(0o700)
            cron = root / "crontab"
            cron.write_text("#!/bin/sh\nif [ \"$1\" = '-l' ]; then\n"
                            "  [ -n \"$CRON_FAIL\" ] && { echo denied >&2; exit 1; }\n"
                            "  cat \"$TEST_ROOT/entries\"\nelse\n  cat > \"$TEST_ROOT/entries\"\nfi\n")
            cron.chmod(0o700)
            entries = root / "entries"
            entries.write_text("# keep this job\n15 * * * * /usr/bin/true\n")
            env = {**os.environ, "PATH": f"{root}:{os.environ['PATH']}", "TEST_ROOT": str(root)}
            installer = ["python3", str(root / "tools/install-sync-cron.py"), "--php", str(php)]
            preview = subprocess.run(installer, env=env, capture_output=True, text=True, check=True).stdout
            self.assertNotIn("sigra-sync:", entries.read_text())
            for _ in range(2):
                subprocess.run(installer + ["--install"], env=env, capture_output=True, check=True)
            installed = entries.read_text()
            self.assertEqual(installed.count("# sigra-sync:"), 1)
            self.assertIn("15 * * * * /usr/bin/true", installed)
            self.assertIn(preview.strip(), installed)
            # Run the exact command emitted for cron, with paths containing spaces and quotes.
            command = preview.split(" ", 5)[5]
            subprocess.run(["/bin/sh", "-c", command], cwd="/", env=env, check=True)
            logs = list((root / "writable/logs").glob("sync-*.log"))
            self.assertEqual(len(logs), 1)
            self.assertIn("without HTTP", logs[0].read_text())
            failed = subprocess.run(["/bin/sh", "-c", command], env={**env, "SYNC_EXIT": "7"})
            self.assertEqual(failed.returncode, 7)
            denied = subprocess.run(installer + ["--install"], env={**env, "CRON_FAIL": "1"}, capture_output=True)
            self.assertNotEqual(denied.returncode, 0)
            self.assertEqual(entries.read_text(), installed)

    def test_reject_cron_metacharacters(self):
        spec = importlib.util.spec_from_file_location("installer", ROOT / "tools/install-sync-cron.py")
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        for root in ("/srv/sigra%foo", "/srv/sigra\nfoo"):
            with self.assertRaises(ValueError):
                module.cron_line(Path(root), Path("/usr/bin/php"))


if __name__ == "__main__":
    unittest.main()
