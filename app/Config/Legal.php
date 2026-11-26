<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class Legal extends BaseConfig
{
    public string $policyVersion = '2026-11-20.1';
    public string $responsible = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    public string $phone = '';

    public function __construct()
    {
        parent::__construct();
        foreach (['responsible', 'email', 'address', 'city', 'phone'] as $field) {
            $this->{$field} = trim((string) env('legal.' . $field, $this->{$field}));
        }
    }

    public function contactReady(): bool
    {
        return $this->responsible !== '' && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false
            && $this->address !== '' && $this->city !== '' && $this->phone !== '';
    }
}
