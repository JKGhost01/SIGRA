<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$record = db_connect()->table('citizen_reports')->where('place', 'QA-http-modular')->orderBy('id', 'DESC')->get()->getRowArray();
expectArchitecture(is_array($record) && $record['consent_version'] === '2026-11-20.1', 'Submitted report lacks the accepted policy version');
expectArchitecture(is_string($record['consent_at']) && strtotime($record['consent_at']) !== false, 'Submitted report lacks the server acceptance timestamp');
echo "Report consent evidence: current version and server timestamp persisted in the isolated database.\n";
