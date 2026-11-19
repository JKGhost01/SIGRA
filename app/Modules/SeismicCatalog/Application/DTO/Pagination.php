<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\DTO;

final readonly class Pagination
{
    public int $page;
    public int $pages;
    public int $from;
    public int $to;

    public function __construct(public int $total, int $page = 1, public int $perPage = 20)
    {
        if ($total < 0 || !in_array($perPage, [20, 50, 100], true)) throw new \InvalidArgumentException('Paginación inválida.');
        $this->pages = max(1, (int) ceil($total / $perPage));
        $this->page = max(1, min($page, $this->pages));
        $this->from = $total === 0 ? 0 : ($this->page - 1) * $perPage + 1;
        $this->to = min($this->page * $perPage, $total);
    }

    public function offset(): int { return ($this->page - 1) * $this->perPage; }
}
