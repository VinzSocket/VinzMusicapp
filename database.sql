-- ==========================================================
-- MELOFY — database.sql
-- ==========================================================
-- CATATAN PENTING: login.php, redeem.php, report.php, dan admin.php
-- di paket ini TIDAK memakai MySQL — mereka pakai file JSON biasa
-- (data/users.json, data/redeem_codes.json, data/laporan.json) supaya
-- bisa langsung jalan di hosting PHP mana pun tanpa perlu setup
-- database dulu.
--
-- File .sql ini disediakan sebagai OPSI kalau suatu saat kamu mau
-- upgrade dari penyimpanan JSON ke MySQL sungguhan (lebih cocok kalau
-- pengguna sudah banyak). Skemanya dibuat semirip mungkin dengan
-- struktur data JSON yang dipakai sekarang, supaya migrasinya mudah.
--
-- Untuk memakainya: import file ini lewat phpMyAdmin (Import > pilih
-- file ini > Go), lalu login.php/redeem.php/report.php perlu ditulis
-- ulang untuk pakai PDO/mysqli alih-alih file JSON.
-- ==========================================================

CREATE DATABASE IF NOT EXISTS melofy_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE melofy_db;

-- ----------------------------------------------------------
-- Tabel: users
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    email           VARCHAR(190)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NULL,       -- NULL kalau login via Google
    auth_provider   ENUM('melofy','google') NOT NULL DEFAULT 'melofy',
    nickname        VARCHAR(150)        NULL,
    picture         VARCHAR(500)        NULL,
    plan            ENUM('free','pelajar','pro','flagship') NOT NULL DEFAULT 'free',
    plan_expires_at DATETIME            NULL,       -- NULL = permanen / tidak berlaku
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: redeem_codes
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS redeem_codes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(64)         NOT NULL UNIQUE,
    plan            ENUM('free','pelajar','pro','flagship') NOT NULL,
    name            VARCHAR(150)        NOT NULL,
    duration_days   INT UNSIGNED        NULL,       -- NULL = tidak ada batas waktu
    single_use      TINYINT(1)          NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: redeem_claims
-- Mencatat SIAPA + dari IP MANA sebuah kode single-use sudah diklaim.
-- Untuk kode single-use: 1 baris = kode itu sudah "habis" dipakai.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS redeem_claims (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    redeem_code_id  INT UNSIGNED        NOT NULL,
    email           VARCHAR(190)        NOT NULL,
    ip_address      VARCHAR(45)         NOT NULL,   -- cukup panjang untuk IPv6
    claimed_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (redeem_code_id) REFERENCES redeem_codes(id) ON DELETE CASCADE,
    INDEX idx_ip (ip_address),
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Tabel: error_reports
-- Laporan otomatis dari frontend (mis. API key gagal), dilihat
-- lewat panel Admin > Info Web.
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS error_reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(100)        NOT NULL,   -- mis: 'apikey_failed', 'stream_failed'
    message         TEXT                NOT NULL,
    email           VARCHAR(190)        NULL,       -- email user saat error terjadi (kalau ada)
    ip_address      VARCHAR(45)         NULL,
    user_agent      VARCHAR(500)        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------
-- Seed data awal (SAMA seperti data/redeem_codes.json bawaan)
-- ----------------------------------------------------------
INSERT INTO redeem_codes (code, plan, name, duration_days, single_use) VALUES
    ('MELOFYPRO',     'pro',      'Paket Pro',                NULL, 0),
    ('FLAGSHIPVIP',   'flagship', 'Flagship Annual',          NULL, 0),
    ('PELAJAR100',    'pelajar',  'Paket Pelajar',            NULL, 0),
    ('MELOFYPELAJAR', 'pelajar',  'Paket Pelajar (7 Hari)',   7,    1)
ON DUPLICATE KEY UPDATE code = code;

-- Catatan: akun Flagship (VinzHosting@melofy.com) SENGAJA tidak dimasukkan
-- ke tabel users — di login.php dia dicek langsung dari kredensial yang
-- di-hardcode di config.php (ADMIN_EMAIL / ADMIN_PASSWORD), sama seperti
-- pola yang dipakai kalau nanti pindah ke MySQL: baiknya kredensial admin
-- disimpan di file konfigurasi server, bukan di tabel yang sama dengan
-- user biasa.
