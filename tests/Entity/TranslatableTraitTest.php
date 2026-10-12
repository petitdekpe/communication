<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use PHPUnit\Framework\TestCase;

final class TranslatableTraitTest extends TestCase
{
    public function testReturnsTheRequestedLocale(): void
    {
        $article = $this->article('fr', 'en');

        self::assertSame('Titre en', $article->translate('en')->getTitle());
        self::assertFalse($article->isTranslationFallback('en'));
        self::assertSame(['fr', 'en'], $article->getAvailableLocales());
    }

    public function testFallsBackToFrenchWhenTheTranslationIsMissing(): void
    {
        $article = $this->article('fr');

        self::assertSame('fr', $article->translate('en')->getLocale());
        self::assertSame('Titre fr', $article->translate('en')->getTitle());
        self::assertTrue($article->isTranslationFallback('en'));
        self::assertNull($article->getTranslation('en'));
    }

    public function testFallsBackToTheFirstTranslationWithoutFrench(): void
    {
        $article = $this->article('en');

        self::assertSame('en', $article->translate('fr')->getLocale());
        self::assertTrue($article->isTranslationFallback('fr'));
    }

    public function testTranslateWithoutAnyTranslationFails(): void
    {
        $this->expectException(\LogicException::class);

        (new Article())->translate('fr');
    }

    public function testAddTranslationLinksBothSidesAndRejectsADuplicateLocale(): void
    {
        $article = $this->article('fr');
        self::assertSame($article, $article->translate('fr')->getTranslatable());

        $this->expectException(\LogicException::class);
        $article->addTranslation((new ArticleTranslation())->setLocale('fr')->setTitle('Doublon'));
    }

    private function article(string ...$locales): Article
    {
        $article = new Article();
        foreach ($locales as $locale) {
            $article->addTranslation((new ArticleTranslation())->setLocale($locale)->setTitle('Titre '.$locale));
        }

        return $article;
    }
}
