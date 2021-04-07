<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SupportController extends Controller
{
    /**
     * Submit contact request form.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function request(Request $request)
    {
        $rules = [
            'user' => 'string|nullable|max:256',
            'name' => 'string|nullable|max:256',
            'email' => 'required|email',
            'summary' => 'required|string|max:512',
            'body' => 'required|string',
        ];

        $params = $request->only(array_keys($rules));

        // Check required fields
        $v = Validator::make($params, $rules);

        if ($v->fails()) {
            return response()->json(['status' => 'error', 'errors' => $v->errors()], 422);
        }

        $to = \config('app.support_email');

        if (empty($to)) {
            \Log::error("Failed to send a support request. SUPPORT_EMAIL not set");
            return $this->errorResponse(500, \trans('app.support-request-error'));
        }

        $content = sprintf(
            "ID: %s\nName: %s\nWorking email address: %s\nSubject: %s\n\n%s\n",
            $params['user'] ?? '',
            $params['name'] ?? '',
            $params['email'],
            $params['summary'],
            $params['body'],
        );

        Mail::raw($content, function ($message) use ($params, $to) {
            // Remove the global reply-to addressee
            $message->getHeaders()->remove('Reply-To');

            $message->to($to)
                ->from($params['email'], $params['name'] ?? null)
                ->replyTo($params['email'], $params['name'] ?? null)
                ->subject($params['summary']);
        });

        return response()->json([
                'status' => 'success',
                'message' => \trans('app.support-request-success'),
        ]);
    }
}
