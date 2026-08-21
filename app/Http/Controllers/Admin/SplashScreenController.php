<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\SplashScreen;

use App\Helper\Translation;
use App\Helper\Seq;
use Carbon\Carbon;
use Exception;

class SplashScreenController extends Controller
{
    public function index(Request $request, $uuid = null)
    {
        $title = 'Visitor announcement';
        $translations = Translation::languageList();
        $splashScreens = SplashScreen::query()
            ->latest('published_at')
            ->latest('id')
            ->get();
        $splashEnabled = $splashScreens->contains(fn (SplashScreen $splash) => (bool) $splash->status);
        return view('admin.splash_screen.index')->with(compact('title', 'splashScreens', 'splashEnabled', 'translations', 'uuid'));
    }

    public function store(Request $request)
    {

        $request->validate([
            'language' => ['required', 'array', 'min:1'],
            'language.*' => ['required', 'string', 'max:10'],
            'title' => ['required', 'array'],
            'title.*' => ['required', 'string', 'max:255'],
            'details' => ['required', 'array'],
            'details.*' => ['required', 'string', 'max:25000'],
            'published_at' => ['required', 'array'],
            'published_at.*' => ['required', 'date_format:d-m-Y'],
            'enabled' => 'required|boolean',
        ]);
        
        try {
            DB::beginTransaction();
            $uuid = Seq::uuidV4();
            SplashScreen::query()->update(['status' => 0]);
            foreach ($request->language as $language) {
                $subject = SplashScreen::find(@$request->id[$language]);
                $published_at = Carbon::createFromFormat('d-m-Y', $request->published_at[$language])->startOfDay();
                if ($subject) {
                    $subject->update([
                        'uuid' => $uuid,
                        'title' => $request->title[$language],
                        'details' => $request->details[$language],
                        'language' => $language,
                        'published_at' => $published_at,
                        'status' => $request->boolean('enabled'),
                    ]);
                } else {
                    $subject = SplashScreen::create([
                        'uuid' => $uuid,
                        'title' => $request->title[$language],
                        'details' => $request->details[$language],
                        'language' => $language,
                        'published_at' => $published_at,
                        'status' => $request->boolean('enabled'),
                    ]);
                }
            }
            DB::commit();
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            );
            return redirect(route('splash.screen.index'))->with($notification);
        } catch (Exception $e) {
            DB::rollback();
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            );
            return back()->with($notification);
        }
    }
}
