<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class UsersAddGuestFlag extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->boolean('is_guest')->default(false);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('is_guest');
        });
    }
}
