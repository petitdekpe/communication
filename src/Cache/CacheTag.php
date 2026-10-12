<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Étiquettes des caches de pages (HTML complet et blocs dynamiques). Publier ou modifier un contenu invalide les
 * étiquettes concernées ; une page en cache porte celles des données qu'elle a affichées.
 */
final class CacheTag
{
    /** Articles, communiqués, documents, événements, démarches, chiffres clés, consultations, médias : tout ce que listent les blocs dynamiques. */
    public const string CONTENT = 'content';

    /** Structure de l'arborescence des pages (titres, chemins, ordre, publication) : fil d'Ariane, sous-pages, plan du site. */
    public const string PAGES_TREE = 'pages-tree';

    /** Paramètres du site (avis officiel, coordonnées, réseaux sociaux). */
    public const string SETTINGS = 'settings';

    /** Menus de navigation. */
    public const string MENU = 'menu';

    private function __construct()
    {
    }

    /** Étiquette propre à une page (blocs et textes de cette page seulement). */
    public static function page(int $id): string
    {
        return 'page-'.$id;
    }
}
