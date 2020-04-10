<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Domain;
use App\User;

class DomainsController extends \App\Http\Controllers\API\V4\DomainsController
{
    /**
     * Search for domains
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $search = trim(request()->input('search'));
        $owner = trim(request()->input('owner'));
        $result = collect([]);

        if ($owner) {
            if ($owner = User::find($owner)) {
                foreach ($owner->wallets as $wallet) {
                    $entitlements = $wallet->entitlements()->where('entitleable_type', Domain::class)->get();

                    foreach ($entitlements as $entitlement) {
                        $domain = $entitlement->entitleable;
                        $result->push($domain);
                    }
                }

                $result = $result->sortBy('namespace');
            }
        } elseif (!empty($search)) {
            if ($domain = Domain::where('namespace', $search)->first()) {
                $result->push($domain);
            }
        }

        // Process the result
        $result = $result->map(function ($domain) {
            $data = $domain->toArray();
            $data = array_merge($data, self::domainStatuses($domain));
            return $data;
        });

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxdomains', ['x' => count($result)]),
        ];

        return response()->json($result);
    }
}
