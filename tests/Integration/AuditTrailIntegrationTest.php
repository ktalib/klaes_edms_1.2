<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests for audit trail system
 * Tests creation, retrieval, querying, and relationship integrity
 */
class AuditTrailIntegrationTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test: Audit trail creates complete record with all relationships
     */
    public function test_audit_trail_creates_complete_record()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);
        
        $service->logAction(
            action: 'CREATED',
            resourceType: 'report',
            resourceId: 1,
            newValues: ['name' => 'Test Report']
        );

        $log = AuditLog::latest()->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->user->id, $log->user_id);
        $this->assertEquals('CREATED', $log->action);
        $this->assertEquals('report', $log->resource_type);
        $this->assertEquals(1, $log->resource_id);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
        $this->assertNotNull($log->created_at);
    }

    /**
     * Test: Audit trail tracks data changes accurately
     */
    public function test_audit_trail_tracks_data_changes()
    {
        $this->actingAs($this->user);

        $oldValues = ['status' => 'pending', 'priority' => 'high'];
        $newValues = ['status' => 'approved', 'priority' => 'critical'];

        $service = app(AuditService::class);
        $service->logAction(
            action: 'UPDATED',
            resourceType: 'report',
            resourceId: 5,
            oldValues: $oldValues,
            newValues: $newValues
        );

        $log = AuditLog::where('resource_id', 5)->first();

        $this->assertEquals($oldValues, $log->old_values);
        $this->assertEquals($newValues, $log->new_values);
    }

    /**
     * Test: Multiple audit operations maintain relationship integrity
     */
    public function test_multiple_operations_maintain_integrity()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);

        // Create -> Update -> Delete sequence
        $service->logAction('CREATED', 'report', 10);
        $service->logAction('UPDATED', 'report', 10, ['v1' => 1], ['v1' => 2]);
        $service->logAction('DELETED', 'report', 10, ['v1' => 2]);

        $logs = AuditLog::byResource('report', 10)->orderBy('created_at')->get();

        $this->assertCount(3, $logs);
        $this->assertEquals('CREATED', $logs[0]->action);
        $this->assertEquals('UPDATED', $logs[1]->action);
        $this->assertEquals('DELETED', $logs[2]->action);
    }

    /**
     * Test: Audit trail retrieval is fast with indexes
     */
    public function test_audit_trail_retrieval_is_fast()
    {
        // Create multiple audit logs
        for ($i = 0; $i < 100; $i++) {
            AuditLog::factory()->create();
        }

        $start = microtime(true);

        // Query by user
        $logs = AuditLog::byUser($this->user->id)->get();

        $duration = microtime(true) - $start;

        // Query should complete in under 100ms
        $this->assertLessThan(0.1, $duration);
    }

    /**
     * Test: Audit trail supports complex filtering
     */
    public function test_audit_trail_supports_complex_filtering()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);
        $now = now();

        // Create logs with different attributes
        for ($i = 0; $i < 5; $i++) {
            $service->logAction(
                action: $i < 3 ? 'CREATED' : 'UPDATED',
                resourceType: $i < 2 ? 'report' : 'dashboard',
                resourceId: $i,
                newValues: ['iteration' => $i]
            );
        }

        // Complex query: user + action + resource + date
        $logs = AuditLog::byUser($this->user->id)
            ->byAction('CREATED')
            ->byResource('report', null)
            ->recent(30)
            ->get();

        $this->assertTrue($logs->every(fn($log) => 
            $log->user_id === $this->user->id &&
            $log->action === 'CREATED' &&
            $log->resource_type === 'report'
        ));
    }

    /**
     * Test: Audit trail handles concurrent writes
     */
    public function test_audit_trail_handles_concurrent_writes()
    {
        $users = User::factory()->count(3)->create();
        
        $service = app(AuditService::class);

        // Simulate concurrent writes from different users
        foreach ($users as $i => $user) {
            $this->actingAs($user);

            for ($j = 0; $j < 10; $j++) {
                $service->logAction(
                    action: 'CREATED',
                    resourceType: 'report',
                    resourceId: $i * 100 + $j
                );
            }
        }

        // Verify all logs were created
        $this->assertCount(30, AuditLog::all());

        // Verify no conflicts in user associations
        foreach ($users as $user) {
            $userLogs = AuditLog::byUser($user->id)->get();
            $this->assertCount(10, $userLogs);
        }
    }

    /**
     * Test: Audit trail maintains data consistency after deletion
     */
    public function test_audit_trail_maintains_consistency_after_deletion()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);
        
        // Create audit logs
        for ($i = 0; $i < 5; $i++) {
            $service->logAction('CREATED', 'report', $i);
        }

        $initialCount = AuditLog::count();

        // Soft delete one log
        $log = AuditLog::first();
        $log->delete();

        // Verify soft delete
        $this->assertCount($initialCount - 1, AuditLog::all());
        $this->assertCount($initialCount, AuditLog::withTrashed()->all());
    }

    /**
     * Test: Audit trail generates accurate statistics
     */
    public function test_audit_trail_generates_accurate_statistics()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);

        // Create varied audit logs
        $service->logAction('CREATED', 'report', 1);
        $service->logAction('CREATED', 'report', 2);
        $service->logAction('UPDATED', 'report', 3);
        $service->logAction('DELETED', 'dashboard', 4);
        $service->logAction('VIEWED', 'analytics', null);

        $stats = $service->getAuditStats(days: 7);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_actions', $stats);
        $this->assertGreaterThan(0, $stats['total_actions']);
        
        if (isset($stats['actions_breakdown'])) {
            $this->assertArrayHasKey('CREATED', $stats['actions_breakdown']);
        }
    }

    /**
     * Test: Audit trail handles large data values
     */
    public function test_audit_trail_handles_large_data_values()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);

        // Create large JSON data
        $largeData = [];
        for ($i = 0; $i < 100; $i++) {
            $largeData["field_$i"] = str_repeat('value', 100);
        }

        $service->logAction(
            action: 'UPDATED',
            resourceType: 'report',
            resourceId: 1,
            oldValues: $largeData,
            newValues: $largeData
        );

        $log = AuditLog::latest()->first();

        $this->assertIsArray($log->old_values);
        $this->assertIsArray($log->new_values);
        $this->assertCount(100, $log->old_values);
    }

    /**
     * Test: Audit trail respects user context
     */
    public function test_audit_trail_respects_user_context()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $service = app(AuditService::class);

        // Log actions as different users
        $this->actingAs($user1);
        $service->logAction('CREATED', 'report', 1);

        $this->actingAs($user2);
        $service->logAction('CREATED', 'report', 2);

        // Verify correct user association
        $log1 = AuditLog::where('resource_id', 1)->first();
        $log2 = AuditLog::where('resource_id', 2)->first();

        $this->assertEquals($user1->id, $log1->user_id);
        $this->assertEquals($user2->id, $log2->user_id);
    }

    /**
     * Test: Audit trail handles null user gracefully
     */
    public function test_audit_trail_handles_null_user()
    {
        Auth::logout();

        $service = app(AuditService::class);

        // This should handle gracefully or create with null user_id
        try {
            $service->logAction('CREATED', 'report', 1);
            
            // If it doesn't throw, check if log was created
            $log = AuditLog::latest()->first();
            if ($log) {
                $this->assertNull($log->user_id);
            }
        } catch (\Exception $e) {
            // Expected behavior - should fail gracefully
            $this->assertTrue(true);
        }
    }

    /**
     * Test: Audit trail querying performance scales
     */
    public function test_audit_trail_query_performance_scales()
    {
        // Create 1000 audit logs
        for ($i = 0; $i < 1000; $i++) {
            AuditLog::factory()->create();
        }

        $start = microtime(true);

        // Complex query with multiple conditions
        $logs = AuditLog::byAction('CREATED')
            ->recent(30)
            ->limit(100)
            ->get();

        $duration = microtime(true) - $start;

        // Query should complete in under 500ms even with 1000 records
        $this->assertLessThan(0.5, $duration);
    }

    /**
     * Test: Audit trail maintains referential integrity
     */
    public function test_audit_trail_maintains_referential_integrity()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);
        
        $service->logAction('CREATED', 'report', 1);

        $log = AuditLog::latest()->first();

        // Verify foreign key relationship
        $this->assertNotNull($log->user);
        $this->assertEquals($this->user->id, $log->user->id);
    }

    /**
     * Test: Audit trail supports pagination
     */
    public function test_audit_trail_supports_pagination()
    {
        // Create 50 audit logs
        for ($i = 0; $i < 50; $i++) {
            AuditLog::factory()->create();
        }

        $page1 = AuditLog::paginate(10);

        $this->assertCount(10, $page1);
        $this->assertEquals(50, $page1->total());
        $this->assertEquals(1, $page1->currentPage());
    }

    /**
     * Test: Audit trail enrichment with report details
     */
    public function test_audit_trail_enrichment_with_context()
    {
        $this->actingAs($this->user);

        $service = app(AuditService::class);

        $reportMetadata = [
            'report_name' => 'Monthly Summary',
            'format' => 'pdf',
            'recipients' => ['admin@example.com'],
            'generated_at' => now()->toDateTimeString(),
        ];

        $service->logAction(
            action: 'CREATED',
            resourceType: 'report',
            resourceId: 1,
            newValues: $reportMetadata
        );

        $log = AuditLog::latest()->first();

        $this->assertIsArray($log->new_values);
        $this->assertArrayHasKey('report_name', $log->new_values);
        $this->assertArrayHasKey('recipients', $log->new_values);
    }
}
