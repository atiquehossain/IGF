<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Subscriber;
use App\Services\AdminPrivateSearch;
use App\Services\AdminAuditService;
use Throwable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function sendEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'signature_image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    
        try {
            $email = $request->email;
            $subject = $request->subject;
            $message = $request->message;
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
    
                // Generate the full URL using asset()
                $signatureImageUrl = asset('storage/' . $path);

            }

            Mail::send('admin.emails.subscriber_notification', ['body' => $message, 'signatureImageUrl' => $signatureImageUrl], function ($mail) use ($email, $subject) {
                $mail->to($email)
                     ->subject($subject);
            });
    
            Log::info('Subscriber email dispatched.', ['has_signature_image' => $signatureImageUrl !== null]);
            return response()->json(['message' => 'Email sent successfully.'], 200);
        } catch (Throwable $e) {
            Log::error('Subscriber email dispatch failed.', [
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
