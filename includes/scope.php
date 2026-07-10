<?php
/**
 * includes/scope.php — Revisi Juli 2026 R7 (item #5, #6)
 *
 * Helper "Community Scope" untuk mencegah Broken Access Control / IDOR
 * antar-komunitas. Semua halaman list wajib memfilter data agar user
 * hanya melihat data komunitasnya sendiri, KECUALI:
 *   - role = 'superadmin', ATAU
 *   - user termasuk anggota komunitas ber-slug 'superduperadmin'.
 *
 * Cara pakai singkat di halaman list:
 *
 *   require_once __DIR__ . '/includes/scope.php';
 *   $__vids   = scope_visible_user_ids();
 *   $__vkids  = scope_visible_komunitas_ids();
 *   $__viewAll = scope_is_super();
 *
 *   // Untuk query yang berbasis user_id:
 *   //   ... WHERE user_id = ANY($1::int[]) ...
 *   // param: [scope_user_ids_sql_array()]
 *
 *   // Untuk query yang berbasis komunitas_id (mis. jadwal/event):
 *   //   ... WHERE (komunitas_id IS NULL OR komunitas_id = ANY($1::int[])) ...
 *   // param: [scope_kom_ids_sql_array()]
 */

if (!function_exists('current_user'))  require_once __DIR__ . '/auth.php';

/** ID slug komunitas "SuperDuperAdmin". Diseed di sportapp.sql (id=5, slug=superduperadmin). */
function scope_super_kom_slug(): string { return 'superduperadmin'; }

/**
 * Ambil daftar komunitas_id milik user saat ini (dari pivot user_komunitas
 * bila ada, fallback ke kolom users.komunitas_id).
 */
function scope_current_user_kom_ids(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = current_user(); if (!$u) return $cache = [];
    $uid = (int)$u['id'];
    $ids = [];
    try {
        $rows = db_all("SELECT komunitas_id FROM user_komunitas WHERE user_id=$1", [$uid]);
        foreach ($rows as $r) $ids[] = (int)$r['komunitas_id'];
    } catch (Throwable $e) {}
    if (!$ids) {
        try {
            $kid = (int) db_val("SELECT komunitas_id FROM users WHERE id=$1", [$uid]);
            if ($kid) $ids[] = $kid;
        } catch (Throwable $e) {}
    }
    return $cache = array_values(array_unique(array_filter($ids)));
}

/** True bila user role=superadmin ATAU anggota komunitas SuperDuperAdmin. */
function scope_is_super(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = current_user(); if (!$u) return $cache = false;
    if (($u['role'] ?? '') === 'superadmin') return $cache = true;
    try {
        $superKomId = (int) db_val("SELECT id FROM komunitas WHERE slug=$1 LIMIT 1", [scope_super_kom_slug()]);
    } catch (Throwable $e) { $superKomId = 0; }
    if ($superKomId && in_array($superKomId, scope_current_user_kom_ids(), true)) return $cache = true;
    return $cache = false;
}

/**
 * Revisi Juli 2026 R10 — True bila user adalah anggota komunitas 'SuperDuperAdmin'
 * DAN role-nya BUKAN 'superadmin'. Dipakai untuk MENYEMBUNYIKAN sejumlah widget
 * publik (Story, Social Feed, Online, Forum Komunitas, Monitoring Upload,
 * Kalender Aktivitas Publik/Saya, Leaderboard, Tren Kehadiran Mingguan,
 * Riwayat Sesi) dari anggota komunitas admin bila mereka bukan superadmin.
 * (Role superadmin tetap dapat melihat semua widget tersebut.)
 */
function scope_is_superduper_kom_member(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = current_user(); if (!$u) return $cache = false;
    if (($u['role'] ?? '') === 'superadmin') return $cache = false;
    try {
        $superKomId = (int) db_val("SELECT id FROM komunitas WHERE slug=$1 LIMIT 1", [scope_super_kom_slug()]);
    } catch (Throwable $e) { $superKomId = 0; }
    if (!$superKomId) return $cache = false;
    return $cache = in_array($superKomId, scope_current_user_kom_ids(), true);
}

/**
 * Daftar komunitas_id yang boleh dilihat user saat ini.
 * Untuk super-scope: kembalikan semua id komunitas yang aktif.
 */
function scope_visible_komunitas_ids(): array {
    static $cache = null; if ($cache !== null) return $cache;
    if (scope_is_super()) {
        try { $rows = db_all("SELECT id FROM komunitas"); }
        catch (Throwable $e) { $rows = []; }
        return $cache = array_map(fn($r)=>(int)$r['id'], $rows);
    }
    return $cache = scope_current_user_kom_ids();
}

/**
 * Daftar user_id yang boleh dilihat user saat ini (satu komunitas atau lebih).
 * Untuk super-scope: kembalikan semua id user (aktif maupun tidak).
 * Selalu memasukkan id user saat ini agar bisa melihat data dirinya sendiri.
 */
