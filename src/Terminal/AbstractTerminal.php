<?php

namespace Phoenix\Terminal;


use Phoenix\TerminalClient;
use Phoenix\BaseAbstract;
use phpseclib\Net\SFTP;

/**
 * @property TerminalClient $client
 * @property string $environment
 * @property SFTP $ssh
 *
 * Class AbstractTerminal
 * @package Phoenix\Terminal
 */
class AbstractTerminal extends BaseAbstract
{
    /**
     * @var
     */
    protected $_client;


    /**
     * AbstractTerminal constructor.
     * @param TerminalClient $client
     */
    public function __construct(TerminalClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    /**
     * @param TerminalClient|null $client
     * @return bool|TerminalClient|null
     */
    protected function client(TerminalClient $client = null)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_client))
                return $this->_client;
            return false;
        }
        return $this->_client = $client;
    }

    /**
     * @return bool|SFTP
     */
    protected function ssh()
    {
        if ($this->client->ssh && method_exists($this->client->ssh, 'isConnected') && $this->client->ssh->isConnected())
            return $this->client->ssh;
        return false;
    }

    /**
     * @return bool|string
     */
    protected function environment()
    {
        if (!empty($this->client->environment))
            return $this->client->environment;
        return false;
    }

    /**
     * @param string $command
     * @return bool|string
     */
    protected function exec(string $command = '')
    {
        return $this->client->exec($command);
    }


    /**
     * @param $origin_dir
     * @param $dest_dir
     * @return bool
     */
    public
    function move_files($origin_dir = '', $dest_dir = '')
    {
        $mainStr = sprintf(" files from <strong>%s</strong> directory to <strong>%s</strong> directory in %s environment.",
            $origin_dir, $dest_dir, $this->environment);
        $error_string = sprintf("Can't move " . $mainStr . ".", $this->environment);
        if (empty($origin_dir)) {
            $this->log(sprintf("%s Origin directory not supplied to function.", $error_string));
            return false;
        }
        if (empty($dest_dir)) {
            $this->log(sprintf("%s Destination directory not supplied to function.", $error_string));
            return false;
        }
        $this->log("Moving " . $mainStr, 'info');
        if (!$this->ssh->is_dir($origin_dir)) {
            $this->log(sprintf("%s Origin directory <strong>%s</strong> doesn't exist.",
                $error_string, $origin_dir));
            return false;
        }
        if (!$this->ssh->is_dir($dest_dir) && !$this->ssh->mkdir($dest_dir)) {
            $this->log(sprintf("%s Failed to create directory at <strong>%s</strong> in %s environment.", $error_string, $dest_dir, $this->environment));
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
        if (strpos($output, "0 status") !== false) {
            $deleted_origin_contents = $this->exec("shopt -s dotglob; rm -r " . $origin_dir . "*; echo $? status");
            if (strpos($deleted_origin_contents, "0 status") !== false) {
                $this->log("Successfully moved " . $mainStr . $this->client->format_output($output . $deleted_origin_contents), 'success');
                return true;
            }
        }
        $this->log("Failed to move " . $mainStr . $this->client->format_output($output . $deleted_origin_contents));
        return false;
    }

    /**
     * @param string $dir
     * @return bool|null
     */
    public
    function dir_is_empty(string $dir = '')
    {
        $error_string = "Can't check if directory empty.";
        if (empty($dir)) {
            $this->log(sprintf("%s No directory supplied to function.", $error_string));
            return false;
        }
        if (!$this->ssh->is_dir($dir)) {
            $this->log(sprintf("%s Directory <strong>%s</strong> doesn't exist in %s environment. You should check if dir exists first.", $error_string, $dir, $this->environment));
            return null;
        }

        $output = $this->exec('find ' . $dir . ' -maxdepth 0 -empty -exec echo {} is empty. \;');
        if (strpos($output, $dir . ' is empty') !== false) {
            return true;
        }
        return false;
    }

    /**
     * @param string $dir
     * @return bool
     */
    public
    function pruneDirTree(string $dir = '')
    {
        $error_string = "Can't prune directories";
        $dirStr = !empty($dir) ? " starting with <strong>" . $dir . "</strong>" : '';
        $this->log(sprintf("Pruning empty directories%s.", $dirStr), 'info');
        if (empty($dir)) {
            $this->log(sprintf("%s No directory supplied to function.", $error_string));
            return false;
        }
        if (!$this->ssh->is_dir($dir)) {
            $this->log(sprintf("%s <strong>%s</strong> is not a directory.", $error_string, $dir));
            return false;
        }
        $root = self::trailing_slash($this->client->root);
        if (self::trailing_slash($dir) == $root || self::trailing_slash(dirname($dir)) == $root) {
            $this->log(sprintf("%s Shouldn't be pruning root directory.", $error_string, $dir));
            return false;
        }
        $continue = true;
        $success = true;
        $upstream_dir = $dir;
        $message = '';
        while ($continue) {
            if ($this->dir_is_empty($upstream_dir)) {
                $deleted_upstream = $this->ssh->delete($upstream_dir, true);
                if ($deleted_upstream) {
                    $message .= sprintf("Deleted empty directory <strong>%s</strong>. ", $upstream_dir);
                    $upstream_dir = dirname($upstream_dir);
                } else {
                    $message .= sprintf("Failed to delete <strong>%s</strong> even though it is empty.", $upstream_dir);
                    $continue = false;
                }
            } else {
                $message .= sprintf("Didn't delete <strong>%s</strong> as it contains files and/or directories.", $upstream_dir);
                $continue = false;
            }
        }
        if ($success) {
            $this->log(sprintf("Successfully pruned <strong>%s</strong> directory tree. ", $dir) . $message, 'success');
            return true;
        }
        $this->log(sprintf("Failed to prune <strong>%s</strong> directory tree.", $dir) . $message);
        return false;
    }

    /**
     * @param string $dir
     * @return bool|string
     */
    public static function trailing_slash(string $dir = '')
    {
        return self::trailing_char($dir);
    }

    /**
     * @param string $dir
     * @param string $char
     * @return bool|string
     */
    public static function trailing_char(string $dir = '', $char = '/')
    {
        if (empty($dir)) return false;
        return rtrim($dir, $char) . $char;
    }

    /**
     * @param string $output
     * @param bool $success
     * @return bool|null
     */
    protected function logFinish($output = '', $success = false, $command = '')
    {
        $action = $this->getCaller();
        if (!empty($action)) {
            $output = $this->client->format_output($output);
            $command = $this->client->formatCommand($command);
            if (!empty($success)) {
                $this->log(sprintf('Successfully %s %s. %s%s', $this->actions[$this->getCaller()]['past'], $this->mainStr(), $command, $output), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s. %s%s', $this->actions[$this->getCaller()]['action'], $this->mainStr(), $command, $output));
            return false;
        }
        return null;
    }
}