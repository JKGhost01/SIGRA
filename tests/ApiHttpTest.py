"""Exercise real routes/filters/controllers through PHP-CGI without business writes.

Usage: python3 tests/ApiHttpTest.py
Set PHP_CGI if php-cgi is not on PATH. Database failure cases deliberately use
an unreachable test connection. No registration or valid detection is submitted.
"""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get("PHP_CGI") or shutil.which("php-cgi")
if not PHP and Path("/opt/lampp/bin/php-cgi").exists():
    PHP = "/opt/lampp/bin/php-cgi"
if not PHP:
    raise SystemExit("Define PHP_CGI con la ruta a php-cgi.")
VERSION = re.search(r"public string \$version = '([^']+)'", (ROOT / "app/Config/Sigra.php").read_text())[1]
checks = 0


def check(url, status, *, method="GET", body="", key=True, content_type="application/json", extra=None):
    global checks
    uri = urlsplit(url)
    env = dict(os.environ)
    env.update({
        "CI_ENVIRONMENT": "development", "app.baseURL": "http://localhost:8080/",
        "APP_API_TOKEN": "sigra-test-process-only", "red.appKey": "sigra-test-process-only",
        "sigra.version": VERSION,
        "database.default.hostname": "127.0.0.1", "database.default.port": "1",
        "database.default.username": "sigra_test", "database.default.password": "",
        "database.default.database": "sigra_test_unreachable",
        "SCRIPT_FILENAME": str(ROOT / "tests/Support/cgi.php"), "SCRIPT_NAME": "/index.php",
        "REQUEST_URI": url, "QUERY_STRING": uri.query, "REQUEST_METHOD": method,
        "SERVER_NAME": "localhost", "SERVER_PORT": "8080", "HTTP_HOST": "localhost:8080",
        "REDIRECT_STATUS": "200", "CONTENT_TYPE": content_type,
        "CONTENT_LENGTH": str(len(body.encode())), "HTTP_ACCEPT": "application/json",
        "HTTP_AUTHORIZATION": "Bearer sigra-test-process-only" if key else "",
        "HTTP_X_SIGRA_KEY": "sigra-test-process-only" if key else "",
        "HTTP_X_DEVICE_TOKEN": "",
    })
    env.update(extra or {})
    response = subprocess.run([PHP], input=body.encode(), env=env, cwd=ROOT,
                              capture_output=True, timeout=20)
    raw = response.stdout.decode()
    headers, separator, data = raw.partition("\r\n\r\n")
    assert separator, f"Missing CGI headers: {url}"
    actual = re.search(r"^Status: (\d+)", headers, re.M)
    actual = int(actual[1]) if actual else 200
    assert actual == status, f"{url}: expected {status}, got {actual}"
    payload = json.loads(data)
    headers = {k.lower(): v.strip() for k, v in (line.split(":", 1)
               for line in headers.splitlines() if ":" in line)}
    assert "application/json" in headers.get("content-type", ""), url
    assert "no-store" in headers.get("cache-control", ""), url
    assert headers.get("x-sigra-version") == VERSION, url
    assert headers.get("x-api-version") == "v1", url
    if "request_id" in payload:
        assert payload["request_id"] == headers.get("x-request-id"), url
    if status >= 400:
        assert payload["status"] in ("error", "degraded"), url
        assert "<html" not in data.lower() and "Backtrace" not in data, url
    checks += 1
    print(f"OK {status} {method} {url}")
    return payload


for route in ["recent", "ultimo", "alertas", "detalle/missing"]:
    check("/api/sismos/" + route, 401, key=False)
for route in ["registro", "estado", "deteccion", "desactivar"]:
    check("/api/red/" + route, 401, method="POST", key=False)
check("/api/red/alertas?lat=4&lng=-74", 401, key=False)

for query in ["hours[]=2", "hours=1.5", "hours=0", "limit=1001", "min_magnitude=abc",
              "min_magnitude=1e309", "lat=91&lng=0", "lat=0", "lng=0", "fuente[]=SGC",
              "order[]=fecha", "order=unknown", "order=distancia", "radius_km=500",
              "lat=0&lng=0&radius_km=-1", "lat=0&lng=0&radius_km=20001"]:
    check("/api/sismos/recent?" + query, 422)
for route in ["ultimo", "alertas", "detalle/missing"]:
    check(f"/api/sismos/{route}?lat=91&lng=0", 422)
for route in ["red", "alertas"]:
    for query in ["lat=0", "lat=91&lng=0", "lat[]=0&lng=0", "lat=1e309&lng=0"]:
        check(f"/api/red/{route}?{query}", 422)
check("/api/red/alertas", 422)
for body, status in [("{", 400), ("[]", 422), ("null", 422), ('"text"', 422),
                     ('{"device_uuid": []}', 422), ('{"latitude": 91, "longitude": 0}', 422),
                     ('{"monitoring_enabled": "invalid"}', 422),
                     ('{"confidence": 1e309}', 422)]:
    check("/api/red/registro", status, method="POST", body=body)
check("/api/red/registro", 415, method="POST", content_type="text/plain", body="hi")
check("/api/red/estado", 401, method="POST", body="{}")
check("/api/red/desactivar", 401, method="POST")

assert check("/api/red/config", 200, key=False)["version"] == VERSION
assert check("/api/red/health", 200, key=False)["version"] == VERSION
assert check("/api/health", 503, key=False)["database"] == "error"
failure = check("/api/sismos/recent", 500)
assert failure["message"] == "Error interno de la API SIGRA."
check("/api/red/estado", 500, method="POST", body="{}", extra={"HTTP_X_DEVICE_TOKEN": "unreachable-test-token"})
for route in ["/api/sismos/recent", "/api/red/registro"]:
    check(route, 401, method="POST" if "registro" in route else "GET", key=False,
          extra={"CI_ENVIRONMENT": "production", "APP_API_TOKEN": "", "red.appKey": "",
                 "app.apiToken": "", "app_apiToken": ""})
v1 = check("/api/v1/sismos/recent", 401, key=False)
assert v1["error"]["code"] == "AUTHENTICATION_REQUIRED" and len(v1["request_id"]) == 32
v1 = check("/api/v1/sismos/recent?hours=0", 422)
assert v1["error"]["code"] == "VALIDATION_FAILED"
v1 = check("/api/v1/red/registro", 401, method="POST", key=False)
assert v1["error"]["code"] == "AUTHENTICATION_REQUIRED"
assert "environment" not in check("/api/red/health", 200, key=False)
assert "environment" not in check("/api/v1/health", 503, key=False)
print(f"{checks} API HTTP checks passed (database success paths are not covered).")
