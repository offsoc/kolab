<?php

namespace App\Http\Controllers\API\V4;

use App\Package;
use App\Http\Controllers\ResourceController;
use Illuminate\Http\Request;

class PackagesController extends ResourceController
{
    /**
     * Display a listing of packages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // TODO: Packages should have an 'active' flag too, I guess
        $response = [];
        $packages = Package::withSubjectTenantContext()->select()->orderBy('title')->get();

        foreach ($packages as $package) {
            $response[] = [
                'id' => $package->id,
                'title' => $package->title,
                'name' => $package->name,
                'description' => $package->description,
                'cost' => $package->cost(),
                'isDomain' => $package->isDomain(),
            ];
        }

        return response()->json($response);
    }
}
