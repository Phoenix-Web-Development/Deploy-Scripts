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
        /*
        $this->mainStr($worktree, $separate_repo_path);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;

        $success = ($delete_branch) ? true : false;
        return $this->logFinish('', $success);
        */
    }
    /*
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
    */
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
            $strFails = array('fatal', 'not a valid ref');
            $strSuccess = "refs/heads/" . $branch;
        } elseif ($stream == 'up') {
            $exists = $this->exec($cd . "git branch --remotes --contains " . $branch);
            $strFails = array('error: malformed object name ' . $branch, "origin/master");
            $strSuccess = "origin/" . $branch;
        } else
            return $this->logError("Stream should be set to upstream or downstream only.");
        foreach ($strFails as $strFail) {
            if (stripos($exists, $strFail) !== false)
                return false;
        }
        if (strpos($exists, $strSuccess) !== false)
            return true;
        return null;
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|string
     */
    public function checkout(string $worktree = '', string $branch = '')
    {
        $this->mainStr($worktree, $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $currentBranch = $this->getCurrent($worktree);
        if ($currentBranch == $branch)
            return true;
        $cd = "cd " . $worktree . "; ";
        $strNewLocalBranch = '';
        $strSetUpstream = '';
        if ($this->check($worktree, $branch) === false) {
            $strNewLocalBranch = '-b ';

            if ($this->check($worktree, $branch, 'up') === true)
                $strSetUpstream = "; git branch --set-upstream-to=origin/" . $branch . " " . $branch . "";
        }
        $command = $cd . "git checkout " . $strNewLocalBranch . $branch . $strSetUpstream;
        $output = $this->exec($command);

        $success = ($this->getCurrent($worktree) == $branch) ? true : false;
        $this->logFinish($output, $success, $command);
        if ($success)
            return $branch;
        return false;
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

    /**
     * @param string $worktree
     * @return bool|string
     */
    public function getCurrent(string $worktree = '')
    {
        if (!$this->validate($worktree))
            return false;
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
        if (isset($this->_validated))
            return $this->_validated;
        if (!$this->client->git()->check($worktree))
            return $this->_validated = false;
        //return $this->logError("Nominated git worktree directory is not a git worktree.");
        return $this->_validated = true;
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
        if (!empty($branch))
            $string .= " <strong>" . $branch . "</strong>";
        if (!empty($worktree))
            $string .= sprintf($worktree_str, $worktree);

        return $this->_mainStr[$action] = $string;
    }
}