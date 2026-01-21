<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ComplaintController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $query = Complaint::with(['user', 'application', 'assignedTo', 'resolvedBy']);

        // Filter based on role
        if ($user->role_id == 1) {
            // Applicants see only their own complaints
            $query->where('user_id', $user->id);
        }

        $complaints = $query->latest()->get();

        return Inertia::render('Complaints/Index', [
            'complaints' => $complaints,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'nullable|exists:applications,id',
            'type' => 'required|in:technical,process,delay,documentation,other',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $complaint = Complaint::create([
            'user_id' => Auth::id(),
            'application_id' => $validated['application_id'] ?? null,
            'type' => $validated['type'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Complaint submitted successfully',
            'complaint' => $complaint,
        ], 201);
    }

    public function show($id)
    {
        $complaint = Complaint::with(['user', 'application', 'assignedTo', 'resolvedBy'])
            ->findOrFail($id);

        // Authorization check
        if (Auth::user()->role_id == 1 && $complaint->user_id != Auth::id()) {
            abort(403, 'Unauthorized access to this complaint.');
        }

        return response()->json($complaint);
    }

    public function update(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);

        // Only admin and assigned staff can update
        if (Auth::user()->role_id == 1) {
            abort(403, 'Applicants cannot update complaints directly.');
        }

        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,resolved,closed',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'resolution' => 'nullable|string',
        ]);

        if (isset($validated['status']) && $validated['status'] == 'resolved' && !$complaint->resolved_at) {
            $validated['resolved_at'] = now();
            $validated['resolved_by'] = Auth::id();
        }

        $complaint->update($validated);

        return response()->json([
            'message' => 'Complaint updated successfully',
            'complaint' => $complaint->fresh(['user', 'application', 'assignedTo', 'resolvedBy']),
        ]);
    }

    public function assign(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);

        // Only admin can assign
        if (Auth::user()->role_id != 2) {
            abort(403, 'Only administrators can assign complaints.');
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $complaint->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => 'in_progress',
        ]);

        return response()->json([
            'message' => 'Complaint assigned successfully',
            'complaint' => $complaint->fresh(['assignedTo']),
        ]);
    }

    public function resolve(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);

        $validated = $request->validate([
            'resolution' => 'required|string',
        ]);

        $complaint->update([
            'status' => 'resolved',
            'resolution' => $validated['resolution'],
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Complaint resolved successfully',
            'complaint' => $complaint->fresh(['resolvedBy']),
        ]);
    }

    public function destroy($id)
    {
        $complaint = Complaint::findOrFail($id);

        // Only admin can delete
        if (Auth::user()->role_id != 2) {
            abort(403, 'Only administrators can delete complaints.');
        }

        $complaint->delete();

        return response()->json([
            'message' => 'Complaint deleted successfully',
        ]);
    }
}
