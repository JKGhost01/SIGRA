<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Quakes;
use Config\Sgc;
/** Legacy constructor; the injectable use case owns synchronization. */
class QuakeSyncService
{
    public function __construct(private readonly ?Quakes $quakes = null, private readonly ?Sgc $sgc = null) {}
    public function sync(): array { return \Config\Services::syncEvents(false, $this->quakes, $this->sgc)->handle(); }
}
