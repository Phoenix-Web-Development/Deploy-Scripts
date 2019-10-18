<?php

namespace Phoenix\Github;

use Phoenix\BaseAbstract;
use Phoenix\GithubClient;

/**
 * @property GithubClient $client
 *
 * Class AbstractGithub
 * @package Phoenix\Github
 */
class AbstractGithub extends BaseAbstract
{
    /**
     * @var
     */
    protected $_client;

    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * AbstractGithub constructor.
     *
     * @param GithubClient $client
     */
    public function __construct(GithubClient $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * @param GithubClient|null $client
     * @return bool|GithubClient
     */
    protected function client(GithubClient $client = null)
    {
        if (func_num_args() === 0) {
            if (!empty($this->_client))
                return $this->_client;
            return false;
        }
        return $this->_client = $client;
    }
}