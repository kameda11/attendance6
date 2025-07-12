<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_in_functionality()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤');
        $response->assertSee('clockInBtn');

        $clockInResponse = $this->post('/attendance/clock-in');
        $clockInResponse->assertStatus(200);
        $clockInResponse->assertJson(['success' => true]);

        $afterClockInResponse = $this->get('/attendance');
        $afterClockInResponse->assertStatus(200);
        $afterClockInResponse->assertSee('出勤中');
    }

    public function test_clock_in_button_not_displayed_for_completed_user()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $today = now();
        $user->attendances()->create([
            'clock_in_time' => $today->copy()->subHours(8),
            'clock_out_time' => $today,
            'status' => 'completed',
            'created_at' => $today,
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $response->assertDontSee('id="clockInBtn"');
        $response->assertDontSee('onclick="clockIn()"');
        $response->assertSee('退勤済');
        $response->assertSee('お疲れ様でした');
    }


    public function test_clock_in_time_is_recorded_in_attendance_list()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $clockInTime = now();
        $this->travelTo($clockInTime);

        $clockInResponse = $this->post('/attendance/clock-in');
        $clockInResponse->assertStatus(200);
        $clockInResponse->assertJson(['success' => true]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($clockInTime->format('H:i'));

        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals($clockInTime->format('Y-m-d H:i:s'), $attendance->clock_in_time->format('Y-m-d H:i:s'));

        $this->travelBack();
    }
}
 