<?php

declare(strict_types=1);

namespace App\Libraries;

/** Compatibility error name; application rules depend on the shared error. */
class RedApiException extends \App\Shared\Application\OperationRejected {}
