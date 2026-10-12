<?php

declare(strict_types=1);

namespace App\DataFixtures;

/**
 * Pages éditoriales du site, reprises mot pour mot du « Document éditorial du site web institutionnel »
 * (Point focal Communication, octobre 2026, soumis à la validation de la Ministre). Les rubriques dynamiques
 * (actualités, documentation, médiathèque, agenda) sont des routes du portail et ne figurent pas ici.
 *
 * Ce que le document laisse à renseigner reste un marqueur [À VALIDER] : biographie de la Ministre, horaires,
 * coordonnées des services, pages légales. La version anglaise est une traduction à faire valider.
 */
final class EditorialPages
{
    /**
     * @return list<array{key: string, parent: string|null, fr: array{title: string, slug: string, body: string}, en: array{title: string, slug: string, body: string}}>
     */
    public static function all(): array
    {
        return [
            self::ministry(),
            self::missions(),
            self::organisation(),
            self::bodies(),
            self::minister(),
            self::actions(),
            self::fields(),
            self::projects(),
            self::mediaRoom(),
            self::understand(),
            self::professionals(),
            self::contact(),
            self::legal('legal', 'Mentions légales', 'mentions-legales', 'Legal notice', 'legal-notice'),
            self::legal('privacy', 'Politique de confidentialité', 'politique-de-confidentialite', 'Privacy policy', 'privacy-policy'),
            self::legal('cookies', 'Politique relative aux cookies', 'politique-cookies', 'Cookie policy', 'cookie-policy'),
            self::legal('accessibility', 'Accessibilité', 'accessibilite', 'Accessibility', 'accessibility'),
        ];
    }

