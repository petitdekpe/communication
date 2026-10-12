<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Story\AppStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration (textes « [À VALIDER] » uniquement) : php bin/console doctrine:fixtures:load --group=demo.
 */
final class AppFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['demo'];
    }

    public function load(ObjectManager $manager): void
    {
        AppStory::load();
    }
}
