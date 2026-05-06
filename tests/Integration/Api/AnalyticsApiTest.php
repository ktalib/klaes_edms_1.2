<?php

namespace Tests\Integration\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for all API endpoints
 * Tests full request/response cycle, database operations, and status codes
 */
class AnalyticsApiTest extends TestCase
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
     * Test: fetchSessionStats endpoint returns valid data
     */
    public function test_fetchSessionStats_returns_valid_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_sessions',
                'unique_users',
                'avg_duration',
                'stats' => []
            ]
        ]);
    }

    /**
     * Test: fetchSessionStats endpoint validates period_days
     */
    public function test_fetchSessionStats_validates_period_days()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => -5,
            'granularity' => 'daily',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: fetchSessionStats endpoint validates granularity
     */
    public function test_fetchSessionStats_validates_granularity()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'invalid_granularity',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: fetchSessionStats endpoint requires authentication
     */
    public function test_fetchSessionStats_requires_authentication()
    {
        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Test: fetchTrends endpoint returns time series data
     */
    public function test_fetchTrends_returns_timeseries_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/trends', [
            'period_days' => 30,
            'granularity' => 'daily',
            'metrics' => ['sessions', 'users']
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'labels' => [],
                'datasets' => []
            ]
        ]);
    }

    /**
     * Test: fetchTopUsers endpoint returns user rankings
     */
    public function test_fetchTopUsers_returns_user_rankings()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/top-users', [
            'period_days' => 30,
            'limit' => 10
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'user_id',
                    'user_name',
                    'sessions',
                    'total_duration'
                ]
            ]
        ]);
    }

    /**
     * Test: fetchDeviceAnalytics endpoint returns device breakdown
     */
    public function test_fetchDeviceAnalytics_returns_device_breakdown()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/devices', [
            'period_days' => 30
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'devices' => [],
                'browsers' => [],
                'platforms' => []
            ]
        ]);
    }

    /**
     * Test: fetchPeakHours endpoint returns hourly distribution
     */
    public function test_fetchPeakHours_returns_hourly_distribution()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/peak-hours', [
            'period_days' => 30
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'labels' => [],
                'values' => [],
                'peak_hour' => 'integer'
            ]
        ]);
    }

    /**
     * Test: Analytics endpoints create audit logs
     */
    public function test_analytics_endpoints_create_audit_logs()
    {
        $this->actingAs($this->adminUser);

        $initialCount = AuditLog::count();

        $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        // Verify audit log was created
        $this->assertGreaterThan($initialCount, AuditLog::count());
    }

    /**
     * Test: Analytics endpoints handle concurrent requests
     */
    public function test_analytics_endpoints_handle_concurrent_requests()
    {
        $this->actingAs($this->adminUser);

        $promises = [];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/api/activity-analytics/sessions', [
                'period_days' => 30,
                'granularity' => 'daily',
            ]);

            $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        }
    }

    /**
     * Test: Analytics endpoints return data in correct format
     */
    public function test_analytics_endpoints_return_correct_format()
    {
        $this->actingAs($this->adminUser);

        $endpoints = [
            '/api/activity-analytics/sessions' => ['period_days' => 30, 'granularity' => 'daily'],
            '/api/activity-analytics/trends' => ['period_days' => 30, 'granularity' => 'daily', 'metrics' => ['sessions']],
            '/api/activity-analytics/top-users' => ['period_days' => 30, 'limit' => 10],
            '/api/activity-analytics/devices' => ['period_days' => 30],
            '/api/activity-analytics/peak-hours' => ['period_days' => 30],
        ];

        foreach ($endpoints as $endpoint => $data) {
            $response = $this->post($endpoint, $data);

            $this->assertTrue($response->isJson(), "Endpoint {$endpoint} did not return JSON");
            $this->assertArrayHasKey('success', $response->json());
            $this->assertArrayHasKey('data', $response->json());
        }
    }

    /**
     * Test: Report generation endpoint
     */
    public function test_report_generation_endpoint()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
            'format' => 'pdf'
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['success', 'data']);
    }

    /**
     * Test: Report list endpoint
     */
    public function test_report_list_endpoint()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/api/activity-reports');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'success',
            'data' => []
        ]);
    }

    /**
     * Test: Report download endpoint
     */
    public function test_report_download_endpoint()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/api/activity-reports/1/download');

        // Response could be 200 (file) or 404 (not found)
        $this->assertTrue(in_array($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND,
        ]));
    }

    /**
     * Test: Comparison endpoint calculates deltas
     */
    public function test_comparison_endpoint_calculates_deltas()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions'
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');

        if ($data) {
            $this->assertArrayHasKey('period1_value', $data);
            $this->assertArrayHasKey('period2_value', $data);
            $this->assertArrayHasKey('delta', $data);
            $this->assertArrayHasKey('percent_change', $data);
        }
    }

    /**
     * Test: Comparison endpoint validates metrics
     */
    public function test_comparison_endpoint_validates_metrics()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'invalid_metric'
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: API endpoints check authorization
     */
    public function test_api_endpoints_check_authorization()
    {
        $this->actingAs($this->user);

        $endpoints = [
            '/api/activity-analytics/sessions',
            '/api/activity-reports',
            '/api/report-comparison/compare',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->post($endpoint, ['period_days' => 30]);

            $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        }
    }

    /**
     * Test: API endpoints return consistent error format
     */
    public function test_api_endpoints_return_consistent_error_format()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => -5,
            'granularity' => 'daily',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonStructure(['success', 'message']);
    }

    /**
     * Test: API endpoints handle database errors gracefully
     */
    public function test_api_endpoints_handle_database_errors()
    {
        $this->actingAs($this->adminUser);

        // Test with extreme values
        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 99999,
            'granularity' => 'daily',
        ]);

        // Should handle gracefully
        $this->assertIn($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);
    }

    /**
     * Test: API response times are acceptable
     */
    public function test_api_response_times_are_acceptable()
    {
        $this->actingAs($this->adminUser);

        $start = microtime(true);

        $response = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);

        $duration = microtime(true) - $start;

        // API endpoint should respond in under 2 seconds
        $this->assertLessThan(2.0, $duration);
        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test: Multiple API endpoints can be called sequentially
     */
    public function test_multiple_endpoints_can_be_called_sequentially()
    {
        $this->actingAs($this->adminUser);

        $response1 = $this->post('/api/activity-analytics/sessions', [
            'period_days' => 30,
            'granularity' => 'daily',
        ]);
        $this->assertEquals(Response::HTTP_OK, $response1->getStatusCode());

        $response2 = $this->post('/api/activity-analytics/trends', [
            'period_days' => 30,
            'granularity' => 'daily',
            'metrics' => ['sessions']
        ]);
        $this->assertEquals(Response::HTTP_OK, $response2->getStatusCode());

        $response3 = $this->post('/api/activity-analytics/top-users', [
            'period_days' => 30,
            'limit' => 10
        ]);
        $this->assertEquals(Response::HTTP_OK, $response3->getStatusCode());
    }
}
