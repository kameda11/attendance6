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

        $breaks = [
            ['start' => 'break1_start_time', 'end' => 'break1_end_time'],
            ['start' => 'break2_start_time', 'end' => 'break2_end_time'],
        ];

        foreach ($breaks as $break) {
            $this->validateSingleBreak($validator, $break['start'], $break['end'], $inTime, $outTime);
        }
    }

    private function validateSingleBreak($validator, $startField, $endField, $inTime, $outTime)
    {
        $startTime = $this->input($startField);
        $endTime = $this->input($endField);

        if ($startTime && $endTime) {
            try {
                $breakStart = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                $breakEnd = \Carbon\Carbon::createFromFormat('H:i', $endTime);

                if ($breakStart->greaterThanOrEqualTo($breakEnd)) {
                    $validator->errors()->add($startField, '休憩時間が不適切な値です');
                }
                if ($breakStart->lessThan($inTime) || $breakStart->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add($startField, '休憩時間が不適切な値です');
                }
                if ($breakEnd->lessThanOrEqualTo($inTime) || $breakEnd->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add($endField, '休憩時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add($endField, '休憩時間が不適切な値です');
            }
        } elseif ($startTime) {
            try {
                $breakStart = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                if ($breakStart->lessThan($inTime) || $breakStart->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add($startField, '休憩時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add($startField, '休憩時間が不適切な値です');
            }
        } elseif ($endTime) {
            try {
                $breakEnd = \Carbon\Carbon::createFromFormat('H:i', $endTime);
                if ($breakEnd->lessThanOrEqualTo($inTime) || $breakEnd->greaterThanOrEqualTo($outTime)) {
                    $validator->errors()->add($endField, '休憩時間が不適切な値です');
                }
            } catch (\Exception $e) {
                $validator->errors()->add($endField, '休憩時間が不適切な値です');
            }
        }
    }

    public function messages(): array
    {
        return [
            'clock_in_time.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'clock_out_time.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'break1_start_time.regex' => '休憩時間が不適切な値です',
            'break1_end_time.regex' => '休憩時間が不適切な値です',
            'break2_start_time.regex' => '休憩時間が不適切な値です',
            'break2_end_time.regex' => '休憩時間が不適切な値です',
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
