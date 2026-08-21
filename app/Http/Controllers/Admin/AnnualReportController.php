<?php

namespace App\Http\Controllers\Admin;

use App\Helper\Str;
use App\Http\Controllers\Controller;
use App\Models\AnnualReport;
use App\Services\LegacyMediaReferenceService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Throwable;

class AnnualReportController extends Controller
{
    private const MAX_PDF_KILOBYTES = 10240;

    public function __construct(private LegacyMediaReferenceService $mediaReferences)
    {
    }

    public function index(Request $request)
    {
        $title = $request->Lang->AnnualReportTitle;
        $search = $request->search;
        $annual_reports = AnnualReport::query()
            ->where('title', 'like', '%' . $search . '%')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.annual-report.index')->with(compact('title', 'annual_reports', 'search'));
    }

    public function create(Request $request)
    {
        $title = $request->Lang->AnnualReportTitle . ' ' . $request->Lang->Common->Create;

        return view('admin.annual-report.add')->with(compact('title'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $storedName = null;

        try {
            $storedName = $this->storePdf($validated['annual_report_path']);
            $report = AnnualReport::create([
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'published_at' => $validated['published_at'],
                'publisher_name' => $validated['publisher_name'] ?? null,
                'url' => $validated['url'] ?? null,
                'language' => app()->getLocale(),
                'notice_type' => 'annual-report',
                'ip' => $request->ip(),
                'order_by' => $validated['order_by'] ?? null,
                'image_path' => $storedName,
                'file_type' => 'application/pdf',
                'file_size' => (string) $validated['annual_report_path']->getSize(),
                'status' => 0,
            ]);

            $notification = [
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success',
            ];

            return $request->boolean('save_and_update')
                ? redirect(route('annual.report.edit', $report->id))->with($notification)
                : redirect(route('annual.report.index'))->with($notification);
        } catch (Throwable $exception) {
            if ($storedName) {
                $this->deleteIfUnreferenced($storedName);
            }
            Log::error('Annual report creation failed.', ['exception_class' => $exception::class]);

            return back()->with([
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error',
            ]);
        }
    }

    public function show($id = null, Request $request)
    {
    }

    public function edit($id = null, Request $request)
    {
        $title = $request->Lang->AnnualReportTitle . ' ' . $request->Lang->Common->Update;
        $annual_report = AnnualReport::findOrFail($id);

        return view('admin.annual-report.edit')->with(compact('title', 'annual_report'));
    }

    public function update(Request $request)
    {
        $report = AnnualReport::findOrFail($request->integer('id'));
        $validated = $request->validate($this->rules($report));
        $storedName = null;
        $oldName = basename((string) $report->image_path);

        try {
            if (isset($validated['annual_report_path'])) {
                $storedName = $this->storePdf($validated['annual_report_path']);
            }

            $report->update([
                'title' => $validated['title'],
                'published_at' => $validated['published_at'],
                'publisher_name' => $validated['publisher_name'] ?? null,
                'notice_type' => 'annual-report',
                'order_by' => $validated['order_by'] ?? null,
                'url' => $validated['url'] ?? null,
                'ip' => $request->ip(),
                'image_path' => $storedName ?: $oldName,
                'file_type' => 'application/pdf',
                'file_size' => isset($validated['annual_report_path'])
                    ? (string) $validated['annual_report_path']->getSize()
                    : $report->file_size,
            ]);

            if ($storedName && $oldName !== '') {
                $this->deleteIfUnreferenced($oldName);
            }

            $notification = [
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success',
            ];

            return $request->boolean('save_and_update')
                ? redirect(route('annual.report.edit', $report->id))->with($notification)
                : redirect(route('annual.report.index'))->with($notification);
        } catch (Throwable $exception) {
            if ($storedName) {
                $this->deleteIfUnreferenced($storedName);
            }
            Log::error('Annual report update failed.', ['exception_class' => $exception::class]);

            return back()->with([
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error',
            ]);
        }
    }

    public function status(Request $request)
    {
        abort_unless($request->ajax(), 404);
        $report = AnnualReport::findOrFail($request->integer('id'));
        $report->update(['status' => $report->status ? 0 : 1]);

        return response([
            'message' => $report->status
                ? $request->Lang->Common->Form->PublishSuccessfully
                : $request->Lang->Common->Form->UnpublishSuccessfully,
        ]);
    }

    public function destroy($id = null, Request $request)
    {
        $report = AnnualReport::findOrFail($id);
        $report->delete();

        return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully]);
    }

    public function image($img = null)
    {
        $name = basename((string) $img);
        abort_if($name === '' || !Storage::disk('local')->exists('annual-reports/' . $name), 404);

        return response()->file(Storage::disk('local')->path('annual-reports/' . $name), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="annual-report.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function rules(?AnnualReport $report = null): array
    {
        $fileRules = [
            $report ? 'nullable' : 'required',
            'file',
            File::types(['pdf'])->max(self::MAX_PDF_KILOBYTES),
            function (string $attribute, mixed $value, callable $fail): void {
                if (!$value instanceof UploadedFile || !$value->isValid()) {
                    $fail('The annual report must be a valid PDF file.');
                    return;
                }

                $handle = @fopen($value->getRealPath(), 'rb');
                $signature = $handle ? fread($handle, 5) : false;
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if ($signature !== '%PDF-') {
                    $fail('The annual report must contain a valid PDF signature.');
                }
            },
        ];

        return [
            'annual_report_path' => $fileRules,
            'title' => ['required', 'string', 'max:255', 'unique:annual_reports,title' . ($report ? ',' . $report->id : '')],
            'published_at' => ['required', 'date'],
            'publisher_name' => ['nullable', 'string', 'max:100'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'order_by' => ['nullable', 'integer', 'between:-2147483648,2147483647'],
        ];
    }

    private function storePdf(UploadedFile $file): string
    {
        $name = bin2hex(random_bytes(24)) . '.pdf';
        $stored = Storage::disk('local')->putFileAs('annual-reports', $file, $name);
        if ($stored === false) {
            throw new \RuntimeException('Unable to store annual report.');
        }

        return $name;
    }

    private function deleteIfUnreferenced(string $name): void
    {
        $path = 'annual-reports/' . basename($name);
        if (!$this->mediaReferences->physicalPathInUse('local', $path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
