<?php

namespace Pace;

use Doctrine\Common\Inflector\Inflector;

class Type
{
    /**
     * Object type names with adjacent uppercase letters.
     *
     * @var array
     */
    protected static $irregularNames = [
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
        'wipCategory' => 'WIPCategory'
    ];

    /**
     * Convert a name to camel case.
     *
     * @param string $name
     * @return string
     */
    public static function camelize($name)
    {
        return array_search($name, static::$irregularNames) ?: lcfirst($name);
    }

    /**
     * Convert a name to model case.
     *
     * @param string $name
     * @return string
     */
    public static function modelify($name)
    {
        return array_search($name, array_flip(static::$irregularNames)) ?: ucfirst($name);
    }

    /**
     * Get the singular form of a name.
     *
     * @param string $name
     * @return string
     */
    public static function singularize($name)
    {
        return Inflector::singularize($name);
    }
}
