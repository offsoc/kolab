<?php

namespace App\Http\Controllers\API\V4\Reseller;

class StatsController extends \App\Http\Controllers\API\V4\Admin\StatsController
{
    /** @var array List of enabled charts */
    protected $charts = [
        'discounts',
        // 'income',
        'users',
        'users-all',
    ];
}
