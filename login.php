<?php
/**
 * ==========================================================
 * MELOFY BACKEND — login.php
 * ==========================================================
 * Menangani semua urusan akun: register, login (email+password),
 * login Google, cek sesi, dan logout. Data user disimpan di
 * data/users.json (dibuat otomatis oleh config.php).
 *
 * CARA PAKAI DARI FRONTEND (fetch):
 *   POST login.php   { action: "register", name, email, password }
 *   POST login.php   { action: "login", email, password }
 *   POST login.php   { action: "google_login", id_token }
 *   GET  login.php?action=session
 *   POST login.php   { action: "logout" }
 *
 * Semua respons berbentuk JSON: { ok: true/false, ... }
 */

require __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action'] ?? '') : (read_body_json()['action'] ?? '');

switch ($action) {

    case 'register': {
        $body = read_body_json();
        $name = trim($body['name'] ?? '');
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if (!$name || !$email || !$password) {
            respond(['ok' => false, 'message' => 'Nama, email, dan password wajib diisi.'], 400);
        }
        if (strlen($password) < 6) {
            respond(['ok' => false, 'message' => 'Password minimal 6 karakter.'], 400);
        }
        if (strtolower($email) === strtolower(ADMIN_EMAIL)) {
            respond(['ok' => false, 'message' => 'Email ini sudah dipakai sistem.'], 400);
        }

        $users = read_json_file(USERS_FILE);
        foreach ($users as $u) {
            if (strtolower($u['email']) === $email) {
                respond(['ok' => false, 'message' => 'Email sudah terdaftar.'], 409);
            }
        }

        $newUser = [
            'name' => $name,
            'email' => $email,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'authProvider' => 'melofy',
            'nickname' => $name,
            'picture' => 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($email),
            'plan' => 'free',
            'planExpiresAt' => null,
            'createdAt' => date('c'),
        ];
        $users[] = $newUser;
        write_json_file(USERS_FILE, $users);

        $_SESSION['melofy_email'] = $email;
        respond(['ok' => true, 'user' => sanitize_user($newUser)]);
    }

    case 'login': {
        $body = read_body_json();
        $email = strtolower(trim($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        // Akun Flagship: dicek DI SINI (server), bukan lagi lewat kode JS yang
        // bisa dibaca siapa pun lewat "View Page Source" di browser.
        if (strtolower($email) === strtolower(ADMIN_EMAIL) && $password === ADMIN_PASSWORD) {
            $_SESSION['melofy_email'] = ADMIN_EMAIL;
            respond(['ok' => true, 'user' => [
                'name' => 'Vinz Hosting',
                'email' => ADMIN_EMAIL,
                'nickname' => 'Vinz (Flagship)',
                'picture' => 'https://api.dicebear.com/7.x/bottts/svg?seed=flagship',
                'isFlagship' => true,
                'isAdmin' => true,
                'plan' => 'flagship',
                'planExpiresAt' => null,
            ]]);
        }

        if (!$email || !$password) {
            respond(['ok' => false, 'message' => 'Email dan password wajib diisi.'], 400);
        }

        $users = read_json_file(USERS_FILE);
        foreach ($users as $u) {
            if (strtolower($u['email']) === $email) {
                if (($u['authProvider'] ?? 'melofy') !== 'melofy') {
                    respond(['ok' => false, 'message' => 'Akun ini terdaftar via Google. Silakan gunakan tombol Login Google.'], 400);
                }
                if (!password_verify($password, $u['passwordHash'])) {
                    respond(['ok' => false, 'message' => 'Password salah.'], 401);
                }
                $_SESSION['melofy_email'] = $email;
                respond(['ok' => true, 'user' => sanitize_user($u)]);
            }
        }

        respond(['ok' => false, 'message' => 'Akun tidak ditemukan.'], 404);
    }

    case 'google_login': {
        $body = read_body_json();
        $idToken = $body['id_token'] ?? '';
        if (!$idToken) {
            respond(['ok' => false, 'message' => 'id_token wajib diisi.'], 400);
        }

        $profile = verify_google_id_token($idToken);
        if (!$profile) {
            respond(['ok' => false, 'message' => 'Token Google tidak valid.'], 401);
        }

        $email = strtolower($profile['email']);
        $users = read_json_file(USERS_FILE);
        $found = null;
        foreach ($users as &$u) {
            if (strtolower($u['email']) === $email) {
                $found = &$u;
                break;
            }
        }

        if (!$found) {
            $newUser = [
                'name' => $profile['name'] ?? $email,
                'email' => $email,
                'passwordHash' => null,
                'authProvider' => 'google',
                'nickname' => $profile['name'] ?? $email,
                'picture' => $profile['picture'] ?? ('https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($email)),
                'plan' => 'free',
                'planExpiresAt' => null,
                'createdAt' => date('c'),
            ];
            $users[] = $newUser;
            write_json_file(USERS_FILE, $users);
            $found = $newUser;
        }

        $_SESSION['melofy_email'] = $email;
        respond(['ok' => true, 'user' => sanitize_user($found)]);
    }

    case 'session': {
        $email = current_session_email();
        if (!$email) {
            respond(['ok' => false, 'message' => 'Belum login.'], 401);
        }
        if (strtolower($email) === strtolower(ADMIN_EMAIL)) {
            respond(['ok' => true, 'user' => [
                'name' => 'Vinz Hosting', 'email' => ADMIN_EMAIL, 'nickname' => 'Vinz (Flagship)',
                'picture' => 'https://api.dicebear.com/7.x/bottts/svg?seed=flagship',
                'isFlagship' => true, 'isAdmin' => true, 'plan' => 'flagship', 'planExpiresAt' => null,
            ]]);
        }
        $users = read_json_file(USERS_FILE);
        foreach ($users as $u) {
            if (strtolower($u['email']) === strtolower($email)) {
                respond(['ok' => true, 'user' => sanitize_user($u)]);
            }
        }
        respond(['ok' => false, 'message' => 'User tidak ditemukan.'], 404);
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        respond(['ok' => true]);
    }

    default:
        respond(['ok' => false, 'message' => 'Action tidak dikenal.'], 400);
}

function sanitize_user($u) {
    unset($u['passwordHash']);
    return $u;
}

// Verifikasi id_token Google LEWAT SERVER (bukan cuma decode base64 di
// browser) — supaya orang tidak bisa memalsukan payload token dan
// login sebagai email siapa pun lewat panggilan API langsung.
function verify_google_id_token($idToken) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $context);
    if (!$response) return null;

    $data = json_decode($response, true);
    if (!$data || !isset($data['aud']) || $data['aud'] !== GOOGLE_CLIENT_ID) {
        return null;
    }
    if (!isset($data['email']) || ($data['email_verified'] ?? 'false') !== 'true') {
        return null;
    }

    return [
        'email' => $data['email'],
        'name' => $data['name'] ?? null,
        'picture' => $data['picture'] ?? null,
    ];
}
