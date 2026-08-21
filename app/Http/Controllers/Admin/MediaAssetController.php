<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MediaAssetController extends Controller
{
    public function __construct(private MediaUsageService $usage)
    {
    }

    public function index(Request $request)
    {
        $query = MediaAsset::query()->latest();
        if ($request->boolean('trash')) {
            $query->onlyTrashed();
        }
        if ($request->filled('search')) {
            $query->where(function ($builder) use ($request) {
                $search = '%' . $request->string('search')->trim() . '%';
                $builder->where('original_name', 'like', $search)
                    ->orWhere('alt_text', 'like', $search)
                    ->orWhere('caption', 'like', $search);
            });
        }
        if ($request->string('type')->toString() === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        }
        if ($request->string('type')->toString() === 'document') {
            $query->where('mime_type', 'not like', 'image/%');
        }

        return view('admin.media.index', [
            'title' => 'Media library',
            'assets' => $query->paginate(30)->withQueryString(),
            'isTrash' => $request->boolean('trash'),
            'search' => $request->string('search')->toString(),
            'type' => $request->string('type')->toString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/webm,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $file = $data['file'];
        $path = $file->store('media/' . now()->format('Y/m'), 'public');
        $width = null;
        $height = null;
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $dimensions = @getimagesize($file->getRealPath());
            $width = $dimensions[0] ?? null;
            $height = $dimensions[1] ?? null;
        }

        $asset = MediaAsset::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'extension' => strtolower($file->getClientOriginalExtension()),
            'bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'locale' => $data['locale'] ?? '*',
            'uploaded_by' => auth('admin')->id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Media uploaded.', 'asset' => $asset], 201);
        }

        return back()->with(['message' => 'Media uploaded.', 'alert-type' => 'success']);
    }

    public function update(MediaAsset $mediaAsset, Request $request)
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
            'locale' => ['required', 'string', 'max:10'],
        ]);
        $mediaAsset->update($data);

        return $request->expectsJson()
            ? response()->json(['message' => 'Media details saved.', 'asset' => $mediaAsset->fresh()])
            : back()->with(['message' => 'Media details saved.', 'alert-type' => 'success']);
    }

    public function destroy(MediaAsset $mediaAsset, Request $request)
    {
        $mediaAsset->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Media moved to trash.'])
            : back()->with(['message' => 'Media moved to trash.', 'alert-type' => 'success']);
    }

    public function restore(string $uuid, Request $request)
    {
        $asset = MediaAsset::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $asset->restore();

        return $request->expectsJson()
            ? response()->json(['message' => 'Media restored.', 'asset' => $asset])
            : back()->with(['message' => 'Media restored.', 'alert-type' => 'success']);
    }

    public function forceDestroy(string $uuid, Request $request)
    {
        $asset = MediaAsset::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $references = $this->usage->references($asset);
        abort_if(array_sum($references) > 0, 422, 'This file is still referenced by published or draft content. Remove those references before permanent deletion.');

        Storage::disk($asset->disk)->delete($asset->path);
        $asset->forceDelete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Media permanently deleted.'])
            : back()->with(['message' => 'Media permanently deleted.', 'alert-type' => 'success']);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['trash', 'restore'])],
            'assets' => ['required', 'array', 'min:1'],
            'assets.*' => ['required', 'uuid'],
        ]);

        if ($data['action'] === 'trash') {
            MediaAsset::whereIn('uuid', $data['assets'])->delete();
        } else {
            MediaAsset::onlyTrashed()->whereIn('uuid', $data['assets'])->restore();
        }

        return back()->with(['message' => 'Media selection updated.', 'alert-type' => 'success']);
    }
}
