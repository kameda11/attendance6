@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/attendance/detail.css') }}">

@section('content')
<div class="attendance-detail-container">
    <div class="attendance-detail-header">
        <h1>勤怠詳細</h1>
    </div>

    <form action="{{ $attendance ? route('user.attendance.update', ['id' => $attendance->id]) : route('user.attendance.store') }}" method="POST">
        @csrf
        @if($attendance)
        @method('PUT')
        @endif

        @if(!$attendance)
        <input type="hidden" name="date" value="{{ $date }}">
        @error('date')
        <span class="error-message">{{ $message }}</span>
        @enderror
        @endif

        <div class="attendance-detail-table">
            <table>
                <tbody>
                    <tr>
                        <th>名前</th>
                        <td>{{ Auth::user()->name }}</td>
                    </tr>
                    <tr>
                        <th>日付</th>
                        <td>
                            @if($attendance)
                            <div class="date-display">
                                <span class="date-year">{{ $attendance->created_at->format('Y') }}年</span>
                                <span class="date-month-day">{{ $attendance->created_at->format('n') }}月{{ $attendance->created_at->format('j') }}日</span>
                            </div>
                            @else
                            @if($date)
                            <div class="date-display">
                                @php $dateObj = \Carbon\Carbon::parse($date); @endphp
                                <span class="date-year">{{ $dateObj->format('Y') }}年</span>
                                <span class="date-month-day">{{ $dateObj->format('n') }}月{{ $dateObj->format('j') }}日</span>
                            </div>
                            @else
                            未設定
                            @endif
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>出勤・退勤</th>
                        <td>
                            @if($hasPendingRequest)
                            <div class="pending-time-display">
                                <span class="pending-clock-in-time">{{ $displayData['clockInTime'] }}</span>
                                <span class="pending-time-separator"> ～ </span>
                                <span class="pending-clock-out-time">{{ $displayData['clockOutTime'] }}</span>
                            </div>
                            @else
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="clock_in_time" maxlength="5" value="{{ old('clock_in_time', $displayData['clockInTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="clock_out_time" maxlength="5" value="{{ old('clock_out_time', $displayData['clockOutTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            @error('clock_in_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @error('clock_out_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>休憩</th>
                        <td>
                            @if($hasPendingRequest)
                            @if($displayData['break1StartTime'] || $displayData['break1EndTime'])
                            <div class="pending-break-item">
                                <span class="pending-break-time">
                                    <span class="pending-break-start-time">{{ $displayData['break1StartTime'] }}</span>
                                    <span class="pending-break-separator"> ～ </span>
                                    <span class="pending-break-end-time">{{ $displayData['break1EndTime'] }}</span>
                                </span>
                            </div>
                            @endif
                            @else
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break1_start_time" maxlength="5" value="{{ old('break1_start_time', $displayData['break1StartTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break1_end_time" maxlength="5" value="{{ old('break1_end_time', $displayData['break1EndTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            @error('break1_start_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @error('break1_end_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>休憩2</th>
                        <td>
                            @if($hasPendingRequest)
                            @if($displayData['break2StartTime'] || $displayData['break2EndTime'])
                            <div class="pending-break-item">
                                <span class="pending-break-time">
                                    <span class="pending-break-start-time">{{ $displayData['break2StartTime'] }}</span>
                                    <span class="pending-break-separator"> ～ </span>
                                    <span class="pending-break-end-time">{{ $displayData['break2EndTime'] }}</span>
                                </span>
                            </div>
                            @endif
                            @else
                            <div class="time-inputs">
                                <div class="time-input">
                                    <input type="text" name="break2_start_time" maxlength="5" value="{{ old('break2_start_time', $displayData['break2StartTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                                <label>～</label>
                                <div class="time-input">
                                    <input type="text" name="break2_end_time" maxlength="5" value="{{ old('break2_end_time', $displayData['break2EndTime']) }}" inputmode="numeric" autocomplete="off">
                                </div>
                            </div>
                            @error('break2_start_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @error('break2_end_time')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>備考</th>
                        <td>
                            @if($hasPendingRequest)
                            <div class="pending-notes-display">
                                {{ $displayData['notes'] }}
                            </div>
                            @else
                            <textarea name="notes" class="notes-textarea" rows="4" cols="50">{{ old('notes', $displayData['notes']) }}</textarea>
                            @error('notes')
                            <div class="error-message-container">
                                <span class="error-message">{{ $message }}</span>
                            </div>
                            @enderror
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="attendance-detail-actions">
            @if($hasPendingRequest)
            <div class="attendance-notice">
                <p>*承認待ちのため修正はできません。</p>
            </div>
            @else
            <button type="submit" class="btn btn-primary">修正</button>
            @endif
        </div>
    </form>
</div>
@endsection