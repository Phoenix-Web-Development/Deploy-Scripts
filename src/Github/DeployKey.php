<?php

namespace Phoenix\Github;

/**
 * Class DeployKey
 * @package Phoenix\Github
 */
class DeployKey extends AbstractGithub
{
    /**
     * @param string $repo_name
     * @param string $key_title
     * @param string $public_key
     * @throws \Github\Exception\MissingArgumentException
     */
    public function create(string $repo_name = '', string $key_title = '', string $public_key = '')
    {
        $this->upload($repo_name, $key_title, $public_key);
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @param string $public_key
     * @return bool|null
     * @throws \Github\Exception\MissingArgumentException
     */
    public function upload(string $repo_name = '', string $key_title = '', string $public_key = '')
    {
        $this->mainStr($repo_name, $key_title);
        $this->logStart();
        if (!$this->validate($repo_name, $key_title))
            return false;
        if (empty($public_key))
            return $this->logError("Public key not supplied to method.");

        if ($this->get($repo_name, $key_title))
            return $this->logError(sprintf("Key with title <strong>%s</strong> already exists.", $key_title), 'warning');

        $uploaded_key = $this->client->client->repo()->keys()->create($this->client->user, $repo_name,
            array('title' => $key_title, 'key' => $public_key));
        $success = (!empty($uploaded_key) && $uploaded_key['title'] == $key_title) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @return bool|null
     */
    public function delete(string $repo_name = '', string $key_title = '')
    {
        return $this->remove($repo_name, $key_title);
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @return bool|null
     */
    public function remove(string $repo_name = '', string $key_title = '')
    {
        $this->mainStr($repo_name, $key_title);
        $this->logStart();
        if (!$this->validate($repo_name, $key_title))
            return false;
        $existing_key = $this->get($repo_name, $key_title);
        if (!$existing_key)
            return $this->logError(sprintf("Key titled <strong>%s</strong> doesn't exist.", $key_title), 'warning');
        $this->client->client->repo()->keys()->remove($this->client->user, $repo_name, $existing_key['id']);
        $success = !$this->get($repo_name, $key_title) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @return bool
     */
    public function get(string $repo_name = '', string $key_title = '')
    {
        $this->mainStr($repo_name, $key_title);
        if (!$this->validate($repo_name, $key_title))
            return false;
        $deploy_keys = $this->client->client->repo()->keys()->all($this->client->user, $repo_name);
        if (!empty($deploy_keys)) {
            foreach ($deploy_keys as $key) {
                if ($key['title'] == $key_title) {
                    return $key;
                }
            }
        }
        return false;
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @return bool
     */
    protected function validate(string $repo_name = '', string $key_title = '')
    {
        if (empty($repo_name))
            return $this->logError("Repository name not supplied to method.");
        if (empty($key_title))
            return $this->logError("Key title not supplied to method.");
        return true;
    }

    /**
     * @param string $repo_name
     * @param string $key_title
     * @param string $public_key
     * @return string
     */
    protected function mainStr(string $repo_name = '', string $key_title = '', string $public_key = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $repo_name = !empty($repo_name) ? sprintf(' for repository <strong>%s</strong>', $repo_name) : '';
        $key_title = !empty($url) ? sprintf(' with key title <strong>%s</strong>', $key_title) : '';
        $public_key = !empty($url) ? sprintf(' and public key <strong>%s</strong>', $public_key) : '';
        return $this->_mainStr = " GitHub deploy key" . $repo_name . $key_title . $public_key;
    }
}