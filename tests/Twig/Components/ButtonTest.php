<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Enum\ButtonVariant;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

final class ButtonTest extends ComponentTestCase
{
    public function testRendersButtonByDefault(): void
    {
        $button = $this->render('Button', ['label' => 'Envoyer'])->filter('button');

        self::assertCount(1, $button);
        self::assertSame('button', $button->attr('type'));
        self::assertSame('btn btn-primary', $button->attr('class'));
        self::assertSame('Envoyer', self::text($button));
    }

    public function testRendersLinkWhenHrefIsGiven(): void
    {
        $crawler = $this->render('Button', ['label' => 'Déposer', 'href' => '/fr/depot', 'variant' => 'accent', 'size' => 'lg']);

        self::assertCount(0, $crawler->filter('button'));
        $link = $crawler->filter('a');
        self::assertSame('/fr/depot', $link->attr('href'));
        self::assertSame('btn btn-accent btn-lg', $link->attr('class'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function variants(): iterable
    {
        yield 'primary' => ['primary', 'btn-primary'];
        yield 'secondary' => ['secondary', 'btn-outline'];
        yield 'accent' => ['accent', 'btn-accent'];
        yield 'ghost' => ['ghost', 'btn-ghost'];
    }

    #[DataProvider('variants')]
    public function testVariantClass(string $variant, string $expectedClass): void
    {
        $class = (string) $this->render('Button', ['label' => 'Ok', 'variant' => $variant])->filter('button')->attr('class');

        self::assertStringContainsString($expectedClass, $class);
    }

    public function testAcceptsEnumInstance(): void
    {
        $class = (string) $this->render('Button', ['label' => 'Ok', 'variant' => ButtonVariant::Ghost])->filter('button')->attr('class');

        self::assertStringContainsString('btn-ghost', $class);
    }

    public function testIconIsRenderedAsSvgAndIconOnlyHidesLabelVisually(): void
    {
        $crawler = $this->render('Button', ['label' => 'Rechercher', 'icon' => 'ri:search-line', 'iconOnly' => true, 'size' => 'sm']);

        $svg = $crawler->filter('button > svg');
        self::assertCount(1, $svg, 'L\'icône est un vrai <svg>, pas du texte échappé');
        self::assertStringContainsString('size-4', (string) $svg->attr('class'));
        self::assertSame('sr-only', $crawler->filter('button > span')->attr('class'));
        self::assertStringContainsString('btn-icon', (string) $crawler->filter('button')->attr('class'));
    }

    public function testIconAtTheEnd(): void
    {
        $children = $this->render('Button', ['label' => 'Suite', 'icon' => 'ri:arrow-right-line', 'iconPosition' => 'end'])->filter('button')->children();

        self::assertSame('span', $children->eq(0)->nodeName());
        self::assertSame('svg', $children->eq(1)->nodeName());
    }

    public function testDisabledAndSubmit(): void
    {
        $button = $this->render('Button', ['label' => 'Envoyer', 'type' => 'submit', 'disabled' => true])->filter('button');

        self::assertSame('submit', $button->attr('type'));
        self::assertNotNull($button->attr('disabled'));
    }

    public function testExtraAttributesArePassedThrough(): void
    {
        $button = $this->render('Button', ['label' => 'Ok', 'class' => 'mt-4', 'data-action' => 'x#y'])->filter('button');

        self::assertSame('btn btn-primary mt-4', $button->attr('class'));
        self::assertSame('x#y', $button->attr('data-action'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidProps(): iterable
    {
        yield 'libellé manquant' => [[]];
        yield 'libellé vide' => [['label' => ' ']];
        yield 'variante inconnue' => [['label' => 'Ok', 'variant' => 'danger']];
        yield 'taille inconnue' => [['label' => 'Ok', 'size' => 'xl']];
        yield 'icône seule sans icône' => [['label' => 'Ok', 'iconOnly' => true]];
        yield 'lien désactivé' => [['label' => 'Ok', 'href' => '/fr', 'disabled' => true]];
        yield 'type inconnu' => [['label' => 'Ok', 'type' => 'link']];
    }

    /**
     * @param array<string, mixed> $props
     */
    #[DataProvider('invalidProps')]
    public function testRejectsInvalidProps(array $props): void
    {
        $this->expectException(ExceptionInterface::class);

        $this->mountTwigComponent('Button', $props);
    }
}
