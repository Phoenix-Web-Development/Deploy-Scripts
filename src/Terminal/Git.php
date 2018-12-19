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
        $command = 'cd ' . $worktree . '; git init --separate-git-dir ' . $separate_repo_path . '; git reset --hard origin/master';
        $output = $this->exec($command);
        $success = stripos($output, 'Reinitialized existing Git repository') !== false ? true : false;
        return $this->logFinish($output, $success, $command);
    }

    /**
     * Deletes Git Repo and .git file at worktree.
     *
     * @param string $worktree
     * @param string $repo_path
     * @return bool
     */
    public function delete(string $repo_path = '')
    {
        $this->mainStr($repo_path);
        $this->logStart();
        if (!$this->validate($repo_path))
            return false;
        $gitDir = self::trailing_slash($repo_path) . '.git';
        if (!$this->isGitRepo($gitDir))
            return $this->logError("Nominated git directory is not a git repository.");
        $delete_repo = $this->ssh->delete($gitDir, true);
        $prune = $this->client->api()->pruneDirTree($repo_path);
        $success = ($delete_repo && $prune) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $worktree
     * @return bool|null
     */
    public function purge(string $worktree = '')
    {
        $this->mainStr($worktree);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $command = 'cd ' . $worktree . '; git rm --force -r .; git clean --force -xd';
        $output = $this->exec($command);
        $success = (true) ? true : false;
        return $this->logFinish($output, $success, $command);
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool|null
     */
    public function reset(string $worktree = '', string $branch = 'master')
    {
        $this->mainStr($worktree, $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $currentBranch = $this->client->gitBranch()->getCurrent($worktree);
        if ($currentBranch != $branch) {
            if (!$this->client->gitBranch()->check($worktree, $branch, 'down') || $this->client->gitBranch()->check($worktree, $branch, 'up'))
                return $this->logError(sprintf("Upstream and/or downstream branch <strong>%s</strong> doesn't exist.", $branch));
            if (!$this->client->gitBranch()->checkout($worktree, $branch))
                return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $branch));
        }
        $command = 'cd ' . $worktree . '; git reset --hard origin/' . $branch;
        $output = $this->exec($command);
        $success = strpos($output, "HEAD is now at") !== false ? true : false;
        return $this->logFinish($output, $success, $command);
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
        $this->mainStr($worktree, $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $cd = "cd " . $worktree . "; ";
        $this->exec($cd . "git fetch --all");
        $changes = $this->getChanges($worktree);
        if (!empty($changes))
            return $this->logError("Uncommitted changes in Git repo. " . $this->client->format_output($changes));
        if ($this->client->gitBranch()->check($worktree, $branch, 'up') === false)
            return $this->logError(sprintf("No upstream branch called <strong>%s</strong>.", $branch));
        $currentBranch = $this->client->gitBranch()->getCurrent($worktree);
        if ($currentBranch != $branch)
            $currentBranch = $this->client->gitBranch()->checkout($worktree, $branch);
        if ($currentBranch != $branch)
            return $this->logError(sprintf("Couldn't checkout branch <strong>%s</strong>.", $branch));
        $command = $cd . "git pull --verbose;";
        $output = $this->exec($command);
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
        return $this->logFinish($output, $success, $command);
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
        $this->mainStr($worktree, $branch);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;
        $upstreamBranch = $this->client->gitBranch()->check($worktree, $branch, 'up');
        if ($this->getChanges($worktree) === false && $upstreamBranch === true) {
            $this->log("No need to " . $this->mainStr() . ". No changes in repository to commit.", 'info');
            return true;
        }
        $cd = "cd " . $worktree . "; ";
        $this->exec($cd . "git fetch --all");

        $currentBranch = $this->client->gitBranch()->getCurrent($worktree);
        $strCheckout = '';
        if ($currentBranch != $branch) {
            //should be on branch master. is on branch dev
            return $this->logError(sprintf("Repository is checked out to wrong branch <strong>%s</strong>. Should be checked out to branch <strong>%s</strong>",
                $currentBranch, $branch));
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
        $commands = $cd . $strCheckout . "
                        git add . --all;
                        git commit -m '" . $git_message . "';
                        git push " . $strNewRemoteBranch . "--porcelain;";
        $output = $this->exec($commands);
        $status = $this->exec($cd . "git status");
        if (substr(trim($output), -4) === 'Done' && $this->getChanges($worktree) === false && strpos($status, "Your branch is ahead of") === false)
            $success = true;
        else $success = false;
        return $this->logFinish($output, $success, $commands);
    }

    public function check(string $worktree = '')
    {
        $this->mainStr($worktree);
        if (!$this->validate($worktree))
            return false;
        return true;
    }

    /**
     * @param string $repo_path
     * @return bool
     */
    public function waitForUnlock(string $repo_path = '')
    {
        $filePath = self::trailing_slash($repo_path) . ".git/index.lock";
        for ($i = 0; $i <= 15; $i++) {
            sleep(1);
            if (!$this->ssh->file_exists($filePath))
                return true;
        }
        $this->log(sprintf("Waiting for %s environment Git repository to unlock failed. Waited <strong>%s</strong> seconds for file <strong>%s</strong> to delete.", $this->environment, $i, $filePath));
        return false;
    }

    /**
     * @param string $worktree
     * @return bool
     */
    protected function getChanges(string $worktree = '')
    {
        $this->mainStr($worktree);
        if (!$this->validate($worktree))
            return null;
        $output = $this->exec("cd " . $worktree . "; git status --porcelain");
        if (strlen($output) == 0)
            return false;
        return $output;
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
        if (stripos($output, 'true') !== false)
            return true;
        return false;
    }

    /**
     * @param string $dir
     * @return bool
     */
    protected function validate(string $dir = '')
    {
        if (isset($this->_validated))
            return $this->_validated;
        if (empty($dir))
            return $this->_validated = $this->logError("Worktree missing from method input.");
        if (!$this->ssh->is_dir($dir))
            return $this->_validated = $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $dir));
        //$action = $this->getCaller();
        if (!$this->isGitWorktree($dir))
            return $this->_validated = $this->logError("Nominated git worktree directory is not a git worktree.");

        return $this->_validated = true;
    }

    /**
     * @param string $dir
     * @param string $branch
     * @return string
     */
    protected function mainStr(string $dir = '', $branch = '')
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $string = sprintf("%s environment Git repository", $this->environment); //update/commit
        switch ($action) {
            case 'move':
                $dir_path_str = " to <strong>" . $dir . "</strong>";
                break;
            default:
                $dir_path_str = " at <strong>%s</strong>";
                break;
        }
        if (!empty($dir))
            $string .= sprintf($dir_path_str, $dir);
        if (!empty($branch))
            $string .= " on branch <strong>" . $branch . "</strong>";
        return $this->_mainStr[$action] = $string;
    }
}