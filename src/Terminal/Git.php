<?php

namespace Phoenix\Terminal;

/**
 * Class Git
 * @package Phoenix\Terminal
 */
class Git extends AbstractTerminal
{
    /**
     * Moves Git repository to a new directory, leaving worktree in initial directory.
     *
     * @param string $worktree
     * @param string $separate_repo_path
     * @return bool
     */
    public function move(string $worktree = '', string $separate_repo_path = '')
    {
        $this->mainStr($worktree, $separate_repo_path);
        $this->logStart();
        if (!$this->validate($worktree, $separate_repo_path))
            return false;
        if (!$this->ssh->is_dir($separate_repo_path)) {
            $this->ssh->mkdir($separate_repo_path, -1, true);
            if (!$this->ssh->is_dir($separate_repo_path))
                return $this->logError(sprintf("Destination directory <strong>%s</strong> doesn't exist and couldn't create it.", $separate_repo_path));
        }
        if (!$this->dir_is_empty($separate_repo_path)) {
            if ($this->isGitRepo($separate_repo_path)) {
                $worktree_pointer = $this->exec('git rev-parse --resolve-git-dir ' . self::trailing_slash($worktree) . '.git');
                if ($worktree_pointer == rtrim($separate_repo_path, '/')) {
                    return $this->logError(sprintf("Git repository already exists at <strong>%s</strong> and worktree already points there.", $separate_repo_path), 'warning');
                }
                return $this->logError(sprintf("A Git repository already exists at <strong>%s</strong>. Worktree doesn't point there.", $separate_repo_path));
            }
            return $this->logError(sprintf("Directory already exists at <strong>%s</strong> and contains files.", $separate_repo_path));
        }
        $output = $this->exec('cd ' . $worktree . '; git init --separate-git-dir ' . $separate_repo_path);
        $success = stripos($output, 'Reinitialized existing Git repository') !== false ? true : false;
        return $this->logFinish($output, $success);
    }

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

