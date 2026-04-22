<?php
/**
 * includes/concours_data.php – Base de connaissances des concours burkinabè
 * 
 * Contient la liste des principaux concours de la fonction publique
 * au Burkina Faso avec leurs matières, coefficients, et exemples de questions.
 * 
 * Cette base est utilisée pour :
 * - Guider l'IA lors de la génération de quiz et examens blancs
 * - Afficher les filières disponibles dans l'application
 * - Personnaliser les contenus selon le concours ciblé
 */

/**
 * Retourne la liste complète des concours et leurs caractéristiques.
 */
function getConcoursList(): array {
    return [

        'police' => [
            'nom'          => 'Police Nationale (ENAPOSC)',
            'description'  => 'École Nationale de Police et de Sécurité de Ouagadougou',
            'emoji'        => '👮',
            'duree_prep'   => '6 mois recommandés',
            'matieres'     => [
                'Français'         => ['coeff' => 3, 'themes' => ['Expression écrite', 'Résumé de texte', 'Dictée orthographique', 'Compréhension de texte']],
                'Mathématiques'    => ['coeff' => 2, 'themes' => ['Arithmétique', 'Algèbre', 'Géométrie', 'Statistiques']],
                'Culture générale' => ['coeff' => 2, 'themes' => ['Histoire du BF', 'Géographie', 'Institutions BF', 'Actualités africaines']],
                'Droit'            => ['coeff' => 2, 'themes' => ['Droit constitutionnel', 'Droits de l\'homme', 'Procédure pénale']],
                'Éducation civique'=> ['coeff' => 1, 'themes' => ['Citoyenneté', 'Institutions de la République']],
            ],
            'examples_questions' => [
                'Citez les organes constitutionnels du Burkina Faso.',
                'Quelle est la différence entre crime, délit et contravention ?',
                'Définissez la présomption d\'innocence.',
                'Calculez l\'itinéraire optimal entre deux postes de contrôle (problème géométrique).',
                'Rédigez un rapport de surveillance de 200 mots à partir d\'éléments donnés.',
            ],
        ],

        'enaref' => [
            'nom'          => 'ENAREF (Fiscalité et Douanes)',
            'description'  => 'École Nationale des Régies Financières',
            'emoji'        => '🏦',
            'duree_prep'   => '8 mois recommandés',
            'matieres'     => [
                'Français'         => ['coeff' => 3, 'themes' => ['Rédaction administrative', 'Résumé', 'Vocabulaire fiscal']],
                'Mathématiques'    => ['coeff' => 3, 'themes' => ['Calcul commercial', 'Pourcentages', 'Statistiques', 'Algorithmes']],
                'Économie'         => ['coeff' => 3, 'themes' => ['Macroéconomie', 'Systèmes fiscaux', 'Commerce international', 'Budgets d\'État']],
                'Droit'            => ['coeff' => 2, 'themes' => ['Droit fiscal', 'Droit douanier', 'Droit commercial', 'Contentieux fiscal']],
                'Culture générale' => ['coeff' => 1, 'themes' => ['Géopolitique', 'Intégration régionale UEMOA/CEDEAO']],
                'Anglais'          => ['coeff' => 1, 'themes' => ['Vocabulaire commercial', 'Documents douaniers']],
            ],
            'examples_questions' => [
                'Expliquez la différence entre l\'impôt direct et l\'impôt indirect.',
                'Qu\'est-ce que la TVA ? Quel est son taux standard au Burkina Faso ?',
                'Calculez le montant de la taxe sur un bien importé valant 500 000 FCFA avec un tarif de 15%.',
                'Définissez le dumping et ses conséquences sur l\'économie nationale.',
                'Quelles sont les missions de la Direction Générale des Impôts au Burkina Faso ?',
            ],
        ],

        'enseignement_primaire' => [
            'nom'          => 'Enseignement Primaire (ENEP)',
            'description'  => 'École Nationale des Enseignants du Primaire',
            'emoji'        => '📚',
            'duree_prep'   => '4 mois recommandés',
            'matieres'     => [
                'Français'              => ['coeff' => 4, 'themes' => ['Grammaire', 'Orthographe', 'Expression écrite', 'Littérature africaine']],
                'Mathématiques'         => ['coeff' => 3, 'themes' => ['Arithmétique', 'Mesures', 'Géométrie élémentaire']],
                'Sciences de l\'éducation' => ['coeff' => 3, 'themes' => ['Pédagogie', 'Psychologie de l\'enfant', 'Didactique']],
                'Sciences naturelles'   => ['coeff' => 2, 'themes' => ['Biologie', 'Géographie physique', 'Sciences de la vie']],
                'Éducation civique'     => ['coeff' => 1, 'themes' => ['Valeurs civiques', 'Droits de l\'enfant']],
            ],
            'examples_questions' => [
                'Qu\'est-ce que la pédagogie par objectifs ? Donnez un exemple.',
                'Distinguez la méthode globale de la méthode syllabique en apprentissage de la lecture.',
                'Comment préparer une fiche de leçon pour une classe de CP1 en mathématiques ?',
                'Quel est le rôle des parents dans l\'éducation selon John Dewey ?',
                'Accordez les participes passés dans les phrases suivantes...',
            ],
        ],

        'enseignement_secondaire' => [
            'nom'          => 'Enseignement Secondaire (ENS)',
            'description'  => 'École Normale Supérieure de Koudougou',
            'emoji'        => '🎓',
            'duree_prep'   => '6 mois recommandés',
            'matieres'     => [
                'Spécialité'          => ['coeff' => 5, 'themes' => ['Selon la filière choisie (Maths, Français, SVT, Physique, Histoire-Géo, etc.)']],
                'Didactique'          => ['coeff' => 3, 'themes' => ['Élaboration de cours', 'Gestion de classe', 'Évaluation']],
                'Psychopédagogie'     => ['coeff' => 2, 'themes' => ['Développement cognitif', 'Motivation des élèves']],
                'Français'            => ['coeff' => 2, 'themes' => ['Expression écrite', 'Lecture critique']],
                'Culture générale BF' => ['coeff' => 1, 'themes' => ['Histoire', 'Institutions', 'Actualité']],
            ],
            'examples_questions' => [
                'Construisez une progression annuelle pour la matière [Spécialité] en classe de Terminale.',
                'Expliquez la théorie de Vygotsky sur la zone proximale de développement.',
                'Comment gérer un conflit en classe de manière constructive ?',
                'Proposez des activités d\'apprentissage actif pour un cours de physique-chimie.',
                'Qu\'est-ce que l\'évaluation formative ? Comment la mettre en pratique ?',
            ],
        ],

        'sante' => [
            'nom'          => 'Santé Publique',
            'description'  => 'Concours des professions de santé (infirmiers, sages-femmes, techniciens)',
            'emoji'        => '🏥',
            'duree_prep'   => '6 mois recommandés',
            'matieres'     => [
                'Biologie/Sciences'   => ['coeff' => 4, 'themes' => ['Anatomie', 'Physiologie', 'Microbiologie', 'Pharmacologie de base']],
                'Français'            => ['coeff' => 3, 'themes' => ['Rédaction de rapport médical', 'Résumé', 'Vocabulaire médical']],
                'Mathématiques'       => ['coeff' => 2, 'themes' => ['Calcul de dosages', 'Statistiques épidémiologiques']],
                'Culture générale'    => ['coeff' => 2, 'themes' => ['Santé publique en Afrique', 'Épidémiologie', 'OMS/ONG Santé']],
                'Chimie'              => ['coeff' => 2, 'themes' => ['Chimie des médicaments', 'Stérilisation', 'Biochimie']],
            ],
            'examples_questions' => [
                'Décrivez les étapes de la désinfection d\'un matériel médical.',
                'Quels sont les symptômes du paludisme ? Quel est le traitement recommandé par l\'OMS ?',
                'Calculez la dose d\'antibiotique pour un patient de 60 kg selon le protocole donné.',
                'Qu\'est-ce que la chaîne du froid dans le programme élargi de vaccination (PEV) ?',
                'Citez 5 maladies à déclaration obligatoire au Burkina Faso.',
            ],
        ],

        'douanes' => [
            'nom'          => 'Douanes Nationales',
            'description'  => 'Direction Générale des Douanes du Burkina Faso',
            'emoji'        => '🚛',
            'duree_prep'   => '6 mois recommandés',
            'matieres'     => [
                'Droit douanier'    => ['coeff' => 4, 'themes' => ['Code des douanes CEMAC/UEMOA', 'Régimes douaniers', 'Contentieux']],
                'Économie'          => ['coeff' => 3, 'themes' => ['Commerce international', 'Balance des paiements', 'UEMOA']],
                'Mathématiques'     => ['coeff' => 3, 'themes' => ['Calcul de droits de douane', 'Valeur en douane', 'Statistiques']],
                'Français'          => ['coeff' => 2, 'themes' => ['Rédaction administrative', 'Rapport d\'inspection']],
                'Anglais douanier'  => ['coeff' => 2, 'themes' => ['Documents d\'import/export', 'Incoterms', 'Nomenclature']],
            ],
            'examples_questions' => [
                'Quelle est la différence entre un entrepôt douanier et une zone franche ?',
                'Calculez la valeur en douane d\'une marchandise CIF de 2 000 000 FCFA avec un taux de 20%.',
                'Qu\'est-ce que la nomenclature tarifaire harmonisée (SH) ?',
                'Citez les infractions douanières et leurs sanctions.',
                'Expliquez le régime de l\'admission temporaire.',
            ],
        ],

        'eaux_forets' => [
            'nom'          => 'Eaux et Forêts / Environnement',
            'description'  => 'Agent des Eaux, Forêts et Chasse',
            'emoji'        => '🌿',
            'duree_prep'   => '4 mois recommandés',
            'matieres'     => [
                'Sciences naturelles'  => ['coeff' => 4, 'themes' => ['Botanique', 'Zoologie', 'Écologie', 'Pédologie']],
                'Droit de l\'environnement' => ['coeff' => 3, 'themes' => ['Code forestier BF', 'Code de l\'environnement', 'Conventions CITES']],
                'Géographie'           => ['coeff' => 2, 'themes' => ['Zones agro-écologiques', 'Désertification', 'Sahel']],
                'Français'             => ['coeff' => 2, 'themes' => ['Rapport de mission', 'PV de constat']],
                'Mathématiques'        => ['coeff' => 2, 'themes' => ['Statistiques forestières', 'Calcul de superficie']],
            ],
            'examples_questions' => [
                'Qu\'est-ce que la désertification ? Quelles en sont les causes au Sahel ?',
                'Définissez le Couvert Végétal National et son rôle écologique.',
                'Quelles sont les espèces d\'arbres protégées par le code forestier burkinabè ?',
                'Rédigez un procès-verbal de constat d\'une coupe illégale de bois.',
                'Expliquez le mécanisme REDD+ et son application en Afrique de l\'Ouest.',
            ],
        ],

        'magistrature' => [
            'nom'          => 'Magistrature (ENAM)',
            'description'  => 'École Nationale d\'Administration et de Magistrature',
            'emoji'        => '⚖️',
            'duree_prep'   => '12 mois recommandés',
            'matieres'     => [
                'Droit civil'       => ['coeff' => 5, 'themes' => ['Code civil', 'Droit des personnes', 'Droit des obligations', 'Droit des successions']],
                'Droit pénal'       => ['coeff' => 4, 'themes' => ['Infractions', 'Procédure pénale', 'Droit des victimes']],
                'Droit constitutionnel' => ['coeff' => 3, 'themes' => ['Constitution BF', 'Institutions', 'Libertés fondamentales']],
                'Droit administratif'   => ['coeff' => 3, 'themes' => ['Actes administratifs', 'Contentieux', 'Fonction publique']],
                'Droit OHADA'       => ['coeff' => 2, 'themes' => ['Actes uniformes', 'Droit commercial OHADA', 'Arbitrage']],
                'Français'          => ['coeff' => 2, 'themes' => ['Rédaction juridique', 'Dissertation', 'Cas pratique']],
            ],
            'examples_questions' => [
                'Distinguez l\'action en nullité et l\'action en résolution d\'un contrat.',
                'Quelles sont les conditions de validité d\'un contrat selon le droit OHADA ?',
                'Analysez le principe de la séparation des pouvoirs dans la Constitution du Burkina Faso.',
                'Qu\'est-ce que la présomption de paternité ? Quand peut-elle être renversée ?',
                'Rédigez une note juridique sur la responsabilité civile délictuelle.',
            ],
        ],

        'gendarmerie' => [
            'nom'          => 'Gendarmerie Nationale',
            'description'  => 'École Nationale de Gendarmerie',
            'emoji'        => '🎖️',
            'duree_prep'   => '5 mois recommandés',
            'matieres'     => [
                'Français'          => ['coeff' => 3, 'themes' => ['Rédaction', 'Résumé', 'Dictée']],
                'Mathématiques'     => ['coeff' => 2, 'themes' => ['Arithmétique', 'Logique', 'Géométrie']],
                'Droit'             => ['coeff' => 2, 'themes' => ['Procédure pénale', 'Droit public', 'Droits de l\'homme']],
                'Culture générale'  => ['coeff' => 2, 'themes' => ['Histoire militaire', 'Géopolitique africaine', 'Institutions BF']],
                'Éducation civique' => ['coeff' => 1, 'themes' => ['Valeurs républicaines', 'Déontologie']],
            ],
            'examples_questions' => [
                'Quelles sont les missions constitutionnelles de la Gendarmerie Nationale ?',
                'Qu\'est-ce qu\'un crime flagrant ? Quelles mesures un gendarme peut-il prendre ?',
                'Définissez la garde à vue et ses droits pour le gardé à vue.',
                'Citez les différentes subdivisions de la Gendarmerie au Burkina Faso.',
                'Rédigez un rapport de patrouille de sécurité routière.',
            ],
        ],

        'administration' => [
            'nom'          => 'Administration Générale (ENAM)',
            'description'  => 'Concours administratif général et affaires étrangères',
            'emoji'        => '🏛️',
            'duree_prep'   => '8 mois recommandés',
            'matieres'     => [
                'Droit public'       => ['coeff' => 4, 'themes' => ['Droit administratif', 'Droit constitutionnel', 'Finances publiques']],
                'Économie'           => ['coeff' => 3, 'themes' => ['Économie générale', 'Économie du développement', 'Politique économique']],
                'Français'           => ['coeff' => 3, 'themes' => ['Dissertation', 'Résumé', 'Rédaction administrative']],
                'Relations internationales' => ['coeff' => 2, 'themes' => ['Droit international', 'Organisations internationales', 'Diplomatie']],
                'Culture générale'   => ['coeff' => 2, 'themes' => ['Histoire contemporaine', 'Géopolitique', 'Développement durable']],
            ],
            'examples_questions' => [
                'Qu\'est-ce que la décentralisation ? Quels sont ses avantages pour le Burkina Faso ?',
                'Analysez le budget de l\'État burkinabè et ses grands équilibres.',
                'Définissez le service public et ses principes (continuité, égalité, adaptabilité).',
                'Quelles sont les grandes orientations du PNDES (Plan National de Développement Économique et Social) ?',
                'Rédigez une note à l\'attention du Director général sur un sujet de gouvernance.',
            ],
        ],

    ];
}

