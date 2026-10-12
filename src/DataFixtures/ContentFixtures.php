<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\KeyFigure;
use App\Entity\KeyFigureTranslation;
use App\Entity\Page;
use App\Entity\PageTranslation;
use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Tag;
use App\Enum\ArticleType;
use App\Enum\Audience;
use App\Enum\PublicationStatus;
use App\Factory\ArticleFactory;
use App\Factory\CategoryFactory;
use App\Factory\SettingFactory;
use App\Factory\TagFactory;
use App\Home\HomeSettings;
use App\Settings\SiteSettings;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Contenu éditorial réel, repris de sources publiques citées dans chaque article (gouv.bj, presse béninoise).
 * Aucun chiffre ni délai n'est ajouté tant qu'une source officielle ne le fournit pas ; les pages et les coordonnées
 * viennent du document éditorial officiel (octobre 2026), ce qu'il laisse à renseigner reste un marqueur [À VALIDER].
 *
 * Avant de charger le contenu réel, les données de démonstration (toute ligne dont un texte contient « [À VALIDER] »)
 * sont supprimées ; le contenu saisi dans l'administration, les comptes et la page d'accueil sont conservés.
 *
 * php bin/console doctrine:fixtures:load --group=contenu --append
 */
final class ContentFixtures extends Fixture implements FixtureGroupInterface
{
    private const string DEMO_MARKER = '%[À VALIDER]%';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public static function getGroups(): array
    {
        return ['contenu'];
    }

