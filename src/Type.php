<?php

namespace Pace;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;

class Type
{
    /**
     * Object types with adjacent uppercase letters.
     *
     * @var array
     */
    protected static array $irregularNames = [
        'apSetup' => 'APSetup',
        'arSetup' => 'ARSetup',
        'crmSetup' => 'CRMSetup',
        'crmStatus' => 'CRMStatus',
        'crmUser' => 'CRMUser',
        'csr' => 'CSR',
        'dsfMediaSize' => 'DSFMediaSize',
        'dsfOrderStatus' => 'DSFOrderStatus',
        'faSetup' => 'FASetup',
        'glAccount' => 'GLAccount',
        'glAccountBalance' => 'GLAccountBalance',
        'glAccountBalanceSummary' => 'GLAccountBalanceSummary',
        'glAccountBudget' => 'GLAccountBudget',
        'glAccountingPeriod' => 'GLAccountingPeriod',
        'glBatch' => 'GLBatch',
        'glDepartment' => 'GLDepartment',
        'glDepartmentLocation' => 'GLDepartmentLocation',
        'glJournalEntry' => 'GLJournalEntry',
        'glJournalEntryAudit' => 'GLJournalEntryAudit',
        'glLocation' => 'GLLocation',
        'glRegisterNumber' => 'GLRegisterNumber',
        'glSchedule' => 'GLSchedule',
        'glScheduleLine' => 'GLScheduleLine',
        'glSetup' => 'GLSetup',
        'glSplit' => 'GLSplit',
        'glSummaryName' => 'GLSummaryName',
        'jmfReceivedMessage' => 'JMFReceivedMessage',
        'jmfReceivedMessagePartition' => 'JMFReceivedMessagePartition',
        'jmfReceivedMessageTransaction' => 'JMFReceivedMessageTransaction',
        'jmfReceivedMessageTransactionPartition' => 'JMFReceivedMessageTransactionPartition',
        'poSetup' => 'POSetup',
        'poStatus' => 'POStatus',
        'rssChannel' => 'RSSChannel',
        'uom' => 'UOM',
        'uomDimension' => 'UOMDimension',
        'uomRange' => 'UOMRange',
        'uomSetup' => 'UOMSetup',
        'uomType' => 'UOMType',
        'wipCategory' => 'WIPCategory',
    ];

    /**
     * Object types with irregular primary keys.
     *
     * @var array
     */
    protected static array $irregularKeys = [
        'FileAttachment' => 'attachment',
    ];

    /**
     * The Doctrine Inflector instance.
     *
     * @var Inflector|null
     */
    protected static ?Inflector $inflector = null;

    /**
     * Convert a name to camel case.
     *
     * @param string $name
     * @return string
     */
    public static function camelize(string $name): string
    {
        return array_search($name, static::$irregularNames) ?: lcfirst($name);
    }

    /**
     * Convert a name to model case.
     *
     * @param string $name
     * @return string
     */
    public static function modelify(string $name): string
    {
        return array_search($name, array_flip(static::$irregularNames)) ?: ucfirst($name);
    }

    /**
     * Get the singular form of a name.
     *
     * @param string $name
     * @return string
     */
    public static function singularize(string $name): string
    {
        if (is_null(static::$inflector)) {
            static::$inflector = InflectorFactory::create()->build();
        }

        return static::$inflector->singularize($name);
    }

    /**
     * Get the primary key for the specified type.
     *
     * @param string $type
     * @return string|null
     */
    public static function keyName(string $type): ?string
    {
        if (array_key_exists($type, static::$irregularKeys)) {
            return static::$irregularKeys[$type];
        }

        return null;
    }
}
