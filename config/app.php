<?php

declare(strict_types=1);

use App\Support\Env;

// Configuration technique : tout ce qui change d'un serveur à l'autre vient du .env.
return [
    'env'   => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url'   => rtrim((string) Env::get('APP_URL', 'http://localhost/paiement'), '/'),

    'db' => [
        'host'     => Env::get('DB_HOST', '127.0.0.1'),
        'port'     => (int) Env::get('DB_PORT', '3306'),
        'name'     => Env::get('DB_NAME', 'contribution_scolaire'),
        'user'     => Env::get('DB_USER', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
    ],

    'fedapay' => [
        'secret_key'     => Env::get('FEDAPAY_SECRET_KEY', ''),
        'environment'    => Env::get('FEDAPAY_ENVIRONMENT', 'live'),
        'webhook_secret' => Env::get('FEDAPAY_WEBHOOK_SECRET', ''),
    ],

    'admin' => [
        'password' => Env::get('ADMIN_PASSWORD', 'directeur2026'),
    ],

    'log' => BASE_PATH . '/storage/logs/app.log',
];
