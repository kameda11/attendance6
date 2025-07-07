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
        'start_time' => 'time',
        'end_time' => 'time',
    ];

    /**
     * 申請者とのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 対象の休憩とのリレーション
     */
    public function break()
    {
        return $this->belongsTo(Breaktime::class, 'break_id');
    }

    /**
     * 勤怠記録を取得（break_idから間接的に取得）
     */
    public function attendance()
    {
        if ($this->break_id) {
            return $this->break->attendance;
        }

        // break_idがnullの場合（新規作成時）は、user_idとtarget_dateから取得
        return $this->user->attendances()
            ->whereDate('created_at', $this->target_date)
            ->first();
    }

    /**
     * 申請が承認待ちかチェック
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * 申請が承認済みかチェック
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * 新規作成申請かチェック
     */
    public function isCreateRequest()
    {
        return $this->request_type === 'create';
    }

    /**
     * 修正申請かチェック
     */
    public function isUpdateRequest()
    {
        return $this->request_type === 'update';
    }
}
