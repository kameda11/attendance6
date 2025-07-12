<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;


    public function test_displays_not_working_status_when_no_attendance()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');

        $response->assertSeeText('勤務外');
    }


    public function test_displays_working_status_when_user_is_working()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'working',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
        $response->assertSeeText('出勤中');
    }


    public function test_displays_break_status_when_user_is_on_break()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'break',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');

        $response->assertSeeText('休憩中');
    }


    public function test_displays_completed_status_when_user_is_completed()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        $user->attendances()->create([
            'clock_in_time' => now()->subHours(8),
            'clock_out_time' => now(),
            'status' => 'completed',
        ]);

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
        $response->assertSeeText('退勤済');
    }
}