    /**
     * Performs git pull
     *
     * @param string $worktree
     * @param string $branch
     * @return bool
     */
    public function pull(string $worktree = '', string $branch = 'master')
    {
        $this->mainStr($worktree, '', $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $changes = $this->checkForChanges($worktree);
        if (!empty($changes))
            return $this->logError("Uncommitted changes in Git repo. " . $changes);
        if ($this->isBranch($worktree, $branch, 'up') === false)
            return $this->logError(sprintf("No upstream branch called <strong>%s</strong>", $branch));
        $cd = "cd " . $worktree . "; ";
        $this->exec($cd . "git fetch --all");
        $currentBranch = $this->getCurrentBranch($worktree);


        $strCheckout = '';
        if ($currentBranch != $branch) {
            $strNewLocalBranch = '';
            $strSetUpstream = '';
            if ($this->isBranch($worktree, $branch) === false) {
                $strNewLocalBranch = ' -b ';
                $strSetUpstream = "git branch --set-upstream-to=origin/" . $branch . " " . $branch . "; ";
            }
            $strCheckout = "git checkout " . $strNewLocalBranch . $branch . "; " . $strSetUpstream;
        }
        $commands = $cd . $strCheckout . " git pull --porcelain;";
        d($commands);
        $output = $this->exec($commands);
        d($output);
        $success = strpos($output, 'blegh') !== false ? true : false;
        return $this->logFinish($output, $success);
    }

    /**
     * Commits and pushes to upstream.
     * Checks out nominated branch if a different branch is checked out
     * Creates new branch if nominated branch doesn't exist.
     *
     * @param string $worktree
     * @param string $branch
     * @param string $git_message
     * @return bool
     */
    public function commit(string $worktree = '',
                           string $branch = 'master',
                           string $git_message = 'update WordPress core, plugins and/or themes'
    )
    {
        $this->mainStr($worktree, '', $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        if ($this->checkForChanges($worktree) === false)
            return $this->logError("No changes in repository to commit.");
        $cd = "cd " . $worktree . "; ";
        $this->exec($cd . "git fetch --all");

        $currentBranch = $this->getCurrentBranch($worktree);

        $strCheckout = '';
        if ($currentBranch != $branch) {
            $strNewLocalBranch = '';
            if ($this->isBranch($worktree, $branch) === false) {
                $strNewLocalBranch = ' -b ';
                $newBranch = true;
            }

            $strCheckout = "git checkout " . $strNewLocalBranch . $branch . "; ";
        }
        $strNewRemoteBranch = '';
        if (!empty($newBranch) && $this->isBranch($worktree, $branch, 'up') === true)
            return $this->logError(sprintf("Upstream branch <strong>%s</strong> already exists. Should probably pull from this first.", $branch));
        if (!empty($newBranch) || $this->isBranch($worktree, $branch, 'up') === false || $this->branchHasUpstream($worktree, $branch) === false)
            $strNewRemoteBranch = ' --set-upstream origin ' . $branch;
        $commands = $cd . $strCheckout . "
                        git add . --all;
                        git commit -m '" . $git_message . "';
                        git push --porcelain " . $strNewRemoteBranch . ";";
        $output = $this->exec($commands);
        $status = $this->exec($cd . "git status");
        if (substr(trim($output), -4) === 'Done' && $this->checkForChanges($worktree) === false && strpos($status, "Your branch is ahead of") === false)
            $success = true;
        else $success = false;
        return $this->logFinish($output, $success);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    public function checkForChanges(string $worktree = '')
    {
        $this->mainStr($worktree);
        if (!$this->validate($worktree))
            return null;
        $output = $this->exec("cd " . $worktree . "; git status --porcelain");
        d($output);
        if (strlen($output) == 0)
            return false;
        return $output;
    }

    /**
     * Determine whether repo includes a certain branch
     *
     * @param string $worktree
     * @param string $branch
     * @param string $stream
     * @return bool|null
     */
    public function isBranch(string $worktree = '', string $branch = '', string $stream = 'down')
    {
        $this->mainStr($worktree);
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

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|null
     */
    public function branchHasUpstream(string $worktree = '', string $branch = '')
    {
        $this->mainStr($worktree);
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

    public function getCurrentBranch(string $worktree = '')
    {
        $this->mainStr($worktree);
        if (!$this->validate($worktree))
            return null;
        $cd = "cd " . $worktree . "; ";
        $currentBranch = trim($this->exec($cd . "git symbolic-ref --short HEAD"));
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * @param string $dir
     * @return bool
     */
    protected function isGitRepo(string $dir = '')
    {
        return $this->isGitThing($dir, 'repo');
    }

    /**
     * @param string $dir
     * @return bool
     */
    protected function isGitWorktree(string $dir = '')
    {
        return $this->isGitThing($dir, 'worktree');
    }

    /**
     * @param string $dir
     * @param string $thing
     * @return bool
     */
    protected function isGitThing(string $dir = '', $thing = 'repo')
    {
        if (empty($dir))
            return false;
        switch ($thing) {
            case 'repo':
                $parseFor = '--is-inside-git-dir';
                break;
            case 'worktree':
                $parseFor = '--is-inside-work-tree';
                break;
            default:
                return false;
        }
        $output = $this->exec('cd ' . $dir . '; git rev-parse ' . $parseFor);
        if (strpos($output, 'true') !== false)
            return true;
        return false;
    }

    /**
     * @param string $repo_path
     * @param string $worktree
     * @return bool
     */
    protected function validate(string $worktree = '', string $repo_path = '')
    {
        if (empty($worktree))
            return $this->logError("Worktree missing from method input.");
        if (!$this->ssh->is_dir($worktree))
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        if (!$this->isGitWorktree($worktree))
            return $this->logError("Nominated git worktree directory is not a git worktree.");
        $action = $this->getCaller();
        if (in_array($action, array('delete', 'move'))) {
            if (empty($repo_path))
                return $this->logError("Repository path missing from method input.");
        }
        return true;
    }

    /**
     * @param string $repo_path
     * @param string $worktree
     * @return string
     */
    protected function mainStr(string $worktree = '', string $repo_path = '', $branch = '')
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$this->getCaller()]))
                return $this->_mainStr[$this->getCaller()];
        }
        $string = sprintf("%s environment Git repository", $this->environment); //update/commit
        switch ($this->getCaller()) {
            case 'move':
                $repo_path_str = " to <strong>" . $repo_path . "</strong>";
                $worktree_str = " separate from worktree at <strong>%s</strong>";
                break;
            default:
                $repo_path_str = " at <strong>%s</strong>";
                $worktree_str = " with worktree at <strong>%s</strong>";
                break;
        }
        if (!empty($repo_path))
            $string .= sprintf($repo_path_str, $repo_path);
        if (!empty($worktree))
            $string .= sprintf($worktree_str, $worktree);
        if (!empty($branch))
            $string .= " on branch <strong>" . $branch . "</strong>";
        return $this->_mainStr[$this->getCaller()] = $string;
    }
}