<?php

return [
    'entitlement-created' => ':user_email a créé :sku_title pour :object',
    'entitlement-billed' => ':sku_title for :object est facturé à :amount',
    'entitlement-deleted' => ':user_email supprimé :sku_title pour :object',

    'entitlement-created-short' => 'Ajoutée :sku_title pour :object',
    'entitlement-billed-short' => 'Facturé :sku_title pour :object',
    'entitlement-deleted-short' => 'Supprimé :sku_title pour :object',

    'wallet-award' => 'bonus de :amount attribué à :wallet; :description',
    'wallet-chback' => ':amount été refacturé par :wallet',
    'wallet-credit' => ':amount a été ajouté au solde de :wallet',
    'wallet-debit' => ':amount a été déduit du solde de :wallet',
    'wallet-penalty' => 'Le solde de :wallet été réduit de :amount; :description',
    'wallet-refund' => ':amount a été remboursé sur le solde de :wallet',

    'wallet-award-short' => 'Prime: :description',
    'wallet-chback-short' => 'Rétrofacturation',
    'wallet-credit-short' => 'Paiement',
    'wallet-debit-short' => 'Déduction',
    'wallet-penalty-short' => 'Charger: :description',
    'wallet-refund-short' => 'Remboursement: :description',
];
