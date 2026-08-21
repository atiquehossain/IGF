<?php

namespace App\Services;

use App\Models\AnnualReport;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\LatestNews;
use App\Models\NoticeBoard;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

final class LegacyMediaReferenceService
{
    /**
     * A flat collection has one physical directory shared by every row. Both
     * legacy pointer columns must therefore be checked before a basename can
     * be removed.
     *
     * @var array<string, array{class-string<Model>, list<string>}>
     */
    private const FLAT_REFERENCES = [
        'banner' => [Banner::class, ['image', 'path']],
        'category' => [Category::class, ['image', 'path']],
        'notice_board' => [NoticeBoard::class, ['image_path']],
        'our_members' => [LatestNews::class, ['image', 'path']],
        'page' => [Page::class, ['thumbnail']],
        'testimonial' => [Testimonial::class, ['photo']],
    ];

    public function flatImageInUse(
        string $collection,
        string $name,
        ?Model $excluding = null,
    ): bool {
        return $this->failClosed(function () use ($collection, $name, $excluding): bool {
            $definition = self::FLAT_REFERENCES[$collection] ?? null;
            if ($definition === null || !$this->isSafeBasename($name)) {
                return true;
            }

            [$modelClass, $fields] = $definition;

            return $this->modelHasReference(
                $modelClass,
                $fields,
                fn (Model $record, string $value): bool => $this->filename($value) === $name,
                $excluding,
            );
        });
    }

    public function galleryImageInUse(
        int $recordId,
        string $name,
        ?Model $excluding = null,
    ): bool {
        return $this->failClosed(function () use ($recordId, $name, $excluding): bool {
            if ($recordId < 1 || !$this->isSafeBasename($name)) {
                return true;
            }

            $directory = "photos/1/gallery/{$recordId}/";

            return $this->modelHasReference(
                Gallery::class,
                ['image', 'path', 'url'],
                function (Model $record, string $value) use ($recordId, $name, $directory): bool {
                    if ((int) $record->getKey() === $recordId && $this->filename($value) === $name) {
                        return true;
                    }

                    $path = $this->normalizedWebPath($value);

                    return $path !== null
                        && str_starts_with($path, $directory)
                        && $this->filename($path) === $name;
                },
                $excluding,
            );
        });
    }

    public function physicalPathInUse(
        string $disk,
        string $path,
        ?Model $excluding = null,
    ): bool {
        return $this->failClosed(function () use ($disk, $path, $excluding): bool {
            $path = $this->normalizedPhysicalPath($path);
            if ($path === null) {
                return true;
            }

            if ($disk === 'public'
                && preg_match('#\Aphotos/1/(banner|category|notice_board|our_members|page|testimonial)/([^/]+)\z#', $path, $match)) {
                return $this->flatImageInUse($match[1], rawurldecode($match[2]), $excluding);
            }

            if ($disk === 'public'
                && preg_match('#\Aphotos/1/gallery/(\d+)(?:/.*)?\z#', $path, $match)) {
                return $this->galleryDirectoryInUse((int) $match[1], $excluding);
            }

            if ($disk === 'local'
                && preg_match('#\Aannual-reports/([^/]+)\z#', $path, $match)) {
                return $this->filenameReferenceInUse(
                    AnnualReport::class,
                    ['image_path', 'file_path'],
                    rawurldecode($match[1]),
                    $excluding,
                );
            }

            if ($disk === 'local'
                && preg_match('#\Anotice-attachments/([^/]+)\z#', $path, $match)) {
                return $this->filenameReferenceInUse(
                    NoticeBoard::class,
                    ['file_path'],
                    rawurldecode($match[1]),
                    $excluding,
                );
            }

            if ($disk === 'local'
                && preg_match('#\Auploads/users/(\d+/350X350/[^/]+)\z#i', $path, $match)) {
                $relativePath = rawurldecode($match[1]);

                return $this->modelHasReference(
                    User::class,
                    ['avatar'],
                    fn (Model $record, string $value): bool => $this->normalizedAvatarPath($value) === $relativePath,
                    $excluding,
                );
            }

            // Unknown legacy layouts are deliberately retained. A cleanup
            // failure is preferable to breaking a surviving content record.
            return true;
        });
    }

