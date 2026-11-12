<?php

use App\Libraries\CitizenReportService;
use App\Libraries\RedApiException;

$visitor = session()->get('citizen_reporter');
if (!is_string($visitor) || $visitor === '') {
    $visitor = bin2hex(random_bytes(32));
    session()->set('citizen_reporter', $visitor);
}
try {
    (new CitizenReportService())->submit($datos, $visitor, service('request')->getIPAddress());
} catch (RedApiException $error) {
    service('response')->setStatusCode($error->statusCode);
    if ($error->statusCode === 429) service('response')->setHeader('Retry-After', '60');
    $errores = [$error->getMessage()];
    return require __DIR__ . '/form.php';
} catch (Throwable $error) {
    log_message('error', 'Citizen report submission: {message}', ['message' => $error->getMessage()]);
    service('response')->setStatusCode(503);
    $errores = [lang('Sigra.reportUnavailable')];
    return require __DIR__ . '/form.php';
}
session()->setFlashdata('mensaje', lang('Sigra.reportReceived'));
return redirect()->to(site_url('sismos'))->withCookies();