    /** @return array<string, mixed> */
    private static function ministry(): array
    {
        return [
            'key' => 'ministry',
            'parent' => null,
            'fr' => ['title' => 'Le Ministère', 'slug' => 'le-ministere', 'body' => <<<'HTML'
<p>Le Ministère de la Communication, en charge des Médias, conduit l’action publique de l’État dans les domaines relevant de la communication et des médias, conformément aux textes en vigueur.</p>
<p>Il intervient dans un secteur en profonde transformation, marqué par la diversification des médias, la révolution numérique, l’évolution des pratiques professionnelles et l’apparition de nouveaux modes de production et de diffusion des contenus.</p>
<p>Son action porte notamment sur la structuration et l’évolution du secteur, la professionnalisation des acteurs, la qualité de l’information, le développement des contenus, la transformation des médias de service public et l’adaptation du cadre de la communication et des médias aux nouveaux usages.</p>
<h2 id="notre-cap">Notre cap : accompagner la transformation du secteur</h2>
<p>Le Bénin dispose d’un secteur des médias et de la communication en pleine évolution.</p>
<p>Presse, radio, télévision, médias en ligne, audiovisuel, production de contenus, communication publique et nouveaux usages numériques composent aujourd’hui un environnement professionnel de plus en plus diversifié.</p>
<p>Notre rôle est d’accompagner cette évolution, de contribuer à la structuration du secteur et de créer les conditions nécessaires à son développement.</p>
<p>Cela passe notamment par la qualité et la fiabilité de l’information, le renforcement des compétences, l’évolution du cadre réglementaire, la transformation numérique, le développement des contenus en langues locales et l’accompagnement des nouveaux métiers.</p>
<h2>Comment fonctionne le Ministère ?</h2>
<p>Cette rubrique présente les principales structures du Ministère, leurs responsabilités et leurs interactions dans la préparation, la mise en œuvre et le suivi des politiques publiques relevant de la communication et des médias. L’organisation et le fonctionnement des directions techniques et des délégations départementales sont fixés par arrêté de la Ministre.</p>
<ul>
<li><a href="/fr/le-ministere/missions">Missions et attributions</a></li>
<li><a href="/fr/le-ministere/organisation">Organisation</a></li>
<li><a href="/fr/le-ministere/structures-sous-tutelle">Organismes sous tutelle et structures associées</a></li>
</ul>
<h2>Les textes qui encadrent notre action</h2>
<p>Retrouvez les principaux textes législatifs et réglementaires qui encadrent l’organisation du Ministère et le fonctionnement du secteur des médias et de la communication.</p>
<p><a href="/fr/documents">Consulter les textes</a></p>
HTML],
            'en' => ['title' => 'The Ministry', 'slug' => 'the-ministry', 'body' => <<<'HTML'
<p>The Ministry of Communication, in charge of Media, leads the State’s public action in the fields of communication and the media, in accordance with the texts in force.</p>
<p>It operates in a sector undergoing profound change, marked by the diversification of the media, the digital revolution, changing professional practices and the emergence of new ways of producing and distributing content.</p>
<p>Its work focuses in particular on structuring and developing the sector, professionalising stakeholders, the quality of information, content development, the transformation of public service media and adapting the framework for communication and the media to new uses.</p>
<h2 id="notre-cap">Our direction: supporting the transformation of the sector</h2>
<p>Benin has a media and communication sector undergoing rapid change.</p>
<p>The press, radio, television, online media, audiovisual, content production, public communication and new digital uses now make up an increasingly diverse professional environment.</p>
<p>Our role is to support this change, to help structure the sector and to create the conditions needed for its development.</p>
<p>This involves, in particular, the quality and reliability of information, skills development, the evolution of the regulatory framework, digital transformation, the development of content in local languages and support for new professions.</p>
<h2>How does the Ministry work?</h2>
<p>This section presents the Ministry’s main structures, their responsibilities and how they interact in preparing, implementing and monitoring public policies relating to communication and the media. The organisation and operation of the technical directorates and departmental delegations are set by order of the Minister.</p>
<ul>
<li><a href="/en/the-ministry/missions">Missions and responsibilities</a></li>
<li><a href="/en/the-ministry/organisation">Organisation</a></li>
<li><a href="/en/the-ministry/supervised-bodies">Supervised bodies and associated structures</a></li>
</ul>
<h2>The texts that frame our work</h2>
<p>Find the main legislative and regulatory texts that frame the organisation of the Ministry and the operation of the media and communication sector.</p>
<p><a href="/en/documents">Browse the texts</a></p>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function missions(): array
    {
        return [
            'key' => 'missions',
            'parent' => 'ministry',
            'fr' => ['title' => 'Missions et attributions', 'slug' => 'missions', 'body' => <<<'HTML'
<p>Conformément à l’article 3 du décret n° 2026-400 du 1er juillet 2026, le Ministère de la Communication, en charge des Médias, a pour mission la définition, la mise en œuvre, le suivi et l’évaluation de la politique générale de l’État en matière d’information, de communication et de médias. À ce titre, il est chargé de :</p>
<h2>En matière de cadre institutionnel, législatif et réglementaire</h2>
<ul>
<li>assurer la coordination et le suivi de la mise en œuvre des politiques, stratégies, projets et programmes du secteur ;</li>
<li>proposer les textes législatifs et réglementaires et les réformes favorables au développement du secteur, en tenant compte des nouvelles chaînes de valeur et des médias sociaux et de communication de masse ;</li>
<li>contribuer à la définition et à l’amélioration du cadre institutionnel et de gouvernance du secteur ;</li>
<li>définir, en liaison avec les institutions compétentes, les principes et outils de gouvernance applicables aux médias communautaires et locaux ;</li>
<li>participer à la gestion des ressources en fréquences radioélectriques affectées à la radiodiffusion sonore et télévisuelle ;</li>
<li>contribuer à la mobilisation des ressources et au développement de partenariats avec le secteur privé, les partenaires techniques et financiers et les institutions nationales, régionales et internationales ;</li>
<li>assurer la représentation de l’État dans les instances régionales et internationales relevant de son domaine de compétence.</li>
</ul>
<h2>En matière d’information, de contenus et de communication publique</h2>
<ul>
<li>contribuer à la définition et à la mise en œuvre de la politique de communication publique de l’État ;</li>
<li>contribuer à la promotion de l’action gouvernementale et à la communication gouvernementale ;</li>
<li>promouvoir, à travers les contenus médiatiques, la cohésion sociale, le dialogue entre les communautés linguistiques et socioprofessionnelles et la valorisation des identités culturelles ;</li>
<li>promouvoir les initiatives de lutte contre la désinformation, la mésinformation et les manipulations de l’information, dans le respect des principes de neutralité, d’objectivité, de pluralisme et des libertés publiques ;</li>
<li>renforcer la qualité du paysage audiovisuel national et soutenir la valorisation de l’image et du rayonnement du Bénin à travers les médias et les contenus, notamment les contenus innovants et ceux en langues nationales.</li>
</ul>
<h2>En matière de développement des médias</h2>
<ul>
<li>proposer et mettre en œuvre les politiques, programmes et projets de développement des médias publics, privés, communautaires, locaux et en ligne ;</li>
<li>co-construire avec les acteurs du secteur la viabilité économique des entreprises de médias (mesure d’audience, marché publicitaire, modèles de financement) ;</li>
<li>contribuer à la modernisation et à la transformation des médias, de la production à la diffusion ;</li>
<li>promouvoir la formation, le renforcement des capacités et l’amélioration des conditions d’exercice des professionnels des médias ;</li>
<li>contribuer à la préservation, à la numérisation et à la valorisation du patrimoine national audiovisuel et de presse ;</li>
<li>assurer, en liaison avec les organisations professionnelles, la coordination des relations entre l’État et les acteurs du secteur des médias.</li>
</ul>
<h2>En matière de service public des médias et d’organismes sous tutelle</h2>
<ul>
<li>mettre en œuvre les orientations stratégiques relatives au service public des médias et garantir l’égalité d’accès des citoyens à l’information de service public sur toute l’étendue du territoire national ;</li>
<li>assurer la tutelle de l’État sur les organismes publics du secteur et veiller à leur bonne gouvernance et à leur performance, notamment à travers le suivi des contrats d’objectifs et de performance ;</li>
<li>assurer la modernisation, la transformation et le développement des infrastructures et des outils de production, d’édition et de diffusion des médias du service public ;</li>
<li>veiller à ce que les médias du service public contribuent à la cohésion nationale, à la promotion des valeurs républicaines, à l’intégration nationale, à la paix sociale et au rayonnement du Bénin.</li>
</ul>
HTML],
            'en' => ['title' => 'Missions and responsibilities', 'slug' => 'missions', 'body' => <<<'HTML'
<p>In accordance with Article 3 of Decree No. 2026-400 of 1 July 2026, the Ministry of Communication, in charge of Media, is responsible for defining, implementing, monitoring and evaluating the State’s general policy on information, communication and the media. To this end, it is responsible for:</p>
<h2>Institutional, legislative and regulatory framework</h2>
<ul>
<li>coordinating and monitoring the implementation of the sector’s policies, strategies, projects and programmes;</li>
<li>proposing legislative and regulatory texts and reforms that support the sector’s development, taking into account new value chains and social and mass communication media;</li>
<li>contributing to defining and improving the sector’s institutional and governance framework;</li>
<li>defining, together with the competent institutions, the governance principles and tools applicable to community and local media;</li>
<li>taking part in managing the radio frequency resources allocated to sound and television broadcasting;</li>
<li>contributing to resource mobilisation and to the development of partnerships with the private sector, technical and financial partners and national, regional and international institutions;</li>
<li>representing the State in regional and international bodies within its field of competence.</li>
</ul>
<h2>Information, content and public communication</h2>
<ul>
<li>contributing to defining and implementing the State’s public communication policy;</li>
<li>contributing to the promotion of government action and to government communication;</li>
<li>promoting, through media content, social cohesion, dialogue between linguistic and socio-professional communities and the enhancement of cultural identities;</li>
<li>promoting initiatives against disinformation, misinformation and information manipulation, in compliance with the principles of neutrality, objectivity, pluralism and public freedoms;</li>
<li>strengthening the quality of the national audiovisual landscape and supporting the promotion of Benin’s image and influence through the media and content, in particular innovative content and content in national languages.</li>
</ul>
<h2>Media development</h2>
<ul>
<li>proposing and implementing policies, programmes and projects for the development of public, private, community, local and online media;</li>
<li>co-building with sector stakeholders the economic viability of media companies (audience measurement, advertising market, funding models);</li>
<li>contributing to the modernisation and transformation of the media, from production to distribution;</li>
<li>promoting training, capacity building and better working conditions for media professionals;</li>
<li>contributing to the preservation, digitisation and promotion of the national audiovisual and press heritage;</li>
<li>ensuring, together with professional organisations, the coordination of relations between the State and media sector stakeholders.</li>
</ul>
<h2>Public service media and supervised bodies</h2>
<ul>
<li>implementing the strategic orientations on public service media and guaranteeing citizens’ equal access to public service information throughout the national territory;</li>
<li>exercising the State’s supervision over the sector’s public bodies and ensuring their good governance and performance, in particular by monitoring objectives and performance contracts;</li>
<li>ensuring the modernisation, transformation and development of the infrastructure and tools for producing, editing and distributing public service media;</li>
<li>ensuring that public service media contribute to national cohesion, the promotion of republican values, national integration, social peace and Benin’s influence.</li>
</ul>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function organisation(): array
    {
        return [
            'key' => 'organisation',
            'parent' => 'ministry',
            'fr' => ['title' => 'Organisation', 'slug' => 'organisation', 'body' => <<<'HTML'
<h2>Une administration organisée autour des enjeux du secteur</h2>
<p>Le Ministère s’appuie sur différentes structures chargées de mettre en œuvre ses missions et d’accompagner les évolutions du secteur de la communication et des médias.</p>
<table>
<thead><tr><th scope="col">Structure</th><th scope="col">Rôle</th></tr></thead>
<tbody>
<tr><th scope="row">Cabinet de la Ministre</th><td>Outre les services prévus par la structure-type des ministères, la Ministre dispose d’un conseiller technique juridique, d’un conseiller technique à l’intelligence artificielle et, selon ses besoins, de trois autres conseillers techniques.</td></tr>
<tr><th scope="row">Secrétariat général et directions centrales</th><td>Structures communes à tous les ministères, fixées par le décret portant structure-type des ministères.</td></tr>
<tr><th scope="row">Direction de l’Information et de la Communication</th><td>Cadre législatif et réglementaire, fréquences de radiodiffusion, communication publique, fiabilité et pluralisme de l’information, lutte contre la désinformation, contenus locaux et langues nationales, rayonnement de l’image du Bénin.</td></tr>
<tr><th scope="row">Direction du Développement des Médias</th><td>Politiques et programmes de développement des médias publics, privés, communautaires, locaux et sociaux ; suivi du paysage médiatique ; viabilité économique ; médias numériques ; formation ; patrimoine audiovisuel.</td></tr>
<tr><th scope="row">Délégués départementaux</th><td>Un délégué nommé en Conseil des Ministres dans chaque département : relais de la communication publique, suivi de proximité des médias communautaires et locaux, appui à la mise en œuvre territoriale des politiques sectorielles.</td></tr>
</tbody>
</table>
<p>L’organisation et le fonctionnement des directions techniques et des délégations départementales sont fixés par arrêté de la Ministre.</p>
<p><a href="/fr/le-ministere/structures-sous-tutelle">Organismes sous tutelle et structures associées</a></p>
HTML],
            'en' => ['title' => 'Organisation', 'slug' => 'organisation', 'body' => <<<'HTML'
<h2>An administration organised around the sector’s challenges</h2>
<p>The Ministry relies on several structures responsible for carrying out its missions and supporting developments in the communication and media sector.</p>
<table>
<thead><tr><th scope="col">Structure</th><th scope="col">Role</th></tr></thead>
<tbody>
<tr><th scope="row">Minister’s Office</th><td>In addition to the services provided for by the standard structure of ministries, the Minister has a legal technical adviser, a technical adviser on artificial intelligence and, as needed, three other technical advisers.</td></tr>
<tr><th scope="row">General Secretariat and central directorates</th><td>Structures common to all ministries, set by the decree on the standard structure of ministries.</td></tr>
<tr><th scope="row">Directorate of Information and Communication</th><td>Legislative and regulatory framework, broadcasting frequencies, public communication, reliability and pluralism of information, fight against disinformation, local content and national languages, promotion of Benin’s image.</td></tr>
<tr><th scope="row">Directorate of Media Development</th><td>Policies and programmes for the development of public, private, community, local and social media; monitoring of the media landscape; economic viability; digital media; training; audiovisual heritage.</td></tr>
<tr><th scope="row">Departmental delegates</th><td>A delegate appointed by the Council of Ministers in each department: a relay for public communication, close monitoring of community and local media, support for the territorial implementation of sector policies.</td></tr>
</tbody>
</table>
<p>The organisation and operation of the technical directorates and departmental delegations are set by order of the Minister.</p>
<p><a href="/en/the-ministry/supervised-bodies">Supervised bodies and associated structures</a></p>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function bodies(): array
    {
        return [
            'key' => 'bodies',
            'parent' => 'ministry',
            'fr' => ['title' => 'Organismes sous tutelle et structures associées', 'slug' => 'structures-sous-tutelle', 'body' => <<<'HTML'
<ul>
<li><strong>Sous tutelle :</strong> la Société de Radio et de Télévision du Bénin (SRTB), la Société béninoise d’Infrastructures de Radiodiffusion (SBIR) et l’Office national d’Imprimerie et de Presse (ONIP) ;</li>
<li><strong>Représentation de l’État coordonnée par le Ministère :</strong> la Société Commune d’Audiovisuel et Bénin Diffusion ;</li>
<li><strong>Appui technique</strong> à l’organe de tutelle de la Société de Production Audiovisuelle.</li>
</ul>
HTML],
            'en' => ['title' => 'Supervised bodies and associated structures', 'slug' => 'supervised-bodies', 'body' => <<<'HTML'
<ul>
<li><strong>Under supervision:</strong> the Benin Radio and Television Company (SRTB), the Beninese Broadcasting Infrastructure Company (SBIR) and the National Printing and Press Office (ONIP);</li>
<li><strong>State representation coordinated by the Ministry:</strong> the Société Commune d’Audiovisuel and Bénin Diffusion;</li>
<li><strong>Technical support</strong> to the supervisory body of the Audiovisual Production Company.</li>
</ul>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function minister(): array
    {
        return [
            'key' => 'minister',
            'parent' => null,
            'fr' => ['title' => 'La Ministre', 'slug' => 'la-ministre', 'body' => <<<'HTML'
<p><strong>Aurélie Adam Soulé Zoumarou</strong><br>Ministre de la Communication, en charge des Médias</p>
<h2>Le mot de la Ministre</h2>
<p>Le secteur des médias connaît aujourd’hui des transformations profondes.</p>
<p>Les technologies modifient les façons de produire, de diffuser et de recevoir l’information. Les frontières entre les différents formats évoluent. De nouveaux métiers apparaissent et de nouveaux publics accèdent à des contenus toujours plus nombreux.</p>
<p>Dans ce contexte, notre responsabilité est de créer les conditions d’un secteur professionnel, dynamique et capable de répondre aux attentes des citoyens.</p>
<p>Notre action portera ainsi sur la qualité de l’information, le développement des compétences, la transformation des médias de service public, l’évolution du cadre réglementaire, le développement des contenus et l’accompagnement des transformations numériques.</p>
<p>Nous voulons également renforcer le dialogue avec les professionnels et favoriser l’émergence de nouvelles opportunités dans les métiers des médias, de l’audiovisuel, de la communication et de la création numérique.</p>
<p><strong>Aurélie Adam Soulé Zoumarou</strong></p>
HTML],
            'en' => ['title' => 'The Minister', 'slug' => 'the-minister', 'body' => <<<'HTML'
<p><strong>Aurélie Adam Soulé Zoumarou</strong><br>Minister of Communication, in charge of Media</p>
<h2>A word from the Minister</h2>
<p>The media sector is going through profound changes.</p>
<p>Technology is changing the way information is produced, distributed and received. The boundaries between formats are shifting. New professions are emerging and new audiences have access to ever more content.</p>
<p>In this context, our responsibility is to create the conditions for a professional, dynamic sector able to meet citizens’ expectations.</p>
<p>Our work will therefore focus on the quality of information, skills development, the transformation of public service media, the evolution of the regulatory framework, content development and support for digital transformation.</p>
<p>We also want to strengthen dialogue with professionals and encourage new opportunities in the professions of the media, audiovisual, communication and digital creation.</p>
<p><strong>Aurélie Adam Soulé Zoumarou</strong></p>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function actions(): array
    {
        return [
            'key' => 'actions',
            'parent' => null,
            'fr' => ['title' => 'Nos actions', 'slug' => 'nos-actions', 'body' => <<<'HTML'
<h2>Agir sur le secteur, avec ses acteurs</h2>
<p>L’action du Ministère se traduit par des politiques publiques, des projets, des programmes, des actions de renforcement de capacités, des travaux réglementaires et des initiatives conduites avec les professionnels du secteur.</p>
<dl>
<dt>Action 1 : Structurer</dt>
<dd>Contribuer à une meilleure organisation du secteur et à l’évolution de son cadre institutionnel et réglementaire.</dd>
<dt>Action 2 : Professionnaliser</dt>
<dd>Renforcer les compétences et accompagner l’évolution des métiers.</dd>
<dt>Action 3 : Transformer</dt>
<dd>Accompagner la transformation numérique des médias, des infrastructures et des pratiques.</dd>
<dt>Action 4 : Développer les contenus</dt>
<dd>Encourager la production de contenus diversifiés, accessibles et adaptés aux publics.</dd>
<dt>Action 5 : Accompagner</dt>
<dd>Créer des conditions favorables au développement des médias, de la presse, de l’audiovisuel et de la création numérique.</dd>
<dt>Action 6 : Informer et sensibiliser</dt>
<dd>Développer l’éducation aux médias et à l’information et faciliter l’accès des citoyens à une meilleure compréhension du secteur.</dd>
</dl>
HTML],
            'en' => ['title' => 'Our actions', 'slug' => 'our-actions', 'body' => <<<'HTML'
<h2>Acting on the sector, with its stakeholders</h2>
<p>The Ministry’s work takes the form of public policies, projects, programmes, capacity-building actions, regulatory work and initiatives carried out with sector professionals.</p>
<dl>
<dt>Action 1: Structure</dt>
<dd>Contribute to better organisation of the sector and to the development of its institutional and regulatory framework.</dd>
<dt>Action 2: Professionalise</dt>
<dd>Strengthen skills and support the evolution of professions.</dd>
<dt>Action 3: Transform</dt>
<dd>Support the digital transformation of the media, infrastructure and practices.</dd>
<dt>Action 4: Develop content</dt>
<dd>Encourage the production of diverse, accessible content suited to audiences.</dd>
<dt>Action 5: Support</dt>
<dd>Create favourable conditions for the development of the media, the press, audiovisual and digital creation.</dd>
<dt>Action 6: Inform and raise awareness</dt>
<dd>Develop media and information literacy and make it easier for citizens to understand the sector.</dd>
</dl>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function fields(): array
    {
        return [
            'key' => 'fields',
            'parent' => null,
            'fr' => ['title' => 'Domaines d’intervention', 'slug' => 'domaines-d-intervention', 'body' => <<<'HTML'
<h2>Six champs d’intervention pour un secteur en mutation</h2>
<dl>
<dt>1. Médias et presse</dt>
<dd>Presse écrite, radio, télévision, médias en ligne et autres acteurs participant à la production et à la diffusion de l’information.</dd>
<dt>2. Journalisme et métiers de l’information</dt>
<dd>Journalisme, traitement de l’information, production éditoriale et développement des compétences professionnelles.</dd>
<dt>3. Audiovisuel et métiers de l’image</dt>
<dd>Production audiovisuelle, réalisation, photographie, vidéo, montage, technique et métiers associés.</dd>
<dt>4. Communication publique</dt>
<dd>Communication institutionnelle, communication gouvernementale, relations avec les médias et information des citoyens.</dd>
<dt>5. Numérique et nouveaux usages</dt>
<dd>Médias sociaux, plateformes, transformation numérique, nouveaux formats et nouvelles pratiques de production et de diffusion.</dd>
<dt>6. Création et contenus</dt>
<dd>Production de contenus audiovisuels et numériques, création, valorisation des langues et cultures et développement de nouvelles activités liées à l’économie des contenus.</dd>
</dl>
HTML],
            'en' => ['title' => 'Fields of intervention', 'slug' => 'fields-of-intervention', 'body' => <<<'HTML'
<h2>Six fields of intervention for a changing sector</h2>
<dl>
<dt>1. Media and press</dt>
<dd>Print press, radio, television, online media and other stakeholders involved in producing and distributing information.</dd>
<dt>2. Journalism and information professions</dt>
<dd>Journalism, information processing, editorial production and the development of professional skills.</dd>
<dt>3. Audiovisual and image professions</dt>
<dd>Audiovisual production, directing, photography, video, editing, technical work and related professions.</dd>
<dt>4. Public communication</dt>
<dd>Institutional communication, government communication, media relations and informing citizens.</dd>
<dt>5. Digital and new uses</dt>
<dd>Social media, platforms, digital transformation, new formats and new production and distribution practices.</dd>
<dt>6. Creation and content</dt>
<dd>Production of audiovisual and digital content, creation, promotion of languages and cultures and development of new activities linked to the content economy.</dd>
</dl>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function projects(): array
    {
        $projects = [
            ['Registre numérique des médias béninois', 'Digital Register of Beninese Media',
                'Améliorer la connaissance du secteur des médias au Bénin à travers un dispositif numérique structuré.', 'Improve knowledge of the media sector in Benin through a structured digital system.',
                'Étude et mise en place d’un registre numérique permettant de disposer d’informations structurées sur les médias béninois et de faciliter leur identification.', 'Study and creation of a digital register providing structured information on Beninese media and making them easier to identify.',
                'Disposer d’une meilleure connaissance du paysage médiatique pour améliorer le suivi du secteur et faciliter l’accès à une information fiable sur les acteurs.', 'Gain a better understanding of the media landscape in order to improve monitoring of the sector and make reliable information on stakeholders easier to access.'],
            ['Radios rurales et communautaires', 'Rural and community radio stations',
                'Renforcer le cadre institutionnel et la gouvernance administrative et financière des radios rurales et communautaires.', 'Strengthen the institutional framework and the administrative and financial governance of rural and community radio stations.',
                null, null,
                'Contribuer à la consolidation de ces médias de proximité et à leur capacité à jouer durablement leur rôle auprès des populations.', 'Help consolidate these local media and their ability to play their role for communities over the long term.'],
            ['Cartographie des communautés de médias sociaux en langues locales', 'Mapping of social media communities in local languages',
                'Mieux connaître les communautés qui utilisent les médias sociaux en langues locales.', 'Gain a better understanding of the communities that use social media in local languages.',
                null, null,
                'Identifier les pratiques, les communautés et les usages afin de mieux comprendre les nouveaux espaces de circulation des contenus.', 'Identify practices, communities and uses in order to better understand the new spaces where content circulates.'],
            ['Renforcement des capacités en anglais des journalistes', 'Strengthening journalists’ English-language skills',
                'Renforcer les compétences linguistiques des journalistes.', 'Strengthen journalists’ language skills.',
                null, null,
                'Faciliter l’accès aux sources internationales, aux formations, aux échanges professionnels et aux opportunités dans un environnement médiatique de plus en plus ouvert.', 'Make it easier to access international sources, training, professional exchanges and opportunities in an increasingly open media environment.'],
            ['Conférence régionale 2027 sur la RNT', '2027 Regional Conference on DTT',
                'Préparer une conférence régionale consacrée à la radiodiffusion numérique terrestre.', 'Prepare a regional conference on digital terrestrial broadcasting.',
                null, null,
                'Créer un cadre régional d’échanges sur les enjeux techniques, réglementaires et professionnels liés à l’évolution de la radiodiffusion.', 'Create a regional forum for discussing the technical, regulatory and professional issues linked to the evolution of broadcasting.'],
            ['Cadre législatif et réglementaire', 'Legislative and regulatory framework',
                'Faire évoluer le cadre législatif et réglementaire applicable à la communication et aux médias en fonction des transformations du secteur.', 'Develop the legislative and regulatory framework applicable to communication and the media in line with the sector’s transformations.',
                null, null,
                'Disposer d’un cadre adapté aux réalités professionnelles, technologiques et économiques actuelles.', 'Have a framework suited to today’s professional, technological and economic realities.'],
            ['Acquisition de contenus pour les médias de service public', 'Acquisition of content for public service media',
                'Renforcer l’offre éditoriale des médias de service public par l’acquisition de contenus adaptés aux besoins des publics.', 'Strengthen the editorial offer of public service media by acquiring content suited to audiences’ needs.',
                null, null,
                'Diversifier et améliorer l’offre de contenus proposée aux citoyens.', 'Diversify and improve the content offered to citizens.'],
            ['Data Center national', 'National Data Center',
                'Héberger et stocker les contenus des chaînes digitales sur le Data Center national.', 'Host and store the content of digital channels on the National Data Center.',
                null, null,
                'Renforcer les capacités d’hébergement, de stockage et de conservation des contenus numériques.', 'Strengthen hosting, storage and preservation capacity for digital content.'],
            ['Production audiovisuelle en langues locales', 'Audiovisual production in local languages',
                'Développer la production audiovisuelle en langues locales dans le cadre de la communication gouvernementale.', 'Develop audiovisual production in local languages as part of government communication.',
                null, null,
                'Rendre les contenus publics plus accessibles aux différentes composantes linguistiques de la population.', 'Make public content more accessible to the different linguistic groups of the population.'],
            ['Ateliers thématiques avec les acteurs des médias', 'Thematic workshops with media stakeholders',
                'Créer des cadres réguliers d’échanges avec les professionnels du secteur.', 'Create regular forums for exchange with sector professionals.',
                null, null,
                'Identifier les difficultés, recueillir les propositions des acteurs et construire des réponses adaptées aux évolutions du secteur.', 'Identify difficulties, gather stakeholders’ proposals and build responses suited to the sector’s evolution.'],
        ];

        $fr = '<h2>Des priorités aux projets</h2><p>Les projets du Ministère traduisent concrètement les orientations retenues pour accompagner l’évolution du secteur. Chaque fiche projet publiée sur le site présentera : objectif, description, état d’avancement, structure porteuse, partenaires et résultats attendus.</p>';
        $en = '<h2>From priorities to projects</h2><p>The Ministry’s projects put into practice the orientations adopted to support the sector’s evolution. Each project sheet published on the site will present: objective, description, progress, lead structure, partners and expected results.</p>';
        foreach ($projects as $i => [$titleFr, $titleEn, $goalFr, $goalEn, $descFr, $descEn, $issueFr, $issueEn]) {
            $n = $i + 1;
            $fr .= \sprintf('<h3>Projet %d : %s</h3><dl><dt>Objectif</dt><dd>%s</dd>', $n, $titleFr, $goalFr);
            $en .= \sprintf('<h3>Project %d: %s</h3><dl><dt>Objective</dt><dd>%s</dd>', $n, $titleEn, $goalEn);
            if (null !== $descFr) {
                $fr .= \sprintf('<dt>Description</dt><dd>%s</dd>', $descFr);
                $en .= \sprintf('<dt>Description</dt><dd>%s</dd>', $descEn);
            }
            $fr .= \sprintf('<dt>Enjeu</dt><dd>%s</dd></dl>', $issueFr);
            $en .= \sprintf('<dt>Issue</dt><dd>%s</dd></dl>', $issueEn);
        }

        return [
            'key' => 'projects',
            'parent' => null,
            'fr' => ['title' => 'Programmes et projets', 'slug' => 'programmes-et-projets', 'body' => $fr],
            'en' => ['title' => 'Programmes and projects', 'slug' => 'programmes-and-projects', 'body' => $en],
        ];
    }

    /** @return array<string, mixed> */
    private static function mediaRoom(): array
    {
        return [
            'key' => 'media_room',
            'parent' => null,
            'fr' => ['title' => 'Espace médias', 'slug' => 'espace-medias', 'body' => <<<'HTML'
<h2>Tout ce dont les médias ont besoin pour suivre l’action du Ministère</h2>
<p>Cet espace rassemble les ressources officielles nécessaires à la couverture des activités du Ministère et à l’accès à l’information institutionnelle.</p>
<dl>
<dt><a href="/fr/communiques">Communiqués de presse</a></dt>
<dd>Les communiqués officiels du Ministère.</dd>
<dt>Dossiers de presse</dt>
<dd>Les dossiers documentaires préparés à l’occasion des principales activités.</dd>
<dt>Discours et interventions</dt>
<dd>Discours, allocutions, déclarations et interventions officielles.</dd>
<dt><a href="/fr/agenda">Agenda presse</a></dt>
<dd>Les activités ouvertes à la couverture médiatique.</dd>
<dt><a href="/fr/mediatheque">Photothèque presse</a></dt>
<dd>Photographies officielles téléchargeables selon les conditions définies par le Ministère.</dd>
<dt><a href="/fr/mediatheque">Vidéothèque presse</a></dt>
<dd>Images et vidéos des principales activités.</dd>
<dt><a href="/fr/contact">Contacts presse</a></dt>
<dd>Les coordonnées utiles pour les demandes d’informations et les relations avec le Ministère.</dd>
<dt><a href="/fr/demarches?public=journalistes">Accréditations</a></dt>
<dd>Informations relatives aux dispositifs d’accès et d’accréditation lors des événements.</dd>
</dl>
HTML],
            'en' => ['title' => 'Media room', 'slug' => 'media-room', 'body' => <<<'HTML'
<h2>Everything the media need to follow the Ministry’s work</h2>
<p>This space brings together the official resources needed to cover the Ministry’s activities and to access institutional information.</p>
<dl>
<dt><a href="/en/press-releases">Press releases</a></dt>
<dd>The Ministry’s official press releases.</dd>
<dt>Press kits</dt>
<dd>Documentary files prepared for the main activities.</dd>
<dt>Speeches and statements</dt>
<dd>Speeches, addresses, declarations and official statements.</dd>
<dt><a href="/en/events">Press agenda</a></dt>
<dd>Activities open to media coverage.</dd>
<dt><a href="/en/media-library">Press photo library</a></dt>
<dd>Official photographs that can be downloaded under the conditions set by the Ministry.</dd>
<dt><a href="/en/media-library">Press video library</a></dt>
<dd>Images and videos of the main activities.</dd>
<dt><a href="/en/contact">Press contacts</a></dt>
<dd>Useful contact details for information requests and relations with the Ministry.</dd>
<dt><a href="/en/services?public=journalistes">Accreditation</a></dt>
<dd>Information on access and accreditation arrangements at events.</dd>
</dl>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function understand(): array
    {
        return [
            'key' => 'understand',
            'parent' => null,
            'fr' => ['title' => 'Comprendre les médias', 'slug' => 'comprendre-les-medias', 'body' => <<<'HTML'
<h2>Comprendre les médias pour mieux comprendre l’information</h2>
<p>Les médias occupent une place essentielle dans la vie de la société. Mais entre presse, radio, télévision, médias en ligne, réseaux sociaux, journalistes, producteurs, communicants et créateurs de contenus, les rôles peuvent parfois sembler difficiles à distinguer. Cet espace propose des contenus simples pour mieux comprendre cet environnement.</p>
<h2>Quels sont les différents types de médias ?</h2>
<p>Presse écrite, radio, télévision, médias en ligne : découvrez leurs caractéristiques, leurs modes de fonctionnement et leurs spécificités.</p>
<h2>Quel est le rôle d’un journaliste ?</h2>
<p>Comprendre le travail journalistique, la recherche d’information, la vérification, le traitement et la diffusion de l’information.</p>
<h2>Journaliste, communicant, créateur de contenus : qui fait quoi ?</h2>
<p>Ces activités peuvent utiliser les mêmes outils numériques, mais elles répondent à des finalités, des responsabilités et des pratiques professionnelles différentes.</p>
<h2>Comment vérifier une information ?</h2>
<p>Quelques réflexes simples :</p>
<ul>
<li>identifier la source ;</li>
<li>vérifier la date ;</li>
<li>rechercher l’origine de l’information ;</li>
<li>comparer plusieurs sources ;</li>
<li>distinguer un fait d’une opinion ;</li>
<li>se méfier des titres sensationnels ;</li>
<li>vérifier les images et vidéos lorsqu’elles sont utilisées comme preuve.</li>
</ul>
<h2>Comprendre les fausses informations</h2>
<p>Pourquoi circulent-elles ? Comment sont-elles produites ? Pourquoi sont-elles parfois difficiles à détecter ?</p>
<h2>Les médias à l’ère du numérique</h2>
<p>Les plateformes, les réseaux sociaux, les nouveaux formats et les nouveaux modes de consommation transforment profondément le secteur.</p>
HTML],
            'en' => ['title' => 'Understanding the media', 'slug' => 'understanding-the-media', 'body' => <<<'HTML'
<h2>Understanding the media to better understand information</h2>
<p>The media play an essential role in society. But between the press, radio, television, online media, social networks, journalists, producers, communicators and content creators, the roles can sometimes seem hard to tell apart. This space offers simple content to help you better understand this environment.</p>
<h2>What are the different types of media?</h2>
<p>Print press, radio, television, online media: discover their characteristics, how they work and what makes each one distinct.</p>
<h2>What is the role of a journalist?</h2>
<p>Understand journalistic work: gathering information, verification, processing and distributing information.</p>
<h2>Journalist, communicator, content creator: who does what?</h2>
<p>These activities may use the same digital tools, but they serve different purposes and involve different responsibilities and professional practices.</p>
<h2>How can you check a piece of information?</h2>
<p>A few simple habits:</p>
<ul>
<li>identify the source;</li>
<li>check the date;</li>
<li>look for where the information comes from;</li>
<li>compare several sources;</li>
<li>distinguish a fact from an opinion;</li>
<li>be wary of sensational headlines;</li>
<li>check images and videos when they are used as proof.</li>
</ul>
<h2>Understanding false information</h2>
<p>Why does it circulate? How is it produced? Why is it sometimes hard to detect?</p>
<h2>The media in the digital age</h2>
<p>Platforms, social networks, new formats and new ways of consuming content are profoundly transforming the sector.</p>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function professionals(): array
    {
        return [
            'key' => 'professionals',
            'parent' => null,
            'fr' => ['title' => 'Espace professionnels', 'slug' => 'espace-professionnels', 'body' => <<<'HTML'
<h2>Un espace pour les professionnels du secteur</h2>
<p>Cet espace rassemble les informations et ressources utiles aux professionnels des médias, de la presse, de l’audiovisuel, de la communication et aux acteurs des nouveaux usages numériques.</p>
<dl>
<dt>Formations</dt>
<dd>Formations, ateliers et actions de renforcement de capacités proposés ou accompagnés par le Ministère.</dd>
<dt><a href="/fr/programmes-et-projets">Projets</a></dt>
<dd>Projets qui concernent directement les professionnels du secteur.</dd>
<dt><a href="/fr/consultations">Consultations</a></dt>
<dd>Consultations, appels à contributions et cadres d’échanges ouverts aux acteurs.</dd>
<dt><a href="/fr/documents">Ressources</a></dt>
<dd>Guides, référentiels, textes, études et autres ressources professionnelles.</dd>
<dt>Répertoire des acteurs</dt>
<dd>Le futur répertoire permettra d’améliorer la connaissance des acteurs du secteur et de faciliter leur identification.</dd>
</dl>
<p>Le répertoire pourra intégrer, selon les critères et modalités retenus : médias, organes de presse, radios, télévisions, médias en ligne, professionnels de l’audiovisuel, producteurs, photographes, vidéastes, techniciens, agences et structures de communication, et autres acteurs professionnels du secteur.</p>
<p>Il sera articulé avec le Registre numérique des médias béninois et avec le futur dispositif de référencement que le Ministère retiendra.</p>
HTML],
            'en' => ['title' => 'Professionals’ area', 'slug' => 'professionals', 'body' => <<<'HTML'
<h2>A space for sector professionals</h2>
<p>This space brings together information and resources useful to professionals in the media, the press, audiovisual and communication, and to those working with new digital uses.</p>
<dl>
<dt>Training</dt>
<dd>Training, workshops and capacity-building actions offered or supported by the Ministry.</dd>
<dt><a href="/en/programmes-and-projects">Projects</a></dt>
<dd>Projects that directly concern sector professionals.</dd>
<dt><a href="/en/consultations">Consultations</a></dt>
<dd>Consultations, calls for contributions and discussion forums open to stakeholders.</dd>
<dt><a href="/en/documents">Resources</a></dt>
<dd>Guides, reference frameworks, texts, studies and other professional resources.</dd>
<dt>Directory of stakeholders</dt>
<dd>The future directory will improve knowledge of the sector’s stakeholders and make them easier to identify.</dd>
</dl>
<p>Depending on the criteria and arrangements adopted, the directory may include: media outlets, press organisations, radio stations, television channels, online media, audiovisual professionals, producers, photographers, videographers, technicians, communication agencies and structures, and other professional stakeholders in the sector.</p>
<p>It will be linked to the Digital Register of Beninese Media and to the future referencing system that the Ministry adopts.</p>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function contact(): array
    {
        return [
            'key' => 'contact',
            'parent' => null,
            'fr' => ['title' => 'Nous contacter', 'slug' => 'contact', 'body' => <<<'HTML'
<p>Une question, une demande d’information, une sollicitation professionnelle ? Le Ministère met à votre disposition plusieurs moyens pour entrer en contact avec ses services.</p>
<h2>Coordonnées</h2>
<table>
<tbody>
<tr><th scope="row">Ministère</th><td>Ministère de la Communication, en charge des Médias</td></tr>
<tr><th scope="row">Adresse</th><td>01 BP 120 Cotonou, Bénin</td></tr>
<tr><th scope="row">Téléphone</th><td><a href="tel:+2290121358066">+229 01 21 35 80 66</a></td></tr>
<tr><th scope="row">Courriel</th><td><a href="mailto:mc.contact@gouv.bj">mc.contact@gouv.bj</a></td></tr>
<tr><th scope="row">Horaires</th><td>[À VALIDER] À confirmer par le Secrétariat général</td></tr>
</tbody>
</table>
<h2>Contacter un service</h2>
<table>
<tbody>
<tr><th scope="row">Relations presse</th><td>[À VALIDER] Coordonnées à renseigner</td></tr>
<tr><th scope="row">Communication</th><td>[À VALIDER] Coordonnées à renseigner</td></tr>
<tr><th scope="row">Administration</th><td>[À VALIDER] Coordonnées à renseigner</td></tr>
<tr><th scope="row">Demandes institutionnelles</th><td>[À VALIDER] Coordonnées à renseigner</td></tr>
</tbody>
</table>
HTML],
            'en' => ['title' => 'Contact us', 'slug' => 'contact', 'body' => <<<'HTML'
<p>A question, a request for information, a professional enquiry? The Ministry offers several ways to get in touch with its services.</p>
<h2>Contact details</h2>
<table>
<tbody>
<tr><th scope="row">Ministry</th><td>Ministry of Communication, in charge of Media</td></tr>
<tr><th scope="row">Address</th><td>01 BP 120 Cotonou, Benin</td></tr>
<tr><th scope="row">Telephone</th><td><a href="tel:+2290121358066">+229 01 21 35 80 66</a></td></tr>
<tr><th scope="row">Email</th><td><a href="mailto:mc.contact@gouv.bj">mc.contact@gouv.bj</a></td></tr>
<tr><th scope="row">Opening hours</th><td>[À VALIDER] To be confirmed by the General Secretariat</td></tr>
</tbody>
</table>
<h2>Contact a department</h2>
<table>
<tbody>
<tr><th scope="row">Press relations</th><td>[À VALIDER] Contact details to be added</td></tr>
<tr><th scope="row">Communication</th><td>[À VALIDER] Contact details to be added</td></tr>
<tr><th scope="row">Administration</th><td>[À VALIDER] Contact details to be added</td></tr>
<tr><th scope="row">Institutional requests</th><td>[À VALIDER] Contact details to be added</td></tr>
</tbody>
</table>
HTML],
        ];
    }

    /** @return array<string, mixed> */
    private static function legal(string $key, string $titleFr, string $slugFr, string $titleEn, string $slugEn): array
    {
        return [
            'key' => $key,
            'parent' => null,
            'fr' => ['title' => $titleFr, 'slug' => $slugFr, 'body' => '<p>[À VALIDER] Texte à fournir par le Ministère (le document éditorial ne prévoit que l’intitulé de cette page).</p>'],
            'en' => ['title' => $titleEn, 'slug' => $slugEn, 'body' => '<p>[À VALIDER] Text to be provided by the Ministry (the editorial document only provides the title of this page).</p>'],
        ];
    }
}
