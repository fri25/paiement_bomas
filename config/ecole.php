<?php

declare(strict_types=1);

// Configuration métier de l'école : c'est ce fichier qu'on modifie à chaque rentrée.
// Les montants sont en FCFA (entiers) et ne sont JAMAIS repris du navigateur : le serveur les recalcule.
return [
    'nom'       => 'École Primaire Privé Sainte Josephine de Bomas',
    'annee'     => '2026-2027',
    'telephone' => '01 97 11 22 11',   // affiché en bas de page ('' pour masquer)

    'classes' => ['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2'],

    // par_eleve: true => montant multiplié par le nombre d'enfants
    'contributions' => [
        ['id' => 'annuelle',  'libelle' => 'Contribution annuelle',     'detail' => "Toute l'année scolaire", 'montant' => 15000, 'par_eleve' => true],
        ['id' => 'trimestre', 'libelle' => 'Contribution du trimestre', 'detail' => '1er trimestre',          'montant' => 5000,  'par_eleve' => true],
    ],

    'montant_libre' => [
        'actif'   => true,
        'libelle' => 'Autre montant',
        'detail'  => 'Don ou complément, au choix',
        'minimum' => 500,
        'maximum' => 1000000,
    ],

    'max_eleves' => 6,
];
