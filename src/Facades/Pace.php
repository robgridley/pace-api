<?php

namespace Pace\Facades;

use Illuminate\Support\Facades\Facade;
use Pace\Client;

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
