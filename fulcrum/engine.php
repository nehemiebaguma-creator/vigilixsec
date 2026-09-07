<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_index_by_id')) {
    function vg_fulcrum_index_by_id(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $indexed[$id] = $row;
            }
        }

        return $indexed;
    }
}

if (!function_exists('vg_fulcrum_plate_normalize')) {
    function vg_fulcrum_plate_normalize(string $plate): string
    {
        $plate = strtoupper(trim($plate));
        $plate = (string) preg_replace('/[^A-Z0-9]+/', '', $plate);
        return $plate;
    }
}

if (!function_exists('vg_fulcrum_road_status_is_active')) {
    function vg_fulcrum_road_status_is_active(string $status): bool
    {
        return !in_array(strtolower(trim($status)), ['resolved', 'resolu', 'closed', 'archive', 'archived', 'inactive', 'inactif'], true);
    }
}

if (!function_exists('vg_fulcrum_plate_auth_is_alert')) {
    function vg_fulcrum_plate_auth_is_alert(string $status): bool
    {
        $status = strtolower(trim($status));
        if ($status === '') {
            return false;
        }

        foreach (['watchlist', 'unauthor', 'non autor', 'non_autor', 'suspect', 'blacklist', 'recherche', 'critical', 'critique', 'high'] as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vg_fulcrum_plate_needs_verification')) {
    function vg_fulcrum_plate_needs_verification(string $status, array $integrity = [], array $identity = []): bool
    {
        if (!empty($integrity['needs_verification']) || !empty($identity['without_qr_code'])) {
            return true;
        }

        $status = strtolower(trim($status));
        foreach (['verify', 'verification', 'sans qr', 'without qr', 'old', 'ancien'] as $needle) {
            if (str_contains($status, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vg_fulcrum_road_timestamp')) {
    function vg_fulcrum_road_timestamp(array $row): string
    {
        foreach (['updated_at', 'read_at', 'reported_at', 'created_at'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return vg_fulcrum_now();
    }
}

if (!function_exists('vg_fulcrum_collect_road_events')) {
    function vg_fulcrum_collect_road_events(array $signals, array $clientsById, array $cameraById = []): array
    {
        $events = [];
        $watchlistByPlate = [];
        $authorityByPlate = [];
        $identityByPlate = [];
        $trafficPressure = [];
        $plateTrails = [];
        $roadVisionEvent = static function (array $row): bool {
            $category = strtolower(trim((string) ($row['category'] ?? $row['axis'] ?? '')));
            $eventType = strtolower(trim((string) ($row['event_type'] ?? '')));
            if (in_array($category, ['traffic', 'incident', 'road', 'mobility'], true)) {
                return true;
            }

            foreach (['watchlist', 'traffic', 'vehicle', 'parking', 'blocking', 'incident', 'corridor'] as $needle) {
                if (str_contains($eventType, $needle)) {
                    return true;
                }
            }

            return false;
        };
        $ensurePressure = static function (string $zone) use (&$trafficPressure): void {
            $zone = trim($zone) !== '' ? trim($zone) : 'Kinshasa centre';
            if (!isset($trafficPressure[$zone])) {
                $trafficPressure[$zone] = [
                    'reports' => 0,
                    'incidents' => 0,
                    'plate_alerts' => 0,
                    'plate_verify' => 0,
                    'vision_critical' => 0,
                ];
            }
        };

        foreach ((array) ($signals['vehicle_watchlist'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $plate = vg_fulcrum_plate_normalize((string) ($entry['plate_number'] ?? $entry['plate'] ?? ''));
            if ($plate === '') {
                continue;
            }

            $isActive = array_key_exists('active', $entry)
                ? !empty($entry['active'])
                : !in_array(strtolower(trim((string) ($entry['status'] ?? 'active'))), ['inactive', 'disabled', 'archive', 'archived'], true);
            if ($isActive) {
                $watchlistByPlate[$plate] = $entry;
            }
        }

        foreach ((array) ($signals['authority_vehicles'] ?? []) as $entry) {
            if (!is_array($entry) || empty($entry['is_active'])) {
                continue;
            }

            $plate = vg_fulcrum_plate_normalize((string) ($entry['plate_number'] ?? ''));
            if ($plate !== '') {
                $authorityByPlate[$plate] = $entry;
            }
        }

        foreach ((array) ($signals['vehicle_identities'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $plate = vg_fulcrum_plate_normalize((string) ($entry['plate_number'] ?? ''));
            if ($plate !== '') {
                $identityByPlate[$plate] = $entry;
            }
        }

        foreach ((array) ($signals['traffic_incidents'] ?? []) as $incident) {
            if (!is_array($incident) || !vg_fulcrum_road_status_is_active((string) ($incident['status'] ?? 'detecte'))) {
                continue;
            }

            $zone = vg_fulcrum_zone_label($incident, $clientsById);
            $ensurePressure($zone);
            $trafficPressure[$zone]['incidents'] += 1;
        }

        foreach (vg_fulcrum_sort_desc((array) ($signals['traffic_reports'] ?? []), 'reported_at') as $report) {
            if (!is_array($report) || !vg_fulcrum_road_status_is_active((string) ($report['status'] ?? 'active'))) {
                continue;
            }

            $sourceType = strtolower(trim((string) ($report['source_type'] ?? '')));
            $sourceId = trim((string) ($report['source_id'] ?? ''));
            $cameraContext = $sourceType === 'camera' && $sourceId !== '' && isset($cameraById[$sourceId]) && is_array($cameraById[$sourceId])
                ? $cameraById[$sourceId]
                : [];
            $reportContext = $report + $cameraContext;
            $clientId = trim((string) ($reportContext['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $zone = vg_fulcrum_zone_label($reportContext, $clientsById);
            $ensurePressure($zone);
            $trafficPressure[$zone]['reports'] += 1;

            $severity = vg_fulcrum_normalize_level((string) ($report['severity'] ?? $report['priority'] ?? $report['status'] ?? 'high'));
            if ($severity === 'low') {
                $severity = 'medium';
            }

            $sourceLabel = trim((string) ($report['axis'] ?? $report['source_label'] ?? $report['source_id'] ?? 'Corridor routier'));
            $detail = trim((string) ($report['detail'] ?? $report['recommendation'] ?? $report['culprit_label'] ?? 'Signal routier actif a consolider.'));
            if ($sourceLabel !== '') {
                $detail .= ' • ' . $sourceLabel;
            }

            $events[] = vg_fulcrum_make_event([
                'source' => 'road',
                'source_id' => trim((string) ($report['id'] ?? $sourceId ?: vg_fulcrum_id('fxroad'))),
                'event_type' => 'traffic_report',
                'axis' => 'traffic',
                'group_key' => 'zone:' . vg_fulcrum_slug($zone) . ':road',
                'title' => in_array($severity, ['critical', 'high'], true) ? 'Corridor routier sous tension' : 'Flux routier a surveiller',
                'detail' => $detail,
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Corridor VIGILANCE') : (string) ($report['client_name'] ?? ''),
                'zone' => $zone,
                'severity' => $severity,
                'confidence' => 0.76,
                'requires_validation' => in_array($severity, ['critical', 'high'], true),
                'recommended_action' => 'Coordonner le controle routier, la video et le centre OPS sur ce corridor.',
                'source_ids' => [trim((string) ($report['id'] ?? $sourceId))],
                'signal_sources' => ['road', 'traffic'],
                'status' => (string) ($report['status'] ?? 'active'),
                'entities' => [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'traffic_reports_active' => 1,
                    'vehicle_count' => (int) ($report['vehicle_count'] ?? 0),
                    'culprit_label' => (string) ($report['culprit_label'] ?? ''),
                ],
                'created_at' => vg_fulcrum_road_timestamp($report),
                'updated_at' => vg_fulcrum_road_timestamp($report),
            ]);
        }

        foreach (vg_fulcrum_sort_desc((array) ($signals['plate_reads'] ?? []), 'read_at') as $read) {
            if (!is_array($read)) {
                continue;
            }

            $plate = vg_fulcrum_plate_normalize((string) ($read['plate_number'] ?? $read['plate'] ?? ''));
            if ($plate === '') {
                continue;
            }

            $sourceLabel = trim((string) ($read['source'] ?? $read['source_label'] ?? $read['camera_name'] ?? $read['source_id'] ?? $plate));
            $sourceType = strtolower(trim((string) ($read['source_type'] ?? 'camera'))) ?: 'camera';
            $sourceId = trim((string) ($read['source_id'] ?? $read['camera_id'] ?? ''));
            $cameraContext = $sourceType === 'camera' && $sourceId !== '' && isset($cameraById[$sourceId]) && is_array($cameraById[$sourceId])
                ? $cameraById[$sourceId]
                : [];
            $readContext = $read + $cameraContext;
            $zone = vg_fulcrum_zone_label($readContext, $clientsById);
            $ensurePressure($zone);

            if (!isset($plateTrails[$plate])) {
                $plateTrails[$plate] = [
                    'latest' => $readContext,
                    'sources' => [],
                    'source_types' => [],
                    'zones' => [],
                    'detections' => [],
                ];
            }

            if ($sourceLabel !== '') {
                $plateTrails[$plate]['sources'][$sourceLabel] = true;
            }
            $plateTrails[$plate]['source_types'][$sourceType] = true;
            $plateTrails[$plate]['zones'][$zone] = true;
            $plateTrails[$plate]['detections'][] = $readContext;
        }

        foreach ($plateTrails as $plate => $trail) {
            $latestRead = (array) ($trail['latest'] ?? []);
            $zone = vg_fulcrum_zone_label($latestRead, $clientsById);
            $ensurePressure($zone);
            $clientId = trim((string) ($latestRead['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $authStatus = strtolower(trim((string) ($latestRead['auth_status'] ?? '')));
            $risk = function_exists('vgx_check_vehicle_risk') ? (array) vgx_check_vehicle_risk($plate) : [];
            $watchlist = $watchlistByPlate[$plate] ?? (is_array($risk['watchlist_entry'] ?? null) ? (array) $risk['watchlist_entry'] : null);
            $authority = $authorityByPlate[$plate] ?? null;
            if (is_array($authority)) {
                continue;
            }

            $identity = $identityByPlate[$plate] ?? (is_array($risk['identity'] ?? null) ? (array) $risk['identity'] : []);
            $integrity = function_exists('vgx_plate_get_integrity') ? (array) vgx_plate_get_integrity($plate) : [];
            $sourceCount = count((array) ($trail['sources'] ?? []));
            $zoneCount = count((array) ($trail['zones'] ?? []));
            $detectionCount = count((array) ($trail['detections'] ?? []));
            $sourceLabels = array_keys((array) ($trail['sources'] ?? []));
            $sourceTypes = array_keys((array) ($trail['source_types'] ?? []));
            $riskLevel = strtoupper(trim((string) ($risk['risk_level'] ?? '')));
            $riskReason = trim((string) ($risk['reason'] ?? $latestRead['reason'] ?? $latestRead['auth_status'] ?? ''));
            $isAlert = is_array($watchlist) || !empty($risk['alert']) || vg_fulcrum_plate_auth_is_alert($authStatus) || in_array($riskLevel, ['CRITICAL', 'HIGH'], true);
            $needsVerification = !$isAlert && vg_fulcrum_plate_needs_verification($authStatus . ' ' . $riskLevel, $integrity, $identity);
            $plateDetail = trim((string) ($latestRead['source'] ?? $latestRead['source_label'] ?? $latestRead['camera_name'] ?? $latestRead['source_id'] ?? 'capteur routier'));

            if ($isAlert) {
                $trafficPressure[$zone]['plate_alerts'] += 1;
                $severity = is_array($watchlist) || $riskLevel === 'CRITICAL' ? 'critical' : 'high';
                $events[] = vg_fulcrum_make_event([
                    'source' => 'plate',
                    'source_id' => trim((string) ($latestRead['id'] ?? $plate)),
                    'event_type' => 'plate_watch',
                    'axis' => 'traffic',
                    'group_key' => 'plate:' . vg_fulcrum_slug($plate, $plate) . ':road',
                    'title' => is_array($watchlist) ? 'Plaque watchlist detectee' : 'Plaque routiere sensible detectee',
                    'detail' => $plate . ' • ' . ($riskReason !== '' ? $riskReason : 'Controle terrain recommande.') . ($plateDetail !== '' ? ' • ' . $plateDetail : ''),
                    'client_id' => $clientId,
                    'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Controle routier') : '',
                    'zone' => $zone,
                    'severity' => $severity,
                    'confidence' => is_array($watchlist) ? 0.9 : 0.84,
                    'signal_count' => max(1, $detectionCount),
                    'requires_validation' => true,
                    'recommended_action' => 'Verifier la scene, croiser camera/drone et decider un controle terrain immediat.',
                    'source_ids' => [trim((string) ($latestRead['id'] ?? $plate))],
                    'signal_sources' => array_values(array_unique(array_filter(array_merge(['plate'], $sourceTypes)))),
                    'status' => (string) ($latestRead['auth_status'] ?? $risk['risk_level'] ?? 'alert'),
                    'entities' => [
                        'plate' => $plate,
                        'auth_status' => (string) ($latestRead['auth_status'] ?? $risk['risk_level'] ?? ''),
                        'risk_reason' => $riskReason,
                        'source_count' => $sourceCount,
                        'zone_count' => $zoneCount,
                        'detection_count' => $detectionCount,
                        'watchlist_id' => is_array($watchlist) ? (string) ($watchlist['id'] ?? '') : '',
                        'without_qr_code' => !empty($identity['without_qr_code']) || !empty($integrity['needs_verification']),
                    ],
                    'created_at' => vg_fulcrum_road_timestamp($latestRead),
                    'updated_at' => vg_fulcrum_road_timestamp($latestRead),
                ]);
            } elseif ($needsVerification) {
                $trafficPressure[$zone]['plate_verify'] += 1;
                $events[] = vg_fulcrum_make_event([
                    'source' => 'road',
                    'source_id' => trim((string) ($latestRead['id'] ?? $plate . '-verify')),
                    'event_type' => 'plate_verification',
                    'axis' => 'traffic',
                    'group_key' => 'plate:' . vg_fulcrum_slug($plate, $plate) . ':road',
                    'title' => 'Verification d integrite plaque requise',
                    'detail' => $plate . ' • format a confirmer ou exception sans QR a arbitrer.' . ($plateDetail !== '' ? ' • ' . $plateDetail : ''),
                    'client_id' => $clientId,
                    'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Controle routier') : '',
                    'zone' => $zone,
                    'severity' => $detectionCount >= 2 ? 'high' : 'medium',
                    'confidence' => 0.72,
                    'signal_count' => max(1, $detectionCount),
                    'requires_validation' => true,
                    'recommended_action' => 'Verifier la plaque, l exception autorisee et l identite vehicule avant escalation.',
                    'source_ids' => [trim((string) ($latestRead['id'] ?? $plate . '-verify'))],
                    'signal_sources' => array_values(array_unique(array_filter(array_merge(['road'], $sourceTypes)))),
                    'status' => (string) ($latestRead['auth_status'] ?? 'verify'),
                    'entities' => [
                        'plate' => $plate,
                        'source_count' => $sourceCount,
                        'zone_count' => $zoneCount,
                        'detection_count' => $detectionCount,
                        'without_qr_code' => !empty($identity['without_qr_code']) || !empty($integrity['needs_verification']),
                    ],
                    'created_at' => vg_fulcrum_road_timestamp($latestRead),
                    'updated_at' => vg_fulcrum_road_timestamp($latestRead),
                ]);
            }

            if (($isAlert || $needsVerification) && ($sourceCount >= 2 || $detectionCount >= 3 || $zoneCount >= 2)) {
                $events[] = vg_fulcrum_make_event([
                    'source' => 'road',
                    'source_id' => 'trajectory-' . vg_fulcrum_slug($plate, $plate),
                    'event_type' => 'plate_trajectory',
                    'axis' => 'traffic',
                    'group_key' => 'plate:' . vg_fulcrum_slug($plate, $plate) . ':road',
                    'title' => 'Trajectoire plaque multi-capteurs',
                    'detail' => $plate . ' • ' . $detectionCount . ' detection(s) sur ' . max(1, $sourceCount) . ' source(s) et ' . max(1, $zoneCount) . ' zone(s): ' . implode(', ', array_slice($sourceLabels, 0, 3)),
                    'client_id' => $clientId,
                    'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Controle routier') : '',
                    'zone' => $zone,
                    'severity' => $isAlert ? 'critical' : 'high',
                    'confidence' => 0.84,
                    'signal_count' => max(2, $detectionCount),
                    'requires_validation' => true,
                    'recommended_action' => 'Consolider la trajectoire, verrouiller les preuves et decider l interception ou la veille active.',
                    'source_ids' => ['trajectory-' . vg_fulcrum_slug($plate, $plate)],
                    'signal_sources' => array_values(array_unique(array_filter(array_merge(['plate', 'road'], $sourceTypes)))),
                    'status' => $isAlert ? 'alert' : 'verify',
                    'entities' => [
                        'plate' => $plate,
                        'source_count' => $sourceCount,
                        'zone_count' => $zoneCount,
                        'detection_count' => $detectionCount,
                    ],
                    'created_at' => vg_fulcrum_road_timestamp($latestRead),
                    'updated_at' => vg_fulcrum_road_timestamp($latestRead),
                ]);
            }
        }

        foreach (vg_fulcrum_sort_desc((array) ($signals['vision_events'] ?? []), 'created_at') as $visionEvent) {
            if (!is_array($visionEvent) || !$roadVisionEvent($visionEvent)) {
                continue;
            }

            $severity = vg_fulcrum_normalize_level((string) ($visionEvent['severity'] ?? $visionEvent['risk_level'] ?? $visionEvent['status'] ?? 'medium'));
            if ($severity === 'low') {
                continue;
            }

            $sourceType = strtolower(trim((string) ($visionEvent['source_type'] ?? '')));
            $sourceId = trim((string) ($visionEvent['source_id'] ?? $visionEvent['camera_id'] ?? ''));
            $cameraContext = $sourceType === 'camera' && $sourceId !== '' && isset($cameraById[$sourceId]) && is_array($cameraById[$sourceId])
                ? $cameraById[$sourceId]
                : [];
            $visionContext = $visionEvent + $cameraContext;
            $plate = vg_fulcrum_plate_normalize((string) ($visionContext['plate_number'] ?? $visionContext['plate'] ?? ''));
            $zone = vg_fulcrum_zone_label($visionContext, $clientsById);
            $ensurePressure($zone);
            if (in_array($severity, ['critical', 'high'], true)) {
                $trafficPressure[$zone]['vision_critical'] += 1;
            }

            $clientId = trim((string) ($visionContext['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $eventType = strtolower(trim((string) ($visionEvent['event_type'] ?? '')));
            $groupKey = $plate !== '' ? 'plate:' . vg_fulcrum_slug($plate, $plate) . ':road' : 'zone:' . vg_fulcrum_slug($zone) . ':road';
            $title = str_contains($eventType, 'watchlist')
                ? 'Detection vision plaque prioritaire'
                : 'Scene routiere analysee par vision';
            $detail = trim((string) ($visionEvent['message'] ?? $visionEvent['note'] ?? $visionEvent['description'] ?? 'Evenement vision routier a arbitrer.'));
            $sourceLabel = trim((string) ($visionEvent['source_label'] ?? $visionEvent['camera_name'] ?? $visionEvent['source_id'] ?? 'IA route'));
            if ($sourceLabel !== '') {
                $detail .= ' • ' . $sourceLabel;
            }

            $events[] = vg_fulcrum_make_event([
                'source' => 'road',
                'source_id' => trim((string) ($visionEvent['id'] ?? $sourceId ?: vg_fulcrum_id('fxvisionroad'))),
                'event_type' => 'road_vision_event',
                'axis' => 'traffic',
                'group_key' => $groupKey,
                'title' => $title,
                'detail' => $detail,
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Controle routier') : '',
                'zone' => $zone,
                'severity' => $severity,
                'confidence' => str_contains($eventType, 'watchlist') ? 0.88 : 0.78,
                'requires_validation' => in_array($severity, ['critical', 'high'], true),
                'recommended_action' => 'Arbitrer la scene route avec le controle video, les plaques et les moyens terrain.',
                'source_ids' => [trim((string) ($visionEvent['id'] ?? $sourceId))],
                'signal_sources' => array_values(array_unique(array_filter(['road', $sourceType === 'drone' ? 'drone' : 'camera']))),
                'status' => (string) ($visionEvent['status'] ?? 'active'),
                'entities' => [
                    'plate' => $plate,
                    'vision_critical' => in_array($severity, ['critical', 'high'], true) ? 1 : 0,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                'created_at' => vg_fulcrum_road_timestamp($visionEvent),
                'updated_at' => vg_fulcrum_road_timestamp($visionEvent),
            ]);
        }

        foreach ($trafficPressure as $zone => $pressure) {
            $signalSources = [];
            $signalCount = 0;
            foreach ([
                'traffic' => (int) (($pressure['reports'] ?? 0) + ($pressure['incidents'] ?? 0)),
                'plate' => (int) (($pressure['plate_alerts'] ?? 0) + ($pressure['plate_verify'] ?? 0)),
                'camera' => (int) ($pressure['vision_critical'] ?? 0),
            ] as $source => $count) {
                if ($count <= 0) {
                    continue;
                }

                $signalSources[] = $source;
                $signalCount += 1;
            }

            if ($signalCount < 2) {
                continue;
            }

            $detailParts = [];
            if (($pressure['reports'] ?? 0) > 0) {
                $detailParts[] = (int) $pressure['reports'] . ' rapport(s) corridor';
            }
            if (($pressure['incidents'] ?? 0) > 0) {
                $detailParts[] = (int) $pressure['incidents'] . ' incident(s) actifs';
            }
            if (($pressure['plate_alerts'] ?? 0) > 0) {
                $detailParts[] = (int) $pressure['plate_alerts'] . ' plaque(s) en alerte';
            }
            if (($pressure['vision_critical'] ?? 0) > 0) {
                $detailParts[] = (int) $pressure['vision_critical'] . ' scene(s) vision critiques';
            }

            $events[] = vg_fulcrum_make_event([
                'source' => 'road',
                'source_id' => 'corridor-' . vg_fulcrum_slug($zone),
                'event_type' => 'road_corridor_pressure',
                'axis' => 'traffic',
                'group_key' => 'zone:' . vg_fulcrum_slug($zone) . ':road',
                'title' => 'Corridor routier sous pression fusionnee',
                'detail' => implode(' • ', $detailParts),
                'client_id' => '',
                'client_name' => '',
                'zone' => $zone,
                'severity' => (($pressure['plate_alerts'] ?? 0) > 0 && ((($pressure['reports'] ?? 0) + ($pressure['incidents'] ?? 0) + ($pressure['vision_critical'] ?? 0)) > 0)) ? 'critical' : 'high',
                'confidence' => 0.83,
                'signal_count' => max(2, $signalCount),
                'requires_validation' => true,
                'recommended_action' => 'Ouvrir le module routier, confirmer la menace et engager la doctrine corridor.',
                'source_ids' => ['corridor-' . vg_fulcrum_slug($zone)],
                'signal_sources' => $signalSources,
                'status' => 'open',
                'entities' => [
                    'traffic_reports_active' => (int) ($pressure['reports'] ?? 0),
                    'traffic_incidents_active' => (int) ($pressure['incidents'] ?? 0),
                    'plate_alerts' => (int) ($pressure['plate_alerts'] ?? 0),
                    'vision_critical' => (int) ($pressure['vision_critical'] ?? 0),
                ],
                'created_at' => vg_fulcrum_now(),
                'updated_at' => vg_fulcrum_now(),
            ]);
        }

        return $events;
    }
}

if (!function_exists('vg_fulcrum_route_for_axis')) {
    function vg_fulcrum_route_for_axis(array $event): string
    {
        $clientId = trim((string) ($event['client_id'] ?? ''));
        $axis = strtolower(trim((string) ($event['axis'] ?? $event['source'] ?? '')));

        return match ($axis) {
            'behavioral' => 'admin/behavioral-analysis.php',
            'traffic' => 'admin/road-control.php',
            'mission', 'operations', 'radio' => $clientId !== '' ? 'admin/control-center.php?client_id=' . rawurlencode($clientId) : 'admin/control-center.php',
            default => $clientId !== '' ? 'admin/control-center.php?client_id=' . rawurlencode($clientId) : 'admin/map-intelligence.php',
        };
    }
}

if (!function_exists('vg_fulcrum_make_event')) {
    function vg_fulcrum_make_event(array $payload): array
    {
        $payload = array_merge([
            'source' => 'system',
            'source_id' => '',
            'event_type' => 'signal',
            'axis' => 'operations',
            'title' => 'Signal Fulcrum',
            'detail' => '',
            'client_id' => '',
            'client_name' => '',
            'zone' => 'Kinshasa centre',
            'severity' => 'medium',
            'confidence' => 0.65,
            'signal_count' => 1,
            'requires_validation' => false,
            'validation_status' => 'pending',
            'recommended_action' => 'Consolider les signaux et confirmer la tactique humaine.',
            'source_ids' => [],
            'signal_sources' => [],
            'entities' => [],
            'status' => 'open',
            'created_at' => vg_fulcrum_now(),
            'updated_at' => vg_fulcrum_now(),
        ], $payload);

        if (!isset($payload['route']) || trim((string) $payload['route']) === '') {
            $payload['route'] = vg_fulcrum_route_for_axis($payload);
        }

        $seed = implode('|', [
            (string) ($payload['source'] ?? 'system'),
            (string) ($payload['source_id'] ?? ''),
            (string) ($payload['group_key'] ?? ''),
            (string) ($payload['client_id'] ?? ''),
            (string) ($payload['zone'] ?? ''),
        ]);
        $payload['id'] = trim((string) ($payload['id'] ?? '')) !== ''
            ? (string) $payload['id']
            : 'fxev-' . substr(sha1($seed), 0, 16);
        $payload['signal_sources'] = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['signal_sources'] ?? [$payload['source']])))));
        $payload['source_ids'] = array_values(array_unique(array_filter(array_map('strval', (array) ($payload['source_ids'] ?? [$payload['source_id']])))));
        $payload['severity'] = vg_fulcrum_normalize_level((string) ($payload['severity'] ?? 'medium'));
        $payload['confidence'] = max(0.05, min(1.0, (float) ($payload['confidence'] ?? 0.65)));
        $payload['risk_score'] = isset($payload['risk_score'])
            ? max(5, min(100, (int) $payload['risk_score']))
            : vg_fulcrum_event_risk($payload);
        $payload['priority_label'] = vg_fulcrum_priority_label((int) $payload['risk_score']);

        return $payload;
    }
}

if (!function_exists('vg_fulcrum_group_events')) {
    function vg_fulcrum_group_events(array $events, array $suppressedGroupKeys = []): array
    {
        $groups = [];
        $suppressed = array_fill_keys($suppressedGroupKeys, true);

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $groupKey = trim((string) ($event['group_key'] ?? ''));
            if ($groupKey === '' || isset($suppressed[$groupKey])) {
                continue;
            }

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = $event;
                $groups[$groupKey]['source_ids'] = array_values((array) ($event['source_ids'] ?? []));
                $groups[$groupKey]['signal_sources'] = array_values((array) ($event['signal_sources'] ?? []));
                $groups[$groupKey]['signal_count'] = max(1, (int) ($event['signal_count'] ?? 1));
                $groups[$groupKey]['title_stack'] = [$event['title'] ?? 'Signal'];
                continue;
            }

            $group = $groups[$groupKey];
            $group['source_ids'] = array_values(array_unique(array_merge(
                (array) ($group['source_ids'] ?? []),
                (array) ($event['source_ids'] ?? [])
            )));
            $group['signal_sources'] = array_values(array_unique(array_merge(
                (array) ($group['signal_sources'] ?? []),
                (array) ($event['signal_sources'] ?? [])
            )));
            $group['signal_count'] = max(1, (int) ($group['signal_count'] ?? 1)) + max(1, (int) ($event['signal_count'] ?? 1));
            $group['requires_validation'] = !empty($group['requires_validation']) || !empty($event['requires_validation']);
            $group['risk_score'] = max((int) ($group['risk_score'] ?? 0), (int) ($event['risk_score'] ?? 0));
            $group['confidence'] = max((float) ($group['confidence'] ?? 0.65), (float) ($event['confidence'] ?? 0.65));
            $group['severity'] = vg_fulcrum_severity_weight((string) ($event['severity'] ?? 'medium')) > vg_fulcrum_severity_weight((string) ($group['severity'] ?? 'medium'))
                ? (string) ($event['severity'] ?? 'medium')
                : (string) ($group['severity'] ?? 'medium');
            if (strcmp((string) ($event['updated_at'] ?? $event['created_at'] ?? ''), (string) ($group['updated_at'] ?? $group['created_at'] ?? '')) > 0) {
                $group['updated_at'] = (string) ($event['updated_at'] ?? $event['created_at'] ?? '');
                $group['detail'] = (string) ($event['detail'] ?? $group['detail'] ?? '');
                $group['source'] = (string) ($event['source'] ?? $group['source'] ?? 'system');
                $group['source_id'] = (string) ($event['source_id'] ?? $group['source_id'] ?? '');
            }

            $group['entities'] = array_merge((array) ($group['entities'] ?? []), (array) ($event['entities'] ?? []));
            $group['title_stack'][] = (string) ($event['title'] ?? 'Signal');
            $groups[$groupKey] = $group;
        }

        $grouped = [];
        foreach ($groups as $groupKey => $group) {
            $group['group_key'] = $groupKey;
            $group['id'] = 'fxev-' . substr(sha1($groupKey), 0, 16);
            $group['risk_score'] = vg_fulcrum_event_risk($group);
            $group['priority_label'] = vg_fulcrum_priority_label((int) $group['risk_score']);
            $group['title'] = max(1, (int) ($group['signal_count'] ?? 1)) > 1
                ? (string) ($group['title'] ?? 'Signal critique') . ' • fusion ' . count((array) ($group['signal_sources'] ?? [])) . ' modules'
                : (string) ($group['title'] ?? 'Signal critique');
            $group['detail'] = max(1, (int) ($group['signal_count'] ?? 1)) > 1
                ? (int) ($group['signal_count'] ?? 1) . ' signaux correles • ' . (string) ($group['detail'] ?? '')
                : (string) ($group['detail'] ?? '');
            unset($group['title_stack']);
            $grouped[] = $group;
        }

        usort($grouped, static function (array $left, array $right): int {
            $scoreCompare = ((int) ($right['risk_score'] ?? 0)) <=> ((int) ($left['risk_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        });

        return array_values($grouped);
    }
}

if (!function_exists('vg_fulcrum_collect_signals')) {
    function vg_fulcrum_collect_signals(): array
    {
        $store = vgx_store();
        $signals = [
            'clients' => array_values((array) ($store['clients'] ?? [])),
            'agents' => array_values((array) ($store['agents'] ?? [])),
            'cameras' => array_values((array) ($store['cameras'] ?? [])),
            'alerts' => array_values((array) ($store['alerts'] ?? [])),
            'interventions' => array_values((array) ($store['interventions'] ?? [])),
            'communications' => array_values((array) ($store['communications'] ?? [])),
            'call_requests' => array_values((array) ($store['call_requests'] ?? [])),
            'camera_analyses' => array_values((array) ($store['camera_analyses'] ?? [])),
            'face_events' => array_values((array) ($store['face_recognition_events'] ?? [])),
            'behavioral_reports' => array_values((array) ($store['behavioral_reports'] ?? [])),
            'traffic_reports' => array_values((array) ($store['traffic_reports'] ?? [])),
            'plate_reads' => array_values((array) ($store['plate_reads'] ?? [])),
            'vehicle_watchlist' => array_values((array) ($store['vehicle_watchlist'] ?? [])),
            'vision_events' => array_values((array) ($store['vision_events'] ?? [])),
            'traffic_incidents' => array_values((array) ($store['traffic_incidents'] ?? [])),
            'authority_vehicles' => array_values((array) ($store['authority_vehicles'] ?? [])),
            'vehicle_identities' => array_values((array) ($store['vehicle_identities'] ?? [])),
            'drones' => array_values((array) ($store['drones'] ?? [])),
            'operation_casefiles' => array_values((array) ($store['operation_casefiles'] ?? [])),
        ];

        $clientsById = vg_fulcrum_index_by_id($signals['clients']);
        $cameraById = vg_fulcrum_index_by_id($signals['cameras']);
        $events = [];

        foreach ($signals['alerts'] as $alert) {
            $alertId = trim((string) ($alert['id'] ?? ''));
            $clientId = trim((string) ($alert['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $zone = vg_fulcrum_zone_label($alert, $clientsById);
            $severity = vg_fulcrum_normalize_level((string) ($alert['priority'] ?? $alert['level'] ?? $alert['severity'] ?? 'high'));
            $events[] = vg_fulcrum_make_event([
                'source' => 'alert',
                'source_id' => $alertId,
                'event_type' => 'alert',
                'axis' => 'surveillance',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug($zone)) . ':surveillance',
                'title' => $severity === 'critical' ? 'Alerte prioritaire site' : 'Alerte a confirmer',
                'detail' => trim((string) ($alert['message'] ?? $alert['type'] ?? 'Signal terrain en attente de levee de doute.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : 'Site VIGILANCE',
                'zone' => $zone,
                'severity' => $severity,
                'confidence' => $severity === 'critical' ? 0.92 : 0.84,
                'requires_validation' => in_array($severity, ['critical', 'high'], true),
                'recommended_action' => $severity === 'critical'
                    ? 'Verifier les cameras, confirmer la levee de doute et valider le dispatch humain.'
                    : 'Confirmer l alerte avec un croisement camera, radio et supervision.',
                'source_ids' => [$alertId],
                'signal_sources' => ['alert'],
                'status' => (string) ($alert['status'] ?? 'open'),
                'entities' => [
                    'alert_id' => $alertId,
                    'camera_id' => (string) ($alert['camera_id'] ?? ''),
                    'type' => (string) ($alert['type'] ?? ''),
                ],
                'created_at' => (string) ($alert['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($alert['updated_at'] ?? $alert['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach ($signals['interventions'] as $intervention) {
            $interventionId = trim((string) ($intervention['id'] ?? ''));
            $clientId = trim((string) ($intervention['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $status = strtolower(trim((string) ($intervention['status'] ?? 'en cours')));
            $severity = in_array($status, ['escalade', 'critical', 'urgence'], true) ? 'high' : 'medium';
            $events[] = vg_fulcrum_make_event([
                'source' => 'intervention',
                'source_id' => $interventionId,
                'event_type' => 'mission',
                'axis' => 'mission',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($intervention, $clientsById))) . ':operations',
                'title' => 'Mission terrain active',
                'detail' => trim((string) ($intervention['comment'] ?? $intervention['report'] ?? 'Equipe terrain en cours de traitement.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : 'Site en intervention',
                'zone' => vg_fulcrum_zone_label($intervention, $clientsById),
                'severity' => $severity,
                'confidence' => 0.78,
                'requires_validation' => false,
                'recommended_action' => 'Suivre l avancee terrain, verifier les confirmations radio et preparer la cloture evidencee.',
                'source_ids' => [$interventionId],
                'signal_sources' => ['intervention'],
                'status' => (string) ($intervention['status'] ?? 'en cours'),
                'entities' => [
                    'intervention_id' => $interventionId,
                    'alert_id' => (string) ($intervention['alert_id'] ?? ''),
                    'agent_ids' => function_exists('vgx_intervention_agent_ids')
                        ? vgx_intervention_agent_ids($intervention)
                        : array_values(array_filter([(string) ($intervention['agent_id'] ?? '')])),
                ],
                'created_at' => (string) ($intervention['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($intervention['updated_at'] ?? $intervention['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        $latestCameraAnalyses = [];
        foreach (vg_fulcrum_sort_desc($signals['camera_analyses'], 'analyzed_at') as $analysis) {
            $cameraId = trim((string) ($analysis['camera_id'] ?? ''));
            if ($cameraId !== '' && !isset($latestCameraAnalyses[$cameraId])) {
                $latestCameraAnalyses[$cameraId] = $analysis;
            }
        }
        foreach ($latestCameraAnalyses as $cameraId => $analysis) {
            $camera = $cameraById[$cameraId] ?? null;
            $clientId = trim((string) ($analysis['client_id'] ?? $camera['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $threatScore = (float) ($analysis['threat_score'] ?? $analysis['anomaly_score'] ?? 0);
            $faces = (int) ($analysis['faces_detected'] ?? 0);
            $matches = (int) ($analysis['face_match_count'] ?? 0);
            $severity = $matches > 0 || $threatScore >= 80 ? 'high' : ($threatScore >= 50 || $faces > 0 ? 'medium' : 'low');
            if ($severity === 'low' && trim((string) ($analysis['recommendation'] ?? '')) === '') {
                continue;
            }
            $events[] = vg_fulcrum_make_event([
                'source' => 'camera',
                'source_id' => trim((string) ($analysis['id'] ?? $cameraId)),
                'event_type' => 'camera_analysis',
                'axis' => 'surveillance',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($analysis, $clientsById))) . ':surveillance',
                'title' => 'Signal video intelligent',
                'detail' => trim((string) ($analysis['recommendation'] ?? $analysis['summary'] ?? 'Activite camera a consolider.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : (string) ($analysis['client_name'] ?? 'Camera VIGILANCE'),
                'zone' => vg_fulcrum_zone_label($analysis + (is_array($camera) ? $camera : []), $clientsById),
                'severity' => $severity,
                'confidence' => $matches > 0 ? 0.9 : 0.76,
                'requires_validation' => $matches > 0 || $threatScore >= 80,
                'recommended_action' => 'Valider la levee de doute avec supervision humaine et eventuellement capture IA.',
                'source_ids' => [trim((string) ($analysis['id'] ?? $cameraId))],
                'signal_sources' => ['camera'],
                'status' => (string) ($analysis['status'] ?? 'analyzed'),
                'entities' => [
                    'camera_id' => $cameraId,
                    'faces_detected' => $faces,
                    'match_count' => $matches,
                    'threat_score' => $threatScore,
                ],
                'created_at' => (string) ($analysis['analyzed_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($analysis['analyzed_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_sort_desc($signals['face_events'], 'captured_at') as $faceEvent) {
            $matchCount = (int) ($faceEvent['match_count'] ?? 0);
            if ($matchCount <= 0) {
                continue;
            }
            $clientId = trim((string) ($faceEvent['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $severity = vg_fulcrum_normalize_level((string) ($faceEvent['status'] ?? 'critical-match'));
            $events[] = vg_fulcrum_make_event([
                'source' => 'face',
                'source_id' => trim((string) ($faceEvent['id'] ?? '')),
                'event_type' => 'identity_match',
                'axis' => 'surveillance',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($faceEvent, $clientsById))) . ':surveillance',
                'title' => $severity === 'critical' ? 'Correspondance faciale critique' : 'Correspondance faciale a confirmer',
                'detail' => trim((string) ($faceEvent['camera_name'] ?? 'Camera VIGILANCE')) . ' • ' . $matchCount . ' match(es) remonte(s).',
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : (string) ($faceEvent['client_name'] ?? 'Site VIGILANCE'),
                'zone' => vg_fulcrum_zone_label($faceEvent, $clientsById),
                'severity' => $severity,
                'confidence' => 0.94,
                'requires_validation' => true,
                'recommended_action' => 'Confirmer humainement l identite, verrouiller la trace et activer la chaine OPS.',
                'source_ids' => [trim((string) ($faceEvent['id'] ?? ''))],
                'signal_sources' => ['face'],
                'status' => (string) ($faceEvent['status'] ?? 'critical-match'),
                'entities' => [
                    'camera_id' => (string) ($faceEvent['camera_id'] ?? ''),
                    'match_count' => $matchCount,
                    'faces_detected' => (int) ($faceEvent['faces_detected'] ?? 0),
                ],
                'created_at' => (string) ($faceEvent['captured_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($faceEvent['captured_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_sort_desc($signals['behavioral_reports'], 'updated_at') as $report) {
            $payload = is_array($report['report_payload'] ?? null) ? $report['report_payload'] : [];
            $clientId = trim((string) ($report['client_id'] ?? $payload['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $behavioralScore = max(
                (float) ($report['stress_score'] ?? 0),
                (float) ($payload['stress_score'] ?? 0),
                (float) ($payload['multimodal']['deception_score'] ?? 0),
                100 - (float) ($report['coherence_score'] ?? $payload['coherence_score'] ?? 100)
            );
            if ($behavioralScore < 45) {
                continue;
            }
            $severity = $behavioralScore >= 80 ? 'critical' : ($behavioralScore >= 65 ? 'high' : 'medium');
            $events[] = vg_fulcrum_make_event([
                'source' => 'behavioral',
                'source_id' => trim((string) ($report['id'] ?? $report['session_id'] ?? '')),
                'event_type' => 'behavioral_assessment',
                'axis' => 'behavioral',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($report, $clientsById))) . ':surveillance',
                'title' => 'Lecture comportementale renforcee',
                'detail' => trim((string) ($payload['strict_logic']['summary'] ?? $payload['recommendations'][0] ?? $report['report_status'] ?? 'Indices multimodaux a consolider.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Sujet VIGILANCE') : 'Sujet VIGILANCE',
                'zone' => vg_fulcrum_zone_label($report, $clientsById),
                'severity' => $severity,
                'confidence' => 0.86,
                'requires_validation' => true,
                'recommended_action' => 'Activer un examen superviseur video/audio et croiser avec les autres modules IA.',
                'source_ids' => [trim((string) ($report['id'] ?? $report['session_id'] ?? ''))],
                'signal_sources' => ['behavioral'],
                'status' => (string) ($report['report_status'] ?? 'en analyse'),
                'entities' => [
                    'session_id' => (string) ($report['session_id'] ?? ''),
                    'behavioral_score' => (int) round($behavioralScore),
                    'stress_score' => (int) round((float) ($report['stress_score'] ?? $payload['stress_score'] ?? 0)),
                    'coherence_score' => (int) round((float) ($report['coherence_score'] ?? $payload['coherence_score'] ?? 0)),
                ],
                'created_at' => (string) ($report['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($report['updated_at'] ?? $report['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_sort_desc($signals['traffic_incidents'], 'updated_at') as $incident) {
            $status = strtolower(trim((string) ($incident['status'] ?? 'detecte')));
            if (in_array($status, ['resolved', 'resolu'], true)) {
                continue;
            }
            $severity = vg_fulcrum_normalize_level((string) ($incident['severity'] ?? $incident['priority'] ?? $status));
            $zone = vg_fulcrum_zone_label($incident, $clientsById);
            $events[] = vg_fulcrum_make_event([
                'source' => 'traffic',
                'source_id' => trim((string) ($incident['id'] ?? '')),
                'event_type' => 'traffic_incident',
                'axis' => 'traffic',
                'group_key' => 'zone:' . vg_fulcrum_slug($zone) . ':road',
                'title' => 'Incident routier critique',
                'detail' => trim((string) ($incident['type'] ?? 'Incident routier')) . ' • ' . trim((string) ($incident['police_notes'] ?? $incident['description'] ?? 'Coordination tactique requise.')),
                'client_id' => trim((string) ($incident['client_id'] ?? '')),
                'client_name' => trim((string) ($incident['client_name'] ?? '')),
                'zone' => $zone,
                'severity' => $severity,
                'confidence' => 0.74,
                'requires_validation' => in_array($severity, ['critical', 'high'], true),
                'recommended_action' => 'Synchroniser route, camera, radio et si possible drone sur cet axe.',
                'source_ids' => [trim((string) ($incident['id'] ?? ''))],
                'signal_sources' => ['traffic'],
                'status' => (string) ($incident['status'] ?? 'detecte'),
                'entities' => [
                    'incident_id' => (string) ($incident['id'] ?? ''),
                    'plate' => (string) ($incident['plate'] ?? ''),
                ],
                'created_at' => (string) ($incident['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($incident['updated_at'] ?? $incident['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_collect_road_events($signals, $clientsById, $cameraById) as $roadEvent) {
            if (is_array($roadEvent)) {
                $events[] = $roadEvent;
            }
        }

        foreach (vg_fulcrum_sort_desc($signals['communications'], 'created_at') as $communication) {
            $urgency = strtolower(trim((string) ($communication['urgency'] ?? '')));
            $ackStatus = strtolower(trim((string) ($communication['ack_status'] ?? $communication['status'] ?? '')));
            if (!in_array($urgency, ['high', 'critical', 'urgent'], true) && !in_array($ackStatus, ['pending', 'en attente'], true)) {
                continue;
            }
            $clientId = trim((string) ($communication['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $severity = in_array($urgency, ['critical', 'urgent'], true) ? 'high' : 'medium';
            $events[] = vg_fulcrum_make_event([
                'source' => 'radio',
                'source_id' => trim((string) ($communication['id'] ?? '')),
                'event_type' => 'radio_message',
                'axis' => 'radio',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($communication, $clientsById))) . ':operations',
                'title' => 'Message radio a traiter',
                'detail' => trim((string) ($communication['subject'] ?? 'Message OPS')) . ' • ' . trim((string) ($communication['message'] ?? 'ACK en attente.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'OPS') : (string) ($communication['client_name'] ?? 'OPS'),
                'zone' => vg_fulcrum_zone_label($communication, $clientsById),
                'severity' => $severity,
                'confidence' => 0.68,
                'requires_validation' => false,
                'recommended_action' => 'Confirmer la reception, qualifier l urgence et archiver la chaine radio.',
                'source_ids' => [trim((string) ($communication['id'] ?? ''))],
                'signal_sources' => ['radio'],
                'status' => (string) ($communication['ack_status'] ?? $communication['status'] ?? 'pending'),
                'entities' => [
                    'channel' => (string) ($communication['channel'] ?? ''),
                    'urgency' => (string) ($communication['urgency'] ?? ''),
                ],
                'created_at' => (string) ($communication['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($communication['updated_at'] ?? $communication['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_sort_desc($signals['call_requests'], 'created_at') as $call) {
            $status = strtolower(trim((string) ($call['status'] ?? 'new')));
            if (!in_array($status, ['new', 'open', 'urgent', 'pending'], true)) {
                continue;
            }
            $clientId = trim((string) ($call['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $events[] = vg_fulcrum_make_event([
                'source' => 'call',
                'source_id' => trim((string) ($call['id'] ?? '')),
                'event_type' => 'client_call',
                'axis' => 'operations',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($call, $clientsById))) . ':surveillance',
                'title' => 'Demande client en attente',
                'detail' => trim((string) ($call['reason'] ?? $call['message'] ?? 'Rappel terrain souhaite.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : 'Abonne VIGILANCE',
                'zone' => vg_fulcrum_zone_label($call, $clientsById),
                'severity' => 'medium',
                'confidence' => 0.7,
                'requires_validation' => false,
                'recommended_action' => 'Rappeler le client et verifier le lien avec les signaux en cours.',
                'source_ids' => [trim((string) ($call['id'] ?? ''))],
                'signal_sources' => ['call'],
                'status' => (string) ($call['status'] ?? 'new'),
                'entities' => [
                    'phone' => (string) ($call['phone'] ?? ''),
                ],
                'created_at' => (string) ($call['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($call['updated_at'] ?? $call['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        foreach (vg_fulcrum_sort_desc($signals['operation_casefiles'], 'updated_at') as $casefile) {
            $status = strtolower(trim((string) ($casefile['status'] ?? '')));
            $priority = strtolower(trim((string) ($casefile['priority'] ?? '')));
            if (in_array($status, ['archive', 'archived', 'closed'], true) || !in_array($priority, ['haute', 'high', 'critique', 'critical'], true)) {
                continue;
            }
            $clientId = trim((string) ($casefile['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $events[] = vg_fulcrum_make_event([
                'source' => 'casefile',
                'source_id' => trim((string) ($casefile['id'] ?? '')),
                'event_type' => 'casefile',
                'axis' => 'operations',
                'group_key' => 'client:' . ($clientId !== '' ? $clientId : vg_fulcrum_slug(vg_fulcrum_zone_label($casefile, $clientsById))) . ':operations',
                'title' => 'Dossier operationnel prioritaire',
                'detail' => trim((string) ($casefile['summary'] ?? $casefile['case_code'] ?? 'Dossier tactique a suivre.')),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Site VIGILANCE') : 'Site VIGILANCE',
                'zone' => vg_fulcrum_zone_label($casefile, $clientsById),
                'severity' => $priority === 'critical' || $priority === 'critique' ? 'critical' : 'high',
                'confidence' => 0.73,
                'requires_validation' => true,
                'recommended_action' => 'Maintenir un suivi centralise et verrouiller les validations humaines sensibles.',
                'source_ids' => [trim((string) ($casefile['id'] ?? ''))],
                'signal_sources' => ['casefile'],
                'status' => (string) ($casefile['status'] ?? 'open'),
                'entities' => [
                    'casefile_id' => (string) ($casefile['id'] ?? ''),
                    'case_code' => (string) ($casefile['case_code'] ?? ''),
                ],
                'created_at' => (string) ($casefile['created_at'] ?? vg_fulcrum_now()),
                'updated_at' => (string) ($casefile['updated_at'] ?? $casefile['created_at'] ?? vg_fulcrum_now()),
            ]);
        }

        $kpis = vg_fulcrum_build_kpis($signals, $events);
        if (($kpis['cameras_total'] ?? 0) > 0 && ($kpis['camera_coverage_pct'] ?? 100) < 50) {
            $events[] = vg_fulcrum_make_event([
                'source' => 'system',
                'source_id' => 'camera-coverage',
                'event_type' => 'coverage',
                'axis' => 'surveillance',
                'group_key' => 'system:coverage',
                'title' => 'Maillage camera degrade',
                'detail' => 'Le ratio de cameras utiles est sous le seuil cible. Le coeur surveillance doit etre requalifie.',
                'severity' => 'high',
                'confidence' => 0.8,
                'requires_validation' => true,
                'recommended_action' => 'Prioriser la remise en ligne des flux et valider les sites aveugles.',
                'source_ids' => ['camera-coverage'],
                'signal_sources' => ['camera', 'system'],
                'status' => 'open',
                'entities' => [
                    'camera_coverage_pct' => (int) ($kpis['camera_coverage_pct'] ?? 0),
                ],
            ]);
        }

        if (($kpis['alerts_open'] ?? 0) > 0 && ($kpis['agents_available'] ?? 0) === 0 && ($kpis['agents_total'] ?? 0) > 0) {
            $events[] = vg_fulcrum_make_event([
                'source' => 'system',
                'source_id' => 'agent-readiness',
                'event_type' => 'coverage',
                'axis' => 'mission',
                'group_key' => 'system:dispatch',
                'title' => 'Couverture terrain sous pression',
                'detail' => 'Des alertes restent ouvertes alors qu aucun agent disponible n est detecte.',
                'severity' => 'critical',
                'confidence' => 0.88,
                'requires_validation' => true,
                'recommended_action' => 'Reallouer immediatement les equipes ou confirmer une posture de renfort.',
                'source_ids' => ['agent-readiness'],
                'signal_sources' => ['intervention', 'system'],
                'status' => 'open',
                'entities' => [
                    'agents_available' => (int) ($kpis['agents_available'] ?? 0),
                    'alerts_open' => (int) ($kpis['alerts_open'] ?? 0),
                ],
            ]);
        }

        return [$signals, $events];
    }
}

if (!function_exists('vg_fulcrum_build_mission_links')) {
    function vg_fulcrum_build_mission_links(array $signals, array $events): array
    {
        $clientsById = vg_fulcrum_index_by_id((array) ($signals['clients'] ?? []));
        $rows = [];

        foreach ((array) ($signals['interventions'] ?? []) as $intervention) {
            $clientId = trim((string) ($intervention['client_id'] ?? ''));
            $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;
            $relatedEventIds = [];
            $priority = 'P4';
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                if ($clientId !== '' && (string) ($event['client_id'] ?? '') !== $clientId) {
                    continue;
                }
                $relatedEventIds[] = (string) ($event['id'] ?? '');
                if (strcmp((string) ($event['priority_label'] ?? 'P4'), $priority) < 0) {
                    $priority = (string) ($event['priority_label'] ?? 'P4');
                }
            }

            $interventionId = trim((string) ($intervention['id'] ?? vg_fulcrum_id('fxmission')));
            $rows[] = [
                'id' => 'mission-' . vg_fulcrum_slug($interventionId, 'current'),
                'client_id' => $clientId,
                'client_name' => is_array($client) ? (string) ($client['name'] ?? $client['full_name'] ?? 'Abonne VIGILANCE') : 'Abonne VIGILANCE',
                'zone' => vg_fulcrum_zone_label($intervention, $clientsById),
                'alert_id' => (string) ($intervention['alert_id'] ?? ''),
                'intervention_id' => $interventionId,
                'status' => (string) ($intervention['status'] ?? 'en cours'),
                'priority_label' => $priority,
                'agent_ids' => function_exists('vgx_intervention_agent_ids')
                    ? vgx_intervention_agent_ids($intervention)
                    : array_values(array_filter([(string) ($intervention['agent_id'] ?? '')])),
                'source_event_ids' => array_values(array_filter($relatedEventIds)),
                'payload' => [
                    'comment' => (string) ($intervention['comment'] ?? ''),
                    'report' => (string) ($intervention['report'] ?? ''),
                    'signal_count' => count(array_filter($relatedEventIds)),
                ],
                'updated_at' => (string) ($intervention['updated_at'] ?? $intervention['created_at'] ?? vg_fulcrum_now()),
            ];
        }

        $roadMissionKeys = [];
        foreach ($events as $event) {
            if (!is_array($event) || (string) ($event['axis'] ?? '') !== 'traffic') {
                continue;
            }
            if ((int) ($event['risk_score'] ?? 0) < 72) {
                continue;
            }

            $groupKey = trim((string) ($event['group_key'] ?? $event['id'] ?? ''));
            if ($groupKey === '' || isset($roadMissionKeys[$groupKey])) {
                continue;
            }

            $roadMissionKeys[$groupKey] = true;
            $rows[] = [
                'id' => 'mission-road-' . substr(sha1($groupKey), 0, 12),
                'client_id' => (string) ($event['client_id'] ?? ''),
                'client_name' => trim((string) ($event['client_name'] ?? '')) !== '' ? (string) ($event['client_name'] ?? '') : 'Controle routier VIGILANCE',
                'zone' => (string) ($event['zone'] ?? 'Zone a confirmer'),
                'alert_id' => '',
                'intervention_id' => '',
                'status' => !empty($event['requires_validation']) ? 'a valider' : 'pre-positionner',
                'priority_label' => (string) ($event['priority_label'] ?? 'P3'),
                'agent_ids' => [],
                'source_event_ids' => array_values(array_filter([(string) ($event['id'] ?? '')])),
                'payload' => [
                    'doctrine' => 'road-corridor',
                    'route' => (string) ($event['route'] ?? 'admin/road-control.php'),
                    'recommended_action' => (string) ($event['recommended_action'] ?? ''),
                    'signal_count' => (int) ($event['signal_count'] ?? 1),
                ],
                'updated_at' => (string) ($event['updated_at'] ?? $event['created_at'] ?? vg_fulcrum_now()),
            ];
        }

        return vg_fulcrum_sort_desc($rows);
    }
}

if (!function_exists('vg_fulcrum_build_recommendations')) {
    function vg_fulcrum_build_recommendations(array $events, array $signals, array $zoneStats, array $existingRecommendations = []): array
    {
        $suppressed = array_fill_keys(vg_fulcrum_suppressions()['recommendation_fingerprints'] ?? [], true);
        $rows = [];
        $now = vg_fulcrum_now();

        foreach (array_slice($events, 0, 6) as $event) {
            $fingerprint = 'event:' . (string) ($event['group_key'] ?? $event['id'] ?? vg_fulcrum_id('fxrec'));
            if (isset($suppressed[$fingerprint])) {
                continue;
            }

            $existing = $existingRecommendations[$fingerprint] ?? null;
            $route = (string) ($event['route'] ?? vg_fulcrum_route_for_axis($event));
            $linkedEntities = (array) ($event['entities'] ?? []);
            $title = match ((string) ($event['axis'] ?? 'operations')) {
                'behavioral' => 'Conduire une validation multimodale supervisee',
                'traffic' => 'Arbitrer un corridor routier multi-capteurs',
                'mission' => 'Recalibrer la chaine missionnelle en temps reel',
                default => 'Verrouiller une levee de doute centralisee',
            };
            $actionLabel = match ((string) ($event['axis'] ?? 'operations')) {
                'behavioral' => 'Ouvrir analyse comportementale',
                'traffic' => 'Ouvrir controle routier',
                'mission', 'radio' => 'Ouvrir centre OPS',
                default => 'Validation humaine',
            };
            $summary = trim((string) ($event['title'] ?? 'Signal critique')) . ' • ' . trim((string) ($event['detail'] ?? 'Signal a analyser.'));
            $rationale = 'Fusion ' . implode(', ', array_values((array) ($event['signal_sources'] ?? [])))
                . ' • score ' . (int) ($event['risk_score'] ?? 0)
                . ' • confiance ' . vg_fulcrum_confidence_label((float) ($event['confidence'] ?? 0.65)) . '.';

            $row = [
                'id' => is_array($existing) ? (string) ($existing['id'] ?? '') : '',
                'fingerprint' => $fingerprint,
                'category' => (string) ($event['axis'] ?? 'operations'),
                'title' => $title,
                'summary' => $summary,
                'severity' => (string) ($event['severity'] ?? 'medium'),
                'priority_label' => (string) ($event['priority_label'] ?? 'P3'),
                'risk_score' => (int) ($event['risk_score'] ?? 0),
                'confidence' => (float) ($event['confidence'] ?? 0.65),
                'zone' => (string) ($event['zone'] ?? ''),
                'client_id' => (string) ($event['client_id'] ?? ''),
                'source_event_id' => (string) ($event['id'] ?? ''),
                'action_label' => $actionLabel,
                'action_route' => $route,
                'rationale' => $rationale,
                'requires_validation' => !empty($event['requires_validation']) || (int) ($event['risk_score'] ?? 0) >= 75,
                'validation_status' => is_array($existing) ? (string) ($existing['validation_status'] ?? 'pending') : 'pending',
                'validated_by_id' => is_array($existing) ? (string) ($existing['validated_by_id'] ?? '') : '',
                'validated_by_name' => is_array($existing) ? (string) ($existing['validated_by_name'] ?? '') : '',
                'validation_note' => is_array($existing) ? (string) ($existing['validation_note'] ?? '') : '',
                'linked_entities' => $linkedEntities,
                'created_at' => is_array($existing) ? (string) ($existing['created_at'] ?? $now) : $now,
                'updated_at' => $now,
            ];
            $rows[] = $row;
        }

        $kpis = vg_fulcrum_build_kpis($signals, $events);
        $topRoadZone = null;
        foreach ($zoneStats as $zoneRow) {
            if (!is_array($zoneRow)) {
                continue;
            }

            $metrics = (array) ($zoneRow['metrics'] ?? []);
            $corridorPressure = (int) ($metrics['corridor_pressure'] ?? 0);
            if ($corridorPressure > 0 || (int) ($metrics['plate_alerts'] ?? 0) > 0 || (int) ($metrics['traffic_incidents_active'] ?? 0) > 0) {
                $topRoadZone = $zoneRow;
                break;
            }
        }
        $systemRules = [];
        if (($kpis['camera_coverage_pct'] ?? 100) < 50) {
            $systemRules[] = [
                'fingerprint' => 'system:camera-grid',
                'category' => 'surveillance',
                'title' => 'Requalifier la grille camera critique',
                'summary' => 'La couverture camera exploitable est sous le seuil cible. Fulcrum recommande une priorite technique immediate.',
                'severity' => 'high',
                'priority_label' => 'P2',
                'risk_score' => 78,
                'confidence' => 0.82,
                'zone' => '',
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir reseau cameras',
                'action_route' => 'admin/cameras.php',
                'rationale' => 'Les flux cameras alimentent la surveillance coeur du systeme. Une degradation de reseau affaiblit toute la logique de fusion.',
                'requires_validation' => true,
                'linked_entities' => ['camera_coverage_pct' => (int) ($kpis['camera_coverage_pct'] ?? 0)],
            ];
        }
        if (($kpis['road_plate_alerts'] ?? 0) > 0) {
            $systemRules[] = [
                'fingerprint' => 'system:road-intercept',
                'category' => 'traffic',
                'title' => 'Arbitrer une interception routiere supervisee',
                'summary' => 'Des plaques sensibles ou watchlist sont remontees dans la couche routiere. Fulcrum recommande une levee de doute renforcee.',
                'severity' => 'critical',
                'priority_label' => 'P1',
                'risk_score' => min(98, 84 + ((int) ($kpis['road_plate_alerts'] ?? 0) * 3)),
                'confidence' => 0.91,
                'zone' => (string) ($topRoadZone['zone'] ?? ''),
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir controle routier',
                'action_route' => 'admin/road-control.php',
                'rationale' => 'La fusion plaque, video et doctrine terrain impose une validation humaine avant tout engagement critique sur corridor.',
                'requires_validation' => true,
                'linked_entities' => [
                    'road_plate_alerts' => (int) ($kpis['road_plate_alerts'] ?? 0),
                    'road_watchlist_active' => (int) ($kpis['road_watchlist_active'] ?? 0),
                ],
            ];
        }
        if ((($kpis['traffic_reports_active'] ?? 0) > 0 || ($kpis['road_vision_critical'] ?? 0) > 0) && ($kpis['road_cameras_ready'] ?? 0) === 0) {
            $systemRules[] = [
                'fingerprint' => 'system:road-camera-gap',
                'category' => 'traffic',
                'title' => 'Refermer un angle mort video sur corridor actif',
                'summary' => 'La pression routiere augmente alors que la couche camera rattachee au corridor n est pas suffisamment qualifiee.',
                'severity' => 'high',
                'priority_label' => 'P2',
                'risk_score' => 79,
                'confidence' => 0.83,
                'zone' => (string) ($topRoadZone['zone'] ?? ''),
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir controle routier',
                'action_route' => 'admin/road-control.php',
                'rationale' => 'Un corridor sous charge sans regard camera exploitable degrade la confiance de decision et le suivi de trajectoire.',
                'requires_validation' => true,
                'linked_entities' => [
                    'traffic_reports_active' => (int) ($kpis['traffic_reports_active'] ?? 0),
                    'road_vision_critical' => (int) ($kpis['road_vision_critical'] ?? 0),
                    'road_cameras_ready' => (int) ($kpis['road_cameras_ready'] ?? 0),
                ],
            ];
        }
        if (($kpis['alerts_open'] ?? 0) > 0 && ($kpis['agents_available'] ?? 0) === 0 && ($kpis['agents_total'] ?? 0) > 0) {
            $systemRules[] = [
                'fingerprint' => 'system:terrain-pressure',
                'category' => 'mission',
                'title' => 'Arbitrer la couverture terrain sous tension',
                'summary' => 'Alerte(s) ouverte(s) avec indisponibilite des agents detectee. Une validation directionnelle est requise.',
                'severity' => 'critical',
                'priority_label' => 'P1',
                'risk_score' => 92,
                'confidence' => 0.9,
                'zone' => '',
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir centre OPS',
                'action_route' => 'admin/control-center.php',
                'rationale' => 'Le moteur detecte un desequilibre entre charge d alerte et ressources humaines disponibles.',
                'requires_validation' => true,
                'linked_entities' => [
                    'alerts_open' => (int) ($kpis['alerts_open'] ?? 0),
                    'agents_available' => (int) ($kpis['agents_available'] ?? 0),
                ],
            ];
        }
        if (is_array($topRoadZone) && (int) (($topRoadZone['metrics']['corridor_pressure'] ?? 0)) >= 2) {
            $systemRules[] = [
                'fingerprint' => 'system:road-corridor',
                'category' => 'traffic',
                'title' => 'Stabiliser le corridor routier critique',
                'summary' => 'Une zone concentre plusieurs signaux route, plaque et vision. Une doctrine corridor doit etre pilotee depuis Fulcrum.',
                'severity' => (int) ($topRoadZone['risk_score'] ?? 0) >= 85 ? 'critical' : 'high',
                'priority_label' => (string) ((int) ($topRoadZone['risk_score'] ?? 0) >= 85 ? 'P1' : 'P2'),
                'risk_score' => max(78, (int) ($topRoadZone['risk_score'] ?? 0)),
                'confidence' => 0.87,
                'zone' => (string) ($topRoadZone['zone'] ?? ''),
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir controle routier',
                'action_route' => 'admin/road-control.php',
                'rationale' => 'Fulcrum considere la couche routiere comme un pilier de surveillance active, de trajectoire et de confiance missionnelle.',
                'requires_validation' => true,
                'linked_entities' => (array) ($topRoadZone['metrics'] ?? []),
            ];
        }
        if (($kpis['behavioral_high_risk'] ?? 0) > 0 && ($kpis['face_critical'] ?? 0) > 0) {
            $systemRules[] = [
                'fingerprint' => 'system:multimodal-trust',
                'category' => 'behavioral',
                'title' => 'Lancer une lecture confiance video + audio + identite',
                'summary' => 'Des indices comportementaux eleves et des correspondances faciales coexistent. Une validation humaine renforcee est recommandee.',
                'severity' => 'critical',
                'priority_label' => 'P1',
                'risk_score' => 94,
                'confidence' => 0.92,
                'zone' => '',
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir analyse comportementale',
                'action_route' => 'admin/behavioral-analysis.php',
                'rationale' => 'Fulcrum privilegie la convergence micro, video, logique et trace operationnelle pour fiabiliser la decision.',
                'requires_validation' => true,
                'linked_entities' => [
                    'behavioral_high_risk' => (int) ($kpis['behavioral_high_risk'] ?? 0),
                    'face_critical' => (int) ($kpis['face_critical'] ?? 0),
                ],
            ];
        }
        if (($kpis['drones_active'] ?? 0) > 0 && !empty($zoneStats) && (int) ($zoneStats[0]['risk_score'] ?? 0) >= 75) {
            $systemRules[] = [
                'fingerprint' => 'system:drone-overwatch',
                'category' => 'surveillance',
                'title' => 'Positionner une surveillance aerienne sur zone chaude',
                'summary' => 'La zone la plus a risque peut beneficier d une couverture aerienne coordonnee avec les flux OPS.',
                'severity' => 'high',
                'priority_label' => 'P2',
                'risk_score' => max(76, (int) ($zoneStats[0]['risk_score'] ?? 0)),
                'confidence' => 0.79,
                'zone' => (string) ($zoneStats[0]['zone'] ?? ''),
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir flotte drones',
                'action_route' => 'admin/drones.php',
                'rationale' => 'Les drones doivent renforcer la confiance de situation, pas seulement produire une presence visuelle.',
                'requires_validation' => true,
                'linked_entities' => ['zone' => (string) ($zoneStats[0]['zone'] ?? '')],
            ];
        }

        foreach ($systemRules as $rule) {
            if (isset($suppressed[$rule['fingerprint']])) {
                continue;
            }

            $existing = $existingRecommendations[$rule['fingerprint']] ?? null;
            $rows[] = array_merge($rule, [
                'id' => is_array($existing) ? (string) ($existing['id'] ?? '') : '',
                'validation_status' => is_array($existing) ? (string) ($existing['validation_status'] ?? 'pending') : 'pending',
                'validated_by_id' => is_array($existing) ? (string) ($existing['validated_by_id'] ?? '') : '',
                'validated_by_name' => is_array($existing) ? (string) ($existing['validated_by_name'] ?? '') : '',
                'validation_note' => is_array($existing) ? (string) ($existing['validation_note'] ?? '') : '',
                'created_at' => is_array($existing) ? (string) ($existing['created_at'] ?? $now) : $now,
                'updated_at' => $now,
            ]);
        }

        if ($rows === []) {
            $rows[] = [
                'id' => '',
                'fingerprint' => 'system:nominal',
                'category' => 'operations',
                'title' => 'Maintenir la veille nominale Fulcrum',
                'summary' => 'Aucun faisceau critique ne justifie une escalation immediate. La logique de fusion reste active.',
                'severity' => 'low',
                'priority_label' => 'P4',
                'risk_score' => 24,
                'confidence' => 0.7,
                'zone' => '',
                'client_id' => '',
                'source_event_id' => '',
                'action_label' => 'Ouvrir carte intelligence',
                'action_route' => 'admin/map-intelligence.php',
                'rationale' => 'Le moteur garde la surveillance, l audit et les correlations disponibles sans forcer une action.',
                'requires_validation' => false,
                'validation_status' => 'nominal',
                'validated_by_id' => '',
                'validated_by_name' => '',
                'validation_note' => '',
                'linked_entities' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rows as $index => $row) {
            if (trim((string) ($row['id'] ?? '')) === '') {
                $rows[$index]['id'] = 'fxrec-' . substr(sha1((string) ($row['fingerprint'] ?? $index)), 0, 16);
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $priorityCompare = strcmp((string) ($left['priority_label'] ?? 'P4'), (string) ($right['priority_label'] ?? 'P4'));
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return ((int) ($right['risk_score'] ?? 0)) <=> ((int) ($left['risk_score'] ?? 0));
        });

        return array_values($rows);
    }
}

if (!function_exists('vg_fulcrum_build_risk_scores')) {
    function vg_fulcrum_build_risk_scores(array $events, array $signals, array $zoneStats, array $kpis, array $recommendations): array
    {
        $criticalEvents = count(array_filter($events, static fn(array $event): bool => (int) ($event['risk_score'] ?? 0) >= 85));
        $hotZones = count(array_filter($zoneStats, static fn(array $row): bool => (int) ($row['risk_score'] ?? 0) >= 72));
        $roadHotZones = count(array_filter($zoneStats, static function (array $row): bool {
            $metrics = (array) ($row['metrics'] ?? []);
            return ((int) ($metrics['corridor_pressure'] ?? 0)) > 0;
        }));
        $pendingValidation = count(array_filter($recommendations, static function (array $row): bool {
            return !empty($row['requires_validation']) && in_array((string) ($row['validation_status'] ?? 'pending'), ['pending', 'standby', 'dismissed'], true);
        }));

        $avgTopRisk = 0;
        if ($events !== []) {
            $top = array_slice(array_map(static fn(array $event): int => (int) ($event['risk_score'] ?? 0), $events), 0, 5);
            $avgTopRisk = (int) round(array_sum($top) / max(1, count($top)));
        }

        $score = $avgTopRisk;
        $score += $criticalEvents * 6;
        $score += $hotZones * 5;
        $score += min(18, (int) ($kpis['road_plate_alerts'] ?? 0) * 4);
        $score += min(12, (((int) ($kpis['traffic_reports_active'] ?? 0)) + ((int) ($kpis['road_vision_critical'] ?? 0))) * 2);
        $score += min(10, max(0, $roadHotZones - 1) * 2);
        $score += (int) round(max(0, 100 - (int) ($kpis['camera_coverage_pct'] ?? 100)) * 0.16);
        $score += (int) round(max(0, 100 - (int) ($kpis['agent_readiness_pct'] ?? 100)) * 0.18);
        $score += min(18, $pendingValidation * 3);
        $score = max(12, min(100, $score));

        $rows = [[
            'id' => 'risk-global',
            'scope' => 'global',
            'scope_id' => 'fulcrum',
            'label' => 'Posture globale Fulcrum',
            'zone' => '',
            'risk_score' => $score,
            'risk_band' => vg_fulcrum_risk_band($score),
            'priority_label' => vg_fulcrum_priority_label($score),
            'confidence' => max(0.45, min(0.98, ((int) ($kpis['intelligence_confidence'] ?? 65)) / 100)),
            'status' => 'active',
            'metrics' => array_merge($kpis, [
                'critical_events' => $criticalEvents,
                'hot_zones' => $hotZones,
                'road_hot_zones' => $roadHotZones,
                'pending_validation' => $pendingValidation,
                'events_total' => count($events),
                'recommendations_total' => count($recommendations),
            ]),
            'computed_at' => vg_fulcrum_now(),
            'updated_at' => vg_fulcrum_now(),
        ]];

        foreach (array_slice($zoneStats, 0, 8) as $zone) {
            $rows[] = [
                'id' => 'risk-zone-' . vg_fulcrum_slug((string) ($zone['zone'] ?? 'zone')),
                'scope' => 'zone',
                'scope_id' => (string) ($zone['zone'] ?? ''),
                'label' => 'Zone ' . (string) ($zone['label'] ?? $zone['zone'] ?? 'OPS'),
                'zone' => (string) ($zone['zone'] ?? ''),
                'risk_score' => (int) ($zone['risk_score'] ?? 0),
                'risk_band' => (string) ($zone['risk_band'] ?? vg_fulcrum_risk_band((int) ($zone['risk_score'] ?? 0))),
                'priority_label' => vg_fulcrum_priority_label((int) ($zone['risk_score'] ?? 0)),
                'confidence' => 0.78,
                'status' => 'active',
                'metrics' => (array) ($zone['metrics'] ?? []),
                'computed_at' => vg_fulcrum_now(),
                'updated_at' => vg_fulcrum_now(),
            ];
        }

        return $rows;
    }
}

if (!function_exists('vg_fulcrum_store_snapshot')) {
    function vg_fulcrum_store_snapshot(?array $store = null): array
    {
        $store = is_array($store) ? $store : vgx_store();
        $events = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_events'] ?? [])));
        $riskScores = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_risk_scores'] ?? [])), 'computed_at');
        $recommendations = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_recommendations'] ?? [])));
        $zoneStats = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_zone_stats'] ?? [])));
        $auditLogs = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_audit_logs'] ?? [])), 'created_at');
        $aiDecisions = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_ai_decisions'] ?? [])), 'created_at');
        $missionLinks = vg_fulcrum_sort_desc(array_values((array) ($store['fulcrum_missions_links'] ?? [])));

        $global = null;
        foreach ($riskScores as $row) {
            if ((string) ($row['scope'] ?? '') === 'global') {
                $global = $row;
                break;
            }
        }

        return [
            'generated_at' => (string) ($global['computed_at'] ?? $events[0]['updated_at'] ?? vg_fulcrum_now()),
            'events' => $events,
            'risk_scores' => $riskScores,
            'recommendations' => $recommendations,
            'zone_stats' => $zoneStats,
            'audit_logs' => $auditLogs,
            'ai_decisions' => $aiDecisions,
            'mission_links' => $missionLinks,
            'global_posture' => $global,
            'kpis' => is_array($global['metrics'] ?? null) ? $global['metrics'] : [],
            'hot_zones' => array_values(array_filter($zoneStats, static fn(array $row): bool => (int) ($row['risk_score'] ?? 0) >= 72)),
            'priority_queue' => array_slice($events, 0, 8),
        ];
    }
}

if (!function_exists('vg_fulcrum_snapshot_is_fresh')) {
    function vg_fulcrum_snapshot_is_fresh(array $store, int $ttl = 45): bool
    {
        foreach ((array) ($store['fulcrum_risk_scores'] ?? []) as $row) {
            if ((string) ($row['scope'] ?? '') !== 'global') {
                continue;
            }

            $timestamp = strtotime((string) ($row['computed_at'] ?? ''));
            return $timestamp !== false && $timestamp >= (time() - max(5, $ttl));
        }

        return false;
    }
}

if (!function_exists('vg_fulcrum_refresh_snapshot')) {
    function vg_fulcrum_refresh_snapshot(bool $force = false, int $ttl = 45): array
    {
        $store = vgx_store();
        if (!$force && vg_fulcrum_snapshot_is_fresh($store, $ttl) && !empty($store['fulcrum_risk_scores'])) {
            return vg_fulcrum_store_snapshot($store);
        }

        [$signals, $rawEvents] = vg_fulcrum_collect_signals();
        $suppressed = vg_fulcrum_suppressions();
        $events = vg_fulcrum_group_events($rawEvents, (array) ($suppressed['event_group_keys'] ?? []));
        $zoneStats = vg_fulcrum_build_zone_stats($signals, $events);
        $existingRecommendations = [];
        foreach ((array) ($store['fulcrum_recommendations'] ?? []) as $recommendation) {
            if (!is_array($recommendation)) {
                continue;
            }

            $fingerprint = trim((string) ($recommendation['fingerprint'] ?? ''));
            if ($fingerprint !== '') {
                $existingRecommendations[$fingerprint] = $recommendation;
            }
        }

        $recommendations = vg_fulcrum_build_recommendations($events, $signals, $zoneStats, $existingRecommendations);
        $kpis = vg_fulcrum_build_kpis($signals, $events);
        $riskScores = vg_fulcrum_build_risk_scores($events, $signals, $zoneStats, $kpis, $recommendations);
        $snapshotToken = 'snap-' . date('YmdHis');
        $missionLinks = vg_fulcrum_build_mission_links($signals, $events);

        foreach ($events as $index => $row) {
            $events[$index]['snapshot_token'] = $snapshotToken;
        }
        foreach ($riskScores as $index => $row) {
            $riskScores[$index]['snapshot_token'] = $snapshotToken;
        }
        foreach ($recommendations as $index => $row) {
            $recommendations[$index]['snapshot_token'] = $snapshotToken;
        }
        foreach ($zoneStats as $index => $row) {
            $zoneStats[$index]['snapshot_token'] = $snapshotToken;
        }
        foreach ($missionLinks as $index => $row) {
            $missionLinks[$index]['snapshot_token'] = $snapshotToken;
        }

        // The dashboard and APIs read the local operational store directly.
        // Avoid replaying the whole snapshot row-by-row into SQL on every refresh,
        // which heavily delays page opens and manual re-synchronisations.
        vg_fulcrum_store_replace_collections([
            'fulcrum_events' => $events,
            'fulcrum_risk_scores' => $riskScores,
            'fulcrum_recommendations' => $recommendations,
            'fulcrum_zone_stats' => $zoneStats,
            'fulcrum_missions_links' => $missionLinks,
        ], false);

        return vg_fulcrum_store_snapshot();
    }
}
