<?php

namespace App\Http\Controllers\API\V4\Admin;

class UsersController extends \App\Http\Controllers\API\V4\UsersController
{
    public function index()
    {
        $result = \App\User::orderBy('email')->get()->map(function ($user) {
            $data = $user->toArray();
            $data = array_merge($data, self::userStatuses($user));
            return $data;
        });

        return response()->json($result);
    }
}
