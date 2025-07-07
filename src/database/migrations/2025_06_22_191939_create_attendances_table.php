<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // 新しいテーブルを作成
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->nullable();
            $table->datetime('clock_in_time')->nullable();
            $table->datetime('clock_out_time')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['working', 'break', 'completed'])->default('working');
            $table->timestamps();

            // 開発・テスト用のため、ユニーク制約は一時的に削除
            // 本番環境では適切な制約を追加することを推奨
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendances');
    }
}
