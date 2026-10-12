<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Entity\Article;
use App\Entity\ArticleTranslation;
use App\Enum\PublicationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Slugs (générés, uniques par langue, stables), horodatage et date de publication automatiques.
 */
final class ContentLifecycleTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use Factories;
    use ResetDatabase;

    public function testSlugsAreGeneratedAndUniquePerLocale(): void
    {
        $em = $this->em();
        $first = $this->article(['fr' => 'Même titre', 'en' => 'Même titre']);
        $second = $this->article(['fr' => 'Même titre']);
        $em->persist($first);
        $em->persist($second);
        $em->flush();

        $third = $this->article(['fr' => 'Même titre']);
        $em->persist($third);
        $em->flush();

        self::assertSame('meme-titre', $first->translate('fr')->getSlug());
        self::assertSame('meme-titre', $first->translate('en')->getSlug(), 'Unicité par langue : même slug autorisé en anglais');
        self::assertSame('meme-titre-2', $second->translate('fr')->getSlug(), 'Doublon dans le même flush');
        self::assertSame('meme-titre-3', $third->translate('fr')->getSlug(), 'Doublon avec la base');
    }

    public function testExistingSlugIsKeptWhenTheTitleChangesAndRegeneratedWhenEmptied(): void
    {
        $em = $this->em();
        $article = $this->article(['fr' => 'Titre initial']);
        $em->persist($article);
        $em->flush();

        $article->translate('fr')->setTitle('Titre modifié');
        $em->flush();
        self::assertSame('titre-initial', $article->translate('fr')->getSlug(), 'URL publiée stable');

        $article->translate('fr')->setSlug('');
        $em->flush();
        $em->refresh($article->translate('fr'));
        self::assertSame('titre-modifie', $article->translate('fr')->getSlug());
    }

    public function testDuplicateSlugIsReportedByTheValidator(): void
    {
        $em = $this->em();
        $em->persist($this->article(['fr' => 'Titre']));
        $em->flush();

        $duplicate = (new ArticleTranslation())->setLocale('fr')->setTitle('Autre')->setSlug('titre');
        (new Article())->addTranslation($duplicate);

        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($duplicate);

        self::assertCount(1, $violations);
        self::assertSame('Ce slug est déjà utilisé dans cette langue.', $violations[0]->getMessage());
    }

    public function testTimestampsAndPublicationDateAreFilledAutomatically(): void
    {
        $em = $this->em();
        self::mockTime('2026-10-01 10:00:00');

        $published = $this->article(['fr' => 'Publié'])->setStatus(PublicationStatus::Published);
        $draft = $this->article(['fr' => 'Brouillon']);
        $em->persist($published);
        $em->persist($draft);
        $em->flush();

        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00:00'), $published->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00:00'), $published->getUpdatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00:00'), $published->getPublishedAt());
        self::assertNull($draft->getPublishedAt());

        // Modifier une traduction met à jour son contenu ; publier un brouillon fixe sa date de publication.
        self::mockTime('2026-10-02 08:30:00');
        $published->translate('fr')->setTitle('Publié, corrigé');
        $draft->setStatus(PublicationStatus::Published);
        $em->flush();

        self::assertEquals(new \DateTimeImmutable('2026-10-01 10:00:00'), $published->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-02 08:30:00'), $published->getUpdatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-02 08:30:00'), $draft->getPublishedAt());

        $em->clear();
        $reloaded = $em->find(Article::class, $published->getId());
        self::assertNotNull($reloaded);
        self::assertEquals(new \DateTimeImmutable('2026-10-02 08:30:00'), $reloaded->getUpdatedAt(), 'Persisté en base');
    }

    /**
     * @param array<string, string> $titles langue => titre
     */
    private function article(array $titles): Article
    {
        $article = new Article();
        foreach ($titles as $locale => $title) {
            $article->addTranslation((new ArticleTranslation())->setLocale($locale)->setTitle($title));
        }

        return $article;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
