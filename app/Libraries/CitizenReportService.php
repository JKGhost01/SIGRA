<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\CitizenReportStore;
use Config\CitizenReports;
use DateTimeImmutable;
use Throwable;

class CitizenReportService
{
    private CitizenReports $config;
    private CitizenReportStore $store;

    public function __construct(?CitizenReportStore $store = null, ?CitizenReports $config = null)
    {
        $this->config = $config ?? config(CitizenReports::class);
        $this->store = $store ?? new CitizenReportStore(db_connect());
    }

    public function validate(array $input, ?int $now = null): array
    {
        $now ??= time();
        foreach (['latitud', 'longitud', 'magnitud', 'ubicacion', 'fecha_hora'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || str_contains($input[$field], "\0") || trim($input[$field]) === '') {
                throw new RedApiException(lang('Sigra.reportInvalid'));
            }
        }
        try {
            $latitude = ApiInput::number($input['latitud'], 'latitud', -90, 90);
            $longitude = ApiInput::number($input['longitud'], 'longitud', -180, 180);
            $intensity = ApiInput::number($input['magnitud'], 'intensidad', 0, 10);
        } catch (RedApiException) {
            throw new RedApiException(lang('Sigra.reportInvalid'));
        }
        $place = trim($input['ubicacion']);
        if (!mb_check_encoding($place, 'UTF-8') || mb_strlen($place) > 150 || preg_match('/[\x00-\x1F\x7F<>]/u', $place)) {
            throw new RedApiException(lang('Sigra.reportInvalid'));
        }
        $value = trim($input['fecha_hora']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value)) {
            throw new RedApiException(lang('Sigra.reportTimeInvalid'));
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
        if (!$date || $date->format('Y-m-d\TH:i') !== $value
            || $date->getTimestamp() > $now + 120
            || $date->getTimestamp() < $now - $this->config->maxAgeHours * 3600) {
            throw new RedApiException(lang('Sigra.reportTimeInvalid'));
        }
        return ['reported_at' => $date->format('Y-m-d H:i:s'), 'latitude' => $latitude,
            'longitude' => $longitude, 'felt_intensity' => $intensity, 'place' => $place];
    }

    public function submit(array $input, string $visitor, string $ip, ?int $now = null): int
    {
        $now ??= time();
        $report = $this->validate($input, $now);
        if (strlen($this->config->hashKey) < 32 || $visitor === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('Citizen reports require a stable secret and client identity.');
        }
        // Raw IP addresses and session identifiers are never stored in these tables.
        $reporter = hash_hmac('sha256', 'visitor:' . $visitor, $this->config->hashKey);
        $network = hash_hmac('sha256', 'network:' . $this->networkAddress($ip), $this->config->hashKey);
        $id = $this->store->transaction(function () use ($report, $reporter, $network, $now): int {
            if (!$this->store->canSubmit($reporter, $network, $now, $this->config->hourlyLimit, $this->config->dailyLimit)) {
                throw new RedApiException(lang('Sigra.reportRateLimited'), 429);
            }
            return $this->store->insert($report + ['reporter_key' => $reporter, 'network_key' => $network,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s', $now)]);
        });
        // A saved report remains pending if reconciliation temporarily fails.
        try { $this->reconcile($now); } catch (Throwable $error) {
            log_message('error', 'Citizen report matching: {message}', ['message' => $error->getMessage()]);
        }
        return $id;
    }

    private function networkAddress(string $ip): string
    {
        $packed = inet_pton($ip);
        // Treat an IPv6 /64 as one network, preventing address rotation from adding votes.
        if (strlen($packed) === 16) {
            if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") return inet_ntop(substr($packed, 12));
            return bin2hex(substr($packed, 0, 8));
        }
        return inet_ntop($packed);
    }

    public function reconcile(?int $now = null): void
    {
        $now ??= time();
        $this->store->transaction(function () use ($now): void {
            $since = date('Y-m-d H:i:s', $now - $this->config->pendingHours * 3600);
            $this->store->expireAndPurge($since, date('Y-m-d H:i:s', $now - $this->config->retentionDays * 86400));
            $events = $this->store->sgcEvents(date('Y-m-d H:i:s', strtotime($since) - $this->config->matchSeconds));
            $reports = $this->store->recentReports(date('Y-m-d H:i:s', strtotime($since) - 2 * $this->config->matchSeconds));
            $result = (new CitizenReportMatcher($this->config))->classify($reports, $events);
            $updates = $result['reports'];
            $changes = $result['events'];
            $this->store->updateReports($updates);
            $this->store->updateCounts($changes);
        });
    }
}
