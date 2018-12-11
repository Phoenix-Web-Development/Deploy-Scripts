<?php

namespace Phoenix\Terminal;

use Phoenix\Base;
use Phoenix\TerminalClient;
use phpseclib\Net\SFTP;

/**
 * @property TerminalClient $client
 * @property string $environment
 * @property string $mainStr
 * @property SFTP $ssh
 * @property array $root
 *
 * Class AbstractTerminal
 * @package Phoenix\Terminal
 */
class AbstractTerminal extends Base
{
    /**
     * @var
     */
    protected $_client;

    /**
     * @var
     */
    protected $_environment;

    /**
     * @var
     */
    protected $_mainStr;

    /**
     * @var
     */
    protected $_root;

    /**
     * @var
     */
    protected $_ssh;

    /**
     * AbstractTerminal constructor.
     * @param TerminalClient $client
     */
    public function __construct(TerminalClient $client)
    {
        $this->client($client);
        $this->ssh($client->ssh);
        $this->environment($client->environment);
        parent::__construct();
    }
    /*
        public function __call($method, $arguments)
        {
            $error_string = sprintf("Can't execute <code>%s</code> terminal function in %s environment.", $method, $this->environment);
            if (method_exists($this, $method)) {
                if (isset($this->ssh) || $this->environment == 'local' || $method == 'ssh') {
                    return call_user_func_array(array($this, $method), $arguments);
                }
                $this->log($error_string . " No SSH connection was established.");
                return false;
            }
            $this->log($error_string . " No method available.");
            return false;
        }
    */

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
     * @param SFTP|null $ssh
     * @return bool|SFTP
     */
    protected function ssh(SFTP $ssh = null)
    {
        if (func_num_args() == 0) {
            if (!empty($this->_ssh))
                return $this->_ssh;
            return false;
        }
        return $this->_ssh = $ssh;
    }

    /**
     * @param string|null $environment
     * @return bool|string
     */
    protected function environment(string $environment = null)
    {
        if (func_num_args() == 0)
            return $this->_environment;
        if (is_string($environment))
            return $this->_environment = $environment;
        return false;
    }


