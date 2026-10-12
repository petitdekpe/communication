<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rôles de l'administration. La hiérarchie (security.yaml) fait hériter les droits :
 * SUPER_ADMIN > ADMIN > REVIEWER > EDITOR ; ADMIN couvre aussi TRANSLATOR et PRESS.
 */
enum AdminRole: string
{
    case SuperAdmin = 'ROLE_SUPER_ADMIN';
    case Admin = 'ROLE_ADMIN';
    case Reviewer = 'ROLE_REVIEWER';
    case Editor = 'ROLE_EDITOR';
    case Translator = 'ROLE_TRANSLATOR';
    case Press = 'ROLE_PRESS';

    public function labelKey(): string
    {
        return 'admin.role.'.$this->value;
    }
}
