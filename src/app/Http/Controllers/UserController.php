<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Attendance;
use App\Models\AttendanceRequest as AttendanceRequestModel;
use App\Http\Requests\AttendanceFormRequest;

class UserController extends Controller
{

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


    public function clockIn(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $existingAttendance = $user->attendances()
            ->whereDate('created_at', today())
            ->first();
        if ($existingAttendance) {
            return response()->json([
                'success' => false
            ]);
        }
        $attendance = $user->attendances()->create([
            'clock_in_time' => now(),
            'status' => 'working',
        ]);

        return response()->json([
            'success' => true,
            'attendance' => $attendance
        ]);
    }


    public function clockOut(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
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


    public function breakStart(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
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


    public function breakEnd(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
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

    public function attendanceList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $currentMonth = \Carbon\Carbon::create($year, $month, 1);
        $prevMonth = $currentMonth->copy()->subMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        $attendances = $user->attendances()
            ->with('breaks')
            ->where(function ($query) use ($year, $month) {
                $query->where(function ($q) use ($year, $month) {
                    $q->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_in_time', $year)
                        ->whereMonth('clock_in_time', $month);
                })->orWhere(function ($q) use ($year, $month) {
                    $q->whereYear('clock_out_time', $year)
                        ->whereMonth('clock_out_time', $month);
                });
            })
            ->get()
            ->keyBy(function ($attendance) {
                if ($attendance->clock_in_time) {
                    return $attendance->clock_in_time->format('Y-m-d');
                } elseif ($attendance->clock_out_time) {
                    return $attendance->clock_out_time->format('Y-m-d');
                } else {
                    return $attendance->created_at->format('Y-m-d');
                }
            });

        $attendanceRequests = $user->attendanceRequests()
            ->where('status', 'approved')
            ->whereBetween('target_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->keyBy(function ($request) {
                return $request->target_date->format('Y-m-d');
            });

        $breakRequests = $user->breakRequests()
            ->where('status', 'approved')
            ->whereBetween('target_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->groupBy(function ($request) {
                return $request->target_date->format('Y-m-d');
            });

        $calendar = [];
        $currentDate = $startOfMonth->copy();

        while ($currentDate <= $endOfMonth) {
            $dateKey = $currentDate->format('Y-m-d');
            $attendance = $attendances->get($dateKey);
            $attendanceRequest = $attendanceRequests->get($dateKey);
            $dateBreakRequests = $breakRequests->get($dateKey, collect());

            $workTime = '';
            $breakTime = '';
            $hasApprovedRequest = false;

            if ($attendanceRequest) {
                $hasApprovedRequest = true;

                if ($attendanceRequest->clock_in_time && $attendanceRequest->clock_out_time) {
                    $workTime = $this->calculateWorkTime($attendanceRequest->clock_in_time, $attendanceRequest->clock_out_time, []);
                }

                if ($attendanceRequest->break_info) {
                    $breakTime = $this->calculateBreakTimeFromInfo($attendanceRequest->break_info);
                }
            }

            if (!$attendanceRequest && $attendance) {
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
                'hasApprovedRequest' => $hasApprovedRequest,
                'workTime' => $workTime,
                'breakTime' => $breakTime,
                'isToday' => $currentDate->isToday(),
                'isWeekend' => $currentDate->isWeekend(),
            ];

            $currentDate->addDay();
        }

        $summary = $this->calculateSummary($attendances);

        return view('attendance.list', compact('calendar', 'currentMonth', 'prevMonth', 'nextMonth', 'summary'));
    }

