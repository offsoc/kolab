<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa;
use Lcobucci\JWT\Token\Builder;

class LicenseController extends Controller
{
    /**
     * Get the information on any license for the user.
     *
     * @param string $type License type
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function license(Request $request, string $type)
    {
        $user = $this->guard()->user();

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $licenses = $user->licenses()->where('type', $type)->orderBy('created_at')->get();

        // No licenses for the user, take one if available
        if (!count($licenses)) {
            DB::beginTransaction();

            $license = License::withObjectTenantContext($user)
                ->where('type', $type)
                ->whereNull('user_id')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($license) {
                $license->user_id = $user->id;
                $license->save();

                $licenses = \collect([$license]);
            }

            DB::commit();
        }

        // Slim down the result set
        $licenses = $licenses->map(function ($license) {
                return [
                    'key' => $license->key,
                    'type' => $license->type,
                ];
        });

        return response()->json([
            'list' => $licenses,
            'count' => count($licenses),
            'hasMore' => false, // TODO
        ]);
    }
}
