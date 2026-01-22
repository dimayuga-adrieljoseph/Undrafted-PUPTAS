<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
Use Inertia\Inertia;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
        ]);

        $schedule = Schedule::create([
            'name' => $validated['name'],
            'start' => $validated['start'],
            'end' => $validated['end'],
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after:start',
        ]);

        $schedule->update($validated);

        return response()->json($schedule);
    }

    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return response()->json(null, 204);
    }
}