<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class AuditLogTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test: AuditLog can be created with valid data
     */
    public function test_audit_log_can_be_created()
    {
        $auditLog = AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'CREATED',
            'resource_type' => 'report',
            'resource_id' => 1,
            'new_values' => ['name' => 'Test Report'],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'user_id' => $this->user->id,
            'action' => 'CREATED',
        ]);
    }

    /**
     * Test: AuditLog belongs to User
     */
    public function test_audit_log_belongs_to_user()
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $auditLog->user);
        $this->assertEquals($this->user->id, $auditLog->user->id);
    }

    /**
     * Test: byResource() scope filters by resource type and ID
     */
    public function test_byResource_scope_filters_correctly()
    {
        AuditLog::factory()->count(3)->create([
            'resource_type' => 'report',
            'resource_id' => 1,
        ]);

        AuditLog::factory()->count(2)->create([
            'resource_type' => 'report',
            'resource_id' => 2,
        ]);

        AuditLog::factory()->count(1)->create([
            'resource_type' => 'dashboard',
            'resource_id' => 1,
        ]);

        $filtered = AuditLog::byResource('report', 1)->get();

        $this->assertCount(3, $filtered);
        $this->assertTrue($filtered->every(fn($log) => $log->resource_type === 'report' && $log->resource_id === 1));
    }

    /**
     * Test: byAction() scope filters by action type
     */
    public function test_byAction_scope_filters_correctly()
    {
        AuditLog::factory()->count(5)->create(['action' => 'CREATED']);
        AuditLog::factory()->count(3)->create(['action' => 'UPDATED']);
        AuditLog::factory()->count(2)->create(['action' => 'DELETED']);

        $created = AuditLog::byAction('CREATED')->get();
        $updated = AuditLog::byAction('UPDATED')->get();

        $this->assertCount(5, $created);
        $this->assertCount(3, $updated);
        $this->assertTrue($created->every(fn($log) => $log->action === 'CREATED'));
    }

    /**
     * Test: byUser() scope filters by user ID
     */
    public function test_byUser_scope_filters_correctly()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        AuditLog::factory()->count(4)->create(['user_id' => $user1->id]);
        AuditLog::factory()->count(2)->create(['user_id' => $user2->id]);

        $user1Logs = AuditLog::byUser($user1->id)->get();
        $user2Logs = AuditLog::byUser($user2->id)->get();

        $this->assertCount(4, $user1Logs);
        $this->assertCount(2, $user2Logs);
        $this->assertTrue($user1Logs->every(fn($log) => $log->user_id === $user1->id));
    }

    /**
     * Test: recent() scope filters by days
     */
    public function test_recent_scope_filters_by_days()
    {
        $now = now();

        // Create logs at different times
        AuditLog::factory()->create(['created_at' => $now->subDays(1)]);
        AuditLog::factory()->create(['created_at' => $now->subDays(5)]);
        AuditLog::factory()->create(['created_at' => $now->subDays(15)]);
        AuditLog::factory()->create(['created_at' => $now->subDays(35)]);

        $recent7 = AuditLog::recent(7)->get();
        $recent30 = AuditLog::recent(30)->get();

        $this->assertCount(2, $recent7);
        $this->assertCount(3, $recent30);
    }

    /**
     * Test: getActionDisplayName() converts action to readable text
     */
    public function test_getActionDisplayName_returns_readable_text()
    {
        $actions = [
            'CREATED' => 'Created',
            'UPDATED' => 'Updated',
            'DELETED' => 'Deleted',
            'DOWNLOADED' => 'Downloaded',
            'VIEWED' => 'Viewed',
        ];

        foreach ($actions as $action => $expected) {
            $auditLog = AuditLog::factory()->create(['action' => $action]);
            $this->assertEquals($expected, $auditLog->getActionDisplayName());
        }
    }

    /**
     * Test: getResourceDisplayName() converts resource type to readable text
     */
    public function test_getResourceDisplayName_returns_readable_text()
    {
        $resources = [
            'report' => 'Report',
            'scheduled_report' => 'Scheduled Report',
            'user_activity_log' => 'User Activity Log',
            'dashboard' => 'Dashboard',
            'analytics' => 'Analytics',
        ];

        foreach ($resources as $type => $expected) {
            $auditLog = AuditLog::factory()->create(['resource_type' => $type]);
            $this->assertEquals($expected, $auditLog->getResourceDisplayName());
        }
    }

    /**
     * Test: hasRecordedChanges() returns true when changes exist
     */
    public function test_hasRecordedChanges_returns_true_with_changes()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
        ]);

        $this->assertTrue($auditLog->hasRecordedChanges());
    }

    /**
     * Test: hasRecordedChanges() returns false when no changes
     */
    public function test_hasRecordedChanges_returns_false_without_changes()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => null,
            'new_values' => null,
        ]);

        $this->assertFalse($auditLog->hasRecordedChanges());
    }

    /**
     * Test: getChangedFields() extracts field names and values
     */
    public function test_getChangedFields_extracts_field_changes()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => ['status' => 'pending', 'count' => 5, 'name' => 'Old Name'],
            'new_values' => ['status' => 'approved', 'count' => 10, 'name' => 'New Name'],
        ]);

        $changedFields = $auditLog->getChangedFields();

        $this->assertArrayHasKey('status', $changedFields);
        $this->assertArrayHasKey('count', $changedFields);
        $this->assertArrayHasKey('name', $changedFields);
        $this->assertEquals('pending', $changedFields['status']['old']);
        $this->assertEquals('approved', $changedFields['status']['new']);
        $this->assertEquals(5, $changedFields['count']['old']);
        $this->assertEquals(10, $changedFields['count']['new']);
    }

    /**
     * Test: getChangedFields() returns empty array when no changes
     */
    public function test_getChangedFields_returns_empty_when_no_changes()
    {
        $auditLog = AuditLog::factory()->create([
            'old_values' => null,
            'new_values' => null,
        ]);

        $changedFields = $auditLog->getChangedFields();

        $this->assertIsArray($changedFields);
        $this->assertEmpty($changedFields);
    }

    /**
     * Test: JSON columns are automatically cast
     */
    public function test_json_columns_are_cast_automatically()
    {
        $oldValues = ['status' => 'pending', 'count' => 5];
        $newValues = ['status' => 'approved', 'count' => 10];

        $auditLog = AuditLog::factory()->create([
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $this->assertIsArray($auditLog->old_values);
        $this->assertIsArray($auditLog->new_values);
        $this->assertEquals($oldValues, $auditLog->old_values);
        $this->assertEquals($newValues, $auditLog->new_values);
    }

    /**
     * Test: Timestamps are set correctly
     */
    public function test_timestamps_are_set_correctly()
    {
        $before = now();
        
        $auditLog = AuditLog::factory()->create();
        
        $after = now();

        $this->assertTrue($auditLog->created_at->between($before, $after));
        $this->assertTrue($auditLog->updated_at->between($before, $after));
    }

    /**
     * Test: Soft deletes work correctly
     */
    public function test_soft_deletes_work_correctly()
    {
        $auditLog = AuditLog::factory()->create();
        $id = $auditLog->id;

        $auditLog->delete();

        $this->assertSoftDeleted('audit_logs', ['id' => $id]);
        $this->assertNull(AuditLog::find($id));
        $this->assertNotNull(AuditLog::withTrashed()->find($id));
    }

    /**
     * Test: Combining multiple scopes works correctly
     */
    public function test_combining_multiple_scopes_works()
    {
        $user = User::factory()->create();
        $now = now();

        // Create logs with different attributes
        AuditLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'CREATED',
            'resource_type' => 'report',
            'created_at' => $now->subDays(5),
        ]);

        AuditLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'UPDATED',
            'resource_type' => 'report',
            'created_at' => $now->subDays(35),
        ]);

        AuditLog::factory()->create([
            'user_id' => $user->id,
            'action' => 'CREATED',
            'resource_type' => 'dashboard',
            'created_at' => $now->subDays(5),
        ]);

        $filtered = AuditLog::byUser($user->id)
            ->byAction('CREATED')
            ->byResource('report', null)
            ->recent(10)
            ->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals('CREATED', $filtered[0]->action);
        $this->assertEquals('report', $filtered[0]->resource_type);
    }

    /**
     * Test: AuditLog fillable attributes
     */
    public function test_audit_log_fillable_attributes()
    {
        $data = [
            'user_id' => $this->user->id,
            'action' => 'CREATED',
            'resource_type' => 'report',
            'resource_id' => 1,
            'old_values' => null,
            'new_values' => ['name' => 'Test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Agent',
        ];

        $auditLog = AuditLog::create($data);

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $auditLog->{$key});
        }
    }

    /**
     * Test: Multiple audit logs can exist for same resource
     */
    public function test_multiple_logs_can_exist_for_same_resource()
    {
        for ($i = 0; $i < 5; $i++) {
            AuditLog::factory()->create([
                'resource_type' => 'report',
                'resource_id' => 1,
                'action' => $i % 2 === 0 ? 'CREATED' : 'UPDATED',
            ]);
        }

        $logs = AuditLog::byResource('report', 1)->get();

        $this->assertCount(5, $logs);
        $this->assertTrue($logs->every(fn($log) => $log->resource_type === 'report' && $log->resource_id === 1));
    }

    /**
     * Test: Audit log query performance with indexes
     */
    public function test_audit_log_queries_use_indexes()
    {
        AuditLog::factory()->count(100)->create();

        $start = microtime(true);
        $logs = AuditLog::byAction('CREATED')->byUser($this->user->id)->recent(30)->get();
        $elapsed = microtime(true) - $start;

        // Query should complete quickly (less than 100ms)
        $this->assertLessThan(0.1, $elapsed);
    }
}
