<?php

declare(strict_types=1);

use App\Http\Request;

$app = require dirname(__DIR__) . '/bootstrap.php';

$app->traiter(Request::depuisGlobales())->envoyer();