    public function load(ObjectManager $manager): void
    {
        $this->purgeDemo($manager);

        // Rubriques d'actualités du document éditorial : Vie du Ministère, Médias et communication, Projets, Avis et annonces.
        $life = $this->category(0, 'Vie du Ministère', 'Ministry life', 'Réunions, audiences, visites, rencontres et activités institutionnelles.', 'Meetings, audiences, visits and institutional activities.');
        $mediaNews = $this->category(1, 'Médias et communication', 'Media and communication', 'Actualités du secteur et initiatives concernant les professionnels.', 'Sector news and initiatives concerning professionals.');
        $this->category(2, 'Projets', 'Projects', 'Avancement des projets et principales étapes de leur mise en œuvre.', 'Project progress and main implementation milestones.');
        $this->category(3, 'Avis et annonces', 'Notices and announcements', 'Informations administratives et annonces destinées aux citoyens et aux professionnels.', 'Administrative information and announcements for citizens and professionals.');

        $media = $this->tag('Médias', 'Media');
        $haac = $this->tag('HAAC', 'HAAC');
        $consultation = $this->tag('Concertation', 'Consultation');
        $appointments = $this->tag('Nominations', 'Appointments');
        $online = $this->tag('Médias en ligne', 'Online media');

        $this->article(
            ArticleType::Dossier,
            '2026-05-24 12:00',
            $life,
            [$media, $appointments],
            true,
            [
                'fr' => [
                    'title' => 'Un ministère dédié aux médias : Aurélie Adam Soulé Zoumarou à la tête de la Communication',
                    'excerpt' => 'Lors de la formation du premier gouvernement du Président Romuald Wadagni, le 24 mai 2026, le portefeuille de la Communication, en charge des Médias, est confié à Aurélie Adam Soulé Zoumarou.',
                    'body' => '<p>Le 24 mai 2026, à la suite de l\'investiture du Président Romuald Wadagni, le premier gouvernement de son mandat est formé. Il recrée un ministère consacré aux médias : le Ministère de la Communication, en charge des Médias, confié à Aurélie Adam Soulé Zoumarou.</p>'
                        .'<p>Le 25 mai 2026, le Conseil national du patronat de l\'audiovisuel (CNPA-Bénin) et l\'Union des professionnels des médias du Bénin (UPMB) ont salué cette décision dans une déclaration commune, y voyant la reconnaissance de l\'importance des médias dans le processus de développement du pays.</p>'
                        .'<h2>Parcours</h2><p>Ingénieure en télécommunications, diplômée de Télécom SudParis, Aurélie Adam Soulé Zoumarou a travaillé chez SFR et Accenture, dirigé la gestion du spectre à l\'ARCEP-Bénin, puis occupé le poste de Senior Policy Manager Afrique à la GSMA. Elle est entrée au gouvernement le 6 octobre 2017 comme ministre de l\'Économie numérique et de la Communication, puis a dirigé le ministère du Numérique et de la Digitalisation de 2021 à 2026.</p>'
                        .'<p class="source">Sources : <a href="https://beninwebtv.bj/benin-les-faitieres-des-medias-saluent-le-retour-dun-ministere-dedie-aux-medias/">Bénin Web TV, réaction des faîtières des médias</a> ; <a href="https://beninwebtv.bj/portrait/portrait-ministre-aurelie-adam-soule-zoumarou-gouvernement-wadagni-2026/">Bénin Web TV, portrait de la ministre</a>.</p>',
                ],
                'en' => [
                    'title' => 'A ministry dedicated to the media: Aurélie Adam Soulé Zoumarou heads Communication',
                    'excerpt' => 'When President Romuald Wadagni\'s first government was formed on 24 May 2026, the Communication portfolio, in charge of Media, was entrusted to Aurélie Adam Soulé Zoumarou.',
                    'body' => '<p>On 24 May 2026, following the inauguration of President Romuald Wadagni, the first government of his term was formed. It re-established a ministry devoted to the media: the Ministry of Communication, in charge of Media, entrusted to Aurélie Adam Soulé Zoumarou.</p>'
                        .'<p>On 25 May 2026, the National Council of Audiovisual Employers (CNPA-Bénin) and the Union of Media Professionals of Benin (UPMB) welcomed the decision in a joint statement, seeing it as recognition of the importance of the media in the country\'s development.</p>'
                        .'<h2>Background</h2><p>A telecommunications engineer who graduated from Télécom SudParis, Aurélie Adam Soulé Zoumarou worked at SFR and Accenture, headed spectrum management at ARCEP-Bénin, then served as Senior Policy Manager for Africa at the GSMA. She joined the government on 6 October 2017 as Minister of Digital Economy and Communication, then led the Ministry of Digital Affairs and Digitalisation from 2021 to 2026.</p>'
                        .'<p class="source">Sources: <a href="https://beninwebtv.bj/en/benin-media-federations-welcome-the-return-of-a-dedicated-media-ministry/">Bénin Web TV, media federations\' reaction</a>; <a href="https://beninwebtv.bj/portrait/portrait-ministre-aurelie-adam-soule-zoumarou-gouvernement-wadagni-2026/">Bénin Web TV, portrait of the Minister</a>.</p>',
                ],
            ],
        );

        $this->article(
            ArticleType::Actualite,
            '2026-07-05 07:03',
            $life,
            [$media, $consultation],
            false,
            [
                'fr' => [
                    'title' => 'Secteur des médias : la ministre engage des concertations avec les acteurs',
                    'excerpt' => 'Le 3 juillet 2026, Aurélie Adam Soulé Zoumarou a reçu le CNPA, l\'UPMB et l\'ODEM pour examiner les mutations du paysage médiatique national.',
                    'body' => '<p>Le vendredi 3 juillet 2026, la ministre de la Communication, en charge des Médias, a rencontré le Conseil national du patronat de l\'audiovisuel (CNPA), l\'Union des professionnels des médias du Bénin (UPMB) et l\'Observatoire de la déontologie et de l\'éthique dans les médias (ODEM).</p>'
                        .'<p>Ces rencontres visaient à examiner les mutations du paysage médiatique national et à recueillir les préoccupations et les contributions des acteurs, afin d\'améliorer un environnement médiatique « professionnel, viable, innovant et davantage au service du développement ».</p>'
                        .'<p>Elles s\'inscrivent dans la mise en œuvre de la vision du Président Romuald Wadagni : bâtir un écosystème de médias fiables et de production de contenus qui valorisent le Bénin.</p>'
                        .'<p class="source">Source : <a href="https://www.gouv.bj/article/3577/secteur-medias-ministre-aurelie-adam-soule-zoumarou-engage-concertations-avec-acteurs/">gouv.bj, 5 juillet 2026</a>.</p>',
                ],
                'en' => [
                    'title' => 'Media sector: the Minister opens consultations with stakeholders',
                    'excerpt' => 'On 3 July 2026, Aurélie Adam Soulé Zoumarou met the CNPA, the UPMB and the ODEM to review changes in the national media landscape.',
                    'body' => '<p>On Friday 3 July 2026, the Minister of Communication, in charge of Media, met the National Council of Audiovisual Employers (CNPA), the Union of Media Professionals of Benin (UPMB) and the Observatory of Media Ethics and Professional Conduct (ODEM).</p>'
                        .'<p>The meetings aimed to review changes in the national media landscape and to gather stakeholders\' concerns and contributions, in order to improve a media environment that is "professional, viable, innovative and more in service of development".</p>'
                        .'<p>They form part of the implementation of President Romuald Wadagni\'s vision: building an ecosystem of reliable media and content production that showcases Benin.</p>'
                        .'<p class="source">Source: <a href="https://www.gouv.bj/article/3577/secteur-medias-ministre-aurelie-adam-soule-zoumarou-engage-concertations-avec-acteurs/">gouv.bj, 5 July 2026</a>.</p>',
                ],
            ],
        );

        $this->article(
            ArticleType::Actualite,
            '2026-09-17 12:00',
            $life,
            [$media, $haac, $consultation],
            false,
            [
                'fr' => [
                    'title' => 'La ministre échange avec la HAAC sur les orientations du secteur des médias',
                    'excerpt' => 'Le 17 septembre 2026 à Cotonou, Aurélie Adam Soulé Zoumarou a rencontré les conseillers de la HAAC, après une première phase de consultations ouverte le 3 juillet.',
                    'body' => '<p>Le 17 septembre 2026 à Cotonou, la ministre de la Communication, en charge des Médias, a échangé avec les conseillers de la Haute Autorité de l\'Audiovisuel et de la Communication (HAAC) sur les orientations du secteur : qualité de l\'information, contenus produits au Bénin et évolution du paysage médiatique.</p>'
                        .'<p>Cette rencontre fait suite aux consultations engagées le 3 juillet avec le CNPA, l\'UPMB et l\'ODEM, élargies ensuite aux radios, aux télévisions, aux agences de communication et aux créateurs de contenus. « Nous avons voulu une consultation vraiment large », a déclaré la ministre.</p>'
                        .'<p class="source">Source : <a href="https://lanouvelletribune.info/2026/09/benin-aurelie-adam-soule-zoumarou-echange-avec-la-haac-sur-les-medias/">La Nouvelle Tribune, septembre 2026</a>.</p>',
                ],
                'en' => [
                    'title' => 'The Minister discusses the direction of the media sector with the HAAC',
                    'excerpt' => 'On 17 September 2026 in Cotonou, Aurélie Adam Soulé Zoumarou met the HAAC councillors, following a first round of consultations opened on 3 July.',
                    'body' => '<p>On 17 September 2026 in Cotonou, the Minister of Communication, in charge of Media, held talks with the councillors of the High Authority for Audiovisual and Communication (HAAC) on the direction of the sector: the quality of information, content produced in Benin and the evolution of the media landscape.</p>'
                        .'<p>The meeting follows the consultations started on 3 July with the CNPA, the UPMB and the ODEM, later extended to radio and television stations, communication agencies and content creators. "We wanted a truly broad consultation," the Minister said.</p>'
                        .'<p class="source">Source: <a href="https://lanouvelletribune.info/2026/09/benin-aurelie-adam-soule-zoumarou-echange-avec-la-haac-sur-les-medias/">La Nouvelle Tribune, September 2026</a>.</p>',
                ],
            ],
        );

        $this->article(
            ArticleType::Actualite,
            '2026-10-01 12:00',
            $mediaNews,
            [$media, $haac, $online],
            false,
            [
                'fr' => [
                    'title' => 'La HAAC signe des conventions avec 89 médias en ligne',
                    'excerpt' => 'Le 1er octobre 2026 à Cotonou, la HAAC a formalisé les conventions d\'exploitation de 89 opérateurs : 50 organes de presse écrite en ligne, 26 web TV et 13 web radios.',
                    'body' => '<p>Le 1er octobre 2026 à Cotonou, la Haute Autorité de l\'Audiovisuel et de la Communication (HAAC) a signé des conventions d\'exploitation avec 89 opérateurs de médias numériques, posant un cadre de régulation pour la presse en ligne et les services audiovisuels diffusés sur internet.</p>'
                        .'<p>Les 89 opérateurs se répartissent en 50 organes de presse écrite en ligne, 26 web TV et 13 web radios. Le président de la HAAC les a invités à respecter strictement les obligations de leur cahier des charges, en insistant sur la fiabilité de l\'information et la responsabilité des contenus.</p>'
                        .'<p class="source">Source : <a href="https://journaldubenin.com/benin-haac-conventions-89-medias-en-ligne/">Journal du Bénin, 1er octobre 2026</a>.</p>',
                ],
                'en' => [
                    'title' => 'The HAAC signs agreements with 89 online media outlets',
                    'excerpt' => 'On 1 October 2026 in Cotonou, the HAAC formalised the operating agreements of 89 operators: 50 online print outlets, 26 web TV channels and 13 web radios.',
                    'body' => '<p>On 1 October 2026 in Cotonou, the High Authority for Audiovisual and Communication (HAAC) signed operating agreements with 89 digital media operators, setting a regulatory framework for the online press and for audiovisual services distributed over the internet.</p>'
                        .'<p>The 89 operators comprise 50 online print outlets, 26 web TV channels and 13 web radios. The HAAC president urged them to comply strictly with the obligations in their specifications, stressing the reliability of information and responsibility for content.</p>'
                        .'<p class="source">Source: <a href="https://journaldubenin.com/benin-haac-conventions-89-medias-en-ligne/">Journal du Bénin, 1 October 2026</a>.</p>',
                ],
            ],
        );

        $this->article(
            ArticleType::Communique,
            '2026-10-07 18:00',
            $life,
            [$appointments],
            false,
            [
                'fr' => [
                    'title' => 'Conseil des ministres du 7 octobre 2026 : nominations au Ministère de la Communication',
                    'excerpt' => 'Le Conseil des ministres a pourvu plusieurs postes de direction et de conseil au Ministère de la Communication, en charge des Médias.',
                    'body' => '<p>Réuni le mercredi 7 octobre 2026, le Conseil des ministres a procédé à des nominations au Ministère de la Communication, en charge des Médias :</p>'
                        .'<ul><li>Directeur de cabinet : Djabirou Amadou ;</li><li>Secrétaire général du ministère : Marius Atayi Guedegbe ;</li><li>Inspecteur général du ministère : Eugène Alley ;</li><li>Directeur de l\'information et de la communication : Rémy Oke ;</li><li>Directeur du développement des médias : Ghislain Foundohou ;</li><li>Conseillers techniques : Amos Bankole, Appolinaire Gbaguidi, Fadil Moussougan et Léonce Yenoukoumè Akpolgan.</li></ul>'
                        .'<p class="source">Source : <a href="https://beninwebtv.bj/benin-plusieurs-nominations-ce-mercredi-en-conseil-des-ministres-au-ministere-de-la-communication/">Bénin Web TV, 7 octobre 2026</a>.</p>',
                ],
                'en' => [
                    'title' => 'Council of Ministers of 7 October 2026: appointments at the Ministry of Communication',
                    'excerpt' => 'The Council of Ministers filled several management and advisory posts at the Ministry of Communication, in charge of Media.',
                    'body' => '<p>Meeting on Wednesday 7 October 2026, the Council of Ministers made the following appointments at the Ministry of Communication, in charge of Media:</p>'
                        .'<ul><li>Director of the Minister\'s Office: Djabirou Amadou;</li><li>Secretary General of the Ministry: Marius Atayi Guedegbe;</li><li>Inspector General of the Ministry: Eugène Alley;</li><li>Director of Information and Communication: Rémy Oke;</li><li>Director of Media Development: Ghislain Foundohou;</li><li>Technical advisers: Amos Bankole, Appolinaire Gbaguidi, Fadil Moussougan and Léonce Yenoukoumè Akpolgan.</li></ul>'
                        .'<p class="source">Source: <a href="https://beninwebtv.bj/benin-plusieurs-nominations-ce-mercredi-en-conseil-des-ministres-au-ministere-de-la-communication/">Bénin Web TV, 7 October 2026</a>.</p>',
                ],
            ],
        );

        $this->keyFigures($manager);
        $this->services($manager);
        $this->pages($manager);

        // Page d'accueil et coordonnées : textes du document éditorial officiel.
        $this->removeSettings($manager, [HomeSettings::HERO_TITLE, HomeSettings::HERO_LEAD, SiteSettings::CONTACT_ADDRESS, SiteSettings::CONTACT_PHONE, SiteSettings::CONTACT_EMAIL]);
        SettingFactory::createOne(['name' => HomeSettings::HERO_TITLE, 'values' => [
            'fr' => 'Des médias professionnels. Des contenus de qualité. Une communication au service des citoyens.',
            'en' => 'Professional media. Quality content. Communication in the service of citizens.',
        ]]);
        SettingFactory::createOne(['name' => HomeSettings::HERO_LEAD, 'values' => [
            'fr' => 'Le secteur des médias et de la communication évolue rapidement. Le Ministère accompagne cette transformation en agissant sur la qualité de l’information, la professionnalisation des acteurs, le développement des contenus, la transformation des médias de service public et l’adaptation du secteur aux nouveaux usages.',
            'en' => 'The media and communication sector is changing fast. The Ministry supports this transformation by working on the quality of information, the professionalisation of stakeholders, content development, the transformation of public service media and the sector’s adaptation to new uses.',
        ]]);
        SettingFactory::createOne(['name' => SiteSettings::CONTACT_ADDRESS, 'values' => ['fr' => '01 BP 120 Cotonou, Bénin', 'en' => '01 BP 120 Cotonou, Benin']]);
        SettingFactory::createOne(['name' => SiteSettings::CONTACT_PHONE, 'values' => ['fr' => '+229 01 21 35 80 66', 'en' => '+229 01 21 35 80 66']]);
        SettingFactory::createOne(['name' => SiteSettings::CONTACT_EMAIL, 'values' => ['fr' => 'mc.contact@gouv.bj', 'en' => 'mc.contact@gouv.bj']]);
    }

