<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_start_functionality()
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
        $response->assertSee('休憩入');
        $response->assertSee('breakStartBtn');

        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        $afterBreakStartResponse = $this->get('/attendance');
        $afterBreakStartResponse->assertStatus(200);
        $afterBreakStartResponse->assertSee('休憩中');
        $afterBreakStartResponse->assertSee('休憩戻');
    }

    public function test_break_in_button_reappears_after_break_cycle()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $response->assertSee('休憩入');
        $response->assertSee('breakStartBtn');
        $response->assertSee('出勤中');
        $response->assertDontSee('休憩中');
    }

    public function test_break_cycle_status_changes_correctly()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        $afterBreakStartResponse = $this->get('/attendance');
        $afterBreakStartResponse->assertStatus(200);
        $afterBreakStartResponse->assertSee('休憩中');
        $afterBreakStartResponse->assertSee('休憩戻');
        $afterBreakStartResponse->assertSee('breakEndBtn');

        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        $afterBreakEndResponse = $this->get('/attendance');
        $afterBreakEndResponse->assertStatus(200);
        $afterBreakEndResponse->assertSee('出勤中');
        $afterBreakEndResponse->assertSee('休憩入');
        $afterBreakEndResponse->assertDontSee('休憩中');
        $afterBreakEndResponse->assertDontSee('休憩戻');
    }

    public function test_break_return_button_displays_after_multiple_break_cycles()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now()->subHours(3),
            'status' => 'working',
        ]);

        $this->post('/attendance/break-start');
        $this->post('/attendance/break-end');

        $secondBreakStartResponse = $this->post('/attendance/break-start');
        $secondBreakStartResponse->assertStatus(200);
        $secondBreakStartResponse->assertJson(['success' => true]);

        $response = $this->get('/attendance');
        $response->assertStatus(200);

        $response->assertSee('休憩戻');
        $response->assertSee('breakEndBtn');
        $response->assertSee('休憩中');
        $response->assertDontSee('出勤中');
        $response->assertDontSee('休憩入');
    }

    public function test_break_time_is_recorded_in_attendance_list()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subHours(3),
            'status' => 'working',
        ]);

        $breakStartTime = now();
        $this->travelTo($breakStartTime);

        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        $breakEndTime = now()->addMinutes(30);
        $this->travelTo($breakEndTime);

        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        $response->assertSee('0:30');

        $break = $attendance->breaks()->first();
        $this->assertNotNull($break);
        $this->assertEquals($breakStartTime->format('Y-m-d H:i:s'), $break->start_time->format('Y-m-d H:i:s'));
        $this->assertEquals($breakEndTime->format('Y-m-d H:i:s'), $break->end_time->format('Y-m-d H:i:s'));

        $this->assertEquals(30, $break->start_time->diffInMinutes($break->end_time));

        $this->travelBack();
    }
}
 