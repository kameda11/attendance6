<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_id',
        'target_date',
        'request_type',
        'status',
        'clock_in_time',
        'clock_out_time',
        'notes',
        'break_info',
    ];

    protected $casts = [
        'target_date' => 'date',
        'clock_in_time' => 'datetime',
        'clock_out_time' => 'datetime',
        'break_info' => 'array',
    ];

    public function setBreakInfoAttribute($value)
    {
        if (is_array($value)) {
            foreach ($value as &$break) {
                if (isset($break['start_time']) && $break['start_time']) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $break['start_time'])) {
                        $break['start_time'] = $break['start_time'];
                    }
                }
                if (isset($break['end_time']) && $break['end_time']) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $break['end_time'])) {
                        $break['end_time'] = $break['end_time'];
                    }
                }
            }
        }
        
        $this->attributes['break_info'] = json_encode($value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';

    const TYPE_CREATE = 'create';
    const TYPE_UPDATE = 'update';

    public function getStatusLabelAttribute()
    {
        return [
            self::STATUS_PENDING => '申請中',
            self::STATUS_APPROVED => '承認済み',
        ][$this->status] ?? $this->status;
    }

    public function getRequestTypeLabelAttribute()
    {
        return [
            self::TYPE_CREATE => '新規作成',
            self::TYPE_UPDATE => '修正申請',
        ][$this->request_type] ?? $this->request_type;
    }
}
