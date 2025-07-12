<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'break_id',
        'target_date',
        'request_type',
        'status',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'target_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function break()
    {
        return $this->belongsTo(Breaktime::class, 'break_id');
    }

    public function attendance()
    {
        if ($this->break_id) {
            return $this->break->attendance;
        }

        return $this->user->attendances()
            ->whereDate('created_at', $this->target_date)
            ->first();
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isCreateRequest()
    {
        return $this->request_type === 'create';
    }

    public function isUpdateRequest()
    {
        return $this->request_type === 'update';
    }
}
