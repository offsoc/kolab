<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends \App\Http\Controllers\Controller
{
    public const COLOR_GREEN = '#48d368';       // '#28a745'
    public const COLOR_GREEN_DARK = '#19692c';
    public const COLOR_RED = '#e77681';         // '#dc3545'
    public const COLOR_RED_DARK = '#a71d2a';
    public const COLOR_BLUE = '#4da3ff';        // '#007bff'
    public const COLOR_BLUE_DARK = '#0056b3';

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

        if (!method_exists($this, $method)) {
            return $this->errorResponse(404);
        }

        $result = $this->{$method}();

        return response()->json($result);
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
            $start->subWeeks(1);
            $weeks--;
        }

        $labels = array_reverse($labels);
        $start->startOfWeek(Carbon::MONDAY);

        $created = DB::table('users')
            ->selectRaw("concat(year(created_at), '-', week(created_at, 3)) as period, count(*) as cnt")
            ->where('created_at', '>=', $start->toDateString())
            ->groupByRaw('1')
            ->get();

        $deleted = DB::table('users')
            ->selectRaw("concat(year(deleted_at), '-', week(deleted_at, 3)) as period, count(*) as cnt")
            ->where('deleted_at', '>=', $start->toDateString())
            ->groupByRaw('1')
            ->get();

        $empty = array_fill_keys($labels, 0);
        $created = array_merge($empty, $created->pluck('cnt', 'period')->all());
        $deleted = array_merge($empty, $deleted->pluck('cnt', 'period')->all());

        //$created = [5, 2, 4, 2, 0, 5, 2, 4];
        //$deleted = [1, 2, 3, 1, 2, 1, 2, 3];

        // See https://frappe.io/charts/docs for format/options description

        return [
            'title' => 'Users - last 8 weeks',
            // 'type' => 'axis-mixed',
            'colors' => [self::COLOR_GREEN, self::COLOR_RED],
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
                ]
            ]
        ];
    }
}
