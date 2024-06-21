<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in mail/sms messages sent by the app.
    */

    'header' => "Salut :name,",
    'footer1' => "Meilleures salutations,",
    'footer2' => "Votre :site Équipe",

    'more-info-html' => "Cliquez <a href=\":href\">ici</a> pour plus d'information.",
    'more-info-text' => "Cliquez :href pour plus d'information.",

    'degradedaccountreminder-subject' => "Rappel du :site: Votre compte est gratuit",
    'degradedaccountreminder-body1' => "Merci de ne pas quitter le site, nous vous rappelons que votre compte :email est gratuit."
        . " et limité à la réception d'emails, et à l'utilisation du client web et du cockpit uniquement.",
    'degradedaccountreminder-body2' => "Vous disposez ainsi d'un compte idéal à employer pour l'enregistrement de comptes auprès de tiers"
        . " et les réinitialisations de mot de passe, les notifications ou même simplement les souscriptions aux newsletters et autres.",
    'degradedaccountreminder-body3' => "Pour récupérer les fonctionnalités telles que l'envoi de e-mail, les calendriers, les carnets d'adresses et la synchronisation des téléphones"
        . " et les voix et vidéoconférences, connectez-vous au cockpit et assurez-vous que le solde de votre compte est positif.",
    'degradedaccountreminder-body4' => "Vous pouvez également y supprimer votre compte, afin que vos données disparaissent de nos systèmes.",
    'degradedaccountreminder-body5' => "Nous apprécions votre collaboration!",

    'negativebalance-subject' => ":site Paiement Requis",
    'negativebalance-body' => "C'est une notification pour vous informer que votre :site le solde du compte :email est en négatif et nécessite votre attention."
        . " Veillez à mettre en place un auto-paiement pour éviter de tel avertissement comme celui-ci dans le future.",
    'negativebalance-body-ext' => "Régler votre compte pour le maintenir en fontion:",

    'negativebalancereminderdegrade-subject' => ":site Rappel de Paiement",
    'negativebalancereminderdegrade-body' => "Vous n'avez peut-être pas rendu compte que vous êtes en retard avec votre paiement pour :email."
        . " Veillez à mettre en place un auto-paiement pour éviter de tel avertissement comme celui-ci dans le future.",
    'negativebalancereminderdegrade-body-ext' => "Régler votre compte pour le maintenir en fontion:",

    'negativebalancereminderdegrade-body-warning' => "Veuillez noter que votre compte :email sera dégradé"
        . " si le solde de votre compte n'est pas réglé avant le :date.",

    'negativebalancedegraded-subject' => ":site de Compte Dégradé",
    'negativebalancedegraded-body' => "Votre compte :email a été dégradé pour avoir un solde négatif depuis trop longtemps."
        . " Envisagez de mettre en place un paiement automatique pour éviter des messages comme celui-ci à l'avenir.",
    'negativebalancedegraded-body-ext' => "Réglez maintenant pour rétablir votre compte:",

    'passwordreset-subject' => ":site Réinitialisation du mot de passe",
    'passwordreset-body1' => "Quelqu'un a récemment demandé de changer votre :site mot de passe pour :email.",
    'passwordreset-body2' => "Si vous êtes dans ce cas, veuillez utiliser ce code de vérification pour terminer le processus:",
    'passwordreset-body3' => "Vous pourrez également cliquer sur le lien ci-dessous:",
    'passwordreset-body4' => "si vous n'avez pas fait une telle demande, vous pouvez soit ignorer ce message, soit prendre contact avec nous au sujet de cet incident.",

    'paymentmandatedisabled-subject' => ":site Problème d'auto-paiement",
    'paymentmandatedisabled-body' => "Votre :site solde du compte est négatif"
        . " et le montant configuré pour le rechargement automatique du solde ne suffit pas"
        . " le coût des abonnements consommés.",
    'paymentmandatedisabled-body-ext' => "En vous facturant plusieurs fois le même monant dans un court laps de temps"
        . " peut entraîner des problêmes avec le fournisseur du service de paiement."
        . " Pour éviter tout problème, nous avons suspendu l'auto-paiement pour votre compte."
        . " Pour resourdre le problème,veuillez vous connecter aux paramètres de votre compte et modifier le montant d'auto-paiement.",

    'paymentfailure-subject' => ":site Paiement Echoué",
    'paymentfailure-body' => "Un problème est survenu avec l'auto-paiement pour votre compte :email.\n"
        . "Nous avons tenté de vous facturer via votre méthode de paiement choisie, mais le chargement n'a pas été effectué.",
    'paymentfailure-body-ext' => "Pour éviter tout problème supplémentaire, nous avons suspendu l'auto-paiement sur votre compte."
        . " Pour resourdre le problème,veuillez vous connecter aux paramètres de votre compte au",
    'paymentfailure-body-rest' => "Vous y trouverez la possibilité de payer manuellement votre compte et"
        . " de modifier vos paramètres d'auto-paiement.",

    'paymentsuccess-subject' => ":site Paiement Effectué",
    'paymentsuccess-body' => "L'auto-paiement pour votre :site le compte s'est exécuté sans problème. "
        . "Vous pouvez contrôler le solde de votre nouveau compte et obtenir plus de détails ici:",

    'support' => "Cas particulier? Il y a un probléme avec une charge?\n"
        . ":site Le support reste à votre disposition.",

    'signupverification-subject' => ":site Enregistrement",
    'signupverification-body1' => "Voici votre code de vérification pour le :site registration process:",
    'signupverification-body2' => "Vous pouvez également continuer avec le processus d'enregistrement en cliquant sur le lien ci-dessous:",

    'signupinvitation-subject' => ":site Invitation",
    'signupinvitation-header' => "Salut,",
    'signupinvitation-body1' => "Vous êtes invité à joindre :site. Cliquez sur le lien ci-dessous pour vous inscrire.",
    'signupinvitation-body2' => "",

];