    private function calculateWorkTime($clockIn, $clockOut, $breakTimes = [])
    {
        $totalMinutes = $clockIn->diffInMinutes($clockOut);

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

    private function calculateBreakTimeFromInfo($breakInfo)
    {
        $totalMinutes = 0;

        foreach ($breakInfo as $break) {
            if (isset($break['start_time']) && isset($break['end_time'])) {
                try {
                    $startTimeStr = $break['start_time'];
                    $endTimeStr = $break['end_time'];

                    if (preg_match('/^\d{1,2}:\d{2}$/', $startTimeStr) && preg_match('/^\d{1,2}:\d{2}$/', $endTimeStr)) {
                        $startTime = \Carbon\Carbon::createFromFormat('H:i', $startTimeStr);
                        $endTime = \Carbon\Carbon::createFromFormat('H:i', $endTimeStr);
                    } else {
                        $startTime = \Carbon\Carbon::parse($startTimeStr);
                        $endTime = \Carbon\Carbon::parse($endTimeStr);
                    }

                    $totalMinutes += $startTime->diffInMinutes($endTime);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%d:%02d', $hours, $minutes);
    }

    private function getJapaneseWeekday($dayOfWeek)
    {
        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        return $weekdays[$dayOfWeek];
    }

    private function calculateSummary($attendances)
    {
        $workDays = 0;
        $totalWorkMinutes = 0;
        $totalBreakMinutes = 0;

        foreach ($attendances as $attendance) {
            if ($attendance->clock_in_time && $attendance->clock_out_time) {
                $workDays++;

                $workMinutes = $attendance->clock_in_time->diffInMinutes($attendance->clock_out_time);

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
            $attendance = null;
            $date = request()->get('date', now()->format('Y-m-d'));
        } else {
            $attendance = $user->attendances()->with(['breaks', 'attendanceRequests'])->findOrFail($id);
            $date = $attendance->created_at->format('Y-m-d');
        }

        $hasPendingRequest = $this->checkPendingRequest($user, $attendance, $date);
        $hasApprovedRequest = $this->checkApprovedRequest($user, $attendance, $date);

        $pendingAttendanceRequest = null;
        $approvedAttendanceRequest = null;

        if ($hasPendingRequest) {
            if ($attendance) {
                $pendingAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                    ->where('status', 'pending')
                    ->first();
            } else {
                $targetDate = $date ?? now()->format('Y-m-d');
                $pendingAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                    ->where('target_date', $targetDate)
                    ->where('status', 'pending')
                    ->first();
            }
        }

        if ($hasApprovedRequest) {
            if ($attendance) {
                $approvedAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                    ->where('status', 'approved')
                    ->first();
            } else {
                $targetDate = $date ?? now()->format('Y-m-d');
                $approvedAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                    ->where('target_date', $targetDate)
                    ->where('status', 'approved')
                    ->first();
            }
        }

        $displayData = $this->prepareDisplayData($attendance, $hasPendingRequest, $pendingAttendanceRequest, $hasApprovedRequest, $approvedAttendanceRequest);

        return view('attendance.detail', compact(
            'attendance',
            'date',
            'hasPendingRequest',
            'hasApprovedRequest',
            'pendingAttendanceRequest',
            'approvedAttendanceRequest',
            'displayData'
        ));
    }

    private function prepareDisplayData($attendance, $hasPendingRequest, $pendingAttendanceRequest, $hasApprovedRequest, $approvedAttendanceRequest)
    {
        $data = [];

        $requestToUse = $approvedAttendanceRequest ?? $pendingAttendanceRequest;
        $hasRequest = $hasApprovedRequest || $hasPendingRequest;

        if ($hasRequest && $requestToUse) {
            $data['clockInTime'] = $requestToUse->clock_in_time ? $requestToUse->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $requestToUse->clock_out_time ? $requestToUse->clock_out_time->format('H:i') : '';
        } else {
            $data['clockInTime'] = $attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '';
            $data['clockOutTime'] = $attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '';
        }

        if ($hasRequest && $requestToUse && $requestToUse->break_info) {
            $firstBreakInfo = $requestToUse->break_info[0] ?? null;
            if ($firstBreakInfo) {
                $startTimeStr = $firstBreakInfo['start_time'] ?? '';
                $endTimeStr = $firstBreakInfo['end_time'] ?? '';

                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startTimeStr)) {
                    $data['break1StartTime'] = substr($startTimeStr, 11, 5);
                } else {
                    $data['break1StartTime'] = $startTimeStr;
                }

                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endTimeStr)) {
                    $data['break1EndTime'] = substr($endTimeStr, 11, 5);
                } else {
                    $data['break1EndTime'] = $endTimeStr;
                }

                $data['break1Notes'] = '';
            } else {
                $data['break1StartTime'] = '';
                $data['break1EndTime'] = '';
                $data['break1Notes'] = '';
            }
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

        if ($hasRequest && $requestToUse && $requestToUse->break_info && count($requestToUse->break_info) > 1) {
            $secondBreakInfo = $requestToUse->break_info[1] ?? null;
            if ($secondBreakInfo) {
                $startTimeStr = $secondBreakInfo['start_time'] ?? '';
                $endTimeStr = $secondBreakInfo['end_time'] ?? '';

                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startTimeStr)) {
                    $data['break2StartTime'] = substr($startTimeStr, 11, 5);
                } else {
                    $data['break2StartTime'] = $startTimeStr;
                }

                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endTimeStr)) {
                    $data['break2EndTime'] = substr($endTimeStr, 11, 5);
                } else {
                    $data['break2EndTime'] = $endTimeStr;
                }

                $data['break2Notes'] = '';
            } else {
                $data['break2StartTime'] = '';
                $data['break2EndTime'] = '';
                $data['break2Notes'] = '';
            }
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

