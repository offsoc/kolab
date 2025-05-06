<?php

namespace App\Http\Controllers\API\V4\Reseller;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;

class StatsController extends \App\Http\Controllers\API\V4\Admin\StatsController
{
    /** @var array List of enabled charts */
    protected $charts = [
        'discounts',
        // 'income',
        'payers',
        'users',
        'users-all',
        'vouchers',
    ];

    /**
     * Add tenant scope to the queries when needed
     *
     * @param Builder  $query    The query
     * @param callable $addQuery Additional tenant-scope query-modifier
     *
     * @return Builder
     */
    protected function applyTenantScope($query, $addQuery = null)
    {
        if ($addQuery) {
            $user = Auth::guard()->user();
            $query = $addQuery($query, $user->tenant_id);
        } else {
            $query = $query->withSubjectTenantContext();
        }

        return $query;
    }
}
