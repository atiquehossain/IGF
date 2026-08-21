<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class TeacherTrainingTypeController extends Controller
{
    public function index()
    {
        // Training-type filters belonged to the retired learning-resource
        // module. Keep the legacy response key stable for older clients.
        return response()->json([
            'status' => true,
            'properties' => [],
            'data' => ['teacher_training_types' => []],
        ]);
    }
}
