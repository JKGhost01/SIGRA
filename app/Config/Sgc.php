<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sgc extends BaseConfig
{
    public string $feedUrl = 'https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_2.json';
    public int $cacheSeconds = 5;
}
