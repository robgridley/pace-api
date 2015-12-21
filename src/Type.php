<?php

namespace Pace;

use InvalidArgumentException;

class Type
{
    /**
     * The type name.
     *
     * @var string
     */
    protected $name;

    /**
     * The camel-cased type name.
     *
     * @var string
     */
    protected $camelized;

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
     * Create a new instance.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        if (!preg_match('/^([A-Z]+[a-z]*)+$/', $name)) {
            throw new InvalidArgumentException('Type name must be in StudlyCaps.');
        }

        $this->name = $name;
        $this->camelized = array_search($name, static::$irregularNames) ?: lcfirst($name);
    }

    /**
     * Get the camel-cased name.
     *
     * @return string
     */
    public function camelize()
    {
        return $this->camelized;
    }

    /**
     * Create a new instance from a camel-cased name.
     *
     * @param string $name
     * @return Type
     */
    public static function decamelize($name)
    {
        if (array_key_exists($name, static::$irregularNames)) {
            return new static(static::$irregularNames[$name]);
        }

        return new static(ucfirst($name));
    }

    /**
     * Convert the instance to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
