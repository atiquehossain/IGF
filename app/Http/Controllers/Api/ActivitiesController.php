<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ActivitiesController extends Controller
{
    public function index()
    {
        // The legacy activities module is not installed on Ignite sites.
        // Keep its public API route compatible for older clients without
        // referencing removed models or tables.
        return response()->json([
            'status' => true,
            'properties' => ['page' => 1, 'total_page' => 1, 'total_count' => 0],
            'data' => ['activities' => []],
        ]);
    }
}
