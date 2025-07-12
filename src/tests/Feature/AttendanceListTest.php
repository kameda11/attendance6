<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\AttendanceRequest;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠一覧機能のテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠一覧ページで開く
     * 3. 自分の勤怠情報がすべて表示されていることを確認する
     * 
     * 期待挙動:
     * 自分の勤怠情報がすべて表示されている
     */
    public function test_user_can_view_all_their_attendance_records()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 複数の勤怠記録を作成
        $attendance1 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(5)->setTime(9, 0),
            'clock_out_time' => now()->subDays(5)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => now()->subDays(5),
        ]);

        $attendance2 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(3)->setTime(8, 30),
            'clock_out_time' => now()->subDays(3)->setTime(17, 30),
            'status' => 'completed',
            'created_at' => now()->subDays(3),
        ]);

        $attendance3 = $user->attendances()->create([
            'clock_in_time' => now()->subDays(1)->setTime(9, 15),
            'clock_out_time' => now()->subDays(1)->setTime(18, 15),
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // 2. 勤怠一覧ページで開く
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 3. 自分の勤怠情報がすべて表示されていることを確認する
        // 期待挙動: 自分の勤怠情報がすべて表示されている
        $response->assertSee('09:00'); // 1つ目の勤怠記録の出勤時刻
        $response->assertSee('18:00'); // 1つ目の勤怠記録の退勤時刻

        $response->assertSee('08:30'); // 2つ目の勤怠記録の出勤時刻
        $response->assertSee('17:30'); // 2つ目の勤怠記録の退勤時刻

        $response->assertSee('09:15'); // 3つ目の勤怠記録の出勤時刻
        $response->assertSee('18:15'); // 3つ目の勤怠記録の退勤時刻

        // 勤怠一覧画面の基本要素が表示されていることを確認
        $response->assertSee('勤怠一覧');
        $response->assertSee('日付');
        $response->assertSee('出勤');
        $response->assertSee('退勤');
        $response->assertSee('休憩');
        $response->assertSee('合計');
        $response->assertSee('詳細');
    }

    /**
     * 現在の月が表示されることを確認するテスト
     * 
     * テスト手順:
     * 1. ユーザーにログインする
     * 2. 勤怠一覧ページを開く
     * 
     * 期待挙動:
     * 現在の月が表示されている
     */
    public function test_current_month_is_displayed()
    {
        // 1. ユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 期待挙動: 現在の月が表示されている
        $currentMonth = now()->format('Y/m');
        $response->assertSee($currentMonth);
    }

    /**
     * 前月ボタンの動作を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠一覧ページで開く
     * 3. 「前月」ボタンを押す
     * 
     * 期待挙動:
     * 前月の情報が表示されている
     */
    public function test_previous_month_button_works_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 前月の勤怠記録を作成
        $prevMonth = now()->subMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $prevMonth->copy()->setTime(9, 0),
            'clock_out_time' => $prevMonth->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $prevMonth,
        ]);

        // 2. 勤怠一覧ページで開く
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 3. 「前月」ボタンを押す
        $prevMonthYear = $prevMonth->year;
        $prevMonthNumber = $prevMonth->month;
        $response = $this->get("/attendance/list?year={$prevMonthYear}&month={$prevMonthNumber}");
        $response->assertStatus(200);

        // 期待挙動: 前月の情報が表示されている
        $prevMonthDisplay = $prevMonth->format('Y/m');
        $response->assertSee($prevMonthDisplay);

        // 前月の勤怠データが表示されていることを確認
        $response->assertSee('09:00'); // 前月の勤怠記録の出勤時刻
        $response->assertSee('18:00'); // 前月の勤怠記録の退勤時刻
    }

    /**
     * 翌月ボタンの動作を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠一覧ページで開く
     * 3. 「翌月」ボタンを押す
     * 
     * 期待挙動:
     * 翌月の情報が表示されている
     */
    public function test_next_month_button_works_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 次月の勤怠記録を作成
        $nextMonth = now()->addMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $nextMonth->copy()->setTime(9, 0),
            'clock_out_time' => $nextMonth->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $nextMonth,
        ]);

        // 2. 勤怠一覧ページで開く
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 3. 「翌月」ボタンを押す
        $nextMonthYear = $nextMonth->year;
        $nextMonthNumber = $nextMonth->month;
        $response = $this->get("/attendance/list?year={$nextMonthYear}&month={$nextMonthNumber}");
        $response->assertStatus(200);

        // 期待挙動: 翌月の情報が表示されている
        $nextMonthDisplay = $nextMonth->format('Y/m');
        $response->assertSee($nextMonthDisplay);

        // 翌月の勤怠データが表示されていることを確認
        $response->assertSee('09:00'); // 翌月の勤怠記録の出勤時刻
        $response->assertSee('18:00'); // 翌月の勤怠記録の退勤時刻
    }

    /**
     * 詳細ボタンの動作を確認するテスト
     * 
     * テスト手順:
     * 1. 勤怠情報が登録されたユーザーにログインする
     * 2. 勤怠一覧ページで開く
     * 3. 「詳細」ボタンを押す
     * 
     * 期待挙動:
     * その日の勤怠詳細画面に遷移する
     */
    public function test_detail_button_works_correctly()
    {
        // 1. 勤怠情報が登録されたユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠記録を作成
        $attendanceDate = now()->subDays(5);
        $attendance = $user->attendances()->create([
            'clock_in_time' => $attendanceDate->copy()->setTime(9, 0),
            'clock_out_time' => $attendanceDate->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $attendanceDate,
        ]);

        // 2. 勤怠一覧ページで開く
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 3. 「詳細」ボタンを押す
        $detailUrl = "/attendance/detail/{$attendance->id}";
        $response = $this->get($detailUrl);
        $response->assertStatus(200);

        // 期待挙動: その日の勤怠詳細画面に遷移する
        $response->assertSee('09:00'); // 勤怠詳細画面に出勤時刻が表示される
        $response->assertSee('18:00'); // 勤怠詳細画面に退勤時刻が表示される
    }
}
