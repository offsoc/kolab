<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Get the per-user webmail configuration.
     *
     * @return \Illuminate\Http\JsonResponse The response
     */
    public function webmail(Request $request)
    {
        $user = $this->guard()->user();

        if (!$this->checkTenant($user)) {
            return $this->errorResponse(404);
        }

        $config = [
            'plugins' => [],
        ];

        $skus = $user->skuTitles();

        if (in_array('activesync', $skus)) {
            $config['plugins'][] = 'kolab_activesync';
        }

        if (in_array('2fa', $skus)) {
            $config['plugins'][] = 'kolab_2fa';
        }

        if (in_array('groupware', $skus)) {
            $config['plugins'][] = 'calendar';
            $config['plugins'][] = 'kolab_files';
            $config['plugins'][] = 'kolab_addressbook';
            $config['plugins'][] = 'kolab_tags';
            // $config['plugins'][] = 'kolab_notes';
            $config['plugins'][] = 'tasklist';
        } else {
            // disable groupware plugins in case they are enabled by default
            $config['calendar_disabled'] = true;
            $config['kolab_files_disabled'] = true;
            // $config['kolab_addressbook_disabled'] = true;
            // $config['kolab_notes_disabled'] = true;
            $config['kolab_tags_disabled'] = true;
            $config['tasklist_disabled'] = true;
        }

        // TODO: Per-domain configuration, e.g. skin/logo
        // $config['skin'] = 'apostrophy';
        // $config['skin_logo'] = 'data:image/svg+xml;base64,'
        //    . base64_encode(file_get_contents(storage_path('logo.svg')));

        return response()->json($config);
    }
}