/**
 * Retourne les infos d'un concours spécifique.
 * Si le concours n'existe pas, retourne des infos génériques.
 */
function getConcoursInfo(string $concoursKey): array {
    $liste = getConcoursList();
    
    if (isset($liste[$concoursKey])) {
        return $liste[$concoursKey];
    }
    
    // Concours générique si non trouvé
    return [
        'nom'         => 'Concours Général de la Fonction Publique',
        'description' => 'Préparation générale aux concours burkinabè',
        'emoji'       => '📋',
        'matieres'    => [
            'Français'         => ['coeff' => 3, 'themes' => ['Expression écrite', 'Résumé']],
            'Mathématiques'    => ['coeff' => 2, 'themes' => ['Calcul', 'Logique']],
            'Culture générale' => ['coeff' => 2, 'themes' => ['Histoire', 'Géographie', 'Institutions']],
        ],
        'examples_questions' => [
            'Décrivez les institutions de la République du Burkina Faso.',
            'Calculez le pourcentage d\'évolution d\'un indicateur économique donné.',
            'Rédigez un texte argumentatif sur le rôle de la jeunesse dans le développement du BF.',
        ],
    ];
}

/**
 * Génère un contexte de prompt pour guider l'IA selon le concours.
 */
function buildConcoursContext(string $concoursKey, string $matiere = ''): string {
    $info = getConcoursInfo($concoursKey);
    
    $matieresList = [];
    foreach ($info['matieres'] as $nom => $detail) {
        $coeff        = $detail['coeff'];
        $themes       = implode(', ', $detail['themes']);
        $matieresList[] = "- {$nom} (coeff. {$coeff}) : {$themes}";
    }
    
    $matieresText  = implode("\n", $matieresList);
    $exemplesList  = implode("\n- ", $info['examples_questions']);
    $matiereTarget = !empty($matiere) ? "\nFOCUS SUR LA MATIÈRE : {$matiere}" : '';
    
    return <<<CONTEXT
CONCOURS CIBLÉ : {$info['nom']}
{$info['description']}
{$matiereTarget}

MATIÈRES ET COEFFICIENTS :
{$matieresText}

EXEMPLES DE QUESTIONS TYPIQUES DE CE CONCOURS :
- {$exemplesList}

Génère du contenu adapté à ce niveau et à ce concours spécifiquement.
CONTEXT;
}
