<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CoursesController extends Controller
{

    public function courses(Request $request)
    {
        $title = '';
        try {
            $response = [
                'status' => true,
                'title' => 'Courses',
                'meta_tag' => $request->meta_tag,
                'data' => [],
            ];
            return Inertia::render('Courses/courses')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function courseDetails(Request $request)
    {
        $title = 'Course Details';
        try {
            $response = [
                'status' => true,
                'title' => 'Course Details',
                'meta_tag' => $request->meta_tag,
                'data' => [],
            ];
            return Inertia::render('Courses/components/CourseDetails')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }
    
    public function courseStarted(Request $request)
    {
        $title = 'Course Started';
        try {
            $response = [
                'status' => true,
                'title' => 'Course Started',
                'meta_tag' => $request->meta_tag,
                'data' => [],
            ];
            return Inertia::render('Courses/components/CourseDetails/CourseStarted')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }
}
