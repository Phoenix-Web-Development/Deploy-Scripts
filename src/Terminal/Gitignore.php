<?php

namespace Phoenix\Terminal;

/**
 * Class Gitignore
 * @package Phoenix\Terminal
 */
class Gitignore extends AbstractTerminal
{

    /**
     * @param string $worktree
     * @return bool
     */
    public function create(string $worktree = '')
    {
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $filepath = self::trailing_slash($worktree) . '.gitignore';
        if ($this->ssh->file_exists($filepath) && strlen($this->ssh->get($filepath)) > 0)
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> already exists so no need to create.", $worktree), 'warning');
        $success = $this->ssh->put($filepath, BASE_DIR . '/../configs/gitignore-template', \phpseclib\Net\SFTP::SOURCE_LOCAL_FILE) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    public function delete(string $worktree = '')
    {
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $filepath = self::trailing_slash($worktree) . '.gitignore';
        if (!$this->ssh->file_exists($filepath))
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> doesn't exist so no need to delete.", $worktree), 'warning');
        $success = $this->ssh->delete($filepath) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    protected function validate(string $worktree = '')
    {
        if (!$this->ssh->is_dir($worktree)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        }
        if (!$this->client->git()->check($worktree))
            return $this->logError(sprintf("Directory <strong>%s</strong> is not a Git worktree.", $worktree));
        return true;
    }

    /**
     * @param string $worktree
     * @return string
     */
    protected
    function mainStr(string $worktree = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $worktree = !empty($worktree) ? sprintf(' in directory <strong>%s</strong>', $worktree) : '';
        return $this->_mainStr = sprintf("%s environment gitignore file%s", $this->environment, $worktree);
    }
}