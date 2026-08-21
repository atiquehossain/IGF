<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Skill;
use App\Models\Activity;
use Inertia\Inertia;

use App\Helper\StaticUtil;
use App\Helper\MyMenu;

use Exception;
use Auth;

class ActivitiesController extends Controller
{
    public function activities(Request $request, $locale = 'en')
    {
        $title = $request->Lang->Activity->Title;
        $age = $request->age;
        $filter = $request->filter;
        $user = Auth::user();

        try {
            $skills = Skill::select('id as value', 'name as text')->where('language', $locale)->get();
            $activityTabs = StaticUtil::activityTabs($locale);
            $allActivities = Activity::select('activities.*')
                ->selectRaw('CONCAT("/storage/photos/1/activities/", activities.image) as image_path')
                ->selectRaw('CONCAT("/storage/photos/1/activities/", activities.cover_image) as cover_image_path')
                ->where('language', $locale)
                ->where('type', 'sel')
                ->where('status', 1);

            if($user){
              $allActivities = $allActivities->where('activities.is_public',1)
                              ->orWhere('activities.is_public',0)
                              ->orWhere('activities.is_public',null)
                              ->where('activities.language', $locale);
            } else{
              $allActivities = $allActivities->where('activities.is_public',1)
                              ->where('activities.language', $locale);
            }
            
            $allActivities = $allActivities->paginate(15);  

            $response = [
                'status' => true,
                'title' =>  $title,
                'meta_tag' => $request->meta_tag,
                'properties' => [
                    'page' => $allActivities->currentPage(),
                    'total_page' => $allActivities->lastPage(),
                    'total_count' => $allActivities->total(),
                ],
                'data' => [
                    'skills' => $skills,
                    'activityTabs' => $activityTabs,
                    'allActivities' => $allActivities->items(),
                    'age' => $age,
                    'filter' => $filter,
                ],
            ];
            return Inertia::render('Activities/activities')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }

    public function activitiesDetail(Request $request, $locale = 'en')
    {
        $title = $request->Lang->Activity->Details;
        
        try {
            $parentSlug = explode(".activitiesDetail",$request->route()->getName())[0];
            $currentNav = MyMenu::firstMenuBySlug($locale, $parentSlug);
            
            $activityDetails = Activity::select('activities.*')
                ->with(['audio_music'])
                ->selectRaw('CONCAT("/storage/photos/1/activities/", activities.image) as image_path')
                ->selectRaw('CONCAT("/storage/photos/1/activities/", activities.cover_image) as cover_image_path')
                ->where('language', $locale)
                ->where('slug', $request->slug)
                ->firstOrFail();

            $response = [
                'status' => true,
                'title' =>  $activityDetails->title,
                'meta_tag' => $request->meta_tag,
                'data' => [
                    'activityDetails' => $activityDetails,
                    'current_nav' => $currentNav,
                ],
            ];
            
            return Inertia::render('Activities/components/ActivitiesDetail')->with($response);
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
