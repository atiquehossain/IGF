<?php

namespace App\Services;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TechnicalSeoInternalFetcher
{
    public function __construct(private HttpKernel $kernel, private TechnicalSeoUrlPolicy $urls)
    {
    }

    /** @return array{status:int,content_type:string,body:string,location:?string,too_large:bool} */
    public function fetch(string $path, int $maxBytes): array
    {
        $safeTarget = $this->urls->internalAuditTarget($path, '/');
        if ($safeTarget === null || $safeTarget !== $path) {
            throw new \InvalidArgumentException('The audit fetcher only accepts normalized public application targets.');
        }
        $safePath = (string) (parse_url($safeTarget, PHP_URL_PATH) ?: '/');

        $file = $this->publicFile($safePath);
        if ($file !== null) {
            $size = filesize($file);
            if ($size === false || $size > $maxBytes) {
                return ['status' => 413, 'content_type' => '', 'body' => '', 'location' => null, 'too_large' => true];
            }
            $body = file_get_contents($file);

            return [
                'status' => 200,
                'content_type' => (string) (mime_content_type($file) ?: 'application/octet-stream'),
                'body' => is_string($body) ? $body : '',
                'location' => null,
                'too_large' => false,
            ];
        }

        $origin = $this->urls->origin();
        $url = $origin['scheme'] . '://' . $origin['host']
            . (in_array($origin['port'], [80, 443], true) ? '' : ':' . $origin['port'])
            . $safeTarget;
        $server = [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.1',
            'HTTP_X_SEO_AUDIT' => '1',
            'HTTP_HOST' => $origin['host'],
            'SERVER_PORT' => $origin['port'],
            'HTTPS' => $origin['scheme'] === 'https' ? 'on' : 'off',
        ];
        $request = Request::create($url, 'GET', [], [], [], $server);
        $previousLocale = app()->getLocale();
        try {
            /** @var Response $response */
            $response = $this->kernel->handle($request);
            $body = (string) $response->getContent();
            $tooLarge = strlen($body) > $maxBytes;

            return [
                'status' => $tooLarge ? 413 : $response->getStatusCode(),
                'content_type' => (string) $response->headers->get('Content-Type', ''),
                'body' => $tooLarge ? '' : $body,
                'location' => $response->headers->get('Location'),
                'too_large' => $tooLarge,
            ];
        } finally {
            if (isset($response)) {
                $this->kernel->terminate($request, $response);
            }
            app()->setLocale($previousLocale);
        }
    }

    private function publicFile(string $path): ?string
    {
        $candidate = public_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim(rawurldecode($path), '/')));
        $real = realpath($candidate);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $roots = array_filter([
            realpath(public_path()),
            realpath(storage_path('app/public')),
        ]);
        foreach ($roots as $root) {
            $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($real, $prefix)) {
                return $real;
            }
        }

        return null;
    }
}
