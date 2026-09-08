<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

use App\Models\Division;
use App\Models\District;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str as SupportStr;
use Throwable;

class DivisionController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->DivisionTitle;
        $search = $request->search;
        $divisions = Division::where('name', 'like', '%' . $search . '%')->paginate(15);
        return view('admin.division.index')->with(compact('title', 'divisions', 'search'));
    }

    public function create() {
        return redirect()->route('division.index');
    }

    public function store(Request $request) {
        $validated = $request->validate($this->rules());

        try {
            $division = Division::create([
                'name' => trim((string) $validated['name']),
                'slug' => SupportStr::slug((string) $validated['name']),
                'description' => $this->nullableNarrative($validated['description'] ?? null),
                'description_bn' => $this->nullableNarrative($validated['description_bn'] ?? null),
                'status' => 0,
            ]);
            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function show($id = null, Request $request) {
        return redirect()->route('division.index');
    }

    public function edit($id = null, Request $request) {
        try {
            $division = Division::select('id', 'name', 'slug', 'description', 'description_bn')
                ->where('id', $id)
                ->first();
            if ($division === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }
            $response = [ 'data' => $division];
            return response($response, 200);
        } catch (Throwable $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 404);
        }
    }

    public function update(Request $request) {
        $validated = $request->validate($this->rules($request->integer('id')) + [
            'id' => ['required', 'integer', 'exists:divisions,id'],
        ]);
        try {
            $division = Division::find($request->id);
            if (empty($division)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $division->update([
                'name' => trim((string) $validated['name']),
                // The slug is deliberately stable so existing public links keep working.
                'description' => array_key_exists('description', $validated)
                    ? $this->nullableNarrative($validated['description'])
                    : $division->description,
                'description_bn' => array_key_exists('description_bn', $validated)
                    ? $this->nullableNarrative($validated['description_bn'])
                    : $division->description_bn,
            ]);


            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Throwable $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request, int $id) {
        try {
            $data = Division::find($id);
            if ($data === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            if ((bool) $data->status) {
                $dependencies = [
                    'active_districts' => District::query()
                        ->where('division_id', $data->id)
                        ->where('status', 1)
                        ->count(),
                    'published_team_members' => LatestNews::query()
                        ->where('type', 'our-members')
                        ->where('division_id', $data->id)
                        ->where('status', 1)
                        ->count(),
                    'published_activities' => NoticeBoard::query()
                        ->where('division_id', $data->id)
                        ->where('status', 1)
                        ->count(),
                ];
                if (array_sum($dependencies) > 0) {
                    return response([
                        'message' => 'Unpublish or reassign this division’s active districts, team profiles, and activities first.',
                        'dependencies' => $dependencies,
                    ], 409);
                }
            }

            $data->status = !((bool) $data->status);
            $data->save();

            return response([
                'status' => (bool) $data->status,
                'message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully),
            ], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request) {
        try {
            $division = Division::find($id);
            if ($division === null) {
                return response(['message' => $request->Lang->Common->Form->DataNotFound], 404);
            }

            $dependencies = [
                'districts' => District::where('division_id', $division->id)->count(),
                'users' => User::where('division_id', $division->id)->count(),
                'team_members' => LatestNews::where('type', 'our-members')->where('division_id', $division->id)->count(),
                'publications' => NoticeBoard::where('division_id', $division->id)->count(),
            ];
            if (array_sum($dependencies) > 0) {
                return response([
                    'message' => 'This division cannot be deleted while districts, registrations, team members, or activities still reference it.',
                    'dependencies' => $dependencies,
                ], 409);
            }

            $division->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Throwable $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    /** @return array<string, mixed> */
    private function rules(?int $ignoreId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('divisions', 'name')->ignore($ignoreId),
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId): void {
                    if ($ignoreId !== null) {
                        return;
                    }
                    $slug = SupportStr::slug(trim((string) $value));
                    if ($slug === '') {
                        $fail('Enter a division name that can create a readable website address.');

                        return;
                    }

                    if (Division::query()->where('slug', $slug)->exists()) {
                        $fail('Another division already uses this website address.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_bn' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function nullableNarrative(mixed $value): ?string
    {
        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/\r\n?/", "\n", $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : $value;
    }

}
