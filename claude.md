Tu travailles sur le portail institutionnel du Ministère de la Communication, chargé des Médias (MCM), République du Bénin. Domaine cible : un sous-domaine gouv.bj.

OBJECTIF
Un portail orienté services (démarches pour journalistes, médias, entreprises, citoyens) avec une couche éditoriale (actualités, communiqués, documents officiels, agenda, chiffres clés sourcés, consultations). Bilingue français (langue par défaut) et anglais.

STACK IMPOSÉE

- Symfony 7.4 LTS, PHP 8.3, Doctrine ORM, MariaDB 10.11+
- Twig + Twig Components + Stimulus + AssetMapper ; Tailwind via symfonycasts/tailwind-bundle (aucun Node en production)
- « Back-office sur mesure sous /admin : contrôleurs Symfony, Symfony Forms, Twig Components, Turbo (Frames + Streams), Live Components, Tailwind. Aucun bundle d'admin générique (ni EasyAdmin, ni Sonata). »
Vérifie qu'aucune dépendance EasyAdmin n'est installée (composer.json, config/bundles.php) et retire-la si besoin.
Fini quand : composer why easycorp/easyadmin-bundle ne renvoie rien.
- Flysystem pour les fichiers (adaptateur local pointant sur un montage NFS), LiipImagine pour les vignettes
- Symfony Workflow pour la publication, Symfony Translation pour l'interface
- PHPUnit + Panther pour les tests, PHPStan niveau 6, PHP-CS-Fixer (règles @Symfony)

IDENTITÉ VISUELLE (alignée sur presidence.bj et gouv.bj)

- Couleur principale : bleu nuit #162233 (variante #1F2F47)
- Couleurs du drapeau : vert #008751, jaune #FCD116, rouge #E8112D (filet tricolore, accents)
- Fond doux : crème #FFEDCF et gris très clair #F7F9FC ; texte #1B1B1B et #475569
- Polices : Montserrat (texte, menus, boutons) ; Baskervville (grands titres éditoriaux uniquement)
- Boutons en pastille (border-radius complet), cartes en rayon 12 px
- Contraste WCAG AA minimum : jamais de texte blanc sur jaune ou crème, jamais de texte jaune/or sur blanc

RÈGLES

- Aucun contenu inventé : tout texte réel, chiffre, tarif, délai, numéro de téléphone est un marqueur [À VALIDER].
- Toutes les chaînes d'interface passent par le traducteur (messages+intl-icu.fr.yaml / .en.yaml).
- Toutes les URL publiques sont préfixées par la langue (/fr/..., /en/...) avec slugs traduits.
- Accessibilité : HTML sémantique, lien d'évitement, focus visible, attributs alt obligatoires, navigation clavier.
- Code : PSR-12, types stricts, services final, pas de logique dans les contrôleurs au-delà de l'orchestration.
- À la fin de chaque tâche : lancer les tests et PHPStan, résumer les fichiers modifiés et les points à vérifier.
