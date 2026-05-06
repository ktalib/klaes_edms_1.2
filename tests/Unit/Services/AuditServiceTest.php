<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AuditService;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AuditServiceTest extends TestCase
{
    protected AuditService $auditService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Test: logAction() creates audit log with all required fields
     */
    public function test_logAction_creates_audit_log_with_required_fields()
    {
        $this->actingAs($this->user);

        $this->auditService->logAction(
            action: 'CREATED',
            resourceType: 'report',
            resourceId: 1,
            oldValues: null,
            newValues: ['name' => 'Test Report', 'format' => 'pdf']
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'CREATED',
            'resource_type' => 'report',
            'resource_id' => 1,
        ]);

        $auditLog = AuditLog::latest()->first();
        $this->assertNotNull($auditLog->ip_address);
        $this->assertNotNull($auditLog->user_agent);
        $this->assertEquals(['name' => 'Test Report', 'format' => 'pdf'], $auditLog->new_values);
    }

    /**
     * Test: logAction() captures IP address correctly
     */
    public function test_logAction_captures_ipv4_address()
    {
        $this->actingAs($this->user);

        $this->auditService->logAction(
            action: 'VIEWED',
            resourceType: 'dashboard',
            resourceId: null
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertMatchesRegularExpression('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $auditLog->ip_address);
    }

    /**
     * Test: logAction() handles old and new values JSON serialization
     */
    public function test_logAction_serializes_old_and_new_values_to_json()
    {
        $this->actingAs($this->user);

        $oldValues = ['status' => 'pending', 'count' => 5];
        $newValues = ['status' => 'approved', 'count' => 10];

        $this->auditService->logAction(
            action: 'UPDATED',
            resourceType: 'report',
            resourceId: 42,
            oldValues: $oldValues,
            newValues: $newValues
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals($oldValues, $auditLog->old_values);
        $this->assertEquals($newValues, $auditLog->new_values);
    }

    /**
     * Test: logReportGenerated() logs report creation with metadata
     */
    public function test_logReportGenerated_logs_report_creation()
    {
        $this->actingAs($this->user);

        $this->auditService->logReportGenerated(
            reportId: 1,
            format: 'pdf',
            filters: ['status' => 'active', 'date_from' => '2025-01-01'],
            recordCount: 150
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('report', $auditLog->resource_type);
        $this->assertEquals(1, $auditLog->resource_id);
        $this->assertStringContainsString('pdf', json_encode($auditLog->new_values));
        $this->assertStringContainsString('150', json_encode($auditLog->new_values));
    }

    /**
     * Test: logReportDownloaded() logs download action
     */
    public function test_logReportDownloaded_logs_download_action()
    {
        $this->actingAs($this->user);

        $this->auditService->logReportDownloaded(
            reportId: 5,
            format: 'excel'
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('DOWNLOADED', $auditLog->action);
        $this->assertEquals('report', $auditLog->resource_type);
        $this->assertEquals(5, $auditLog->resource_id);
    }

    /**
     * Test: logReportDeleted() preserves report data in audit trail
     */
    public function test_logReportDeleted_preserves_report_data()
    {
        $this->actingAs($this->user);

        $reportData = [
            'id' => 10,
            'name' => 'Monthly Activity Report',
            'format' => 'pdf',
            'created_at' => now()->toDateTimeString()
        ];

        $this->auditService->logReportDeleted(
            reportId: 10,
            reportData: $reportData
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('DELETED', $auditLog->action);
        $this->assertEquals('report', $auditLog->resource_type);
        $this->assertStringContainsString('Monthly Activity Report', json_encode($auditLog->old_values));
    }

    /**
     * Test: logReportExported() logs export operation
     */
    public function test_logReportExported_logs_export_operation()
    {
        $this->actingAs($this->user);

        $this->auditService->logReportExported(
            reportId: 3,
            exportFormat: 'csv',
            destination: 'email:admin@example.com'
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('EXPORTED', $auditLog->action);
        $this->assertStringContainsString('csv', json_encode($auditLog->new_values));
        $this->assertStringContainsString('email', json_encode($auditLog->new_values));
    }

    /**
     * Test: logScheduleCreated() logs schedule creation
     */
    public function test_logScheduleCreated_logs_schedule_creation()
    {
        $this->actingAs($this->user);

        $this->auditService->logScheduleCreated(
            scheduleId: 7,
            frequency: 'daily',
            format: 'pdf',
            recipients: ['user1@example.com', 'user2@example.com']
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('CREATED', $auditLog->action);
        $this->assertEquals('scheduled_report', $auditLog->resource_type);
        $this->assertEquals(7, $auditLog->resource_id);
        $this->assertStringContainsString('daily', json_encode($auditLog->new_values));
    }

    /**
     * Test: logScheduleUpdated() logs schedule modifications
     */
    public function test_logScheduleUpdated_logs_schedule_modifications()
    {
        $this->actingAs($this->user);

        $oldValues = ['frequency' => 'weekly', 'enabled' => true];
        $newValues = ['frequency' => 'monthly', 'enabled' => false];

        $this->auditService->logScheduleUpdated(
            scheduleId: 8,
            oldValues: $oldValues,
            newValues: $newValues
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('UPDATED', $auditLog->action);
        $this->assertEquals('scheduled_report', $auditLog->resource_type);
        $this->assertEquals($oldValues, $auditLog->old_values);
        $this->assertEquals($newValues, $auditLog->new_values);
    }

    /**
     * Test: logScheduleDeleted() preserves schedule data
     */
    public function test_logScheduleDeleted_preserves_schedule_data()
    {
        $this->actingAs($this->user);

        $scheduleData = [
            'id' => 9,
            'name' => 'Weekly Report',
            'frequency' => 'weekly',
            'created_at' => now()->toDateTimeString()
        ];

        $this->auditService->logScheduleDeleted(
            scheduleId: 9,
            scheduleData: $scheduleData
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('DELETED', $auditLog->action);
        $this->assertEquals('scheduled_report', $auditLog->resource_type);
        $this->assertStringContainsString('Weekly Report', json_encode($auditLog->old_values));
    }

    /**
     * Test: logScheduleExecuted() logs schedule execution with result
     */
    public function test_logScheduleExecuted_logs_execution_result()
    {
        $this->actingAs($this->user);

        $this->auditService->logScheduleExecuted(
            scheduleId: 10,
            success: true,
            message: 'Report generated and sent to 3 recipients',
            recipients: ['user1@example.com', 'user2@example.com', 'user3@example.com']
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('EXECUTED', $auditLog->action);
        $this->assertEquals('scheduled_report', $auditLog->resource_type);
        $this->assertStringContainsString('generated', json_encode($auditLog->new_values));
    }

    /**
     * Test: logActivityDataAccessed() logs data access
     */
    public function test_logActivityDataAccessed_logs_data_access()
    {
        $this->actingAs($this->user);

        $filters = ['status' => 'active', 'user_id' => 5];

        $this->auditService->logActivityDataAccessed(
            filters: $filters,
            recordsReturned: 250
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('VIEWED', $auditLog->action);
        $this->assertEquals('user_activity_log', $auditLog->resource_type);
        $this->assertStringContainsString('250', json_encode($auditLog->new_values));
    }

    /**
     * Test: logAnalyticsViewed() logs analytics access
     */
    public function test_logAnalyticsViewed_logs_analytics_access()
    {
        $this->actingAs($this->user);

        $parameters = ['period' => 30, 'granularity' => 'daily'];

        $this->auditService->logAnalyticsViewed(
            analyticsType: 'advanced_analytics',
            parameters: $parameters
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('VIEWED', $auditLog->action);
        $this->assertEquals('analytics', $auditLog->resource_type);
        $this->assertStringContainsString('30', json_encode($auditLog->new_values));
    }

    /**
     * Test: logBulkAction() logs bulk operations
     */
    public function test_logBulkAction_logs_bulk_operations()
    {
        $this->actingAs($this->user);

        $this->auditService->logBulkAction(
            action: 'DELETED',
            resourceType: 'audit_log',
            affectedCount: 500,
            criteria: ['older_than_days' => 90]
        );

        $auditLog = AuditLog::latest()->first();
        $this->assertEquals('DELETED', $auditLog->action);
        $this->assertEquals('audit_log', $auditLog->resource_type);
        $this->assertStringContainsString('500', json_encode($auditLog->new_values));
        $this->assertStringContainsString('90', json_encode($auditLog->new_values));
    }

    /**
     * Test: getResourceAudit() retrieves audit history for resource
     */
    public function test_getResourceAudit_retrieves_resource_history()
    {
        $this->actingAs($this->user);

        // Create multiple audit logs for same resource
        for ($i = 0; $i < 3; $i++) {
            $this->auditService->logAction(
                action: $i === 0 ? 'CREATED' : 'UPDATED',
                resourceType: 'report',
                resourceId: 20,
                newValues: ['iteration' => $i]
            );
        }

        $history = $this->auditService->getResourceAudit('report', 20, limit: 10);

        $this->assertCount(3, $history);
        $this->assertEquals('report', $history[0]['resource_type']);
        $this->assertEquals(20, $history[0]['resource_id']);
    }

    /**
     * Test: getUserAudit() retrieves all actions by user
     */
    public function test_getUserAudit_retrieves_user_actions()
    {
        $this->actingAs($this->user);

        // Create multiple audit logs by user
        for ($i = 0; $i < 5; $i++) {
            $this->auditService->logAction(
                action: 'VIEWED',
                resourceType: 'dashboard',
                resourceId: $i
            );
        }

        $userActions = $this->auditService->getUserAudit($this->user->id, limit: 10);

        $this->assertCount(5, $userActions);
        $this->assertTrue($userActions->every(fn($log) => $log['user_id'] === $this->user->id));
    }

    /**
     * Test: getAuditStats() returns statistical summary
     */
    public function test_getAuditStats_returns_statistical_summary()
    {
        $this->actingAs($this->user);

        // Create audit logs with different actions and resources
        $this->auditService->logAction('CREATED', 'report', 1);
        $this->auditService->logAction('UPDATED', 'report', 1);
        $this->auditService->logAction('DELETED', 'report', 2);
        $this->auditService->logAction('VIEWED', 'dashboard', null);

        $stats = $this->auditService->getAuditStats(days: 7);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertArrayHasKey('actions_breakdown', $stats);
        $this->assertArrayHasKey('resource_breakdown', $stats);
        $this->assertArrayHasKey('top_users', $stats);
        $this->assertGreaterThan(0, $stats['total_actions']);
    }

    /**
     * Test: Audit logs are associated with correct user
     */
    public function test_audit_logs_are_associated_with_correct_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1);
        $this->auditService->logAction('CREATED', 'report', 1);

        $this->actingAs($user2);
        $this->auditService->logAction('UPDATED', 'report', 1);

        $user1Logs = AuditLog::where('user_id', $user1->id)->get();
        $user2Logs = AuditLog::where('user_id', $user2->id)->get();

        $this->assertCount(1, $user1Logs);
        $this->assertCount(1, $user2Logs);
    }

    /**
     * Test: Audit log timestamps are set correctly
     */
    public function test_audit_log_timestamps_are_set_correctly()
    {
        $this->actingAs($this->user);

        $before = now();
        $this->auditService->logAction('CREATED', 'report', 1);
        $after = now();

        $auditLog = AuditLog::latest()->first();

        $this->assertTrue($auditLog->created_at->between($before, $after));
        $this->assertTrue($auditLog->updated_at->between($before, $after));
    }

    /**
     * Test: Audit service handles null values gracefully
     */
    public function test_audit_service_handles_null_values_gracefully()
    {
        $this->actingAs($this->user);

        $this->auditService->logAction(
            action: 'VIEWED',
            resourceType: 'dashboard',
            resourceId: null,
            oldValues: null,
            newValues: null
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNull($auditLog->resource_id);
        $this->assertNull($auditLog->old_values);
        $this->assertNull($auditLog->new_values);
    }

    /**
     * Test: Multiple concurrent audit logs don't conflict
     */
    public function test_multiple_audit_logs_dont_conflict()
    {
        $this->actingAs($this->user);

        // Create multiple logs rapidly
        for ($i = 0; $i < 10; $i++) {
            $this->auditService->logAction(
                action: 'CREATED',
                resourceType: 'report',
                resourceId: $i
            );
        }

        $this->assertCount(10, AuditLog::where('user_id', $this->user->id)->get());
    }
}
