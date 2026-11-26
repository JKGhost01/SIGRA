<?php

declare(strict_types=1);

define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
$_ENV['app.baseURL'] = $_SERVER['app.baseURL'] = 'http://localhost:8080/';
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-env';
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\System\Http\Controllers\PagesController;
use App\Modules\System\Presentation\ViewModels\LegalContent;
use App\Modules\CitizenReports\Http\Controllers\CitizenReportController;
use CodeIgniter\HTTP\{IncomingRequest, Response, URI, UserAgent};

$request = new IncomingRequest(config(Config\App::class), new CodeIgniter\HTTP\SiteURI(config(Config\App::class), '/'), '', new UserAgent());
$request->setLocale('es');
Config\Services::injectMock('request', $request);
$pages = new PagesController();
$pages->initController($request, new Response(config(Config\App::class)), service('logger'));
$legal = config(Config\Legal::class);
$legal->email = $legal->address = $legal->city = $legal->phone = '';
expectArchitecture(!$legal->contactReady(), 'Missing contact must not be treated as a completed policy');
foreach (['privacy', 'processing', 'cookies', 'terms'] as $method) {
    $html = $pages->{$method}();
    expectArchitecture(str_contains($html, '2026-11-20.1') && str_contains($html, 'tratamiento-datos'), 'Legal page lacks version or cross-links');
    expectArchitecture(str_contains($html, 'Falta configurar'), 'Incomplete operator details were concealed');
}
$legal->responsible = 'SIGRA test controller';
$legal->email = 'rights@example.test';
$legal->address = '<script>bad()</script>';
$legal->city = 'Bogotá, Colombia';
$legal->phone = '+57 000 0000000';
expectArchitecture($legal->contactReady(), 'Configured controller contact rejected');
$html = $pages->processing();
expectArchitecture(!str_contains($html, '<script>bad()') && str_contains($html, '&lt;script&gt;'), 'Operator configuration allowed HTML injection');
expectArchitecture(str_contains($html, 'rights@example.test'), 'Rights contact missing');
$documents = LegalContent::documents('en');
expectArchitecture(count($documents) === 4 && str_contains($documents['privacy']['title'], 'Privacy'), 'English legal documents missing');

// A valid report without current affirmative authorization must stop before persistence.
$request->setGlobal('post', ['latitud' => '4.6', 'longitud' => '-74.1', 'magnitud' => '4',
    'ubicacion' => 'Bogotá', 'fecha_hora' => date('Y-m-d\TH:i'), 'privacy_consent' => '0']);
$response = new Response(config(Config\App::class));
$reports = new CitizenReportController();
$reports->initController($request, $response, service('logger'));
$result = $reports->store();
expectArchitecture($response->getStatusCode() === 422 && str_contains($result, 'Debes autorizar'), 'Report without consent reached persistence');
$request->setGlobal('post', array_merge($request->getPost(), ['privacy_consent' => '1', 'privacy_version' => 'old']));
$reports->store();
expectArchitecture($response->getStatusCode() === 422, 'Stale privacy notice accepted');
$legal->email = '';
$request->setGlobal('post', array_merge($request->getPost(), ['privacy_version' => $legal->policyVersion]));
$reports->store();
expectArchitecture($response->getStatusCode() === 503, 'Reports collected before controller contact was configured');
$document = new DOMDocument();
@$document->loadHTML($result);
$xpath = new DOMXPath($document);
expectArchitecture($xpath->query('//form[@method="POST" and contains(@action,"sismos/create")]/fieldset//input[@name="privacy_consent"]')->length === 1,
    'Consent must be inside the report form and its fieldset');
echo "Legal: ES/EN policies, contact completeness, XSS, current consent and no collection with incomplete controller details passed.\n";
