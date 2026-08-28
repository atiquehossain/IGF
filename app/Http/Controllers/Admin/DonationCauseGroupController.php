<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationCauseGroup;
use App\Models\DonationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DonationCauseGroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        DonationCauseGroup::create($this->validateGroup($request));

        return back()->with([
            'message' => 'Donation cause group created successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function update(Request $request, DonationCauseGroup $donationCauseGroup): RedirectResponse
    {
        $donationCauseGroup->update($this->validateGroup($request, $donationCauseGroup));

        return back()->with([
            'message' => 'Donation cause group updated successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function status(DonationCauseGroup $donationCauseGroup): RedirectResponse
    {
        DB::transaction(function () use ($donationCauseGroup): void {
            $locked = DonationCauseGroup::query()->lockForUpdate()->findOrFail($donationCauseGroup->id);
            $locked->update(['status' => !((bool) $locked->status)]);
        });

        return back()->with([
            'message' => 'Donation cause group visibility updated successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function destroy(DonationCauseGroup $donationCauseGroup): RedirectResponse
    {
        return DB::transaction(function () use ($donationCauseGroup): RedirectResponse {
            $locked = DonationCauseGroup::query()->lockForUpdate()->findOrFail($donationCauseGroup->id);
            $hasCauses = DonationType::withTrashed()
                ->where('donation_cause_group_id', $locked->id)
                ->exists();

            if ($hasCauses) {
                return back()->withErrors([
                    'donation_cause_group' => 'Move every attached donation cause to another group before deleting this group.',
                ], 'donationCauseGroup');
            }

            $locked->delete();

            return back()->with([
                'message' => 'Donation cause group deleted successfully.',
                'alert-type' => 'success',
            ]);
        });
    }

    private function validateGroup(Request $request, ?DonationCauseGroup $group = null): array
    {
        $name = trim(strip_tags((string) $request->input('group_name', '')));
        $description = trim(strip_tags((string) $request->input('group_description', '')));
        $attributes = Validator::make([
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'display_order' => $request->input('group_display_order', 0),
        ], [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('donation_cause_groups', 'name')->ignore($group?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'display_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ])->validateWithBag('donationCauseGroup');

        if ($group === null) {
            $attributes['status'] = true;
        }

        return $attributes;
    }
}
