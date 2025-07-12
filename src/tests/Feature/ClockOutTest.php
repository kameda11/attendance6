<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_clock_out_functionality()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('退勤');
        $response->assertSee('clockOutBtn');

        $clockOutResponse = $this->post('/attendance/clock-out');
        $clockOutResponse->assertStatus(200);
        $clockOutResponse->assertJson(['success' => true]);

        $afterClockOutResponse = $this->get('/attendance');
        $afterClockOutResponse->assertStatus(200);
        $afterClockOutResponse->assertSee('退勤済');
    }

    public function test_clock_out_time_is_recorded_in_attendance_list()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $clockInTime = now();
        $this->travelTo($clockInTime);

        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'status' => 'working',
            'created_at' => $clockInTime,
        ]);

        $this->assertNotNull($attendance);
        $this->assertEquals('working', $attendance->status);

        $clockOutTime = $clockInTime->copy()->addHours(8);

        $attendance->update([
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
        ]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee($clockOutTime->format('H:i'));

        $attendance = Attendance::where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals($clockOutTime->format('Y-m-d H:i:s'), $attendance->clock_out_time->format('Y-m-d H:i:s'));

        $this->travelBack();
    }
}
