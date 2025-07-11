<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Tests\TestCase;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 休憩入機能のテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中のユーザーにログインする
     * 2. 画面に「休憩入」ボタンが表示されていることを確認する
     * 3. 休憩の処理を行う
     * 
     * 期待挙動:
     * 画面上に「休憩入」ボタンが表示され、処理後に画面上に表示されるステータスが「休憩中」になる
     */
    public function test_break_start_functionality()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        // 2. 画面に「休憩入」ボタンが表示されていることを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('休憩入');
        $response->assertSee('breakStartBtn');

        // 3. 休憩の処理を行う
        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        // 期待挙動: 処理後に画面上に表示されるステータスが「休憩中」になる
        $afterBreakStartResponse = $this->get('/attendance');
        $afterBreakStartResponse->assertStatus(200);
        $afterBreakStartResponse->assertSee('休憩中');
        $afterBreakStartResponse->assertSee('休憩戻');
    }

    /**
     * 休憩入と休憩戻の処理後に「休憩入」ボタンが再表示されることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中のユーザーにログインする
     * 2. 休憩入と休憩戻の処理を行う
     * 3. 「休憩入」ボタンが表示されることを確認する
     * 
     * 期待挙動:
     * 画面上に「休憩入」ボタンが表示される
     */
    public function test_break_in_button_reappears_after_break_cycle()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        // 2. 休憩入と休憩戻の処理を行う
        // 休憩入処理
        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        // 休憩戻処理
        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        // 3. 「休憩入」ボタンが表示されることを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);

        // 期待挙動: 画面上に「休憩入」ボタンが表示される
        $response->assertSee('休憩入');
        $response->assertSee('breakStartBtn');
        $response->assertSee('出勤中');
        $response->assertDontSee('休憩中');
    }

    /**
     * 休憩入と休憩戻の処理でステータスが正しく変更されることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中であるユーザーにログインする
     * 2. 休憩入の処理を行う
     * 3. 休憩戻の処理を行う
     * 
     * 期待挙動:
     * 休憩戻ボタンが表示され、処理後にステータスが「出勤中」に変更される
     */
    public function test_break_cycle_status_changes_correctly()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(2),
            'status' => 'working',
        ]);

        // 2. 休憩入の処理を行う
        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        // 休憩入後の状態確認
        $afterBreakStartResponse = $this->get('/attendance');
        $afterBreakStartResponse->assertStatus(200);
        $afterBreakStartResponse->assertSee('休憩中');
        $afterBreakStartResponse->assertSee('休憩戻');
        $afterBreakStartResponse->assertSee('breakEndBtn');

        // 3. 休憩戻の処理を行う
        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        // 期待挙動: 処理後にステータスが「出勤中」に変更される
        $afterBreakEndResponse = $this->get('/attendance');
        $afterBreakEndResponse->assertStatus(200);
        $afterBreakEndResponse->assertSee('出勤中');
        $afterBreakEndResponse->assertSee('休憩入');
        $afterBreakEndResponse->assertDontSee('休憩中');
        $afterBreakEndResponse->assertDontSee('休憩戻');
    }

    /**
     * 複数回の休憩サイクルで「休憩戻」ボタンが正しく表示されることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中であるユーザーにログインする
     * 2. 休憩入と休憩戻の処理を行い、再度休憩入りの処理を行う
     * 3. 「休憩戻」ボタンが表示されることを確認する
     * 
     * 期待挙動:
     * 画面上に「休憩戻」ボタンが表示される
     */
    public function test_break_return_button_displays_after_multiple_break_cycles()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $user->attendances()->create([
            'clock_in_time' => now()->subHours(3),
            'status' => 'working',
        ]);

        // 2. 休憩入と休憩戻の処理を行い、再度休憩入りの処理を行う
        // 1回目の休憩サイクル
        $this->post('/attendance/break-start');
        $this->post('/attendance/break-end');

        // 2回目の休憩入り（再度休憩入りの処理）
        $secondBreakStartResponse = $this->post('/attendance/break-start');
        $secondBreakStartResponse->assertStatus(200);
        $secondBreakStartResponse->assertJson(['success' => true]);

        // 3. 「休憩戻」ボタンが表示されることを確認する
        $response = $this->get('/attendance');
        $response->assertStatus(200);

        // 期待挙動: 画面上に「休憩戻」ボタンが表示される
        $response->assertSee('休憩戻');
        $response->assertSee('breakEndBtn');
        $response->assertSee('休憩中');
        $response->assertDontSee('出勤中');
        $response->assertDontSee('休憩入');
    }

    /**
     * 勤怠一覧画面で休憩時刻が正確に記録されていることを確認するテスト
     * 
     * テスト手順:
     * 1. ステータスが出勤中であるユーザーにログインする
     * 2. 休憩入と休憩戻の処理を行う
     * 3. 勤怠一覧画面から休憩の日付を確認する
     * 
     * 期待挙動:
     * 勤怠一覧画面に休憩時刻が正確に記録されている
     */
    public function test_break_time_is_recorded_in_attendance_list()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        /** @var User $user */
        $user = User::factory()->create();
        $user = User::find($user->id);
        $this->actingAs($user);

        // 出勤中の勤怠記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now()->subHours(3),
            'status' => 'working',
        ]);

        // 2. 休憩入と休憩戻の処理を行う
        $breakStartTime = now();
        $this->travelTo($breakStartTime);

        // 休憩入処理
        $breakStartResponse = $this->post('/attendance/break-start');
        $breakStartResponse->assertStatus(200);
        $breakStartResponse->assertJson(['success' => true]);

        $breakEndTime = now()->addMinutes(30);
        $this->travelTo($breakEndTime);

        // 休憩戻処理
        $breakEndResponse = $this->post('/attendance/break-end');
        $breakEndResponse->assertStatus(200);
        $breakEndResponse->assertJson(['success' => true]);

        // 3. 勤怠一覧画面から休憩の日付を確認する
        $response = $this->get('/attendance/list');
        $response->assertStatus(200);

        // 期待挙動: 勤怠一覧画面に休憩時刻が正確に記録されている
        // 休憩時間は合計時間として表示される（例：0:30）
        $response->assertSee('0:30');

        // データベースにも正確に記録されていることを確認
        $break = $attendance->breaks()->first();
        $this->assertNotNull($break);
        $this->assertEquals($breakStartTime->format('Y-m-d H:i:s'), $break->start_time->format('Y-m-d H:i:s'));
        $this->assertEquals($breakEndTime->format('Y-m-d H:i:s'), $break->end_time->format('Y-m-d H:i:s'));

        // 休憩時間が30分であることを確認
        $this->assertEquals(30, $break->start_time->diffInMinutes($break->end_time));

        // 時間を元に戻す
        $this->travelBack();
    }
}
