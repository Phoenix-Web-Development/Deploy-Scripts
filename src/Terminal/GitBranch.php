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
    public function delete(string $worktree = '', string $separate_repo_path = ''): bool
    {
        d($worktree);
        d($separate_repo_path);

        /*
        $this->mainStr($worktree, $separate_repo_path);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;

        $success = ($delete_branch) ? true : false;
        return $this->logFinish($success);
        */
        return false;
    }

    /**
     * Determine whether repo includes a certain branch
     *
     * @param array $args
     * @return bool|null
     */
    public function check(array $args = []): ?bool
    {
        $this->mainStr($args);
        if (!$this->validate($args))
            return null;
        if (empty($args['stream']))
            $args['stream'] = 'down';
        if ($args['stream'] == 'down') {
            $exists = $this->exec('git show-ref --verify refs/heads/' . $args['branch'], $args['worktree']);
            $strFails = array('fatal', 'not a valid ref');
            $strSuccess = 'refs/heads/' . $args['branch'];
        } elseif ($args['stream'] == 'up') {
            //$exists = $this->exec("git branch --remotes --contains " . $args['branch'], $args['worktree']);
            $exists = $this->exec('git branch -a', $args['worktree']);

            $strFails = array('error: malformed object name ' . $args['branch']);
            //$strSuccess = "origin/" . $args['branch'];
            $strSuccess = 'remotes/origin/' . $args['branch'];
        } else
            return $this->logError('Stream should be set to upstream or downstream only.');

        foreach ($strFails as $strFail) {
            if (stripos($exists, $strFail) !== false) {
                return false;
            }
        }
        if (stripos($exists, $strSuccess) !== false)
            return true;
        return null;
    }

    /**
     * @param array $args
     * @return bool|mixed
     */
    public function checkout(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $currentBranch = $this->getCurrent($args);
        if ($currentBranch == $args['branch'])
            $this->logFinish(true, 'Correct branch <strong>' . $currentBranch . '</strong> already checked out');
        $strNewLocalBranch = '';
        $strSetUpstream = '';
        if ($this->check($args) === false) {
            $strNewLocalBranch = '-b ';
            $args['stream'] = 'up';
            if ($this->check($args) === true)
                $strSetUpstream = ' --track origin/' . $args['branch'];
        }
        $command = 'git checkout ' . $strNewLocalBranch . $args['branch'] . $strSetUpstream;
        $output = $this->exec($command, $args['worktree']);

        $success = $this->getCurrent($args) == $args['branch'] ? true : false;
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
    public function hasUpstream(string $worktree = '', string $branch = ''): ?bool
    {
        $args = ['worktree' => $worktree, 'branch' => $branch];
        if (!$this->validate($args))
            return null;
        $upstream = $this->exec('git rev-parse --abbrev-ref ' . $args['branch'] . '@{upstream}', $args['worktree']);
        if (strpos($upstream, "fatal: no upstream configured for branch '" . $args['branch'] . "'") !== false)
            return false;
        if (strpos($upstream, "fatal: no such branch: '" . $args['branch'] . "'") !== false)
            return false;
        if (strpos($upstream, "origin/'" . $args['branch'] . "'") !== false)
            return true;
        return null;
    }


    /**
     * Get current checked-out Git branch
     *
     * @param array $args
     * @return bool|string
     */
    protected function getCurrent(array $args = [])
    {
        $currentBranch = $this->exec('git symbolic-ref --short HEAD', $args['worktree']);
        if (strlen($currentBranch) > 0)
            return $currentBranch;
        return false;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function reset(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $currentBranch = $this->getCurrent($args);
        if ($currentBranch != $args['branch']) {
            if (!$this->check($args))
                return $this->logError(sprintf("Downstream branch <strong>%s</strong> doesn't exist.", $args['branch']));
            $args['stream'] = 'up';
            if (!$this->check($args))
                return $this->logError(sprintf("Upstream branch <strong>%s</strong> doesn't exist.", $args['branch']));
            if (!$this->checkout($args))
                return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $args['branch']));
        }
        $command = 'git reset --hard origin/' . $args['branch'];
        $output = $this->exec($command, $args['worktree']);
        $success = strpos($output, 'HEAD is now at') !== false ? true : false;
        return $this->logFinish($success, $output, $command);
    }


    /**
     * Performs git pull on nominated branch
     *
     * @param array $args
     * @return bool|null
     */
    public function pull(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $this->exec('git fetch --all', $args['worktree']);
        $changes = $this->getChanges($args);
        if (!empty($changes))
            return $this->logError('Uncommitted changes in Git repo. ' . $this->formatOutput($changes));
        $args['stream'] = 'up';
        if ($this->check($args) === false)
            return $this->logError(sprintf('No upstream branch called <strong>%s</strong>.', $args['branch']));
        $currentBranch = $this->getCurrent($args);
        if ($currentBranch != $args['branch'])
            $currentBranch = $this->checkout($args);
        if ($currentBranch != $args['branch'])
            return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $args['branch']));
        $command = 'git pull --verbose;';
        $output = $this->exec($command, $args['worktree']);

        $success = false;
        $errorStrings = array('error: ', 'would be overwritten');
        $successStrings = array('Already up to date', 'Fast-forward');
        foreach ($successStrings as $successStr) {
            if (strpos($output, $successStr) !== false) {
                $success = true;
                break;
            }
        }
        foreach ($errorStrings as $errorStr) {
            if (stripos($output, $errorStr) !== false) {
                $success = false;
                break;
            }
        }
        return $this->logFinish($success, $output, $command);
    }


    /**
     * Commits and pushes to upstream.
     * Checks out nominated branch if a different branch is checked out
     * Creates new branch if nominated branch doesn't exist.
     *
     * @param array $args
     * @return bool|null
     */
    public function commit(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $args['stream'] = 'up';
        $upstreamBranch = $this->check($args);
        if ($this->getChanges($args) === false && $upstreamBranch === true)
            return $this->logFinish(true, 'No changes in repository to commit.');
        $this->exec('git fetch --all', $args['worktree']);

        $currentBranch = $this->getCurrent($args);
        if ($args['branch'] == 'current')
            $args['branch'] = $currentBranch;
        if ($currentBranch != $args['branch'])
            if (!$this->checkout($args))
                return $this->logError(sprintf("Couldn't checkout nominated branch <strong>%s</strong>. ", $args['branch']));
        $strNewRemoteBranch = ($upstreamBranch === false) ? '--set-upstream origin ' . $args['branch'] . ' ' : '';
        $commands = "git add . --all;                       
                     git commit -m '" . $args['message'] . "';
                     git push " . $strNewRemoteBranch . '--porcelain;';
        $output = $this->exec('git add . --all', $args['worktree']);
        $output .= $this->exec("git commit -m '" . $args['message'] . "'", $args['worktree']);
        $output .= $this->exec('git push ' . $strNewRemoteBranch . '--porcelain;', $args['worktree']);
        d($output);
        $status = $this->exec('git status', $args['worktree']);
        if (substr(trim($output), -4) === 'Done' && $this->getChanges($args) === false && strpos($status, 'Your branch is ahead of') === false)
            $success = true;
        else $success = false;
        return $this->logFinish($success, $output, $commands);
    }

    /**
     * @param array $args
     * @return bool|string|null
     */
    protected function getChanges(array $args = [])
    {
        $this->mainStr($args);
        if (!$this->validate($args))
            return null;
        $output = $this->exec('git status --porcelain', $args['worktree']);
        if (strlen($output) == 0 || strpos($output, 'nothing to commit, working tree clean') !== false)
            return false;
        return $output;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    protected function validate(array $args = []): ?bool
    {
        if (isset($this->_validated))
            return $this->_validated;

        if (empty($args['worktree']))
            return $this->_validated = $this->logError('<strong>Worktree</strong> missing from method input.');
        if (!$this->client->git()->checkGitWorktree($args['worktree']))
            return $this->_validated = $this->logError(sprintf('Directory <strong>%s</strong> is not a Git worktree.', $args['worktree']));
        if (empty($args['branch']) && $this->getCaller() != 'getCurrent') {
            return $this->_validated = $this->logError('No branch name inputted to method.');
        }
        return $this->_validated = true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function mainStr(array $args = []): string
    {
        if (!empty($this->_mainStr) && func_num_args() === 0)
            return $this->_mainStr;
        $branchStr = !empty($args['branch']) ? ' <strong>' . $args['branch'] . '</strong>' : '';
        $workTreeStr = !empty($args['worktree']) ? sprintf(' with worktree at <strong>%s</strong>', $args['worktree']) : '';
        return $this->_mainStr = sprintf('%s environ Git repository branch%s%s', $this->environment, $branchStr, $workTreeStr);
    }
}