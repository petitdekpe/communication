<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\UX\TwigComponent\Test\InteractsWithTwigComponents;

/**
 * Base des tests de rendu des Twig Components (langue par défaut : fr).
 */
abstract class ComponentTestCase extends KernelTestCase
{
    use InteractsWithTwigComponents;

    /**
     * @param array<string, mixed> $props
     */
    protected function render(string $component, array $props = []): Crawler
    {
        return $this->renderTwigComponent($component, $props)->crawler();
    }

    /**
     * Texte visible et masqué, espaces normalisés (y compris les espaces insécables).
     */
    protected static function text(Crawler $crawler): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $crawler->text('', false)));
    }
}
