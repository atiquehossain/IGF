<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Permission;
use App\Models\VolunteerCause;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VolunteerCauseController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Volunteer Opportunities';
        $search = trim((string) $request->input('search', ''));
        $volunteerCauses = VolunteerCause::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $admin = auth('admin')->user();
        $permissions = app(Permission::class);

        return view('admin.volunteerCause.index', [
            'title' => $title,
            'volunteerCauses' => $volunteerCauses,
            'search' => $search,
            'canCreateOpportunity' => $permissions->allows($admin, 'volunteerCause.store'),
            'canEditOpportunity' => $permissions->allows($admin, 'volunteerCause.edit'),
            'canPublishOpportunity' => $permissions->allows($admin, 'volunteerCause.status'),
            'canDeleteOpportunity' => $permissions->allows($admin, 'volunteerCause.destroy'),
        ]);
    }

    public function create()
    {
        return redirect()->route('volunteerCause.index')->with('message', 'Create volunteer causes from the cause list.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('volunteer_causes', 'name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        VolunteerCause::create([
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'status' => false,
        ]);

        return back()->with([
            'message' => 'Volunteer opportunity saved as a draft. Publish it when it is ready for the public sign-up form.',
            'alert-type' => 'success',
        ]);
    }

    public function show($id = null, Request $request)
    {
        return redirect()->route('volunteerCause.index')->with('message', 'Volunteer cause details are managed from the cause list.');
    }

    public function edit($id = null, Request $request)
    {
        $volunteerCause = VolunteerCause::query()
            ->select('id', 'name', 'description', 'status')
            ->findOrFail($id);

        return response()->json(['data' => $volunteerCause]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', Rule::exists('volunteer_causes', 'id')->whereNull('deleted_at')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('volunteer_causes', 'name')->ignore($request->integer('id'))->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $volunteerCause = VolunteerCause::query()->findOrFail($data['id']);
        $volunteerCause->update([
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ]);

        return back()->with([
            'message' => 'Volunteer opportunity updated.',
            'alert-type' => 'success',
        ]);
    }

    public function status(Request $request)
    {
        $volunteerCause = VolunteerCause::query()->findOrFail($request->route('id'));
        $volunteerCause->update(['status' => !$volunteerCause->status]);

        $message = $volunteerCause->status
            ? 'Volunteer opportunity published. It now appears on the public sign-up form.'
            : 'Volunteer opportunity unpublished. It is now hidden from the public sign-up form.';

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'status' => (bool) $volunteerCause->status])
            : back()->with(['message' => $message, 'alert-type' => 'success']);
    }

    public function destroy($id = null, Request $request)
    {
        $volunteerCause = VolunteerCause::query()->findOrFail($id);
        $volunteerCause->delete();
        $message = 'Volunteer opportunity moved to trash.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with(['message' => $message, 'alert-type' => 'success']);
    }
}