function scope_visible_user_ids(): array {
    static $cache = null; if ($cache !== null) return $cache;
    $u = current_user();
    if (!$u) return $cache = [];
    $me = (int)$u['id'];
    if (scope_is_super()) {
        try { $rows = db_all("SELECT id FROM users"); } catch (Throwable $e) { $rows = []; }
        return $cache = array_map(fn($r)=>(int)$r['id'], $rows);
    }
    $kids = scope_current_user_kom_ids();
    if (!$kids) return $cache = [$me];
    try {
        $arr = '{'.implode(',', array_map('intval', $kids)).'}';
        $rows = db_all(
            "SELECT DISTINCT COALESCE(uk.user_id, u.id) AS id
             FROM users u
             LEFT JOIN user_komunitas uk ON uk.user_id = u.id
             WHERE u.komunitas_id = ANY($1::int[])
                OR uk.komunitas_id = ANY($1::int[])
                OR u.id=$2",
            [$arr, $me]);
        $ids = array_map(fn($r)=>(int)$r['id'], $rows);
        if (!in_array($me, $ids, true)) $ids[] = $me;
        return $cache = array_values(array_unique($ids));
    } catch (Throwable $e) { return $cache = [$me]; }
}

/** Postgres int[] literal, mis. "{1,2,3}". Untuk dipakai dengan $N::int[]. */
function scope_user_ids_sql_array(): string {
    $ids = scope_visible_user_ids();
    if (!$ids) $ids = [0];
    return '{'.implode(',', array_map('intval', $ids)).'}';
}
function scope_kom_ids_sql_array(): string {
    $ids = scope_visible_komunitas_ids();
    if (!$ids) $ids = [0];
    return '{'.implode(',', array_map('intval', $ids)).'}';
}

/**
 * Revisi Juli 2026 R9 — Ambil "primary" komunitas_id user saat ini.
 * Dipakai saat INSERT jadwal/dll agar row baru langsung terikat pada komunitas
 * user login (bukan NULL). Super-scope: kembalikan komunitas_id user itu sendiri
 * bila ada; kalau tidak ada, kembalikan null (biarkan NULL — super lihat semua).
 */
function scope_primary_kom_id(): ?int {
    $ids = scope_current_user_kom_ids();
    if (!$ids) return null;
    return (int)$ids[0];
}

/**
 * Guard: pastikan target user_id ada dalam scope user saat ini.
 * Bila tidak: hentikan dengan HTTP 403 (mencegah IDOR akses profil / data
 * milik komunitas lain).
 */
function scope_require_user(int $targetUserId): void {
    if ($targetUserId <= 0) return;
    if (scope_is_super()) return;
    if (in_array($targetUserId, scope_visible_user_ids(), true)) return;
    http_response_code(403);
    die('Akses ditolak: pengguna berada di komunitas berbeda.');
}

/**
 * Guard: pastikan komunitas_id target berada dalam scope user saat ini.
 * NULL diperlakukan sebagai data lawas — hanya boleh dilihat oleh super-scope.
 */
function scope_require_kom(?int $targetKomId): void {
    if (scope_is_super()) return;
    $vk = scope_visible_komunitas_ids();
    if ($targetKomId === null) {
        // data lawas tanpa komunitas: hanya super-scope; user lain: tolak
        http_response_code(403);
        die('Akses ditolak: data tidak tercatat pada komunitas manapun.');
    }
    if (!in_array((int)$targetKomId, $vk, true)) {
        http_response_code(403);
        die('Akses ditolak: komunitas berbeda.');
    }
}

/**
 * Nama komunitas ber-id tertentu (util kecil untuk render label).
 */
function scope_kom_name(?int $kid): string {
    if (!$kid) return '';
    try { return (string) db_val("SELECT nama FROM komunitas WHERE id=$1", [(int)$kid]); }
    catch (Throwable $e) { return ''; }
}

/**
 * Revisi Nov 2026 — Fitur Islami hanya untuk member komunitas
 *   KawanKeringat Kantor, Ladies Grup, dan SuperDuperAdmin.
 * Role 'superadmin' tetap dibolehkan.
 */
function scope_can_access_islami(): bool {
    static $cache = null;
    if ($cache !== null) return $cache;
    $u = current_user(); if (!$u) return $cache = false;
    if (($u['role'] ?? '') === 'superadmin') return $cache = true;
    $kids = scope_current_user_kom_ids();
    if (!$kids) return $cache = false;
    try {
        $arr = '{'.implode(',', array_map('intval', $kids)).'}';
        $row = db_one(
          "SELECT 1 FROM komunitas
             WHERE id = ANY($1::int[])
               AND slug IN ('kawankeringat-kantor','ladies-grup','superduperadmin')
             LIMIT 1", [$arr]);
        return $cache = !empty($row);
    } catch (Throwable $e) { return $cache = false; }
}


/**
 * Revisi #8 (Kalkulator) — True bila user adalah anggota komunitas
 * "KawanKeringat Kantor" (slug: kawankeringat-kantor). Role superadmin
 * juga dibolehkan. Dipakai untuk membatasi fitur Indikator Hormon
 * Gairah Seksual di kalkulator.php hanya untuk komunitas tersebut.
 */
if (!function_exists('scope_is_kawankeringat_kantor')) {
    function scope_is_kawankeringat_kantor(): bool {
        static $cache = null;
        if ($cache !== null) return $cache;
        $u = current_user(); if (!$u) return $cache = false;
        if (($u['role'] ?? '') === 'superadmin') return $cache = true;
        $kids = scope_current_user_kom_ids();
        if (!$kids) return $cache = false;
        try {
            $arr = '{'.implode(',', array_map('intval', $kids)).'}';
            $row = db_one(
              "SELECT 1 FROM komunitas
                 WHERE id = ANY(\$1::int[])
                   AND slug = 'kawankeringat-kantor'
                 LIMIT 1", [$arr]);
            return $cache = !empty($row);
        } catch (Throwable $e) { return $cache = false; }
    }
}
