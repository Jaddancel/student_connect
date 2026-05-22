<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('contact_number', 10)->nullable()->after('middle_name');
            $table->unsignedTinyInteger('age')->nullable()->after('contact_number');
            $table->enum('sex', ['Male', 'Female'])->nullable()->after('age');
            $table->string('religion')->nullable()->after('sex');
            $table->string('nationality')->nullable()->after('religion');
            $table->date('birthday')->nullable()->after('nationality');
            $table->string('course_year')->nullable()->after('birthday');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'age', 'sex', 'religion', 'nationality', 'birthday', 'course_year']);
        });
    }
};
