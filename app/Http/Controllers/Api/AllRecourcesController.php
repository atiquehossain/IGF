<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllRecourcesController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:60'],
            'selectedSubjects' => ['nullable', 'array', 'max:100'],
            'selectedSubjects.*' => ['integer'],
            'selectedClasses' => ['nullable', 'array', 'max:100'],
            'selectedClasses.*' => ['integer'],
            'selectedPackages' => ['nullable', 'array', 'max:100'],
            'selectedPackages.*' => ['integer'],
            'selectedTrainingTypes' => ['nullable', 'array', 'max:100'],
            'selectedTrainingTypes.*' => ['string', 'max:100'],
            'selectedAdvPackages' => ['nullable', 'array', 'max:100'],
            'selectedAdvPackages.*' => ['integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        // This endpoint survives upgrades from the legacy learning-resource
        // module. New Ignite installations intentionally do not create that
        // module's tables, so return the stable empty collection contract
        // instead of throwing a class/table error.
        if (!Schema::hasTable('resources')) {
            return $this->emptyResponse();
        }

        $query = DB::table('resources')->where('status', 1);
        $locale = (string) data_get($request, 'share.locale', app()->getLocale());

        $this->applyFilter($query, 'type', $filters['type'] ?? null, fn ($value) => $value !== 'view_all');
        $this->applyFilter($query, 'subject_id', $filters['selectedSubjects'] ?? null);
        $this->applyFilter($query, 'class_id', $filters['selectedClasses'] ?? null);
        $this->applyFilter($query, 'package_id', $filters['selectedPackages'] ?? null);
        $this->applyFilter($query, 'teacher_training_type', $filters['selectedTrainingTypes'] ?? null);
        $this->applyFilter($query, 'package_id', $filters['selectedAdvPackages'] ?? null);

        if (Schema::hasColumn('resources', 'language')) {
            $query->where('language', $locale);
        }
        if (!$request->user() && Schema::hasColumn('resources', 'is_public')) {
            $query->where('is_public', 1);
        }

        $resources = $query->orderByDesc('id')->paginate(15)->withQueryString();
        $items = collect($resources->items())->map(function ($resource): object {
            $resource->image_path = !empty($resource->image)
                ? '/storage/photos/1/resources/' . ltrim((string) $resource->image, '/')
                : null;
            $resource->cover_image_path = !empty($resource->cover_image)
                ? '/storage/photos/1/resources/' . ltrim((string) $resource->cover_image, '/')
                : null;

            return $resource;
        })->all();

        return response()->json([
            'status' => true,
            'properties' => [
                'page' => $resources->currentPage(),
                'total_page' => $resources->lastPage(),
                'total_count' => $resources->total(),
            ],
            'data' => ['resources' => $items],
        ]);
    }

    private function applyFilter(Builder $query, string $column, mixed $value, ?callable $condition = null): void
    {
        if (!Schema::hasColumn('resources', $column) || $value === null || $value === '' || $value === []) {
            return;
        }
        if ($condition && !$condition($value)) {
            return;
        }

        is_array($value) ? $query->whereIn($column, $value) : $query->where($column, $value);
    }

    private function emptyResponse()
    {
        return response()->json([
            'status' => true,
            'properties' => ['page' => 1, 'total_page' => 1, 'total_count' => 0],
            'data' => ['resources' => []],
        ]);
    }
}
