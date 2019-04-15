<?php

namespace Phoenix\Terminal;

/**
 * Class Git
 * @package Phoenix\Terminal
 */
class Git extends AbstractTerminal
{

    /**
     * @param array $args
     * @return bool|null
     */
    public function clone(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (empty($args['worktree_path']) && empty($args['repo_path']))
            return $this->logError("Both worktree path and repo path missing from method input. Need at least one.");
        if (empty($args['worktree_path']))
            $args['worktree_path'] = $args['repo_path'];
        if (empty($args['repo_path']))
            $args['repo_path'] = $args['worktree_path'];
        if (!$this->isDirClear($args['repo_path'], $args['worktree_path']))
            return false;
        if ($this->isGitWorktree($args['worktree_path']))
            return $this->logError("Already a git worktree in <strong>" . $args['worktree_path'] . "</strong> directory.");
        if ($this->isGitRepo($args['repo_path']))
            return $this->logError("Already a git repository in <strong>" . $args['repo_path'] . "</strong> directory.");

        $successMessage = "Git clone from " . $args['url'] . " successful";

        $separateGitDir = !empty($args['repo_path']) && ($args['repo_path'] != $args['worktree_path']) ? '--separate-git-dir ' . $args['repo_path'] . ' ' : '';
        $command = 'if git clone ' . $separateGitDir . $args["url"] . ' ' . $args["worktree_path"] . '
        then
            echo "' . $successMessage . '"
        else
            echo "Git clone failed"
        fi;';
        $output = $this->exec($command);
        $success = stripos($output, $successMessage) !== false ? true : false;

        return $this->logFinish($success, $output, $command);
    }

    /**
     * Moves Git repository to a new directory, leaving worktree in initial directory.
     *
     * @param string $worktree
     * @param string $separate_repo_path
     * @return bool
     */
    public function move(string $worktree = '', string $separate_repo_path = '')
    {
        $args = ['worktree_path' => $worktree, 'repo_path' => $separate_repo_path];

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (!$this->is_dir($args['repo_path']))
            return $this->logError(sprintf("Repository path directory <strong>%s</strong> doesn't exist.", $args['repo_path']));
        if (!$this->is_dir($separate_repo_path)) {
            $this->ssh->mkdir($separate_repo_path, -1, true);
            if (!$this->is_dir($separate_repo_path))
                return $this->logError(sprintf("Destination directory <strong>%s</strong> doesn't exist and couldn't create it.", $separate_repo_path));
        }
        if (!$this->isDirClear($separate_repo_path, $worktree))
            return false;

        $command = 'git init --separate-git-dir ' . $separate_repo_path . '; git reset --hard origin/master';
        $output = $this->exec($command, $worktree);
        $success = stripos($output, 'Reinitialized existing Git repository') !== false ? true : false;
        return $this->logFinish($success, $output, $command);
    }

    /**
     * Deletes Git Repo but not worktree or .git file at worktree.
     *
     * @param string $worktree
     * @param string $repo_path
     * @return bool
     */
    public function delete(string $repo_path = '')
    {
        $args = ['repo_path' => $repo_path];

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (!$this->isGitRepo($args['repo_path'])) {
            $appendedRepoPath = self::trailing_slash($args['repo_path']) . '.git';
            if (!$this->isGitRepo($appendedRepoPath))
                return $this->logError(
                    sprintf("Nominated directory <strong>%s</strong> and <strong>%s</strong> are not a git repository.",
                        $args['repo_path'], $appendedRepoPath)
                );
            $args['repo_path'] = $appendedRepoPath;
        }
        $success = $this->deleteFile($args['repo_path'], true) ? true : false;
        $this->client->dir()->prune(dirname($args['repo_path']));
        return $this->logFinish($success);
    }

    /**
     * @param string $worktree
     * @return bool|null
     */
    public function purge(string $worktree = '')
    {
        $args = ['worktree_path' => $worktree];
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        $command = 'git rm --force -r .; git clean --force -xd';
        $output = $this->exec($command, $args['worktree_path']);
        $success = (true) ? true : false;
        return $this->logFinish($success, $output, $command);
    }

