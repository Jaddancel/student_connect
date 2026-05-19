<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('position')->nullable()->after('occupation');
            $table->string('photo')->nullable()->after('position');
            $table->string('birthplace')->nullable()->after('birthday');
            $table->string('home_address')->nullable()->after('birthplace');
            $table->string('parents_guardian')->nullable()->after('home_address');
            $table->text('talents_hobbies')->nullable()->after('parents_guardian');
            $table->json('financial_support')->nullable()->after('talents_hobbies');
            $table->string('scholar_provider')->nullable()->after('financial_support');
            $table->string('financial_support_other')->nullable()->after('scholar_provider');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'position',
                'photo',
                'birthplace',
                'home_address',
                'parents_guardian',
                'talents_hobbies',
                'financial_support',
                'scholar_provider',
                'financial_support_other',
            ]);
        });
    }
};
