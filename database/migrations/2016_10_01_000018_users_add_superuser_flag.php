<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class UsersAddSuperuserFlag extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->boolean('is_superuser')->default(false);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('is_superuser');
        });
    }
}
