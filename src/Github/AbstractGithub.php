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
     * AbstractGithub constructor.
     * @param GithubClient $client
     */
    public function __construct(GithubClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * @param GithubClient|null $client
     * @return bool|GithubClient
     */
    protected function client(GithubClient $client = null)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_client))
                return $this->_client;
            return false;
        }
        return $this->_client = $client;
    }

    /**
     * @param bool $success
     * @return bool|null
     */
    protected function logFinish($success = false)
    {
        $action = $this->getCaller();
        if (!empty($action)) {
            if (!empty($success)) {
                $this->log(sprintf('Successfully %s %s.', $this->actions[$this->getCaller()]['past'], $this->mainStr()), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s.', $this->getCaller(), $this->mainStr()));
            return false;
        }
        return null;
    }
}