<?php

namespace Phoenix\Terminal;


use Phoenix\TerminalClient;
use Phoenix\BaseAbstract;
use phpseclib\Net\SFTP;

/**
 * @property WHMClient $client
 * @property string $environment
 *
 * Class AbstractTerminal
 * @package Phoenix\Terminal
 */
class AbstractWHM extends BaseAbstract
{
    /**
     * @var
     */
    protected $_client;


    /**
     * AbstractTerminal constructor.
     * @param TerminalClient $client
     */
    public function __construct(TerminalClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * @param WHMClient|null $client
     * @return bool|WHMClient|null
     */
    protected function client(WHMClient $client = null)
    {
        if (func_num_args() === 0) {
            if (!empty($this->_client))
                return $this->_client;
            return false;
        }
        return $this->_client = $client;
    }

}