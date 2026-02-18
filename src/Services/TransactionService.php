<?php

namespace Pace\Services;

use Closure;
use Pace\Service;
use Pace\Soap\Middleware\CommitTransaction;
use Pace\Soap\Middleware\RollbackTransaction;
use Pace\Soap\Middleware\StartTransaction;
use Pace\Soap\Middleware\Transaction;
use Pace\Soap\SoapClient;
use SoapFault;
use Throwable;

class TransactionService extends Service
{
    /**
     * Wrap the specified closure in a transaction.
     *
     * @param Closure $callback
     */
    public function transaction(Closure $callback)
    {
        $this->startTransaction();

        try {
            $callback();
        } catch (SoapFault $exception) {
            // Pace has already rolled the transaction back.
            SoapClient::removeMiddleware('transaction');
            throw $exception;
        } catch (Throwable $exception) {
            $this->rollback();
            throw $exception;
        }

        $this->commit();
    }

    /**
     * Start a new transaction.
     *
     * @param int $timeout
     */
    public function startTransaction(int $timeout = 60)
    {
        SoapClient::addMiddleware('startTransaction', new StartTransaction);

        $response = $this->soap->startTransaction(['in0' => $timeout]);

        SoapClient::removeMiddleware('startTransaction');
        SoapClient::addMiddleware('transaction', new Transaction($response->out));
    }

    /**
     * Rollback the transaction.
     */
    public function rollback()
    {
        SoapClient::addMiddleware('rollback', new RollbackTransaction);

        $this->soap->rollback();

        SoapClient::removeMiddleware('rollback');
        SoapClient::removeMiddleware('transaction');
    }

    /**
     * Commit the transaction.
     */
    public function commit()
    {
        SoapClient::addMiddleware('commit', new CommitTransaction);

        $this->soap->commit();

        SoapClient::removeMiddleware('commit');
        SoapClient::removeMiddleware('transaction');
    }
}