    /**
     * @param string $command
     * @param bool $format
     * @return bool|string
     */
    public function exec(string $command = '', bool $format = false)
    {
        $error_string = $this->log(sprintf('<code>exec()</code> failed to execute <code>%s</code> in %s environment terminal. ',
            $command, $this->environment));

        if ($this->ssh && method_exists($this->ssh, 'isConnected') && $this->ssh->isConnected()) {
            if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
                $this->log(sprintf("%s environment SSH exec() failed as you aren't authenticated. Exec() called by <code>%s()</code> function.",
                    ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
                return false;
            }
            $this->log(sprintf('<code>exec()</code> failed in %s environment terminal.', $this->environment));
            $this->ssh->setTimeout(240); //downloading WP can take a while
            d('Executing ' . $command);
            $this->ssh->enablePTY();
            $this->ssh->exec($command);
            if ($this->ssh->isTimeout()) {
                $this->log($error_string . "Timeout reached for <code>exec()</code>.");
                return false;
            }
            $output = $this->ssh->read();
            if ($this->ssh->isTimeout()) {
                $this->log($error_string . "Timeout reached for <code>read()</code>.");
                return false;
            }
            $this->ssh->setTimeout(false); //downloading WP can take a while
            $this->ssh->disablePTY();
            if ($this->ssh->isTimeout()) {
                return false;
            }
            //$output = $this->ssh->read('imogen@r143 [~]');
            //$this->ssh->write($command);
            //$output .= $this->ssh->read('imogen@r143 [~]');
        } elseif ($this->environment == 'local') {
            //exec($command, $output);
            exec($command, $raw_outputs);
            $output = implode('<br>', $raw_outputs);
            /*
            foreach ($raw_outputs as $raw_output) {
                $output .= $raw_output;
            }
            */
        } else {
            $this->log($error_string);
            return false;
        }
        return $format ? $this->format_output($output) : $output;
    }

    /**
     * @param string $output
     * @return bool|string
     */
    public
    function format_output(string $output = '')
    {
        if (!empty($output)) {
            $output = self::trailing_char($output, '</code>');
            $prepend = "<code><strong>Terminal output:</strong> ";
            $output = $prepend . ltrim($output, $prepend);
            return $output;
        }
        return false;
    }

    /**
     * @param string $dir
     * @return bool
     */
    public function dir_exists(string $dir = '')
    {
        if (empty($dir)) {
            $this->log("Can't check if directory exists. No directory supplied to function. ");
            return false;
        }

        $output = $this->exec('if test -d ' . $dir . '; then echo "exist"; fi');
        if (strpos($output, 'exist') !== false)
            return true;
        return false;
    }

    /**
     * @param $origin_dir
     * @param $dest_dir
     * @return bool
     */
    protected
    function move_files($origin_dir = '', $dest_dir = '')
    {
        $error_string = sprintf("Can't move files between directories in %s environment.", $this->environment);
        if (empty($origin_dir)) {
            $this->log(sprintf("%s Origin directory not supplied to function.", $error_string));
            return false;
        }
        if (empty($dest_dir)) {
            $this->log(sprintf("%s Destination directory not supplied to function.", $error_string));
            return false;
        }
        $this->log(sprintf("Moving files from <strong>%s</strong> directory to <strong>%s</strong> directory in %s environment.",
            $origin_dir, $dest_dir, $this->environment), 'info');
        if (!$this->dir_exists($origin_dir)) {
            $this->log(sprintf("%s Origin directory <strong>%s</strong> doesn't exist.",
                $error_string, $origin_dir));
            return false;
        }
        if (!$this->dir_exists($dest_dir))
            $this->exec('mkdir ' . $dest_dir);

        $origin_dir = self::trailing_slash($origin_dir) . '*';
        $dest_dir = self::trailing_slash($dest_dir);

        $output = $this->exec(
            'shopt -s dotglob; 
            mv ' . $origin_dir . ' ' . $dest_dir . ' ;'
        );
    }

    /**
     * @return mixed
     */
    protected function root()
    {
        if (!empty($this->_root))
            return $this->_root;
        $pwd = trim($this->exec('pwd')) ?? false;
        if (!empty($pwd))
            return $this->_root = $pwd;
        $this->log(sprintf("Couldn't get %s environment root directory", $this->environment));
        return false;
    }

    protected
    function dir_is_empty(string $dir = '')
    {
        $error_string = "Can't check if directory empty.";
        if (empty($dir)) {
            $this->log(sprintf("%s No directory supplied to function.", $error_string));
            return false;
        }
        if (!$this->dir_exists($dir)) {
            $this->log(sprintf("%s Directory <strong>%s</strong> doesn't exist in %s environment. You should check if dir exists first.", $error_string, $this->environment));
            return null;
        }

        $output = $this->exec('find ' . $dir . ' -maxdepth 0 -empty -exec echo {} is empty. \;');
        if (strpos($output, $dir . ' is empty') === false) {
            return false;
        }
        return true;
    }

    function whoami()
    {
        $whoami = trim($this->exec('whoami')) ?? false;
        return $whoami;
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

    protected function mainStr()
    {
        return "I probably should have been called";
    }

    /**
     * @param string $arg1
     * @param string $arg2
     * @param string $arg3
     */
    protected function logStart($arg1 = '', $arg2 = '', $arg3 = '')
    {
        $this->log(ucfirst($this->actions[$this->getCaller()]['present']) . ' ' . $this->mainStr($arg1, $arg2, $arg3), 'info');
    }

    /**
     * @param string $output
     * @param bool $success
     * @return bool|null
     */
    protected function logFinish($output = '', $success = false)
    {
        $action = $this->getCaller();
        if (!empty($action)) {
            $output = $this->format_output($output);
            if (!empty($success)) {
                $this->log(sprintf('Successfully %s %s. %s', $this->actions[$this->getCaller()]['past'], $this->mainStr(), $output), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s. %s', $this->getCaller(), $this->mainStr(), $output));
            return false;
        }
        return null;
    }

    /**
     * @param string $error
     * @return bool
     */
    protected function logError($error = '')
    {
        $this->log(sprintf("Can't %s %s. %s", $this->getCaller(), $this->mainStr(), $error));
        return false;
    }

    /**
     * @return bool|mixed
     */
    protected function getCaller()
    {
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        array_shift($dbt);
        foreach ($dbt as $function) {
            $caller = isset($function['function']) ? $function['function'] : null;
            try {
                $reflection = new \ReflectionMethod($this, $caller);
                if ($reflection->isPublic()) {
                    $caller = explode('\\', $caller);
                    return end($caller);
                }
            } catch (\Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
            }
        }
        return false;
    }
}