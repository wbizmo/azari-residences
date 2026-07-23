<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) $table->string('username')->nullable()->unique()->after('name');
            if (! Schema::hasColumn('users', 'phone')) $table->string('phone')->nullable()->after('email');
            if (! Schema::hasColumn('users', 'avatar_path')) $table->string('avatar_path')->nullable()->after('phone');
            if (! Schema::hasColumn('users', 'is_admin')) $table->boolean('is_admin')->default(false)->index();
            if (! Schema::hasColumn('users', 'is_active')) $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['username', 'phone', 'avatar_path', 'is_admin', 'is_active'] as $column) {
                if (Schema::hasColumn('users', $column)) $table->dropColumn($column);
            }
        });
    }
};
