@extends('layouts.app')
<link rel="stylesheet" href="{{ asset('css/attendance.css') }}">

@section('content')
<div class="attendance-container">
    <div class="attendance-status">
        <div class="status-indicator">
            <span class="status-value" id="workStatus">
                @if($todayAttendance)
                @switch($todayAttendance->status)
                @case('working')
                出勤中
                @break
                @case('break')
                休憩中
                @break
                @case('completed')
                退勤済
                @break
                @default
                勤務外
                @endswitch
                @else
                勤務外
                @endif
            </span>
        </div>
    </div>

    <div class="attendance-info">
        <div class="date-time-info">
            <div class="date-display">
                <span class="value" id="currentDate">{{ date('Y年m月d日') }}({{ date('D') }})</span>
            </div>
            <div class="time-display">
                <span class="value" id="currentTime">{{ date('H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="attendance-buttons">
        <div class="button-group">
            @if(!$todayAttendance)
            <button type="button" class="btn btn-primary" id="clockInBtn" onclick="clockIn()">
                出勤
            </button>
            @elseif($todayAttendance->status === 'working')
            <button type="button" class="btn btn-warning" id="clockOutBtn" onclick="goodbye()">
                退勤
            </button>
            @elseif($todayAttendance->status === 'completed')
            <div class="completion-message">
                <p>お疲れ様でした。</p>
            </div>
            @endif
        </div>

        <div class="button-group">
            @if($todayAttendance && $todayAttendance->status === 'working')
            <button type="button" class="btn btn-info" id="breakStartBtn" onclick="breakStart()">
                休憩入
            </button>
            @endif
            @if($todayAttendance && $todayAttendance->status === 'break')
            <button type="button" class="btn btn-success" id="breakEndBtn" onclick="breakEnd()">
                休憩戻
            </button>
            @endif
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('currentTime');
        const dateElement = document.getElementById('currentDate');

        timeElement.textContent = now.toLocaleTimeString('ja-JP', {
            hour12: false,
            hour: '2-digit',
            minute: '2-digit'
        });

        const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
        const weekday = weekdays[now.getDay()];

        dateElement.textContent = now.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) + `(${weekday})`;
    }

    function clockIn() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('CSRFトークンが見つかりません。');
            return;
        }

        fetch('{{ route("user.clock-in") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function clockOut() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('CSRFトークンが見つかりません。');
            return;
        }

        fetch('{{ route("user.clock-out") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function breakStart() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('CSRFトークンが見つかりません。');
            return;
        }

        fetch('{{ route("user.break-start") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function breakEnd() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.error('CSRFトークンが見つかりません。');
            return;
        }

        fetch('{{ route("user.break-end") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    function goodbye() {
        clockOut();
    }

    updateTime();
    setInterval(updateTime, 1000);
</script>
@endsection