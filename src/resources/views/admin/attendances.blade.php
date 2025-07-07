@extends('layouts.admin')

@section('content')
<link rel="stylesheet" href="{{ asset('css/admin/attendances.css') }}">

<div class="admin-attendances-container">
    <div class="header-content">
        <h1>{{ $selectedDate->format('Y年m月d日') }}の勤怠</h1>
    </div>

    <div class="date-navigation">
        <div class="nav-content">
            <a href="{{ route('admin.attendances', ['date' => $prevDate->format('Y-m-d')]) }}" class="nav-button prev">
                <svg class="arrow-left" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                前日
            </a>

            <div class="current-date">
                <div class="date-selector">
                    <input type="date" id="dateSelector" value="{{ $selectedDate->format('Y-m-d') }}" class="date-input">
                    <label for="dateSelector" class="calendar-button">
                        <img src="{{ asset('storage/app/public/calendar.png') }}" alt="カレンダー" class="calendar-icon">
                    </label>
                </div>
                <h2>{{ $selectedDate->format('Y/m/d') }}</h2>
            </div>
            <a href="{{ route('admin.attendances', ['date' => $nextDate->format('Y-m-d')]) }}" class="nav-button next">
                翌日
                <svg class="arrow-right" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 12H19M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>
        </div>
    </div>

    <main class="attendances-main">
        <div class="attendances-table">
            <table>
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>休憩</th>
                        <th>合計</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allAttendanceData as $data)
                    <tr class="{{ $data['attendance'] ? 'has-attendance' : 'no-attendance' }}">
                        <td class="user-name">
                            <div class="user-info">
                                <span class="name">{{ $data['user']->name }}</span>
                            </div>
                        </td>
                        <td>{{ $data['attendance'] && $data['attendance']->clock_in_time ? $data['attendance']->clock_in_time->format('H:i') : '' }}</td>
                        <td>{{ $data['attendance'] && $data['attendance']->clock_out_time ? $data['attendance']->clock_out_time->format('H:i') : '' }}</td>
                        <td>{{ $data['breakTime'] }}</td>
                        <td>{{ $data['workTime'] }}</td>
                        <td>
                            @if($data['attendance'])
                            <a href="{{ route('admin.attendance.detail', ['id' => $data['attendance']->id]) }}" class="action-button detail">詳細</a>
                            @else
                            <a href="{{ route('admin.attendance.detail', ['id' => 0, 'user_id' => $data['user']->id, 'date' => $selectedDate->format('Y-m-d')]) }}" class="action-button detail">詳細</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="no-users">ユーザーが登録されていません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
    document.getElementById('dateSelector').addEventListener('change', function() {
        const selectedDate = this.value;
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('date', selectedDate);
        window.location.href = currentUrl.toString();
    });

    // カレンダーアイコンをクリックしたときにdate pickerを開く
    document.querySelector('.calendar-button').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('dateSelector').showPicker();
    });
</script>
@endsection