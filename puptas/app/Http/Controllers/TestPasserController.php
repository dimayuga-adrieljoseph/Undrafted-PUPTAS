<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TestPassersImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\TestPasser;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestPasserEmail;
use Symfony\Component\Mime\Part\TextPart;
use Illuminate\Validation\Rule;

class TestPasserController extends Controller
{
    // Get grouped passers by school year and batch number


    public function index()
    {
        $passers = TestPasser::all()
            ->groupBy(['school_year', 'batch_number'])
            ->map(function ($batches) {
                return $batches->map(function ($passers) {
                    return $passers->values(); // reset keys, convert collection to array-like
                });
            });

        return Inertia::render('TestPassersEmail', [
            'groupedPassers' => $passers,
            'registrationUrl' => url('/register'),
        ]);
    }



    public function sendEmails(Request $request)
    {
        $passerIds = $request->input('passer_ids');
        $messageTemplate = $request->input('message_template');

        // Check if inputs are present
        if (!$passerIds || !$messageTemplate) {
            return response()->json(['error' => 'Missing required inputs'], 422);
        }

        $passers = TestPasser::whereIn('test_passer_id', $passerIds)->get();

        foreach ($passers as $passer) {
            // Replace placeholders in template for personalization
            $personalizedMessage = str_replace(
                ['{{firstname}}', '{{surname}}'],
                [$passer->first_name, $passer->surname],
                $messageTemplate
            );

            // Log or debug $personalizedMessage if needed
            // \Log::info("Sending email to {$passer->email} with message: {$personalizedMessage}");

            Mail::to($passer->email)
                ->send(new TestPasserEmail($passer, $personalizedMessage));
        }

        return response()->json(['message' => 'Emails sent successfully!']);
    }



    // Helper function to replace placeholders
    private function replacePlaceholders($template, $passer)
    {
        return str_replace(
            ['{{firstname}}', '{{surname}}'],
            [$passer->first_name, $passer->surname],
            $template
        );
    }

    public function upload(Request $request)
    {
        $request->validate([
            'batch_number' => 'required|string',
            'school_year' => 'required|string',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $batch = $request->input('batch_number');
        $schoolYear = $request->input('school_year');

        Excel::import(new TestPassersImport($batch, $schoolYear), $request->file('file'));

        return response()->json(['message' => 'Excel file uploaded and data imported successfully']);
    }

    public function update(Request $request, $id)
    {
        // Find the passer or fail
        $passer = TestPasser::findOrFail($id);

        // Validate input
        $validatedData = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'school_address' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . date('Y'),
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('test_passers')->ignore($passer->test_passer_id, 'test_passer_id'),
            ],
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,registered,inactive',
        ]);

        // Update passer with validated data
        $passer->update($validatedData);

        return response()->json([
            'message' => 'Passer updated successfully',
            'passer' => $passer,
        ]);
    }

    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'surname' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'school_address' => 'nullable|string|max:255',
            'shs_school' => 'nullable|string|max:255',
            'strand' => 'nullable|string|max:255',
            'year_graduated' => 'nullable|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'email' => 'required|email|unique:test_passers,email',
            'reference_number' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'school_year' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:pending,registered,inactive',
        ]);

        // Set default status if not provided
        $validated['status'] = $validated['status'] ?? 'pending';

        // Create new passer record
        $passer = TestPasser::create($validated);

        // Return the new passer data (adjust as needed)
        return response()->json($passer, 201);
    }
}
