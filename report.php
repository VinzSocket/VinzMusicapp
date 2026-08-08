<?php
/**
 * ==========================================================
 * MELOFY BACKEND — report.php
 * ==========================================================
 * Menerima laporan masalah OTOMATIS dari frontend (mis. API key
 * gagal, semua sumber stream gagal, dll), menyimpannya ke
 * data/laporan.json, dan menyediakannya untuk panel Admin > Info Web.
 *
 * CARA PAKAI DARI FRONTEND:
 *   POST report.php   { action: "log_error", type, message, extra? }
 *   GET  report.php?action=admin_get_reports   (khusus Admin)
 *   POST report.php   { action: "admin_clear_reports" }        (khusus Admin)
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$body = $method === 'POST' ? read_body_json() : [];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : ($body['action'] ?? '');

switch ($action) {

    case 'log_error': {
        $type = trim($body['type'] ?? 'unknown');
        $message = trim($body['message'] ?? '-');
        $extra = is_array($body['extra'] ?? null) ? $body['extra'] : [];
        $extra['email'] = current_session_email();
        $extra['userAgent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

        log_report($type, $message, $extra);
        respond(['ok' => true]);
    }

    case 'admin_get_reports': {
        require_admin();
        $reports = read_json_file(LAPORAN_FILE);
        respond(['ok' => true, 'reports' => array_reverse($reports)]); // terbaru dulu
    }

    case 'admin_clear_reports': {
        require_admin();
        write_json_file(LAPORAN_FILE, []);
        respond(['ok' => true]);
    }

    default:
        respond(['ok' => false, 'message' => 'Action tidak dikenal.'], 400);
}
