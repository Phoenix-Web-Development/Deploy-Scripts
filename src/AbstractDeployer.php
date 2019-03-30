<?php

namespace Phoenix;

use Phoenix\BaseAbstract;

/**
 * @property string $environment
 *
 * Class AbstractDeployer
 * @package Phoenix\Terminal
 */
class AbstractDeployer extends BaseAbstract
{
    /**
     * @var
     */
    protected $_client;


    /**
     * AbstractDeployer constructor.
     * @param TerminalClient $client
     */
    public function __construct()
    {
        parent::__construct();
    }


}