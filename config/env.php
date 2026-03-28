<?php
// config/env.php — Centralized .env file loader
// Parses key=value pairs from the project .env file and makes them
// available via getenv() and the $_ENV superglobal.

class Env {
    private static $loaded = false;
    private static $vars = [];

    /**
     * Load .env file. Safe to call multiple times (idempotent).
     */
    public static function load($path = null) {
        if (self::$loaded) return;

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!is_file($path)) {
            // No .env file — fall back to system env vars
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if ($line === '' || $line[0] === '#') continue;

            // Parse KEY=VALUE
            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Strip surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$vars[$key] = $value;

            // Set in environment so getenv() works
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default.
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) self::load();
        
        // 12-factor app methodology: System env vars override .env file vars
        $val = $_ENV[$key] ?? getenv($key);
        if ($val !== false && $val !== null) {
            return $val;
        }
        
        return self::$vars[$key] ?? $default;
    }
}
