<?php

declare(strict_types=1);

/*
 * Routeur du serveur PHP intégré utilisé par Panther : les fichiers présents dans public/ sont servis
 * tels quels, tout le reste (y compris /assets/*, servi à la volée par AssetMapper hors production)
 * passe par le front controller Symfony.
 */
$publicDir = dirname(__DIR__).'/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);

if (is_string($path) && '/' !== $path && is_file($publicDir.$path)) {
    return false;
}

// autoload_runtime.php recharge SCRIPT_FILENAME pour obtenir l'application : il doit désigner index.php.
$_SERVER['SCRIPT_FILENAME'] = $publicDir.'/index.php';

require $publicDir.'/index.php';
