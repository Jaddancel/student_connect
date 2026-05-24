<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('student_id')->nullable()->after('photo');
            $table->string('id_photo_front')->nullable()->after('student_id');
            $table->string('id_photo_back')->nullable()->after('id_photo_front');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['student_id', 'id_photo_front', 'id_photo_back']);
        });
    }
};
