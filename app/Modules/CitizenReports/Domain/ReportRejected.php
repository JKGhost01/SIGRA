<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Domain;

final class ReportRejected extends \RuntimeException
{
    public function __construct(public readonly string $messageKey, public readonly int $statusCode = 422)
    { parent::__construct($messageKey); }
}
