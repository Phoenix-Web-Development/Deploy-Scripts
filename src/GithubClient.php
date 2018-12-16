<?php

namespace Phoenix;

/**
 * @method Github\DeployKey deploykey()
 * @method Github\DeployKey deploy_key()
 * @method Github\Repository repo()
 * @method Github\Repository repository()
 * @method Github\Webhook webhook()
 *
 * @property \Github\Client $client
 * @property $user
 *
 * Class Github
 */
class GithubClient extends BaseClient
{
    public $curl;

    private $_client;

    private $_user;

    public function __construct()
    {
        parent::__construct();
        return true;
    }

    protected function client(\Github\Client $client = null)
    {
        if (!empty($this->_client))
            return $this->_client;
        if (empty($client))
            return false;
        return $this->_client = $client;
    }

    protected function user($user = '')
    {
        if (!empty($this->_user))
            return $this->_user;
        if (empty($user))
            return false;
        return $this->_user = $user;
    }


    public function api($name = '')
    {
        $name = strtolower($name);
        switch ($name) {
            case 'deploykey':
            case 'deploy_key':
                $api = new Github\DeployKey($this);
                break;
            case 'repo':
            case 'repository':
                $api = new Github\Repository($this);
                break;
            case 'webhook':
                $api = new Github\Webhook($this);
                break;
            case '':
                $api = new Github\AbstractGithub($this);
        }
        $error_string = sprintf("Can't execute <code>%s</code> Github method.", $name);
        if (!empty($api))
            return $api;
        $this->log($error_string . " No Github connection was established.");
        return new ErrorAbstract();
    }
}