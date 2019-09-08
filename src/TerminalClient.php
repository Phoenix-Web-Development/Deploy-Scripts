<?php

namespace Phoenix;

use phpseclib\Net\SFTP;

/**
 *
 *
 * @method Terminal\Dir dir()
 * @method Terminal\Dir directory()
 * @method Terminal\DotGitFile dotGitFile()
 * @method Terminal\DotGitFile dot_git_file()
 * @method Terminal\Git git()
 * @method Terminal\GitBranch gitBranch()
 * @method Terminal\GitBranch git_branch()
 * @method Terminal\Gitignore gitignore()
 * @method Terminal\Htaccess htaccess()
 * @method Terminal\SSHConfig sshconfig()
 * @method Terminal\SSHConfig ssh_config()
 * @method Terminal\SSHKey sshkey()
 * @method Terminal\SSHKey ssh_key()
 * @method Terminal\GithubWebhookEndpointConfig github_webhook_endpoint_config()
 * @method Terminal\GithubWebhookEndpointConfig githubWebhookEndpointConfig()
 * @method Terminal\LocalVirtualHost localVirtualHost()
 * @method Terminal\LocalProjectDirSetup localProjectDirSetup()
 * @method Terminal\WP wp()
 * @method Terminal\WP wordpress()
 * @method Terminal\WPCLI wp_cli()
 * @method Terminal\WPCLI wpcli()
 * @method Terminal\WPCLI wordpress_cli()
 * @method Terminal\WPCLI wordpresscli()
 * @method Terminal\WPCLIConfig wp_cli_config()
 * @method Terminal\WPCLIConfig wpcliconfig()
 * @method Terminal\WPCLIConfig wordpress_cli_config()
 * @method Terminal\WPCLIConfig wordpresscliconfig()
 * @method Terminal\WPDB wp_db()
 * @method Terminal\WPDB wpdb()
 * @method Terminal\WPDB wordpress_db()
 * @method Terminal\WPDB wordpressdb()
 *
 * @property SFTP $ssh
 * @property string $root
 *
 * Class Terminal
 * @package Phoenix
 */
class TerminalClient extends BaseClient
{
    /**
     * @var string
     */
    public $environment;

    /**
     * @var
     */
    protected $_root;

    /**
     * @var
     */
    protected $_ssh;

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
     * @param string $name
     * @return bool|ErrorAbstract|Terminal\DotGitFile|Terminal\Git|Terminal\GitBranch|Terminal\Gitignore|Terminal\Htaccess|Terminal\SSHConfig|Terminal\SSHKey
     */
    public function api($name = '')
    {
        $error_string = sprintf("Can't execute <code>%s</code> terminal method in %s environment.",
            $name, $this->environment);
        if ($this->environment != 'local' && (!$this->ssh || !method_exists($this->ssh, 'isConnected') || !$this->ssh->isConnected())) {
            $this->log($error_string . " No SSH connection was established.");
            return new ErrorAbstract();
        }

        $name = strtolower($name);
        switch ($name) {
            case 'dir':
            case 'directory':
                $api = new Terminal\Dir($this);
                break;
            case 'dotgitfile':
            case 'dot_git_file':
                $api = new Terminal\DotGitFile($this);
                break;
            case 'git':
                $api = new Terminal\Git($this);
                break;
            case 'gitbranch':
            case 'git_branch':
                $api = new Terminal\GitBranch($this);
                break;
            case 'gitignore':
                $api = new Terminal\Gitignore($this);
                break;
            case 'htaccess':
                $api = new Terminal\Htaccess($this);
                break;
            case 'sshconfig':
            case 'ssh_config':
                $api = new Terminal\SSHConfig($this);
                break;
            case 'sshkey':
            case 'ssh_key':
                $api = new Terminal\SSHKey($this);
                break;
            case 'github_webhook_endpoint_config':
            case 'githubwebhookendpointconfig':
                $api = new Terminal\GithubWebhookEndpointConfig($this);
                break;
            case 'localvirtualhost':
            case 'virtualhost':
                $api = new Terminal\LocalVirtualHost($this);
                break;
            case 'localprojectdirsetup':
                $api = new Terminal\LocalProjectDirSetup($this);
                break;
            case 'root':
                $api = new Terminal\AbstractTerminal($this);
                return $api->root;
                break;
            case 'wp':
            case 'wordpress':
                $api = new Terminal\WP($this);
                break;
            case 'wp_cli':
            case 'wpcli':
            case 'wordpress_cli':
            case 'wordpresscli':
                $api = new Terminal\WPCLI($this);
                break;
            case 'wp_cli_config':
            case 'wpcliconfig':
            case 'wordpress_cli_config':
            case 'wordpresscliconfig':
                $api = new Terminal\WPCLIConfig($this);
                break;
            case 'wp_db':
            case 'wpdb':
            case 'wordpress_db':
            case 'wordpressdb':
                $api = new Terminal\WPDB($this);
                break;
            case '':
            default:
                $api = new Terminal\AbstractTerminal($this);
                break;
        }
        if (empty($api))
            return false;
        return $api;
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
        $this->_ssh = $ssh;
        sleep(1);
        //$this->prompt();
        return $this->_ssh;
    }

