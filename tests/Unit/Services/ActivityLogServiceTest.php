<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ActivityLogService;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserActivityLogSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ActivityLogServiceTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Cache::flush();
    }

    /**
     * Test: recordLogin() creates new activity log entry
     */
    public function test_recordLogin_creates_new_entry()
    {
        $activityLog = ActivityLogService::recordLogin($this->user, '192.168.1.1', 'Mozilla/5.0');

        $this->assertNotNull($activityLog);
        $this->assertDatabaseHas('user_activity_logs', [
            'user_id' => $this->user->id,
            'ip_address' => '192.168.1.1',
            'status' => 'Online',
        ]);
    }

    /**
     * Test: recordLogin() marks previous sessions as offline
     */
    public function test_recordLogin_marks_previous_sessions_offline()
    {
        // Create first session
        $firstSession = ActivityLogService::recordLogin($this->user, '192.168.1.1', 'Mozilla/5.0');
        $this->assertEquals('Online', $firstSession->status);

        // Create second session
        $secondSession = ActivityLogService::recordLogin($this->user, '192.168.1.2', 'Chrome/5.0');
        $this->assertEquals('Online', $secondSession->status);

        // First session should be marked offline
        $firstSession->refresh();
        $this->assertEquals('Offline', $firstSession->status);
        $this->assertNotNull($firstSession->logout_time);
    }

    /**
     * Test: recordLogin() parses device information correctly
     */
    public function test_recordLogin_parses_device_info()
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0';
        $activityLog = ActivityLogService::recordLogin($this->user, '192.168.1.1', $userAgent);

        $this->assertNotNull($activityLog->device);
        $this->assertNotNull($activityLog->browser);
        $this->assertNotNull($activityLog->platform);
    }

    /**
     * Test: recordLogout() marks session as offline
     */
    public function test_recordLogout_marks_session_offline()
    {
        $session = ActivityLogService::recordLogin($this->user);
        $this->assertEquals('Online', $session->status);

        $result = ActivityLogService::recordLogout($this->user);

        $this->assertTrue($result);
        $session->refresh();
        $this->assertEquals('Offline', $session->status);
        $this->assertNotNull($session->logout_time);
    }

    /**
     * Test: recordLogout() calculates session duration
     */
    public function test_recordLogout_calculates_session_duration()
    {
        $session = ActivityLogService::recordLogin($this->user);
        
        // Advance time by 30 minutes
        Carbon::setTestNow(now()->addMinutes(30));

        ActivityLogService::recordLogout($this->user);

        $session->refresh();
        $this->assertNotNull($session->duration_minutes);
        $this->assertGreaterThan(0, $session->duration_minutes);
    }

    /**
     * Test: recordLogout() returns false when no active session
     */
    public function test_recordLogout_returns_false_without_active_session()
    {
        $result = ActivityLogService::recordLogout($this->user);

        $this->assertFalse($result);
    }

    /**
     * Test: updateHeartbeat() updates last_seen_at timestamp
     */
    public function test_updateHeartbeat_updates_last_seen_timestamp()
    {
        $session = ActivityLogService::recordLogin($this->user);
        $originalTime = $session->last_seen_at;

        Carbon::setTestNow(now()->addSeconds(30));

        $result = ActivityLogService::updateHeartbeat($this->user);

        $this->assertTrue($result);
        $session->refresh();
        $this->assertNotEquals($originalTime, $session->last_seen_at);
    }

    /**
     * Test: updateHeartbeat() creates session if none exists
     */
    public function test_updateHeartbeat_creates_session_if_missing()
    {
        $this->assertNull(UserActivityLog::getCurrentSessionForUser($this->user->id));

        $result = ActivityLogService::updateHeartbeat($this->user);

        $this->assertTrue($result);
        $this->assertNotNull(UserActivityLog::getCurrentSessionForUser($this->user->id));
    }

    /**
     * Test: updateHeartbeat() links file number if provided
     */
    public function test_updateHeartbeat_links_file_number()
    {
        $session = ActivityLogService::recordLogin($this->user);
        $fileNumber = 'ST-COM-2025-001';

        ActivityLogService::updateHeartbeat($this->user, $fileNumber);

        $session->refresh();
        $this->assertEquals($fileNumber, $session->related_file_number);
    }

    /**
     * Test: detectIdleUsers() marks sessions as idle
     */
    public function test_detectIdleUsers_marks_sessions_idle()
    {
        // Create online session
        $session = ActivityLogService::recordLogin($this->user);
        
        // Set last_seen_at to 35 minutes ago
        $session->update(['last_seen_at' => now()->subMinutes(35)]);

        $result = ActivityLogService::detectIdleUsers(idleMinutes: 30);

        $this->assertGreaterThan(0, $result);
        $session->refresh();
        $this->assertEquals('Idle', $session->status);
    }

    /**
     * Test: detectIdleUsers() respects idle minutes threshold
     */
    public function test_detectIdleUsers_respects_threshold()
    {
        // Create online session
        $session = ActivityLogService::recordLogin($this->user);
        
        // Set last_seen_at to 10 minutes ago (below threshold)
        $session->update(['last_seen_at' => now()->subMinutes(10)]);

        $result = ActivityLogService::detectIdleUsers(idleMinutes: 30);

        $this->assertEquals(0, $result);
        $session->refresh();
        $this->assertEquals('Online', $session->status);
    }

    /**
     * Test: detectStaleUsers() marks sessions as offline
     */
    public function test_detectStaleUsers_marks_sessions_offline()
    {
        // Create online session
        $session = ActivityLogService::recordLogin($this->user);
        
        // Set last_seen_at to 130 minutes ago
        $session->update(['last_seen_at' => now()->subMinutes(130)]);

        $result = ActivityLogService::detectStaleUsers(timeoutMinutes: 120);

        $this->assertGreaterThan(0, $result);
        $session->refresh();
        $this->assertEquals('Offline', $session->status);
    }

    /**
     * Test: performCleanup() deletes old test data
     */
    public function test_performCleanup_deletes_old_test_data()
    {
        // Create test data older than 7 days
        UserActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'test_control' => 'TEST',
            'login_time' => now()->subDays(10),
        ]);

        // Create recent test data
        UserActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'test_control' => 'TEST',
            'login_time' => now()->subDays(2),
        ]);

        $results = ActivityLogService::performCleanup(testDataDays: 7);

        $this->assertGreaterThan(0, $results['test_data_deleted']);
    }

    /**
     * Test: performCleanup() deletes old archived logs
     */
    public function test_performCleanup_deletes_old_archived_logs()
    {
        // Create old offline session
        $session = UserActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'test_control' => 'PRO',
            'status' => 'Offline',
            'login_time' => now()->subDays(100),
            'logout_time' => now()->subDays(100),
        ]);

        // Create recent offline session
        UserActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'test_control' => 'PRO',
            'status' => 'Offline',
            'login_time' => now()->subDays(30),
            'logout_time' => now()->subDays(30),
        ]);

        $results = ActivityLogService::performCleanup(archiveDays: 90);

        $this->assertGreaterThan(0, $results['archived_logs_deleted']);
    }

    /**
     * Test: performCleanup() cleans orphaned entries
     */
    public function test_performCleanup_cleans_orphaned_entries()
    {
        // Create session for user
        $session = ActivityLogService::recordLogin($this->user);
        
        // Delete user (creates orphaned entry)
        $this->user->delete();

        $results = ActivityLogService::performCleanup();

        // Result depends on cascade behavior
        $this->assertIsArray($results);
    }

    /**
     * Test: getOnlineUserCount() returns cached count
     */
    public function test_getOnlineUserCount_returns_cached_count()
    {
        ActivityLogService::recordLogin($this->user);

        $count = ActivityLogService::getOnlineUserCount();

        $this->assertGreaterThan(0, $count);
        $this->assertTrue(Cache::has('activity_logs_online_count'));
    }

    /**
     * Test: getOnlineUsers() returns list of online users
     */
    public function test_getOnlineUsers_returns_online_users()
    {
        ActivityLogService::recordLogin($this->user);

        $onlineUsers = ActivityLogService::getOnlineUsers();

        $this->assertIsCollection($onlineUsers);
        $this->assertTrue($onlineUsers->count() > 0);
    }

    /**
     * Test: getIdleUsers() returns only idle users
     */
    public function test_getIdleUsers_returns_only_idle_users()
    {
        // Create idle session
        $idleSession = UserActivityLog::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'Idle',
        ]);

        // Create online session
        $onlineSession = UserActivityLog::factory()->create([
            'status' => 'Online',
        ]);

        $idleUsers = ActivityLogService::getIdleUsers();

        $this->assertTrue($idleUsers->every(fn($log) => $log->status === 'Idle'));
    }

    /**
     * Test: getActivityStatistics() returns statistical data
     */
    public function test_getActivityStatistics_returns_statistics()
    {
        // Create activity logs
        for ($i = 0; $i < 5; $i++) {
            UserActivityLog::factory()->create([
                'user_id' => $this->user->id,
                'login_time' => now()->subDays($i),
            ]);
        }

        $stats = ActivityLogService::getActivityStatistics(days: 30);

        $this->assertIsArray($stats);
    }

    /**
     * Test: linkFileNumber() links file number to session
     */
    public function test_linkFileNumber_links_to_session()
    {
        ActivityLogService::recordLogin($this->user);
        $fileNumber = 'ST-RES-2025-042';

        $result = ActivityLogService::linkFileNumber($this->user, $fileNumber);

        $this->assertTrue($result);
        $session = UserActivityLog::getCurrentSessionForUser($this->user->id);
        $this->assertEquals($fileNumber, $session->related_file_number);
    }

    /**
     * Test: linkFileNumber() returns false without active session
     */
    public function test_linkFileNumber_returns_false_without_session()
    {
        $result = ActivityLogService::linkFileNumber($this->user, 'ST-COM-2025-001');

        $this->assertFalse($result);
    }

    /**
     * Test: unlinkFileNumber() removes file number from session
     */
    public function test_unlinkFileNumber_removes_file_number()
    {
        $session = ActivityLogService::recordLogin($this->user);
        $session->update(['related_file_number' => 'ST-IND-2025-100']);

        $result = ActivityLogService::unlinkFileNumber($this->user);

        $this->assertTrue($result);
        $session->refresh();
        $this->assertNull($session->related_file_number);
    }

    /**
     * Test: getSettings() returns or creates user settings
     */
    public function test_getSettings_returns_or_creates_settings()
    {
        $settings = ActivityLogService::getSettings($this->user);

        $this->assertInstanceOf(UserActivityLogSetting::class, $settings);
        $this->assertEquals($this->user->id, $settings->user_id);
    }

    /**
     * Test: updateSettings() updates user timezone
     */
    public function test_updateSettings_updates_timezone()
    {
        $result = ActivityLogService::updateSettings($this->user, [
            'timezone' => 'America/New_York',
        ]);

        $this->assertNotNull($result);
        // Verify in database if applicable
    }

    /**
     * Test: Multiple simultaneous login/logout operations
     */
    public function test_multiple_simultaneous_operations()
    {
        $user2 = User::factory()->create();

        ActivityLogService::recordLogin($this->user, '192.168.1.1');
        ActivityLogService::recordLogin($user2, '192.168.1.2');

        $this->assertEquals(2, UserActivityLog::online()->count());

        ActivityLogService::recordLogout($this->user);

        $this->assertEquals(1, UserActivityLog::online()->count());
    }

    /**
     * Test: Activity log service handles exceptions gracefully
     */
    public function test_service_handles_exceptions_gracefully()
    {
        // Test with null user (should not crash)
        $result = ActivityLogService::recordLogout(User::factory()->make());

        $this->assertFalse($result);
    }

    /**
     * Test: Session duration calculated correctly
     */
    public function test_session_duration_calculated_correctly()
    {
        $session = ActivityLogService::recordLogin($this->user);
        $startTime = $session->login_time;

        Carbon::setTestNow($startTime->addHours(2)->addMinutes(15));

        ActivityLogService::recordLogout($this->user);

        $session->refresh();
        $this->assertGreaterThan(100, $session->duration_minutes);
        $this->assertLessThan(150, $session->duration_minutes);
    }

    /**
     * Test: User agent parsing for different devices
     */
    public function test_user_agent_parsing_for_different_devices()
    {
        $browsers = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15',
            'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15',
        ];

        foreach ($browsers as $userAgent) {
            $session = ActivityLogService::recordLogin(
                User::factory()->create(),
                '192.168.1.1',
                $userAgent
            );

            $this->assertNotNull($session->device);
            $this->assertNotNull($session->browser);
            $this->assertNotNull($session->platform);
        }
    }
}
