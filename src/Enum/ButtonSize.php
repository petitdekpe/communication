<?php

declare(strict_types=1);

namespace App\Enum;

enum ButtonSize: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';

    public function cssClass(): string
    {
        return match ($this) {
            self::Sm => 'btn-sm',
            self::Md => '',
            self::Lg => 'btn-lg',
        };
    }

    public function iconClass(): string
    {
        return match ($this) {
            self::Sm => 'size-4',
            self::Md => 'size-5',
            self::Lg => 'size-6',
        };
    }
}
