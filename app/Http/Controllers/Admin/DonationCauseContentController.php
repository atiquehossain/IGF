<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationType;
use App\Models\MediaAsset;
use App\Services\DonationCauseContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DonationCauseContentController extends Controller
{
    public function __construct(private DonationCauseContentService $content)
    {
    }

    public function edit(DonationType $donationType): View
    {
        $donationType->load(['amountCards', 'landingSections.imageAsset', 'landingSections.videoAsset']);

        $referencedImageUuids = $donationType->landingSections
            ->pluck('image_media_uuid')
            ->filter()
            ->unique();
        $referencedVideoUuids = $donationType->landingSections
            ->pluck('video_media_uuid')
            ->filter()
            ->unique();

        $images = $this->mediaOptions('image/', $referencedImageUuids->all());
        $videos = $this->mediaOptions('video/', $referencedVideoUuids->all());
        $publicUrl = $donationType->purpose_key === 'direct'
            ? route('frontend.donate.direct')
            : route('frontend.donate.cause', ['cause' => $donationType->slug]);

        return view('admin.donationType.content', [
            'title' => __('donation_content.controller.page_title'),
            'donationType' => $donationType,
            'amountCards' => $donationType->amountCards->values(),
            'landingSections' => $donationType->landingSections->values(),
            'images' => $images,
            'videos' => $videos,
            'layoutOptions' => collect(DonationCauseContentService::LAYOUT_OPTIONS)
                ->map(fn (string $translationKey): string => __($translationKey))
                ->all(),
            'maxAmountCards' => DonationCauseContentService::MAX_AMOUNT_CARDS,
            'maxLandingSections' => DonationCauseContentService::MAX_LANDING_SECTIONS,
            'minDonationAmount' => DonationCauseContentService::MIN_AMOUNT,
            'maxDonationAmount' => DonationCauseContentService::MAX_AMOUNT,
            'publicUrl' => $publicUrl,
        ]);
    }

    public function update(Request $request, DonationType $donationType): RedirectResponse
    {
        $payload = $this->content->validateAdminPayload($request->all());
        try {
            $this->content->replace($donationType, $payload, $request->user('admin'));
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return redirect()
                ->route('donationType.content.edit', $donationType)
                ->withInput()
                ->withErrors([
                    'content_editor_version' => $exception->getMessage()
                        ?: $this->content->conflictMessage(),
                ]);
        }

        return redirect()
            ->route('donationType.content.edit', $donationType)
            ->with([
                'message' => __('donation_content.controller.saved'),
                'alert-type' => 'success',
            ]);
    }

    private function mediaOptions(string $mimePrefix, array $referencedUuids)
    {
        $recent = MediaAsset::query()
            ->where('mime_type', 'like', $mimePrefix . '%')
            ->latest()
            ->limit(150)
            ->get();

        if ($referencedUuids === []) {
            return $recent;
        }

        return $recent
            ->concat(MediaAsset::query()
                ->whereIn('uuid', $referencedUuids)
                ->where('mime_type', 'like', $mimePrefix . '%')
                ->get())
            ->unique('uuid')
            ->values();
    }
}
