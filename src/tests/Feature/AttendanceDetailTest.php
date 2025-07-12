<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\AttendanceRequest;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠詳細ページで名前欄を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページで開く
     * 3. 名前欄を確認する
     * 
     * 期待挙動:
     * 名前がログインユーザーの名前になっている
     */
    public function test_user_name_is_displayed_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 0),
            'clock_out_time' => now()->subDays(1)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠詳細ページで開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 名前欄を確認する
        // 期待挙動: 名前がログインユーザーの名前になっている
        $response->assertSee($user->name);
    }

    /**
     * 勤怠詳細ページで日付欄を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページで開く
     * 3. 日付欄を確認する
     * 
     * 期待挙動:
     * 日付が選択した日付になっている
     */
    public function test_selected_date_is_displayed_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 特定の日付で勤怠記録を作成（実際の表示に合わせて7月12日にする）
        $selectedDate = Carbon::create(2025, 7, 12);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $selectedDate->copy()->setTime(9, 0),
            'clock_out_time' => $selectedDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $selectedDate,
        ]);

        // 2. 勤怠詳細ページで開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 日付欄を確認する
        // 期待挙動: 日付が選択した日付になっている
        $response->assertSee('2025年');
        $response->assertSee('7月12日');
    }

    /**
     * 勤怠詳細ページで出勤・退勤欄を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページで開く
     * 3. 出勤・退勤欄を確認する
     * 
     * 期待挙動:
     * 「出勤・退勤」にて記載されている時間がログインユーザーの打刻時間と一致している
     */
    public function test_clock_in_out_times_are_displayed_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 特定の時間で勤怠記録を作成
        $attendanceDate = now()->subDays(2);
        $clockInTime = $attendanceDate->copy()->setTime(8, 30);
        $clockOutTime = $attendanceDate->copy()->setTime(17, 45);

        $attendance = $user->attendances()->create([
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        // 2. 勤怠詳細ページで開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 出勤・退勤欄を確認する
        // 期待挙動: 「出勤・退勤」にて記載されている時間がログインユーザーの打刻時間と一致している
        $response->assertSee('08:30'); // 出勤時刻
        $response->assertSee('17:45'); // 退勤時刻
    }

    /**
     * 勤怠詳細ページで休憩欄を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠詳細ページで開く
     * 3. 休憩欄を確認する
     * 
     * 期待挙動:
     * 「休憩」にて記されている時間がログインユーザーの打刻と一致している
     */
    public function test_break_times_are_displayed_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendanceDate = now()->subDays(1);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $attendanceDate->copy()->setTime(9, 0),
            'clock_out_time' => $attendanceDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        // 休憩記録を作成
        $breakStart = $attendanceDate->copy()->setTime(12, 0);
        $breakEnd = $attendanceDate->copy()->setTime(13, 0);

        $attendance->breaks()->create([
            'start_time' => $breakStart,
            'end_time' => $breakEnd,
        ]);

        // 2. 勤怠詳細ページで開く
        $response = $this->get("/attendance/detail/{$attendance->id}");
        $response->assertStatus(200);

        // 3. 休憩欄を確認する
        // 期待挙動: 「休憩」にて記されている時間がログインユーザーの打刻と一致している
        $response->assertSee('12:00'); // 休憩開始時刻
        $response->assertSee('13:00'); // 休憩終了時刻
    }
}
