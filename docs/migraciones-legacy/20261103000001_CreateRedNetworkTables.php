<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Red ciudadana de detección temprana de sismos.
 *
 * - red_devices: teléfonos que participan como sensores.
 * - red_detections: detecciones de onda P reportadas por los teléfonos.
 * - red_alerts: eventos fusionados por el servidor.
 * - red_alert_deliveries: alertas entregadas a cada teléfono.
 */
class CreateRedNetworkTables extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('red_devices')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'device_uuid' => ['type' => 'CHAR', 'constraint' => 64],
                'device_token_hash' => ['type' => 'CHAR', 'constraint' => 64],
                'platform' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'android'],
                'manufacturer' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'model' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
                'os_version' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'app_version' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'fcm_token' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'location_accuracy_m' => ['type' => 'FLOAT', 'null' => true],
                'location_updated_at' => ['type' => 'DATETIME', 'null' => true],
                'monitoring_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'notifications_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'alert_radius_km' => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 250],
                'min_magnitude' => ['type' => 'DECIMAL', 'constraint' => '3,1', 'default' => 3.0],
                'battery_level' => ['type' => 'TINYINT', 'constraint' => 3, 'null' => true],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
                'last_detection_at' => ['type' => 'DATETIME', 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('device_uuid', 'red_devices_uuid_unique');
            $this->forge->addUniqueKey('device_token_hash', 'red_devices_token_unique');
            $this->forge->addKey('last_seen_at', false, false, 'red_devices_seen_idx');
            $this->forge->createTable('red_devices');
        }

        if (! $this->db->tableExists('red_detections')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'device_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'device_uuid' => ['type' => 'CHAR', 'constraint' => 64],
                'detected_at_ms' => ['type' => 'BIGINT', 'constraint' => 20],
                'received_at_ms' => ['type' => 'BIGINT', 'constraint' => 20],
                'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'accuracy_m' => ['type' => 'FLOAT', 'null' => true],
                'sta_lta_ratio' => ['type' => 'FLOAT', 'null' => true],
                'peak_accel_mps2' => ['type' => 'FLOAT', 'null' => true],
                'dominant_freq_hz' => ['type' => 'FLOAT', 'null' => true],
                'duration_ms' => ['type' => 'INT', 'constraint' => 10, 'null' => true],
                'confidence' => ['type' => 'FLOAT', 'default' => 0.5],
                'cluster_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
                'raw_payload' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['device_id', 'detected_at_ms'], 'red_detections_device_time_unique');
            $this->forge->addKey('detected_at_ms', false, false, 'red_detections_time_idx');
            $this->forge->addKey('cluster_id', false, false, 'red_detections_cluster_idx');
            $this->forge->createTable('red_detections');
        }

        if (! $this->db->tableExists('red_alerts')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'alert_uuid' => ['type' => 'CHAR', 'constraint' => 36],
                'source' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'crowd'],
                'source_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
                'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'longitude' => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'depth_km' => ['type' => 'FLOAT', 'default' => 10.0],
                'magnitude_estimated' => ['type' => 'DECIMAL', 'constraint' => '3,1'],
                'confidence' => ['type' => 'FLOAT', 'default' => 0.5],
                'detection_count' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
                'device_count' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
                'radius_km' => ['type' => 'FLOAT', 'default' => 100.0],
                'origin_time_ms' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
                'expires_at' => ['type' => 'DATETIME'],
                'details' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('alert_uuid', 'red_alerts_uuid_unique');
            $this->forge->addUniqueKey(['source', 'source_id'], 'red_alerts_source_unique');
            $this->forge->addKey(['status', 'expires_at'], false, false, 'red_alerts_status_expires_idx');
            $this->forge->addKey(['latitude', 'longitude'], false, false, 'red_alerts_location_idx');
            $this->forge->createTable('red_alerts');
        }

        if (! $this->db->tableExists('red_alert_deliveries')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'alert_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'device_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
                'distance_km' => ['type' => 'FLOAT'],
                'seconds_to_s_wave' => ['type' => 'FLOAT', 'null' => true],
                'channel' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'poll'],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
                'sent_at' => ['type' => 'DATETIME', 'null' => true],
                'seen_at' => ['type' => 'DATETIME', 'null' => true],
                'error_message' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['alert_id', 'device_id'], 'red_deliveries_unique');
            $this->forge->addKey('device_id', false, false, 'red_deliveries_device_idx');
            $this->forge->createTable('red_alert_deliveries');
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('red_alert_deliveries', true);
        $this->forge->dropTable('red_alerts', true);
        $this->forge->dropTable('red_detections', true);
        $this->forge->dropTable('red_devices', true);
    }
}
