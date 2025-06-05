<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Get the per-user webmail configuration.
     *
     * @return JsonResponse The response
     */
    public function webmail(Request $request)
    {
        $user = $this->guard()->user();

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $config = [
            'kolab-configuration-overlays' => [],
        ];

        $skus = $user->skuTitles();

        // TODO conditionally switch to kolabobjects
        $config['kolab-configuration-overlays'][] = 'kolab4';

        if (in_array('activesync', $skus)) {
            $config['kolab-configuration-overlays'][] = 'activesync';
        }

        if (in_array('2fa', $skus)) {
            $config['kolab-configuration-overlays'][] = '2fa';
        }

        if (in_array('groupware', $skus)) {
            $config['kolab-configuration-overlays'][] = 'groupware';
        }


        // TODO: Per-domain configuration, e.g. skin/logo
        // $config['skin'] = 'apostrophy';
        // $config['skin_logo'] = 'data:image/svg+xml;base64,'
        //    . base64_encode(file_get_contents(storage_path('logo.svg')));

        return response()->json($config);
    }
}
