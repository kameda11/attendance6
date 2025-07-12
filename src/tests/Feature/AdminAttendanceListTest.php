<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\AttendanceRequest;
use App\Models\Admin;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者が勤怠一覧画面でその日の全ユーザーの勤怠情報が正確な値で表示されるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠一覧画面を開く
     * 
     * 期待挙動:
     * その日の全ユーザーの勤怠情報が正確な値になっている
     */
    public function test_admin_can_view_accurate_daily_attendance_list()
    {
        // 1. 管理者ユーザーにログインする
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // テスト用ユーザーを作成
        $user1 = User::factory()->create(['name' => '田中太郎']);
        $user2 = User::factory()->create(['name' => '佐藤花子']);
        $user3 = User::factory()->create(['name' => '鈴木一郎']);

        // 指定日の勤怠データを作成
        $targetDate = now()->format('Y-m-d');

        // ユーザー1の勤怠データ（完全な勤怠記録）
        $attendance1 = $user1->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 09:00:00'),
            'clock_out_time' => Carbon::parse($targetDate . ' 18:00:00'),
            'status' => 'completed',
            'created_at' => Carbon::parse($targetDate),
        ]);

        // ユーザー1の休憩データ（1時間の休憩）
        $attendance1->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 12:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 13:00:00'),
        ]);

        // ユーザー2の勤怠データ（退勤時間なし）
        $attendance2 = $user2->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 08:30:00'),
            'status' => 'working',
            'created_at' => Carbon::parse($targetDate),
        ]);

        // ユーザー3の勤怠データ（複数休憩）
        $attendance3 = $user3->attendances()->create([
            'clock_in_time' => Carbon::parse($targetDate . ' 08:00:00'),
            'clock_out_time' => Carbon::parse($targetDate . ' 17:00:00'),
            'status' => 'completed',
            'created_at' => Carbon::parse($targetDate),
        ]);

        // ユーザー3の休憩データ（複数休憩）
        $attendance3->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 10:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 10:15:00'),
        ]);
        $attendance3->breaks()->create([
            'start_time' => Carbon::parse($targetDate . ' 12:00:00'),
            'end_time' => Carbon::parse($targetDate . ' 13:00:00'),
        ]);

        // 2. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendances?date=' . $targetDate);
        $response->assertStatus(200);

        // 期待挙動: その日の全ユーザーの勤怠情報が正確な値になっている

        // ユーザー1の勤怠情報確認
        $response->assertSee('田中太郎');
        $response->assertSee('09:00'); // 出勤時間
        $response->assertSee('18:00'); // 退勤時間
        $response->assertSee('01:00'); // 休憩時間（1時間）
        $response->assertSee('08:00'); // 勤務時間（9時間-1時間休憩=8時間）

        // ユーザー2の勤怠情報確認
        $response->assertSee('佐藤花子');
        $response->assertSee('08:30'); // 出勤時間
        // 退勤時間は空（退勤していないため）

        // ユーザー3の勤怠情報確認
        $response->assertSee('鈴木一郎');
        $response->assertSee('08:00'); // 出勤時間
        $response->assertSee('17:00'); // 退勤時間
        $response->assertSee('01:15'); // 休憩時間（15分+1時間=1時間15分）
        $response->assertSee('07:45'); // 勤務時間（9時間-1時間15分休憩=7時間45分）

        // 全ユーザーが表示されていることを確認
        $response->assertSee('田中太郎');
        $response->assertSee('佐藤花子');
        $response->assertSee('鈴木一郎');
    }

    /**
     * 管理者が勤怠一覧画面でその日の日付が表示されるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠一覧画面を開く
     * 
     * 期待挙動:
     * 勤怠一覧画面にその日の日付が表示されている
     */
    public function test_admin_can_view_current_date_in_attendance_list()
    {
        // 1. 管理者ユーザーにログインする
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // テスト用ユーザーを作成
        $user = User::factory()->create(['name' => '田中太郎']);

        // 今日の勤怠データを作成
        $today = now();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $today->copy()->setTime(9, 0),
            'clock_out_time' => $today->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $today,
        ]);

        // 2. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 期待挙動: 勤怠一覧画面にその日の日付が表示されている

        // 日付の表示形式を確認（Y年m月d日形式）
        $response->assertSee($today->format('Y年m月d日') . 'の勤怠');

        // 日付セレクターの値も確認
        $response->assertSee($today->format('Y-m-d'));

        // ナビゲーション部分の日付表示も確認
        $response->assertSee($today->format('Y/m/d'));
    }

    /**
     * 管理者が勤怠一覧画面で「前日」ボタンを押して前日の勤怠情報が表示されるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠一覧画面を開く
     * 3. 「前日」ボタンを押す
     * 
     * 期待挙動:
     * 前日の日付の勤怠情報が表示される
     */
    public function test_admin_can_view_previous_day_attendance_list()
    {
        // 1. 管理者ユーザーにログインする
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // テスト用ユーザーを作成
        $user = User::factory()->create(['name' => '田中太郎']);

        // 前日の勤怠データを作成
        $yesterday = now()->subDay();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $yesterday->copy()->setTime(9, 0),
            'clock_out_time' => $yesterday->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $yesterday,
        ]);

        // 前日の休憩データを作成
        $attendance->breaks()->create([
            'start_time' => $yesterday->copy()->setTime(12, 0),
            'end_time' => $yesterday->copy()->setTime(13, 0),
        ]);

        // 2. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 3. 「前日」ボタンを押す
        $response = $this->get('/admin/attendances?date=' . $yesterday->format('Y-m-d'));
        $response->assertStatus(200);

        // 期待挙動: 前日の日付の勤怠情報が表示される

        // 前日の日付が表示されていることを確認
        $response->assertSee($yesterday->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($yesterday->format('Y-m-d'));
        $response->assertSee($yesterday->format('Y/m/d'));

        // 前日の勤怠情報が表示されていることを確認
        $response->assertSee('田中太郎');
        $response->assertSee('09:00'); // 出勤時間
        $response->assertSee('18:00'); // 退勤時間
        $response->assertSee('01:00'); // 休憩時間
        $response->assertSee('08:00'); // 勤務時間
    }

    /**
     * 管理者が勤怠一覧画面で「翌日」ボタンを押して翌日の勤怠情報が表示されるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーにログインする
     * 2. 勤怠一覧画面を開く
     * 3. 「翌日」ボタンを押す
     * 
     * 期待挙動:
     * 翌日の日付の勤怠情報が表示される
     */
    public function test_admin_can_view_next_day_attendance_list()
    {
        // 1. 管理者ユーザーにログインする
        /** @var Admin $admin */
        $admin = Admin::factory()->create();
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // テスト用ユーザーを作成
        $user = User::factory()->create(['name' => '田中太郎']);

        // 翌日の勤怠データを作成
        $tomorrow = now()->addDay();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $tomorrow->copy()->setTime(8, 30),
            'clock_out_time' => $tomorrow->copy()->setTime(17, 30),
            'status' => 'completed',
            'created_at' => $tomorrow,
        ]);

        // 翌日の休憩データを作成
        $attendance->breaks()->create([
            'start_time' => $tomorrow->copy()->setTime(11, 30),
            'end_time' => $tomorrow->copy()->setTime(12, 30),
        ]);

        // 2. 勤怠一覧画面を開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 3. 「翌日」ボタンを押す
        $response = $this->get('/admin/attendances?date=' . $tomorrow->format('Y-m-d'));
        $response->assertStatus(200);

        // 期待挙動: 翌日の日付の勤怠情報が表示される

        // 翌日の日付が表示されていることを確認
        $response->assertSee($tomorrow->format('Y年m月d日') . 'の勤怠');
        $response->assertSee($tomorrow->format('Y-m-d'));
        $response->assertSee($tomorrow->format('Y/m/d'));

        // 翌日の勤怠情報が表示されていることを確認
        $response->assertSee('田中太郎');
        $response->assertSee('08:30'); // 出勤時間
        $response->assertSee('17:30'); // 退勤時間
        $response->assertSee('01:00'); // 休憩時間
        $response->assertSee('08:00'); // 勤務時間（9時間-1時間休憩）
    }
}
