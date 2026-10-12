<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Symfony\Component\Panther\Client;

/**
 * Pilotage de la taille d'affichage par le protocole DevTools : Chrome headless impose sinon une largeur
 * de fenêtre minimale (~500 px) qui fausse les tests mobiles.
 */
trait ViewportTrait
{
    private function openAt(string $url, int $width, int $height = 800): Client
    {
        $client = static::createPantherClient();
        $this->setViewport($client, $width, $height);
        $client->request('GET', $url);

        return $client;
    }

    private function setViewport(Client $client, int $width, int $height): void
    {
        $this->webDriver($client)->executeCustomCommand('/session/:sessionId/goog/cdp/execute', 'POST', [
            'cmd' => 'Emulation.setDeviceMetricsOverride',
            'params' => ['width' => $width, 'height' => $height, 'deviceScaleFactor' => 1, 'mobile' => false],
        ]);
    }

    private function webDriver(Client $client): RemoteWebDriver
    {
        $driver = $client->getWebDriver();
        if (!$driver instanceof RemoteWebDriver) {
            self::fail('Panther doit piloter un ChromeDriver distant.');
        }

        return $driver;
    }
}
