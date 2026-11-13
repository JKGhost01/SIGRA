<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

/** A policy classification remains experimental and never certifies a scientific fact. */
final readonly class AlertCandidate
{
    public function __construct(public string $id, public CandidateState $state = CandidateState::Candidate) {}
    public function transition(CandidateState $next): self
    {
        $allowed = match ($this->state) {
            CandidateState::Experimental => [CandidateState::Candidate, CandidateState::Rejected],
            CandidateState::Candidate => [CandidateState::UnderReview, CandidateState::ConfirmedByPolicy, CandidateState::Rejected, CandidateState::Expired],
            CandidateState::UnderReview => [CandidateState::ConfirmedByPolicy, CandidateState::Rejected, CandidateState::Expired],
            CandidateState::ConfirmedByPolicy => [CandidateState::Cancelled, CandidateState::Expired],
            default => [],
        };
        if (!in_array($next, $allowed, true)) throw new \DomainException('Transición de candidato inválida.');
        return new self($this->id, $next);
    }
}
