<?php
class SiteSetting
{
    private static ?array $cache = null;

    private static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::get()->query('SELECT setting_key, setting_value FROM site_setting') as $r) {
                    self::$cache[$r['setting_key']] = $r['setting_value'];
                }
            } catch (Throwable $e) {
                // table absente : valeurs par défaut
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $all = self::all();
        return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): bool
    {
        $st = Database::get()->prepare(
            'INSERT INTO site_setting (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $ok = $st->execute([$key, $value]);
        self::$cache = null;
        return $ok;
    }
}