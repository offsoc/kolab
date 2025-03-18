<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\User;
use App\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
 * Note: We use a separate controller for search, as this will
 * be different that just a user search/listing functionality,
 * it includes aliases (and contacts), how we do the search is different too.
 */

class SearchController extends Controller
{
    /**
     * Search request for user's email addresses
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function searchSelf(Request $request)
    {
        $user = $this->guard()->user();
        $search = trim(request()->input('search'));
        $with_aliases = !empty(request()->input('alias'));
        $limit = intval(request()->input('limit'));

        if ($limit <= 0) {
            $limit = 15;
        } elseif ($limit > 100) {
            $limit = 100;
        }

        // Prepare the query
        $query = User::select('email', 'id')->where('id', $user->id);
        $aliases = DB::table('user_aliases')->select(DB::raw('alias as email, user_id as id'))
            ->where('user_id', $user->id);

        if (strlen($search)) {
            $aliases->whereLike('alias', "%{$search}%");
            $query->whereLike('email', "%{$search}%");
        }

        if ($with_aliases) {
            $query->union($aliases);
        }

        // Execute the query
        $result = $query->orderBy('email')->limit($limit)->get();

        $result = $this->resultFormat($result);

        return response()->json([
            'list' => $result,
            'count' => count($result),
        ]);
    }

    /**
     * Search request for addresses of all users (in an account)
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function searchUser(Request $request)
    {
        $user = $this->guard()->user();
        $search = trim(request()->input('search'));
        $with_aliases = !empty(request()->input('alias'));
        $limit = intval(request()->input('limit'));

        if ($limit <= 0) {
            $limit = 15;
        } elseif ($limit > 100) {
            $limit = 100;
        }

        $wallet = $user->wallet();

        // Limit users to the user's account
        $allUsers = $wallet->entitlements()
            ->where('entitleable_type', User::class)
            ->select('entitleable_id')
            ->distinct();

        // Sub-query for user IDs who's names match the search criteria
        $foundUserIds = UserSetting::select('user_id')
            ->whereIn('key', ['first_name', 'last_name'])
            ->whereLike('value', "%{$search}%")
            ->whereIn('user_id', $allUsers);

        // Prepare the query
        $query = User::select('email', 'id')->whereIn('id', $allUsers);
        $aliases = DB::table('user_aliases')->select(DB::raw('alias as email, user_id as id'))
            ->whereIn('user_id', $allUsers);

        if (strlen($search)) {
            $query->where(function ($query) use ($foundUserIds, $search) {
                $query->whereLike('email', "%{$search}%")
                    ->orWhereIn('id', $foundUserIds);
            });

            $aliases->where(function ($query) use ($foundUserIds, $search) {
                $query->whereLike('alias', "%{$search}%")
                    ->orWhereIn('user_id', $foundUserIds);
            });
        }

        if ($with_aliases) {
            $query->union($aliases);
        }

        // Execute the query
        $result = $query->orderBy('email')->limit($limit)->get();

        $result = $this->resultFormat($result);

        return response()->json([
            'list' => $result,
            'count' => count($result),
        ]);
    }

    /**
     * Format the search result, inject user names
     */
    protected function resultFormat($result)
    {
        if ($result->count()) {
            // Get user names
            $settings = UserSetting::whereIn('key', ['first_name', 'last_name'])
                ->whereIn('user_id', $result->pluck('id'))
                ->get()
                ->mapWithKeys(function ($item) {
                    return [($item->user_id . ':' . $item->key) => $item->value];
                })
                ->all();

            // "Format" the result, include user names
            $result = $result->map(function ($record) use ($settings) {
                    return [
                        'email' => $record->email,
                        'name' => trim(
                            ($settings["{$record->id}:first_name"] ?? '')
                            . ' '
                            . ($settings["{$record->id}:last_name"] ?? '')
                        ),
                    ];
            })
                ->sortBy(['name', 'email'])
                ->values();
        }

        return $result;
    }
}
