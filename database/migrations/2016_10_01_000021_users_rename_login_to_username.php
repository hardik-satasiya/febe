<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class UsersRenameLoginToUsername extends Migration
{

    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->renameColumn('login', 'username');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->renameColumn('username', 'login');
        });
    }

}
