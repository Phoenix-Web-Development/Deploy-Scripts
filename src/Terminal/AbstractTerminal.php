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
     * @var
     */
    private $sanityList;

    /**
     * @var string
     */
    protected $logElement = 'h4';

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
     * @param string $dir
     * @return bool
     */
    public
    function is_readable(string $dir = '')
    {
        if ($this->environment != 'local')
            return $this->ssh->is_readable($dir);
        return $this->is_readable($dir);
    }

    /**
     * @param string $dir
     * @return bool
     */
    public
    function is_writable(string $dir = '')
    {
        if ($this->environment != 'local')
            return $this->ssh->is_writable($dir);
        return is_writable($dir);
    }

    /**
     * @param string $dir
     * @return bool|null
     */
    public
    function isDirEmpty(string $dir = '')
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
        if (file_exists($path))
            return true;
        $command = $this->formatSudoCommand('file-exists', [$path]);
        $output = $this->exec($command);
        if (strpos($output, 'File ' . $path . ' exists') !== false)
            return true;
        return false;
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

        //Directory $dir exists
        if (is_dir($dir))
            return true;

        $command = $this->formatSudoCommand('is-dir', [$dir]);
        $output = $this->exec($command);
        if (strpos($output, 'Directory ' . $dir . ' exists') !== false)
            return true;
        return false;
    }

    /**
     * @param string $filepath
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    public
    function mkdir(string $filepath = '', int $mode = 07777, $recursive = false)
    {
        if ($this->environment != 'local') {
            return $this->ssh->mkdir($filepath, $mode, $recursive);
        }
        return mkdir($filepath, $mode, $recursive);
    }

    /**
     * @param $filepath
     * @param $mode
     * @param bool $recursive
     * @return bool|mixed
     */
    function chmod($filepath, $mode, $recursive = false)
    {
        if ($this->environment != 'local') {
            return $this->ssh->chmod($mode, $filepath, $recursive);
        }
        return chmod($filepath, $mode);
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
    protected
    function deleteFile(string $filepath = '', $recursive = true)
    {
        if (empty($filepath))
            return $this->logError('No filepath inputted to <code>deleteFile</code> method');

        if ($this->inSanityList($filepath))
            return $this->logError(sprintf("Won't delete <strong>%s</strong> as it's in the sanity list.", $filepath));

        if ($this->environment != 'local') {
            return $this->ssh->delete($filepath, $recursive);
        }

        if (!$this->file_exists($filepath) && !$this->is_dir($filepath))
            return $this->logError(sprintf("Can't delete <strong>%s</strong> as it doesn't exist.", $filepath));

        if (!$this->is_writable($filepath))
            return $this->logError(sprintf("Can't delete <strong>%s</strong> as it isn't writable. Probably insufficient permissions.", $filepath));

        $perms = fileperms($filepath);

        $unixUser = posix_geteuid();
        $unixGroup = posix_getegid();
        if (((empty($perms & 0x0080) || (fileowner($filepath) != $unixUser))
            && (empty($perms & 0x0010) || (filegroup($filepath) != $unixGroup))
            && (empty($perms & 0x0002)))
        ) return $this->logError(sprintf("Can't delete <strong>%s</strong>. Insufficient permissions to delete it.", $filepath));

        $containerPerms = fileperms(dirname($filepath));
        if (((empty($containerPerms & 0x0080) || (fileowner(dirname($filepath)) != $unixUser))
            && (empty($containerPerms & 0x0010) || (filegroup(dirname($filepath)) != $unixGroup))
            && (empty($containerPerms & 0x0002)))
        ) return $this->logError(sprintf("Can't delete <strong>%s</strong>. Insufficient permissions in containing directory to delete it.", $filepath));

        if (!is_dir($filepath))
            return unlink($filepath);

        $objects = scandir($filepath);
        foreach ($objects as $object) {
            if (!in_array($object, [".", ".."])) {
                $item = $filepath . "/" . $object;
                if (is_dir($item))
                    $success = $this->deleteFile($item);
                else
                    $success = unlink($item);
                if (!$success)
                    return $this->logError(sprintf("Failed to delete <strong>%s</strong>.", $item));
            }
        }
        return rmdir($filepath);
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
                $string = sprintf('Successfully %s %s.', $this->actions[$this->getCaller()]['past'], $this->mainStr());
                $messageType = 'success';
                $return = true;
            } else {
                $string = sprintf('Failed to %s %s.', $this->actions[$this->getCaller()]['action'], $this->mainStr());
                $messageType = 'error';
                $return = false;
            }
            $string = $this->elementWrap($string) . ' ' . $command . $output;
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
        $maxStrLen = 800;
        if (strlen($output) > $maxStrLen * 2)
            $output = substr($output, 0, $maxStrLen) . '...<strong><i>snipped for brevity</i></strong>...' . substr($output, -1 * $maxStrLen, $maxStrLen);
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

    /**
     * Sanity check, nowhere near thorough
     *
     * @param string $filepath
     * @return bool
     */
    protected function inSanityList(string $filepath = '')
    {
        if (empty($filepath))
            return false;

        if (empty($this->sanityList)) {
            $sanityList = [
                '/', '~/', '/bin', '/bin', '/boot', '/cdrom', '/dev', '/etc', '/home', '/lib', '/lost+found', '/media',
                '/opt', '/proc', '/root', '/run', '/sbin', '/snap', '/srv', '/swapfile', '/sys', '/tmp', '/usr', '/var'
            ];
            if (!empty($this->root))
                $sanityList[] = $this->root;
            $sanityListTrailingSlash = [];
            foreach ($sanityList as $sanityItem) {
                $sanityItemTrailingSlash = self::trailing_slash($sanityItem);
                if ($sanityItem != $sanityItemTrailingSlash)
                    $sanityListTrailingSlash[] = $sanityItemTrailingSlash;
            }
            $this->sanityList = array_merge($sanityList, $sanityListTrailingSlash);
        }
        if (in_array($filepath, $this->sanityList))
            return true;
        return false;
    }

}