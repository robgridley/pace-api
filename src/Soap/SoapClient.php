<?php

namespace Pace\Soap;

use SoapClient as PhpSoapClient;

class SoapClient extends PhpSoapClient
{
    /**
     * The middleware.
     *
     * @var array
     */
    protected static $middleware = [];

    /**
     * Add the specified middleware.
     *
     * @param string $name
     * @param callable $callable
     */
    public static function addMiddleware(string $name, callable $callable)
    {
        static::$middleware[$name] = $callable;
    }

    /**
     * Remove the specified middleware.
     *
     * @param string $name
     */
    public static function removeMiddleware(string $name)
    {
        unset(static::$middleware[$name]);
    }

    /**
     * Apply middleware before calling the specified SOAP function.
     *
     * @param string $function
     * @param array $arguments
     * @return mixed
     */
    public function __call($function, $arguments)
    {
        $this->applyMiddleware();

        return parent::__call($function, $arguments);
    }

    /**
     * Apply the middleware.
     */
    protected function applyMiddleware()
    {
        $headers = [];

        foreach(static::$middleware as $middleware) {
            $headers = $middleware($headers);
        }

        $this->__setSoapHeaders($headers);
    }
}
