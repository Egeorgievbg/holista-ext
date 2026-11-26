<?php
declare(strict_types=1);

/**
 * Autoloader for tools/verify.sh when Composer is not available.
 *
 * Mirrors the PSR-4 mapping from composer.json:
 *   Espo\Modules\Holista\ -> src/files/custom/Espo/Modules/Holista
 *
 * Not used when running through Composer (vendor/autoload.php takes over).
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Espo\\Modules\\Holista\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/files/custom/Espo/Modules/Holista/' . $relative . '.php';

    if (is_file($file)) {
        require $file;
    }
});
