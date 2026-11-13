<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Http\Controllers;

use App\Controllers\BaseController;
use App\Libraries\ReportFormGuard;
use App\Modules\CitizenReports\Domain\ReportRejected;
use App\Modules\CitizenReports\Http\Requests\SubmitCitizenReportRequest;
use App\Modules\CitizenReports\Application\Commands\SubmitReport\SubmitReportCommand;
final class CitizenReportController extends BaseController
{
    public function index()
    {
        if (strtoupper($this->request->getMethod()) === 'POST') return $this->store();
        return $this->form([], session()->getFlashdata('error') ? [lang('Sigra.reportRejected')] : []);
    }
    public function store()
    {
        $datos = SubmitCitizenReportRequest::input($this->request);
        try {
            // Reject invalid input before constructing persistence adapters.
            service('reportValidator')->validate(SubmitCitizenReportRequest::canonical($datos), service('sigraClock')->now()->getTimestamp());
            $visitor = session()->get('citizen_reporter');
            if (!is_string($visitor) || $visitor === '') {
                $visitor = bin2hex(random_bytes(32));
                session()->set('citizen_reporter', $visitor);
            }
            service('submitCitizenReport')->handle(new SubmitReportCommand(SubmitCitizenReportRequest::canonical($datos), $visitor, $this->request->getIPAddress()));
        } catch (ReportRejected $error) {
            $this->response->setStatusCode($error->statusCode);
            if ($error->statusCode === 429) $this->response->setHeader('Retry-After', '60');
            return $this->form($datos, [lang($error->messageKey)]);
        } catch (\Throwable $error) {
            log_message('error', 'Citizen report submission: {message}', ['message' => $error->getMessage()]);
            $this->response->setStatusCode(503);
            return $this->form($datos, [lang('Sigra.reportUnavailable')]);
        }
        session()->setFlashdata('mensaje', lang('Sigra.reportReceived'));
        return redirect()->to(site_url('sismos'), 303)->withCookies();
    }
    private function form(array $datos, array $errores): string
    {
        return view('App\Modules\CitizenReports\Presentation\Views\reports\create\form', [
            'datos' => $datos, 'errores' => $errores, 'reportToken' => ReportFormGuard::issue(),
        ]);
    }
}
