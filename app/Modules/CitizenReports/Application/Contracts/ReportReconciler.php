<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Application\Contracts;

interface ReportReconciler
{
    public function reconcile(): void;
}
