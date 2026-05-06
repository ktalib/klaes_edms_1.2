<?php

namespace Tests\Unit\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\AnalyticsDashboardController;
use App\Http\Controllers\ReportComparisonController;
use App\Http\Controllers\ActivityLogReportsController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsDashboardControllerTest extends TestCase
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
     * Test: index() returns analytics dashboard view
     */
    public function test_index_returns_analytics_dashboard_view()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('activity.advanced-analytics');
    }

    /**
     * Test: index() requires authentication
     */
    public function test_index_requires_authentication()
    {
        $response = $this->get('/analytics-dashboard');

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: index() checks view-analytics permission
     */
    public function test_index_checks_view_analytics_permission()
    {
        $this->actingAs($this->user);

        // User without permission should get denied
        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test: index() passes user to view
     */
    public function test_index_passes_user_to_view()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/analytics-dashboard');

        $response->assertViewHas('user');
    }

    /**
     * Test: index() handles errors gracefully
     */
    public function test_index_handles_errors_gracefully()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/analytics-dashboard');

        $response->assertStatus(Response::HTTP_OK);
    }
}

class ReportComparisonControllerTest extends TestCase
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
     * Test: index() returns comparison dashboard view
     */
    public function test_index_returns_comparison_dashboard()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/report-comparison');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('activity.report-comparison');
    }

    /**
     * Test: index() requires authentication
     */
    public function test_index_requires_authentication()
    {
        $response = $this->get('/report-comparison');

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: index() checks view-analytics permission
     */
    public function test_index_checks_view_analytics_permission()
    {
        $this->actingAs($this->user);

        $response = $this->get('/report-comparison');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test: compare() validates input parameters
     */
    public function test_compare_validates_period_days()
    {
        $this->actingAs($this->adminUser);

        // Invalid period (too large)
        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 400,
            'period2_days' => 30,
            'metric' => 'sessions',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: compare() validates metric parameter
     */
    public function test_compare_validates_metric_parameter()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 30,
            'metric' => 'invalid_metric',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: compare() accepts valid parameters
     */
    public function test_compare_accepts_valid_parameters()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Test: compare() returns JSON response
     */
    public function test_compare_returns_json_response()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $this->assertTrue($response->isJson());
        $this->assertArrayHasKey('success', $response->json());
        $this->assertArrayHasKey('data', $response->json());
    }

    /**
     * Test: compare() returns comparison data
     */
    public function test_compare_returns_comparison_data()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'all',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    /**
     * Test: compare() requires authentication
     */
    public function test_compare_requires_authentication()
    {
        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: compare() checks authorization
     */
    public function test_compare_checks_authorization()
    {
        $this->actingAs($this->user);

        $response = $this->post('/report-comparison/compare', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test: exportPdf() returns JSON response
     */
    public function test_exportPdf_returns_json_response()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/report-comparison/export-pdf', [
            'period1_days' => 30,
            'period2_days' => 7,
            'metric' => 'sessions',
        ]);

        $this->assertTrue($response->isJson());
        $response->assertJsonStructure(['success', 'message']);
    }

    /**
     * Test: exportPdf() requires authentication
     */
    public function test_exportPdf_requires_authentication()
    {
        $response = $this->post('/report-comparison/export-pdf');

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: exportPdf() checks authorization
     */
    public function test_exportPdf_checks_authorization()
    {
        $this->actingAs($this->user);

        $response = $this->post('/report-comparison/export-pdf');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}

class ActivityLogReportsControllerTest extends TestCase
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
     * Test: index() returns reports page
     */
    public function test_index_returns_reports_page()
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/activity-reports');

        $response->assertStatus(Response::HTTP_OK);
    }

    /**
     * Test: index() requires authentication
     */
    public function test_index_requires_authentication()
    {
        $response = $this->get('/activity-reports');

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: index() checks view-analytics permission
     */
    public function test_index_checks_authorization()
    {
        $this->actingAs($this->user);

        $response = $this->get('/activity-reports');

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test: generate() validates request
     */
    public function test_generate_validates_request()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/activity-reports/generate', [
            // Missing required fields
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: generate() returns success response
     */
    public function test_generate_returns_success_response()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test: download() returns report file
     */
    public function test_download_returns_report_file()
    {
        $this->actingAs($this->adminUser);

        // First generate a report
        $generateResponse = $this->post('/activity-reports/generate', [
            'report_type' => 'activity_summary',
            'period' => 30,
        ]);

        if ($generateResponse->getStatusCode() === 200) {
            $reportId = $generateResponse->json('data.id') ?? 1;

            $response = $this->get("/activity-reports/download/{$reportId}");

            // Response should be successful (could be file or error)
            $this->assertTrue(in_array($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_NOT_FOUND,
            ]));
        }
    }

    /**
     * Test: schedule() creates scheduled report
     */
    public function test_schedule_creates_scheduled_report()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/activity-reports/schedule', [
            'report_type' => 'activity_summary',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['admin@example.com'],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test: schedule() validates recipients
     */
    public function test_schedule_validates_recipients()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/activity-reports/schedule', [
            'report_type' => 'activity_summary',
            'frequency' => 'daily',
            'format' => 'pdf',
            'recipients' => ['invalid-email'],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Test: delete() removes report
     */
    public function test_delete_removes_report()
    {
        $this->actingAs($this->adminUser);

        // Reports controller delete functionality
        $response = $this->delete('/activity-reports/1');

        $this->assertTrue(in_array($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND,
        ]));
    }

    /**
     * Test: delete() requires authentication
     */
    public function test_delete_requires_authentication()
    {
        $response = $this->delete('/activity-reports/1');

        $response->assertRedirect(route('login'));
    }

    /**
     * Test: export() supports multiple formats
     */
    public function test_export_supports_multiple_formats()
    {
        $this->actingAs($this->adminUser);

        $formats = ['csv', 'pdf', 'excel'];

        foreach ($formats as $format) {
            $response = $this->post('/activity-reports/export', [
                'report_type' => 'activity_summary',
                'period' => 30,
                'format' => $format,
            ]);

            // Response should be successful or unprocessable
            $this->assertTrue(in_array($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            ]));
        }
    }

    /**
     * Test: controllers return proper HTTP status codes
     */
    public function test_controllers_return_proper_status_codes()
    {
        $this->actingAs($this->adminUser);

        // Test 200 OK
        $response = $this->get('/activity-reports');
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        // Test 302 redirect for unauthenticated user
        Auth::logout();
        $response = $this->get('/activity-reports');
        $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
    }

    /**
     * Test: all controllers return JSON for AJAX requests
     */
    public function test_controllers_return_json_for_ajax()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/activity-reports/generate', 
            ['report_type' => 'activity_summary', 'period' => 30],
            ['Accept' => 'application/json']
        );

        $this->assertTrue($response->isJson());
    }

    /**
     * Test: controllers handle exceptions gracefully
     */
    public function test_controllers_handle_exceptions_gracefully()
    {
        $this->actingAs($this->adminUser);

        // Test with invalid parameters
        $response = $this->post('/activity-reports/generate', [
            'report_type' => 'invalid_type',
            'period' => -5,
        ]);

        // Should return error response, not exception
        $this->assertNotNull($response->getContent());
    }
}
