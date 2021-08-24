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

    'negativebalance-subject' => ":site Paiement Requis",
    'negativebalance-body' => "C'est une notification pour vous informer que votre :site le solde du compte est en négatif et nécessite votre attention."
        . " Veillez à mettre en place un auto-paiement pour éviter de tel avertissement comme celui-ci dans le future.",
    'negativebalance-body-ext' => "Régler votre compte pour le maintenir en fontion:",

    'negativebalancereminder-subject' => ":site Rappel de Paiement",
    'negativebalancereminder-body' => "Vous n'avez peut-être pas rendu compte que vous êtes en retard avec votre paiement pour :site compte."
        . " Veillez à mettre en place un auto-paiement pour éviter de tel avertissement comme celui-ci dans le future.",
    'negativebalancereminder-body-ext' => "Régler votre compte pour le maintenir en fontion:",
    'negativebalancereminder-body-warning' => "Soyez conscient que votre compte sera suspendu si le"
        . " solde de votre compte n'est réglé avant le :date.",

    'negativebalancesuspended-subject' => ":site Compte Suspendu",
    'negativebalancesuspended-body' => "Votre :site compte a été suspendu à la suite d'un solde négatif pendant trop longtemps."
        . " Veillez nvisager de mettre en place un auto-paiement pour éviter de tel avertissement comme celui-ci dans le future.",
    'negativebalancesuspended-body-ext' => "Régler votre compte pour le maintenir en fontion:",
    'negativebalancesuspended-body-warning' => "Veuillez vous assurer que votre compte et toutes ses données seront supprimés"
        . " si le solde de votre compte n'est pas réglé avant le :date.",

    'negativebalancebeforedelete-subject' => ":site Dernier Avertissement",
    'negativebalancebeforedelete-body' => "Ceci-ci est le dernier rappel pour régler votre :site solde de compte."
        . " votre compte et toutes ses données seront supprimés si le solde de votre compte nest pas régler avant le :date.",
    'negativebalancebeforedelete-body-ext' => "Régler votre compte immédiatement:",

    'passwordreset-subject' => ":site Réinitialisation du mot de passe",
    'passwordreset-body1' => "Quelqu'un a récemment demandé de changer votre :site mot de passe.",
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
    'paymentfailure-body' => "Un problème est survenu avec l'auto-paiement pour votre :site account.\n"
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

    'signupcode-subject' => ":site Enregistrement",
    'signupcode-body1' => "Voici votre code de vérification pour le :site registration process:",
    'signupcode-body2' => "Vous pouvez également continuer avec le processus d'enregistrement en cliquant sur le lien ci-dessous:",

    'signupinvitation-subject' => ":site Invitation",
    'signupinvitation-header' => "Salut,",
    'signupinvitation-body1' => "Vous êtes invité à joindre :site. Cliquez sur le lien ci-dessous pour vous inscrire.",
    'signupinvitation-body2' => "",

    'suspendeddebtor-subject' => ":site Compte Suspendu",
    'suspendeddebtor-body' => "Vous êtes en retard avec le paiement de votre :site compte"
        . " pour plus de :days jours. Votre compte est suspendu.",
    'suspendeddebtor-middle' => "Réglez immédiatement pour réactiver votre compte.",
    'suspendeddebtor-cancel' => "Vous ne souhaitez plus être notre client?"
        . " Voici la démarche à suivre pour annuler votre compte:",

];