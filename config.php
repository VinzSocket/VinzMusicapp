<?php
/**
 * ==========================================================
 * MELOFY BACKEND — config.php
 * ==========================================================
 * File ini di-include di SETIAP file backend lain (login.php,
 * redeem.php, report.php, admin.php). Isinya: helper bersama,
 * lokasi file data JSON, dan pengaturan akun Flagship/Admin.
 *
 * PENYIMPANAN DATA: pakai file JSON biasa (bukan MySQL), supaya
 * bisa langsung jalan di hosting PHP mana pun tanpa perlu setup
 * database dulu. database.sql tetap disediakan terpisah kalau
 * suatu saat mau upgrade ke MySQL sungguhan.
 *
 * PENTING SEBELUM DIPAKAI:
 * 1. Upload folder "data/" (dibuat otomatis oleh skrip ini kalau
 *    belum ada) HARUS bisa ditulisi PHP (writable). Di hosting
 *    cPanel biasanya: klik kanan folder data > Permissions > 755
 *    atau 775. Kalau masih gagal simpan, coba 777 (kurang aman,
 *    tapi banyak dipakai di shared hosting Indonesia).
 * 2. Ganti ALLOWED_ORIGIN di bawah ke domain situsmu untuk
 *    keamanan lebih baik (sekarang "*" = semua situs boleh akses).
 */

// ---------- KONFIGURASI DASAR ----------
define('ALLOWED_ORIGIN', '*'); // Ganti mis. jadi "https://melofy.vinzhosting.my.id"
define('DATA_DIR', __DIR__ . '/data');
define('USERS_FILE', DATA_DIR . '/users.json');
define('REDEEM_CODES_FILE', DATA_DIR . '/redeem_codes.json');
define('LAPORAN_FILE', DATA_DIR . '/laporan.json');

// Akun Flagship/Admin — SAMA seperti yang dipakai di index.html,
// tapi di sini jauh lebih aman karena source PHP tidak bisa dibaca
// pengunjung (beda dengan JS di index.html yang bisa dibuka lewat
// "View Source" oleh siapa pun).
define('ADMIN_EMAIL', 'VinzHosting@melofy.com');
define('ADMIN_PASSWORD', 'Alvin2010#');

define('GOOGLE_CLIENT_ID', '606659995825-bdmkmg51k222mbr6b6dtie92ahbg1hpt.apps.googleusercontent.com');

// ---------- SETUP AWAL ----------
if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0775, true);
}

function ensure_json_file($path, $default) {
    if (!file_exists($path)) {
        @file_put_contents($path, json_encode($default, JSON_PRETTY_PRINT));
    }
}
ensure_json_file(USERS_FILE, []);
ensure_json_file(REDEEM_CODES_FILE, [
    // Kode-kode ini SAMA persis dengan yang ada di index.html versi lama,
    // supaya kode yang sudah pernah dibagikan tetap berlaku.
    'MELOFYPRO'   => ['plan' => 'pro', 'name' => 'Paket Pro', 'singleUse' => false],
    'FLAGSHIPVIP' => ['plan' => 'flagship', 'name' => 'Flagship Annual', 'singleUse' => false],
    'PELAJAR100'  => ['plan' => 'pelajar', 'name' => 'Paket Pelajar', 'singleUse' => false],
    'MELOFYPELAJAR' => ['plan' => 'pelajar', 'name' => 'Paket Pelajar (7 Hari)', 'durationDays' => 7, 'singleUse' => true, 'claim' => null],
]);
ensure_json_file(LAPORAN_FILE, []);

// ---------- CORS + HEADER JSON ----------
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- HELPER UMUM ----------
function respond($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function read_body_json() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function read_json_file($path) {
    if (!file_exists($path)) return [];
    $content = @file_get_contents($path);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : [];
}

function write_json_file($path, $data) {
    // LOCK_EX supaya aman kalau ada 2 request nulis bersamaan
    return @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

// IP asli pengguna. Kalau situs di belakang Cloudflare, header
// CF-Connecting-IP lebih akurat daripada REMOTE_ADDR (yang kalau
// dip-proxy Cloudflare akan menunjukkan IP Cloudflare, bukan IP asli).
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function current_session_email() {
    return $_SESSION['melofy_email'] ?? null;
}

function is_admin_session() {
    $email = current_session_email();
    return $email !== null && strtolower($email) === strtolower(ADMIN_EMAIL);
}

function require_admin() {
    if (!is_admin_session()) {
        respond(['ok' => false, 'message' => 'Akses ditolak. Hanya untuk akun Admin/Flagship.'], 403);
    }
}

// Mencatat masalah otomatis (mis. API key gagal) ke laporan.json,
// supaya kelihatan di panel Admin > Info Web.
function log_report($type, $message, $extra = []) {
    $reports = read_json_file(LAPORAN_FILE);
    $reports[] = array_merge([
        'id' => uniqid('rpt_'),
        'type' => $type,
        'message' => $message,
        'time' => date('c'),
        'ip' => get_client_ip(),
    ], $extra);

    // Batasi maksimal 500 laporan terakhir supaya file tidak membengkak selamanya
    if (count($reports) > 500) {
        $reports = array_slice($reports, -500);
    }

    write_json_file(LAPORAN_FILE, $reports);
}
