@extends('layouts.admin')
<link rel="stylesheet" href="{{ asset('css/admin/detail.css') }}">

@section('content')
<div class="attendance-detail-container">
    <div class="attendance-detail-header">
        <h1>勤怠詳細</h1>
    </div>

    <form action="{{ $attendance ? route('admin.attendance.update', ['id' => $attendance->id]) : route('admin.attendance.store') }}" method="POST">
        @csrf
        @if($attendance)
        @method('PUT')
        <input type="hidden" name="date" value="{{ $selectedDate ? $selectedDate->format('Y-m-d') : $attendance->created_at->format('Y-m-d') }}">
        @else
        @if($user)
        <input type="hidden" name="user_id" value="{{ $user->id }}">
        @endif
        <input type="hidden" name="date" value="{{ $selectedDate ? $selectedDate->format('Y-m-d') : '' }}">
        @endif
        <div class="attendance-detail-table">
            <table>
                <tbody>
                    <tr>
                        <th>名前</th>
                        <td>{{ $user ? $user->name : '未設定' }}</td>
                    </tr>
                    <tr>
                        <th>日付</th>
                        <td>
                            @if($selectedDate)
                            <div class="date-section">
                                <span class="date-year-section">
                                    <span class="date-year">{{ $selectedDate->format('Y') }}</span><span class="date-unit">年</span>
                                </span>
                                <span class="date-month-day-section">
                                    <span class="date-month-day">{{ $selectedDate->format('n') }}</span><span class="date-unit">月</span>
                                    <span class="date-month-day">{{ $selectedDate->format('j') }}</span><span class="date-unit">日</span>
                                </span>
                            </div>
                            @elseif($attendance)
                            <div class="date-section">
                                <span class="date-year-section">
                                    <span class="date-year">{{ $attendance->created_at->format('Y') }}</span><span class="date-unit">年</span>
                                </span>
                                <span class="date-month-day-section">
                                    <span class="date-month-day">{{ $attendance->created_at->format('n') }}</span><span class="date-unit">月</span>
                                    <span class="date-month-day">{{ $attendance->created_at->format('j') }}</span><span class="date-unit">日</span>
                                </span>
                            </div>
                            @else
                            未設定
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>出勤・退勤</th>
                        <td>
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="clock_in_time" maxlength="5" value="{{ old('clock_in_time', $attendanceRequest && $attendanceRequest->clock_in_time ? $attendanceRequest->clock_in_time->format('H:i') : ($attendance && $attendance->clock_in_time ? $attendance->clock_in_time->format('H:i') : '')) }}" inputmode="numeric" autocomplete="off">
                                    @error('clock_in_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="clock_out_time" maxlength="5" value="{{ old('clock_out_time', $attendanceRequest && $attendanceRequest->clock_out_time ? $attendanceRequest->clock_out_time->format('H:i') : ($attendance && $attendance->clock_out_time ? $attendance->clock_out_time->format('H:i') : '')) }}" inputmode="numeric" autocomplete="off">
                                    @error('clock_out_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>休憩</th>
                        <td>
                            @php
                            // デバッグ情報
                            echo "<!-- Debug: breakRequests count = " . ($breakRequests ? $breakRequests->count() : 'null') . " -->";
                            echo "<!-- Debug: attendance breaks count = " . ($attendance && $attendance->breaks ? $attendance->breaks->count() : 'null') . " -->";
                            @endphp
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break1_start_time" maxlength="5" value="{{ old('break1_start_time', $attendance && $attendance->breaks->count() > 0 ? $attendance->breaks->first()->start_time->format('H:i') : '') }}" inputmode="numeric" autocomplete="off">
                                    @error('break1_start_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break1_end_time" maxlength="5" value="{{ old('break1_end_time', $attendance && $attendance->breaks->count() > 0 ? $attendance->breaks->first()->end_time->format('H:i') : '') }}" inputmode="numeric" autocomplete="off">
                                    @error('break1_end_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>休憩2</th>
                        <td>
                            @php
                            // デバッグ情報
                            echo "<!-- Debug: breakRequests count = " . ($breakRequests ? $breakRequests->count() : 'null') . " -->";
                            echo "<!-- Debug: attendance breaks count = " . ($attendance && $attendance->breaks ? $attendance->breaks->count() : 'null') . " -->";
                            if ($attendance && $attendance->breaks) {
                            $attendance->breaks->each(function($break, $index) {
                            echo "<!-- Debug: Break " . ($index + 1) . " = " . $break->start_time->format('H:i') . " - " . $break->end_time->format('H:i') . " -->";
                            });
                            }
                            @endphp
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break2_start_time" maxlength="5" value="{{ old('break2_start_time', $attendance && $attendance->breaks->count() > 1 ? $attendance->breaks->get(1)->start_time->format('H:i') : '') }}" inputmode="numeric" autocomplete="off">
                                    @error('break2_start_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break2_end_time" maxlength="5" value="{{ old('break2_end_time', $attendance && $attendance->breaks->count() > 1 ? $attendance->breaks->get(1)->end_time->format('H:i') : '') }}" inputmode="numeric" autocomplete="off">
                                    @error('break2_end_time')
                                    <span class="error-message">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>
                            <textarea name="notes" class="notes-textbox" rows="4" cols="50">{{ old('notes', $attendanceRequest && $attendanceRequest->notes ? $attendanceRequest->notes : ($attendance ? ($attendance->notes ?? '') : '')) }}</textarea>
                            @error('notes')
                            <span class="error-message">{{ $message }}</span>
                            @enderror
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="attendance-detail-actions">
            <button type="submit" class="btn btn-primary">修正</button>
        </div>
    </form>
</div>
@endsection