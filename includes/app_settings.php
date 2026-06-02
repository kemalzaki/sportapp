<?php
/**
 * includes/app_settings.php
 * Helper key/value untuk pengaturan biaya (admin midtrans, biaya aplikasi),
 * sumber tunggal kebenaran dipakai oleh jajanan.php & admin/biaya.php.
 */
if (!function_exists('app_setting')) {
    function app_setting(string $key, $default = null) {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            try {
                $rs = db_all("SELECT skey, sval FROM app_settings");
                foreach ($rs as $r) $cache[$r['skey']] = $r['sval'];
            } catch (Throwable $e) { $cache = []; }
        }
        return array_key_exists($key, $cache) ? $cache[$key] : $default;
    }
    function app_setting_int(string $key, int $default = 0): int {
        $v = app_setting($key, null);
        return ($v === null || $v === '') ? $default : (int)$v;
    }
    function app_setting_float(string $key, float $default = 0.0): float {
        $v = app_setting($key, null);
        return ($v === null || $v === '') ? $default : (float)$v;
    }
    function app_setting_set(string $key, string $val, ?string $ket = null): void {
        if ($ket !== null) {
            db_exec("INSERT INTO app_settings(skey,sval,keterangan,updated_at) VALUES($1,$2,$3,now())
                     ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, keterangan=EXCLUDED.keterangan, updated_at=now()",
                    [$key,$val,$ket]);
        } else {
            db_exec("INSERT INTO app_settings(skey,sval,updated_at) VALUES($1,$2,now())
                     ON CONFLICT (skey) DO UPDATE SET sval=EXCLUDED.sval, updated_at=now()",
                    [$key,$val]);
        }
    }
}
