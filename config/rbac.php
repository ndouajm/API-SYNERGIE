<?php

return [
    'permissions' => [
        'dashboard.view' => 'Consulter le tableau de bord',
        'adhesions.view' => 'Consulter les adhésions',
        'documents.view' => 'Consulter les pièces jointes',
        'adhesions.update' => 'Modifier les adhésions',
        'adhesions.delete' => 'Supprimer les adhésions',
        'adhesions.export' => 'Extraire les adhésions',
        'links.view' => 'Consulter les liens',
        'links.create' => 'Générer des liens',
        'links.update' => 'Activer ou désactiver les liens',
        'users.manage' => 'Gérer les utilisateurs et les rôles',
    ],
    'roles' => [
        'admin' => [
            'name' => 'Administrateur',
            'description' => 'Accès complet à toutes les fonctions du bureau.',
            'permissions' => ['*'],
        ],
        'adhesion_viewer' => [
            'name' => 'Consultation des adhésions',
            'description' => 'Consulter les informations des dossiers sans accéder aux pièces.',
            'permissions' => ['adhesions.view'],
        ],
        'document_viewer' => [
            'name' => 'Consultation des pièces',
            'description' => 'Consulter les adhésions et leurs pièces jointes.',
            'permissions' => ['adhesions.view', 'documents.view'],
        ],
        'adhesion_editor' => [
            'name' => 'Modification des adhésions',
            'description' => 'Consulter et modifier les informations des dossiers.',
            'permissions' => ['adhesions.view', 'adhesions.update'],
        ],
        'adhesion_deleter' => [
            'name' => 'Suppression des adhésions',
            'description' => 'Consulter et supprimer les dossiers et leurs pièces jointes.',
            'permissions' => ['adhesions.view', 'adhesions.delete'],
        ],
        'adhesion_exporter' => [
            'name' => 'Extraction des adhésions',
            'description' => 'Consulter et extraire les informations des dossiers.',
            'permissions' => ['adhesions.view', 'adhesions.export'],
        ],
        'link_manager' => [
            'name' => 'Gestion des liens',
            'description' => 'Consulter, générer et activer ou désactiver les liens.',
            'permissions' => ['links.view', 'links.create', 'links.update'],
        ],
    ],
];
