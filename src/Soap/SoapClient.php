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
    protected static array $middleware = [];

    /**
     * Add the specified middleware.
     *
     * @param string $name
     * @param callable $callable
     */
    public static function addMiddleware(string $name, callable $callable): void
    {
        static::$middleware[$name] = $callable;
    }

    /**
     * Remove the specified middleware.
     *
     * @param string $name
     */
    public static function removeMiddleware(string $name): void
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
    public function __call(string $function, array $arguments): mixed
    {
        $this->applyMiddleware();

        return parent::__soapCall($function, $arguments);
    }

    /**
     * Apply the middleware.
     */
    protected function applyMiddleware(): void
    {
        $headers = [];

        foreach (static::$middleware as $middleware) {
            $headers = $middleware($headers);
        }

        $this->__setSoapHeaders($headers);
    }
}
