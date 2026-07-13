<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (! Schema::hasColumn('profiles', 'balance')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('balance')->default(0)->after('points');
            });
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('points')->change();
        });

        DB::statement('UPDATE profiles SET balance = points');
    }

    public function down()
    {
        if (Schema::hasColumn('profiles', 'balance')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('balance');
            });
        }
    }
};
