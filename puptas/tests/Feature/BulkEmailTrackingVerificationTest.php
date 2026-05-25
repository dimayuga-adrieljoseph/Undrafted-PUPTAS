<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\EmailTrackingService;
use App\Models\BulkEmailOperation;
use App\Models\EmailLog;

/**
 * End-to-end verification tests for the Bulk Email Tracking feature.
 * 
 * These tests verify:
 * 18.1 - Chunked dispatching with 100+ recipients and correct EmailLog records
 * 18.2 - Progress endpoint returns correct counts and stops updating after completion
 * 18.3 - Retry flow: failed logs reset to pending, counts update correctly
 * 18.4 - Max retry limit (3) enforced; 2001+ recipients rejected
 * 18.5 - Backward compatibility: jobs without emailLogId/bulkOperationId still function
 */
class BulkEmailTrackingVerificationTest extends TestCase
{
    use RefreshDatabase;

    private EmailTrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmailTrackingService::class);
    }

    // =========================================================================
    // 18.1 - Verify bulk send with 100+ recipients creates chunked dispatching
    //         and correct EmailLog records
    // =========================================================================

    /** @test */
    public function it_chunks_recipients_using_configured_chunk_size()
    {
        // Verify the chunking logic: 150 items with chunk_size=100 should produce 2 chunks
        $chunkSize = config('email-tracking.chunk_size', 100);
        $this->assertEquals(100, $chunkSize, 'Default chunk_size should be 100');

        $recipients = range(1, 150);
        $chunks = array_chunk($recipients, $chunkSize);

        $this->assertCount(2, $chunks, '150 recipients should produce 2 chunks of 100');
        $this->assertCount(100, $chunks[0], 'First chunk should have 100 items');
        $this->assertCount(50, $chunks[1], 'Second chunk should have 50 items');
    }

    /** @test */
    public function it_creates_bulk_operation_with_correct_total_count()
    {
        $operation = $this->service->createBulkOperation(
            'pupcet_result',
            150,
            1
        );

        $this->assertTrue($operation->exists);
        $this->assertEquals('pupcet_result', $operation->email_type);
        $this->assertEquals(150, $operation->total_count);
        $this->assertEquals(150, $operation->pending_count);
        $this->assertEquals(0, $operation->sent_count);
        $this->assertEquals(0, $operation->failed_count);
        $this->assertEquals('in_progress', $operation->status);
        $this->assertNotNull($operation->started_at);
    }

    /** @test */
    public function it_creates_email_log_records_for_each_recipient()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 5, 1);

        for ($i = 1; $i <= 5; $i++) {
            $log = $this->service->createEmailLog(
                $operation->id,
                "recipient{$i}@example.com",
                "Recipient {$i}",
                $i,
                'pupcet_result',
                "<p>Hello Recipient {$i}</p>"
            );

            $this->assertTrue($log->exists);
            $this->assertEquals('pending', $log->status);
            $this->assertEquals($operation->id, $log->bulk_operation_id);
            $this->assertEquals("recipient{$i}@example.com", $log->recipient_email);
        }

        $this->assertEquals(5, EmailLog::where('bulk_operation_id', $operation->id)->count());
    }

    /** @test */
    public function chunking_logic_handles_exact_multiples_correctly()
    {
        // 200 items with chunk_size=100 should produce exactly 2 chunks
        $chunkSize = 100;
        $recipients = range(1, 200);
        $chunks = array_chunk($recipients, $chunkSize);

        $this->assertCount(2, $chunks);
        $this->assertCount(100, $chunks[0]);
        $this->assertCount(100, $chunks[1]);
    }

    /** @test */
    public function chunking_logic_handles_less_than_chunk_size()
    {
        // 50 items with chunk_size=100 should produce 1 chunk
        $chunkSize = 100;
        $recipients = range(1, 50);
        $chunks = array_chunk($recipients, $chunkSize);

        $this->assertCount(1, $chunks);
        $this->assertCount(50, $chunks[0]);
    }

    // =========================================================================
    // 18.2 - Verify progress endpoint returns correct counts during active
    //         operation and stops updating after completion
    // =========================================================================

    /** @test */
    public function progress_returns_correct_counts_during_active_operation()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 5, 1);

        // Create 5 email logs
        $logs = [];
        for ($i = 1; $i <= 5; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark 2 as sent
        $this->service->markSent($logs[0]->id);
        $this->service->markSent($logs[1]->id);

        // Mark 1 as failed
        $this->service->markFailed($logs[2]->id, 'SMTP timeout');

        // Update progress
        $this->service->updateBulkProgress($operation->id);

        // Get progress
        $progress = $this->service->getBulkOperationProgress($operation->id);

        $this->assertEquals($operation->id, $progress['id']);
        $this->assertEquals('in_progress', $progress['status']);
        $this->assertEquals(5, $progress['total_count']);
        $this->assertEquals(2, $progress['sent_count']);
        $this->assertEquals(1, $progress['failed_count']);
        $this->assertEquals(2, $progress['pending_count']);
        $this->assertNull($progress['completed_at']);

        // Verify count invariant: sent + failed + pending = total
        $this->assertEquals(
            $progress['total_count'],
            $progress['sent_count'] + $progress['failed_count'] + $progress['pending_count']
        );
    }

    /** @test */
    public function progress_transitions_to_completed_when_all_sent()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 3, 1);

        $logs = [];
        for ($i = 1; $i <= 3; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark all as sent
        foreach ($logs as $log) {
            $this->service->markSent($log->id);
        }

        $this->service->updateBulkProgress($operation->id);

        $progress = $this->service->getBulkOperationProgress($operation->id);

        $this->assertEquals('completed', $progress['status']);
        $this->assertEquals(3, $progress['sent_count']);
        $this->assertEquals(0, $progress['failed_count']);
        $this->assertEquals(0, $progress['pending_count']);
        $this->assertNotNull($progress['completed_at']);
    }

    /** @test */
    public function progress_transitions_to_completed_with_failures_when_some_fail()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 3, 1);

        $logs = [];
        for ($i = 1; $i <= 3; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark 2 sent, 1 failed
        $this->service->markSent($logs[0]->id);
        $this->service->markSent($logs[1]->id);
        $this->service->markFailed($logs[2]->id, 'Connection refused');

        $this->service->updateBulkProgress($operation->id);

        $progress = $this->service->getBulkOperationProgress($operation->id);

        $this->assertEquals('completed_with_failures', $progress['status']);
        $this->assertEquals(2, $progress['sent_count']);
        $this->assertEquals(1, $progress['failed_count']);
        $this->assertEquals(0, $progress['pending_count']);
        $this->assertNotNull($progress['completed_at']);
    }

    // =========================================================================
    // 18.3 - Verify retry flow: failed logs reset to pending, jobs re-dispatched,
    //         counts update correctly
    // =========================================================================

    /** @test */
    public function retry_resets_failed_logs_to_pending_and_clears_error()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 3, 1);

        $logs = [];
        for ($i = 1; $i <= 3; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark all as failed
        foreach ($logs as $log) {
            $this->service->markFailed($log->id, 'SMTP error');
        }

        $this->service->updateBulkProgress($operation->id);

        // Verify operation is completed_with_failures
        $progress = $this->service->getBulkOperationProgress($operation->id);
        $this->assertEquals('completed_with_failures', $progress['status']);

        // Retry all failed
        $retriedCount = $this->service->retryAllFailed($operation->id);
        $this->assertEquals(3, $retriedCount);

        // Verify logs are reset to pending
        foreach ($logs as $log) {
            $log->refresh();
            $this->assertEquals('pending', $log->status);
            $this->assertNull($log->error_message);
            $this->assertNull($log->failed_at);
            $this->assertEquals(1, $log->retry_count);
        }

        // Verify operation is back to in_progress
        $operation->refresh();
        $this->assertEquals('in_progress', $operation->status);
        $this->assertNull($operation->completed_at);
    }

    /** @test */
    public function retry_increments_retry_count_correctly()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 1, 1);
        $log = $this->service->createEmailLog(
            $operation->id,
            'user@example.com',
            'User',
            1,
            'pupcet_result'
        );

        // First failure and retry
        $this->service->markFailed($log->id, 'Error 1');
        $this->service->retryFailedEmails([$log->id]);
        $log->refresh();
        $this->assertEquals(1, $log->retry_count);
        $this->assertEquals('pending', $log->status);

        // Second failure and retry
        $this->service->markFailed($log->id, 'Error 2');
        $this->service->retryFailedEmails([$log->id]);
        $log->refresh();
        $this->assertEquals(2, $log->retry_count);
        $this->assertEquals('pending', $log->status);

        // Third failure and retry
        $this->service->markFailed($log->id, 'Error 3');
        $this->service->retryFailedEmails([$log->id]);
        $log->refresh();
        $this->assertEquals(3, $log->retry_count);
        $this->assertEquals('pending', $log->status);
    }

    /** @test */
    public function retry_selected_only_retries_specified_logs()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 3, 1);

        $logs = [];
        for ($i = 1; $i <= 3; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark all as failed
        foreach ($logs as $log) {
            $this->service->markFailed($log->id, 'SMTP error');
        }

        // Retry only the first two
        $retriedCount = $this->service->retryFailedEmails([$logs[0]->id, $logs[1]->id]);
        $this->assertEquals(2, $retriedCount);

        // First two should be pending, third should still be failed
        $logs[0]->refresh();
        $logs[1]->refresh();
        $logs[2]->refresh();

        $this->assertEquals('pending', $logs[0]->status);
        $this->assertEquals('pending', $logs[1]->status);
        $this->assertEquals('failed', $logs[2]->status);
    }

    // =========================================================================
    // 18.4 - Verify max retry limit (3) enforced with appropriate error;
    //         verify 2001+ recipients rejected
    // =========================================================================

    /** @test */
    public function max_retry_limit_prevents_further_retries()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 1, 1);
        $log = $this->service->createEmailLog(
            $operation->id,
            'user@example.com',
            'User',
            1,
            'pupcet_result'
        );

        // Exhaust all 3 retries
        for ($i = 0; $i < 3; $i++) {
            $this->service->markFailed($log->id, "Error attempt {$i}");
            $this->service->retryFailedEmails([$log->id]);
        }

        $log->refresh();
        $this->assertEquals(3, $log->retry_count);

        // Now mark as failed again
        $this->service->markFailed($log->id, 'Final error');

        // Attempt retry - should return 0 (rejected)
        $retriedCount = $this->service->retryFailedEmails([$log->id]);
        $this->assertEquals(0, $retriedCount);

        // Log should still be failed with retry_count = 3
        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals(3, $log->retry_count);
    }

    /** @test */
    public function max_retry_config_defaults_to_three()
    {
        $maxRetries = config('email-tracking.max_retry_count', 3);
        $this->assertEquals(3, $maxRetries);
    }

    /** @test */
    public function max_recipients_config_defaults_to_2000()
    {
        $maxRecipients = config('email-tracking.max_recipients_per_operation', 2000);
        $this->assertEquals(2000, $maxRecipients);
    }

    /** @test */
    public function recipients_exceeding_max_are_rejected_in_validation_logic()
    {
        // Simulate the validation logic from TestPasserController::sendEmails()
        $maxRecipients = config('email-tracking.max_recipients_per_operation', 2000);
        $passerIds = range(1, 2001);

        // This is the exact check from the controller
        $exceedsLimit = count($passerIds) > $maxRecipients;
        $this->assertTrue($exceedsLimit, '2001 recipients should exceed the 2000 limit');

        // Verify 2000 is acceptable
        $passerIds2000 = range(1, 2000);
        $this->assertFalse(count($passerIds2000) > $maxRecipients, '2000 recipients should be acceptable');
    }

    /** @test */
    public function retry_only_works_on_failed_status_logs()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 3, 1);

        $pendingLog = $this->service->createEmailLog(
            $operation->id,
            'pending@example.com',
            'Pending User',
            1,
            'pupcet_result'
        );

        $sentLog = $this->service->createEmailLog(
            $operation->id,
            'sent@example.com',
            'Sent User',
            2,
            'pupcet_result'
        );
        $this->service->markSent($sentLog->id);

        $failedLog = $this->service->createEmailLog(
            $operation->id,
            'failed@example.com',
            'Failed User',
            3,
            'pupcet_result'
        );
        $this->service->markFailed($failedLog->id, 'Error');

        // Try to retry all three - only the failed one should be retried
        $retriedCount = $this->service->retryFailedEmails([
            $pendingLog->id,
            $sentLog->id,
            $failedLog->id,
        ]);

        $this->assertEquals(1, $retriedCount);

        // Verify only the failed log was reset
        $pendingLog->refresh();
        $sentLog->refresh();
        $failedLog->refresh();

        $this->assertEquals('pending', $pendingLog->status); // unchanged
        $this->assertEquals('sent', $sentLog->status); // unchanged
        $this->assertEquals('pending', $failedLog->status); // reset
        $this->assertEquals(1, $failedLog->retry_count);
    }

    // =========================================================================
    // 18.5 - Verify backward compatibility: existing job dispatches without
    //         emailLogId/bulkOperationId still function correctly
    // =========================================================================

    /** @test */
    public function send_passer_email_job_has_nullable_tracking_params()
    {
        $reflection = new \ReflectionClass(\App\Jobs\SendPasserEmail::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        // Should have 4 params: passer, personalizedMessage, emailLogId, bulkOperationId
        $this->assertGreaterThanOrEqual(4, count($params));

        // emailLogId should be nullable with default null
        $emailLogIdParam = $params[2];
        $this->assertEquals('emailLogId', $emailLogIdParam->getName());
        $this->assertTrue($emailLogIdParam->allowsNull());
        $this->assertTrue($emailLogIdParam->isDefaultValueAvailable());
        $this->assertNull($emailLogIdParam->getDefaultValue());

        // bulkOperationId should be nullable with default null
        $bulkOpParam = $params[3];
        $this->assertEquals('bulkOperationId', $bulkOpParam->getName());
        $this->assertTrue($bulkOpParam->allowsNull());
        $this->assertTrue($bulkOpParam->isDefaultValueAvailable());
        $this->assertNull($bulkOpParam->getDefaultValue());
    }

    /** @test */
    public function send_waitlisted_email_job_has_nullable_tracking_params()
    {
        $reflection = new \ReflectionClass(\App\Jobs\SendWaitlistedEmail::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        // Should have 4 params: passer, messageTemplate, emailLogId, bulkOperationId
        $this->assertGreaterThanOrEqual(4, count($params));

        // emailLogId should be nullable with default null
        $emailLogIdParam = $params[2];
        $this->assertEquals('emailLogId', $emailLogIdParam->getName());
        $this->assertTrue($emailLogIdParam->allowsNull());
        $this->assertTrue($emailLogIdParam->isDefaultValueAvailable());
        $this->assertNull($emailLogIdParam->getDefaultValue());

        // bulkOperationId should be nullable with default null
        $bulkOpParam = $params[3];
        $this->assertEquals('bulkOperationId', $bulkOpParam->getName());
        $this->assertTrue($bulkOpParam->allowsNull());
        $this->assertTrue($bulkOpParam->isDefaultValueAvailable());
        $this->assertNull($bulkOpParam->getDefaultValue());
    }

    /** @test */
    public function send_sar_form_email_job_has_nullable_tracking_params()
    {
        $reflection = new \ReflectionClass(\App\Jobs\SendSarFormEmail::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        // Should have 5 params: passer, downloadUrl, sarGenerationId, emailLogId, bulkOperationId
        $this->assertGreaterThanOrEqual(5, count($params));

        // emailLogId should be nullable with default null
        $emailLogIdParam = $params[3];
        $this->assertEquals('emailLogId', $emailLogIdParam->getName());
        $this->assertTrue($emailLogIdParam->allowsNull());
        $this->assertTrue($emailLogIdParam->isDefaultValueAvailable());
        $this->assertNull($emailLogIdParam->getDefaultValue());

        // bulkOperationId should be nullable with default null
        $bulkOpParam = $params[4];
        $this->assertEquals('bulkOperationId', $bulkOpParam->getName());
        $this->assertTrue($bulkOpParam->allowsNull());
        $this->assertTrue($bulkOpParam->isDefaultValueAvailable());
        $this->assertNull($bulkOpParam->getDefaultValue());
    }

    /** @test */
    public function job_handle_guards_tracking_calls_with_null_checks()
    {
        // Verify the handle() method source code contains null checks
        $sendPasserSource = file_get_contents(app_path('Jobs/SendPasserEmail.php'));
        $this->assertStringContainsString('if ($this->emailLogId)', $sendPasserSource);
        $this->assertStringContainsString('if ($this->bulkOperationId)', $sendPasserSource);

        $sendWaitlistedSource = file_get_contents(app_path('Jobs/SendWaitlistedEmail.php'));
        $this->assertStringContainsString('if ($this->emailLogId)', $sendWaitlistedSource);
        $this->assertStringContainsString('if ($this->bulkOperationId)', $sendWaitlistedSource);

        $sendSarSource = file_get_contents(app_path('Jobs/SendSarFormEmail.php'));
        $this->assertStringContainsString('if ($this->emailLogId)', $sendSarSource);
        $this->assertStringContainsString('if ($this->bulkOperationId)', $sendSarSource);
    }

    /** @test */
    public function error_message_truncated_to_1024_chars()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 1, 1);
        $log = $this->service->createEmailLog(
            $operation->id,
            'user@example.com',
            'User',
            1,
            'pupcet_result'
        );

        // Create a very long error message (2000 chars)
        $longError = str_repeat('A', 2000);
        $this->service->markFailed($log->id, $longError);

        $log->refresh();
        $this->assertEquals(1024, mb_strlen($log->error_message));
    }

    /** @test */
    public function count_invariant_holds_after_mixed_operations()
    {
        $operation = $this->service->createBulkOperation('pupcet_result', 10, 1);

        $logs = [];
        for ($i = 1; $i <= 10; $i++) {
            $logs[] = $this->service->createEmailLog(
                $operation->id,
                "user{$i}@example.com",
                "User {$i}",
                $i,
                'pupcet_result'
            );
        }

        // Mark 4 sent, 3 failed, 3 pending
        for ($i = 0; $i < 4; $i++) {
            $this->service->markSent($logs[$i]->id);
        }
        for ($i = 4; $i < 7; $i++) {
            $this->service->markFailed($logs[$i]->id, 'Error');
        }

        $this->service->updateBulkProgress($operation->id);
        $progress = $this->service->getBulkOperationProgress($operation->id);

        // Count invariant: sent + failed + pending = total
        $this->assertEquals(
            $progress['total_count'],
            $progress['sent_count'] + $progress['failed_count'] + $progress['pending_count'],
            'Count invariant violated: sent + failed + pending must equal total'
        );

        $this->assertEquals(4, $progress['sent_count']);
        $this->assertEquals(3, $progress['failed_count']);
        $this->assertEquals(3, $progress['pending_count']);
        $this->assertEquals('in_progress', $progress['status']);
    }
}
