<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use HS\Models\User\User;

class UsersAddLastSeen extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->timestamp('last_seen')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('last_seen');
        });
    }
}
