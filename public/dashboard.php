<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/auth/check.php';
require_once dirname(__DIR__) . '/includes/app-url.php';

$user = function_exists('vg_current_user') ? vg_current_user() : [];
$role = strtolower(trim((string) ($user['role'] ?? '')));

$target = match ($role) {
    'admin', 'supervisor', 'operator', 'user' => vg_url('admin/dashboard.php'),
    'agent' => vg_url('agent/dashboard.php'),
    'client' => vg_url('client/dashboard.php'),
    default => vg_url('auth/login.php'),
};

header('Location: ' . $target);
exit;
