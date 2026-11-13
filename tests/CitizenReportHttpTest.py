"""Real CGI form/security checks; no database queries or business writes."""
import os
from pathlib import Path
import re
import shutil
import subprocess
import time
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_CGI") or shutil.which("php-cgi") or "/opt/lampp/bin/php-cgi"
cookies = {}
checks = 0


def request(route="/sismos/create", data=None):
    global checks
    body = urlencode(data or {}).encode()
    env = dict(os.environ, REQUEST_METHOD="POST" if data is not None else "GET",
               SCRIPT_FILENAME=str(ROOT / "tests/Support/cgi.php"), SCRIPT_NAME="/index.php", REQUEST_URI=route,
               QUERY_STRING="", SERVER_NAME="localhost", SERVER_PORT="8080", HTTP_HOST="localhost:8080",
               REDIRECT_STATUS="200", CONTENT_TYPE="application/x-www-form-urlencoded",
               CONTENT_LENGTH=str(len(body)), REMOTE_ADDR="192.0.2.1",
               HTTP_COOKIE="; ".join(f"{key}={value}" for key, value in cookies.items()))
    env.update({"CI_ENVIRONMENT": "development", "app.baseURL": "http://localhost:8080/"})
    response = subprocess.run([PHP], input=body, env=env, cwd=ROOT, capture_output=True, timeout=20)
    headers, _, html = response.stdout.decode().partition("\r\n\r\n")
    for key, value in re.findall(r"^Set-Cookie: ([^=]+)=([^;\r\n]*)", headers, re.M | re.I):
        cookies[key] = value
    status = re.search(r"^Status: (\d+)", headers, re.M)
    return int(status[1]) if status else 200, html


def form():
    status, html = request()
    assert status == 200
    assert 'name="report_token"' in html and 'name="website"' in html
    assert 'name="fuente"' not in html and 'name="estado"' not in html
    fields = dict(re.findall(r'<input[^>]+name="([^"]+)"[^>]+value="([^"]*)"', html))
    return {key: fields[key] for key in ["csrf_test_name", "report_token"]}


def expect(code, data, route="/sismos/create"):
    global checks
    status, html = request(route, data)
    assert status == code, f"Expected {code}, got {status}: {route}"
    checks += 1
    print(f"OK {code} {route}", flush=True)
    return html


expect(403, {"latitud": "4.6"})  # Missing CSRF.
fields = form()
expect(403, {"csrf_test_name": fields["csrf_test_name"]})  # Missing session challenge.
fields = form()
expect(403, fields)  # Too fast; challenge is consumed.
fields = form()
expect(403, fields | {"website": "spam"})
fields = form()
expect(413, fields | {"ubicacion": "x" * 9000})
fields = form()
time.sleep(3.1)
html = expect(422, fields | {"latitud[]": "4.6", "longitud": "-74.1", "magnitud": "3",
                           "ubicacion": "QA", "fecha_hora": "2026-11-12T10:10"})
assert 'alert alert-danger' in html and 'trim(): Argument' not in html
# Reusing a consumed challenge must fail, even with the current CSRF cookie.
fields["csrf_test_name"] = cookies.get("csrf_cookie_name", fields["csrf_test_name"])
expect(403, fields)
for route in ["/sismos/edit/SGC-test", "/sismos/delete/SGC-test"]:
    status, _ = request(route)
    assert status == 403
    checks += 1
    expect(403, {}, route)
print(f"{checks} CGI report security checks passed; no database writes.", flush=True)
