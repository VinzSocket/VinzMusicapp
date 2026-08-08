<?php
/**
 * ==========================================================
 * MELOFY BACKEND — redeem.php
 * ==========================================================
 * Menangani klaim kode redeem (dengan pengecekan IP asli + email,
 * DI SERVER — jadi tidak bisa dikelabui lewat localStorage/incognito),
 * dan (khusus Admin/Flagship) menambah kode baru dari menu Admin.
 *
 * CARA PAKAI DARI FRONTEND:
 *   POST redeem.php   { action: "claim", code }              -> perlu sudah login (session)
 *   POST redeem.php   { action: "admin_add_code", code, plan, name, durationDays, singleUse }
 *   GET  redeem.php?action=admin_list_codes
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$body = $method === 'POST' ? read_body_json() : [];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : ($body['action'] ?? '');

switch ($action) {

    case 'claim': {
        $email = current_session_email();
        if (!$email) {
            respond(['ok' => false, 'message' => 'Silakan login terlebih dahulu.'], 401);
        }

        $code = strtoupper(trim($body['code'] ?? ''));
        if (!$code) {
            respond(['ok' => false, 'message' => 'Masukkan kode redeem.'], 400);
        }

        $codes = read_json_file(REDEEM_CODES_FILE);
        if (!isset($codes[$code])) {
            respond(['ok' => false, 'message' => 'Kode tidak valid.'], 404);
        }

        $reward = $codes[$code];
        $ip = get_client_ip();

        if (!empty($reward['singleUse'])) {
            $claim = $reward['claim'] ?? null;

            if ($claim) {
                if (strtolower($claim['email']) === strtolower($email)) {
                    respond(['ok' => false, 'message' => 'Anda sudah pernah menukarkan kode ini sebelumnya.']);
                }
                if ($claim['ip'] === $ip) {
                    respond(['ok' => false, 'message' => 'Kode ini sudah pernah dipakai dari perangkat/jaringan ini.']);
                }
                respond(['ok' => false, 'message' => 'Kode ini sudah digunakan oleh akun lain.']);
            }

            $durationDays = $reward['durationDays'] ?? 7;
            $expiresAt = (time() + $durationDays * 86400) * 1000; // ms, biar cocok sama Date.now() di JS

            $codes[$code]['claim'] = ['email' => $email, 'ip' => $ip, 'claimedAt' => time() * 1000];
            write_json_file(REDEEM_CODES_FILE, $codes);

            update_user_plan($email, $reward['plan'], $expiresAt);
            respond(['ok' => true, 'message' => "Berhasil menukarkan kode untuk {$reward['name']}.", 'plan' => $reward['plan'], 'planExpiresAt' => $expiresAt]);
        }

        // Kode biasa (bukan single-use) — bisa dipakai berkali-kali oleh siapa saja, permanen
        update_user_plan($email, $reward['plan'], null);
        respond(['ok' => true, 'message' => "Berhasil menukarkan kode untuk {$reward['name']}.", 'plan' => $reward['plan'], 'planExpiresAt' => null]);
    }

    case 'admin_add_code': {
        require_admin();

        $code = strtoupper(trim($body['code'] ?? ''));
        $plan = trim($body['plan'] ?? '');
        $name = trim($body['name'] ?? $code);
        $durationDays = isset($body['durationDays']) ? (int)$body['durationDays'] : null;
        $singleUse = !empty($body['singleUse']);

        if (!$code || !$plan) {
            respond(['ok' => false, 'message' => 'Kode dan plan wajib diisi.'], 400);
        }

        $codes = read_json_file(REDEEM_CODES_FILE);
        $codes[$code] = [
            'plan' => $plan,
            'name' => $name,
            'singleUse' => $singleUse,
            'durationDays' => $durationDays,
            'claim' => null,
            'createdAt' => date('c'),
        ];
        write_json_file(REDEEM_CODES_FILE, $codes);

        respond(['ok' => true, 'message' => "Kode {$code} berhasil dibuat."]);
    }

    case 'admin_list_codes': {
        require_admin();
        respond(['ok' => true, 'codes' => read_json_file(REDEEM_CODES_FILE)]);
    }

    case 'admin_delete_code': {
        require_admin();
        $code = strtoupper(trim($body['code'] ?? ''));
        $codes = read_json_file(REDEEM_CODES_FILE);
        unset($codes[$code]);
        write_json_file(REDEEM_CODES_FILE, $codes);
        respond(['ok' => true]);
    }

    default:
        respond(['ok' => false, 'message' => 'Action tidak dikenal.'], 400);
}

function update_user_plan($email, $plan, $planExpiresAt) {
    if (strtolower($email) === strtolower(ADMIN_EMAIL)) return; // Flagship tidak perlu di-update

    $users = read_json_file(USERS_FILE);
    foreach ($users as &$u) {
        if (strtolower($u['email']) === strtolower($email)) {
            $u['plan'] = $plan;
            $u['planExpiresAt'] = $planExpiresAt;
            break;
        }
    }
    write_json_file(USERS_FILE, $users);
}
