<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// ChromeDriver installé par `vendor/bin/bdi detect drivers` : chemin absolu en tête du PATH, sinon Panther
// le lance en relatif (« ./drivers/… »), ce que cmd.exe ne sait pas exécuter sous Windows.
putenv('PATH='.dirname(__DIR__).'/drivers'.\PATH_SEPARATOR.getenv('PATH'));
