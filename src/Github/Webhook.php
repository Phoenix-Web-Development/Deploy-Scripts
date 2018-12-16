<?php

namespace Phoenix\Github;

/**
 * Class Webhook
 * @package Phoenix\Github
 */
class Webhook extends AbstractGithub
{
    /**
     * @param string $repo_name
     * @param string $url
     * @param string $secret
     * @return bool|null
     * @throws \Github\Exception\MissingArgumentException
     */
    public function create(string $repo_name = '', string $url = '', string $secret = '')
    {
        $this->mainStr($repo_name, $url);
        $this->logStart();
        if (!$this->validate($repo_name, $url, $secret))
            return false;
        $params = array(
            'name' => 'web',
            'events' => array('push'),
            'active' => true,
            'config' => array(
                'url' => $url,
                'content_type' => 'json',
                'insecure_ssl' => 0,
                'secret' => $secret
            )
        );
        if ($this->get($repo_name, $url))
            return $this->logError("Found existing webhook with the same url.", 'warning');
        $success = $this->client->client->repo()->hooks()->create($this->client->user, $repo_name, $params);
        d($success);
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @return bool|null
     */
    public function delete(string $repo_name = '')
    {
        return $this->remove($repo_name);
    }

    /**
     * @param string $repo_name
     * @param string $url
     * @return bool|null
     */
    public function remove(string $repo_name = '', string $url = '')
    {
        $this->mainStr($repo_name, $url);
        $this->logStart();
        if (!$this->validate($repo_name, $url))
            return false;
        $hook_to_remove = $this->get($repo_name, $url);

        if (empty($hook_to_remove))
            return $this->logError(sprintf("Couldn't find hook with url <strong>%s</strong> to remove.", $url), 'warning');
        $success = $this->client->client->repo()->hooks()->remove($this->client->user, $repo_name, $hook_to_remove['id']);
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @param string $url
     * @return bool
     */
    public function get(string $repo_name = '', string $url = '')
    {
        $this->mainStr($repo_name, $url);
        if (!$this->validate($repo_name, $url))
            return false;
        $hooks = $this->client->client->repo()->hooks()->all($this->client->user, $repo_name);
        foreach ($hooks as $hook) {
            if ($hook['config']['url'] == $url) {
                return $hook;
            }
        }
        return false;
    }

    /**
     * @param string $repo_name
     * @param string $url
     * @param string $secret
     * @return bool
     */
    protected function validate(string $repo_name = '', string $url = '', string $secret = '')
    {
        if (empty($repo_name))
            return $this->logError("Repository name not supplied to method.");
        if (empty($url))
            return $this->logError("Url not supplied to method.");
        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE)
            return $this->logError("Invalid url supplied to method.");
        if ($this->getCaller() == 'create') {
            if (empty($secret))
                return $this->logError("Webhook secret not supplied to method.");
            if (strlen($secret) < 9)
                return $this->logError("Webhook secret too short. Should be 8 chars long or greater.");
        }
        return true;
    }

    /**
     * @param string $repo_name
     * @param string $url
     * @return string
     */
    protected function mainStr(string $repo_name = '', string $url = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }

        $repo_name = !empty($repo_name) ? sprintf(' in repository <strong>%s</strong>', $repo_name) : '';
        $url = !empty($url) ? sprintf(' with payload url <strong>%s</strong>', $url) : '';
        return $this->_mainStr = " Github webhook" . $repo_name . $url;
    }
}