    /** Supprime les lignes de démonstration (texte contenant le marqueur), */
    private function purgeDemo(ObjectManager $manager): void
    {
        $select = 'SELECT IDENTITY(t.translatable) FROM App\Entity\\';
        $targets = [
            [\App\Entity\Article::class, $select.'ArticleTranslation t WHERE t.title LIKE :marker'],
            [Service::class, $select.'ServiceTranslation t WHERE t.title LIKE :marker'],
            [\App\Entity\Consultation::class, $select.'ConsultationTranslation t WHERE t.title LIKE :marker'],
            [\App\Entity\Event::class, $select.'EventTranslation t WHERE t.title LIKE :marker'],
            [\App\Entity\Video::class, $select.'VideoTranslation t WHERE t.title LIKE :marker'],
            [KeyFigure::class, $select.'KeyFigureTranslation t WHERE t.label LIKE :marker'],
            [\App\Entity\OfficialDocument::class, $select.'OfficialDocumentTranslation t WHERE t.title LIKE :marker'],
            [\App\Entity\MediaGallery::class, $select.'MediaGalleryTranslation t WHERE t.title LIKE :marker'],
            [Page::class, $select.'PageTranslation t WHERE t.title LIKE :marker'],
            [\App\Entity\MenuItem::class, $select.'MenuItemTranslation t WHERE t.label LIKE :marker'],
            [Category::class, $select.'CategoryTranslation t WHERE t.name LIKE :marker'],
            [Tag::class, $select.'TagTranslation t WHERE t.name LIKE :marker'],
            [\App\Entity\Setting::class, $select.'SettingTranslation t WHERE t.value LIKE :marker'],
        ];

        foreach ($targets as [$entity, $dql]) {
            $ids = $this->entityManager->createQuery($dql)
                ->setParameter('marker', self::DEMO_MARKER)
                ->getSingleColumnResult();
            foreach (array_unique($ids) as $id) {
                $object = $manager->find($entity, $id);
                if (null === $object) {
                    continue;
                }
                $manager->remove($object);
            }
            $manager->flush();
        }
    }

