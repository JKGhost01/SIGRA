<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Infrastructure\Persistence;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Persistence for reports, rate budgets and serialized reconciliation using MySQL advisory locks. */
class CiCitizenReportRepository implements \App\Modules\CitizenReports\Application\Contracts\CitizenReportRepository
{
    public function __construct(public readonly BaseConnection $db) {}

    public function transaction(callable $action): mixed
    {
        $lock = 'sigra-reports:' . substr(hash('sha256', $this->db->database), 0, 40);
        $row = $this->db->query('SELECT GET_LOCK(?, 3) AS acquired', [$lock])->getRowArray();
        if ((int) ($row['acquired'] ?? 0) !== 1) throw new RuntimeException('Report processing is busy.');
        $this->db->transBegin();
        try {
            $result = $action();
            if (!$this->db->transStatus()) throw new RuntimeException('Citizen report transaction failed.');
            $this->db->transCommit();
            return $result;
        } catch (Throwable $error) {
            $this->db->transRollback();
            throw $error;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    public function canSubmit(string $reporter, string $network, int $now, int $hourly, int $daily): bool
    {
        foreach ([60 => 1, 3600 => $hourly, 86400 => $daily] as $seconds => $limit) {
            $count = $this->db->table('citizen_reports')->groupStart()
                ->where('reporter_key', $reporter)->orWhere('network_key', $network)->groupEnd()
                ->where('created_at >=', date('Y-m-d H:i:s', $now - $seconds))->countAllResults();
            if ($count >= $limit) return false;
        }
        return true;
    }

    public function insert(array $report): int
    {
        if (!$this->db->table('citizen_reports')->insert($report)) throw new RuntimeException('Report insert failed.');
        return (int) $this->db->insertID();
    }

    public function recentReports(string $since): array
    {
        return $this->db->table('citizen_reports')->where('reported_at >=', $since)->where('status !=', 'expired')
            ->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function updateReports(array $rows): void
    {
        if ($rows !== []) $this->db->table('citizen_reports')->updateBatch($rows, 'id');
    }

    public function expireAndPurge(string $cutoff, string $purge): void
    {
        $this->db->table('citizen_reports')->where('status', 'pending')->where('reported_at <', $cutoff)
            ->update(['status' => 'expired', 'event_id' => null]);
        $this->db->table('citizen_reports')->where('created_at <', $purge)->delete();
    }
}
