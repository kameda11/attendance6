<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceFormRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'clock_in_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'clock_out_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'break1_start_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'break1_end_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'break2_start_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'break2_end_time' => ['nullable', 'regex:/^[0-9]{1,2}:[0-9]{2}$/'],
            'notes' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ];

        if ($this->isMethod('POST') && $this->has('user_id')) {
            $rules['user_id'] = ['required', 'exists:users,id'];
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateClockTimes($validator);
        });
    }

    private function validateClockTimes($validator)
    {
        $clockInTime = $this->input('clock_in_time');
        $clockOutTime = $this->input('clock_out_time');

        if ($clockInTime && $clockOutTime) {
            try {
                $inTime = \Carbon\Carbon::createFromFormat('H:i', $clockInTime);
                $outTime = \Carbon\Carbon::createFromFormat('H:i', $clockOutTime);
                
                if ($inTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('clock_in_time', '出勤時間もしくは退勤時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('clock_in_time', '出勤時間もしくは退勤時間が不適切な値です');
            }
        }

        $this->validateBreaks($validator, $clockInTime, $clockOutTime);
    }

    private function validateBreaks($validator, $clockInTime, $clockOutTime)
    {
        if (!$clockInTime || !$clockOutTime) {
            return;
        }

        try {
            $inTime = \Carbon\Carbon::createFromFormat('H:i', $clockInTime);
            $outTime = \Carbon\Carbon::createFromFormat('H:i', $clockOutTime);
        } catch (\Exception $e) {
            $validator->errors()->add('clock_out_time', '休憩時間が不適切な値です');
            return;
        }

        $break1Start = $this->input('break1_start_time');
        $break1End = $this->input('break1_end_time');

        if ($break1Start && $break1End) {
            try {
                $break1StartTime = \Carbon\Carbon::createFromFormat('H:i', $break1Start);
                $break1EndTime = \Carbon\Carbon::createFromFormat('H:i', $break1End);

                if ($break1StartTime->greaterThanOrEqualTo($break1EndTime)) {
                    $validator->errors()->add('break1_start_time', '休憩時間が不適切な値です');
                }

                if ($break1StartTime->lessThan($inTime) || $break1StartTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break1_start_time', '休憩時間が不適切な値です');
                }

                if ($break1EndTime->lessThanOrEqualTo($inTime) || $break1EndTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break1_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break1_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
            }
        } elseif ($break1Start) {
            try {
                $break1StartTime = \Carbon\Carbon::createFromFormat('H:i', $break1Start);
                if ($break1StartTime->lessThan($inTime) || $break1StartTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break1_start_time', '休憩時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break1_start_time', '休憩時間が不適切な値です');
            }
        } elseif ($break1End) {
            try {
                $break1EndTime = \Carbon\Carbon::createFromFormat('H:i', $break1End);
                if ($break1EndTime->lessThanOrEqualTo($inTime) || $break1EndTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break1_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break1_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
            }
        }

        $break2Start = $this->input('break2_start_time');
        $break2End = $this->input('break2_end_time');

        if ($break2Start && $break2End) {
            try {
                $break2StartTime = \Carbon\Carbon::createFromFormat('H:i', $break2Start);
                $break2EndTime = \Carbon\Carbon::createFromFormat('H:i', $break2End);

                if ($break2StartTime->greaterThanOrEqualTo($break2EndTime)) {
                    $validator->errors()->add('break2_start_time', '休憩時間が不適切な値です');
                }

                if ($break2StartTime->lessThan($inTime) || $break2StartTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break2_start_time', '休憩時間が不適切な値です');
                }

                if ($break2EndTime->lessThanOrEqualTo($inTime) || $break2EndTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break2_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break2_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
            }
        } elseif ($break2Start) {
            try {
                $break2StartTime = \Carbon\Carbon::createFromFormat('H:i', $break2Start);
                if ($break2StartTime->lessThan($inTime) || $break2StartTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break2_start_time', '休憩時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break2_start_time', '休憩時間が不適切な値です');
            }
        } elseif ($break2End) {

            try {
                $break2EndTime = \Carbon\Carbon::createFromFormat('H:i', $break2End);
                if ($break2EndTime->lessThanOrEqualTo($inTime) || $break2EndTime->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add('break2_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add('break2_end_time', '休憩終了時間もしくは退勤時間が不適切な値です');
            }
        }
    }

    public function messages(): array
    {
        return [
            'clock_in_time.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_time.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'break1_start_time.regex' => '休憩時間が不適切な値です',
            'break1_end_time.regex' => '休憩終了時間もしくは退勤時間が不適切な値です',
            'break2_start_time.regex' => '休憩時間が不適切な値です',
            'break2_end_time.regex' => '休憩終了時間もしくは退勤時間が不適切な値です',
            'date.required' => '日付を入力してください',
            'date.date' => '有効な日付を入力してください',
            'notes.required' => '備考を記入してください',
            'notes.max' => '備考は255文字以内で入力してください',
        ];
    }

    public function attributes(): array
    {
        return [
            'clock_in_time' => '出勤時間',
            'clock_out_time' => '退勤時間',
            'break1_start_time' => '休憩1開始時間',
            'break1_end_time' => '休憩1終了時間',
            'break2_start_time' => '休憩2開始時間',
            'break2_end_time' => '休憩2終了時間',
            'notes' => '備考',
            'date' => '日付',
        ];
    }
}
