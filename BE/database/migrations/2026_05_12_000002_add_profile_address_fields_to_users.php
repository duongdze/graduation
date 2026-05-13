<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('address')->nullable()->after('bio');
            $table->string('ward', 100)->nullable()->after('address');
            $table->string('district', 100)->nullable()->after('ward');
            $table->string('city', 100)->nullable()->after('district');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address', 'ward', 'district', 'city']);
        });
    }
};
