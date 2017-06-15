<?php


use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use HS\Models\User\User;

class UsersAddLoginColumn extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->string('login')->nullable()->index();
        });

        /*
         * Set login for existing users
         */
        $users = User::withTrashed()->get();
        foreach ($users as $user) {
            $user->login = $user->email;
            $user->save();
        }

        Schema::table('users', function (Blueprint $table)
        {
            $table->unique('login');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table)
        {
            $table->dropColumn('login');
        });
    }

}