    /** @param list<string> $names */
    private function removeSettings(ObjectManager $manager, array $names): void
    {
        foreach ($manager->getRepository(\App\Entity\Setting::class)->findBy(['name' => $names]) as $setting) {
            $manager->remove($setting);
        }
        $manager->flush();
    }

    /** Chiffres de la HAAC du 1er octobre 2026 (Journal du Bénin) ; la page d'accueil en affiche les quatre premiers. */
    private function keyFigures(ObjectManager $manager): void
    {
        $url = 'https://journaldubenin.com/benin-haac-conventions-89-medias-en-ligne/';
        $figures = [
            ['89', 'Médias en ligne conventionnés par la HAAC', 'Online media outlets under agreement with the HAAC'],
            ['50', 'Organes de presse écrite en ligne', 'Online print outlets'],
            ['26', 'Web TV', 'Web TV channels'],
            ['13', 'Web radios', 'Web radios'],
        ];
        foreach ($figures as $position => [$value, $fr, $en]) {
            $figure = (new KeyFigure())
                ->setValue($value)
                ->setSourceUrl($url)
                ->setDataDate(new \DateTimeImmutable('2026-10-01'))
                ->setPosition($position)
                ->setStatus(PublicationStatus::Published)
                ->setPublishedAt(new \DateTimeImmutable('2026-10-08 08:00'));
            $figure->addTranslation((new KeyFigureTranslation())->setLocale('fr')->setLabel($fr)->setSource('HAAC, conventions du 1er octobre 2026 (Journal du Bénin)'));
            $figure->addTranslation((new KeyFigureTranslation())->setLocale('en')->setLabel($en)->setSource('HAAC, agreements of 1 October 2026 (Journal du Bénin)'));
            $manager->persist($figure);
        }
        $manager->flush();
    }

