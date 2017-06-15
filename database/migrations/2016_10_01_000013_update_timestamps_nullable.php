<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\DbDongle;

class UpdateTimestampsNullable extends Migration
{
    public function up()
    {
        DbDongle::disableStrictMode();

        DbDongle::convertTimestamps('users');
        DbDongle::convertTimestamps('user_groups');
        DbDongle::convertTimestamps('mail_blockers');
    }

    public function down()
    {
        // ...
    }
}
