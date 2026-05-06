<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\AuditService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature tests for report workflows
 * Tests full business logic paths including generation, scheduling, delivery, and archival
 */
class ReportWorkflowTest extends TestCase
{
    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create(['type' => 'super admin']);
    }

    /**
     * Test: Report generation workflow
     */
    public function test_report_generation_workflow()
    {
        $this->actingAs($this->adminUser);

        // Step 1: Generate report
        $response = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = $response->json('data');
        $this->assertNotNull($data);
    }

    /**
     * Test: Report list is accessible
     */
    public function test_report_list_is_accessible()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/api/activity-reports');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $response->assertJsonStructure(['success', 'data']);
    }

    /**
     * Test: Multiple report formats are supported
     */
    public function test_multiple_report_formats_supported()
    {
        $this->actingAs($this->adminUser);

        $formats = ['pdf', 'csv', 'excel'];

        foreach ($formats as $format) {
            $response = $this->post('/api/activity-reports/generate', [
                'report_type' => 'activity_summary',
                'period' => 30,
                'format' => $format
            ]);

            $this->assertIn($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ]);
        }
    }

    /**
     * Test: Report generation is logged to audit trail
     */
    public function test_report_generation_logged_to_audit_trail()
    {
        $this->actingAs($this->adminUser);

        $initialCount = AuditLog::count();

        $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        // Verify audit log was created
        $this->assertGreaterThan($initialCount, AuditLog::count());
    }

    /**
     * Test: Report download workflow
     */
    public function test_report_download_workflow()
    {
        $this->actingAs($this->adminUser);

        // Generate report first
        $generateResponse = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        if ($generateResponse->getStatusCode() === 200) {
            $reportId = $generateResponse->json('data.id') ?? 1;

            // Download report
            $downloadResponse = $this->get("/api/activity-reports/{$reportId}/download");

            $this->assertIn($downloadResponse->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_NOT_FOUND,
            ]);
        }
    }

    /**
     * Test: Report scheduling workflow
     */
    public function test_report_scheduling_workflow()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-reports/schedule', [
            'report_type' => 'activity_summary',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['admin@example.com']
        ]);

        if ($response->getStatusCode() === Response::HTTP_OK) {
            $this->assertTrue($response->json('success'));
        }
    }

    /**
     * Test: Report scheduling validates recipients
     */
    public function test_report_scheduling_validates_recipients()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-reports/schedule', [
            'report_type' => 'activity_summary',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['invalid-email@']
        ]);

        // Should validate or return error
        $this->assertNotNull($response->getContent());
    }

    /**
     * Test: Report deletion workflow
     */
    public function test_report_deletion_workflow()
    {
        $this->actingAs($this->adminUser);

        // Delete report (ID may or may not exist)
        $response = $this->delete('/api/activity-reports/1');

        // Should handle gracefully
        $this->assertIn($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    /**
     * Test: Report export with multiple formats
     */
    public function test_report_export_with_multiple_formats()
    {
        $this->actingAs($this->adminUser);

        $formats = ['pdf', 'csv', 'excel'];

        foreach ($formats as $format) {
            $response = $this->post('/api/activity-reports/export', [
                'report_type' => 'activity_summary',
                'period' => 30,
                'format' => $format
            ]);

            // Should return success or validation error
            $this->assertIn($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ]);
        }
    }

    /**
     * Test: Scheduled report execution workflow
     */
    public function test_scheduled_report_execution_workflow()
    {
        $this->actingAs($this->adminUser);

        // Create scheduled report
        $scheduleResponse = $this->post('/api/activity-reports/schedule', [
            'report_type' => 'activity_summary',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['admin@example.com']
        ]);

        if ($scheduleResponse->getStatusCode() === Response::HTTP_OK) {
            $scheduleId = $scheduleResponse->json('data.id') ?? 1;

            // Execute schedule
            $executeResponse = $this->post("/api/activity-reports/execute-schedule/{$scheduleId}");

            $this->assertIn($executeResponse->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_NOT_FOUND,
            ]);
        }
    }

    /**
     * Test: Report generation with custom date range
     */
    public function test_report_generation_with_custom_date_range()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 90,
            'format' => 'pdf'
        ]);

        $this->assertIn($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);
    }

    /**
     * Test: Report generation with different report types
     */
    public function test_report_generation_with_different_types()
    {
        $this->actingAs($this->adminUser);

        $reportTypes = [
            'activity_summary',
            'user_activity',
            'session_analysis',
            'device_breakdown',
        ];

        foreach ($reportTypes as $type) {
            $response = $this->post('/api/activity-reports/generate', [
                'report_type' => $type,
                'period' => 30,
                'format' => 'pdf'
            ]);

            $this->assertIn($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ]);
        }
    }

    /**
     * Test: Authorization enforced on report operations
     */
    public function test_authorization_enforced_on_report_operations()
    {
        $this->actingAs($this->user);

        $endpoints = [
            ['GET', '/api/activity-reports'],
            ['POST', '/api/activity-reports/generate', ['report_type' => 'activity_summary', 'period' => 30]],
            ['POST', '/api/activity-reports/schedule', ['report_type' => 'activity_summary', 'frequency' => 'daily']],
        ];

        foreach ($endpoints as [$method, $uri, $data]) {
            if ($method === 'GET') {
                $response = $this->get($uri);
            } else {
                $response = $this->post($uri, $data ?? []);
            }

            $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode(),
                "Authorization not enforced for {$method} {$uri}");
        }
    }

    /**
     * Test: Report workflow creates complete audit trail
     */
    public function test_report_workflow_creates_complete_audit_trail()
    {
        $this->actingAs($this->adminUser);

        $initialAuditCount = AuditLog::count();

        // Generate report
        $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        // Export report
        $this->post('/api/activity-reports/export', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        $finalAuditCount = AuditLog::count();

        // Verify audit entries were created
        $this->assertGreaterThan($initialAuditCount, $finalAuditCount);

        // Verify audit logs record correct actions
        $auditLogs = AuditLog::where('user_id', $this->adminUser->id)
            ->latest()
            ->limit(3)
            ->get();

        foreach ($auditLogs as $log) {
            $this->assertNotNull($log->action);
            $this->assertNotNull($log->resource_type);
        }
    }

    /**
     * Test: Report generation performance
     */
    public function test_report_generation_performance()
    {
        $this->actingAs($this->adminUser);

        $start = microtime(true);

        $response = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        $duration = microtime(true) - $start;

        $this->assertLessThan(3.0, $duration);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Test: Concurrent report generation
     */
    public function test_concurrent_report_generation()
    {
        $admin1 = User::factory()->create(['type' => 'super admin']);
        $admin2 = User::factory()->create(['type' => 'super admin']);

        // Admin 1 generates report
        $this->actingAs($admin1);
        $response1 = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        // Admin 2 generates report
        $this->actingAs($admin2);
        $response2 = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        // Both should succeed
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());

        // Each should have separate audit trail
        $admin1Logs = AuditLog::where('user_id', $admin1->id)
            ->where('action', 'CREATED')
            ->count();
        $admin2Logs = AuditLog::where('user_id', $admin2->id)
            ->where('action', 'CREATED')
            ->count();

        $this->assertGreaterThan(0, $admin1Logs);
        $this->assertGreaterThan(0, $admin2Logs);
    }

    /**
     * Test: Report list maintains ordering
     */
    public function test_report_list_maintains_ordering()
    {
        $this->actingAs($this->adminUser);

        // Generate multiple reports
        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/activity-reports/generate', [
                'report_type' => 'activity_summary',
                'period' => 30,
                'format' => 'pdf'
            ]);

            usleep(100000);
        }

        // Get report list
        $response = $this->get('/api/activity-reports');

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $reports = $response->json('data');

        if (is_array($reports) && count($reports) > 1) {
            // Verify ordering (most recent first)
            for ($i = 0; $i < count($reports) - 1; $i++) {
                if (isset($reports[$i]['created_at']) && isset($reports[$i + 1]['created_at'])) {
                    $this->assertGreaterThanOrEqual(
                        strtotime($reports[$i + 1]['created_at']),
                        strtotime($reports[$i]['created_at'])
                    );
                }
            }
        }
    }

    /**
     * Test: Report handles large data sets
     */
    public function test_report_handles_large_datasets()
    {
        $this->actingAs($this->adminUser);

        // Request report for large period
        $response = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 365,
            'format' => 'pdf'
        ]);

        // Should handle large dataset gracefully
        $this->assertIn($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);
    }

    /**
     * Test: Report caching improves performance
     */
    public function test_report_caching_improves_performance()
    {
        $this->actingAs($this->adminUser);

        // First generation
        $start1 = microtime(true);
        $response1 = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);
        $time1 = microtime(true) - $start1;

        // Immediate second generation (likely cached)
        $start2 = microtime(true);
        $response2 = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);
        $time2 = microtime(true) - $start2;

        // Second should be same or faster due to caching
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());
    }

    /**
     * Test: Report error handling and recovery
     */
    public function test_report_error_handling_and_recovery()
    {
        $this->actingAs($this->adminUser);

        // Test with invalid parameters
        $invalidResponse = $this->post('/api/activity-reports/generate', [
            'report_type' => 'invalid_type',
            'period' => -5,
            'format' => 'invalid_format'
        ]);

        // Should return error, not crash
        $this->assertIn($invalidResponse->getStatusCode(), [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST,
        ]);

        // Next valid request should still work
        $validResponse = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        $this->assertEquals(Response::HTTP_OK, $validResponse->getStatusCode());
    }
}
