<?php namespace HS\Facades;

use October\Rain\Support\Facade;

class Auth extends Facade
{
    /**
     * Get the registered name of the component.
     * @return string
     */
    protected static function getFacadeAccessor() { return 'frontend.auth'; }
}
