<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Application\Commands\SubmitReport;

use App\Modules\CitizenReports\Application\Contracts\CitizenReportRepository;
use App\Modules\CitizenReports\Application\Commands\ReconcileReports\ReconcileReportsHandler;
use App\Modules\CitizenReports\Domain\{ReportValidator, ReportPolicy, ReportRejected};
use App\Shared\Domain\Clock;
use Psr\Log\LoggerInterface;
use Throwable;
final class SubmitCitizenReportHandler
{
    public function __construct(private readonly CitizenReportRepository $reports, private readonly ReportValidator $validator,
        private readonly ReportPolicy $policy, private readonly Clock $clock,
        private readonly ReconcileReportsHandler $reconcile, private readonly LoggerInterface $logger,
        private readonly \DateTimeZone $storageTimezone) {}
    public function handle(SubmitReportCommand $command, ?int $now = null): int
    {
        $input = $command->input; $visitor = $command->visitor; $ip = $command->ip;
        $now ??= $this->clock->now()->getTimestamp();
        $report = $this->validator->validate($input, $now);
        if (strlen($this->policy->hashKey) < 32 || $visitor === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new \RuntimeException('Citizen reports require a stable secret and client identity.');
        }
        // Raw IP addresses and session identifiers are never stored in these tables.
        $reporter = hash_hmac('sha256', 'visitor:' . $visitor, $this->policy->hashKey);
        $network = hash_hmac('sha256', 'network:' . $this->networkAddress($ip), $this->policy->hashKey);
        $id = $this->reports->transaction(function () use ($report, $reporter, $network, $now): int {
            if (!$this->reports->canSubmit($reporter, $network, $now, $this->policy->hourlyLimit, $this->policy->dailyLimit)) {
                throw new ReportRejected('Sigra.reportRateLimited', 429);
            }
            return $this->reports->insert($report + ['reporter_key' => $reporter, 'network_key' => $network,
                'status' => 'pending', 'created_at' => (new \DateTimeImmutable('@' . $now))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s')]);
        });
        // A saved report remains pending if reconciliation temporarily fails.
        try { $this->reconcile->handle($now); } catch (Throwable $error) {
            $this->logger->error('Citizen report matching: {message}', ['message' => $error->getMessage()]);
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

}
