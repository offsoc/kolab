<?php

namespace App\Http\Controllers\API\V4\Reseller;

use App\Http\Controllers\Controller;
use App\SignupInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvitationsController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        return $this->errorResponse(404);
    }

    /**
     * Remove the specified invitation.
     *
     * @param int $id Invitation identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $invitation = SignupInvitation::withSubjectTenantContext()->find($id);

        if (empty($invitation)) {
            return $this->errorResponse(404);
        }

        $invitation->delete();

        return response()->json([
                'status' => 'success',
                'message' => trans('app.signup-invitation-delete-success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id Invitation identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $pageSize = 10;
        $search = request()->input('search');
        $page = intval(request()->input('page')) ?: 1;
        $hasMore = false;

        $result = SignupInvitation::withSubjectTenantContext()
            ->latest()
            ->limit($pageSize + 1)
            ->offset($pageSize * ($page - 1));

        if ($search) {
            if (strpos($search, '@')) {
                $result->where('email', $search);
            } else {
                $result->whereLike('email', $search);
            }
        }

        $result = $result->get();

        if (count($result) > $pageSize) {
            $result->pop();
            $hasMore = true;
        }

        $result = $result->map(function ($invitation) {
            return $this->invitationToArray($invitation);
        });

        return response()->json([
                'status' => 'success',
                'list' => $result,
                'count' => count($result),
                'hasMore' => $hasMore,
                'page' => $page,
        ]);
    }

    /**
     * Resend the specified invitation.
     *
     * @param int $id Invitation identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resend($id)
    {
        $invitation = SignupInvitation::withSubjectTenantContext()->find($id);

        if (empty($invitation)) {
            return $this->errorResponse(404);
        }

        if ($invitation->isFailed() || $invitation->isSent()) {
            // Note: The email sending job will be dispatched by the observer
            $invitation->status = SignupInvitation::STATUS_NEW;
            $invitation->save();
        }

        return response()->json([
                'status' => 'success',
                'message' => trans('app.signup-invitation-resend-success'),
                'invitation' => $this->invitationToArray($invitation),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $errors = [];
        $invitations = [];

        $envTenantId = \config('app.tenant_id');
        $subjectTenantId = auth()->user()->tenant_id;

        if (!empty($request->file) && is_object($request->file)) {
            // Expected a text/csv file with multiple email addresses
            if (!$request->file->isValid()) {
                $errors = ['file' => [$request->file->getErrorMessage()]];
            } else {
                $fh = fopen($request->file->getPathname(), 'r');
                $line_number = 0;
                $error = null;

                while ($line = fgetcsv($fh)) {
                    $line_number++;

                    // @phpstan-ignore-next-line
                    if (count($line) >= 1 && $line[0]) {
                        $email = trim($line[0]);

                        if (strpos($email, '@')) {
                            $v = Validator::make(['email' => $email], ['email' => 'email:filter|required']);

                            if ($v->fails()) {
                                $args = ['email' => $email, 'line' => $line_number];
                                $error = trans('app.signup-invitations-csv-invalid-email', $args);
                                break;
                            }

                            $invitations[] = ['email' => $email];
                        }
                    }
                }

                fclose($fh);

                if ($error) {
                    $errors = ['file' => $error];
                } elseif (empty($invitations)) {
                    $errors = ['file' => trans('app.signup-invitations-csv-empty')];
                }
            }
        } else {
            // Expected 'email' field with an email address
            $v = Validator::make($request->all(), ['email' => 'email|required']);

            if ($v->fails()) {
                $errors = $v->errors()->toArray();
            } else {
                $invitations[] = ['email' => $request->email];
            }
        }

        if (!empty($errors)) {
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        $count = 0;
        foreach ($invitations as $idx => $invitation) {
            $inv = SignupInvitation::create($invitation);
            $count++;

            // Set the invitation tenant to the reseller tenant
            if ($envTenantId != $subjectTenantId) {
                $inv->tenant_id = $subjectTenantId;
                $inv->save();
            }
        }

        return response()->json([
                'status' => 'success',
                'message' => \trans_choice('app.signup-invitations-created', $count, ['count' => $count]),
                'count' => $count,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id Invitation identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return $this->errorResponse(404);
    }

    /**
     * Convert an invitation object to an array for output
     *
     * @param \App\SignupInvitation $invitation The signup invitation object
     *
     * @return array
     */
    protected static function invitationToArray(SignupInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'isNew' => $invitation->isNew(),
            'isSent' => $invitation->isSent(),
            'isFailed' => $invitation->isFailed(),
            'isCompleted' => $invitation->isCompleted(),
            'created' => $invitation->created_at->toDateTimeString(),
        ];
    }
}
