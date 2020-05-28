<?php

return [
    'entitlement-created' => ':user_email created :sku_title for :object_email',
    'entitlement-billed' => ':sku_title for :object_email is billed at :amount',
    'entitlement-deleted' => ':user_email deleted :sku_title for :object_email',

    'wallet-award' => 'Bonus of :amount awarded to :wallet_description; :description',
    'wallet-credit' => ':amount was added to the balance of :wallet_description',
    'wallet-debit' => ':amount was deducted from the balance of :wallet_description',
    'wallet-penalty' => 'The balance of wallet :wallet_description was reduced by :amount; :description'
];
