<?php

namespace App\Http\Controllers\API\V4;

use App\Package;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PackagesController extends Controller
{
    /**
     * Show the form for creating a new package.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Remove the specified package from storage.
     *
     * @param int $id Package identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Show the form for editing the specified package.
     *
     * @param int $id Package identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Display a listing of packages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // TODO: Packages should have an 'active' flag too, I guess
        $response = [];
        $packages = Package::select()->orderBy('title')->get();

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

    /**
     * Store a newly created package in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Display the specified package.
     *
     * @param int $id Package identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // TODO
        return $this->errorResponse(404);
    }

    /**
     * Update the specified package in storage.
     *
     * @param \Illuminate\Http\Request $request Request object
     * @param int                      $id      Package identifier
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // TODO
        return $this->errorResponse(404);
    }
}
