<?php

namespace App\Http\Controllers\API\V4\Admin;

use App\Domain;
use App\User;
use Illuminate\Http\Request;

class DomainsController extends \App\Http\Controllers\API\V4\DomainsController
{
    /**
     * Remove the specified domain.
     *
     * @param int $id Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return $this->errorResponse(404);
    }

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

                $result = $result->sortBy('namespace')->values();
            }
        } elseif (!empty($search)) {
            if ($domain = Domain::where('namespace', $search)->first()) {
                $result->push($domain);
            }
        }

        // Process the result
        $result = $result->map(
            function ($domain) {
                return $this->objectToClient($domain);
            }
        );

        $result = [
            'list' => $result,
            'count' => count($result),
            'message' => \trans('app.search-foundxdomains', ['x' => count($result)]),
        ];

        return response()->json($result);
    }

    /**
     * Create a domain.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return $this->errorResponse(404);
    }

    /**
     * Suspend the domain
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function suspend(Request $request, $id)
    {
        $domain = Domain::find($id);

        if (!$this->checkTenant($domain) || $domain->isPublic()) {
            return $this->errorResponse(404);
        }

        $domain->suspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.domain-suspend-success'),
        ]);
    }

    /**
     * Un-Suspend the domain
     *
     * @param \Illuminate\Http\Request $request The API request.
     * @param string                   $id      Domain identifier
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function unsuspend(Request $request, $id)
    {
        $domain = Domain::find($id);

        if (!$this->checkTenant($domain) || $domain->isPublic()) {
            return $this->errorResponse(404);
        }

        $domain->unsuspend();

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.domain-unsuspend-success'),
        ]);
    }
}
