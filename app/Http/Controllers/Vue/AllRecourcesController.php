<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Subject;
use App\Models\EcwClass;
use App\Models\Package;
use App\Models\Resource;
use App\Models\VideoContent;

use App\Helper\StaticUtil;
use App\Helper\MyMenu;

use Inertia\Inertia;
use Exception;
use Auth;

class AllRecourcesController extends Controller
{

    public function resources(Request $request, $locale = 'en')
    {
        $title = $request->Lang->Resource->Title;
        $tab = $request->tab;
        $user = Auth::user();
        
        try {
            $subjects = Subject::select('id as value', 'name as text')->where('language', $locale)->get();
            $classes = EcwClass::select('id as value', 'name as text')->where('language', $locale)->get();
            $packages = Package::select('id as value', 'name as text')->where(function ($query) {
              $query->where('alp_filter', null)
                    ->orWhere('alp_filter',0);
            })->where('language', $locale)->get();
            $rerourceTabs = StaticUtil::rerourceTabs($locale);
            $currentNav = MyMenu::firstMenuBySlug($locale);

            $query = Resource::select('resources.*', 'cls.name as class_name', 'subjects.name as subject_name',
                                    'you_tubes.duration_time as duration', 'video_contents.duration_time as cduration')
                ->selectRaw('CONCAT("/storage/photos/1/resources/", resources.image) as image_path')
                ->selectRaw('CONCAT("/storage/photos/1/resources/", resources.cover_image) as cover_image_path')
                ->leftJoin('ecw_classes as cls', 'cls.id', '=', 'resources.class_id')
                ->leftJoin('subjects', 'subjects.id', '=', 'resources.subject_id')
                ->leftJoin('you_tubes', 'you_tubes.id', '=', 'resources.video_id')
                ->leftJoin('video_contents', 'video_contents.id', '=', 'resources.video_id')
                ->where('resources.language', $locale)
                ->where('resources.status', 1);
                
            if($user){
              $query = $query->where('resources.is_public',1)
                              ->orWhere('resources.is_public',0)
                              ->orWhere('resources.is_public',null)
                              ->where('resources.language', $locale);
            } else{
              $query = $query->where('resources.is_public',1)
                              ->where('resources.language', $locale);
            }
            
            $query = $query->paginate(15);

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
                'properties' => [
                    'page' => $query->currentPage(),
                    'total_page' => $query->lastPage(),
                    'total_count' => $query->total(),
                ],
                'data' => [
                    'current_nav' => $currentNav,
                    'subjects' => $subjects,
                    'classes' => $classes,
                    'packages' => $packages,
                    'rerourceTabs' => $rerourceTabs,
                    'resources' => $query->items(),
                    'tab'=> $tab
                ],
            ];
            return Inertia::render('Resources/resources')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function resourceDetails(Request $request, $locale = 'en')
    {
        $title = $request->Lang->Resource->Details;
        
        try {
            $parentSlug = explode(".resourceDetails",$request->route()->getName())[0];
            $currentNav = MyMenu::firstMenuBySlug($locale, $parentSlug);

            $resources = Resource::select('resources.*', 'cls.name as class_name', 'subjects.name as subject_name', 'you_tubes.title as youtubeTitle', 'you_tubes.video_id as video_id')
                ->with(['audio_music'])
                ->selectRaw('CONCAT("/storage/photos/1/resources/", resources.image) as image_path')
                ->selectRaw('CONCAT("/storage/photos/1/resources/", resources.cover_image) as cover_image_path')
                ->selectRaw('CONCAT("/storage/photos/1/video-content/", video_contents.video) as video_content')
                ->leftJoin('ecw_classes as cls', 'cls.id', '=', 'resources.class_id')
                ->leftJoin('subjects', 'subjects.id', '=', 'resources.subject_id')
                ->leftJoin('you_tubes', 'you_tubes.id', '=', 'resources.video_id')
                ->leftJoin('video_contents', 'video_contents.id', '=', 'resources.video_id')
                ->where('resources.language', $locale)
                ->where('resources.slug', $request->slug)
                ->firstOrFail();
            $response = [
                'status' => true,
                'title' => $resources->title,
                'meta_tag' => $request->meta_tag,
                'data' => [
                    'current_nav' => $currentNav,
                    'resources' => $resources,
                ],
            ];
            return Inertia::render('Resources/component/ResourceDetails')->with($response);
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
