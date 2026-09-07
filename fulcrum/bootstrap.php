<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_collections')) {
    function vg_fulcrum_collections(): array
    {
        return [
            'fulcrum_events',
            'fulcrum_risk_scores',
            'fulcrum_recommendations',
            'fulcrum_audit_logs',
            'fulcrum_zone_stats',
            'fulcrum_ai_decisions',
            'fulcrum_missions_links',
        ];
    }
}

if (!function_exists('vg_fulcrum_json_field_map')) {
    function vg_fulcrum_json_field_map(string $collection): array
    {
        return match ($collection) {
            'fulcrum_events' => [
                'source_ids' => 'source_ids_json',
                'signal_sources' => 'signal_sources_json',
                'entities' => 'entities_json',
            ],
            'fulcrum_risk_scores' => [
                'metrics' => 'metrics_json',
            ],
            'fulcrum_recommendations' => [
                'linked_entities' => 'linked_entities_json',
            ],
            'fulcrum_audit_logs' => [
                'payload' => 'payload_json',
            ],
            'fulcrum_zone_stats' => [
                'metrics' => 'metrics_json',
                'client_ids' => 'client_ids_json',
            ],
            'fulcrum_ai_decisions' => [
                'payload' => 'payload_json',
            ],
            'fulcrum_missions_links' => [
                'agent_ids' => 'agent_ids_json',
                'source_event_ids' => 'source_event_ids_json',
                'payload' => 'payload_json',
            ],
            default => [],
        };
    }
}

if (!function_exists('vg_fulcrum_now')) {
    function vg_fulcrum_now(): string
    {
        return date('c');
    }
}

if (!function_exists('vg_fulcrum_id')) {
    function vg_fulcrum_id(string $prefix = 'fx'): string
    {
        return trim($prefix) . '-' . date('YmdHis') . '-' . substr(md5((string) microtime(true) . '|' . random_int(1000, 999999)), 0, 8);
    }
}

if (!function_exists('vg_fulcrum_slug')) {
    function vg_fulcrum_slug(string $value, string $fallback = 'general'): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));
        return $slug !== '' ? $slug : $fallback;
    }
}

if (!function_exists('vg_fulcrum_normalize_level')) {
    function vg_fulcrum_normalize_level(string $value): string
    {
        $value = strtolower(trim($value));
        return match (true) {
            $value === '',
            in_array($value, ['normal', 'nominal', 'stable', 'resolved', 'resolue', 'resoluee', 'closed'], true) => 'low',
            in_array($value, ['faible', 'low', 'minor', 'veille'], true) => 'low',
            in_array($value, ['modere', 'moderee', 'medium', 'moderate', 'warning', 'attention', 'en cours'], true) => 'medium',
            in_array($value, ['eleve', 'elevee', 'high', 'urgent', 'suspect', 'detecte', 'detected', 'pending'], true) => 'high',
            in_array($value, ['critique', 'critical', 'critical-match', 'danger', 'panic', 'p1'], true) => 'critical',
            default => 'medium',
        };
    }
}

if (!function_exists('vg_fulcrum_truthy')) {
    function vg_fulcrum_truthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'oui', 'yes', 'active', 'actif', 'validated', 'granted'], true);
    }
}

if (!function_exists('vg_fulcrum_sort_desc')) {
    function vg_fulcrum_sort_desc(array $rows, string $key = 'updated_at'): array
    {
        usort($rows, static function (array $left, array $right) use ($key): int {
            return strcmp((string) ($right[$key] ?? $right['created_at'] ?? ''), (string) ($left[$key] ?? $left['created_at'] ?? ''));
        });

        return array_values($rows);
    }
}

