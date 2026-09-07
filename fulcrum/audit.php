<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_log_audit')) {
    function vg_fulcrum_log_audit(string $action, string $subjectType, string $subjectId, array $payload = []): array
    {
        $actor = vg_fulcrum_actor();
        $row = [
            'id' => vg_fulcrum_id('fxaudit'),
            'action' => trim($action) !== '' ? trim($action) : 'action',
            'subject_type' => trim($subjectType) !== '' ? trim($subjectType) : 'module',
            'subject_id' => trim($subjectId) !== '' ? trim($subjectId) : 'fulcrum',
            'actor_id' => (string) ($actor['id'] ?? ''),
            'actor_name' => (string) ($actor['name'] ?? 'Direction OPS'),
            'actor_role' => (string) ($actor['role'] ?? 'admin'),
            'payload' => $payload,
            'created_at' => vg_fulcrum_now(),
        ];

        return vg_fulcrum_store_upsert_row('fulcrum_audit_logs', $row);
    }
}

if (!function_exists('vg_fulcrum_record_decision')) {
    function vg_fulcrum_record_decision(string $recommendationId, string $decision, string $note = '', array $payload = []): array
    {
        $actor = vg_fulcrum_actor();
        $row = [
            'id' => vg_fulcrum_id('fxdecision'),
            'recommendation_id' => trim($recommendationId),
            'decision' => trim($decision) !== '' ? trim($decision) : 'noted',
            'actor_id' => (string) ($actor['id'] ?? ''),
            'actor_name' => (string) ($actor['name'] ?? 'Direction OPS'),
            'actor_role' => (string) ($actor['role'] ?? 'admin'),
            'note' => $note,
            'payload' => $payload,
            'created_at' => vg_fulcrum_now(),
        ];

        return vg_fulcrum_store_upsert_row('fulcrum_ai_decisions', $row);
    }
}

if (!function_exists('vg_fulcrum_set_recommendation_status')) {
    function vg_fulcrum_set_recommendation_status(string $recommendationId, string $status, string $note = ''): ?array
    {
        $recommendation = vg_fulcrum_find_row_by_id('fulcrum_recommendations', trim($recommendationId));
        if (!is_array($recommendation)) {
            return null;
        }

        $actor = vg_fulcrum_actor();
        $recommendation['validation_status'] = trim($status) !== '' ? trim($status) : 'pending';
        $recommendation['validated_by_id'] = (string) ($actor['id'] ?? '');
        $recommendation['validated_by_name'] = (string) ($actor['name'] ?? 'Direction OPS');
        $recommendation['validation_note'] = $note;
        $recommendation['updated_at'] = vg_fulcrum_now();

        $saved = vg_fulcrum_store_upsert_row('fulcrum_recommendations', $recommendation);
        vg_fulcrum_record_decision((string) ($saved['id'] ?? $recommendationId), $status, $note, [
            'fingerprint' => (string) ($saved['fingerprint'] ?? ''),
            'title' => (string) ($saved['title'] ?? ''),
        ]);
        vg_fulcrum_log_audit('recommendation_' . $status, 'recommendation', (string) ($saved['id'] ?? $recommendationId), [
            'note' => $note,
            'fingerprint' => (string) ($saved['fingerprint'] ?? ''),
        ]);

        return $saved;
    }
}

if (!function_exists('vg_fulcrum_delete_event')) {
    function vg_fulcrum_delete_event(string $eventId): bool
    {
        $event = vg_fulcrum_find_row_by_id('fulcrum_events', trim($eventId));
        if (!is_array($event)) {
            return false;
        }

        $groupKey = trim((string) ($event['group_key'] ?? ''));
        if ($groupKey !== '') {
            vg_fulcrum_mark_event_suppressed($groupKey);
        }

        vg_fulcrum_store_delete_where('fulcrum_events', 'id', trim($eventId));
        vg_fulcrum_log_audit('suppress_event', 'event', trim($eventId), [
            'group_key' => $groupKey,
            'title' => (string) ($event['title'] ?? ''),
        ]);

        return true;
    }
}

if (!function_exists('vg_fulcrum_delete_recommendation')) {
    function vg_fulcrum_delete_recommendation(string $recommendationId): bool
    {
        $recommendation = vg_fulcrum_find_row_by_id('fulcrum_recommendations', trim($recommendationId));
        if (!is_array($recommendation)) {
            return false;
        }

        $fingerprint = trim((string) ($recommendation['fingerprint'] ?? ''));
        if ($fingerprint !== '') {
            vg_fulcrum_mark_recommendation_suppressed($fingerprint);
        }

        vg_fulcrum_store_delete_where('fulcrum_recommendations', 'id', trim($recommendationId));
        vg_fulcrum_log_audit('suppress_recommendation', 'recommendation', trim($recommendationId), [
            'fingerprint' => $fingerprint,
            'title' => (string) ($recommendation['title'] ?? ''),
        ]);

        return true;
    }
}

if (!function_exists('vg_fulcrum_delete_audit_log')) {
    function vg_fulcrum_delete_audit_log(string $auditId): bool
    {
        return vg_fulcrum_store_delete_where('fulcrum_audit_logs', 'id', trim($auditId)) !== [];
    }
}

if (!function_exists('vg_fulcrum_purge_collection')) {
    function vg_fulcrum_purge_collection(string $collection): bool
    {
        $allowed = array_merge(vg_fulcrum_collections(), ['fulcrum_suppressions']);
        if (!in_array($collection, $allowed, true)) {
            return false;
        }

        if ($collection === 'fulcrum_suppressions') {
            vg_fulcrum_clear_suppressions();
            vg_fulcrum_log_audit('purge_collection', 'collection', $collection);
            return true;
        }

        vg_fulcrum_store_replace_collection($collection, []);
        vg_fulcrum_log_audit('purge_collection', 'collection', $collection);
        return true;
    }
}

if (!function_exists('vg_fulcrum_purge_module')) {
    function vg_fulcrum_purge_module(): bool
    {
        foreach (vg_fulcrum_collections() as $collection) {
            vg_fulcrum_store_replace_collection($collection, []);
        }
        vg_fulcrum_clear_suppressions();
        return true;
    }
}