    private function galleryDirectoryInUse(int $recordId, ?Model $excluding): bool
    {
        if ($recordId < 1) {
            return true;
        }

        $directory = "photos/1/gallery/{$recordId}/";

        return $this->modelHasReference(
            Gallery::class,
            ['image', 'path', 'url'],
            function (Model $record, string $value) use ($recordId, $directory): bool {
                if ((int) $record->getKey() === $recordId && $this->filename($value) !== null) {
                    return true;
                }

                $path = $this->normalizedWebPath($value);

                return $path !== null && str_starts_with($path, $directory);
            },
            $excluding,
        );
    }

    /** @param class-string<Model> $modelClass @param list<string> $fields */
    private function filenameReferenceInUse(
        string $modelClass,
        array $fields,
        string $name,
        ?Model $excluding,
    ): bool {
        if (!$this->isSafeBasename($name)) {
            return true;
        }

        return $this->modelHasReference(
            $modelClass,
            $fields,
            fn (Model $record, string $value): bool => $this->filename($value) === $name,
            $excluding,
        );
    }

    /**
     * @param class-string<Model> $modelClass
     * @param list<string> $fields
     * @param Closure(Model, string): bool $matches
     */
    private function modelHasReference(
        string $modelClass,
        array $fields,
        Closure $matches,
        ?Model $excluding,
    ): bool {
        $model = new $modelClass;
        $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
            ? $modelClass::withTrashed()
            : $modelClass::query();

        if ($excluding instanceof $modelClass && $excluding->getKey() !== null) {
            $query->where($model->getKeyName(), '<>', $excluding->getKey());
        }

        $found = false;
        $key = $model->getKeyName();
        $query->select(array_values(array_unique([$key, ...$fields])))
            ->chunkById(250, function ($records) use ($fields, $matches, &$found): bool {
                foreach ($records as $record) {
                    foreach ($fields as $field) {
                        $value = $record->getRawOriginal($field);
                        if (is_string($value) && $value !== '' && $matches($record, $value)) {
                            $found = true;

                            return false;
                        }
                    }
                }

                return true;
            }, $key, $key);

        return $found;
    }

    private function filename(string $value): ?string
    {
        $path = $this->normalizedWebPath($value);
        if ($path === null) {
            return null;
        }

        $name = rawurldecode(basename($path));

        return $this->isSafeBasename($name) ? $name : null;
    }

    private function normalizedAvatarPath(string $value): ?string
    {
        $path = $this->normalizedWebPath($value);
        if ($path === null) {
            return null;
        }
        if (str_starts_with($path, 'uploads/users/')) {
            $path = substr($path, strlen('uploads/users/'));
        }

        return preg_match('#\A\d+/350X350/[^/]+\z#i', $path) ? rawurldecode($path) : null;
    }

    private function normalizedWebPath(string $value): ?string
    {
        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            $value = (string) parse_url('https:' . $value, PHP_URL_PATH);
        } elseif (preg_match('#\Ahttps?://#i', $value)) {
            $value = (string) parse_url($value, PHP_URL_PATH);
        } else {
            $value = explode('#', explode('?', $value, 2)[0], 2)[0];
        }

        $value = preg_replace('#/+#', '/', $value) ?? '';
        $value = ltrim($value, '/');
        if (str_starts_with($value, 'public/storage/')) {
            $value = substr($value, strlen('public/storage/'));
        } elseif (str_starts_with($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        }

        return $value !== '' && !$this->containsTraversal($value) ? $value : null;
    }

    private function normalizedPhysicalPath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = preg_replace('#/+#', '/', $path) ?? '';
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' && !$this->containsTraversal($path) ? $path : null;
    }

    private function containsTraversal(string $path): bool
    {
        return in_array('..', explode('/', rawurldecode($path)), true)
            || str_contains($path, "\0");
    }

    private function isSafeBasename(string $name): bool
    {
        return $name !== ''
            && $name === basename($name)
            && !str_contains($name, '\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $name);
    }

    private function failClosed(Closure $lookup): bool
    {
        try {
            return (bool) $lookup();
        } catch (Throwable $exception) {
            report($exception);

            return true;
        }
    }
}
