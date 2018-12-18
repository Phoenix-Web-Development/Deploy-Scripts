<?php

namespace Phoenix\Terminal;


class GitBranch extends AbstractTerminal
{
    /**
     * Deletes Git Repo and .git file at worktree.
     *
     * @param string $worktree
     * @param string $separate_repo_path
     * @return bool
     */
    public function delete(string $worktree = '', string $separate_repo_path = '')
    {
        $this->mainStr($worktree, $separate_repo_path);
        $this->logStart();
        if (!$this->validate($worktree, $separate_repo_path))
            return false;
        if (!$this->isGitRepo($separate_repo_path))
            return $this->logError("Nominated git directory is not a git repository.");
        $delete_repo = $this->ssh->delete($separate_repo_path, true);
        if ($this->dir_is_empty(dirname($separate_repo_path)))
            $delete_repo = $this->ssh->delete(dirname($separate_repo_path, true));
        $delete_worktree_ref = $this->ssh->delete(self::trailing_slash($worktree) . '.git');
        $success = ($delete_repo && $delete_worktree_ref) ? true : false;
        return $this->logFinish('', $success);
    }

    public function create(string $worktree = '', string $branch = '')
    {
        $this->mainStr($worktree);
        if (!$this->validate($worktree))
            return null;
        $cd = "cd " . $worktree . "; ";
        $currentBranch = trim($this->exec($cd . "git checkout -b " . $branch));
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * Determine whether repo includes a certain branch
     *
     * @param string $worktree
     * @param string $branch
     * @param string $stream
     * @return bool|null
     */
    public function check(string $worktree = '', string $branch = '', string $stream = 'down')
    {
        if (!$this->validate($worktree))
            return null;
        $cd = "cd " . $worktree . "; ";
        if ($stream == 'down') {
            $exists = $this->exec($cd . "git show-ref --verify refs/heads/" . $branch);
            $strFail = 'not a valid ref';
            $strSuccess = "refs/heads/" . $branch;
        } elseif ($stream == 'up') {
            $exists = $this->exec($cd . "git branch --remotes --contains " . $branch);
            $strFail = 'error: malformed object name ' . $branch;
            $strSuccess = "origin/" . $branch;
        } else
            return $this->logError("Stream should be set to upstream or downstream only.");
        if (strpos($exists, $strFail) !== false)
            return false;
        if (strpos($exists, $strSuccess) !== false)
            return true;
        return null;
    }

    public function checkout(string $worktree = '', string $branch = '')
    {
        if (!$this->validate($worktree))
            return false;
        $this->exec($cd . "git fetch --all");
        $currentBranch = $this->getCurrent($worktree);
        $cd = "cd " . $worktree . "; ";

        $strCheckout = '';
        if ($currentBranch != $branch) {
            $strNewLocalBranch = '';
            $strSetUpstream = '';
            if ($this->check($worktree, $branch) === false) {
                $strNewLocalBranch = ' -b ';
                $strSetUpstream = "git branch --set-upstream-to=origin/" . $branch . " " . $branch . "; ";
            }
            $strCheckout = "git checkout " . $strNewLocalBranch . $branch . "; " . $strSetUpstream;
        }
        $commands = $cd . $strCheckout . " git pull --porcelain;";

        $output = $this->exec($commands);

    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|null
     */
    public function hasUpstream(string $worktree = '', string $branch = '')
    {
        if (!$this->validate($worktree))
            return null;
        $cd = "cd " . $worktree . "; ";
        $upstream = $this->exec($cd . "git rev-parse --abbrev-ref " . $branch . "@{upstream}");
        if (strpos($upstream, "fatal: no upstream configured for branch '" . $branch . "'") !== false)
            return false;
        if (strpos($upstream, "fatal: no such branch: '" . $branch . "'") !== false)
            return false;
        if (strpos($upstream, "origin/'" . $branch . "'") !== false)
            return true;
        return null;
    }

    public function getCurrent(string $worktree = '')
    {
        if (!$this->validate($worktree))
            return null;
        $cd = "cd " . $worktree . "; ";
        $currentBranch = trim($this->exec($cd . "git symbolic-ref --short HEAD"));
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool
     */
    protected function validate(string $worktree = '', string $branch = '')
    {

        if (empty($worktree))
            return $this->logError("Worktree missing from method input.");
        if (!$this->ssh->is_dir($worktree))
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        if (!$this->client->git()->check($worktree))
            return $this->logError("Nominated git worktree directory is not a git worktree.");
        return true;
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return string
     */
    protected function mainStr(string $worktree = '', $branch = '')
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $string = sprintf("%s environment Git repository branch", $this->environment); //update/commit
        switch ($action) {
            default:
                $worktree_str = " with worktree at <strong>%s</strong>";
                break;
        }

        if (!empty($worktree))
            $string .= sprintf($worktree_str, $worktree);
        if (!empty($branch))
            $string .= " on branch <strong>" . $branch . "</strong>";
        return $this->_mainStr[$action] = $string;
    }
}