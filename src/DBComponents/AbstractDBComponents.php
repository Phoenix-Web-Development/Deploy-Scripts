<?php

namespace Phoenix\DBComponents;

use Phoenix\DBComponentsClient;
use Phoenix\BaseAbstract;
use Phoenix\PDOWrap;

/**
 * @property DBComponentsClient $client
 * @property PDOWrap $pdo
 * @property string $environment
 *
 * Class AbstractDBComponents
 * @package Phoenix\DBComponents
 */
class AbstractDBComponents extends BaseAbstract
{
    /**
     * @var
     */
    protected $_client;

    /**
     * AbstractDBComponents constructor.
     * @param DBComponentsClient $client
     */
    public function __construct(DBComponentsClient $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * @param DBComponentsClient|null $client
     * @return bool|DBComponentsClient|null
     */
    protected function client(DBComponentsClient $client = null)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_client)) {
                return $this->_client;
            }
            return false;
        }

        return $this->_client = $client;
    }

    /**
     * @return bool|PDOWrap
     */
    protected function pdo()
    {
        return $this->client->pdo;
    }

    /**
     * wrapper function to shorten calls
     *
     * @return bool|string
     */
    protected function environment()
    {
        if (!empty($this->client->environment))
            return $this->client->environment;
        return false;
    }
}