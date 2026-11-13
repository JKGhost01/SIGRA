<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

final class CiTransactionManager implements \App\Shared\Application\TransactionManager
{
    public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
    public function run(callable $action): mixed
    {
        if (!$this->db->transBegin()) throw new \RuntimeException('Cannot start transaction.');
        try {
            $result = $action();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new \RuntimeException('Transaction failed.');
            return $result;
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
    }
}
