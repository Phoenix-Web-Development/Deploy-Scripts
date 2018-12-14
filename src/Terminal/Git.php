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
        $this->mainStr($worktree, $separate_repo_path);
        $this->logStart();
        if (!$this->validate($worktree, $separate_repo_path))
            return false;
        if (!$this->ssh->is_dir($separate_repo_path))
            $this->ssh->mkdir($separate_repo_path, -1, true);

        $output = $this->exec('cd ' . $separate_repo_path . '; git rev-parse --is-inside-git-dir');
        if (strpos($output, 'true') !== false)
            return $this->logError(sprintf("Git repository already exists at <strong>%s</strong>. %s", $separate_repo_path, $this->format_output($output)));

        $is_empty = $this->dir_is_empty($separate_repo_path);
        if ($is_empty !== null && !$is_empty)
            return $this->logError(sprintf("Directory already exists at <strong>%s</strong> and contains files.", $separate_repo_path));

        $output = $this->exec('cd ' . $worktree . '; git init --separate-git-dir ' . $separate_repo_path);
        $success = stripos($output, 'Reinitialized existing Git repository') !== false ? true : false;
        return $this->logFinish($output, $success);
    }

    /**
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
        $delete_repo = $this->ssh->delete($separate_repo_path, true);
        if ($this->dir_is_empty(dirname($separate_repo_path)))
            $delete_repo = $this->ssh->delete(dirname($separate_repo_path, true));
        $delete_worktree_ref = $this->ssh->delete($worktree . '/.git');
        $success = $delete_repo && $delete_worktree_ref ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $worktree
     * @param string $branch
     * @return bool
     */
    public function update(string $worktree = '', string $branch = 'master')
    {
        $this->mainStr($worktree);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;

        $init = "cd " . $worktree . "; git checkout " . $branch . ";";

        $output = $this->exec($init . " git diff");
        if (strlen($output) > 0)
            return $this->logError("Uncommitted changes in Git repo. " . $output);
        $output = $this->exec($init . " git pull;");
        $success = strpos($output, 'blegh') !== false ? true : false;
        return $this->logFinish($output, $success);
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
        $this->mainStr($worktree);
        $this->logStart();
        if (!$this->validate($worktree))
            return false;

        $init = "cd " . $worktree . "; git checkout " . $branch . ";";
        $output = $this->exec($init . "
                git add . --all;
                git commit -m '" . $git_message . "';
                git push origin " . $branch . ";"
        );
        $success = strpos($output, 'blegh') !== false ? true : false;
        return $this->logFinish($output, $success);
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
        if (!in_array($this->getCaller(), array('commit', 'update')) && empty($repo_path))
            return $this->logError("Repository path missing from method input.");
        return true;
    }

    /**
     * @param string $repo_path
     * @param string $worktree
     * @return string
     */
    protected function mainStr(string $worktree = '', string $repo_path = '')
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