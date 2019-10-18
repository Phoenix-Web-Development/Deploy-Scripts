<?php

namespace Phoenix\Github;

/**
 * Class Repository
 *
 * @package Phoenix\Github
 */
class Repository extends AbstractGithub
{
    /**
     * @param string $repo_name
     * @param string $url
     * @return bool
     */
    public function create(string $repo_name = '', string $url = ''): bool
    {
        $this->mainStr($repo_name);
        $this->logStart();
        if (!$this->validate($repo_name))
            return false;


        if ($this->get($repo_name))
            return $this->logFinish(true, 'No need as repository named <strong>' . $repo_name . '</strong> already exists.');

        $url = !empty($url) && strpos($url, 'https://') !== 0 && strpos($url, 'http://') !== 0 ? 'https://' . $url : $url;
        $created_repo = $this->client->client->repo()->create($repo_name, 'Website of ' . $repo_name, $url,
            false, //false for private
            null, false, false, false, null, true
        );

        $success = (!empty($created_repo) && $created_repo['name'] === $repo_name);
        return $this->logFinish($success);
    }

    /**
     * @param string $repo_name
     * @return bool
     */
    public function remove(string $repo_name = ''): bool
    {
        return $this->delete($repo_name);
    }

    /**
     * @param string $repo_name
     * @return bool
     */
    public function delete(string $repo_name = ''): bool
    {
        $this->mainStr($repo_name);
        $this->logStart();
        if (!$this->validate($repo_name))
            return false;

        if (!$this->get($repo_name))
            return $this->logError(sprintf("Repository named <strong>%s</strong> doesn't exist.", $repo_name), 'warning');
        $this->client->client->repo()->remove($this->client->user, $repo_name);
        //remove method doesn't return anything so check if repo exists. But API sometimes doesn't update straight away so wait a couple seconds.
        sleep(2);
        $success = !$this->get($repo_name) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param $repo_name
     * @return array|bool
     */
    public function get(string $repo_name = '')
    {
        $this->mainStr($repo_name);
        if (!$this->validate($repo_name))
            return false;
        $repos = $this->client->client->user()->myRepositories();
        if (!empty($repos)) {
            foreach ($repos as $repo) {
                if ($repo['name'] === $repo_name) {
                    return $repo;
                }
            }
        }
        return false;
    }

    /**
     * @param string $repo_name
     * @return bool
     */
    protected function validate(string $repo_name = ''): bool
    {
        if (empty($repo_name))
            return $this->logError('Repository name not supplied to method.');
        return true;
    }

    /**
     * @param string $repo_name
     * @return string
     */
    protected function mainStr(string $repo_name = ''): string
    {
        if (!empty($this->_mainStr) && func_num_args() === 0)
            return $this->_mainStr;

        $repo_name = !empty($repo_name) ? sprintf(' named <strong>%s</strong>', $repo_name) : '';
        return $this->_mainStr = ' GitHub repository' . $repo_name;
    }
}