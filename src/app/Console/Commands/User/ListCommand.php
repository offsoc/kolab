<?php

namespace App\Console\Commands\User;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';

    /**
     * Apply pre-configured filter or raw WHERE clause to the main query.
     *
     * @param object $query  Query builder
     * @param string $filter Pre-defined filter identifier or raw SQL WHERE clause
     *
     * @return object Query builder
     */
    public function applyFilter($query, string $filter)
    {
        // Get users with or without a successful payment, e.g. --filter=WITH-PAYMENT
        if (preg_match('/^(with|without)-payment/i', $filter, $matches)) {
            $method = strtolower($matches[1]) == 'with' ? 'whereIn' : 'whereNotIn';

            return $query->whereIn('id', function ($query) use ($method) {
                // all user IDs from the entitlements
                $query->select('entitleable_id')->distinct()
                    ->from('entitlements')
                    ->where('entitleable_type', \App\User::class)
                    ->{$method}('wallet_id', function ($query) {
                        // wallets with a PAID payment
                        $query->select('wallet_id')->distinct()
                            ->from('payments')
                            ->where('status', \App\Payment::STATUS_PAID);
                    });
            });
        }

        return parent::applyFilter($query, $filter);
    }
}