        if ($hasRequest && $requestToUse) {
            $data['notes'] = $requestToUse->notes ?? '';
        } else {
            $data['notes'] = $attendance ? $attendance->notes : '';
        }

        return $data;
    }

    private function checkPendingRequest($user, $attendance, $date)
    {
        if ($attendance) {
            $hasAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();
            return $hasAttendanceRequest;
        } else {
            $targetDate = $date ?? now()->format('Y-m-d');
            $hasAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                ->where('target_date', $targetDate)
                ->where('status', 'pending')
                ->exists();
            return $hasAttendanceRequest;
        }
    }

    private function checkApprovedRequest($user, $attendance, $date)
    {
        if ($attendance) {
            $hasAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                ->where('status', 'approved')
                ->exists();
            return $hasAttendanceRequest;
        } else {
            $targetDate = $date ?? now()->format('Y-m-d');
            $hasAttendanceRequest = AttendanceRequestModel::where('user_id', $user->id)
                ->where('target_date', $targetDate)
                ->where('status', 'approved')
                ->exists();
            return $hasAttendanceRequest;
        }
    }

    public function attendanceUpdate(AttendanceFormRequest $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        try {
            $validated = $request->validated();

            $attendance = $user->attendances()->findOrFail($id);

            $existingRequest = $user->attendanceRequests()
                ->where('attendance_id', $id)
                ->where('status', 'pending')
                ->first();

            if ($existingRequest) {
                return back()->withErrors(['general' => '既に保留中の申請があります']);
            }

            $requestData = [
                'user_id' => $user->id,
                'attendance_id' => $id,
                'target_date' => $attendance->created_at->format('Y-m-d'),
                'request_type' => 'update',
                'status' => 'pending',
                'notes' => $request->notes,
            ];

            if ($request->clock_in_time) {
                $clockInTime = $request->clock_in_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                    $clockInTime = $attendance->created_at->format('Y-m-d') . ' ' . $clockInTime;
                }
                $requestData['clock_in_time'] = $clockInTime;
            }
            if ($request->clock_out_time) {
                $clockOutTime = $request->clock_out_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                    $clockOutTime = $attendance->created_at->format('Y-m-d') . ' ' . $clockOutTime;
                }
                $requestData['clock_out_time'] = $clockOutTime;
            }

            $attendanceRequest = AttendanceRequestModel::create($requestData);

            $this->processBreakRequests($user, $attendance, $request);

            return redirect()->route('user.attendance.list');
        } catch (\Exception $e) {
            return back()->withErrors(['general' => 'エラーが発生しました: ' . $e->getMessage()]);
        }
    }

    private function processBreakRequests($user, $attendance, $request)
    {
        $breakInfo = [];

        if ($request->break1_start_time || $request->break1_end_time) {
            $breakInfo[] = [
                'start_time' => $request->break1_start_time ?: null,
                'end_time' => $request->break1_end_time ?: null,
            ];
        }
        if ($request->break2_start_time || $request->break2_end_time) {
            $breakInfo[] = [
                'start_time' => $request->break2_start_time ?: null,
                'end_time' => $request->break2_end_time ?: null,
            ];
        }
        if (!empty($breakInfo)) {
            $existingAttendanceRequest = AttendanceRequestModel::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->first();

            if ($existingAttendanceRequest) {
                $existingAttendanceRequest->update(['break_info' => $breakInfo]);
            }
        }
    }

    public function attendanceStore(AttendanceFormRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();

        try {
            $existingAttendance = $user->attendances()
                ->whereDate('created_at', $request->date)
                ->first();
            if ($existingAttendance) {
                return back()->withErrors(['date' => '指定された日付には既に勤怠記録が存在します']);
            }

            $existingRequest = $user->attendanceRequests()
                ->where('target_date', $request->date)
                ->where('status', 'pending')
                ->first();
            if ($existingRequest) {
                return back()->withErrors(['date' => '既に保留中の申請があります']);
            }

            $requestData = [
                'user_id' => $user->id,
                'attendance_id' => null,
                'target_date' => $request->date,
                'request_type' => 'create',
                'status' => 'pending',
                'notes' => $request->notes,
            ];

            if ($request->clock_in_time) {
                $clockInTime = $request->clock_in_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $clockInTime)) {
                    $clockInTime = $request->date . ' ' . $clockInTime;
                }
                $requestData['clock_in_time'] = $clockInTime;
            }
            if ($request->clock_out_time) {
                $clockOutTime = $request->clock_out_time;
                if (preg_match('/^\d{1,2}:\d{2}$/', $clockOutTime)) {
                    $clockOutTime = $request->date . ' ' . $clockOutTime;
                }
                $requestData['clock_out_time'] = $clockOutTime;
            }

            $breakInfo = [];
            if ($request->break1_start_time || $request->break1_end_time) {
                $breakInfo[] = [
                    'start_time' => $request->break1_start_time ?: null,
                    'end_time' => $request->break1_end_time ?: null,
                ];
            }
            if ($request->break2_start_time || $request->break2_end_time) {
                $breakInfo[] = [
                    'start_time' => $request->break2_start_time ?: null,
                    'end_time' => $request->break2_end_time ?: null,
                ];
            }

            if (!empty($breakInfo)) {
                $requestData['break_info'] = $breakInfo;
            }

            $attendanceRequest = AttendanceRequestModel::create($requestData);

            return redirect()->route('user.attendance.list');
        } catch (\Exception $e) {
            return back()->withErrors(['general' => 'エラーが発生しました: ' . $e->getMessage()]);
        }
    }

    public function attendanceRequests(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $requests = $user->attendanceRequests()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('attendance.requests', compact('requests'));
    }

    public function stampCorrectionRequests(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $status = $request->get('status', 'pending');

        $pendingCount = $user->attendanceRequests()->where('status', 'pending')->count() +
            $user->breakRequests()->where('status', 'pending')->count();
        $approvedCount = $user->attendanceRequests()->where('status', 'approved')->count() +
            $user->breakRequests()->where('status', 'approved')->count();

        $attendanceRequests = $user->attendanceRequests()
            ->with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'attendance';
                $attendance = Attendance::where('user_id', $request->user_id)
                    ->whereDate('created_at', $request->target_date)
                    ->first();
                $request->attendance_id = $attendance ? $attendance->id : 0;
                return $request;
            });

        $breakRequests = $user->breakRequests()
            ->with('user')
            ->where('status', $status)
            ->get()
            ->map(function ($request) {
                $request->request_type = 'break';
                $attendance = Attendance::where('user_id', $request->user_id)
                    ->whereDate('created_at', $request->target_date)
                    ->first();
                $request->attendance_id = $attendance ? $attendance->id : 0;
                return $request;
            });

        $allRequests = $attendanceRequests->concat($breakRequests)
            ->sortByDesc('created_at');

        $perPage = 20;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $requests = $allRequests->slice($offset, $perPage);

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
