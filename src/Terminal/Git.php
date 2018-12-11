<?php

namespace Phoenix\Terminal;

/**
 * Class Git
 * @package Phoenix\Terminal
 */
class Git extends AbstractTerminal
{
    /**
     * @param string $worktree
     * @param string $separate_repo_path
     * @return bool
     */
    public function move(string $worktree = '', string $separate_repo_path = '')
    {

        $this->logStart($separate_repo_path, $worktree);

        if (!$this->ssh->is_dir($separate_repo_path))
            $this->ssh->mkdir($separate_repo_path, -1, true);

        $output = $this->exec('cd ' . $separate_repo_path . '; git rev-parse --is-inside-git-dir');
        if (strpos($output, 'true') !== false)
            return $this->logError(sprintf("Git repository already exists at <strong>%s</strong>. %s", $separate_repo_path, $output));

        $is_empty = $this->dir_is_empty($separate_repo_path);
        if ($is_empty !== null && !$is_empty)
            return $this->logError(sprintf("Directory already exists at <strong>%s</strong> and contains files.", $separate_repo_path));

        $output = $this->exec('cd ' . $worktree . '; git init --separate-git-dir ' . $separate_repo_path, true);
        $success = strpos($output, 'Reinitialized existing Git repository') !== false ? true : false;
        $this->logFinish($output, $success);
    }

    /**
     * @param string $worktree
     * @param string $separate_repo_path
     * @return bool
     */
    public function delete(string $worktree = '', string $separate_repo_path = '')
    {
        $this->logStart($separate_repo_path, $worktree);
        if (!$this->validate($worktree))
            return false;
        $output = $this->exec('rm -rf ' . $separate_repo_path . ';');
        $success = !$this->dir_exists($separate_repo_path) ? true : false;
        $this->logFinish($output, $success);
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool
     */
    public function update(string $worktree = '', string $branch = 'master')
    {
        $this->logStart('', $worktree);
        if (!$this->validate($worktree))
            return false;

        $init = "cd " . $worktree . "; git checkout " . $branch . ";";

        $output = $this->exec($init . " git diff");
        if (strlen($output) > 0)
            return $this->logError("Uncommitted changes in Git repo. " . $output);
        $output = $this->exec($init . " git pull;");
        $success = strpos($output, 'blegh') !== false ? true : false;
        $this->logFinish($output, $success);
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @param string $git_message
     * @return bool
     */
    public function commit(string $worktree = '',
                           string $branch = 'master',
                           string $git_message = 'automated update of WordPress, plugins and themes'
    )
    {
        $this->logStart('', $worktree);
        if (!$this->$this->validate($worktree))
            return false;

        $init = "cd " . $worktree . "; git checkout " . $branch . ";";
        $output = $this->exec($init . "
                git add . --all;
                git commit -m '" . $git_message . "';
                git push origin " . $branch . ";"
        );
        $success = strpos($output, 'blegh') !== false ? true : false;
        $this->logFinish($output, $success);
    }

    /**
     * @param string $repo_path
     * @param string $worktree
     * @return bool
     */
    protected function validate(string $repo_path = '', string $worktree = '')
    {
        if (!$this->dir_exists($worktree)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist.", $worktree));
        }
        $output = $this->exec('cd ' . $repo_path . '; git rev-parse --is-inside-git-dir');
        if (strpos($output, 'true') === false)
            return $this->logError(sprintf("Git repository doesn't exist at <strong>%s</strong>", $repo_path));
        return true;
    }

    /**
     * @param string $repo_path
     * @param string $worktree
     * @return string
     */
    protected function mainStr(string $repo_path = '', string $worktree = '')
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
        return $this->_mainStr[$this->getCaller()] = $string;
    }
}