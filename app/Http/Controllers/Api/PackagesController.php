<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class PackagesController extends Controller
{
    public function index()
    {
        // Package filters belonged to the retired learning-resource module.
        return response()->json([
            'status' => true,
            'properties' => [],
            'data' => ['alp_packages' => []],
        ]);
    }
}
