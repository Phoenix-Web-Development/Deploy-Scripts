<?php

namespace Phoenix;

use phpseclib\Net\SFTP;

/**
 *
 * @method Terminal\Git git()
 * @method Terminal\Gitignore gitignore()
 * @method Terminal\SSHConfig sshconfig()
 * @method Terminal\SSHConfig ssh_config()
 * @method Terminal\SSHKey sshkey()
 * @method Terminal\SSHKey ssh_key()
 * @method Terminal\WP wp()
 * @method Terminal\WP wordpress()
 * @method Terminal\WP_CLI wp_cli()
 * @method Terminal\WP_CLI wpcli()
 * @method Terminal\WP_CLI wordpress_cli()
 * @method Terminal\WP_CLI wordpresscli()
 * @method Terminal\WP_DB wp_db()
 * @method Terminal\WP_DB wpdb()
 * @method Terminal\WP_DB wordpress_db()
 * @method Terminal\WP_DB wordpressdb()
 *
 * @property array $prompt
 * @property \phpseclib\Net\SFTP $ssh
 *
 * Class Terminal
 */
class TerminalClient extends Base
{
    public $environment;

    private $_prompt;

    private $_ssh;

    public $ssh;

    /**
     * Terminal constructor.
     * @param string $environment
     */
    public function __construct(string $environment = 'live')
    {
        parent::__construct();
        $this->environment = $environment;
        return true;
    }

    /**
     * @param $name
     * @param $args
     * @return bool|WP_CLI|WP_DB|WP
     */
    public function __call($name, $args)
    {
        $api = $this->api($name);
        if (!empty($api))
            return $api;
        $this->log(sprintf("Undefined method <strong>%s</strong> called from TerminalClient ", $name));
        return false;
    }

    /**
     * @param \phpseclib\Net\SFTP|null $ssh
     * @return bool|\phpseclib\Net\SFTP
     */
    public function set_ssh(\phpseclib\Net\SFTP $ssh = null)
    {
        if (empty($ssh))
            return false;
        $this->ssh = $ssh;
        sleep(1);
        $this->prompt();
        return $this->ssh = $ssh;
    }

    protected function prompt()
    {
        if (!empty($this->_prompt))
            return $this->_prompt;
        $prompt = $this->exec('echo "$PS1"');
        $prompt = str_replace('\u', trim($this->api()->exec('whoami')), $prompt);
        $prompt = str_replace('\h', trim($this->api()->exec('hostname -a')), $prompt);
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
     * @param string $command
     * @param bool $format
     * @return bool|string
     */
    /*
    protected function exec(string $command = '', bool $format = false)
    {
        if (isset($this->ssh) && $this->ssh->isConnected()) {
            if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
                $this->log(sprintf("%s environment SSH exec() failed as you aren't authenticated. Exec() called by <code>%s()</code> function.",
                    ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
                return false;
            }
            $this->ssh->enablePTY();
            $this->ssh->exec($command);
            $output = $this->ssh->read();

            $this->ssh->disablePTY();


        } elseif ($this->environment == 'local') {
            exec($command, $raw_outputs);
            $output = implode('<br>', $raw_outputs);
        } else {
            $this->log(sprintf('<code>exec()</code> failed in %s environment terminal.', $this->environment));
            return false;
        }
        return $format ? $this->format_output($output) : $output;
    }
*/
    protected function read_write(array $commands = array(), bool $format = false)
    {

        if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
            $this->log(sprintf("%s environment SSH read_write() failed as you aren't authenticated. <code>read_write()</code> called by <code>%s()</code> function.",
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
     * @param $name
     * @return bool|Terminal\Gitignore|Terminal\WP|Terminal\WP_CLI|Terminal\WP_DB
     */
    public function api($name = '')
    {
        $name = strtolower($name);
        switch ($name) {
            case 'git':
                $api = new Terminal\Git($this);
                break;
            case 'gitignore':
                $api = new Terminal\Gitignore($this);
                break;
            case 'sshconfig':
            case 'ssh_config':
                $api = new Terminal\SSHConfig($this);
                break;
            case 'sshkey':
            case 'ssh_key':
                $api = new Terminal\SSHKey($this);
                break;
            case 'wp':
            case 'wordpress':
                $api = new Terminal\WP($this);
                break;
            case 'wp_cli':
            case 'wpcli':
            case 'wordpress_cli':
            case 'wordpresscli':
                $api = new Terminal\WP_CLI($this);
                break;
            case 'wp_db':
            case 'wpdb':
            case 'wordpress_db':
            case 'wordpressdb':
                $api = new Terminal\WP_DB($this);
                break;
            default:
                $api = new Terminal\AbstractTerminal($this);
        }
        $error_string = sprintf("Can't execute <code>%s</code> terminal method in %s environment.",
            $name, $this->environment);
        if (empty($api)) {
            $this->log($error_string . " No method available.");
            return false;
        }
        if (isset($this->ssh) || $this->environment == 'local') {
            return $api;
            //return call_user_func_array(array($this, $method), $arguments);
        }
        $this->log($error_string . " No SSH connection was established.");
        return false;
    }

    protected
    function localSSHKey(string $action = 'create', string $key_name = 'id_rsa', string $passphrase = '')
    {
        $output = $this->exec(sprintf('sudo /home/james/PhpstormProjects/Deploy/Project/bash/ssh-keygen.sh %s %s /home/james/.ssh/',
            $passphrase, $key_name, '/home/james/.ssh/'));
        echo $output;
        return $output;
    }

    protected
    function dir_exists(string $dir = '')
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

    protected
    function virtualHost(string $action = 'create', string $domain = '', string $directory = '')
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environment virtual host stuff.", $this->environment)))
            return false;

        $error_string = sprintf("Can't %s virtual host.", $action);
        $output = $this->exec(sprintf('sudo ../bash/virtualhost.sh %s %s %s', $action, $domain, $directory));
        if (strpos($output, 'You have no permission to run') !== false) {
            $this->log(sprintf("%s Insufficient permissions to run the script. Try adding script to NOPASSWD in visudo.", $error_string));
            return false;
        }
    }
}