    /**
     * Démarches de la HAAC accessibles aux journalistes et aux médias. Ni tarif, ni délai, ni liste de pièces n'est
     * publié dans les sources consultées : ces champs restent vides plutôt qu'inventés.
     */
    private function services(ObjectManager $manager): void
    {
        $this->service($manager, Audience::Journalistes, true, 'https://cartedepresse.haac.bj', 0, [
            'fr' => [
                'title' => 'Demander ou renouveler sa carte de presse',
                'summary' => 'La carte de presse professionnelle est délivrée par la HAAC, qui reçoit les demandes sur une plateforme en ligne : première demande, renouvellement ou duplicata.',
                'body' => '<p>Depuis juillet 2025, la HAAC reçoit les demandes de carte de presse sur sa plateforme en ligne <a href="https://cartedepresse.haac.bj">cartedepresse.haac.bj</a>. Le suivi de l\'état d\'avancement du dossier se fait en ligne, sans déplacement. Une adresse électronique valide est nécessaire pour l\'inscription et le suivi.</p>',
                'steps' => ['Créer un compte sur la plateforme de la HAAC.', 'Renseigner son profil individuel.', 'Soumettre la demande (première demande, renouvellement ou duplicata).'],
                'documents' => ['Affiliation à la Caisse nationale de sécurité sociale (CNSS), préalable à l\'exercice légal du journalisme.', 'Les autres pièces et conditions sont fixées par la HAAC : voir la plateforme.'],
                'contact' => 'Haute Autorité de l\'Audiovisuel et de la Communication (HAAC) : contact@haac.bj',
            ],
            'en' => [
                'title' => 'Apply for or renew a press card',
                'summary' => 'The professional press card is issued by the HAAC, which receives applications on an online platform: first application, renewal or duplicate.',
                'body' => '<p>Since July 2025, the HAAC has received press card applications on its online platform, <a href="https://cartedepresse.haac.bj">cartedepresse.haac.bj</a>. Applicants can follow the progress of their file online without travelling. A valid e-mail address is required for registration and follow-up.</p>',
                'steps' => ['Create an account on the HAAC platform.', 'Fill in your individual profile.', 'Submit the application (first application, renewal or duplicate).'],
                'documents' => ['Membership of the National Social Security Fund (CNSS), a prerequisite to practising journalism legally.', 'Other documents and conditions are set by the HAAC: see the platform.'],
                'contact' => 'High Authority for Audiovisual and Communication (HAAC): contact@haac.bj',
            ],
        ], true);

        $this->service($manager, Audience::Medias, false, 'https://haac.bj', 1, [
            'fr' => [
                'title' => 'Exploiter un média en ligne : autorisation et convention de la HAAC',
                'summary' => 'Le Code de l\'information et de la communication (loi n° 2015-07) soumet à autorisation de la HAAC l\'exploitation d\'un site internet de presse écrite ou de communication audiovisuelle.',
                'body' => '<p>Selon l\'article 252 du Code de l\'information et de la communication, l\'exploitation d\'un site internet offrant au public des services de presse écrite ou de communication audiovisuelle est soumise à l\'autorisation de la HAAC. Les blogs ne sont pas concernés.</p><p>Le 1er octobre 2026, la HAAC a signé des conventions d\'exploitation avec 89 médias en ligne, qui doivent respecter strictement les obligations de leur cahier des charges.</p>',
                'steps' => [],
                'documents' => [],
                'contact' => 'Haute Autorité de l\'Audiovisuel et de la Communication (HAAC) : contact@haac.bj',
            ],
            'en' => [
                'title' => 'Operating an online media outlet: HAAC authorisation and agreement',
                'summary' => 'The Information and Communication Code (Law No. 2015-07) requires HAAC authorisation to operate a website offering print press or audiovisual communication services.',
                'body' => '<p>Under Article 252 of the Information and Communication Code, operating a website that offers the public print press or audiovisual communication services is subject to HAAC authorisation. Blogs are not covered.</p><p>On 1 October 2026, the HAAC signed operating agreements with 89 online media outlets, which must strictly comply with the obligations in their specifications.</p>',
                'steps' => [],
                'documents' => [],
                'contact' => 'High Authority for Audiovisual and Communication (HAAC): contact@haac.bj',
            ],
        ], false);
    }

