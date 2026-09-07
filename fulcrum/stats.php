<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_zone_label')) {
    function vg_fulcrum_zone_label(array $row, array $clientsById = []): string
    {
        $clientId = trim((string) ($row['client_id'] ?? ''));
        $client = $clientId !== '' ? ($clientsById[$clientId] ?? null) : null;

        foreach ([
            (string) ($row['zone'] ?? ''),
            (string) ($row['commune'] ?? ''),
            (string) ($row['sector'] ?? ''),
            is_array($client) ? (string) ($client['commune'] ?? '') : '',
            is_array($client) ? (string) ($client['zone'] ?? '') : '',
        ] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return 'Kinshasa centre';
    }
}

if (!function_exists('vg_fulcrum_build_kpis')) {
    function vg_fulcrum_build_kpis(array $signals, array $events): array
    {
        $clients = array_values((array) ($signals['clients'] ?? []));
        $agents = array_values((array) ($signals['agents'] ?? []));
        $cameras = array_values((array) ($signals['cameras'] ?? []));
        $drones = array_values((array) ($signals['drones'] ?? []));
        $communications = array_values((array) ($signals['communications'] ?? []));
        $interventions = array_values((array) ($signals['interventions'] ?? []));
        $behavioralReports = array_values((array) ($signals['behavioral_reports'] ?? []));
        $faceEvents = array_values((array) ($signals['face_events'] ?? []));
        $trafficIncidents = array_values((array) ($signals['traffic_incidents'] ?? []));
        $trafficReports = array_values((array) ($signals['traffic_reports'] ?? []));
        $plateReads = array_values((array) ($signals['plate_reads'] ?? []));
        $vehicleWatchlist = array_values((array) ($signals['vehicle_watchlist'] ?? []));
        $visionEvents = array_values((array) ($signals['vision_events'] ?? []));
        $authorityVehicles = array_values((array) ($signals['authority_vehicles'] ?? []));
        $vehicleIdentities = array_values((array) ($signals['vehicle_identities'] ?? []));
        $alerts = array_values((array) ($signals['alerts'] ?? []));

        $activeStatuses = ['disponible', 'available', 'patrolling', 'flying', 'sur zone', 'online', 'active', 'actif'];
        $isRoadStatusActive = static function (string $status): bool {
            return !in_array(strtolower(trim($status)), ['resolved', 'resolu', 'closed', 'archive', 'archived', 'inactive', 'inactif'], true);
        };
        $isPlateAlert = static function (array $row): bool {
            $status = strtolower(trim((string) ($row['auth_status'] ?? $row['status'] ?? '')));
            if ($status === '') {
                return false;
            }

            foreach (['watchlist', 'unauthor', 'non autor', 'non_autor', 'suspect', 'blacklist', 'recherche', 'critique', 'critical', 'high'] as $needle) {
                if (str_contains($status, $needle)) {
                    return true;
                }
            }

            return false;
        };
        $isRoadVisionEvent = static function (array $row): bool {
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
        $cameraOnline = count(array_filter($cameras, static function (array $camera): bool {
            return trim((string) ($camera['stream_url'] ?? $camera['video_url'] ?? '')) !== ''
                || !in_array(strtolower(trim((string) ($camera['status'] ?? 'active'))), ['offline', 'hors ligne', 'inactif', 'inactive'], true);
        }));
        $agentsAvailable = count(array_filter($agents, static function (array $agent): bool {
            return in_array(strtolower(trim((string) ($agent['status'] ?? ''))), ['disponible', 'available', 'standby', 'prepositionne'], true);
        }));
        $dronesActive = count(array_filter($drones, static function (array $drone) use ($activeStatuses): bool {
            return in_array(strtolower(trim((string) ($drone['status'] ?? 'idle'))), $activeStatuses, true);
        }));
        $communicationsPending = count(array_filter($communications, static function (array $row): bool {
            return in_array(strtolower(trim((string) ($row['ack_status'] ?? $row['status'] ?? ''))), ['pending', 'en attente', 'new', 'open'], true);
        }));
        $interventionsActive = count(array_filter($interventions, static function (array $row): bool {
            return !in_array(strtolower(trim((string) ($row['status'] ?? ''))), ['cloturee', 'cloturee', 'terminee', 'resolved', 'closed'], true);
        }));
        $behavioralHighRisk = count(array_filter($behavioralReports, static function (array $row): bool {
            $payload = is_array($row['report_payload'] ?? null) ? $row['report_payload'] : [];
            $score = (float) ($row['stress_score'] ?? $payload['stress_score'] ?? $payload['multimodal']['deception_score'] ?? 0);
            return $score >= 70;
        }));
        $faceCritical = count(array_filter($faceEvents, static function (array $row): bool {
            return vg_fulcrum_normalize_level((string) ($row['status'] ?? '')) === 'critical'
                || (int) ($row['match_count'] ?? 0) > 0;
        }));
        $trafficActive = count(array_filter($trafficIncidents, static fn(array $row): bool => $isRoadStatusActive((string) ($row['status'] ?? ''))));
        $trafficReportsActive = count(array_filter($trafficReports, static fn(array $row): bool => $isRoadStatusActive((string) ($row['status'] ?? 'active'))));
        $roadPlateAlerts = count(array_filter($plateReads, $isPlateAlert));
        $roadWatchlistActive = count(array_filter($vehicleWatchlist, static function (array $row): bool {
            if (array_key_exists('active', $row)) {
                return !empty($row['active']);
            }

            return !in_array(strtolower(trim((string) ($row['status'] ?? 'active'))), ['inactive', 'disabled', 'archive', 'archived'], true);
        }));
        $roadVisionCritical = count(array_filter($visionEvents, static function (array $row) use ($isRoadVisionEvent): bool {
            if (!$isRoadVisionEvent($row)) {
                return false;
            }

            $severity = vg_fulcrum_normalize_level((string) ($row['severity'] ?? $row['risk_level'] ?? $row['status'] ?? 'medium'));
            return in_array($severity, ['critical', 'high'], true);
        }));
        $authorityVehiclesActive = count(array_filter($authorityVehicles, static fn(array $row): bool => !array_key_exists('is_active', $row) || !empty($row['is_active'])));
        $roadPlateReadsToday = count(array_filter($plateReads, static function (array $row): bool {
            $timestamp = (string) ($row['read_at'] ?? $row['created_at'] ?? '');
            return $timestamp !== '' && substr($timestamp, 0, 10) === date('Y-m-d');
        }));
        $alertsOpen = count(array_filter($alerts, static function (array $row): bool {
            return !in_array(strtolower(trim((string) ($row['status'] ?? ''))), ['traitee', 'resolved', 'closed', 'archivee'], true);
        }));
        $pendingValidation = count(array_filter($events, static function (array $event): bool {
            return !empty($event['requires_validation']) && in_array((string) ($event['validation_status'] ?? 'pending'), ['pending', 'standby', 'dismissed'], true);
        }));
        $avgConfidence = $events === []
            ? 0.0
            : array_sum(array_map(static function (array $event): float {
                $confidence = (float) ($event['confidence'] ?? 0.65);
                return $confidence > 1 ? $confidence / 100 : $confidence;
            }, $events)) / max(1, count($events));

        $roadCameraIds = [];
        $registerRoadCamera = static function (array $row) use (&$roadCameraIds): void {
            $sourceType = strtolower(trim((string) ($row['source_type'] ?? '')));
            $nodeType = strtolower(trim((string) ($row['node_type'] ?? '')));
            $cameraId = trim((string) ($row['camera_id'] ?? ''));
            $sourceId = trim((string) ($row['source_id'] ?? ''));
            if ($cameraId !== '') {
                $roadCameraIds[$cameraId] = true;
                return;
            }

            if ($sourceId !== '' && ($sourceType === 'camera' || str_contains($nodeType, 'camera') || str_contains($nodeType, 'vision'))) {
                $roadCameraIds[$sourceId] = true;
            }
        };

        foreach ($trafficReports as $row) {
            if (is_array($row)) {
                $registerRoadCamera($row);
            }
        }
        foreach ($plateReads as $row) {
            if (is_array($row)) {
                $registerRoadCamera($row);
            }
        }
        foreach ($visionEvents as $row) {
            if (is_array($row) && $isRoadVisionEvent($row)) {
                $registerRoadCamera($row);
            }
        }
        foreach ($trafficIncidents as $row) {
            if (is_array($row)) {
                $registerRoadCamera($row);
            }
        }

        $cameraCoverage = count($clients) > 0 ? (int) round(($cameraOnline / max(1, count($cameras))) * 100) : 0;
        $agentReadiness = count($agents) > 0 ? (int) round(($agentsAvailable / max(1, count($agents))) * 100) : 100;
        $droneReadiness = count($drones) > 0 ? (int) round(($dronesActive / max(1, count($drones))) * 100) : 0;

        return [
            'clients_tracked' => count($clients),
            'cameras_online' => $cameraOnline,
            'cameras_total' => count($cameras),
            'agents_available' => $agentsAvailable,
            'agents_total' => count($agents),
            'drones_active' => $dronesActive,
            'drones_total' => count($drones),
            'interventions_active' => $interventionsActive,
            'communications_pending' => $communicationsPending,
            'behavioral_high_risk' => $behavioralHighRisk,
            'face_critical' => $faceCritical,
            'traffic_active' => $trafficActive,
            'traffic_reports_active' => $trafficReportsActive,
            'road_plate_reads_today' => $roadPlateReadsToday,
            'road_plate_alerts' => $roadPlateAlerts,
            'road_watchlist_active' => $roadWatchlistActive,
            'road_vision_critical' => $roadVisionCritical,
            'road_cameras_ready' => count($roadCameraIds),
            'authority_vehicles_active' => $authorityVehiclesActive,
            'vehicle_identities_total' => count($vehicleIdentities),
            'alerts_open' => $alertsOpen,
            'pending_validation' => $pendingValidation,
            'intelligence_confidence' => (int) round($avgConfidence * 100),
            'camera_coverage_pct' => $cameraCoverage,
            'agent_readiness_pct' => $agentReadiness,
            'drone_readiness_pct' => $droneReadiness,
        ];
    }
}

if (!function_exists('vg_fulcrum_build_zone_stats')) {
    function vg_fulcrum_build_zone_stats(array $signals, array $events): array
    {
        $clients = array_values((array) ($signals['clients'] ?? []));
        $agents = array_values((array) ($signals['agents'] ?? []));
        $cameras = array_values((array) ($signals['cameras'] ?? []));
        $trafficReports = array_values((array) ($signals['traffic_reports'] ?? []));
        $plateReads = array_values((array) ($signals['plate_reads'] ?? []));
        $visionEvents = array_values((array) ($signals['vision_events'] ?? []));
        $trafficIncidents = array_values((array) ($signals['traffic_incidents'] ?? []));
        $clientsById = [];
        $isRoadStatusActive = static function (string $status): bool {
            return !in_array(strtolower(trim($status)), ['resolved', 'resolu', 'closed', 'archive', 'archived', 'inactive', 'inactif'], true);
        };
        $isPlateAlert = static function (array $row): bool {
            $status = strtolower(trim((string) ($row['auth_status'] ?? $row['status'] ?? '')));
            if ($status === '') {
                return false;
            }

            foreach (['watchlist', 'unauthor', 'non autor', 'non_autor', 'suspect', 'blacklist', 'recherche', 'critical', 'critique', 'high'] as $needle) {
                if (str_contains($status, $needle)) {
                    return true;
                }
            }

            return false;
        };
        $isRoadVisionEvent = static function (array $row): bool {
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

        foreach ($clients as $client) {
            $clientId = trim((string) ($client['id'] ?? ''));
            if ($clientId !== '') {
                $clientsById[$clientId] = $client;
            }
        }

        $hq = (array) vg_app('control_hq', ['latitude' => -4.325, 'longitude' => 15.3222]);
        $buckets = [];
        $ensureBucket = static function (string $zone) use (&$buckets, $hq): array {
            $zone = trim($zone) !== '' ? trim($zone) : 'Kinshasa centre';
            if (!isset($buckets[$zone])) {
                $buckets[$zone] = [
                    'zone' => $zone,
                    'label' => $zone,
                    'sites_count' => 0,
                    'camera_count' => 0,
                    'agents_available' => 0,
                    'agents_total' => 0,
                    'event_count' => 0,
                    'signal_count' => 0,
                    'hot_alerts' => 0,
                    'traffic_reports_active' => 0,
                    'traffic_incidents_active' => 0,
                    'plate_reads_total' => 0,
                    'plate_alerts' => 0,
                    'road_vision_critical' => 0,
                    'risk_sum' => 0,
                    'risk_max' => 0,
                    'lat_sum' => 0.0,
                    'lng_sum' => 0.0,
                    'lat_count' => 0,
                    'client_ids' => [],
                    'last_event_at' => '',
                    'fallback_lat' => (float) ($hq['latitude'] ?? -4.325),
                    'fallback_lng' => (float) ($hq['longitude'] ?? 15.3222),
                ];
            }

            return $buckets[$zone];
        };

        foreach ($clients as $index => $client) {
            $zone = vg_fulcrum_zone_label($client, $clientsById);
            $bucket = $ensureBucket($zone);
            $clientId = trim((string) ($client['id'] ?? ''));
            $bucket['sites_count'] += 1;
            if ($clientId !== '') {
                $bucket['client_ids'][] = $clientId;
            }

            $lat = (float) ($client['latitude'] ?? 0);
            $lng = (float) ($client['longitude'] ?? 0);
            if ($lat !== 0.0 || $lng !== 0.0) {
                $bucket['lat_sum'] += $lat;
                $bucket['lng_sum'] += $lng;
                $bucket['lat_count'] += 1;
            } else {
                $seed = abs(crc32((string) ($zone . '|' . $index)));
                $bucket['fallback_lat'] += (($seed % 120) - 60) * 0.00075;
                $bucket['fallback_lng'] += ((((int) floor($seed / 120)) % 120) - 60) * 0.00075;
            }

            $buckets[$zone] = $bucket;
        }

        foreach ($cameras as $camera) {
            $zone = vg_fulcrum_zone_label($camera, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['camera_count'] += 1;
            $buckets[$zone] = $bucket;
        }

        foreach ($agents as $agent) {
            $zone = vg_fulcrum_zone_label($agent, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['agents_total'] += 1;
            if (in_array(strtolower(trim((string) ($agent['status'] ?? ''))), ['disponible', 'available', 'standby', 'prepositionne'], true)) {
            $bucket['agents_available'] += 1;
            }
            $buckets[$zone] = $bucket;
        }

        foreach ($trafficReports as $report) {
            if (!$isRoadStatusActive((string) ($report['status'] ?? 'active'))) {
                continue;
            }

            $zone = vg_fulcrum_zone_label($report, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['traffic_reports_active'] += 1;
            $buckets[$zone] = $bucket;
        }

        foreach ($trafficIncidents as $incident) {
            if (!$isRoadStatusActive((string) ($incident['status'] ?? 'detecte'))) {
                continue;
            }

            $zone = vg_fulcrum_zone_label($incident, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['traffic_incidents_active'] += 1;
            $buckets[$zone] = $bucket;
        }

        foreach ($plateReads as $read) {
            $zone = vg_fulcrum_zone_label($read, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['plate_reads_total'] += 1;
            if ($isPlateAlert($read)) {
                $bucket['plate_alerts'] += 1;
            }
            $buckets[$zone] = $bucket;
        }

        foreach ($visionEvents as $event) {
            if (!$isRoadVisionEvent($event)) {
                continue;
            }

            $severity = vg_fulcrum_normalize_level((string) ($event['severity'] ?? $event['risk_level'] ?? $event['status'] ?? 'medium'));
            if (!in_array($severity, ['critical', 'high'], true)) {
                continue;
            }

            $zone = vg_fulcrum_zone_label($event, $clientsById);
            $bucket = $ensureBucket($zone);
            $bucket['road_vision_critical'] += 1;
            $buckets[$zone] = $bucket;
        }

        foreach ($events as $event) {
            $zone = trim((string) ($event['zone'] ?? ''));
            if ($zone === '') {
                $zone = vg_fulcrum_zone_label($event, $clientsById);
            }

            $bucket = $ensureBucket($zone);
            $bucket['event_count'] += 1;
            $bucket['signal_count'] += max(1, (int) ($event['signal_count'] ?? 1));
            $bucket['risk_sum'] += (int) ($event['risk_score'] ?? 0);
            $bucket['risk_max'] = max($bucket['risk_max'], (int) ($event['risk_score'] ?? 0));
            if ((int) ($event['risk_score'] ?? 0) >= 75) {
                $bucket['hot_alerts'] += 1;
            }
            if (strcmp((string) ($event['updated_at'] ?? $event['created_at'] ?? ''), $bucket['last_event_at']) > 0) {
                $bucket['last_event_at'] = (string) ($event['updated_at'] ?? $event['created_at'] ?? '');
            }
            $buckets[$zone] = $bucket;
        }

        $rows = [];
        foreach ($buckets as $zone => $bucket) {
            $avgRisk = $bucket['event_count'] > 0 ? (int) round($bucket['risk_sum'] / max(1, $bucket['event_count'])) : 0;
            $riskScore = max($bucket['risk_max'], $avgRisk);
            $riskScore += $bucket['hot_alerts'] * 7;
            $riskScore += min(15, max(0, $bucket['signal_count'] - 1) * 2);
            if ($bucket['hot_alerts'] > 0 && $bucket['agents_available'] === 0) {
                $riskScore += 12;
            }
            if ($bucket['event_count'] > 0 && $bucket['camera_count'] === 0) {
                $riskScore += 6;
            }
            $riskScore += min(18, $bucket['plate_alerts'] * 6);
            $riskScore += min(14, $bucket['traffic_incidents_active'] * 5);
            $riskScore += min(12, $bucket['traffic_reports_active'] * 4);
            $riskScore += min(10, $bucket['road_vision_critical'] * 4);
            $riskScore = max(8, min(100, $riskScore));

            $lat = $bucket['lat_count'] > 0 ? $bucket['lat_sum'] / max(1, $bucket['lat_count']) : $bucket['fallback_lat'];
            $lng = $bucket['lat_count'] > 0 ? $bucket['lng_sum'] / max(1, $bucket['lat_count']) : $bucket['fallback_lng'];
            $corridorPressure = $bucket['traffic_reports_active'] + $bucket['traffic_incidents_active'] + $bucket['plate_alerts'] + $bucket['road_vision_critical'];

            $rows[] = [
                'id' => 'zone-' . vg_fulcrum_slug($zone),
                'zone' => $zone,
                'label' => $bucket['label'],
                'risk_score' => $riskScore,
                'risk_band' => vg_fulcrum_risk_band($riskScore),
                'hot_alerts' => $bucket['hot_alerts'],
                'sites_count' => $bucket['sites_count'],
                'camera_count' => $bucket['camera_count'],
                'agents_available' => $bucket['agents_available'],
                'lat' => round((float) $lat, 7),
                'lng' => round((float) $lng, 7),
                'client_ids' => array_values(array_unique($bucket['client_ids'])),
                'metrics' => [
                    'agents_total' => $bucket['agents_total'],
                    'event_count' => $bucket['event_count'],
                    'signal_count' => $bucket['signal_count'],
                    'avg_risk' => $avgRisk,
                    'traffic_reports_active' => $bucket['traffic_reports_active'],
                    'traffic_incidents_active' => $bucket['traffic_incidents_active'],
                    'plate_reads_total' => $bucket['plate_reads_total'],
                    'plate_alerts' => $bucket['plate_alerts'],
                    'road_vision_critical' => $bucket['road_vision_critical'],
                    'corridor_pressure' => $corridorPressure,
                    'last_event_at' => $bucket['last_event_at'],
                ],
                'updated_at' => $bucket['last_event_at'] !== '' ? $bucket['last_event_at'] : vg_fulcrum_now(),
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            $scoreCompare = ((int) ($right['risk_score'] ?? 0)) <=> ((int) ($left['risk_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        });

        return array_values($rows);
    }
}
