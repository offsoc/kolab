<?php

return [
    'entitlement-created' => ':user_email created :sku_title for :object',
    'entitlement-billed' => ':sku_title for :object is billed at :amount',
    'entitlement-deleted' => ':user_email deleted :sku_title for :object',

    'wallet-award' => 'Bonus of :amount awarded to :wallet; :description',
    'wallet-credit' => ':amount was added to the balance of :wallet',
    'wallet-debit' => ':amount was deducted from the balance of :wallet',
    'wallet-penalty' => 'The balance of :wallet was reduced by :amount; :description',

    'entitlement-created-short' => 'Added :sku_title for :object',
    'entitlement-billed-short' => 'Billed :sku_title for :object',
    'entitlement-deleted-short' => 'Deleted :sku_title for :object',

    'wallet-award-short' => 'Bonus: :description',
    'wallet-credit-short' => 'Payment',
    'wallet-debit-short' => 'Deduction',
    'wallet-penalty-short' => 'Charge: :description',
];
