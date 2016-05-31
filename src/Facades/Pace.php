<?php

namespace Pace\Facades;

use Pace\Client;
use Illuminate\Support\Facades\Facade;

class Pace extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Client::class;
    }
}
