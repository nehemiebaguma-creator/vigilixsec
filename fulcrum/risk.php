<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_source_weight')) {
    function vg_fulcrum_source_weight(string $source): int
    {
        return match (strtolower(trim($source))) {
            'alert' => 34,
            'face' => 33,
            'plate' => 32,
            'behavioral' => 31,
            'camera' => 28,
            'road' => 26,
            'intervention' => 25,
            'traffic' => 24,
            'call' => 22,
            'radio' => 20,
            'casefile' => 18,
            'system' => 17,
            default => 16,
        };
    }
}

if (!function_exists('vg_fulcrum_severity_weight')) {
    function vg_fulcrum_severity_weight(string $severity): int
    {
        return match (vg_fulcrum_normalize_level($severity)) {
            'critical' => 42,
            'high' => 30,
            'medium' => 18,
            default => 8,
        };
    }
}

if (!function_exists('vg_fulcrum_priority_label')) {
    function vg_fulcrum_priority_label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'P1',
            $score >= 75 => 'P2',
            $score >= 55 => 'P3',
            default => 'P4',
        };
    }
}

if (!function_exists('vg_fulcrum_risk_band')) {
    function vg_fulcrum_risk_band(int $score): string
    {
        return match (true) {
            $score >= 90 => 'critical',
            $score >= 72 => 'high',
            $score >= 52 => 'elevated',
            $score >= 30 => 'guarded',
            default => 'stable',
        };
    }
}

if (!function_exists('vg_fulcrum_confidence_label')) {
    function vg_fulcrum_confidence_label(float $confidence): string
    {
        if ($confidence > 1) {
            $confidence /= 100;
        }

        return match (true) {
            $confidence >= 0.88 => 'Tres elevee',
            $confidence >= 0.74 => 'Elevee',
            $confidence >= 0.56 => 'Solide',
            $confidence >= 0.40 => 'A confirmer',
            default => 'Faible',
        };
    }
}

if (!function_exists('vg_fulcrum_event_risk')) {
    function vg_fulcrum_event_risk(array $event): int
    {
        $confidence = (float) ($event['confidence'] ?? 0.65);
        if ($confidence > 1) {
            $confidence /= 100;
        }

        $signalCount = max(1, (int) ($event['signal_count'] ?? 1));
        $entities = is_array($event['entities'] ?? null) ? $event['entities'] : [];
        $status = strtolower(trim((string) ($event['status'] ?? '')));
        $severity = vg_fulcrum_normalize_level((string) ($event['severity'] ?? 'medium'));

        $score = vg_fulcrum_source_weight((string) ($event['source'] ?? 'system'));
        $score += vg_fulcrum_severity_weight($severity);
        $score += min(20, max(0, $signalCount - 1) * 7);
        $score += (int) round(max(0.0, min(1.0, $confidence)) * 18);

        if (!empty($event['requires_validation'])) {
            $score += 4;
        }

        if (in_array($status, ['new', 'nouvelle', 'open', 'ouverte', 'active', 'pending', 'en attente', 'detecte', 'detected'], true)) {
            $score += 5;
        }

        $score += min(12, max(0, (int) ($entities['match_count'] ?? $entities['faces_detected'] ?? 0)) * 3);
        $score += min(12, (int) floor(max(
            (float) ($entities['behavioral_score'] ?? 0),
            (float) ($entities['stress_score'] ?? 0),
            (float) ($entities['threat_score'] ?? 0)
        ) / 10));
        $score += min(10, max(0, (int) ($entities['source_count'] ?? $entities['detection_count'] ?? 0) - 1) * 2);
        $score += min(12, max(
            0,
            (int) ($entities['plate_alerts'] ?? 0) * 2,
            (int) ($entities['traffic_reports_active'] ?? 0) * 2,
            (int) ($entities['traffic_incidents_active'] ?? 0) * 2,
            (int) ($entities['vision_critical'] ?? 0) * 2
        ));

        if ($severity === 'low' && $signalCount === 1) {
            $score -= 6;
        }

        return max(5, min(100, $score));
    }
}
