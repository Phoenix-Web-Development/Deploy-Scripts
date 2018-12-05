<?php

namespace Phoenix\Terminal;

use Phoenix\Base;
use Phoenix\TerminalClient;
use phpseclib\Net\SFTP;

/**
 * @property string $environment
 * @property SFTP $ssh
 * @property TerminalClient $client
 *
 * Class AbstractTerminal
 * @package Phoenix\Terminal
 */
class AbstractTerminal extends Base
{

    private $_environment;

    private $_ssh;

    private $_client;

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

        if ($this->ssh && method_exists($this->ssh, 'isConnected') && $this->ssh->isConnected()) {
            if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
                $this->log(sprintf("%s environment SSH exec() failed as you aren't authenticated. Exec() called by <code>%s()</code> function.",
                    ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
                return false;
            }
            d('Executing ' . $command);
            $this->ssh->enablePTY();
            $this->ssh->exec($command);
            $output = $this->ssh->read();

            $this->ssh->disablePTY();

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
            $this->log(sprintf('<code>exec()</code> blegh failed in %s environment terminal.', $this->environment));
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
        if (!empty($output))
            return "<code><strong>Terminal output:</strong> " . $output . "</code>";
        return false;
    }

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


    public function file_exists(string $file = '')
    {
        if (empty($file)) {
            $this->log("Can't check if file exists. No file supplied to function.");
            return false;
        }
        $output = $this->exec('if test -f ' . $file . '; then echo "exist"; fi');
        if (strpos($output, 'exist') !== false)
            return true;
        return false;
    }

    public static function trailing_slash(string $dir = '')
    {
        return self::trailing_char($dir);
    }

    public static function trailing_char(string $dir = '', $char = '/')
    {
        if (empty($dir)) return false;
        return rtrim($dir, $char) . $char;
    }
}