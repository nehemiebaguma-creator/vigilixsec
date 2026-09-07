<?php
declare(strict_types=1);

if (!function_exists('vg_fulcrum_api_require_admin')) {
    function vg_fulcrum_api_require_admin(): void
    {
        if (function_exists('vg_require_role')) {
            vg_require_role('admin');
            return;
        }

        $role = strtolower(trim((string) (($_SESSION['user']['role'] ?? '') ?: '')));
        if ($role !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Acces refuse.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
}

if (!function_exists('vg_fulcrum_api_respond')) {
    function vg_fulcrum_api_respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
