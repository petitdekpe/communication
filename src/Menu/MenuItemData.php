<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\MenuItem as MenuItemEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Valeurs du formulaire d'une entrée de menu : libellés FR/EN et un seul type de lien parmi
 * aucun (groupe), contenu du site, adresse externe, chemin interne par langue.
 */
final class MenuItemData
{
    public const string LINK_NONE = 'none';
    public const string LINK_CONTENT = 'content';
    public const string LINK_URL = 'url';
    public const string LINK_PATH = 'path';

    public const array LINK_TYPES = [self::LINK_NONE, self::LINK_CONTENT, self::LINK_URL, self::LINK_PATH];

    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public ?string $labelFr = null;

    #[Assert\Length(max: 150)]
    public ?string $labelEn = null;

    #[Assert\Choice(choices: self::LINK_TYPES)]
    public string $linkType = self::LINK_NONE;

    /** Contenu lié, sous la forme « page:12 », « article:7 » ou « service:3 ». */
    public ?string $target = null;

    #[Assert\Length(max: 255)]
    public ?string $url = null;

    #[Assert\Length(max: 255)]
    public ?string $pathFr = null;

    #[Assert\Length(max: 255)]
    public ?string $pathEn = null;

    public ?MenuItemEntity $parent = null;

    #[Assert\Callback]
    public function validateLink(ExecutionContextInterface $context): void
    {
        if (self::LINK_CONTENT === $this->linkType && 1 !== preg_match('/^(page|article|service):\d+$/', (string) $this->target)) {
            $context->buildViolation('admin.menus.error.target_required')->atPath('target')->addViolation();
        }
        if (self::LINK_URL === $this->linkType && (null === $this->url || 1 !== preg_match('~^https?://~i', $this->url) || false === filter_var($this->url, \FILTER_VALIDATE_URL))) {
            $context->buildViolation('admin.menus.error.url_invalid')->atPath('url')->addViolation();
        }
        if (self::LINK_PATH === $this->linkType) {
            if (!self::isInternalPath($this->pathFr)) {
                $context->buildViolation('admin.menus.error.path_invalid')->atPath('pathFr')->addViolation();
            }
            if (null !== $this->pathEn && '' !== trim($this->pathEn) && !self::isInternalPath($this->pathEn)) {
                $context->buildViolation('admin.menus.error.path_invalid')->atPath('pathEn')->addViolation();
            }
        }
    }

    /** @return array{0: string, 1: int}|null type et identifiant du contenu lié */
    public function targetParts(): ?array
    {
        if (self::LINK_CONTENT !== $this->linkType || 1 !== preg_match('/^(page|article|service):(\d+)$/', (string) $this->target, $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    private static function isInternalPath(?string $path): bool
    {
        $path = trim((string) $path);

        return str_starts_with($path, '/') && !str_starts_with($path, '//') && 1 !== preg_match('/\s/', $path);
    }
}
