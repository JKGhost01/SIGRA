-- Generated from migration 20261112000002_CreateArchitectureAudit on isolated MariaDB.
-- Import into the existing SIGRA database only if CLI migrations are unavailable.
-- Adds the two audit tables; no existing table is modified or emptied.

CREATE TABLE IF NOT EXISTS `sigra_ingestion_batches` (
  `batch_id` char(32) NOT NULL,
  `started_at_utc` datetime NOT NULL,
  `finished_at_utc` datetime NOT NULL,
  `duration_ms` int(10) unsigned NOT NULL,
  `saved_count` int(10) unsigned NOT NULL,
  `summary` text NOT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `started_at_utc` (`started_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sigra_alert_decisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `candidate_id` varchar(64) NOT NULL,
  `state` varchar(24) NOT NULL,
  `algorithm_version` varchar(32) NOT NULL,
  `reason` varchar(160) NOT NULL,
  `evidence` text NOT NULL,
  `recorded_at_utc` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `candidate_id_recorded_at_utc` (`candidate_id`,`recorded_at_utc`),
  KEY `recorded_at_utc` (`recorded_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

