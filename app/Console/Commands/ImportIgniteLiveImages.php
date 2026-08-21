<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportIgniteLiveImages extends Command
{
    private const MAX_BYTES = 25 * 1024 * 1024;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml' => 'svg',
    ];

    protected $signature = 'igf:import-live-images
        {inventory : JSON inventory produced from the public Ignite site}
        {--dry-run : Validate and download without storing files or database records}';

    protected $description = 'Safely import first-party public Ignite images into the media library';

    public function handle(): int
    {
        try {
            $inventory = $this->readInventory((string) $this->argument('inventory'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $records = [];
        $failures = [];
        $hashes = [];
        $created = 0;
        $reused = 0;

        $bar = $this->output->createProgressBar(count($inventory['assets']));
        $bar->start();

        foreach ($inventory['assets'] as $entry) {
            try {
                $download = $this->downloadAndInspect((string) ($entry['url'] ?? ''));
                $hash = hash('sha256', $download['body']);

                if (isset($hashes[$hash])) {
                    $stored = $hashes[$hash];
                    $reused++;
                } else {
                    $stored = $this->storeAsset($entry, $download, $hash);
                    $hashes[$hash] = $stored;
                    $created += $stored['created'] ? 1 : 0;
                    $reused += $stored['created'] ? 0 : 1;
                }

                $records[] = [
                    'source_url' => $entry['url'],
                    'source_pages' => array_values(array_unique($entry['pages'] ?? [])),
                    'local_path' => $stored['path'],
                    'public_url' => Storage::disk('public')->url($stored['path']),
                    'media_uuid' => $stored['uuid'],
                    'sha256' => $hash,
                    'mime_type' => $download['mime'],
                    'bytes' => strlen($download['body']),
                    'width' => $download['width'],
                    'height' => $download['height'],
                ];
            } catch (Throwable $exception) {
                $failures[] = [
                    'source_url' => (string) ($entry['url'] ?? ''),
                    'reason' => $exception->getMessage(),
                ];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $manifest = [
            'source' => $inventory['source'] ?? 'https://ignite.org.bd/',
            'discovered_at' => $inventory['discovered_at'] ?? null,
            'imported_at' => now()->toIso8601String(),
            'pages_scanned' => count($inventory['pages'] ?? []),
            'source_image_count' => count($inventory['assets']),
            'stored_image_count' => count($hashes),
            'failed_image_count' => count($failures),
            'images' => $records,
            'failures' => $failures,
        ];

        if (!$this->option('dry-run')) {
            Storage::disk('local')->put(
                'imports/ignite-live/source-inventory.json',
                json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
            Storage::disk('local')->put(
                'imports/ignite-live/manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
        }

        $this->info(sprintf(
            '%d source images processed; %d unique files stored; %d existing/duplicate files reused; %d failed.',
            count($records),
            count($hashes),
            $reused,
            count($failures)
        ));

        if ($created > 0) {
            $this->line(sprintf('%d new Media Library record(s) created.', $created));
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->warn($failure['source_url'] . ': ' . $failure['reason']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function readInventory(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The image inventory file is missing or unreadable.');
        }

        $inventory = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($inventory) || !isset($inventory['assets']) || !is_array($inventory['assets'])) {
            throw new RuntimeException('The image inventory must contain an assets array.');
        }

        return $inventory;
    }

    private function downloadAndInspect(string $url): array
    {
        $parts = parse_url($url);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'ignite.org.bd'
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException('Only HTTPS assets hosted on ignite.org.bd can be imported.');
        }

        $response = Http::withHeaders([
            'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif,image/svg+xml',
            'User-Agent' => 'IgniteGlobalFoundationMediaImporter/1.0',
        ])->withOptions(['allow_redirects' => false])
            ->connectTimeout(10)
            ->timeout(40)
            ->get($url);

        $this->assertSuccessfulImageResponse($response);
        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('The image is empty or exceeds the 25 MB import limit.');
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        $headerMime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $mime = $this->normalizeMime($detected, $headerMime, $body);
        if (!array_key_exists($mime, self::MIME_EXTENSIONS)) {
            throw new RuntimeException('The downloaded file is not a supported image.');
        }

        if ($mime === 'image/svg+xml') {
            $this->assertSafeSvg($body);
            $width = null;
            $height = null;
        } else {
            $dimensions = @getimagesizefromstring($body);
            if ($dimensions === false) {
                throw new RuntimeException('The downloaded image signature or dimensions are invalid.');
            }
            [$width, $height] = $dimensions;
            if ($width < 1 || $height < 1 || $width * $height > 80_000_000) {
                throw new RuntimeException('The image dimensions exceed the safe 80 megapixel limit.');
            }
        }

        return compact('body', 'mime', 'width', 'height');
    }

    private function assertSuccessfulImageResponse(Response $response): void
    {
        if (!$response->successful()) {
            throw new RuntimeException('The source returned HTTP ' . $response->status() . '.');
        }

        $contentLength = (int) ($response->header('Content-Length') ?: 0);
        if ($contentLength > self::MAX_BYTES) {
            throw new RuntimeException('The image exceeds the 25 MB import limit.');
        }
    }

    private function normalizeMime(string $detected, string $headerMime, string $body): string
    {
        $aliases = [
            'image/jpg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            'application/xml' => 'image/svg+xml',
            'text/xml' => 'image/svg+xml',
            'text/plain' => 'image/svg+xml',
        ];
        $detected = $aliases[$detected] ?? $detected;
        $headerMime = $aliases[$headerMime] ?? $headerMime;

        if ($this->looksLikeSvg($body) && $headerMime === 'image/svg+xml') {
            return 'image/svg+xml';
        }

        return array_key_exists($detected, self::MIME_EXTENSIONS) ? $detected : '';
    }

    private function looksLikeSvg(string $body): bool
    {
        return preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg\b/i', $body) === 1;
    }

    private function assertSafeSvg(string $body): void
    {
        if (
            !$this->looksLikeSvg($body)
            || preg_match('/<(?:script|foreignObject|iframe|object|embed)\b/i', $body)
            || preg_match('/\son[a-z]+\s*=/i', $body)
            || preg_match('/(?:href|src)\s*=\s*["\']\s*(?:https?:|data:|javascript:|\/\/)/i', $body)
        ) {
            throw new RuntimeException('The SVG contains active or external content.');
        }
    }

    private function storeAsset(array $entry, array $download, string $hash): array
    {
        $extension = self::MIME_EXTENSIONS[$download['mime']];
        $sourceName = basename((string) parse_url((string) $entry['url'], PHP_URL_PATH));
        $stem = Str::slug((string) pathinfo($sourceName, PATHINFO_FILENAME));
        $stem = Str::limit($stem !== '' ? $stem : 'ignite-image', 80, '');
        $filename = $stem . '-' . substr($hash, 0, 12) . '.' . $extension;
        $path = 'media/ignite-live/' . $filename;

        if ($this->option('dry-run')) {
            return ['path' => $path, 'uuid' => null, 'created' => false];
        }

        Storage::disk('public')->put($path, $download['body']);

        $asset = DB::transaction(function () use ($entry, $download, $extension, $sourceName, $path) {
            $asset = MediaAsset::withTrashed()->firstOrNew([
                'disk' => 'public',
                'path' => $path,
            ]);
            $created = !$asset->exists;
            if ($created) {
                $asset->uuid = (string) Str::uuid();
            }
            $asset->fill([
                'original_name' => $sourceName !== '' ? $sourceName : basename($path),
                'mime_type' => $download['mime'],
                'extension' => $extension,
                'bytes' => strlen($download['body']),
                'width' => $download['width'],
                'height' => $download['height'],
                'alt_text' => $this->deriveAltText($sourceName),
                'caption' => 'Imported from ' . $entry['url'],
                'locale' => '*',
                'uploaded_by' => null,
            ]);
            $asset->save();
            if ($asset->trashed()) {
                $asset->restore();
            }

            return [$asset, $created];
        });

        return [
            'path' => $path,
            'uuid' => $asset[0]->uuid,
            'created' => $asset[1],
        ];
    }

    private function deriveAltText(string $sourceName): string
    {
        $stem = Str::of(pathinfo($sourceName, PATHINFO_FILENAME))
            ->replaceMatches('/[_-]+/', ' ')
            ->replaceMatches('/\b(?:rsz|img|image|edited|size|copy)\b/i', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        if ($stem->isEmpty() || preg_match('/^[a-z0-9]{18,}$/i', (string) $stem)) {
            return 'Ignite Global Foundation programme photograph';
        }

        return Str::of((string) $stem)->headline()->limit(150, '')->toString();
    }
}
