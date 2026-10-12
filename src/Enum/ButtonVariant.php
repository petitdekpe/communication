<?php

declare(strict_types=1);

namespace App\Enum;

enum ButtonVariant: string
{
    /** Bleu nuit plein, texte blanc. */
    case Primary = 'primary';
    /** Contour bleu nuit sur fond blanc. */
    case Secondary = 'secondary';
    /** Jaune drapeau, texte bleu nuit (jamais de texte blanc sur jaune). */
    case Accent = 'accent';
    /** Sans fond ni bordure, texte bleu nuit. */
    case Ghost = 'ghost';

    public function cssClass(): string
    {
        return match ($this) {
            self::Primary => 'btn-primary',
            self::Secondary => 'btn-outline',
            self::Accent => 'btn-accent',
            self::Ghost => 'btn-ghost',
        };
    }
}
