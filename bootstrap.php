<?php

declare(strict_types=1);

use App\Application;
use App\Support\Env;

define('BASE_PATH', __DIR__);

// Autoload Composer si installé, sinon autoload PSR-4 minimal (aucune dépendance en production).
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $classe): void {
        if (str_starts_with($classe, 'App\\')) {
            $fichier = BASE_PATH . '/src/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
            if (is_file($fichier)) {
                require $fichier;
            }
        }
    });
}

Env::charger(BASE_PATH . '/.env');

date_default_timezone_set('Africa/Porto-Novo');

return new Application(
    require BASE_PATH . '/config/app.php',
    require BASE_PATH . '/config/ecole.php',
);
