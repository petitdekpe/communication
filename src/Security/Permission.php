<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Permissions de l'administration, évaluées par les Voters (jamais par des tests de rôle dans les templates ou
 * les contrôleurs : on demande `is_granted(Permission::CONTENT_PUBLISH, $contenu)`).
 */
final class Permission
{
    // Contenus (sujet : une entité publiable, ou la classe de l'entité pour « peut-on en créer ? »).
    public const string CONTENT_VIEW = 'ADMIN_CONTENT_VIEW';
    public const string CONTENT_CREATE = 'ADMIN_CONTENT_CREATE';
    public const string CONTENT_EDIT = 'ADMIN_CONTENT_EDIT';
    public const string CONTENT_SUBMIT = 'ADMIN_CONTENT_SUBMIT';
    /** Valider et publier, mais aussi dépublier, rejeter et archiver. */
    public const string CONTENT_PUBLISH = 'ADMIN_CONTENT_PUBLISH';
    public const string CONTENT_DELETE = 'ADMIN_CONTENT_DELETE';
    /** Modifier les champs anglais (traducteurs). */
    public const string CONTENT_TRANSLATE = 'ADMIN_CONTENT_TRANSLATE';

    // Demandes des usagers (sujet : ServiceRequest ou son nom de classe).
    public const string REQUEST_VIEW = 'ADMIN_REQUEST_VIEW';
    public const string REQUEST_PROCESS = 'ADMIN_REQUEST_PROCESS';

    // Administration générale (sans sujet).
    public const string USER_MANAGE = 'ADMIN_USER_MANAGE';
    public const string SETTINGS_MANAGE = 'ADMIN_SETTINGS_MANAGE';
    public const string AUDIT_VIEW = 'ADMIN_AUDIT_VIEW';
    public const string MENU_MANAGE = 'ADMIN_MENU_MANAGE';
    /** Tableau de bord des traductions, export et import XLIFF. */
    public const string TRANSLATION_MANAGE = 'ADMIN_TRANSLATION_MANAGE';

    // Ressource générique de l'administration (sujet : un AdminResource), pour les listes en lecture.
    public const string RESOURCE_VIEW = 'ADMIN_RESOURCE_VIEW';

    private function __construct()
    {
    }
}
