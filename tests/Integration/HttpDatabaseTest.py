"""Real CGI + disposable MySQL success paths, with notifications disabled."""
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import time
from datetime import datetime, timedelta, timezone
from urllib.parse import urlencode, urlsplit

root = Path(__file__).resolve().parents[2]
assert re.fullmatch(r'sigra_[a-z0-9_]+_test', os.environ.get('SIGRA_TEST_DATABASE', ''))
php = os.environ.get('PHP_CGI') or shutil.which('php-cgi') or '/opt/lampp/bin/php-cgi'
cookies = {}
checks = 0


def request(route, expected=200, *, payload=None, form=None, device=''):
    global checks
    url = urlsplit(route)
    body = urlencode(form).encode() if form is not None else json.dumps(payload).encode() if payload is not None else b''
    env = dict(os.environ)
    env.update({'CI_ENVIRONMENT': 'development', 'app.baseURL': 'http://localhost:8080/',
                'APP_API_TOKEN': 'isolated-http-test-key', 'red.appKey': 'isolated-http-test-key',
                'quakes.sgcEnabled': 'false', 'quakes.globalEnabled': 'false',
                'quakes.emscEnabled': 'false', 'quakes.geonetEnabled': 'false',
                'quakes.geofonEnabled': 'false', 'quakes.ingvEnabled': 'false',
                'red.fcmEnabled': 'false', 'red.alertingEnabled': 'false',
                'cache.prefix': 'sigra_architecture_test_', 'red.statsCacheSeconds': '0',
                'reports.hashKey': 'isolated-test-key-which-is-at-least-32-bytes',
                'SCRIPT_FILENAME': str(root / 'tests/Support/cgi.php'), 'SCRIPT_NAME': '/index.php',
                'REQUEST_URI': route, 'QUERY_STRING': url.query,
                'REQUEST_METHOD': 'POST' if payload is not None or form is not None else 'GET',
                'SERVER_NAME': 'localhost', 'SERVER_PORT': '8080', 'HTTP_HOST': 'localhost:8080',
                'REDIRECT_STATUS': '200', 'CONTENT_LENGTH': str(len(body)),
                'CONTENT_TYPE': 'application/x-www-form-urlencoded' if form is not None else 'application/json',
                'HTTP_AUTHORIZATION': 'Bearer isolated-http-test-key',
                'HTTP_X_SIGRA_KEY': 'isolated-http-test-key', 'HTTP_X_DEVICE_TOKEN': device,
                'REMOTE_ADDR': '192.0.2.1', 'HTTP_COOKIE': '; '.join(f'{k}={v}' for k, v in cookies.items())})
    result = subprocess.run([php], input=body, env=env, cwd=root, capture_output=True, timeout=20)
    headers, sep, data = result.stdout.decode().partition('\r\n\r\n')
    assert sep, f'No CGI response for {route}'
    status = re.search(r'^Status: (\d+)', headers, re.M)
    actual = int(status[1]) if status else 200
    assert actual == expected, f'{route}: expected {expected}, got {actual}'
    for name, value in re.findall(r'^Set-Cookie: ([^=]+)=([^;\r\n]*)', headers, re.M | re.I):
        cookies[name] = value
    checks += 1
    return json.loads(data) if 'application/json' in headers else data


for route in ['/sismos', '/sismos/historico', '/sismos/detalle/QA-http-modular']:
    html = request(route)
    assert 'QA-http-modular' in html, f'Missing fixture on {route}'
    asset_prefix = '' if os.environ.get('SIGRA_TEST_PUBLIC_ROOT') == '1' else 'public/'
    assert f'http://localhost:8080/{asset_prefix}assets/' in html, f'Incorrect asset root on {route}'
refresh = request('/sismos/sync')
assert refresh['synced'] and refresh['eventos'][0]['id'] == 'QA-http-modular'
assert refresh['last_success_at'] > 0
assert not request('/sismos/sync')['synced']
for route in ['/api/sismos/recent', '/api/v1/sismos/recent']:
    result = request(route + '?lat=4.6&lng=-74.1&radius_km=500&order=distancia')
    assert result['events'][0]['id'] == 'QA-http-modular' and result['events'][0]['distancia_km'] == 0
assert request('/api/sismos/ultimo')['event']['id'] == 'QA-http-modular'
assert request('/api/sismos/alertas')['alerta'] == 'amarilla'
assert request('/api/sismos/detalle/QA-http-modular')['event']['id'] == 'QA-http-modular'
assert request('/api/v1/sismos/detalle/missing', 404)['error']['code'] == 'RESOURCE_NOT_FOUND'
assert request('/api/health')['database'] == 'ok'
assert request('/api/red/red')['stats']['devices_total'] == 0
registered = request('/api/red/registro', payload={'device_uuid': 'QA-http-modular', 'notifications_enabled': False})
token = registered['device_token']
request('/api/red/registro', 401, payload={'device_uuid': 'QA-http-modular'})
request('/api/red/registro', 401, payload={'device_uuid': 'QA-http-modular'}, device='another-device-token')
rotated = request('/api/red/registro', payload={'device_uuid': 'QA-http-modular', 'notifications_enabled': False}, device=token)
request('/api/red/estado', 401, payload={}, device=token)
assert rotated['device_token'] != token
token = rotated['device_token']
assert request('/api/red/estado', payload={'monitoring_enabled': True}, device=token)['device']['monitoring_enabled']
signal = {'latitude': 4.6, 'longitude': -74.1, 'confidence': 0.9, 'detected_at_ms': int(time.time() * 1000)}
assert request('/api/red/deteccion', payload=signal | {'simulated': True}, device=token)['simulated']
assert request('/api/red/deteccion', payload=signal, device=token)['accepted']
assert request('/api/red/deteccion', payload=signal, device=token)['duplicate']
assert request('/api/red/alertas?lat=4.6&lng=-74.1', device=token)['alerts'] == []
request('/api/red/desactivar', payload={}, device=token)
request('/api/red/estado', 401, payload={}, device=token)
# Valid public report exercises the actual request, handler, MySQL transaction and PRG redirect.
html = request('/sismos/create')
fields = dict(re.findall(r'<input[^>]+name="([^"]+)"[^>]+value="([^"]*)"', html))
time.sleep(3.1)
request('/sismos/create', 303, form={k: fields[k] for k in ['csrf_test_name', 'report_token']} |
        {'latitud': '4.6', 'longitud': '-74.1', 'magnitud': '5', 'ubicacion': 'QA-http-modular',
         'fecha_hora': datetime.now(timezone(timedelta(hours=-5))).strftime('%Y-%m-%dT%H:%M')})
print(f'{checks} CGI/MySQL success-path checks passed; public_root={os.environ.get("SIGRA_TEST_PUBLIC_ROOT")}; notifications disabled.')
