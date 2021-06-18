<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Providers\PaymentProvider;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends \App\Http\Controllers\Controller
{
    public const COLOR_GREEN = '#48d368';       // '#28a745'
    public const COLOR_GREEN_DARK = '#19692c';
    public const COLOR_RED = '#e77681';         // '#dc3545'
    public const COLOR_RED_DARK = '#a71d2a';
    public const COLOR_BLUE = '#4da3ff';        // '#007bff'
    public const COLOR_BLUE_DARK = '#0056b3';
    public const COLOR_ORANGE = '#f1a539';

    /** @var array List of enabled charts */
    protected $charts = [
        'discounts',
        'income',
        'users',
        'users-all',
    ];

    /**
     * Fetch chart data
     *
     * @param string $chart Name of the chart
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function chart($chart)
    {
        if (!preg_match('/^[a-z-]+$/', $chart)) {
            return $this->errorResponse(404);
        }

        $method = 'chart' . implode('', array_map('ucfirst', explode('-', $chart)));

        if (!in_array($chart, $this->charts) || !method_exists($this, $method)) {
            return $this->errorResponse(404);
        }

        $result = $this->{$method}();

        return response()->json($result);
    }

    /**
     * Get discounts chart
     */
    protected function chartDiscounts(): array
    {
        $discounts = DB::table('wallets')
            ->selectRaw("discount, count(discount_id) as cnt")
            ->join('discounts', 'discounts.id', '=', 'wallets.discount_id')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->where('discount', '>', 0)
            ->whereNull('users.deleted_at')
            ->groupBy('discounts.discount');

        $addTenantScope = function ($builder, $tenantId) {
            return $builder->where('users.tenant_id', $tenantId);
        };

        $discounts = $this->applyTenantScope($discounts, $addTenantScope)
            ->pluck('cnt', 'discount')->all();

        $labels = array_keys($discounts);
        $discounts = array_values($discounts);

        // $labels = [10, 25, 30, 100];
        // $discounts = [100, 120, 30, 50];

        $labels = array_map(function ($item) {
            return $item . '%';
        }, $labels);

        // See https://frappe.io/charts/docs for format/options description

        return [
            'title' => 'Discounts',
            'type' => 'donut',
            'colors' => [
                self::COLOR_BLUE,
                self::COLOR_BLUE_DARK,
                self::COLOR_GREEN,
                self::COLOR_GREEN_DARK,
                self::COLOR_ORANGE,
                self::COLOR_RED,
                self::COLOR_RED_DARK
            ],
            'maxSlices' => 8,
            'tooltipOptions' => [], // does not work without it (https://github.com/frappe/charts/issues/314)
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'values' => $discounts
                    ]
                ]
            ]
        ];
    }

    /**
     * Get income chart
     */
    protected function chartIncome(): array
    {
        $weeks = 8;
        $start = Carbon::now();
        $labels = [];

        while ($weeks > 0) {
            $labels[] = $start->format('Y-W');
            $weeks--;
            if ($weeks) {
                $start->subWeeks(1);
            }
        }

        $labels = array_reverse($labels);
        $start->startOfWeek(Carbon::MONDAY);

        $payments = DB::table('payments')
            ->selectRaw("date_format(updated_at, '%Y-%v') as period, sum(amount) as amount")
            ->where('updated_at', '>=', $start->toDateString())
            ->where('status', PaymentProvider::STATUS_PAID)
            ->whereIn('type', [PaymentProvider::TYPE_ONEOFF, PaymentProvider::TYPE_RECURRING])
            ->groupByRaw('1');

        $addTenantScope = function ($builder, $tenantId) {
            $where = '`wallet_id` IN ('
                    . 'select `id` from `wallets` '
                    . 'join `users` on (`wallets`.`user_id` = `users`.`id`) '
                    . 'where `payments`.`wallet_id` = `wallets`.`id` '
                    . 'and `users`.`tenant_id` = ' . intval($tenantId)
                . ')';

            return $builder->whereRaw($where);
        };

        $payments = $this->applyTenantScope($payments, $addTenantScope)
            ->pluck('amount', 'period')
            ->map(function ($amount) {
                return $amount / 100;
            });

        // TODO: exclude refunds/chargebacks

        $empty = array_fill_keys($labels, 0);
        $payments = array_values(array_merge($empty, $payments->all()));

        // $payments = [1000, 1200.25, 3000, 1897.50, 2000, 1900, 2134, 3330];

        $avg = collect($payments)->slice(0, count($labels) - 1)->avg();

        // See https://frappe.io/charts/docs for format/options description

        return [
            'title' => 'Income in CHF - last 8 weeks',
            'type' => 'bar',
            'colors' => [self::COLOR_BLUE],
            'axisOptions' => [
                'xIsSeries' => true,
            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        // 'name' => 'Payments',
                        'values' => $payments
                    ]
                ],
                'yMarkers' => [
                    [
                        'label' => sprintf('average = %.2f', $avg),
                        'value' => $avg,
                        'options' => [ 'labelPos' => 'left' ] // default: 'right'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get created/deleted users chart
     */
    protected function chartUsers(): array
    {
        $weeks = 8;
        $start = Carbon::now();
        $labels = [];

        while ($weeks > 0) {
            $labels[] = $start->format('Y-W');
            $weeks--;
            if ($weeks) {
                $start->subWeeks(1);
            }
        }

        $labels = array_reverse($labels);
        $start->startOfWeek(Carbon::MONDAY);

        $created = DB::table('users')
            ->selectRaw("date_format(created_at, '%Y-%v') as period, count(*) as cnt")
            ->where('created_at', '>=', $start->toDateString())
            ->groupByRaw('1');

        $deleted = DB::table('users')
            ->selectRaw("date_format(deleted_at, '%Y-%v') as period, count(*) as cnt")
            ->where('deleted_at', '>=', $start->toDateString())
            ->groupByRaw('1');

        $created = $this->applyTenantScope($created)->get();
        $deleted = $this->applyTenantScope($deleted)->get();

        $empty = array_fill_keys($labels, 0);
        $created = array_values(array_merge($empty, $created->pluck('cnt', 'period')->all()));
        $deleted = array_values(array_merge($empty, $deleted->pluck('cnt', 'period')->all()));

        // $created = [5, 2, 4, 2, 0, 5, 2, 4];
        // $deleted = [1, 2, 3, 1, 2, 1, 2, 3];

        $avg = collect($created)->slice(0, count($labels) - 1)->avg();

        // See https://frappe.io/charts/docs for format/options description

        return [
            'title' => 'Users - last 8 weeks',
            'type' => 'bar', // Required to fix https://github.com/frappe/charts/issues/294
            'colors' => [self::COLOR_GREEN, self::COLOR_RED],
            'axisOptions' => [
                'xIsSeries' => true,
            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'name' => 'Created',
                        'chartType' => 'bar',
                        'values' => $created
                    ],
                    [
                        'name' => 'Deleted',
                        'chartType' => 'line',
                        'values' => $deleted
                    ]
                ],
                'yMarkers' => [
                    [
                        'label' => sprintf('average = %.1f', $avg),
                        'value' => collect($created)->avg(),
                        'options' => [ 'labelPos' => 'left' ] // default: 'right'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get all users chart
     */
    protected function chartUsersAll(): array
    {
        $weeks = 54;
        $start = Carbon::now();
        $labels = [];

        while ($weeks > 0) {
            $labels[] = $start->format('Y-W');
            $weeks--;
            if ($weeks) {
                $start->subWeeks(1);
            }
        }

        $labels = array_reverse($labels);
        $start->startOfWeek(Carbon::MONDAY);

        $created = DB::table('users')
            ->selectRaw("date_format(created_at, '%Y-%v') as period, count(*) as cnt")
            ->where('created_at', '>=', $start->toDateString())
            ->groupByRaw('1');

        $deleted = DB::table('users')
            ->selectRaw("date_format(deleted_at, '%Y-%v') as period, count(*) as cnt")
            ->where('deleted_at', '>=', $start->toDateString())
            ->groupByRaw('1');

        $created = $this->applyTenantScope($created)->get();
        $deleted = $this->applyTenantScope($deleted)->get();
        $count = $this->applyTenantScope(DB::table('users')->whereNull('deleted_at'))->count();

        $empty = array_fill_keys($labels, 0);
        $created = array_merge($empty, $created->pluck('cnt', 'period')->all());
        $deleted = array_merge($empty, $deleted->pluck('cnt', 'period')->all());
        $all = [];

        foreach (array_reverse($labels) as $label) {
            $all[] = $count;
            $count -= $created[$label] - $deleted[$label];
        }

        $all = array_reverse($all);

        // $start = 3000;
        // for ($i = 0; $i < count($labels); $i++) {
        //     $all[$i] = $start + $i * 15;
        // }

        // See https://frappe.io/charts/docs for format/options description

        return [
            'title' => 'All Users - last year',
            'type' => 'line',
            'colors' => [self::COLOR_GREEN],
            'axisOptions' => [
                'xIsSeries' => true,
                'xAxisMode' => 'tick',
            ],
            'lineOptions' => [
                'hideDots' => true,
                'regionFill' => true,
            ],
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        // 'name' => 'Existing',
                        'values' => $all
                    ]
                ]
            ]
        ];
    }

    /**
     * Add tenant scope to the queries when needed
     *
     * @param \Illuminate\Database\Query\Builder $query    The query
     * @param callable                           $addQuery Additional tenant-scope query-modifier
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applyTenantScope($query, $addQuery = null)
    {
        $user = Auth::guard()->user();

        if ($user->role == 'reseller') {
            if ($addQuery) {
                $query = $addQuery($query, \config('app.tenant_id'));
            } else {
                $query = $query->withEnvTenant();
            }
        }

        // TODO: Tenant selector for admins

        return $query;
    }
}
