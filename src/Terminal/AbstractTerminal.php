<?php

namespace Phoenix\Terminal;

use Phoenix\TerminalClient;
use Phoenix\BaseAbstract;
use phpseclib\Net\SFTP;

/**
 * @property TerminalClient $client
 * @property string $environment
 * @property SFTP $ssh
 * @property string $root
 *
 * @property array $prompt
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
     * @var
     */
    protected $_prompt;

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
     * wrapper function to shorten calls
     *
     * @return bool|string
     */
    protected function root()
    {
        if (!empty($this->client->root))
            return $this->client->root;
        return false;
    }

    /**
     * wrapper function to shorten calls
     *
     * @return bool|SFTP
     */
    protected function ssh()
    {
        if ($this->client->ssh && method_exists($this->client->ssh, 'isConnected') && $this->client->ssh->isConnected())
            return $this->client->ssh;
        return false;
    }

    /**
     * wrapper function to shorten calls
     *
     * @return bool|string
     */
    protected function environment()
    {
        if (!empty($this->client->environment))
            return $this->client->environment;
        return false;
    }

    /**
     * wrapper function to shorten calls
     *
     * @param string $command
     * @param string $startDir
     * @return bool|string
     */
    protected function exec(string $command = '', string $startDir = '')
    {
        return $this->client->exec($command, $startDir);
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
        if (!$this->is_dir($origin_dir)) {
            $this->log(sprintf("%s Origin directory <strong>%s</strong> doesn't exist.",
                $error_string, $origin_dir));
            return false;
        }
        if (!$this->is_dir($dest_dir) && !$this->ssh->mkdir($dest_dir)) {
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
                $this->log("Successfully moved " . $mainStr . $this->formatOutput($output . $deleted_origin_contents), 'success');
                return true;
            }
        }
        $this->log("Failed to move " . $mainStr . $this->formatOutput($output . $deleted_origin_contents));
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
        if (!$this->is_dir($dir)) {
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
     * @param string $path
     * @return bool
     */
    public
    function file_exists(string $path = '')
    {
        if ($this->environment != 'local') {
            return $this->ssh->file_exists($path);
        }
        return file_exists($path);
    }

    /**
     * @param string $dir
     * @return bool
     */
    public
    function is_dir(string $dir = '')
    {
        if (empty($dir))
            return false;
        if ($this->environment != 'local') {
            return $this->ssh->is_dir($dir);
        }
        return is_dir($dir);
    }

    /**
     * Return remote or local size of file in bytes
     *
     * @param string $filepath
     * @return false|int|mixed
     */
    public
    function size(string $filepath = '')
    {
        if ($this->environment != 'local') {
            return $this->ssh->size($filepath);
        }
        return filesize($filepath);
    }

    /**
     * get remote or local file return contents as string
     *
     * @param string $filepath
     * @return false|mixed|string
     */
    public
    function get(string $filepath = '')
    {
        if ($this->environment != 'local') {
            return $this->ssh->get($filepath);
        }
        return file_get_contents($filepath);
    }

    /**
     * upload file or write file locally
     *
     * @param string $filepath
     * @param string $data
     * @param string $mode
     * @return bool|int
     */
    public
    function put(string $filepath = '', string $data = '', $mode = 'string')
    {
        if ($this->environment != 'local') {
            switch ($mode) {
                case 'file':
                    $mode = SFTP::SOURCE_LOCAL_FILE;
                    break;
                case 'string':
                    $mode = SFTP::SOURCE_STRING;
                    break;
            }
            return $this->ssh->put($filepath, $data, $mode);
        }
        if ($mode == 'file')
            $data = file_get_contents($data);

        return file_put_contents($filepath, $data);
    }

    /**
     *
     * delete remote or local file or directory
     *
     * @param string $filepath
     * @param bool $recursive
     * @return bool|false|string
     */
    public
    function deleteFile(string $filepath = '', $recursive = true)
    {
        if (empty($filepath))
            return false;

        //sanity check, nowhere near thorough
        $sanityList =
            ['/', '~/', '/bin', '/bin', '/boot', '/cdrom', '/dev', '/etc', '/home', '/lib', '/lost+found', '/media',
                '/opt', '/proc', '/root', '/run', '/sbin', '/snap', '/srv', '/swapfile', '/sys', '/tmp', '/usr', '/var'
            ];
        $sanityListTrailingSlash = [];
        foreach ($sanityList as $sanityItem) {
            $sanityItemTrailingSlash = self::trailing_slash($sanityItem);
            if ($sanityItem != $sanityItemTrailingSlash)
                $sanityListTrailingSlash[] = $sanityItemTrailingSlash;
        }
        if (in_array($filepath, array_merge($sanityList, $sanityListTrailingSlash)))
            return false;

        if ($this->environment != 'local') {
            return $this->ssh->delete($filepath, $recursive);
        }

        if (!file_exists($filepath))
            return false;

        if (!is_writable($filepath))
            return false;

        $perms = fileperms($filepath);
        $containerPerms = fileperms(dirname($filepath));
        $unixUser = posix_geteuid();
        $unixGroup = posix_getegid();
        if (((empty($perms & 0x0080) || (fileowner($filepath) != $unixUser))
                && (empty($perms & 0x0010) || (filegroup($filepath) != $unixGroup))
                && (empty($perms & 0x0002))) || (
            ((empty($containerPerms & 0x0080) || (fileowner(dirname($filepath)) != $unixUser))
                && (empty($containerPerms & 0x0010) || (filegroup(dirname($filepath)) != $unixGroup))
                && (empty($containerPerms & 0x0002)))
            )
        ) return false;

        if (!is_dir($filepath))
            return unlink($filepath);

        $objects = scandir($filepath);
        foreach ($objects as $object) {
            if (!in_array($object, [".", ".."])) {
                if (is_dir($filepath . "/" . $object))
                    $success = $this->deleteFile($filepath . "/" . $object);
                else
                    $success = unlink($filepath . "/" . $object);
                if (!$success)
                    return false;
            }
        }
        return rmdir($filepath);
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
        if (!$this->is_dir($dir)) {
            $this->log(sprintf("%s <strong>%s</strong> is not a directory.", $error_string, $dir));
            return false;
        }
        $root = self::trailing_slash($this->root);
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
                $deleted_upstream = $this->deleteFile($upstream_dir, true);
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
     * @param bool $success
     * @param string $output
     * @param string $command
     * @return bool|null
     */
    protected function logFinish($success = false, string $output = '', string $command = '')
    {
        $action = $this->getCaller();
        $output = $this->formatOutput($output);
        $command = $this->formatOutput($command, 'command');

        if (!empty($action)) {
            if (!empty($success)) {
                $string = sprintf('Successfully %s %s. %s%s', $this->actions[$this->getCaller()]['past'], $this->mainStr(), $command, $output);
                $messageType = 'success';
                $return = true;
            } else {
                $string = sprintf('Failed to %s %s. %s%s', $this->actions[$this->getCaller()]['action'], $this->mainStr(), $command, $output);
                $messageType = 'error';
                $return = false;
            }
            $string = $this->elementWrap($string);
            $this->log($string, $messageType);
            return $return;
        }
        return null;
    }

    /**
     * @param string $output
     * @param string $type
     * @return bool|string
     */
    public
    function formatOutput(string $output = '', $type = 'output')
    {
        if (empty($output)) {
            return false;
        }

        $append = '</pre>';
        if (substr($output, -strlen($append)) !== $append)
            $output = $output . $append;
        $title = 'Command';
        if ($type == 'output')
            $title = 'Terminal output';

        $prepend = "<pre><strong>" . $title . ":</strong> ";
        if (substr($output, 0, strlen($prepend)) !== $prepend)
            $output = $prepend . $output;

        return '<br>' . $output;
    }

    /**
     * @param string $script
     * @param array $args
     * @return string
     */
    public
    function formatSudoCommand(string $script = '', array $args = [])
    {
        $command = "sudo " . BASH_WRAPPER . " " . $script . " '" . implode("' '", $args) . "'";
        return $command;
    }

    /**
     * @param array $commands
     * @return bool|string
     */
    protected function readWrite(array $commands = array())
    {

        if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
            $this->log(sprintf("%s environment SSH read_write() failed as you aren't authenticated. <code>readWrite()</code> called by <code>%s()</code> function.",
                ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
            return false;
        }

        $prompt = $this->prompt();
        $outputs = array();
        $this->ssh->read($prompt);
        foreach ($commands as $command) {
            if (!empty($command)) {
                $this->ssh->write($command); // note the "\n"
                $output = $this->ssh->read($prompt);
                //echo '<strong>' . $command . ':</strong>' . $output . '<br><br>';
                $output = str_replace($prompt, '', $output);
                $output = str_replace(trim(str_replace('\n', '', $command)), '', $output);
                $output = '<code>' . $command . '</code>' . trim($output);
                $outputs[] = $output;
            }
        }
        return implode('<br>', $outputs);
    }

    /**
     * @return bool|string
     */
    protected function prompt()
    {
        if (!empty($this->_prompt))
            return $this->_prompt;
        $prompt = $this->exec('echo "$PS1"');
        $prompt = str_replace('\u', $this->whoami(), $prompt);
        $prompt = str_replace('\h', trim($this->exec('hostname -a')), $prompt);
        $prompt = str_replace('\w', '~', $prompt);
        $prompt = trim($prompt);
        if (empty($prompt)) {
            $this->log(sprintf("Couldn't work out the %s environment terminal prompt for <code>read()</code> commands.", $this->environment));
            return false;
        }
        $this->log(sprintf("Prompt string for %s environment terminal <code>read()</code> commands set to '<strong>%s</strong>'.", $this->environment, $prompt), 'info');
        return $this->_prompt = $prompt;
    }

    /**
     * @return bool|string
     */
    function whoami()
    {
        return trim($this->exec('whoami')) ?? false;
    }

}