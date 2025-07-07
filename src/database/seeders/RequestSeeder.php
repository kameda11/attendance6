<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceRequest;
use App\Models\BreakRequest;
use Carbon\Carbon;

class RequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->info('ユーザーが存在しません。先にUserSeederを実行してください。');
            return;
        }

        // 過去7日分の申請データを作成
        for ($i = 7; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);

            // 土日はスキップ（営業日のみ）
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($users as $user) {
                // 20%の確率で申請を作成
                if (rand(1, 100) <= 20) {
                    $this->createRequestData($user, $date);
                }
            }
        }
    }

    /**
     * 申請データを作成
     */
    private function createRequestData($user, $date)
    {
        // 既存の勤怠記録を取得
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->first();

        // 勤怠申請を作成（70%の確率で承認済み、30%の確率で申請中）
        $status = rand(1, 100) <= 70 ? 'approved' : 'pending';

        if ($existingAttendance) {
            // 修正申請
            $this->createUpdateRequest($user, $existingAttendance, $date, $status);
        } else {
            // 新規作成申請
            $this->createCreateRequest($user, $date, $status);
        }

        // 休憩申請を作成（既存の勤怠がある場合のみ）
        if ($existingAttendance && rand(1, 100) <= 30) {
            $this->createBreakRequest($user, $existingAttendance, $date, $status);
        }
    }

    /**
     * 修正申請を作成
     */
    private function createUpdateRequest($user, $attendance, $date, $status)
    {
        // 元の時間を少し変更
        $originalClockIn = Carbon::parse($attendance->clock_in_time);
        $originalClockOut = Carbon::parse($attendance->clock_out_time);

        $newClockIn = $originalClockIn->copy()->addMinutes(rand(-30, 30));
        $newClockOut = $originalClockOut->copy()->addMinutes(rand(-30, 30));

        AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $date->format('Y-m-d'),
            'request_type' => 'update',
            'status' => $status,
            'clock_in_time' => $newClockIn,
            'clock_out_time' => $newClockOut,
            'notes' => $this->getRandomRequestNotes(),
        ]);
    }

    /**
     * 新規作成申請を作成
     */
    private function createCreateRequest($user, $date, $status)
    {
        // 出勤時間（8:00-10:00の間でランダム）
        $clockInHour = rand(8, 10);
        $clockInMinute = rand(0, 59);
        $clockInTime = $date->copy()->setTime($clockInHour, $clockInMinute);

        // 退勤時間（17:00-20:00の間でランダム）
        $clockOutHour = rand(17, 20);
        $clockOutMinute = rand(0, 59);
        $clockOutTime = $date->copy()->setTime($clockOutHour, $clockOutMinute);

        // 休憩情報を作成
        $breakInfo = [];
        $breakCount = rand(1, 2);

        for ($i = 0; $i < $breakCount; $i++) {
            $breakStartHour = $clockInHour + rand(1, 3);
            $breakStartMinute = rand(0, 59);
            $breakStartTime = sprintf('%02d:%02d', $breakStartHour, $breakStartMinute);

            $breakEndHour = $breakStartHour + rand(0, 1);
            $breakEndMinute = rand(0, 59);
            $breakEndTime = sprintf('%02d:%02d', $breakEndHour, $breakEndMinute);

            $breakInfo[] = [
                'start_time' => $breakStartTime,
                'end_time' => $breakEndTime,
            ];
        }

        AttendanceRequest::create([
            'user_id' => $user->id,
            'attendance_id' => null,
            'target_date' => $date->format('Y-m-d'),
            'request_type' => 'create',
            'status' => $status,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'break_info' => $breakInfo,
            'notes' => $this->getRandomRequestNotes(),
        ]);
    }

    /**
     * 休憩申請を作成
     */
    private function createBreakRequest($user, $attendance, $date, $status)
    {
        // 既存の休憩を取得
        $existingBreaks = $attendance->breaks;

        if ($existingBreaks->isNotEmpty()) {
            $break = $existingBreaks->first();

            // 修正申請
            BreakRequest::create([
                'user_id' => $user->id,
                'break_id' => $break->id,
                'attendance_id' => $attendance->id,
                'target_date' => $date->format('Y-m-d'),
                'request_type' => 'update',
                'status' => $status,
                'start_time' => Carbon::parse($break->start_time)->addMinutes(rand(-15, 15)),
                'end_time' => Carbon::parse($break->end_time)->addMinutes(rand(-15, 15)),
            ]);
        } else {
            // 新規作成申請
            $breakStartHour = rand(11, 14);
            $breakStartMinute = rand(0, 59);
            $breakStartTime = sprintf('%02d:%02d', $breakStartHour, $breakStartMinute);

            $breakEndHour = $breakStartHour + rand(0, 1);
            $breakEndMinute = rand(0, 59);
            $breakEndTime = sprintf('%02d:%02d', $breakEndHour, $breakEndMinute);

            BreakRequest::create([
                'user_id' => $user->id,
                'break_id' => null,
                'attendance_id' => $attendance->id,
                'target_date' => $date->format('Y-m-d'),
                'request_type' => 'create',
                'status' => $status,
                'start_time' => $breakStartTime,
                'end_time' => $breakEndTime,
            ]);
        }
    }

    /**
     * ランダムな申請備考を取得
     */
    private function getRandomRequestNotes()
    {
        $notes = [
            '遅刻のため修正申請',
            '早退のため修正申請',
            '休憩時間の修正',
            '勤務時間の訂正',
            '忘れていた勤怠記録',
            'システムエラーのため手動入力',
            '外出のため修正',
            '会議参加のため修正',
            '体調不良のため早退',
            '残業のため修正',
            '研修参加のため修正',
            'クライアント訪問のため修正',
        ];

        return $notes[array_rand($notes)];
    }
}
