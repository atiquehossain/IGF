<?php

namespace App\Http\Controllers\Vue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Subject;
use App\Models\EcwClass;
use App\Models\Package;
use App\Models\InteractiveRadio;

use App\Helper\StaticUtil;
use App\Helper\MyMenu;

use Inertia\Inertia;
use Exception;
use Auth;

class InteractiveAudioController extends Controller
{
    public function interactiveAudio(Request $request, $locale = 'en', $slug = null)
    {
        $title = $request->Lang->InteractiveAudio->Title;
        try {
            $subjects = Subject::select('id as value', 'name as text')->where('language', $locale)->get();
            $classes = EcwClass::select('id as value', 'name as text')->where('language', $locale)->get();
            $packages = Package::select('id as value', 'name as text')->where('language', $locale)->get();
            $interactiveAudioTabs = StaticUtil::interactiveAudioTabs($locale);
            $user = Auth::user();

            $interactive_radios = InteractiveRadio::select('interactive_radios.*')
                ->with(['package', 'subject', 'ecwclass', 'ecwclass', 'audio'])
                ->selectRaw('CONCAT("/storage/photos/1/interactive_radios/", interactive_radios.cover_image) as cover_image_path')
                ->where('interactive_radios.language', $locale)
                ->where('interactive_radios.status', 1);

            if($user){
              $interactive_radios = $interactive_radios->where('interactive_radios.is_public',1)
                              ->orWhere('interactive_radios.is_public',0)
                              ->orWhere('interactive_radios.is_public',null)
                              ->where('interactive_radios.language', $locale);
            } else{
              $interactive_radios = $interactive_radios->where('interactive_radios.is_public',1)
                              ->where('interactive_radios.language', $locale);
            }
            
            $interactive_radios = $interactive_radios->paginate(5);    

            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
                'properties' => [
                    'page' => $interactive_radios->currentPage(),
                    'total_page' => $interactive_radios->lastPage(),
                    'total_count' => $interactive_radios->total(),
                ],
                'data' => [
                    'subjects' => $subjects,
                    'classes' => $classes,
                    'packages' => $packages,
                    'interactiveAudioTabs' => $interactiveAudioTabs,
                    'interactive_radios' => $interactive_radios->items()
                ],
            ];
            return Inertia::render('InteractiveAudio/interactiveAudio')->with($response);
        } catch (Exception $e) {
            $response = [
                'status' => true,
                'title' => $title,
                'meta_tag' => $request->meta_tag,
            ];
            return Inertia::render('errors-404')->with($response);
        }
    }
    
    public function interactiveAudioDetail(Request $request, $locale = 'en', $slug = null)
    {
        $title = $request->Lang->InteractiveAudio->Title;
        $slug = $request->slug;
        
        try {
            $parentSlug = explode(".resourceDetails",$request->route()->getName())[0];
            
            $interactive_radio = InteractiveRadio::select('interactive_radios.*')
                ->with(['audio_music'])
                ->selectRaw('CONCAT("/storage/photos/1/interactive_radios/", interactive_radios.cover_image) as cover_image_path')
                ->where('interactive_radios.slug',$slug)
                ->where('interactive_radios.language', $locale)
                ->where('interactive_radios.status', 1)
                ->first();

            $currentNav = MyMenu::firstMenuBySlug($locale, $parentSlug);    
            
            $response = [
                'status' => true,
                'title' => $interactive_radio->title,
                'meta_tag' => $request->meta_tag,
                'properties' => [],
                'data' => [
                    'interactive_radio' => $interactive_radio,
                    'current_nav' => $currentNav,
                ],
            ];
            
            return Inertia::render('InteractiveAudio/components/InteractiveAudioDetails')->with($response);
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