    /** @param array<string, array<string, mixed>> $texts */
    private function service(ObjectManager $manager, Audience $audience, bool $online, string $url, int $position, array $texts, bool $featured): void
    {
        $service = (new Service())
            ->setAudience($audience)
            ->setOnline($online)
            ->setOnlineUrl($url)
            ->setFeatured($featured)
            ->setPosition($position)
            ->setStatus(PublicationStatus::Published)
            ->setPublishedAt(new \DateTimeImmutable('2026-10-08 08:00'));
        foreach ($texts as $locale => $text) {
            $service->addTranslation((new ServiceTranslation())
                ->setLocale($locale)
                ->setTitle($text['title'])
                ->setSummary($text['summary'])
                ->setBody($text['body'])
                ->setCost(null)
                ->setDelay(null)
                ->setSteps($text['steps'])
                ->setRequiredDocuments($text['documents'])
                ->setContact($text['contact']));
        }
        $manager->persist($service);
        $manager->flush();
    }

    /**
     * Pages du document éditorial (voir EditorialPages). Les pages de même chemin déjà en base sont remplacées,
     * pour que le chargement reste rejouable.
     */
    private function pages(ObjectManager $manager): void
    {
        $definitions = EditorialPages::all();
        $slugs = [];
        foreach ($definitions as $definition) {
            foreach (['fr', 'en'] as $locale) {
                $slugs[] = $definition[$locale]['slug'];
            }
        }

        $stale = $this->entityManager->createQuery('SELECT IDENTITY(t.translatable) FROM App\Entity\PageTranslation t WHERE t.slug IN (:slugs)')
            ->setParameter('slugs', array_unique([...$slugs, 'nos-priorites', 'our-priorities']))
            ->getSingleColumnResult();
        foreach (array_unique($stale) as $id) {
            $existing = $manager->find(Page::class, $id);
            if ($existing instanceof Page && !$existing->isHome()) {
                $manager->remove($existing);
            }
        }
        $manager->flush();

        /** @var array<string, Page> $created */
        $created = [];
        foreach ($definitions as $position => $definition) {
            $page = (new Page())
                ->setParent(null === $definition['parent'] ? null : $created[$definition['parent']])
                ->setPosition($position)
                ->setStatus(PublicationStatus::Published)
                ->setPublishedAt(new \DateTimeImmutable('2026-10-12 08:00'));
            foreach (['fr', 'en'] as $locale) {
                $page->addTranslation((new PageTranslation())
                    ->setLocale($locale)
                    ->setTitle($definition[$locale]['title'])
                    ->setSlug($definition[$locale]['slug'])
                    ->setBody($definition[$locale]['body']));
            }
            $manager->persist($page);
            $manager->flush();
            $created[$definition['key']] = $page;
        }
    }

    private function category(int $position, string $fr, string $en, string $descriptionFr, string $descriptionEn): Category
    {
        return CategoryFactory::new()->withTexts([
            'fr' => ['name' => $fr, 'description' => $descriptionFr],
            'en' => ['name' => $en, 'description' => $descriptionEn],
        ])->create(['position' => $position]);
    }

    private function tag(string $fr, string $en): Tag
    {
        return TagFactory::new()->withTexts(['fr' => ['name' => $fr], 'en' => ['name' => $en]])->create();
    }

    /**
     * @param list<Tag>                            $tags
     * @param array<string, array<string, string>> $texts
     */
    private function article(ArticleType $type, string $publishedAt, Category $category, array $tags, bool $featured, array $texts): void
    {
        ArticleFactory::new()->ofType($type)->withTexts($texts)->create([
            'category' => $category,
            'tags' => $tags,
            'featured' => $featured,
            'publishedAt' => new \DateTimeImmutable($publishedAt),
        ]);
    }
}
