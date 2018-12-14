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
    function create(string $repo_name = '', string $url = '', string $secret = '')
    {
        $this->mainStr($repo_name);
        $this->logStart();
        if (!$this->validate())
            return false;
        if (empty($url))
            return $this->logError("Url not supplied to method.");

        $params = array(
            'name' => 'web',
            'events' => array('push'),
            'active' => true,
            'config' => array(
                'url' => $url,
                'content_type' => 'json',
                'insecure_ssl' => 0
            )
        );
        if (!empty($secret)) $params['config']['secret'] = $secret;
        $success = $this->client->client->repo()->hooks()->create($this->client->user, $repo_name, $params);
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @return bool|null
     */
    function delete(string $repo_name = '')
    {
        return $this->remove($repo_name);
    }

    /**
     * @param string $repo_name
     * @return bool|null
     */
    function remove(string $repo_name = '', string $url = '')
    {
        $this->mainStr($repo_name);
        $this->logStart();
        if (!$this->validate())
            return false;
        //($username, $repository, $id)
        $hooks = $this->list($repo_name);
        $id = 1;
        $this->client->client->repo()->hooks()->remove($this->client->user, $repo_name, $id);
        return $this->logFinish($success);
    }

    /**
     * @param $repo_name
     * @return bool|null
     */
    function list($repo_name)
    {
        $this->mainStr($repo_name);
        $this->logStart();
        if (!$this->validate())
            return false;
        return $hooks = $this->client->client->repo()->hooks()->all($this->client->user, $repo_name);
    }

    /**
     * @param string $repo_name
     * @return bool
     */
    function validate(string $repo_name = '')
    {
        if (empty($repo_name))
            return $this->logError("Repository name not supplied to method.");
        return true;
    }

    /**
     * @param string $repo_name
     * @return string
     */
    function mainStr($repo_name = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }

        $repo_name = !empty($repo_name) ? sprintf(' in repository <strong>%s</strong>', $repo_name) : '';
        return $this->_mainStr = sprintf(" Github Webhook", $this->environment, $wp_dir);
    }
}