<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class InteractiveAudioController extends Controller
{
    public function index()
    {
        // The legacy interactive-audio module is not installed on Ignite
        // sites. Preserve its collection shape for older API consumers.
        return response()->json([
            'status' => true,
            'properties' => ['page' => 1, 'total_page' => 1, 'total_count' => 0],
            'data' => ['interactive_radios' => []],
        ]);
    }
}
