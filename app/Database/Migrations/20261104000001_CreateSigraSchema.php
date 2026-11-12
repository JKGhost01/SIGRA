<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Esquema consolidado de SIGRA (v2).
 *
 * Sustituye a las migraciones incrementales 2026-09-21 / 2026-11-03.
 * Está pensado para una instalación desde cero en un hosting con
 * recursos limitados:
 *
 *  - InnoDB con utf8mb4 y filas dinámicas.
 *  - Sin columnas redundantes (source_id duplicaba id, raw_payload
 *    duplicaba columnas ya normalizadas).
 *  - Índices compuestos alineados con las consultas reales.
 *  - Sin claves foráneas: la red escribe mucho y en hosting compartido
 *    cada comprobación extra se nota; la integridad se valida en la capa
 *    de servicio.
 *
 * Para aplicar este esquema sobre una base existente usa:
 *   php spark sigra:db-reset --force
 */
class CreateSigraSchema extends Migration
{
    public function up(): void
    {
        $this->guardarSiHayEsquemaViejo();

        $this->crearSismos();
        $this->crearRedDevices();
        $this->crearRedDetections();
        $this->crearRedAlerts();
        $this->crearRedAlertDeliveries();
    }

    public function down(): void
    {
        $this->forge->dropTable('red_alert_deliveries', true);
        $this->forge->dropTable('red_alerts', true);
        $this->forge->dropTable('red_detections', true);
        $this->forge->dropTable('red_devices', true);
        $this->forge->dropTable('sismos', true);
    }

    /**
     * Evita que un `migrate` accidental deje el esquema viejo a medias.
     */
    private function guardarSiHayEsquemaViejo(): void
    {
        if (! $this->db->tableExists('sismos')) {
            return;
        }

        if ($this->db->fieldExists('source_id', 'sismos')) {
            throw new RuntimeException(
                'La base de datos tiene el esquema antiguo. '
                . 'Ejecuta `php spark sigra:db-reset --force` para recrearla con el esquema optimizado.',
            );
        }
    }

    private function opcionesTabla(): array
    {
        return [
            'ENGINE'     => 'InnoDB',
            'ROW_FORMAT' => 'DYNAMIC',
        ];
    }

