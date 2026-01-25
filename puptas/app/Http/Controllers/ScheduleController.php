<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Rules\ValidationRules;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = Schedule::orderBy('start')->get();

        if ($request->wantsJson()) {
            // Return JSON data for AJAX/Axios requests
            return response()->json($schedules);
        }

        // Return Inertia page for normal browser requests
        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(ValidationRules::scheduleStore());

        $schedule = Schedule::create([
            'name' => $validated['name'],
            'start' => $validated['start'],
            'end' => $validated['end'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'created_by' => auth()->id(),
            'affected_programs' => $validated['affected_programs'] ?? null,
        ]);

        return response()->json($schedule, 201);
    }

    public function show($id)
    {
        $schedule = Schedule::findOrFail($id);
        return response()->json($schedule);
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validated = $request->validate(ValidationRules::scheduleUpdate($id));

        $schedule->update([
            'name' => $validated['name'],
            'start' => $validated['start'],
            'end' => $validated['end'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'affected_programs' => $validated['affected_programs'] ?? null,
        ]);

        return response()->json($schedule);
    }

    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json(null, 204);
    }
}
