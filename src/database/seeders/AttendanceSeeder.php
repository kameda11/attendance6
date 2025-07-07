<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Breaktime;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
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

        // 過去30日分の勤怠データを作成
        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);

            // 土日はスキップ（営業日のみ）
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($users as $user) {
                // 80%の確率で勤怠記録を作成
                if (rand(1, 100) <= 80) {
                    $this->createAttendanceRecord($user, $date);
                }
            }
        }
    }

    /**
     * 勤怠記録を作成
     */
    private function createAttendanceRecord($user, $date)
    {
        // 既に同じユーザーと日付の勤怠記録が存在するかチェック
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('clock_in_time', $date->toDateString())
            ->first();

        if ($existingAttendance) {
            return; // 既に存在する場合はスキップ
        }

        // 出勤時間（8:00-10:00の間でランダム）
        $clockInHour = rand(8, 10);
        $clockInMinute = rand(0, 59);
        $clockInTime = $date->copy()->setTime($clockInHour, $clockInMinute);

        // 退勤時間（17:00-20:00の間でランダム）
        $clockOutHour = rand(17, 20);
        $clockOutMinute = rand(0, 59);
        $clockOutTime = $date->copy()->setTime($clockOutHour, $clockOutMinute);

        // 勤怠記録を作成
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'clock_in_time' => $clockInTime,
            'clock_out_time' => $clockOutTime,
            'status' => 'completed',
            'notes' => $this->getRandomNotes(),
        ]);

        // 休憩時間を作成（1-2回の休憩）
        $breakCount = rand(1, 2);
        $this->createBreakTimes($attendance, $clockInTime, $clockOutTime, $breakCount);
    }

    /**
     * 休憩時間を作成
     */
    private function createBreakTimes($attendance, $clockInTime, $clockOutTime, $breakCount)
    {
        $workStartHour = $clockInTime->hour;
        $workEndHour = $clockOutTime->hour;

        for ($i = 0; $i < $breakCount; $i++) {
            // 休憩開始時間（出勤後1-3時間後）
            $breakStartHour = $workStartHour + rand(1, 3);
            $breakStartMinute = rand(0, 59);
            $breakStartTime = $clockInTime->copy()->setTime($breakStartHour, $breakStartMinute);

            // 休憩終了時間（開始から30分-1時間後）
            $breakDuration = rand(30, 60);
            $breakEndTime = $breakStartTime->copy()->addMinutes($breakDuration);

            // 勤務時間内に収まるように調整
            if ($breakEndTime->hour > $workEndHour) {
                $breakEndTime = $clockOutTime->copy()->subMinutes(rand(10, 30));
            }

            Breaktime::create([
                'attendance_id' => $attendance->id,
                'start_time' => $breakStartTime,
                'end_time' => $breakEndTime,
            ]);
        }
    }

    /**
     * ランダムな備考を取得
     */
    private function getRandomNotes()
    {
        $notes = [
            '通常勤務',
            '会議あり',
            '残業あり',
            '体調良好',
            'プロジェクト進行中',
            'クライアント対応',
            '資料作成',
            '研修参加',
            '営業活動',
            'システム開発',
        ];

        return $notes[array_rand($notes)];
    }
}
