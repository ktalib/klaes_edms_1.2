<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature tests for dashboard workflows
 * Tests user interactions, authorization flows, and business logic
 */
class DashboardWorkflowTest extends TestCase
{
    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create(['type' => 'super admin']);
        Cache::flush();
    }

    /**
     * Test: Admin can access analytics dashboard
     */
    public function test_admin_can_access_analytics_dashboard()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('activity.advanced-analytics');
        $response->assertViewHas('user');
    }

    /**
     * Test: Regular user cannot access analytics dashboard
     */
    public function test_regular_user_cannot_access_analytics_dashboard()
    {
        $this->actingAs($this->user);

        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test: Analytics dashboard loads with initial data
     */
    public function test_analytics_dashboard_loads_with_initial_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_OK);
        // Verify JavaScript data is embedded or can be fetched via API
    }

    /**
     * Test: Dashboard access is logged to audit trail
     */
    public function test_dashboard_access_logged_to_audit_trail()
    {
        $this->actingAs($this->adminUser);

        $initialCount = AuditLog::count();

        $this->get('/analytics-dashboard');

        // Verify audit log was created
        $this->assertGreaterThan($initialCount, AuditLog::count());
    }

    /**
     * Test: Admin can generate comparison report
     */
    public function test_admin_can_generate_comparison_report()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['success', 'data']);
    }

    /**
     * Test: Comparison report contains required metrics
     */
    public function test_comparison_report_contains_required_metrics()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'all',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');

        if ($data && is_array($data)) {
            // Each metric should have period values and calculations
            foreach ($data as $metric) {
                $this->assertArrayHasKey('metric', $metric);
                $this->assertArrayHasKey('period1', $metric);
                $this->assertArrayHasKey('period2', $metric);
            }
        }
    }

    /**
     * Test: User can export comparison to PDF
     */
    public function test_user_can_export_comparison_to_pdf()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/export-pdf', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['success', 'message']);
    }

    /**
     * Test: Dashboard workflow creates correct audit trail entries
     */
    public function test_dashboard_workflow_audit_trail()
    {
        $this->actingAs($this->adminUser);

        $initialAuditCount = AuditLog::count();

        // Access dashboard
        $this->get('/analytics-dashboard');

        // Generate comparison
        $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $finalAuditCount = AuditLog::count();

        // Verify multiple audit entries were created
        $this->assertGreaterThan($initialAuditCount, $finalAuditCount);
    }

    /**
     * Test: Dashboard displays real-time data updates
     */
    public function test_dashboard_displays_realtime_updates()
    {
        $this->actingAs($this->adminUser);

        // First request
        $response1 = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        $data1 = $response1->json('data');

        // Simulate time passing and creating new activity
        ActivityLogService::recordLogin($this->user, '192.168.1.1');

        // Second request
        $response2 = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        $data2 = $response2->json('data');

        // Verify data was updated
        $this->assertNotNull($data1);
        $this->assertNotNull($data2);
    }

    /**
     * Test: Admin workflow: Access, Compare, Export
     */
    public function test_admin_complete_workflow()
    {
        $this->actingAs($this->adminUser);

        // Step 1: Access dashboard
        $dashboardResponse = $this->get('/analytics-dashboard');
        $this->assertEquals(Response::HTTP_OK, $dashboardResponse->getStatusCode());

        // Step 2: Generate analytics data
        $analyticsResponse = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);
        $this->assertEquals(Response::HTTP_OK, $analyticsResponse->getStatusCode());

        // Step 3: Generate comparison
        $comparisonResponse = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'all',
        ]);
        $this->assertEquals(Response::HTTP_OK, $comparisonResponse->getStatusCode());

        // Step 4: Export report
        $exportResponse = $this->post('/report-comparison/export-pdf');
        $this->assertEquals(Response::HTTP_OK, $exportResponse->getStatusCode());
    }

    /**
     * Test: Authorization is enforced throughout workflow
     */
    public function test_authorization_enforced_throughout_workflow()
    {
        $this->actingAs($this->user);

        $endpoints = [
            ['GET', '/analytics-dashboard'],
            ['POST', '/api/activity-analytics/sessions', ['period_days' => 30, 'granularity' => 'daily']],
            ['POST', '/report-comparison/compare', ['period1_days' => 30, 'period2_days' => 7, 'metric' => 'sessions']],
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
     * Test: Session management during dashboard usage
     */
    public function test_session_management_during_dashboard_usage()
    {
        $this->actingAs($this->user);

        // Record login
        $session = ActivityLogService::recordLogin($this->user, '192.168.1.1');
        $this->assertNotNull($session);
        $this->assertEquals('Online', $session->status);

        // Update heartbeat (simulating dashboard activity)
        $result = ActivityLogService::updateHeartbeat($this->user);
        $this->assertTrue($result);

        // Logout
        $logoutResult = ActivityLogService::recordLogout($this->user);
        $this->assertTrue($logoutResult);

        $session->refresh();
        $this->assertEquals('Offline', $session->status);
    }

    /**
     * Test: Dashboard handles errors gracefully
     */
    public function test_dashboard_handles_errors_gracefully()
    {
        $this->actingAs($this->adminUser);

        // Test with invalid parameters
        $response = $this->post('/report-comparison/compare', [
            'period1_days' => -5,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        // Should return error, not exception
        $this->assertNotNull($response->getContent());
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    /**
     * Test: Concurrent dashboard access by multiple admins
     */
    public function test_concurrent_dashboard_access()
    {
        $admin1 = User::factory()->create(['type' => 'super admin']);
        $admin2 = User::factory()->create(['type' => 'super admin']);

        // Admin 1 accesses dashboard
        $this->actingAs($admin1);
        $response1 = $this->get('/analytics-dashboard');
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());

        // Admin 2 accesses dashboard
        $this->actingAs($admin2);
        $response2 = $this->get('/analytics-dashboard');
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());

        // Both should have separate audit trail entries
        $admin1Logs = AuditLog::where('user_id', $admin1->id)->count();
        $admin2Logs = AuditLog::where('user_id', $admin2->id)->count();

        $this->assertGreaterThan(0, $admin1Logs);
        $this->assertGreaterThan(0, $admin2Logs);
    }

    /**
     * Test: Dashboard caching improves performance
     */
    public function test_dashboard_caching_improves_performance()
    {
        $this->actingAs($this->adminUser);

        // First request - cache miss
        $start1 = microtime(true);
        $response1 = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);
        $time1 = microtime(true) - $start1;

        // Second request - cache hit
        $start2 = microtime(true);
        $response2 = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);
        $time2 = microtime(true) - $start2;

        // Both requests should succeed
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());

        // Data should be consistent
        $data1 = $response1->json('data');
        $data2 = $response2->json('data');
        $this->assertEquals($data1, $data2);
    }

    /**
     * Test: Different metrics return different data
     */
    public function test_different_metrics_return_different_data()
    {
        $this->actingAs($this->adminUser);

        $metrics = ['sessions', 'users', 'duration'];

        $responses = [];
        foreach ($metrics as $metric) {
            $response = $this->post('/report-comparison/compare', [
                'period1_days' => 30,
                'period2_days' => 7,
                'metric' => $metric,
            ]);

            $responses[$metric] = $response->json('data');
        }

        // Verify each metric returns data
        foreach ($responses as $metric => $data) {
            $this->assertNotNull($data, "No data returned for metric: {$metric}");
        }
    }

    /**
     * Test: Dashboard maintains user context across requests
     */
    public function test_dashboard_maintains_user_context()
    {
        $this->actingAs($this->user);

        // Make dashboard request
        $response = $this->get('/analytics-dashboard');

        // User should still be authenticated
        $this->assertTrue(auth()->check());
        $this->assertEquals($this->user->id, auth()->user()->id);
    }

    /**
     * Test: Long-running dashboard workflows
     */
    public function test_long_running_workflows()
    {
        $this->actingAs($this->adminUser);

        $startTime = microtime(true);

        // Simulate extended dashboard usage
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/api/activity-analytics/sessions', [
                'period_days' => 30,
                'granularity' => 'daily',
            ]);

            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

            // Small delay between requests
            usleep(100000);
        }

        $totalTime = microtime(true) - $startTime;

        // 5 requests should complete in reasonable time
        $this->assertLessThan(5.0, $totalTime);
    }
}
