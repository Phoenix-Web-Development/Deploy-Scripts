<?php

namespace Phoenix\Terminal;

/**
 * Class Gitignore
 *
 * @package Phoenix\Terminal
 */
class DotGitFile extends AbstractTerminal
{

    /**
     * @param string $worktree
     * @param string $repo_location
     * @return bool|null
     */
    public function create(string $worktree = '', string $repo_location = ''): ?bool
    {
        $this->mainStr($worktree);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $filepath = self::trailing_slash($worktree) . '.git';
        if ($this->file_exists($filepath) && $this->size($filepath) > 0)
            return $this->logError(sprintf('Dot Git file at <strong>%s</strong> already exists so no need to create.', $worktree), 'warning');
        $success = $this->put($filepath, 'gitdir: ' . self::trailing_slash($repo_location) . '.git');
        return $this->logFinish($success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    public function delete(string $worktree = ''): bool
    {
        $this->mainStr($worktree);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $filepath = self::trailing_slash($worktree) . '.git';
        if (!$this->file_exists($filepath))
            return $this->logError(sprintf("Dot Git file at <strong>%s</strong> doesn't exist so no need to delete.", $worktree), 'warning');
        $success = $this->deleteFile($filepath) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    protected function validate(string $worktree = ''): bool
    {
        if (!$this->is_dir($worktree)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        }
        return true;
    }

    /**
     * @param string $worktree
     * @return string
     */
    protected
    function mainStr(string $worktree = ''): string
    {
        if (!empty($this->_mainStr) && func_num_args() === 0)
            return $this->_mainStr;
        $worktree = !empty($worktree) ? sprintf(' in directory <strong>%s</strong>', $worktree) : '';
        return $this->_mainStr = sprintf('%s environment .git file%s', $this->environ, $worktree);
    }
}