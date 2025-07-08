<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('target_date'); //勤怠申請日
            $table->enum('request_type', ['create', 'update']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->time('clock_in_time')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->json('break_info')->nullable(); // 休憩情報をJSON形式で保存
            $table->text('notes');

            $table->timestamps();

            // 開発・テスト用のため、ユニーク制約は一時的に削除
            // 本番環境では必要に応じて適切な制約を追加することを推奨
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_requests');
    }
}
