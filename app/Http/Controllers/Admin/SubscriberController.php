<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SubscriberNotification;
use App\Models\Subscriber;
use App\Services\AdminAuditService;
use App\Services\AdminPrivateSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SubscriberController extends Controller
{
    public function __construct(
        private AdminPrivateSearch $privateSearch,
        private AdminAuditService $audit
    )
    {
    }

    public function index(Request $request)
    {
        if ($request->query->has('search')) {
            return redirect()->route('subscriber.index');
        }

        $title = "Subscribers Email";
        $search = $this->privateSearch->current($request, 'subscribers');

        $subscribers = Subscriber::query()
            ->confirmed()
            ->when($search !== '', fn ($query) => $query->where('email', 'like', '%' . $search . '%'))
            ->latest('id')
            ->paginate(15);

        return view('admin.subscriber.index')->with(compact('title', 'subscribers', 'search'));
    }

    public function destroy($id = null)
    {
        try {
            $subscribers = Subscriber::find($id);
            $subscribers->delete();
            return response(['message' => 'Deleted successfully.'], 200);
        } catch (Throwable $e) {
            return response(['message' => 'Could not delete.'], 403);
        }
    }

    public function excel_download(Request $request): StreamedResponse
    {
        $search = $this->privateSearch->current($request, 'subscribers');
        $query = Subscriber::query()
            ->confirmed()
            ->when($search !== '', fn ($builder) => $builder->where('email', 'like', '%' . $search . '%'));
        $fileName = "subscribe-data_" . date('Y-m-d') . ".xls";
        $this->audit->record(
            $request->user('admin'),
            'subscriber.exported',
            'subscriber-list',
            context: [
                'row_count' => (clone $query)->count(),
                'private_search_active' => $search !== '',
            ]
        );

        return response()->streamDownload(function () use ($search): void {
            echo "\xEF\xBB\xBF";
            echo "Email\tDate\n";
            Subscriber::query()
                ->confirmed()
                ->when($search !== '', fn ($builder) => $builder->where('email', 'like', '%' . $search . '%'))
                ->select(['id', 'email', 'created_at'])
                ->orderBy('id')
                ->chunkById(500, function ($subscribers): void {
                    foreach ($subscribers as $subscriber) {
                        echo self::safeSpreadsheetCell($subscriber->email)
                            . "\t"
                            . self::safeSpreadsheetCell($subscriber->created_at?->toIso8601String())
                            . "\n";
                    }
                });
        }, $fileName, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function sendEmail(Request $request, Subscriber $subscriber)
    {
        abort_unless($subscriber->confirmed_at !== null, 404);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'signature_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            $signatureImageUrl = null;

            if ($request->hasFile('signature_image')) {
                $image = $request->file('signature_image');
                $extension = match ($image->getMimeType()) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw new \RuntimeException('Unsupported signature image.'),
                };
                $filename = bin2hex(random_bytes(24)) . '.' . $extension;
                $path = $image->storeAs('signatures', $filename, 'public');
                $signatureImageUrl = asset('storage/' . $path);
            }

            Mail::to($subscriber->email)->send(new SubscriberNotification(
                $validated['subject'],
                $validated['message'],
                $signatureImageUrl
            ));

            $this->audit->record(
                $request->user('admin'),
                'subscriber.email_sent',
                $subscriber,
                context: ['has_signature_image' => $signatureImageUrl !== null]
            );
            Log::info('Subscriber email dispatched.', [
                'subscriber_id' => $subscriber->getKey(),
                'has_signature_image' => $signatureImageUrl !== null,
            ]);

            return response()->json(['message' => 'Email sent successfully.'], 200);
        } catch (Throwable $e) {
            Log::error('Subscriber email dispatch failed.', [
                'subscriber_id' => $subscriber->getKey(),
                'exception_class' => $e::class,
            ]);

            return response()->json(['message' => 'Failed to send email. Please try again later.'], 500);
        }
    }

    private static function safeSpreadsheetCell(mixed $value): string
    {
        $cell = str_replace(["\t", "\r", "\n"], ' ', (string) $value);

        return preg_match('/^[=+\-@]/', $cell) ? "'" . $cell : $cell;
    }
}
