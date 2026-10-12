<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Aides des tests de formulaires : client avec limiteurs remis à zéro, jeton CSRF, en-têtes d'origine, fichiers.
 */
trait FormTestTrait
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function client(): KernelBrowser
    {
        $client = static::createClient();
        // Compteurs des limiteurs : persistants entre tests (cache sur disque), donc remis à zéro ici.
        static::getContainer()->get('app.rate_limiter_pool')->clear();

        return $client;
    }

    /**
     * @return array<string, string>
     */
    private function origin(): array
    {
        return ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_REFERER' => 'http://localhost/'];
    }

    private function token(Crawler $crawler, string $form): string
    {
        $field = $crawler->filter(\sprintf('input[name="%s[_token]"]', $form));

        return $field->count() > 0 ? (string) $field->attr('value') : '';
    }

    private function upload(string $path, string $name, string $type): UploadedFile
    {
        return new UploadedFile($path, $name, $type, null, true);
    }

    private function pdf(): string
    {
        return $this->tempFile("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mcm');
        self::assertNotFalse($path);
        file_put_contents($path, $content);

        return $this->tempFiles[] = $path;
    }
}