    private function crearSismos(): void
    {
        if ($this->db->tableExists('sismos')) {
            return;
        }

        $this->forge->addField([
            'id'            => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => false],
            'fecha_hora'    => ['type' => 'DATETIME', 'null' => false],
            'latitud'       => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'longitud'      => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'magnitud'      => ['type' => 'DECIMAL', 'constraint' => '3,1', 'null' => false],
            'profundidad'   => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => false, 'default' => 0],
            'ubicacion'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'fuente'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'SGC'],
            'estado'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'automatico'],
            'sgc_metadata'  => ['type' => 'TEXT', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        // Ventana activa e histórico por fecha.
        $this->forge->addKey('fecha_hora', false, false, 'sismos_fecha_idx');
        // Filtro por fuente + orden cronológico (sincronización y API).
        $this->forge->addKey(['fuente', 'fecha_hora'], false, false, 'sismos_fuente_fecha_idx');
        // Ventana activa con magnitud mínima (mapa y app).
        $this->forge->addKey(['fecha_hora', 'magnitud'], false, false, 'sismos_fecha_mag_idx');
        // Histórico ordenado solo por magnitud.
        $this->forge->addKey('magnitud', false, false, 'sismos_mag_idx');
        $this->forge->createTable('sismos', true, $this->opcionesTabla());
    }

    private function crearRedDevices(): void
    {
        if ($this->db->tableExists('red_devices')) {
            return;
        }

        $this->forge->addField([
            'id'                     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'device_uuid'            => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'device_token_hash'      => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'platform'               => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'android'],
            'manufacturer'           => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'model'                  => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'os_version'             => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'app_version'            => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'fcm_token'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'latitude'               => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'longitude'              => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => true],
            'location_accuracy_m'    => ['type' => 'FLOAT', 'null' => true],
            'location_updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'monitoring_enabled'     => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'notifications_enabled'  => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'alert_radius_km'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => false, 'default' => 250],
            'min_magnitude'          => ['type' => 'DECIMAL', 'constraint' => '3,1', 'null' => false, 'default' => 3.0],
            'battery_level'          => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
            'last_seen_at'           => ['type' => 'DATETIME', 'null' => true],
            'last_detection_at'      => ['type' => 'DATETIME', 'null' => true],
            'status'                 => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false, 'default' => 'active'],
            'created_at'             => ['type' => 'DATETIME', 'null' => true],
            'updated_at'             => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('device_uuid', 'red_devices_uuid_unique');
        $this->forge->addUniqueKey('device_token_hash', 'red_devices_token_unique');
        // Teléfonos en línea y candidatos a alerta.
        $this->forge->addKey(['status', 'last_seen_at'], false, false, 'red_devices_online_idx');
        $this->forge->addKey(['status', 'notifications_enabled', 'last_seen_at'], false, false, 'red_devices_candidates_idx');
        // Limpieza de inactivos.
        $this->forge->addKey('updated_at', false, false, 'red_devices_updated_idx');
        $this->forge->createTable('red_devices', true, $this->opcionesTabla());
    }

    private function crearRedDetections(): void
    {
        if ($this->db->tableExists('red_detections')) {
            return;
        }

        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'device_id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'device_uuid'       => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'detected_at_ms'    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'received_at_ms'    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'latitude'          => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'longitude'         => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'accuracy_m'        => ['type' => 'FLOAT', 'null' => true],
            'sta_lta_ratio'     => ['type' => 'FLOAT', 'null' => true],
            'peak_accel_mps2'   => ['type' => 'FLOAT', 'null' => true],
            'dominant_freq_hz'  => ['type' => 'FLOAT', 'null' => true],
            'duration_ms'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'confidence'        => ['type' => 'DECIMAL', 'constraint' => '4,3', 'null' => false, 'default' => 0.500],
            'cluster_id'        => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        // Evita duplicados del mismo teléfono y milisegundo.
        $this->forge->addUniqueKey(['device_id', 'detected_at_ms'], 'red_detections_device_time_unique');
        // Ventana de fusión y limpieza por antigüedad.
        $this->forge->addKey('detected_at_ms', false, false, 'red_detections_time_idx');
        // Actualización masiva de cluster_id.
        $this->forge->addKey('cluster_id', false, false, 'red_detections_cluster_idx');
        $this->forge->createTable('red_detections', true, $this->opcionesTabla());
    }

    private function crearRedAlerts(): void
    {
        if ($this->db->tableExists('red_alerts')) {
            return;
        }

        $this->forge->addField([
            'id'                   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'alert_uuid'           => ['type' => 'CHAR', 'constraint' => 36, 'null' => false],
            'source'               => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false, 'default' => 'crowd'],
            'source_id'            => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'status'               => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => false, 'default' => 'active'],
            'latitude'             => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'longitude'            => ['type' => 'DECIMAL', 'constraint' => '9,6', 'null' => false],
            'depth_km'             => ['type' => 'DECIMAL', 'constraint' => '6,2', 'null' => false, 'default' => 10.0],
            'magnitude_estimated'  => ['type' => 'DECIMAL', 'constraint' => '3,1', 'null' => false],
            'confidence'           => ['type' => 'DECIMAL', 'constraint' => '4,3', 'null' => false, 'default' => 0.500],
            'detection_count'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0],
            'device_count'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => false, 'default' => 0],
            'radius_km'            => ['type' => 'DECIMAL', 'constraint' => '6,1', 'null' => false, 'default' => 100.0],
            'origin_time_ms'       => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'expires_at'           => ['type' => 'DATETIME', 'null' => false],
            'details'              => ['type' => 'TEXT', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('alert_uuid', 'red_alerts_uuid_unique');
        // Un evento oficial (SGC) no se duplica. Los NULL son válidos en MySQL.
        $this->forge->addUniqueKey(['source', 'source_id'], 'red_alerts_source_unique');
        // Alertas activas y expiración.
        $this->forge->addKey(['status', 'expires_at'], false, false, 'red_alerts_status_expires_idx');
        // Búsqueda de alertas recientes por fuente.
        $this->forge->addKey(['source', 'created_at'], false, false, 'red_alerts_source_created_idx');
        $this->forge->addKey('created_at', false, false, 'red_alerts_created_idx');
        $this->forge->addKey('expires_at', false, false, 'red_alerts_expires_idx');
        $this->forge->createTable('red_alerts', true, $this->opcionesTabla());
    }

    private function crearRedAlertDeliveries(): void
    {
        if ($this->db->tableExists('red_alert_deliveries')) {
            return;
        }

        $this->forge->addField([
            'id'                => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'alert_id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'device_id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => false],
            'distance_km'       => ['type' => 'DECIMAL', 'constraint' => '6,1', 'null' => false],
            'seconds_to_s_wave' => ['type' => 'DECIMAL', 'constraint' => '6,1', 'null' => true],
            'channel'           => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'poll'],
            'status'            => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'pending'],
            'sent_at'           => ['type' => 'DATETIME', 'null' => true],
            'seen_at'           => ['type' => 'DATETIME', 'null' => true],
            'error_message'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        // Una sola entrega por alerta y teléfono.
        $this->forge->addUniqueKey(['alert_id', 'device_id'], 'red_deliveries_unique');
        // Bandeja de cada teléfono y marcado de vistas.
        $this->forge->addKey(['device_id', 'created_at'], false, false, 'red_deliveries_device_idx');
        // Limpieza por antigüedad.
        $this->forge->addKey('created_at', false, false, 'red_deliveries_created_idx');
        $this->forge->createTable('red_alert_deliveries', true, $this->opcionesTabla());
    }
}