    /**
     * @param string $command
     * @param string $startDir
     * @return bool|string
     */
    public function exec(string $command = '', string $startDir = '')
    {
        $error_string = sprintf('<code>exec()</code> failed to execute command in %s environment terminal. <pre><strong>Commands</strong>:%s</pre>',
            $this->environment, $command);

        if (!empty($startDir)) {
            if (!$this->is_dir($startDir)) {
                $this->log($error_string . "Directory " . $startDir . " doesn't exist.");
                return false;
            }
            if (!$this->is_readable($startDir)) {
                $this->log($error_string . "Directory " . $startDir . " inaccessible.");
                return false;
            }


            $command = 'cd ' . $startDir . '; ' . $command;
        }

        if ($this->environment == 'local') {
            exec($command . " 2>&1", $raw_outputs);
            return trim(implode('<br>', $raw_outputs));
        }

        if ($this->ssh && method_exists($this->ssh, 'isConnected')) {
            if (!$this->ssh->isConnected()) {
                $this->log($error_string . "SSH is not connected.");
                return false;
            }
            if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
                $this->log(sprintf("%s environment SSH exec() failed as you aren't authenticated. Exec() called by <code>%s()</code> function.",
                    ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
                return false;
            }
            $this->ssh->setTimeout(240); //downloading WP can take a while
            //d('Executing ' . $command);
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
            sleep(3);
            $this->ssh->disablePTY();
            return trim($output);
        }

        $this->log($error_string);
        return false;

    }

    /**
     * @param string $root
     * @return array|bool|false|mixed|string
     */
    protected function root(string $root = '')
    {

        if (!empty($root))
            return $this->_root = $root;
        if (!empty($this->_root))
            return $this->_root;

        if ($this->environment == 'local') {
            if (isset($_SERVER['HOME'])) {
                return $this->_root = $_SERVER['HOME'];
            } else {
                if (!empty(getenv("HOME")))
                    return $this->_root = getenv("HOME");
            }
        }
        $root = trim($this->exec('echo ~')) ?? false;
        if (!empty($root) && $root != '~')
            return $this->_root = $root;
        return false;
    }


    /*
    public
    function localSSHKey(string $action = 'create', string $key_name = 'id_rsa', string $passphrase = '')
    {
        $output = $this->exec(sprintf('sudo /home/james/PhpstormProjects/Deploy/Project/bash/ssh-keygen.sh %s %s /home/james/.ssh/',
            $passphrase, $key_name, '/home/james/.ssh/'));
        echo $output;
        return $output;
    }
    */

}