    /**
     * Little more than a public wrapper for isGitWorktree
     *
     * @param string $worktree
     * @param string $repopath
     * @return bool
     */
    public function checkGitWorktree(string $worktree = '')
    {
        $args = ['worktree_path' => $worktree];
        if (!$this->is_dir($args['worktree_path']))
            return false;
        if (!$this->isGitWorktree($args['worktree_path']))
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

            if (!$this->file_exists($filePath))
                return true;
        }
        $unlockString = "Waiting for %s environment Git repository to unlock failed. Waited <strong>%s</strong> seconds for file <strong>%s</strong> to delete.";
        $this->log(sprintf($unlockString, $this->environment, $i, $filePath));
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
        return $this->isGitThing($dir, 'worktree_path');
    }

    /**
     * @param string $dir
     * @param string $thing
     * @return bool
     */
    protected function isGitThing(string $dir = '', $thing = 'repo')
    {
        if (!$this->is_dir($dir))
            return false;

        switch ($thing) {
            case 'repo':
                $parseFor = '--is-inside-git-dir';
                break;
            case 'worktree_path':
                $parseFor = '--is-inside-work-tree';
                break;
            default:
                return false;
        }
        $output = $this->exec('git rev-parse ' . $parseFor, $dir);

        if (stripos($output, 'true') !== false)
            return true;
        return false;
    }
    //git rev-parse --is-inside-work-tree /home/james/data/Dropbox/htdocs/powertomove/Project/public

    /**
     * @param array $args
     * @return bool|null
     */
    protected function validate(array $args = [])
    {
        if (isset($this->_validated))
            return $this->_validated;
        if (empty($args))
            return $this->_validated = $this->logError("No method input received.");

        $action = $this->getCaller();
        $argsToValidate = [];
        switch ($action) {
            case 'clone':
                $argsToValidate = ['url'];
                break;
            case 'move':
                $argsToValidate = ['worktree_path', 'repo_path'];
                break;
            case 'delete':
                $argsToValidate = ['repo_path'];
                break;
            case 'purge':
                $argsToValidate = ['worktree_path'];
                break;
        }
        foreach ($argsToValidate as $argToValidate) {
            if (empty($args[$argToValidate]))
                return $this->_validated = $this->logError(sprintf("Argument <strong>%s</strong> missing from method input.", $argToValidate));
        }
        if ($action != 'clone') {
            if (!empty($args['worktree_path'])) {
                if (!$this->is_dir($args['worktree_path']))
                    return $this->_validated = $this->logError(sprintf("Worktree path directory <strong>%s</strong> doesn't exist.", $args['worktree_path']));
                if (!$this->isGitWorktree($args['worktree_path']))
                    return $this->_validated = $this->logError(sprintf("Nominated git worktree <strong>%s</strong> is not a git repository.", $args['worktree_path']));
            }
        }


        return $this->_validated = true;
    }

    /**
     * check directory is empty for git repo to be put in
     *
     * @param string $repoPath
     * @param string $worktree
     * @return bool
     */
    protected function isDirClear(string $repoPath = '', string $worktree = '')
    {
        if ($this->is_dir($repoPath) && !$this->isDirEmpty($repoPath)) {
            if ($this->isGitRepo($repoPath)) {
                if (empty($worktree) || $repoPath == $worktree)
                    return $this->logError(sprintf("A Git repository already exists at <strong>%s</strong>", $repoPath));
                $worktree_pointer = $this->exec('git rev-parse --resolve-git-dir ' . self::trailing_slash($worktree) . '.git');
                if ($worktree_pointer == rtrim($repoPath, '/')) {
                    return $this->logError(sprintf("Git repository already exists at <strong>%s</strong> and worktree already points there.", $repoPath), 'warning');
                }
                return $this->logError(sprintf("A Git repository already exists at <strong>%s</strong>. Worktree doesn't point there.", $repoPath));
            }
            return $this->logError(sprintf("Directory already exists at <strong>%s</strong> and contains files.", $repoPath));
        }
        return true;
    }


    protected
    function mainStr(array $args = [])
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $urlStr = !empty($args['url']) ? " at url <strong>" . $args['url'] . "</strong>" : '';
        $worktreePathStr = '';
        if (!empty($args['worktree_path'])) {
            switch ($action) {

                case 'clone':
                    $worktreePathStr = " to <strong>" . $args['worktree_path'] . "</strong>";
                    break;
                case 'move':
                    if (!empty($args['repo_path']))
                        $worktreePathStr = " to <strong>" . $args['repo_path'] . "</strong> from worktree <strong>" . $args['worktree_path'] . "</strong>";
                    break;
                default:
                    $worktreePathStr = " at <strong>" . $args['worktree_path'] . "</strong>";
                    break;
            }
        }
        $branchStr = !empty($args['branch']) ? " on branch <strong>" . $args['branch'] . "</strong>" : '';
        return $this->_mainStr[$action] = sprintf("%s environment Git repository%s%s%s", $this->environment, $urlStr, $worktreePathStr, $branchStr);
    }
}