<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakRequest;
use App\Models\AttendanceRequest as AttendanceRequestModel;
use App\Http\Requests\AttendanceFormRequest;

class UserController extends Controller
{
    /**
     * 勤務管理ページを表示
     */
    public function attendance()
    {
        /** @var User $user */
        $user = Auth::user();
        $todayAttendance = $user->attendances()
            ->with('breaks')
            ->whereDate('created_at', today())
            ->first();

        $recentAttendances = $user->attendances()
            ->with('breaks')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('attendance', compact('user', 'todayAttendance', 'recentAttendances'));
    }

    /**
     * 出勤記録を作成
     */
    public function clockIn(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 今日の出勤記録を取得
        $existingAttendance = $user->attendances()
            ->whereDate('created_at', today())
            ->first();

        // 今日の記録が既に存在する場合はエラー
        if ($existingAttendance) {
            return response()->json([
                'success' => false
            ]);
        }

        // 出勤記録を作成
        $attendance = $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'working',
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance
        ]);
    }

    /**
     * 退勤記録を更新
     */
    public function clockOut(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 今日の最新の出勤記録を取得
        $attendance = $user->attendances()
            ->whereDate('created_at', today())
            ->where('status', '!=', 'completed')
            ->latest()
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false
            ]);
        }

        $attendance->update([
            'clock_out_time' => now(),
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance
        ]);
    }

    /**
     * 休憩開始記録
     */
    public function breakStart(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 今日の最新の勤務記録を取得（退勤済みでないもの）
        $attendance = $user->attendances()
            ->whereDate('created_at', today())
            ->where('status', '!=', 'completed')
            ->latest()
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false
            ]);
        }

        if ($attendance->status !== 'working') {
            return response()->json([
                'success' => false
            ]);
        }

        // 新しい休憩記録を作成
        $break = $attendance->breaks()->create([
            'start_time' => now(),
        ]);

        $attendance->update([
            'status' => 'break',
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance,
            'break' => $break
        ]);
    }

    /**
     * 休憩終了記録
     */
    public function breakEnd(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 今日の最新の勤務記録を取得（退勤済みでないもの）
        $attendance = $user->attendances()
            ->with('breaks')
            ->whereDate('created_at', today())
            ->where('status', '!=', 'completed')
            ->latest()
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false
            ]);
        }

        if ($attendance->status !== 'break') {
            return response()->json([
                'success' => false
            ]);
        }

        // 最新の未終了の休憩を取得
        $activeBreak = $attendance->breaks()
            ->whereNull('end_time')
            ->latest()
            ->first();

        if ($activeBreak) {
            $activeBreak->update([
                'end_time' => now(),
            ]);
        }

        $attendance->update([
            'status' => 'working',
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance
        ]);
    }

    /**
     * 勤務履歴を表示
     */
    public function attendanceHistory(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $attendances = $user->attendances()
            ->with('breaks')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('user.attendance-history', compact('attendances'));
    }

    /**
     * 勤怠一覧を表示
     */
    public function attendanceList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 年月の取得（デフォルトは現在の年月）
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $currentMonth = \Carbon\Carbon::create($year, $month, 1);
        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        // 月の開始日と終了日
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // その月の勤怠データを取得
        $attendances = $user->attendances()
            ->with('breaks')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(function ($attendance) {
                return $attendance->created_at->format('Y-m-d');
            });

        // その月の申請データを取得
        $attendanceRequests = $user->attendanceRequests()
            ->where('status', 'pending')
            ->whereBetween('target_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy('target_date');

        $breakRequests = $user->breakRequests()
            ->where('status', 'pending')
            ->whereBetween('target_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->groupBy('target_date');

        // カレンダー配列を作成
        $calendar = [];
        $currentDate = $startOfMonth->copy();

        while ($currentDate <= $endOfMonth) {
            $dateKey = $currentDate->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            $attendanceRequest = $attendanceRequests->get($dateKey);
            $dateBreakRequests = $breakRequests->get($dateKey, collect());

            // 勤務時間と休憩時間の計算
            $workTime = '';
            $breakTime = '';
            $hasPendingRequest = false;

            if ($attendanceRequest) {
                // 申請中のデータがある場合
                $hasPendingRequest = true;

                if ($attendanceRequest->clock_in_time && $attendanceRequest->clock_out_time) {
                    $workTime = $this->calculateWorkTime($attendanceRequest->clock_in_time, $attendanceRequest->clock_out_time, []);
                }

                // 申請中の休憩データを処理
                if ($attendanceRequest->break_info) {
                    $breakTime = $this->calculateBreakTimeFromInfo($attendanceRequest->break_info);
                }
            } elseif ($attendance) {
                // 実際の勤怠記録がある場合
                if ($attendance->clock_in_time && $attendance->clock_out_time) {
                    $workTime = $this->calculateWorkTime($attendance->clock_in_time, $attendance->clock_out_time, $attendance->breaks);
                }

                if ($attendance->breaks->isNotEmpty()) {
                    $breakTime = $this->calculateBreakTime($attendance->breaks);
                }
            }

            $calendar[] = [
                'day' => $currentDate->format('j'),
                'weekday' => $this->getJapaneseWeekday($currentDate->dayOfWeek),
                'date' => $currentDate->format('Y-m-d'),
                'attendance' => $attendance,
                'attendanceRequest' => $attendanceRequest,
                'hasPendingRequest' => $hasPendingRequest,
                'workTime' => $workTime,
                'breakTime' => $breakTime,
                'isToday' => $currentDate->isToday(),
                'isWeekend' => $currentDate->isWeekend(),
            ];

            $currentDate->addDay();
        }

        // サマリー計算
        $summary = $this->calculateSummary($attendances);

        return view('attendance.list', compact('calendar', 'currentMonth', 'prevMonth', 'nextMonth', 'summary'));
    }

    /**
     * 勤務時間を計算
     */
    private function calculateWorkTime($clockIn, $clockOut, $breakTimes = [])
    {
        $totalMinutes = $clockIn->diffInMinutes($clockOut);

        // 休憩時間を差し引く
        foreach ($breakTimes as $break) {
            if ($break->start_time && $break->end_time) {
                $breakMinutes = $break->start_time->diffInMinutes($break->end_time);
                $totalMinutes -= $breakMinutes;
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * 休憩時間を計算
     */
    private function calculateBreakTime($breakTimes)
    {
        $totalMinutes = 0;
        foreach ($breakTimes as $break) {
            if ($break->start_time && $break->end_time) {
                $totalMinutes += $break->start_time->diffInMinutes($break->end_time);
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * 申請中の休憩情報から休憩時間を計算
     */
    private function calculateBreakTimeFromInfo($breakInfo)
    {
        $totalMinutes = 0;

        foreach ($breakInfo as $break) {
            if (isset($break['start_time']) && isset($break['end_time'])) {
                $startTime = \Carbon\Carbon::parse($break['start_time']);
                $endTime = \Carbon\Carbon::parse($break['end_time']);
                $totalMinutes += $startTime->diffInMinutes($endTime);
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    /**
     * 日本語の曜日を取得
     */
    private function getJapaneseWeekday($dayOfWeek)
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $weekdays[$dayOfWeek];
    }

    /**
     * 月間サマリーを計算
     */
    private function calculateSummary($attendances)
    {
        $workDays = 0;
        $totalWorkMinutes = 0;
        $totalBreakMinutes = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_time && $attendance->clock_out_time) {
                $workDays++;

                $workMinutes = $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);

                // 休憩時間を差し引く
                foreach ($attendance->breaks as $break) {
                    if ($break->start_time && $break->end_time) {
                        $breakMinutes = $break->start_time->diffInMinutes($break->end_time);
                        $workMinutes -= $breakMinutes;
                        $totalBreakMinutes += $breakMinutes;
                    }
                }

                $totalWorkMinutes += $workMinutes;
            }
        }

        return [
            'workDays' => $workDays,
            'totalWorkHours' => round($totalWorkMinutes / 60, 1),
            'totalBreakHours' => round($totalBreakMinutes / 60, 1),
        ];
    }

    public function attendanceDetail($id)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($id == 0) {
            // 勤怠記録がない場合
            $attendance = null;
            $date = request()->get('date', now()->format('Y-m-d'));
        } else {
            $attendance = $user->attendances()->with(['breaks', 'attendanceRequests'])->findOrFail($id);
            $date = $attendance->created_at->format('Y-m-d');
        }

        // 承認待ちの申請があるかチェック
        $hasPendingRequest = $this->checkPendingRequest($user, $attendance, $date);

        // 承認待ちの申請データを取得
        $pendingAttendanceRequest = null;
        $pendingBreakRequests = null;

        if ($hasPendingRequest) {
            if ($attendance) {
                // 既存の勤怠記録の場合
                $pendingAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                    ->where('status', 'pending')
                    ->first();
                $pendingBreakRequests = BreakRequest::where('attendance_id', $attendance->id)
                    ->where('status', 'pending')
                    ->get();
            } else {
                // 新規作成の場合
                $targetDate = $date ?? now()->format('Y-m-d');
                $pendingAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                    ->where('target_date', $targetDate)
                    ->where('status', 'pending')
                    ->first();
                $pendingBreakRequests = BreakRequest::where('user_id', $user->id)
                    ->where('target_date', $targetDate)
                    ->where('status', 'pending')
                    ->get();
            }
        }

        // 表示用データを準備
        $displayData = $this->prepareDisplayData($attendance, $hasPendingRequest, $pendingAttendanceRequest, $pendingBreakRequests);

        return view('attendance.detail', compact(
            'attendance',
            'date',
            'hasPendingRequest',
            'pendingAttendanceRequest',
            'pendingBreakRequests',
            'displayData'
        ));
    }

    /**
     * 表示用データを準備
     */
    private function prepareDisplayData($attendance, $hasPendingRequest, $pendingAttendanceRequest, $pendingBreakRequests)
    {
        $data = [];

        // 出勤・退勤時間
        if ($hasPendingRequest && $pendingAttendanceRequest) {
            $data['clockInTime'] = $pendingAttendanceRequest->clock_in_time ? $pendingAttendanceRequest->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $pendingAttendanceRequest->clock_out_time ? $pendingAttendanceRequest->clock_out_time->format('H:i') : '';
        } elseif ($hasPendingRequest) {
            $data['clockInTime'] = '';
            $data['clockOutTime'] = '';
        } else {
            $data['clockInTime'] = $attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '';
        }

        // 休憩1
        if ($hasPendingRequest && $pendingBreakRequests && $pendingBreakRequests->count() > 0) {
            $firstBreakRequest = $pendingBreakRequests->first();
            $data['break1StartTime'] = $firstBreakRequest->start_time ? (is_string($firstBreakRequest->start_time) ? $firstBreakRequest->start_time : $firstBreakRequest->start_time->format('H:i')) : '';
            $data['break1EndTime'] = $firstBreakRequest->end_time ? (is_string($firstBreakRequest->end_time) ? $firstBreakRequest->end_time : $firstBreakRequest->end_time->format('H:i')) : '';
            $data['break1Notes'] = '';
        } elseif ($hasPendingRequest && $pendingAttendanceRequest && $pendingAttendanceRequest->break_info) {
            $firstBreakInfo = $pendingAttendanceRequest->break_info[0] ?? null;
            if ($firstBreakInfo) {
                $data['break1StartTime'] = $firstBreakInfo['start_time'] ?? '';
                $data['break1EndTime'] = $firstBreakInfo['end_time'] ?? '';
                $data['break1Notes'] = '';
            } else {
                $data['break1StartTime'] = '';
                $data['break1EndTime'] = '';
                $data['break1Notes'] = '';
            }
        } elseif ($hasPendingRequest) {
            $data['break1StartTime'] = '';
            $data['break1EndTime'] = '';
            $data['break1Notes'] = '';
        } elseif ($attendance && $attendance->breaks->count() > 0) {
            $firstBreak = $attendance->breaks->first();
            $data['break1StartTime'] = $firstBreak->start_time ? $firstBreak->start_time->format('H:i') : '';
            $data['break1EndTime'] = $firstBreak->end_time ? $firstBreak->end_time->format('H:i') : '';
            $data['break1Notes'] = '';
        } else {
            $data['break1StartTime'] = '';
            $data['break1EndTime'] = '';
            $data['break1Notes'] = '';
        }

        // 休憩2
        if ($hasPendingRequest && $pendingBreakRequests && $pendingBreakRequests->count() > 1) {
            $secondBreakRequest = $pendingBreakRequests->get(1);
            $data['break2StartTime'] = $secondBreakRequest->start_time ? (is_string($secondBreakRequest->start_time) ? $secondBreakRequest->start_time : $secondBreakRequest->start_time->format('H:i')) : '';
            $data['break2EndTime'] = $secondBreakRequest->end_time ? (is_string($secondBreakRequest->end_time) ? $secondBreakRequest->end_time : $secondBreakRequest->end_time->format('H:i')) : '';
            $data['break2Notes'] = '';
        } elseif ($hasPendingRequest && $pendingAttendanceRequest && $pendingAttendanceRequest->break_info && count($pendingAttendanceRequest->break_info) > 1) {
            $secondBreakInfo = $pendingAttendanceRequest->break_info[1] ?? null;
            if ($secondBreakInfo) {
                $data['break2StartTime'] = $secondBreakInfo['start_time'] ?? '';
                $data['break2EndTime'] = $secondBreakInfo['end_time'] ?? '';
                $data['break2Notes'] = '';
            } else {
                $data['break2StartTime'] = '';
                $data['break2EndTime'] = '';
                $data['break2Notes'] = '';
            }
        } elseif ($hasPendingRequest) {
            $data['break2StartTime'] = '';
            $data['break2EndTime'] = '';
            $data['break2Notes'] = '';
        } elseif ($attendance && $attendance->breaks->count() > 1) {
            $secondBreak = $attendance->breaks->get(1);
            $data['break2StartTime'] = $secondBreak->start_time ? $secondBreak->start_time->format('H:i') : '';
            $data['break2EndTime'] = $secondBreak->end_time ? $secondBreak->end_time->format('H:i') : '';
            $data['break2Notes'] = '';
        } else {
            $data['break2StartTime'] = '';
            $data['break2EndTime'] = '';
            $data['break2Notes'] = '';
        }

        // 備考
        if ($hasPendingRequest && $pendingAttendanceRequest) {
            $data['notes'] = $pendingAttendanceRequest->notes ?? '';
        } elseif ($hasPendingRequest) {
            $data['notes'] = '';
        } else {
            $data['notes'] = $attendance ? $attendance->notes : '';
        }

        return $data;
    }

    /**
     * 承認待ちの申請があるかチェック
     */
    private function checkPendingRequest($user, $attendance, $date)
    {
        if ($attendance) {
            // 既存の勤怠記録の場合、その勤怠IDに対する保留中の申請をチェック
            $hasAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();
            $hasBreakRequest = BreakRequest::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();
            return $hasAttendanceRequest || $hasBreakRequest;
        } else {
            // 新規作成の場合、その日付に対する保留中の申請をチェック
            $targetDate = $date ?? now()->format('Y-m-d');
            $hasAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                ->where('target_date', $targetDate)
                ->where('status', 'pending')
                ->exists();
            $hasBreakRequest = BreakRequest::where('user_id', $user->id)
                ->where('target_date', $targetDate)
                ->where('status', 'pending')
                ->exists();
            return $hasAttendanceRequest || $hasBreakRequest;
        }
    }

    /**
     * 勤怠修正申請を作成
     */
    public function attendanceUpdate(AttendanceFormRequest $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        try {
            $attendance = $user->attendances()->findOrFail($id);

            // 既に保留中の申請があるかチェック
            $existingRequest = $user->attendanceRequests()
                ->where('attendance_id', $id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return back()->withErrors(['general' => '既に保留中の申請があります。']);
            }

            $requestData = [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'target_date' => $attendance->created_at->format('Y-m-d'),
                'request_type' => 'update',
                'status' => 'pending',
                'notes' => $request->notes,
            ];

            // 時間データの処理
            if ($request->clock_in_time) {
                // 既に完全な日時文字列の場合はそのまま使用、そうでなければ:00を追加
                $requestData['clock_in_time'] = strpos($request->clock_in_time, ':00') !== false
                    ? $request->clock_in_time
                    : $request->clock_in_time . ':00';
            }
            if ($request->clock_out_time) {
                // 既に完全な日時文字列の場合はそのまま使用、そうでなければ:00を追加
                $requestData['clock_out_time'] = strpos($request->clock_out_time, ':00') !== false
                    ? $request->clock_out_time
                    : $request->clock_out_time . ':00';
            }

            // 勤怠申請を作成
            $attendanceRequest = AttendanceRequestModel::create($requestData);

            // 休憩時間の申請処理
            $this->processBreakRequests($user, $attendance, $request);

            return redirect()->route('user.attendance.list')->with('success', '修正申請を送信しました。承認をお待ちください。');
        } catch (\Exception $e) {
            return back()->withErrors(['general' => 'エラーが発生しました: ' . $e->getMessage()]);
        }
    }

    /**
     * 休憩申請を処理
     */
    private function processBreakRequests($user, $attendance, $request)
    {
        // 休憩1の申請処理
        if ($request->break1_start_time || $request->break1_end_time) {
            $this->createBreakRequest($user, $attendance, $request, 1);
        }

        // 休憩2の申請処理
        if ($request->break2_start_time || $request->break2_end_time) {
            $this->createBreakRequest($user, $attendance, $request, 2);
        }
    }

    /**
     * 個別の休憩申請を作成
     */
    private function createBreakRequest($user, $attendance, $request, $breakNumber)
    {
        $startTimeField = "break{$breakNumber}_start_time";
        $endTimeField = "break{$breakNumber}_end_time";

        // 既存の休憩を取得
        $existingBreak = $attendance->breaks()->skip($breakNumber - 1)->first();

        $requestData = [
            'user_id' => $user->id,
            'attendance_id' => $attendance->id,
            'target_date' => $attendance->created_at->format('Y-m-d'),
            'status' => 'pending',
        ];

        if ($existingBreak) {
            // 既存の休憩を修正する場合
            $requestData['break_id'] = $existingBreak->id;
            $requestData['request_type'] = 'update';
        } else {
            // 新しい休憩を作成する場合
            $requestData['break_id'] = null;
            $requestData['request_type'] = 'create';
        }

        // 時間データの処理
        if ($request->$startTimeField) {
            $requestData['start_time'] = $request->$startTimeField . ':00';
        }
        if ($request->$endTimeField) {
            $requestData['end_time'] = $request->$endTimeField . ':00';
        }

        // 既に保留中の申請があるかチェック
        $existingRequest = $user->breakRequests()
            ->where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->first();

        if (!$existingRequest) {
            BreakRequest::create($requestData);
        }
    }

    /**
     * 勤怠新規作成申請を作成
     */
    public function attendanceStore(AttendanceFormRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // 指定された日付で既に勤怠記録が存在するかチェック
        $existingAttendance = $user->attendances()
            ->whereDate('created_at', $request->date)
            ->first();

        if ($existingAttendance) {
            return back()->withErrors(['date' => '指定された日付には既に勤怠記録が存在します。']);
        }

        // 既に保留中の申請があるかチェック
        $existingRequest = $user->attendanceRequests()
            ->where('target_date', $request->date)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->withErrors(['date' => '既に保留中の申請があります。']);
        }

        $requestData = [
            'user_id' => $user->id,
            'attendance_id' => null,
            'target_date' => $request->date,
            'request_type' => 'create',
            'status' => 'pending',
            'notes' => $request->notes,
        ];

        // 時間データの処理
        if ($request->clock_in_time) {
            // 時刻のみの場合は:00を追加、完全な日時文字列の場合はそのまま使用
            $clockInTime = $request->clock_in_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                $clockInTime .= ':00';
            }
            $requestData['clock_in_time'] = $clockInTime;
        }
        if ($request->clock_out_time) {
            // 時刻のみの場合は:00を追加、完全な日時文字列の場合はそのまま使用
            $clockOutTime = $request->clock_out_time;
            if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                $clockOutTime .= ':00';
            }
            $requestData['clock_out_time'] = $clockOutTime;
        }

        // 休憩情報を収集
        $breakInfo = [];
        if ($request->break1_start_time || $request->break1_end_time) {
            $breakInfo[] = [
                'start_time' => $request->break1_start_time,
                'end_time' => $request->break1_end_time,
            ];
        }
        if ($request->break2_start_time || $request->break2_end_time) {
            $breakInfo[] = [
                'start_time' => $request->break2_start_time,
                'end_time' => $request->break2_end_time,
            ];
        }

        // 休憩情報を追加
        if (!empty($breakInfo)) {
            $requestData['break_info'] = $breakInfo;
        }

        // 勤怠申請を作成
        $attendanceRequest = AttendanceRequestModel::create($requestData);

        return redirect()->route('user.attendance.list')->with('success', '新規作成申請を送信しました。承認をお待ちください。');
    }

    /**
     * 申請一覧を表示
     */
    public function attendanceRequests(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $requests = $user->attendanceRequests()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('attendance.requests', compact('requests'));
    }

    /**
     * 申請一覧を表示（stamp_correction_request用）
     */
    public function stampCorrectionRequests(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $status = $request->get('status', 'pending');

        // 承認待ちと承認済みの件数を取得（勤怠申請 + 休憩申請）
        $pendingCount = $user->attendanceRequests()->where('status', 'pending')->count() +
            $user->breakRequests()->where('status', 'pending')->count();
        $approvedCount = $user->attendanceRequests()->where('status', 'approved')->count() +
            $user->breakRequests()->where('status', 'approved')->count();

        // 勤怠申請を取得
        $attendanceRequests = $user->attendanceRequests()
            ->with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'attendance';
                // その日付の勤怠IDを取得
                $attendance = Attendance::where('user_id', $request->user_id)
                    ->whereDate('created_at', $request->target_date)
                    ->first();
                $request->attendance_id = $attendance ? $attendance->id : 0;
                return $request;
            });

        // 休憩申請を取得
        $breakRequests = $user->breakRequests()
            ->with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'break';
                // その日付の勤怠IDを取得
                $attendance = Attendance::where('user_id', $request->user_id)
                    ->whereDate('created_at', $request->target_date)
                    ->first();
                $request->attendance_id = $attendance ? $attendance->id : 0;
                return $request;
            });

        // 両方の申請を結合して日時順にソート
        $allRequests = $attendanceRequests->concat($breakRequests)
            ->sortByDesc('created_at');

        // ページネーション用に配列を分割
        $perPage = 20;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $requests = $allRequests->slice($offset, $perPage);

        // 手動でページネーション情報を作成
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $requests,
            $allRequests->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );

        return view('stamp_correction_request.list', compact('paginator', 'status', 'pendingCount', 'approvedCount'));
    }
}
