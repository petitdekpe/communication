<?php

declare(strict_types=1);

namespace App\Tests;

use App\Factory\SettingFactory;
use App\Settings\SiteSettings;

/**
 * L'agenda est désactivé par défaut : les tests qui l'exercent l'activent comme le fait l'administrateur
 * (paramètre « feature.agenda »).
 */
trait EnablesAgenda
{
    private function enableAgenda(): void
    {
        SettingFactory::createOne(['name' => SiteSettings::FEATURE_AGENDA, 'values' => ['fr' => '1', 'en' => '1']]);
    }
}
