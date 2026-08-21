<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

use App\Models\EventCalendar;

use Exception;

class EventCalendarController extends Controller {

    public function index(Request $request) {
        $title = $request->Lang->EventCalendarTitle;
        $search = $request->search;
        $event_calendars = EventCalendar::where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%');
                })
                ->where('event_calendars.language', app()->getLocale())
                ->paginate(15);

        return view('admin.event_calendar.index')->with(compact('title', 'event_calendars', 'search'));
    }

    public function create() {
        return redirect()->route('event_calendar.index')->with('message', 'Create calendar entries from the calendar list.');
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'start_date' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_date' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:start_date'],
        ]);

        try {
            EventCalendar::create([
                        'title' => $validated['title'],
                        'description' => $request->description,
                        'start_date' => $this->databaseDateTime($validated['start_date']),
                        'end_date' => $this->databaseDateTime($validated['end_date']),
                        'language' => app()->getLocale(),
                        'color' => @$request->color,
                        'textColor' => @$request->textColor,
                        'url' => @$request->url,
                        'status' => 0
            ]);

            $notification = array(
                'message' => $request->Lang->Common->Form->AddedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotCreate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function edit($id = null, Request $request) {
        try {
            $eventCalendar = EventCalendar::select('event_calendars.*')->where('id', $id)->first();
            if (empty($eventCalendar)) {
                return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
            }

            $data = $eventCalendar->toArray();
            $data['start_date'] = $this->inputDateTime($eventCalendar->start_date);
            $data['end_date'] = $this->inputDateTime($eventCalendar->end_date);

            $response = [ 'data' => $data];
            return response($response, 200);
        } catch (Exception $e) {
            return response([ 'message' => $request->Lang->Common->Form->DataNotFound], 403);
        }
    }

    public function update(Request $request) {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'id' => ['required', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d\TH:i'],
            'end_date' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:start_date'],
        ]);
        try {
            $eventCalendar = EventCalendar::find($validated['id']);
            if (empty($eventCalendar)) {
                $notification = array(
                    'message' => $request->Lang->Common->Form->NotFound,
                    'alert-type' => 'warning'
                );
                return back()->with($notification);
            }
            $eventCalendar->update([
                'title' => $validated['title'],
                'description' => $request->description,
                'start_date' => $this->databaseDateTime($validated['start_date']),
                'end_date' => $this->databaseDateTime($validated['end_date']),
                'language' => app()->getLocale(),
                'color' => @$request->color,
                'textColor' => @$request->textColor,
                'url' => @$request->url,
            ]);

            $notification = array(
                'message' => $request->Lang->Common->Form->UpdatedSuccessfully,
                'alert-type' => 'success'
            );
            return back()->with($notification);
        } catch (Exception $e) {
            $notification = array(
                'message' => $request->Lang->Common->Form->NotUpdate,
                'alert-type' => 'error'
            );
            return back()->with($notification);
        }
    }

    public function status(Request $request) {
        try {
            if ($request->ajax()) {
                $data = EventCalendar::find($request->id);
                $data->status = $data->status ^ 1;
                $data->update();
                return response(['message' => ($data->status ? $request->Lang->Common->Form->PublishSuccessfully : $request->Lang->Common->Form->UnpublishSuccessfully)], 200);
            }
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotUpdate], 403);
        }
    }

    public function destroy($id = null, Request $request) {
        try {
            $eventCalendar = EventCalendar::find($id);
            $eventCalendar->delete();
            return response(['message' => $request->Lang->Common->Form->DeleteSuccessfully], 200);
        } catch (Exception $e) {
            return response(['message' => $request->Lang->Common->Form->NotDelete], 403);
        }
    }

    private function databaseDateTime(string $value): string {
        return CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value)->format('Y-m-d H:i:s');
    }

    private function inputDateTime(string $value): string {
        return CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
    }

}
