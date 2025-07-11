<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use App\Models\AttendanceRequest;
use App\Models\BreakRequest;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ステータスが勤務外のユーザーにログインして勤怠打刻画面でのステータス確認までのテストメソッドを追加します。
     * 
     * テスト手順:
     * 1. ステータスが勤務外のユーザーにログインする
     * 2. 勤怠打刻画面を開く
     * 3. 画面に表示されているステータスを確認する
     * 
     * 期待挙動:
     * 画面上に表示されているステータスが「勤務外」となる
     */
    public function test_displays_not_working_status_when_no_attendance()
    {
        // 1. ステータスが勤務外のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 2. 勤怠打刻画面を開く
        $response = $this->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('勤務外');

        // 期待挙動: 画面上に表示されているステータスが「勤務外」となる
        $response->assertSeeText('勤務外');
    }

    /**
     * ステータスが出勤中のユーザーにログインして勤怠打刻画面でステータスを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中のユーザーにログインする
     * 2. 勤怠打刻画面を開く
     * 3. 画面に表示されているステータスを確認する
     * 
     * 期待挙動:
     * 画面上に表示されているステータスが「出勤中」となる
     */
    public function test_displays_working_status_when_user_is_working()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤記録を作成して出勤中ステータスにする
        $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'working',
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('出勤中');

        // 期待挙動: 画面上に表示されているステータスが「出勤中」となる
        $response->assertSeeText('出勤中');
    }

    /**
     * ステータスが休憩中のユーザーにログインして勤怠打刻画面でステータスを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが休憩中のユーザーにログインする
     * 2. 勤怠打刻画面を開く
     * 3. 画面に表示されているステータスを確認する
     * 
     * 期待挙動:
     * 画面上に表示されているステータスが「休憩中」となる
     */
    public function test_displays_break_status_when_user_is_on_break()
    {
        // 1. ステータスが休憩中のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 休憩記録を作成して休憩中ステータスにする
        $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'break',
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('休憩中');

        // 期待挙動: 画面上に表示されているステータスが「休憩中」となる
        $response->assertSeeText('休憩中');
    }

    /**
     * ステータスが退勤済のユーザーにログインして勤怠打刻画面でステータスを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが退勤済のユーザーにログインする
     * 2. 勤怠打刻画面を開く
     * 3. 画面に表示されているステータスを確認する
     * 
     * 期待挙動:
     * 画面上に表示されているステータスが「退勤済」となる
     */
    public function test_displays_completed_status_when_user_is_completed()
    {
        // 1. ステータスが退勤済のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 退勤記録を作成して退勤済ステータスにする
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(8),
            'clock_out_time' => now(),
            'status' => 'completed',
        ]);

        // 2. 勤怠打刻画面を開く
        $response = $this->get('/attendance');

        // 3. 画面に表示されているステータスを確認する
        $response->assertStatus(200);
        $response->assertSee('退勤済');

        // 期待挙動: 画面上に表示されているステータスが「退勤済」となる
        $response->assertSeeText('退勤済');
    }
}
