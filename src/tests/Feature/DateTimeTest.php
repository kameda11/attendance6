<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_displays_current_datetime()
    {
        /** @var \App\Models\User $user */
        $user = \App\Models\User::factory()->create();
        $user = \App\Models\User::find($user->id);
        $this->actingAs($user);

        $currentDateTime = Carbon::now();
        $currentDate = $currentDateTime->format('Y年m月d日');
        $currentTime = $currentDateTime->format('H:i');
        $currentWeekday = $currentDateTime->format('D');

        $response = $this->get('/attendance');

        $response->assertStatus(200);
        $response->assertViewIs('attendance');

        $response->assertSee($currentDate);
        $response->assertSee($currentTime);
        $response->assertSee($currentWeekday);

        $fullDateDisplay = $currentDate . '(' . $currentWeekday . ')';
        $response->assertSee($fullDateDisplay);
    }
}
