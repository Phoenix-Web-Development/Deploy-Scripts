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
        return $this->logFinish($success);
        */
    }
    /*
        public function create(string $worktree = '', string $branch = '')
        {
            $this->mainStr($worktree);
            if (!$this->validate($worktree))
                return null;
            $currentBranch = trim($this->exec("git checkout -b " . $branch, $worktree));
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
        $args = ['worktree' => $worktree, 'branch' => $branch];
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return null;
        if ($stream == 'down') {
            $exists = $this->exec("git show-ref --verify refs/heads/" . $args['branch'], $args['worktree']);
            $strFails = array('fatal', 'not a valid ref');
            $strSuccess = "refs/heads/" . $args['branch'];
        } elseif ($stream == 'up') {
            $exists = $this->exec("git branch --remotes --contains " . $args['branch'], $args['worktree']);
            $strFails = array('error: malformed object name ' . $args['branch']);
            $strSuccess = "origin/" . $args['branch'];
        } else
            return $this->logError("Stream should be set to upstream or downstream only.");
        d($exists);
        d($strFails);
        d($strSuccess);

        foreach ($strFails as $strFail) {
            if (stripos($exists, $strFail) !== false) {
                d("branch " . $args['branch'] . " doesn't exist");
                return false;
            }
        }
        if (stripos($exists, $strSuccess) !== false) {
            d("branch " . $args['branch'] . " exists");
            return true;
        }
        return null;
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|string
     */
    public function checkout(string $worktree = '', string $branch = '')
    {
        $args = ['worktree' => $worktree, 'branch' => $branch];
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $currentBranch = $this->getCurrent($args['worktree']);
        if ($currentBranch == $args['branch'])
            return true;
        $strNewLocalBranch = '';
        $strSetUpstream = '';
        if ($this->check($args['worktree'], $args['branch']) === false) {
            $strNewLocalBranch = '-b ';

            if ($this->check($args['worktree'], $args['branch'], 'up') === true)
                $strSetUpstream = "; git branch --set-upstream-to=origin/" . $args['branch'] . " " . $args['branch'] . "";
        }
        $command = "git checkout " . $strNewLocalBranch . $args['branch'] . $strSetUpstream;
        $output = $this->exec($command, $args['worktree']);

        $success = ($this->getCurrent($args['worktree']) == $args['branch']) ? true : false;
        $this->logFinish($success, $output, $command);
        if ($success)
            return $args['branch'];
        return false;
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|null
     */
    public function hasUpstream(string $worktree = '', string $branch = '')
    {
        $args = ['worktree' => $worktree, 'branch' => $branch];
        if (!$this->validate($args))
            return null;
        $upstream = $this->exec("git rev-parse --abbrev-ref " . $args['branch'] . "@{upstream}", $args['worktree']);
        if (strpos($upstream, "fatal: no upstream configured for branch '" . $args['branch'] . "'") !== false)
            return false;
        if (strpos($upstream, "fatal: no such branch: '" . $args['branch'] . "'") !== false)
            return false;
        if (strpos($upstream, "origin/'" . $args['branch'] . "'") !== false)
            return true;
        return null;
    }

    /**
     * @param string $worktree
     * @return bool|string
     */
    public function getCurrent(string $worktree = '')
    {
        $args = ['worktree' => $worktree];
        if (!$this->validate($args))
            return false;
        $currentBranch = trim($this->exec("git symbolic-ref --short HEAD", $args['worktree']));
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function reset(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $currentBranch = $this->getCurrent($args['worktree']);
        if ($currentBranch != $args['branch']) {
            if (!$this->check($args['worktree'], $args['branch'], 'down') ||
                $this->check($args['worktree'], $args['branch'], 'up'))
                return $this->logError(sprintf("Upstream and/or downstream branch <strong>%s</strong> doesn't exist.", $args['branch']));
            if (!$this->checkout($args['worktree'], $args['branch']))
                return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $args['branch']));
        }
        $command = 'git reset --hard origin/' . $args['branch'];
        $output = $this->exec($command, $args['worktree']);
        $success = strpos($output, "HEAD is now at") !== false ? true : false;
        return $this->logFinish($success, $output, $command);
    }


    /**
     * Performs git pull on nominated branch
     *
     * @param array $args
     * @return bool|null
     */
    public function pull(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $this->exec("git fetch --all", $args['worktree']);
        $changes = $this->getChanges($args['worktree']);
        if (!empty($changes))
            return $this->logError("Uncommitted changes in Git repo. " . $this->client->format_output($changes));
        if ($this->check($args['worktree'], $args['branch'], 'up') === false)
            return $this->logError(sprintf("No upstream branch called <strong>%s</strong>.", $args['branch']));
        $currentBranch = $this->getCurrent($args['worktree']);
        if ($currentBranch != $args['branch'])
            $currentBranch = $this->checkout($args['worktree'], $args['branch']);
        if ($currentBranch != $args['branch'])
            return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $args['branch']));
        $command = "git pull --verbose;";
        $output = $this->exec($command, $args['worktree']);
        $errorStrs = array('error: ', 'would be overwritten');
        foreach ($errorStrs as $errorStr) {
            if (stripos($output, $errorStr) !== false) {
                $success = false;
                break;
            }
        }
        if (!isset($success)) {
            $successStrs = array('Already up to date', 'Fast-forward');
            foreach ($successStrs as $successStr) {
                if (strpos($output, $successStr) !== false) {
                    $success = true;
                    break;
                }
            }
        }
        return $this->logFinish($success, $output, $command);
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
        $args = ['worktree' => $worktree, 'branch' => $branch];
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $upstreamBranch = $this->check($args['worktree'], $args['branch'], 'up');
        if ($this->getChanges($args['worktree']) === false && $upstreamBranch === true) {
            $this->log("No need to " . $this->mainStr() . ". No changes in repository to commit.", 'info');
            return true;
        }
        $this->exec("git fetch --all", $args['worktree']);

        $currentBranch = $this->getCurrent($args['worktree']);
        $strCheckout = '';
        if ($currentBranch != $args['branch']) {
            //should be on branch master. is on branch dev
            return $this->logError(sprintf("Repository is checked out to wrong branch <strong>%s</strong>. Should be checked out to branch <strong>%s</strong>",
                $currentBranch, $args['branch']));
            /*
                        $this->client->gitBranch()->checkout($worktree);

                        $strNewLocalBranch = '';
                        if ($this->client->gitBranch()->check($worktree, $branch) === false) {
                            $strNewLocalBranch = ' -b ';
                            $newBranch = true;
                        }

                        $strCheckout = "git checkout " . $strNewLocalBranch . $branch . "; ";
            */
        }
        /*
        $strNewRemoteBranch = '';
        if (!empty($newBranch) && $this->client->gitBranch()->check($worktree, $branch, 'up') === true)
            return $this->logError(sprintf("Upstream branch <strong>%s</strong> already exists. Should probably pull from this first.", $branch));
        if (!empty($newBranch) || $this->client->gitBranch()->check($worktree, $branch, 'up') === false || $this->client->gitBranch()->hasUpstream($worktree, $branch) === false)
            $strNewRemoteBranch = ' --set-upstream origin ' . $branch;
        */
        $strNewRemoteBranch = ($upstreamBranch === false) ? '--set-upstream origin ' . $branch . ' ' : '';
        $commands = $strCheckout . "
                        git add . --all;
                        git commit -m '" . $git_message . "';
                        git push " . $strNewRemoteBranch . "--porcelain;";
        $output = $this->exec($commands, $args['worktree']);
        $status = $this->exec("git status", $args['worktree']);
        if (substr(trim($output), -4) === 'Done' && $this->getChanges($args['worktree']) === false && strpos($status, "Your branch is ahead of") === false)
            $success = true;
        else $success = false;
        return $this->logFinish($success, $output, $commands);
    }

    /**
     * @param string $worktree
     * @return bool
     */
    protected function getChanges(string $worktree = '')
    {
        $args = ['worktree' => $worktree];
        $this->mainStr($args);
        if (!$this->validate($args))
            return null;
        $output = $this->exec("git status --porcelain", $args['worktree']);
        if (strlen($output) == 0)
            return false;
        return $output;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    protected function validate(array $args = [])
    {
        if (isset($this->_validated))
            return $this->_validated;

        if (!$this->client->git()->check($args['worktree']))
            return $this->_validated = false;
        if (($this->getCaller() != 'getCurrent')) {
            if (empty($args['branch'])) {
                return $this->_validated = $this->logError("No branch name inputted to method.");
            }
        }
        return $this->_validated = true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function mainStr(array $args = [])
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }
        $branchStr = !empty($args['branch']) ? " <strong>" . $args['branch'] . "</strong>" : '';
        $workTreeStr = !empty($args['worktree']) ? sprintf(" with worktree at <strong>%s</strong>", $args['worktree']) : '';
        return $this->_mainStr = sprintf("%s environment Git repository branch%s%s", $this->environment, $branchStr, $workTreeStr);
    }
}