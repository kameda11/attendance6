<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_name_is_displayed_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee($user->name);
    }

    public function test_selected_date_is_displayed_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $selectedDate = Carbon::create(2025, 7, 12);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $selectedDate->copy()->setTime(9, 0),
            'clock_out_time' => $selectedDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $selectedDate,
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee('2025年');
        $response->assertSee('7月12日');
    }

    public function test_clock_in_out_times_are_displayed_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendanceDate = now()->subDays(2);
        $clockInTime = $attendanceDate->copy()->setTime(8, 30);
        $clockOutTime = $attendanceDate->copy()->setTime(17, 45);

        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee('08:30');
        $response->assertSee('17:45');
    }

    public function test_break_times_are_displayed_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        $attendanceDate = now()->subDays(1);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $attendanceDate->copy()->setTime(9, 0),
            'clock_out_time' => $attendanceDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        $breakStart = $attendanceDate->copy()->setTime(12, 0);
        $breakEnd = $attendanceDate->copy()->setTime(13, 0);

        $attendance->breaks()->create([
            'start_time' => $breakStart,
            'end_time' => $breakEnd,
        ]);

        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }
}
