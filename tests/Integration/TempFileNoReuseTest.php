<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TempFileNoReuseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean up temp_fileno_sequence for testing
        // DB::connection('sqlsrv')->table('temp_fileno_sequence')->truncate(); // SQL Server truncate might fail on constraints
        DB::connection('sqlsrv')->table('temp_fileno_sequence')->delete();
    }

    public function test_temp_file_number_is_reused_if_not_marked_as_used()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Get first temp file number
        $response1 = $this->getJson(route('instruments.getNextTempFileNo'));
        $response1->assertOk();
        $data1 = $response1->json();
        $tempNo1 = $data1['temp_fileno'];

        // 2. Get second temp file number (should be the same)
        $response2 = $this->getJson(route('instruments.getNextTempFileNo'));
        $response2->assertOk();
        $data2 = $response2->json();
        $tempNo2 = $data2['temp_fileno'];

        $this->assertEquals($tempNo1, $tempNo2, 'Temp file number should be reused if not marked as used');
    }

    public function test_temp_file_number_increments_after_being_marked_as_used()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Get temp file number
        $response1 = $this->getJson(route('instruments.getNextTempFileNo'));
        $response1->assertOk();
        $tempNo1 = $response1->json()['temp_fileno'];
        $sequenceId1 = (int) str_replace('TEMP-', '', $tempNo1);

        // 2. Mark it as used manually
        DB::connection('sqlsrv')->table('temp_fileno_sequence')
            ->where('id', $sequenceId1)
            ->update(['is_used' => 1]);

        // 3. Get next temp file number (should be different)
        $response2 = $this->getJson(route('instruments.getNextTempFileNo'));
        $response2->assertOk();
        $tempNo2 = $response2->json()['temp_fileno'];

        $this->assertNotEquals($tempNo1, $tempNo2, 'Temp file number should increment after being used');
    }

    public function test_different_users_get_different_temp_file_numbers()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // 1. Get temp file number for user 1
        $this->actingAs($user1);
        $tempNo1 = $this->getJson(route('instruments.getNextTempFileNo'))->json()['temp_fileno'];

        // 2. Get temp file number for user 2
        $this->actingAs($user2);
        $tempNo2 = $this->getJson(route('instruments.getNextTempFileNo'))->json()['temp_fileno'];

        $this->assertNotEquals($tempNo1, $tempNo2, 'Different users should get different temp file numbers');
    }

    public function test_capturing_instrument_marks_temp_file_number_as_used()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Get temp file number
        $response = $this->getJson(route('instruments.getNextTempFileNo'));
        if ($response->status() !== 200) {
            $response->dump();
        }
        $response->assertStatus(200);
        
        $tempNo = $response->json()['temp_fileno'];
        $sequenceId = (int) str_replace('TEMP-', '', $tempNo);

        // Verify it's initially not used
        $initial = DB::connection('sqlsrv')->table('temp_fileno_sequence')->where('id', $sequenceId)->first();
        $this->assertEquals(0, $initial->is_used);

        // 2. Capture an instrument using this temp number
        // We simulate a minimal instrument capture request
        $captureData = [
            'instrument_type' => 'Deed of Assignment',
            'temp_fileno' => $tempNo,
            'firstPartyName' => 'Test Grantor',
            'secondPartyName' => 'Test Grantee',
            'is_temporary_file' => true
        ];

        // This might require more fields depending on InstrumentCaptureService validation
        $responseCapture = $this->postJson(route('instruments.store'), $captureData);
        $responseCapture->assertOk();

        // 3. Verify it is now marked as used
        $after = DB::connection('sqlsrv')->table('temp_fileno_sequence')->where('id', $sequenceId)->first();
        $this->assertEquals(1, $after->is_used, 'Temp file number should be marked as used after instrument capture');
    }
}
