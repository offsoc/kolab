<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in the application.
    */

    'chart-created' => "Crée",
    'chart-deleted' => "Supprimé",
    'chart-average' => "moyenne",
    'chart-allusers' => "Tous Utilisateurs - l'année derniere",
    'chart-discounts' => "Rabais",
    'chart-vouchers' => "Coupons",
    'chart-income' => "Revenus en :currency - 8 dernières semaines",
    'chart-users' => "Utilisateurs - 8 dernières semaines",

    'mandate-delete-success' => "L'auto-paiement a été supprimé.",
    'mandate-update-success' => "L'auto-paiement a été mis-à-jour.",

    'planbutton' => "Choisir :plan",
    'siteuser' => "Utilisateur du :site",
    'domain-setconfig-success' => "Les paramètres du domaine sont mis à jour avec succès.",
    'user-setconfig-success' => "Les paramètres d'utilisateur sont mis à jour avec succès.",

    'process-async' => "Le processus d'installation a été poussé. Veuillez patienter.",
    'process-user-new' => "Enregistrement d'un utilisateur...",
    'process-user-ldap-ready' => "Création d'un utilisateur...",
    'process-user-imap-ready' => "Création d'une boîte aux lettres...",
    'process-distlist-new' => "Enregistrement d'une liste de distribution...",
    'process-distlist-ldap-ready' => "Création d'une liste de distribution...",
    'process-domain-new' => "Enregistrement d'un domaine personnalisé...",
    'process-domain-ldap-ready' => "Création d'un domaine personnalisé...",
    'process-domain-verified' => "Vérification d'un domaine personnalisé...",
    'process-domain-confirmed' => "vérification de la propriété d'un domaine personnalisé...",
    'process-success' => "Le processus d'installation s'est terminé avec succès.",
    'process-error-domain-ldap-ready' => "Échec de créer un domaine.",
    'process-error-domain-verified' => "Échec de vérifier un domaine.",
    'process-error-domain-confirmed' => "Échec de la vérification de la propriété d'un domaine.",
    'process-error-distlist-ldap-ready' => "Échec de créer une liste de distrubion.",
    'process-error-resource-imap-ready' => "Échec de la vérification de l'existence d'un dossier partagé.",
    'process-error-resource-ldap-ready' => "Échec de la création d'une ressource.",
    'process-error-shared-folder-imap-ready' => "Impossible de vérifier qu'un dossier partagé existe.",
    'process-error-shared-folder-ldap-ready' => "Échec de la création d'un dossier partagé.",
    'process-error-user-ldap-ready' => "Échec de la création d'un utilisateur.",
    'process-error-user-imap-ready' => "Échec de la vérification de l'existence d'une boîte aux lettres.",
    'process-resource-new' => "Enregistrement d'une ressource...",
    'process-resource-imap-ready' => "Création d'un dossier partagé...",
    'process-resource-ldap-ready' => "Création d'un ressource...",
    'process-shared-folder-new' => "Enregistrement d'un dossier partagé...",
    'process-shared-folder-imap-ready' => "Création d'un dossier partagé...",
    'process-shared-folder-ldap-ready' => "Création d'un dossier partagé...",

    'distlist-update-success' => "Liste de distribution mis-à-jour avec succès.",
    'distlist-create-success' => "Liste de distribution créer avec succès.",
    'distlist-delete-success' => "Liste de distribution suppriméee avec succès.",
    'distlist-suspend-success' => "Liste de distribution à été suspendue avec succès.",
    'distlist-unsuspend-success' => "Liste de distribution à été débloquée avec succès.",
    'distlist-setconfig-success' => "Mise à jour des paramètres de la liste de distribution avec succès.",

    'domain-create-success' => "Domaine a été crée avec succès.",
    'domain-delete-success' => "Domaine supprimé avec succès.",
    'domain-verify-success' => "Domaine vérifié avec succès.",
    'domain-verify-error' => "Vérification de propriété de domaine à échoué.",
    'domain-suspend-success' => "Domaine suspendue avec succès.",
    'domain-unsuspend-success' => "Domaine debloqué avec succès.",

    'resource-update-success' => "Ressource mise à jour avec succès.",
    'resource-create-success' => "Resource crée avec succès.",
    'resource-delete-success' => "Ressource suprimmée avec succès.",
    'resource-setconfig-success' => "Les paramètres des ressources ont été mis à jour avec succès.",

    'shared-folder-update-success' => "Dossier partagé mis à jour avec succès.",
    'shared-folder-create-success' => "Dossier partagé créé avec succès.",
    'shared-folder-delete-success' => "Dossier partagé supprimé avec succès.",
    'shared-folder-setconfig-success' => "Mise à jour des paramètres du dossier partagé avec succès.",

    'user-update-success' => "Mis-à-jour des données de l'utilsateur effectué avec succès.",
    'user-create-success' => "Utilisateur a été crée avec succès.",
    'user-delete-success' => "Utilisateur a été supprimé avec succès.",
    'user-suspend-success' => "Utilisateur a été suspendu avec succès.",
    'user-unsuspend-success' => "Utilisateur a été debloqué avec succès.",
    'user-reset-2fa-success' => "Réinstallation de l'authentification à 2-Facteur avec succès.",
    'user-set-sku-success' => "Souscription ajoutée avec succès.",
    'user-set-sku-already-exists' => "La souscription existe déjà.",

    'search-foundxdomains' => "Les domaines :x ont été trouvés.",
    'search-foundxdistlists' => "Les listes de distribution :x ont été trouvés.",
    'search-foundxresources' => "Les ressources :x ont été trouvés.",
    'search-foundxusers' => "Les comptes d'utilisateurs :x ont été trouvés.",
    'search-foundxshared-folders' => ":x dossiers partagés ont été trouvés.",

    'signup-invitations-created' => "L'invitation à été crée.|:count nombre d'invitations ont été crée.",
    'signup-invitations-csv-empty' => "Aucune adresses email valides ont été trouvées dans le fichier téléchargé.",
    'signup-invitations-csv-invalid-email' => "Une adresse email invalide a été trouvée (:email) on line :line.",
    'signup-invitation-delete-success' => "Invitation supprimée avec succès.",
    'signup-invitation-resend-success' => "Invitation ajoutée à la file d'attente d'envoi avec succès.",

    'support-request-success' => "Demande de soutien soumise avec succès.",
    'support-request-error' => "La soumission de demande de soutien a échoué.",

    'wallet-award-success' => "Le bonus a été ajouté au portefeuille avec succès.",
    'wallet-penalty-success' => "La pénalité a été ajoutée au portefeuille avec succès.",
    'wallet-update-success' => "Portefeuille d'utilisateur a été mis-à-jour avec succès.",

    'wallet-notice-date' => "Avec vos abonnements actuels, le solde de votre compte durera jusqu'à environ :date (:days).",
    'wallet-notice-nocredit' => "Votre crédit a été epuisé, veuillez recharger immédiatement votre solde.",
    'wallet-notice-today' => "Votre reste crédit sera épuisé aujourd'hui, veuillez recharger immédiatement.",
    'wallet-notice-trial' => "Vous êtes dans votre période d'essai gratuite.",
    'wallet-notice-trial-end' => "Vous approchez de la fin de votre période d'essai gratuite, veuillez recharger pour continuer.",
];
