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
        if (!$this->is_dir($worktree)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        }
        if (!$this->client->git()->checkGitWorktree($worktree))
            return $this->logError(sprintf("Directory <strong>%s</strong> is not a Git worktree.", $worktree));
        $filepath = self::trailing_slash($worktree) . '.gitignore';
        if ($this->file_exists($filepath) && $this->size($filepath) > 0)
            return $this->logFinish(true, sprintf("Gitignore file at <strong>%s</strong> already exists so no need to create.", $worktree));
        $success = $this->put($filepath, BASE_DIR . '/../configs/gitignore-template', 'file') ? true : false;
        return $this->logFinish($success);
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
        if (!$this->file_exists($filepath))
            return $this->logFinish(true, sprintf("Gitignore file at <strong>%s</strong> doesn't exist so no need to delete.", $worktree));
        $success = $this->deleteFile($filepath) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    protected function validate(string $worktree = '')
    {
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