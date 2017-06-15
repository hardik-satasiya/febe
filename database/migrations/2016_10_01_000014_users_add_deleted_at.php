<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class UsersAddDeletedAt extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('deleted_at');
        });
    }
}
