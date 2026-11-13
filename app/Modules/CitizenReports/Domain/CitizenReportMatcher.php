<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Domain;

use App\Shared\Domain\GeoDistance;


/** A report count can only annotate an existing, unambiguous SGC event. */
class CitizenReportMatcher
{
    public function __construct(private readonly ReportPolicy $config) {}

    public function classify(array $reports, array $events): array
    {
        $events = array_values(array_filter($events, static fn ($event) =>
            ($event['fuente'] ?? '') === 'SGC' && !str_starts_with((string) $event['id'], 'SGI')));
        $reports = array_values(array_filter($reports, static fn ($report) => ($report['status'] ?? '') !== 'expired'));
        $groups = [];
        $updates = [];
        foreach ($reports as $report) {
            $candidates = [];
            foreach ($events as $event) {
                if (abs(strtotime($report['reported_at']) - strtotime($event['fecha_hora'])) <= $this->config->matchSeconds
                    && GeoDistance::km((float) $report['latitude'], (float) $report['longitude'],
                        (float) $event['latitud'], (float) $event['longitud']) <= $this->config->matchKm) {
                    $candidates[] = $event['id'];
                }
            }
            // Multiple possible SGC events are ambiguous: leave the report pending.
            if (count($candidates) === 1) $groups[$candidates[0]][] = $report;
            else $updates[] = ['id' => $report['id'], 'status' => 'pending', 'event_id' => null];
        }
        $counts = [];
        foreach ($groups as $eventId => $group) {
            $visitors = $networks = $selected = [];
            foreach ($group as $report) {
                if (isset($visitors[$report['reporter_key']]) || isset($networks[$report['network_key']])) {
                    $updates[] = ['id' => $report['id'], 'status' => 'duplicate', 'event_id' => $eventId];
                    continue;
                }
                $visitors[$report['reporter_key']] = $networks[$report['network_key']] = true;
                $selected[] = $report;
            }
            $count = count($selected);
            $confirmed = $count >= $this->config->minParticipants;
            $counts[$eventId] = $confirmed ? $count : 0;
            foreach ($selected as $report) $updates[] = ['id' => $report['id'],
                'status' => $confirmed ? 'confirmed' : 'pending', 'event_id' => $eventId];
        }
        $changes = [];
        foreach ($events as $event) {
            $count = $counts[$event['id']] ?? 0;
            if ((int) $event['citizen_reports_count'] !== $count) {
                $changes[] = ['id' => $event['id'], 'citizen_reports_count' => $count];
            }
        }
        // Only write changed classifications, keeping polling idempotent.
        $previous = array_column($reports, null, 'id');
        $updates = array_values(array_filter($updates, static fn ($row) =>
            $row['status'] !== $previous[$row['id']]['status'] || $row['event_id'] !== $previous[$row['id']]['event_id']));
        return ['reports' => $updates, 'events' => $changes];
    }
}
