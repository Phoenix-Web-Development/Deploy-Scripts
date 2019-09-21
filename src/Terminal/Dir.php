<?php

namespace Phoenix\Terminal;

/**
 * Class Git
 * @package Phoenix\Terminal
 */
class Dir extends AbstractTerminal
{
    /**
     * Prunes directory tree. Deletes empty directory and upstream empty directories stopping when it finds a directory containing files.
     *
     * @param string $dir
     * @return bool
     */
    public
    function prune(string $dir = ''): bool
    {
        $this->mainStr($dir);
        $this->logStart();
        if (!$this->validate($dir))
            return false;

        $continue = true;
        $success = true;
        $upstream_dir = $dir;
        $message = '';
        $actuallyDeletedSomething = false;
        while ($continue) {
            if ($this->isDirEmpty($upstream_dir)) {
                $deleted_upstream = $this->deleteFile($upstream_dir, true);
                if ($deleted_upstream) {
                    $actuallyDeletedSomething = true;
                    $message .= sprintf('<br>Deleted empty directory <strong>%s</strong>. ', $upstream_dir);
                    $upstream_dir = dirname($upstream_dir);
                } else {
                    $message .= sprintf('<br>Failed to delete <strong>%s</strong> even though it is empty.', $upstream_dir);
                    $continue = false;
                    $success = false;
                }
            } else {
                if ($actuallyDeletedSomething)
                    $message .= sprintf("<br>Didn't delete <strong>%s</strong> as it contains files and/or directories.", $upstream_dir);
                else
                    $message .= "Didn't actually delete anything.";
                $continue = false;
            }
        }

        return $this->logFinish($success, $message);
    }


    /**
     * @param string $dir
     * @return bool|null
     */
    protected function validate(string $dir = ''): ?bool
    {
        if (isset($this->_validated))
            return $this->_validated;

        if (empty($dir))
            return $this->logError('No directory supplied to function.');

        if (!$this->is_dir($dir))
            return $this->logError(sprintf('<strong>%s</strong> is not a directory.', $dir));

        if ($this->inSanityList($dir))
            return $this->logError("Shouldn't be pruning directory in sanity list.");

        return $this->_validated = true;
    }


    protected
    function mainStr(string $dir = '')
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $dirStr = !empty($dir) ? ' starting with <strong>' . $dir . '</strong>' : '';
        $envStr = !empty($this->environment) ? ' in ' . $dir . ' environ' : '';

        return $this->_mainStr[$action] = sprintf('empty directories%s%s', $envStr, $dirStr);
    }


    /**
     * Do not use. Hasn't been OOPified
     *
     * @param $origin_dir
     * @param $dest_dir
     * @return bool
     */
    public
    function move_files($origin_dir = '', $dest_dir = ''): bool
    {
        $mainStr = sprintf(' files from <strong>%s</strong> directory to <strong>%s</strong> directory in %s environment.',
            $origin_dir, $dest_dir, $this->environment);
        $error_string = sprintf("Can't move " . $mainStr . '.', $this->environment);
        if (empty($origin_dir)) {
            $this->log(sprintf('%s Origin directory not supplied to function.', $error_string));
            return false;
        }
        if (empty($dest_dir)) {
            $this->log(sprintf('%s Destination directory not supplied to function.', $error_string));
            return false;
        }
        $this->log('Moving ' . $mainStr, 'info');
        if (!$this->is_dir($origin_dir)) {
            $this->log(sprintf("%s Origin directory <strong>%s</strong> doesn't exist.",
                $error_string, $origin_dir));
            return false;
        }
        if (!$this->is_dir($dest_dir) && !$this->mkdir($dest_dir)) {
            $this->log(sprintf('%s Failed to create directory at <strong>%s</strong> in %s environment.', $error_string, $dest_dir, $this->environment));
            return false;
        }
        //$origin_dir = self::trailing_slash($origin_dir) . '*';
        //$dest_dir = self::trailing_slash($dest_dir);
        /*
        $output = $this->exec(
            'shopt -s dotglob;

            mv --force ' . $origin_dir . ' ' . $dest_dir . ' ;
            echo status is $?'
        );
        */
        $origin_dir = self::trailing_slash($origin_dir);
        $dest_dir = self::trailing_slash($dest_dir);
        $output = $this->exec('(cd ' . $origin_dir . ' && tar c .) | (cd ' . $dest_dir . ' && tar xf -); echo $? status');

        $deleted_origin_contents = '';
        if (strpos($output, '0 status') !== false) {
            $deleted_origin_contents = $this->exec('shopt -s dotglob; rm -r ' . $origin_dir . '*; echo $? status');
            if (strpos($deleted_origin_contents, '0 status') !== false) {
                $this->log('Successfully moved ' . $mainStr . $this->formatOutput($output . $deleted_origin_contents), 'success');
                return true;
            }
        }
        $this->log('Failed to move ' . $mainStr . $this->formatOutput($output . $deleted_origin_contents));
        return false;
    }
}