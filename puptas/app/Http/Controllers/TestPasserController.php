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
use App\Rules\ValidationRules;
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
        $validatedData = $request->validate(ValidationRules::testPasserUpdate($id));

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
        $validated = $request->validate(ValidationRules::testPasserStore());

        // Set default status if not provided
        $validated['status'] = $validated['status'] ?? 'pending';

        // Create new passer record
        $passer = TestPasser::create($validated);

        // Return the new passer data (adjust as needed)
        return response()->json($passer, 201);
    }
}
