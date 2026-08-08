<?php
/**
 * ==========================================================
 * MELOFY BACKEND — admin.php
 * ==========================================================
 * Statistik untuk panel Admin > Info Web: jumlah akun terdaftar
 * lewat Melofy (email+password) vs lewat Google.
 *
 * CARA PAKAI DARI FRONTEND:
 *   GET admin.php?action=stats   (khusus Admin/Flagship)
 */

require __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'stats': {
        require_admin();

        $users = read_json_file(USERS_FILE);
        $melofyCount = 0;
        $googleCount = 0;

        foreach ($users as $u) {
            if (($u['authProvider'] ?? 'melofy') === 'google') {
                $googleCount++;
            } else {
                $melofyCount++;
            }
        }

        $codes = read_json_file(REDEEM_CODES_FILE);
        $reports = read_json_file(LAPORAN_FILE);

        respond([
            'ok' => true,
            'stats' => [
                'melofy_accounts' => $melofyCount,
                'google_accounts' => $googleCount,
                'total_accounts' => $melofyCount + $googleCount + 1, // +1 untuk akun Flagship
                'total_redeem_codes' => count($codes),
                'total_reports' => count($reports),
            ],
        ]);
    }

    default:
        respond(['ok' => false, 'message' => 'Action tidak dikenal.'], 400);
}
