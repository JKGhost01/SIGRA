<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class CitizenReports extends BaseConfig
{
    public int $minParticipants = 3;
    public int $matchSeconds = 300;
    public float $matchKm = 150;
    public int $maxAgeHours = 24;
    public int $pendingHours = 48;
    public int $retentionDays = 30;
    public int $hourlyLimit = 3;
    public int $dailyLimit = 10;
    public int $minFormSeconds = 3;
    public string $hashKey = '';

    public function __construct()
    {
        parent::__construct();
        $this->minParticipants = max(3, (int) env('reports.minParticipants', $this->minParticipants));
        $this->matchSeconds = max(30, min(900, (int) env('reports.matchSeconds', $this->matchSeconds)));
        $this->matchKm = max(1, min(500, (float) env('reports.matchKm', $this->matchKm)));
        $this->hourlyLimit = max(1, (int) env('reports.hourlyLimit', $this->hourlyLimit));
        $this->dailyLimit = max(1, (int) env('reports.dailyLimit', $this->dailyLimit));
        $this->hashKey = (string) env('reports.hashKey', env('encryption.key', ''));
    }
}
