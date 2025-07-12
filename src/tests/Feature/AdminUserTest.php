<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 管理者がスタッフ一覧ページで全ユーザーの氏名とメールアドレスを確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者でログインする
     * 2. スタッフ一覧ページを開く
     * 
     * 期待挙動:
     * 全ての一般ユーザーの氏名とメールアドレスが正しく表示されている
     */
    public function test_admin_can_view_all_users_name_and_email()
    {
        // テスト用の管理者と複数のユーザーを作成
        $admin = Admin::factory()->create();
        $user1 = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);
        $user2 = User::factory()->create([
            'name' => '佐藤花子',
            'email' => 'sato@example.com'
        ]);
        $user3 = User::factory()->create([
            'name' => '鈴木一郎',
            'email' => 'suzuki@example.com'
        ]);

        // 1. 管理者でログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. スタッフ一覧ページを開く
        $response = $this->get('/admin/users');
        $response->assertStatus(200);

        // 期待挙動: 全ての一般ユーザーの氏名とメールアドレスが正しく表示されている
        $response->assertSee('田中太郎');
        $response->assertSee('tanaka@example.com');
        $response->assertSee('佐藤花子');
        $response->assertSee('sato@example.com');
        $response->assertSee('鈴木一郎');
        $response->assertSee('suzuki@example.com');
    }

    /**
     * 管理者が選択したユーザーの勤怠一覧ページで勤怠情報を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 選択したユーザーの勤怠一覧ページを開く
     * 
     * 期待挙動:
     * 勤怠情報が正確に表示される
     */
    public function test_admin_can_view_user_attendance_list()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 現在月の勤怠データを作成
        $currentMonth = now();
        $attendance1 = $user->attendances()->create([
            'clock_in_time' => $currentMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $currentMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $currentMonth->copy()->setDay(15),
        ]);

        $attendance2 = $user->attendances()->create([
            'clock_in_time' => $currentMonth->copy()->setDay(16)->setTime(8, 30),
            'clock_out_time' => $currentMonth->copy()->setDay(16)->setTime(17, 30),
            'status' => 'completed',
            'created_at' => $currentMonth->copy()->setDay(16),
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 選択したユーザーの勤怠一覧ページを開く
        $response = $this->get('/admin/user/' . $user->id . '/attendances');
        $response->assertStatus(200);

        // 期待挙動: 勤怠情報が正確に表示される
        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /**
     * 管理者が勤怠一覧ページで前月ボタンを押して前月の情報を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 勤怠一覧ページを開く
     * 3. 「前月」ボタンを押す
     * 
     * 期待挙動:
     * 前月の情報が表示されている
     */
    public function test_admin_can_view_previous_month_attendance()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 前月の勤怠データを作成
        $previousMonth = now()->subMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $previousMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $previousMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $previousMonth->copy()->setDay(15),
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 3. 「前月」ボタンを押す（日別勤怠一覧では前日ボタンになる）
        $previousDate = now()->subDay();
        $response = $this->get("/admin/attendances?date={$previousDate->format('Y-m-d')}");
        $response->assertStatus(200);

        // 期待挙動: 前日の情報が表示されている
        $response->assertSee($previousDate->format('Y年m月d日'));
        $response->assertSee('田中太郎');
    }

    /**
     * 管理者が勤怠一覧ページで翌月ボタンを押して翌月の情報を確認できるテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 勤怠一覧ページを開く
     * 3. 「翌月」ボタンを押す
     * 
     * 期待挙動:
     * 翌月の情報が表示されている
     */
    public function test_admin_can_view_next_month_attendance()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 翌月の勤怠データを作成
        $nextMonth = now()->addMonth();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $nextMonth->copy()->setDay(15)->setTime(9, 0),
            'clock_out_time' => $nextMonth->copy()->setDay(15)->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $nextMonth->copy()->setDay(15),
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 3. 「翌月」ボタンを押す（日別勤怠一覧では翌日ボタンになる）
        $nextDate = now()->addDay();
        $response = $this->get("/admin/attendances?date={$nextDate->format('Y-m-d')}");
        $response->assertStatus(200);

        // 期待挙動: 翌日の情報が表示されている
        $response->assertSee($nextDate->format('Y年m月d日'));
        $response->assertSee('田中太郎');
    }

    /**
     * 管理者が勤怠一覧ページで詳細ボタンを押して勤怠詳細画面に遷移するテスト
     * 
     * テスト手順:
     * 1. 管理者ユーザーでログインする
     * 2. 勤怠一覧ページを開く
     * 3. 「詳細」ボタンを押す
     * 
     * 期待挙動:
     * その日の勤怠詳細画面に遷移する
     */
    public function test_admin_can_navigate_to_attendance_detail()
    {
        // テスト用の管理者とユーザーを作成
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'name' => '田中太郎',
            'email' => 'tanaka@example.com'
        ]);

        // 今日の勤怠データを作成
        $today = now();
        $attendance = $user->attendances()->create([
            'clock_in_time' => $today->copy()->setTime(9, 0),
            'clock_out_time' => $today->copy()->setTime(18, 0),
            'status' => 'completed',
            'created_at' => $today,
        ]);

        // 1. 管理者ユーザーでログインする
        $this->session(['admin_logged_in' => true, 'admin_email' => $admin->email]);

        // 2. 勤怠一覧ページを開く
        $response = $this->get('/admin/attendances');
        $response->assertStatus(200);

        // 3. 「詳細」ボタンを押す
        $response = $this->get('/admin/attendance/' . $attendance->id);
        $response->assertStatus(200);

        // 期待挙動: その日の勤怠詳細画面に遷移する
        $response->assertSee('勤怠詳細');
        $response->assertSee('田中太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }
}
