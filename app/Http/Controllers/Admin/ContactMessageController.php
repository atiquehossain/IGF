<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UpdatesEnquiryWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

use App\Models\ContactMessage;
use App\Models\LatestNews;
use App\Models\Category;
use App\Services\AdminPrivateSearch;

use Exception;
use File;

class ContactMessageController extends Controller {
    use UpdatesEnquiryWorkflow;

    public function __construct(private AdminPrivateSearch $privateSearch)
    {
    }

    public function index(Request $request) {
        $title = $request->Lang->ContactMessage;

        if ($request->query->has('search')) {
            return redirect()->route('contact-message.index', $request->only('workflow_status'));
        }

        $search = $this->privateSearch->current($request, 'contact-messages');
        $status = trim((string) $request->input('workflow_status', ''));

        $contactMessages = ContactMessage::query()
            ->with('assignedAdmin:id,name,email')
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%' . $search . '%';

                $query->where(function ($fields) use ($pattern) {
                    $fields->where('first_name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhere('phone', 'like', $pattern)
                        ->orWhere('message', 'like', $pattern);
                });
            })
            ->when(array_key_exists($status, $this->workflowStatuses()), fn ($query) => $query->where('workflow_status', $status))
            ->latest('id')
            ->paginate(15);

        return view('admin.contact_message.index', [
            'title' => $title,
            'contactMessages' => $contactMessages,
            'search' => $search,
            'selectedStatus' => $status,
            'workflowStatuses' => $this->workflowStatuses(),
            'assignees' => $this->activeWorkflowAssignees(),
        ]);
    }

    public function show($id = null, Request $request) {
        try {
            $message = ContactMessage::select('contact_messages.*')
                            ->where('id', $id)->first();
            $response = [ 'data' => $message];
            return response($response, 200);
        } catch (Exception $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function updateWorkflow(Request $request, ContactMessage $contactMessage)
    {
        return $this->persistWorkflow($request, $contactMessage);
    }

}
