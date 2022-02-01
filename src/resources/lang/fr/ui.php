<?php

/**
 * This file will be converted to a Vue-i18n compatible JSON format on build time
 *
 * Note: The Laravel localization features do not work here. Vue-i18n rules are different
 */

return [

    'app' => [
        'faq' => "FAQ",
    ],

    'btn' => [
        'add' => "Ajouter",
        'accept' => "Accepter",
        'back' => "Back",
        'cancel' => "Annuler",
        'close' => "Fermer",
        'continue' => "Continuer",
        'delete' => "Supprimer",
        'deny' => "Refuser",
        'download' => "Télécharger",
        'edit' => "Modifier",
        'file' => "Choisir le ficher...",
        'moreinfo' => "Plus d'information",
        'refresh' => "Actualiser",
        'reset' => "Réinitialiser",
        'resend' => "Envoyer à nouveau",
        'save' => "Sauvegarder",
        'search' => "Chercher",
        'signup' => "S'inscrire",
        'submit' => "Soumettre",
        'suspend' => "Suspendre",
        'unsuspend' => "Débloquer",
        'verify' => "Vérifier",
    ],

    'dashboard' => [
        'beta' => "bêta",
        'distlists' => "Listes de distribution",
        'chat' => "Chat Vidéo",
        'domains' => "Domaines",
        'invitations' => "Invitations",
        'profile' => "Votre profil",
        'resources' => "Ressources",
        'users' => "D'utilisateurs",
        'wallet' => "Portefeuille",
        'webmail' => "Webmail",
        'stats' => "Statistiques",
    ],

    'distlist' => [
        'list-title' => "Liste de distribution | Listes de Distribution",
        'create' => "Créer une liste",
        'delete' => "Suprimmer une list",
        'email' => "Courriel",
        'list-empty' => "il n'y a pas de listes de distribution dans ce compte.",
        'name' => "Nom",
        'new' => "Nouvelle liste de distribution",
        'recipients' => "Destinataires",
        'sender-policy' => "Liste d'Accès d'Expéditeur",
        'sender-policy-text' => "Cette liste vous permet de spécifier qui peut envoyer du courrier à la liste de distribution."
            . " Vous pouvez mettre une adresse e-mail complète (jane@kolab.org), un domaine (kolab.org) ou un suffixe (.org)"
            . " auquel l'adresse électronique de l'expéditeur est assimilée."
            . " Si la liste est vide, le courriels de quiconque est autorisé."
    ],

    'domain' => [
        'dns-verify' => "Exemple de vérification du DNS d'un domaine:",
        'dns-config' => "Exemple de configuration du DNS d'un domaine:",
        'namespace' => "Espace de noms",
        'verify' => "Vérification du domaine",
        'verify-intro' => "Afin de confirmer que vous êtes bien le titulaire du domaine, nous devons exécuter un processus de vérification avant de l'activer définitivement pour la livraison d'e-mails.",
        'verify-dns' => "Le domaine <b>doit avoir l'une des entrées suivantes</b> dans le DNS:",
        'verify-dns-txt' => "Entrée TXT avec valeur:",
        'verify-dns-cname' => "ou entrée CNAME:",
        'verify-outro' => "Lorsque cela est fait, appuyez sur le bouton ci-dessous pour lancer la vérification.",
        'verify-sample' => "Voici un fichier de zone simple pour votre domaine:",
        'config' => "Configuration du domaine",
        'config-intro' => "Afin de permettre à {app} de recevoir le trafic de messagerie pour votre domaine, vous devez ajuster les paramètres DNS, plus précisément les entrées MX, en conséquence.",
        'config-sample' => "Modifiez le fichier de zone de votre domaine et remplacez les entrées MX existantes par les valeurs suivantes:",
        'config-hint' => "Si vous ne savez pas comment définir les entrées DNS pour votre domaine, veuillez contacter le service d'enregistrement auprès duquel vous avez enregistré le domaine ou votre fournisseur d'hébergement Web.",
        'spf-whitelist' => "SPF Whitelist",
        'spf-whitelist-text' => "Le Sender Policy Framework permet à un domaine expéditeur de dévoiler, par le biais de DNS,"
            . " quels systèmes sont autorisés à envoyer des e-mails avec une adresse d'expéditeur d'enveloppe dans le domaine en question.",
        'spf-whitelist-ex' => "Vous pouvez ici spécifier une liste de serveurs autorisés, par exemple: <var>.ess.barracuda.com</var>.",
        'create' => "Créer domaine",
        'new' => "Nouveau domaine",
        'delete' => "Supprimer domaine",
        'delete-domain' => "Supprimer {domain}",
        'delete-text' => "Voulez-vous vraiment supprimer ce domaine de façon permanente?"
            . " Ceci n'est possible que s'il n'y a pas d'utilisateurs, d'alias ou d'autres objets dans ce domaine."
            . " Veuillez noter que cette action ne peut pas être inversée.",
    ],

    'error' => [
        '400' => "Mauvaide demande",
        '401' => "Non autorisé",
        '403' => "Accès refusé",
        '404' => "Pas trouvé",
        '405' => "Méthode non autorisée",
        '500' => "Erreur de serveur interne",
        'unknown' => "Erreur inconnu",
        'server' => "Erreur de serveur",
        'form' => "Erreur de validation du formulaire",
    ],

    'form' => [
        'acl' => "Droits d'accès",
        'acl-full' => "Tout",
        'acl-read-only' => "Lecture seulement",
        'acl-read-write' => "Lecture-écriture",
        'amount' => "Montant",
        'anyone' => "Chacun",
        'code' => "Le code de confirmation",
        'config' => "Configuration",
        'date' => "Date",
        'description' => "Description",
        'details' => "Détails",
        'domain' => "Domaine",
        'email' => "Adresse e-mail",
        'firstname' => "Prénom",
        'lastname' => "Nom de famille",
        'none' => "aucun",
        'or' => "ou",
        'password' => "Mot de passe",
        'password-confirm' => "Confirmer le mot de passe",
        'phone' => "Téléphone",
        'shared-folder' => "Dossier partagé",
        'status' => "État",
        'surname' => "Nom de famille",
        'type' => "Type",
        'user' => "Utilisateur",
        'primary-email' => "Email principal",
        'id' => "ID",
        'created' => "Créé",
        'deleted' => "Supprimé",
        'disabled' => "Désactivé",
        'enabled' => "Activé",
        'general' => "Général",
        'settings' => "Paramètres",
    ],

    'invitation' => [
        'create' => "Créez des invitation(s)",
        'create-title' => "Invitation à une inscription",
        'create-email' => "Saisissez l'adresse électronique de la personne que vous souhaitez inviter.",
        'create-csv' => "Pour envoyer plusieurs invitations à la fois, fournissez un fichier CSV (séparé par des virgules) ou un fichier en texte brut, contenant une adresse e-mail par ligne.",
        'empty-list' => "Il y a aucune invitation dans la mémoire de données.",
        'title' => "Invitation d'inscription",
        'search' => "Adresse E-mail ou domaine",
        'send' => "Envoyer invitation(s)",
        'status-completed' => "Utilisateur s'est inscrit",
        'status-failed' => "L'envoi a échoué",
        'status-sent' => "Envoyé",
        'status-new' => "Pas encore envoyé",
    ],

    'lang' => [
        'en' => "Anglais",
        'de' => "Allemand",
        'fr' => "Français",
        'it' => "Italien",
    ],

    'login' => [
        '2fa' => "Code du 2ème facteur",
        '2fa_desc' => "Le code du 2ème facteur est facultatif pour les utilisateurs qui n'ont pas configuré l'authentification à deux facteurs.",
        'forgot_password' => "Mot de passe oublié?",
        'header' => "Veuillez vous connecter",
        'sign_in' => "Se connecter",
        'webmail' => "Webmail"
    ],

    'meet' => [
        'title' => "Voix et vidéo-conférence",
        'welcome' => "Bienvenue dans notre programme bêta pour les conférences vocales et vidéo.",
        'url' => "Vous disposez d'une salle avec l'URL ci-dessous. Cette salle ouvre uniquement quand vous y êtes vous-même. Utilisez cette URL pour inviter des personnes à vous rejoindre.",
        'notice' => "Il s'agit d'un travail en évolution et d'autres fonctions seront ajoutées au fil du temps. Les fonctions actuelles sont les suivantes:",
        'sharing' => "Partage d'écran",
        'sharing-text' => "Partagez votre écran pour des présentations ou des exposés.",
        'security' => "sécurité de chambre",
        'security-text' => "Renforcez la sécurité de la salle en définissant un mot de passe que les participants devront connaître."
            . " avant de pouvoir entrer, ou verrouiller la porte afin que les participants doivent frapper, et un modérateur peut accepter ou refuser ces demandes.",
        'qa-title' => "Lever la main (Q&A)",
        'qa-text' => "Les membres du public silencieux peuvent lever la main pour animer une séance de questions-réponses avec les membres du panel.",
        'moderation' => "Délégation des Modérateurs",
        'moderation-text' => "Déléguer l'autorité du modérateur pour la séance, afin qu'un orateur ne soit pas inutilement"
            . " interrompu par l'arrivée des participants et d'autres tâches du modérateur.",
        'eject' => "Éjecter les participants",
        'eject-text' => "Éjectez les participants de la session afin de les obliger à se reconnecter ou de remédier aux violations des règles."
            . " Cliquez sur l'icône de l'utilisateur pour un renvoi effectif.",
        'silent' => "Membres du Public en Silence",
        'silent-text' => "Pour une séance de type webinaire, configurez la salle pour obliger tous les nouveaux participants à être des spectateurs silencieux.",
        'interpreters' => "Canaux d'Audio Spécifiques de Langues",
        'interpreters-text' => "Désignez un participant pour interpréter l'audio original dans une langue cible, pour les sessions avec des participants multilingues."
            . " L'interprète doit être capable de relayer l'audio original et de le remplacer.",
        'beta-notice' => "Rappelez-vous qu'il s'agit d'une version bêta et pourrait entraîner des problèmes."
            . " Au cas où vous rencontreriez des problèmes, n'hésitez pas à nous en faire part en contactant le support.",

        // Room options dialog
        'options' => "Options de salle",
        'password' => "Mot de passe",
        'password-none' => "aucun",
        'password-clear' => "Effacer mot de passe",
        'password-set' => "Définir le mot de passe",
        'password-text' => "Vous pouvez ajouter un mot de passe à votre session. Les participants devront fournir le mot de passe avant d'être autorisés à rejoindre la session.",
        'lock' => "Salle verrouillée",
        'lock-text' => "Lorsque la salle est verrouillée, les participants doivent être approuvés par un modérateur avant de pouvoir rejoindre la réunion.",
        'nomedia' => "Réservé aux abonnés",
        'nomedia-text' => "Force tous les participants à se joindre en tant qu'abonnés (avec caméra et microphone désactivés)"
            . "Les modérateurs pourront les promouvoir en tant qu'éditeurs tout au long de la session.",

        // Room menu
        'partcnt' => "Nombres de participants",
        'menu-audio-mute' => "Désactiver le son",
        'menu-audio-unmute' => "Activer le son",
        'menu-video-mute' => "Désactiver la vidéo",
        'menu-video-unmute' => "Activer la vidéo",
        'menu-screen' => "Partager l'écran",
        'menu-hand-lower' => "Baisser la main",
        'menu-hand-raise' => "Lever la main",
        'menu-channel' => "Canal de langue interprétée",
        'menu-chat' => "Le Chat",
        'menu-fullscreen' => "Plein écran",
        'menu-fullscreen-exit' => "Sortir en plein écran",
        'menu-leave' => "Quitter la session",

        // Room setup screen
        'setup-title' => "Préparez votre session",
        'mic' => "Microphone",
        'cam' => "Caméra",
        'nick' => "Surnom",
        'nick-placeholder' => "Votre nom",
        'join' => "JOINDRE",
        'joinnow' => "JOINDRE MAINTENANT",
        'imaowner' => "Je suis le propriétaire",

        // Room
        'qa' => "Q & A",
        'leave-title' => "Salle fermée",
        'leave-body' => "La session a été fermée par le propriétaire de la salle.",
        'media-title' => "Configuration des médias",
        'join-request' => "Demande de rejoindre",
        'join-requested' => "{user} demandé à rejoindre.",

        // Status messages
        'status-init' => "Vérification de la salle...",
        'status-323' => "La salle est fermée. Veuillez attendre le démarrage de la session par le propriétaire.",
        'status-324' => "La salle est fermée. Elle sera ouverte aux autres participants après votre adhésion.",
        'status-325' => "La salle est prête. Veuillez entrer un mot de passe valide.",
        'status-326' => "La salle est fermée. Veuillez entrer votre nom et réessayer.",
        'status-327' => "En attendant la permission de joindre la salle.",
        'status-404' => "La salle n'existe pas.",
        'status-429' => "Trop de demande. Veuillez, patienter.",
        'status-500' => "La connexion à la salle a échoué. Erreur de serveur.",

        // Other menus
        'media-setup' => "configuration des médias",
        'perm' => "Permissions",
        'perm-av' => "Publication d'audio et vidéo",
        'perm-mod' => "Modération",
        'lang-int' => "Interprète de langue",
        'menu-options' => "Options",
    ],

    'menu' => [
        'cockpit' => "Cockpit",
        'login' => "Connecter",
        'logout' => "Deconnecter",
        'signup' => "S'inscrire",
        'toggle' => "Basculer la navigation",
    ],

    'msg' => [
        'initializing' => "Initialisation...",
        'loading' => "Chargement...",
        'loading-failed' => "Échec du chargement des données.",
        'notfound' => "Resource introuvable.",
        'info' => "Information",
        'error' => "Erreur",
        'warning' => "Avertissement",
        'success' => "Succès",
    ],

    'nav' => [
        'more' => "Charger plus",
        'step' => "Étape {i}/{n}",
    ],

    'password' => [
        'reset' => "Réinitialiser le mot de passe",
        'reset-step1' => "Entrez votre adresse e-mail pour réinitialiser votre mot de passe.",
        'reset-step1-hint' => "Veuillez vérifier votre dossier de spam ou débloquer {email}.",
        'reset-step2' => "Nous avons envoyé un code de confirmation à votre adresse e-mail externe."
            . " Entrez le code que nous vous avons envoyé, ou cliquez sur le lien dans le message.",
    ],

    'resource' => [
        'create' => "Créer une ressource",
        'delete' => "Supprimer une ressource",
        'invitation-policy' => "Procédure d'invitation",
        'invitation-policy-text' => "Les invitations à des événements pour une ressource sont généralement acceptées automatiquement"
            . " si aucun événement n'est en conflit avec le temps demandé. La procédure d'invitation le permet"
            . " de rejeter ces demandes ou d'exiger une acceptation manuelle d'un utilisateur spécifique.",
        'ipolicy-manual' => "Manuel (provisoire)",
        'ipolicy-accept' => "Accepter",
        'ipolicy-reject' => "Rejecter",
        'list-title' => "Ressource | Ressources",
        'list-empty' => "Il y a aucune ressource sur ce compte.",
        'new' => "Nouvelle ressource",
    ],

    'shf' => [
        'create' => "Créer un dossier",
        'delete' => "Supprimer un dossier",
        'acl-text' => "Permet de définir les droits d'accès des utilisateurs au dossier partagé..",
        'list-title' => "Dossier partagé | Dossiers partagés",
        'list-empty' => "Il y a aucun dossier partagé dans ce compte.",
        'new' => "Nouvelle dossier",
        'type-mail' => "Courriel",
        'type-event' => "Calendrier",
        'type-contact' => "Carnet d'Adresses",
        'type-task' => "Tâches",
        'type-note' => "Notes",
        'type-file' => "Fichiers",
    ],

    'signup' => [
        'email' => "Adresse e-mail actuelle",
        'login' => "connecter",
        'title' => "S'inscrire",
        'step1' => "Inscrivez-vous pour commencer votre mois gratuit.",
        'step2' => "Nous avons envoyé un code de confirmation à votre adresse e-mail. Entrez le code que nous vous avons envoyé, ou cliquez sur le lien dans le message.",
        'step3' => "Créez votre identité Kolab (vous pourrez choisir des adresses supplémentaires plus tard).",
        'voucher' => "Coupon Code",
    ],

    'status' => [
        'prepare-account' => "Votre compte est en cours de préparation.",
        'prepare-domain' => "Le domain est en cours de préparation.",
        'prepare-distlist' => "La liste de distribution est en cours de préparation.",
        'prepare-shared-folder' => "Le dossier portagé est en cours de préparation.",
        'prepare-user' => "Le compte d'utilisateur est en cours de préparation.",
        'prepare-hint' => "Certaines fonctionnalités peuvent être manquantes ou en lecture seule pour le moment.",
        'prepare-refresh' => "Le processus ne se termine jamais? Appuyez sur le bouton \"Refresh\", s'il vous plaît.",
        'prepare-resource' => "Nous préparons la ressource.",
        'ready-account' => "Votre compte est presque prêt.",
        'ready-domain' => "Le domaine est presque prêt.",
        'ready-distlist' => "La liste de distribution est presque prête.",
        'ready-resource' => "La ressource est presque prête.",
        'ready-shared-folder' => "Le dossier partagé est presque prêt.",
        'ready-user' => "Le compte d'utilisateur est presque prêt.",
        'verify' => "Veuillez vérifier votre domaine pour terminer le processus de configuration.",
        'verify-domain' => "Vérifier domaine",
        'degraded' => "Dégradé",
        'deleted' => "Supprimé",
        'suspended' => "Suspendu",
        'notready' => "Pas Prêt",
        'active' => "Actif",
    ],

    'support' => [
        'title' => "Contacter Support",
        'id' => "Numéro de client ou adresse é-mail que vous avez chez nous.",
        'id-pl' => "e.g. 12345678 ou john@kolab.org",
        'id-hint' => "Laissez vide si vous n'êtes pas encore client",
        'name' => "Nom",
        'name-pl' => "comment nous devons vous adresser dans notre réponse",
        'email' => "adresse e-mail qui fonctionne",
        'email-pl' => "assurez-vous que nous pouvons vous atteindre à cette adresse",
        'summary' => "Résumé du problème",
        'summary-pl' => "une phrase qui résume votre situation",
        'expl' => "Analyse du problème",
    ],

    'user' => [
        '2fa-hint1' => "Cela éliminera le droit à l'authentification à 2-Facteurs ainsi que les éléments configurés par l'utilisateur.",
        '2fa-hint2' => "Veuillez vous assurer que l'identité de l'utilisateur est correctement confirmée.",
        'add-beta' => "Activer le programme bêta",
        'address' => "Adresse",
        'aliases' => "Alias",
        'aliases-email' => "Alias E-mail",
        'aliases-none' => "Cet utilisateur n'aucune alias e-mail.",
        'add-bonus' => "Ajouter un bonus",
        'add-bonus-title' => "Ajouter un bonus au portefeuille",
        'add-penalty' => "Ajouter une pénalité",
        'add-penalty-title' => "Ajouter une pénalité au portefeuille",
        'auto-payment' => "Auto-paiement",
        'auto-payment-text' => "Recharger par <b>{amount}</b> quand le montant est inférieur à <b>{balance}</b> utilisant {method}",
        'country' => "Pays",
        'create' => "Créer un utilisateur",
        'custno' => "No. de Client.",
        'degraded-warning' => "Le compte est dégradé. Certaines fonctionnalités ont été désactivées.",
        'degraded-hint' => "Veuillez effectuer un paiement.",
        'delete' => "Supprimer Utilisateur",
        'delete-email' => "Supprimer {email}",
        'delete-text' => "Voulez-vous vraiment supprimer cet utilisateur de façon permanente?"
            . " Cela supprimera toutes les données du compte et retirera la permission d'accéder au compte d'e-email."
            . " Veuillez noter que cette action ne peut pas être révoquée.",
        'discount' => "Rabais",
        'discount-hint' => "rabais appliqué",
        'discount-title' => "Rabais de compte",
        'distlists' => "Listes de Distribution",
        'domains' => "Domaines",
        'domains-none' => "Il y a pas de domaines dans ce compte.",
        'ext-email' => "E-mail externe",
        'finances' => "Finances",
        'greylisting' => "Greylisting",
        'greylisting-text' => "La greylisting est une méthode de défense des utilisateurs contre le spam."
            . " Tout e-mail entrant provenant d'un expéditeur non reconnu est temporairement rejeté."
            . " Le serveur d'origine doit réessayer après un délai cette fois-ci, le mail sera accepté."
            . " Les spammeurs ne réessayent généralement pas de remettre le mail.",
        'list-title' => "Comptes d'utilisateur",
        'managed-by' => "Géré par",
        'new' => "Nouveau compte d'utilisateur",
        'org' => "Organisation",
        'package' => "Paquet",
        'price' => "Prix",
        'profile-title' => "Votre profile",
        'profile-delete' => "Supprimer compte",
        'profile-delete-title' => "Supprimer ce compte?",
        'profile-delete-text1' => "Cela supprimera le compte ainsi que tous les domaines, utilisateurs et alias associés à ce compte.",
        'profile-delete-warning' => "Cette opération est irrévocable",
        'profile-delete-text2' => "Comme vous ne pourrez plus rien récupérer après ce point, assurez-vous d'avoir migré toutes les données avant de poursuivre.",
        'profile-delete-support' => "Étant donné que nous nous attachons à toujours nous améliorer, nous aimerions vous demander 2 minutes de votre temps. "
            . "Le meilleur moyen de nous améliorer est le feedback des utilisateurs, et nous voudrions vous demander"
            . "quelques mots sur les raisons pour lesquelles vous avez quitté notre service. Veuillez envoyer vos commentaires au <a href=\"{href}\">{email}</a>.",
        'profile-delete-contact' => "Par ailleurs, n'hésitez pas à contacter le support de {app} pour toute question ou souci que vous pourriez avoir dans ce contexte.",
        'reset-2fa' => "Réinitialiser l'authentification à 2-Facteurs.",
        'reset-2fa-title' => "Réinitialisation de l'Authentification à 2-Facteurs",
        'resources' => "Ressources",
        'title' => "Compte d'utilisateur",
        'search' => "Adresse e-mail ou nom de l'utilisateur",
        'search-pl' => "ID utilisateur, e-mail ou domamine",
        'skureq' => "{sku} demande {list}.",
        'subscription' => "Subscription",
        'subscriptions' => "Subscriptions",
        'subscriptions-none' => "Cet utilisateur n'a pas de subscriptions.",
        'users' => "Utilisateurs",
        'users-none' => "Il n'y a aucun utilisateur dans ce compte.",
    ],

    'wallet' => [
        'add-credit' => "Ajouter un crédit",
        'auto-payment-cancel' => "Annuler l'auto-paiement",
        'auto-payment-change' => "Changer l'auto-paiement",
        'auto-payment-failed' => "La configuration des paiements automatiques a échoué. Redémarrer le processus pour activer les top-ups automatiques.",
        'auto-payment-hint' => "Cela fonctionne de la manière suivante: Chaque fois que votre compte est épuisé, nous débiterons votre méthode de paiement préférée d'un montant que vous aurez défini."
            . " Vous pouvez annuler ou modifier l'option de paiement automatique à tout moment.",
        'auto-payment-setup' => "configurer l'auto-paiement",
        'auto-payment-disabled' => "L'auto-paiement configuré a été désactivé. Rechargez votre porte-monnaie ou augmentez le montant d'auto-paiement.",
        'auto-payment-info' => "L'auto-paiement est <b>set</b> pour recharger votre compte par <b>{amount}</b> lorsque le solde de votre compte devient inférieur à <b>{balance}</b>.",
        'auto-payment-inprogress' => "La configuration d'auto-paiement est toujours en cours.",
        'auto-payment-next' => "Ensuite, vous serez redirigé vers la page de paiement, où vous pourrez fournir les coordonnées de votre carte de crédit.",
        'auto-payment-disabled-next' => "L'auto-paiement est désactivé. Dès que vous aurez soumis de nouveaux paramètres, nous l'activerons et essaierons de recharger votre portefeuille.",
        'auto-payment-update' => "Mise à jour de l'auto-paiement.",
        'banktransfer-hint' => "Veuillez noter qu'un virement bancaire peut nécessiter plusieurs jours avant d'être effectué.",
        'currency-conv' => "Le principe est le suivant: Vous spécifiez le montant dont vous voulez recharger votre portefeuille en {wc}."
            . " Nous convertirons ensuite ce montant en {pc}, et sur la page suivante, vous obtiendrez les coordonnées bancaires pour transférer le montant en {pc}.",
        'fill-up' => "Recharger par",
        'history' => "Histoire",
        'month' => "mois",
        'noperm' => "Seuls les propriétaires de compte peuvent accéder à un portefeuille.",
        'payment-amount-hint' => "Choisissez le montant dont vous voulez recharger votre portefeuille.",
        'payment-method' => "Mode de paiement: {method}",
        'payment-warning' => "Vous serez facturé pour {price}.",
        'pending-payments' => "Paiements en attente",
        'pending-payments-warning' => "Vous avez des paiements qui sont encore en cours. Voir l'onglet \"Paiements en attente\" ci-dessous.",
        'pending-payments-none' => "Il y a aucun paiement en attente pour ce compte.",
        'receipts' => "Reçus",
        'receipts-hint' => "Vous pouvez télécharger ici les reçus (au format PDF) pour les paiements de la période spécifiée. Sélectionnez la période et appuyez sur le bouton Télécharger.",
        'receipts-none' => "Il y a aucun reçu pour les paiements de ce compte. Veuillez noter que vous pouvez télécharger les reçus après la fin du mois.",
        'title' => "Solde du compte",
        'top-up' => "Rechargez votre portefeuille",
        'transactions' => "Transactions",
        'transactions-none' => "Il y a aucun transaction pour ce compte.",
        'when-below' => "lorsque le solde du compte est inférieur à",
    ],
];