if (!function_exists('vg_fulcrum_actor')) {
    function vg_fulcrum_actor(): array
    {
        $user = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
        $name = trim((string) ($user['name'] ?? $_SESSION['user_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? 'Direction OPS'));
        $role = trim((string) ($user['role'] ?? 'admin'));
        $id = trim((string) ($user['id'] ?? $user['email'] ?? $_SESSION['agent_id'] ?? $_SESSION['client_id'] ?? 'ops-admin'));

        return [
            'id' => $id !== '' ? $id : 'ops-admin',
            'name' => $name !== '' ? $name : 'Direction OPS',
            'role' => $role !== '' ? $role : 'admin',
            'email' => trim((string) ($user['email'] ?? $_SESSION['portal_email'] ?? '')),
        ];
    }
}

if (!function_exists('vg_fulcrum_db_available')) {
    function vg_fulcrum_db_available(): bool
    {
        return function_exists('vg_db_connected')
            && function_exists('vg_db_exec')
            && function_exists('vg_db_has_table')
            && vg_db_connected();
    }
}

if (!function_exists('vg_fulcrum_db_table_can_write')) {
    function vg_fulcrum_db_table_can_write(string $table): bool
    {
        return vg_fulcrum_db_available() && vg_db_has_table($table);
    }
}

if (!function_exists('vg_fulcrum_sql_schema')) {
    function vg_fulcrum_sql_schema(): array
    {
        return [
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_events (
    id VARCHAR(80) PRIMARY KEY,
    snapshot_token VARCHAR(80) NULL,
    group_key VARCHAR(190) NOT NULL,
    source VARCHAR(60) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    axis VARCHAR(60) NOT NULL,
    title VARCHAR(255) NOT NULL,
    detail TEXT NULL,
    client_id VARCHAR(80) NULL,
    client_name VARCHAR(190) NULL,
    zone VARCHAR(160) NULL,
    severity VARCHAR(40) NOT NULL,
    priority_label VARCHAR(20) NOT NULL,
    confidence DECIMAL(6,4) NOT NULL DEFAULT 0.6500,
    risk_score INT NOT NULL DEFAULT 0,
    signal_count INT NOT NULL DEFAULT 1,
    requires_validation TINYINT(1) NOT NULL DEFAULT 0,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    recommended_action VARCHAR(255) NULL,
    source_ids_json LONGTEXT NULL,
    signal_sources_json LONGTEXT NULL,
    entities_json LONGTEXT NULL,
    status VARCHAR(60) NULL,
    source_id VARCHAR(120) NULL,
    route VARCHAR(255) NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_events_zone (zone),
    INDEX idx_fulcrum_events_score (risk_score),
    INDEX idx_fulcrum_events_created (created_at)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_risk_scores (
    id VARCHAR(80) PRIMARY KEY,
    snapshot_token VARCHAR(80) NULL,
    scope VARCHAR(40) NOT NULL,
    scope_id VARCHAR(120) NOT NULL,
    label VARCHAR(190) NOT NULL,
    zone VARCHAR(160) NULL,
    risk_score INT NOT NULL DEFAULT 0,
    risk_band VARCHAR(40) NOT NULL,
    priority_label VARCHAR(20) NOT NULL,
    confidence DECIMAL(6,4) NOT NULL DEFAULT 0.6500,
    status VARCHAR(60) NOT NULL DEFAULT 'active',
    metrics_json LONGTEXT NULL,
    computed_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_risk_scope (scope, scope_id),
    INDEX idx_fulcrum_risk_zone (zone)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_recommendations (
    id VARCHAR(80) PRIMARY KEY,
    snapshot_token VARCHAR(80) NULL,
    fingerprint VARCHAR(190) NOT NULL,
    category VARCHAR(60) NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    severity VARCHAR(40) NOT NULL,
    priority_label VARCHAR(20) NOT NULL,
    risk_score INT NOT NULL DEFAULT 0,
    confidence DECIMAL(6,4) NOT NULL DEFAULT 0.6500,
    zone VARCHAR(160) NULL,
    client_id VARCHAR(80) NULL,
    source_event_id VARCHAR(80) NULL,
    action_label VARCHAR(190) NOT NULL,
    action_route VARCHAR(255) NULL,
    rationale TEXT NULL,
    requires_validation TINYINT(1) NOT NULL DEFAULT 1,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    validated_by_id VARCHAR(80) NULL,
    validated_by_name VARCHAR(190) NULL,
    validation_note TEXT NULL,
    linked_entities_json LONGTEXT NULL,
    created_at VARCHAR(40) NOT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_recommendations_fingerprint (fingerprint),
    INDEX idx_fulcrum_recommendations_status (validation_status)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_audit_logs (
    id VARCHAR(80) PRIMARY KEY,
    action VARCHAR(80) NOT NULL,
    subject_type VARCHAR(80) NOT NULL,
    subject_id VARCHAR(120) NOT NULL,
    actor_id VARCHAR(80) NULL,
    actor_name VARCHAR(190) NULL,
    actor_role VARCHAR(60) NULL,
    payload_json LONGTEXT NULL,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_audit_subject (subject_type, subject_id),
    INDEX idx_fulcrum_audit_created (created_at)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_zone_stats (
    id VARCHAR(80) PRIMARY KEY,
    snapshot_token VARCHAR(80) NULL,
    zone VARCHAR(160) NOT NULL,
    label VARCHAR(190) NOT NULL,
    risk_score INT NOT NULL DEFAULT 0,
    risk_band VARCHAR(40) NOT NULL,
    hot_alerts INT NOT NULL DEFAULT 0,
    sites_count INT NOT NULL DEFAULT 0,
    camera_count INT NOT NULL DEFAULT 0,
    agents_available INT NOT NULL DEFAULT 0,
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    metrics_json LONGTEXT NULL,
    client_ids_json LONGTEXT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_zone_stats_zone (zone),
    INDEX idx_fulcrum_zone_stats_score (risk_score)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_ai_decisions (
    id VARCHAR(80) PRIMARY KEY,
    recommendation_id VARCHAR(80) NULL,
    decision VARCHAR(40) NOT NULL,
    actor_id VARCHAR(80) NULL,
    actor_name VARCHAR(190) NULL,
    actor_role VARCHAR(60) NULL,
    note TEXT NULL,
    payload_json LONGTEXT NULL,
    created_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_ai_decisions_recommendation (recommendation_id),
    INDEX idx_fulcrum_ai_decisions_created (created_at)
)
SQL,
            <<<SQL
CREATE TABLE IF NOT EXISTS fulcrum_missions_links (
    id VARCHAR(80) PRIMARY KEY,
    snapshot_token VARCHAR(80) NULL,
    client_id VARCHAR(80) NULL,
    client_name VARCHAR(190) NULL,
    zone VARCHAR(160) NULL,
    alert_id VARCHAR(80) NULL,
    intervention_id VARCHAR(80) NULL,
    status VARCHAR(60) NOT NULL,
    priority_label VARCHAR(20) NOT NULL,
    agent_ids_json LONGTEXT NULL,
    source_event_ids_json LONGTEXT NULL,
    payload_json LONGTEXT NULL,
    updated_at VARCHAR(40) NOT NULL,
    INDEX idx_fulcrum_missions_client (client_id),
    INDEX idx_fulcrum_missions_intervention (intervention_id)
)
SQL,
        ];
    }
}

if (!function_exists('vg_fulcrum_ensure_schema')) {
    function vg_fulcrum_ensure_schema(): void
    {
        static $ensured = false;

        if ($ensured || !vg_fulcrum_db_available()) {
            return;
        }

        foreach (vg_fulcrum_sql_schema() as $statement) {
            vg_db_exec($statement);
        }

        $ensured = true;
    }
}

if (!function_exists('vg_fulcrum_encode_row')) {
    function vg_fulcrum_encode_row(string $collection, array $row): array
    {
        $encoded = $row;
        foreach (vg_fulcrum_json_field_map($collection) as $field => $dbField) {
            if (!array_key_exists($field, $encoded)) {
                continue;
            }

            $value = $encoded[$field];
            $encoded[$dbField] = is_string($value)
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            unset($encoded[$field]);
        }

        foreach ($encoded as $field => $value) {
            if (is_bool($value)) {
                $encoded[$field] = $value ? 1 : 0;
            }
        }

        return $encoded;
    }
}

if (!function_exists('vg_fulcrum_collection_rows')) {
    function vg_fulcrum_collection_rows(string $collection): array
    {
        $rows = vgx_store()[$collection] ?? [];
        return vg_fulcrum_sort_desc(is_array($rows) ? $rows : []);
    }
}

if (!function_exists('vg_fulcrum_find_row_by_id')) {
    function vg_fulcrum_find_row_by_id(string $collection, string $rowId): ?array
    {
        foreach (vg_fulcrum_collection_rows($collection) as $row) {
            if ((string) ($row['id'] ?? '') === trim($rowId)) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('vg_fulcrum_store_replace_collection')) {
    function vg_fulcrum_store_replace_collection(string $collection, array $rows, bool $syncDb = true): void
    {
        vg_fulcrum_store_replace_collections([
            $collection => $rows,
        ], $syncDb);
    }
}

if (!function_exists('vg_fulcrum_store_replace_collections')) {
    function vg_fulcrum_store_replace_collections(array $collections, bool $syncDb = true): void
    {
        if ($collections === []) {
            return;
        }

        $store = vgx_store();
        $normalizedCollections = [];

        foreach ($collections as $collection => $rows) {
            $normalizedRows = array_values(array_filter(is_array($rows) ? $rows : [], static fn($row): bool => is_array($row)));
            $store[(string) $collection] = $normalizedRows;
            $normalizedCollections[(string) $collection] = $normalizedRows;
        }

        vgx_store_save($store);

        if (!$syncDb) {
            return;
        }

        vg_fulcrum_ensure_schema();
        if (!function_exists('vg_db_insert_or_update_row')) {
            return;
        }

        foreach ($normalizedCollections as $collection => $rows) {
            if (!vg_fulcrum_db_table_can_write($collection)) {
                continue;
            }

            vg_db_exec('DELETE FROM `' . $collection . '`');
            foreach ($rows as $row) {
                vg_db_insert_or_update_row($collection, vg_fulcrum_encode_row($collection, $row));
            }
        }
    }
}

if (!function_exists('vg_fulcrum_store_upsert_row')) {
    function vg_fulcrum_store_upsert_row(string $collection, array $row, bool $syncDb = true): array
    {
        $rows = vg_fulcrum_collection_rows($collection);
        $rowId = trim((string) ($row['id'] ?? ''));
        if ($rowId === '') {
            $row['id'] = vg_fulcrum_id('fxrow');
            $rowId = $row['id'];
        }

        $updated = false;
        foreach ($rows as $index => $existing) {
            if ((string) ($existing['id'] ?? '') !== $rowId) {
                continue;
            }

            $rows[$index] = array_merge($existing, $row);
            $updated = true;
            break;
        }

        if (!$updated) {
            $rows[] = $row;
        }

        vg_fulcrum_store_replace_collection($collection, vg_fulcrum_sort_desc($rows), $syncDb);
        return vg_fulcrum_find_row_by_id($collection, $rowId) ?? $row;
    }
}

if (!function_exists('vg_fulcrum_store_delete_where')) {
    function vg_fulcrum_store_delete_where(string $collection, string $field, string $value, bool $syncDb = true): array
    {
        $rows = vg_fulcrum_collection_rows($collection);
        $deleted = [];
        $filtered = [];

        foreach ($rows as $row) {
            if ((string) ($row[$field] ?? '') === $value) {
                $deleted[] = $row;
                continue;
            }

            $filtered[] = $row;
        }

        vg_fulcrum_store_replace_collection($collection, $filtered, $syncDb);
        return $deleted;
    }
}

if (!function_exists('vg_fulcrum_suppressions')) {
    function vg_fulcrum_suppressions(): array
    {
        $raw = vgx_store()['fulcrum_suppressions'] ?? [];
        $raw = is_array($raw) ? $raw : [];

        $normalize = static function ($values): array {
            if (!is_array($values)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, $values))));
        };

        return [
            'event_group_keys' => $normalize($raw['event_group_keys'] ?? []),
            'recommendation_fingerprints' => $normalize($raw['recommendation_fingerprints'] ?? []),
        ];
    }
}

if (!function_exists('vg_fulcrum_save_suppressions')) {
    function vg_fulcrum_save_suppressions(array $payload): void
    {
        $current = vg_fulcrum_suppressions();
        $merged = [
            'event_group_keys' => array_values(array_unique(array_filter(array_map('strval', (array) ($payload['event_group_keys'] ?? $current['event_group_keys']))))),
            'recommendation_fingerprints' => array_values(array_unique(array_filter(array_map('strval', (array) ($payload['recommendation_fingerprints'] ?? $current['recommendation_fingerprints']))))),
        ];

        $store = vgx_store();
        $store['fulcrum_suppressions'] = $merged;
        vgx_store_save($store);
    }
}

if (!function_exists('vg_fulcrum_mark_event_suppressed')) {
    function vg_fulcrum_mark_event_suppressed(string $groupKey): void
    {
        $groupKey = trim($groupKey);
        if ($groupKey === '') {
            return;
        }

        $supp = vg_fulcrum_suppressions();
        $supp['event_group_keys'][] = $groupKey;
        vg_fulcrum_save_suppressions($supp);
    }
}

if (!function_exists('vg_fulcrum_mark_recommendation_suppressed')) {
    function vg_fulcrum_mark_recommendation_suppressed(string $fingerprint): void
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return;
        }

        $supp = vg_fulcrum_suppressions();
        $supp['recommendation_fingerprints'][] = $fingerprint;
        vg_fulcrum_save_suppressions($supp);
    }
}

if (!function_exists('vg_fulcrum_clear_suppressions')) {
    function vg_fulcrum_clear_suppressions(): void
    {
        $store = vgx_store();
        $store['fulcrum_suppressions'] = [
            'event_group_keys' => [],
            'recommendation_fingerprints' => [],
        ];
        vgx_store_save($store);
    }
}

require_once __DIR__ . '/risk.php';
require_once __DIR__ . '/stats.php';
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/api.php';
