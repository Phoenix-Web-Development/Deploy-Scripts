<?php

namespace Phoenix\Terminal;

/**
 * Class Gitignore
 * @package Phoenix\Terminal
 */
class GithubWebhookEndpointConfig extends AbstractTerminal
{

    /**
     * @param string $filepath
     * @param string $worktree_dir
     * @param string $secret
     * @return bool|null
     */
    public function create(string $filepath = '', string $worktree_dir = '', string $secret = '')
    {
        $this->mainStr($filepath, $worktree_dir);
        $this->logStart();
        if (!$this->validate($filepath, $worktree_dir, $secret))
            return false;
        if ($this->ssh->file_exists($filepath) && strlen($this->ssh->get($filepath)) > 0)
            return $this->logError("Config file already exists.", 'warning');
        $data = array(
            'worktree' => $worktree_dir,
            'secret' => $secret
        );
        $success = $this->ssh->put($filepath, json_encode($data)) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $filepath
     * @return bool|null
     */
    public function delete(string $filepath = '')
    {
        $this->mainStr($filepath);
        $this->logStart();
        if (!$this->validate($filepath))
            return false;
        if (!$this->ssh->file_exists($filepath))
            return $this->logError("Config file doesn't exist.", 'warning');

        $success = $this->ssh->delete($filepath) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $filepath
     * @param string $worktree_dir
     * @param string $secret
     * @return bool
     */
    protected function validate(string $filepath = '', string $worktree_dir = '', string $secret = '')
    {
        if (empty($filepath))
            return $this->logError("Filepath for config file missing from method input.");
        if (!$this->ssh->is_dir(dirname($filepath)))
            return $this->logError(sprintf("Config filepath directory <strong>%s</strong> doesn't exist.", dirname($filepath)));
        if ($this->getCaller() == 'create') {
            if (empty($worktree_dir))
                return $this->logError("Git worktree directory missing from method input.");
            if (!$this->ssh->is_dir($worktree_dir))
                return $this->logError(sprintf("Worktree directory <strong>%s</strong> doesn't exist.", $worktree_dir));
            if (empty($secret))
                return $this->logError("Webhook secret missing from method input.");
            if (strlen($secret) < 9)
                return $this->logError("Webhook secret too short. Should be 8 chars long or greater.");
        }
        return true;
    }

    /**
     * @return string
     */
    protected
    function mainStr(string $filepath = '', string $worktree_dir = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $filepath = !empty($filepath) ? sprintf(' at <strong>%s</strong>', $filepath) : '';
        $worktree_dir = !empty($worktree_dir) ? sprintf(' for Git worktree <strong>%s</strong>', $worktree_dir) : '';
        return $this->_mainStr = " " . $this->environment . " environment Github webhook endpoint config file" . $worktree_dir . $filepath;
    }
}