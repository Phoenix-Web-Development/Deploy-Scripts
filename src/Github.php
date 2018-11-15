<?php

namespace Phoenix;

use Github\Client;

/**
 * @property \Github\Client $client
 * @property $user
 *
 * Class Github
 */
class Github extends Base
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

    /**
     * @param string $action
     * @param string $repo_name
     * @param string $key_title
     * @param string $public_key
     * @return bool
     */
    public function deploy_key(string $action = 'upload', string $repo_name = '', string $key_title = '', string $public_key = '')
    {

        if (!$this->validate_action($action, array('upload', 'get', 'remove'), "Can't do github deploy key stuff."))
            return false;

        $error_string = sprintf("Couldn't %s GitHub deploy key.", $action);
        if (empty($repo_name)) {
            $this->log(sprintf("%s Repo name not supplied to function.", $error_string));
            return false;
        }
        if ($action != 'get')
            $this->log(sprintf("%s GitHub deploy key <strong>%s</strong> for repository <strong>%s</strong>.",
                ucfirst($this->actions[$action]['present']), $key_title, $repo_name), 'info');
        $key_title = $key_title ?? 'Deploy Key';
        switch ($action) {
            case 'upload':
                if (empty($public_key)) {
                    $this->log(sprintf("%s Public key not supplied to function.", $error_string), 'error');
                    return false;
                }
                if ($this->deploy_key('get', $repo_name, $key_title)) {
                    $this->log(sprintf("Can't upload GitHub deploy key. Key named <strong>%s</strong> already exists.", $key_title));
                    return false;
                }
                $uploaded_key = $this->client->api('repo')->keys()->create($this->user, $repo_name,
                    array('title' => $key_title, 'key' => $public_key));
                if (!empty($uploaded_key) && $uploaded_key['title'] == $key_title) {
                    $success = true;
                }
                break;
            case 'remove':
                $existing_key = $this->deploy_key('get', $repo_name, $key_title);
                if (!$existing_key) {
                    $this->log(sprintf("Can't remove GitHub deploy key. Key named <strong>%s</strong> doesn't exist.", $key_title));
                    return false;
                }
                $this->client->api('repo')->keys()->remove($this->user, $repo_name, $existing_key['id']);
                if (!$this->deploy_key('get', $repo_name, $key_title)) {
                    $success = true;
                }
                break;
            case 'get':
                $deploy_keys = $this->client->api('repo')->keys()->all($this->user, $repo_name);
                if (!empty($deploy_keys)) {
                    foreach ($deploy_keys as $key) {
                        if ($key['title'] == $key_title) {
                            return $key;
                        }
                    }
                }
                return false;
                /*
                if (!empty($existing_key)) {
                    $this->log(sprintf("Found GitHub deploy key named <strong>%s</strong>.", $key_title));
                    return $existing_key;
                }
                */
                break;
        }
        $message_string = sprintf('GitHub deploy key named <strong>%s</strong> at Github.', $key_title);
        if (!empty($success)) {
            $this->log(sprintf("Successfully %s %s", $this->actions[$action]['past'], $message_string), 'success');
            return true;
        }
        $this->log(sprintf("Failed to %s %s", $action, $message_string), 'error');
        return false;
    }


    /**
     * @param string $action
     * @param string $repo_name
     * @param string $domain
     * @return bool
     */
    function repo(string $action = 'create', string $repo_name = '', string $domain = '')
    {
        if (!$this->validate_action($action, array('create', 'delete'), "Can't do github repo stuff."))
            return false;
        $this->log(sprintf("%s GitHub repository.", ucfirst($this->actions[$action]['present'])), 'info');
        $error_string = sprintf("Can't %s GitHub repository.", $action);
        if (empty($repo_name)) {
            $this->log(sprintf("%s Repo name not supplied to function.", $error_string));
            return false;
        }
        $repo_exists = $this->find_repo($repo_name); //check if repo already exists
        d($repo_exists);
        switch ($action) {
            case 'create':
                if (!empty($repo_exists)) {
                    $this->log(sprintf("%s Repository named <strong>%s</strong> already exists.", $error_string, $repo_name));
                    return false;
                }
                $domain = !empty($domain) && strpos($domain, 'https://') !== 0 && strpos($domain, 'http://') !== 0 ? 'https://' . $domain : $domain;
                $created_repo = $this->client->api('repo')->create($repo_name, 'Website of ' . $repo_name, $domain,
                    true, //false for private
                    null, false, false, false, null, true
                );
                if (!empty($created_repo) && $created_repo['name'] == $repo_name) {
                    $success = true;
                }
                break;
            case 'delete':
                if (empty($repo_exists)) {
                    $this->log(sprintf("%s Repo named <strong>%s</strong> doesn't exist.", $error_string, $repo_name));
                    return false;
                }

                $this->client->api('repo')->remove($this->user, $repo_name);
                //remove method doesn't return anything so check if repo exists. But API sometimes doesn't update straight away so wait a couple seconds.
                sleep(2);
                if (!$this->find_repo($repo_name)) {
                    $success = true;
                }
                break;
        }
        if (!empty($success)) {
            $this->log(sprintf("Successfully %s GitHub repository named <strong>%s</strong>.",
                $this->actions[$action]['past'], $repo_name), 'success');
            return !empty($created_repo) ? $created_repo : true;
        }
        $this->log(sprintf("Failed to %s GitHub repository.", $action), 'error');
        return false;
    }

    /**
     * @param string $repo_name
     * @return bool
     */
    function find_repo(string $repo_name = '')
    {
        if (empty($repo_name)) {
            $this->log("Can't find GitHub repository. No repository name supplied to function.");
            return false;
        }
        $repos = $this->client->api('user')->myRepositories();
        if (!empty($repos)) {
            foreach ($repos as $repo) {
                if ($repo['name'] == $repo_name) {
                    return $repo;
                }
            }
        }
        return false;
    }

}