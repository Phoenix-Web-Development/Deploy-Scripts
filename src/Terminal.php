<?php

namespace Phoenix;
//    "kint-php/kint": "0.9",

/**
 * @property array $actions
 *
 * Class Terminal
 */
class Terminal extends Base
{
    /**
     * @var \phpseclib\Net\SSH2
     */
    private $ssh;

    private $ssh_prompt;

    public $environment;

    /**
     * Terminal constructor.
     * @param \phpseclib\Net\SSH2 $ssh
     * @param string $username
     * @param string $password
     * @param string $environment
     */
    public function __construct(string $environment = 'live')
    {
        $this->environment = $environment;
    }

    /**
     * @param $method
     * @param $arguments
     * @return bool|mixed
     */
    public function __call($method, $arguments)
    {
        $error_string = sprintf("Can't execute <code>%s</code> terminal function in %s environment.", $method, $this->environment);
        if (method_exists($this, $method)) {
            if (isset($this->ssh) || $this->environment == 'local') {
                return call_user_func_array(array($this, $method), $arguments);
            }
            $this->log($error_string . " No SSH connection was established.");
        }
        $this->log($error_string . " No method available.");
        return false;
    }

    /**
     * @param \phpseclib\Net\SSH2 $ssh
     */
    public function setSSH(\phpseclib\Net\SSH2 $ssh)
    {
        $this->ssh = $ssh;
        sleep(1);
        $this->ssh_prompt();
    }

    protected function ssh_prompt()
    {
        if (!empty($this->ssh_prompt))
            return $this->ssh_prompt;
        $prompt = $this->exec('echo "$PS1"');
        //$prompt = explode( " ", $prompt )[ 0 ];
        $prompt = str_replace('\u', trim($this->exec('whoami')), $prompt);
        $prompt = str_replace('\h', trim($this->exec('hostname -a')), $prompt);
        $prompt = str_replace('\w', '~', $prompt);
        $prompt = trim($prompt);
        //$prompt = $user . '@' . $hostname;
        //$prompt = '';
        $this->log(sprintf("Prompt string for read() commands set to '<strong>%s</strong>'", $prompt), 'info');
        return $this->ssh_prompt = $prompt;
    }

    /**
     * @param string $command
     * @param bool $format
     * @return bool|string
     */
    protected function exec(string $command = '', bool $format = false)
    {
        if (isset($this->ssh)) {
            $this->ssh->enablePTY();
            $this->ssh->exec($command);
            $output = $this->ssh->read();
            $this->ssh->disablePTY();

            //$output = $this->ssh->read('imogen@r143 [~]');
            //$this->ssh->write($command);
            //$output .= $this->ssh->read('imogen@r143 [~]');
        } else if ($this->environment == 'local') {
            exec($command, $output);
        } else
            return false;
        return $format ? $this->format_output($output) : $output;
    }

    /*
     * $commands = array(
            "eval $(ssh-agent)\n",
            "ssh-add -t 120 ~/.ssh/jackthekey\n",
            $ssh_password . "\n",
            "git clone git@github.com:jamesjonesphoenix/my-new-repo.git\n"
        );
     */
    protected function read_write(array $commands = array(), bool $format = false)
    {
        $prompt = $this->ssh_prompt();
        $outputs = array();
        $this->ssh->read($prompt);
        foreach ($commands as $command) {
            $this->ssh->write($command); // note the "\n"
            $output = $this->ssh->read($prompt);
            //echo '<strong>' . $command . ':</strong>' . $output . '<br><br>';
            $output = str_replace($prompt, '', $output);
            $output = str_replace(trim(str_replace('\n', '', $command)), '', $output);
            $output = '<code>' . $command . '</code>' . trim($output);
            $outputs[] = $output;
        }
        return implode('<br>', $outputs);
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

    /**
     * @param string $action
     * @return bool
     */
    protected function WPCLI(string $action = 'install')
    {
        if (!in_array($action, array('install', 'delete'))) {
            $this->log(sprintf("Can't do WP CLI stuff in %s environment. Action must be 'install' or 'delete'.", $this->environment), 'error');
            return false;
        }
        $string = sprintf("WP CLI in %s environment", $this->environment);
        $error_string = sprintf("Can't %s %s.", $action, $string);
        $this->log(sprintf('%s WordPress CLI.', ucfirst($this->actions[$action]['present'])), 'info');
        $installed = $this->checkWPCLI();
        $output = '';
        switch ($action) {
            case 'install':
                if ($installed) {
                    $this->log(sprintf('%s already installed.', $string), 'info');
                    return true;
                }
                $output = $this->exec('curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; chmod +x wp-cli.phar; mkdir ~/bin; mv wp-cli.phar ~/bin/wp; echo -e "PATH=$PATH:$HOME/.local/bin:$HOME/bin\n\nexport PATH" >> ~/.bashrc;', true);
                if ($this->checkWPCLI())
                    $success = true;
                break;
            case 'delete':
                if (!$installed) {
                    $this->log($error_string . ' WordPress CLI not installed.', 'info');
                    return true;
                }
                $output = $this->exec('rm ~/bin/wp', true);
                if (!$this->checkWPCLI())
                    $success = true;
                break;
        }
        if (!empty($success)) {
            $this->log(sprintf('Successfully %s %s. %s', $this->actions[$action]['past'], $string, $output), 'success');
            return true;
        }
        $this->log(sprintf('Failed to %s %s. %s', $action, $string, $output));
        return false;
    }

    /**
     * @return bool
     */
    protected function checkWPCLI($verbose = false)
    {
        //export $PATH:~/.local/bin:~/bin;
        $output = $this->exec('wp --info;');
        //d($output );
        //d(strpos( $output, '' ));
        if (strpos($output, 'WP-CLI version:	2') !== false) //big space between : and 2
            return true;
        return false;
    }

    /**
     * @param string $action
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    protected function WordPress(string $action = 'install', array $db_args = array(), array $wp_args = array())
    {
        if (!in_array($action, array('install', 'delete'))) {
            $this->log(sprintf("Can't do WordPress stuff in %s environment. Action must be 'install' or 'delete'.", $this->environment), 'error');
            return false;
        }
        $string = sprintf("WordPress in %s environment", $this->environment);
        $error_string = sprintf("Can't %s %s.", $action, $string);
        $installed = $this->checkWPCLI();
        $output = '';
        switch ($action) {
            case 'install':
                if (!$installed) {
                    if (!$this->WPCLI('install')) {
                        $this->log($error_string . " WP CLI not installed. ", 'error');
                        return false;
                    }
                }
                if (!isset($db_args['name'], $db_args['username'], $db_args['password'])) {
                    $this->log($error_string . " DB name, username and/or password are missing from config . ", 'error');
                    return false;
                }
                if (!isset($wp_args['username'], $wp_args['password'], $wp_args['email'], $wp_args['url'], $wp_args['title'], $wp_args['prefix'])) {
                    $this->log($error_string . " WordPress username, password, email, url, title and/or prefix are missing from config . ", 'error');
                    return false;
                }
                $debug = !empty($wp_args->debug) ? $wp_args->debug : false;
                $wp_plugins = !empty($wp_args['plugins']) ? sprintf('wp plugin install %s;', implode(' ', (array)$wp_args['plugins'])) : '';
                $output .= $this->exec("
                cd ~/public_html; 
                wp core download --skip-content; 
                wp core config--dbname = " . $db_args['name'] . "--dbuser = " . $$db_args['username'] . "--dbpass = " . $db_args['password'] . "--dbprefix = " . $wp_args['prefix'] . " --locale = en_AU--extra - php << PHP
        define( 'AUTOSAVE_INTERVAL', 300 );
        define( 'WP_POST_REVISIONS', 6 );
        define( 'EMPTY_TRASH_DAYS', 7 );
        define( 'DISALLOW_FILE_EDIT', true );
        define( 'WP_DEBUG', " . $debug . " );
        PHP;");
                $output .= $this->exec("wp core install --url=" . $wp_args['url'] . " --title=" . $wp_args['title'] . " --admin_user=" . $wp_args['username']
                    . " --admin_password = " . $wp_args['password'] . "--admin_email = " . $wp_args['email']);
                $output .= $this->exec('wp plugin delete hello;  ' . $wp_plugins . ' wp plugin update --all; wp plugin activate --all; wp post delete 1;' .
                    'wp widget delete $(wp widget list sidebar-1 --format=ids); wp option update default_comment_status closed; wp option update blogdescription "Enter tagline for ' . $wp_args['title'] . ' here"; wp rewrite structure " /%postname %/"'
                    . 'wp rewrite flush;');
                $success = true;
                break;
            case 'delete':
                $success = true;
                break;
        }
        $output = $this->format_output($output);
        if (!empty($success)) {
            $this->log(sprintf('Successfully %s %s. %s', $this->actions[$action]['past'], $string, $output), 'success');
            return true;
        }
        $this->log(sprintf('Failed to %s %s. %s', $action, $string, $output));
        return false;
        return true;
    }

    /**
     * @param string $db_name
     * @param string $db_username
     * @param $db_password
     */
    protected function updateWordPress($db_name = '', $db_username = '', $db_password)
    {
        $this->exec('wp core update --locale="en_AU"; wp core update-db; wp theme update --all; wp plugin update --all; wp core language update; wp db optimize');
    }

    /**
     * @param string $action
     * @param string $key_name
     * @return bool|string
     */
    protected function SSHKey(string $action = 'create', string $key_name = 'id_rsa')
    {
        $this->log(sprintf(" % s % s SSH key named < strong>%s </strong >.", ucfirst($this->actions[$action]['present']), $this->environment, $key_name), 'info');
        $error_string = sprintf("Couldn't %s SSH key.", $action);
        switch ($action) {
            case 'create':
                //$this->exec( sprintf( 'rm ~/.ssh /%s; rm ~/.ssh /%s . pub;', $name, $name ) );
                $output = $this->exec('ssh - keygen - q - t rsa - N "" - f ~/.ssh / ' . $key_name . '; cat ~/.ssh / ' . $key_name . ' . pub');
                if (strpos($output, 'already exists') !== false) {
                    $this->log(sprintf("%s %s SSH key already exists. %s", $error_string, ucfirst($this->environment), $output));
                    return false;
                }
                if (strpos($output, 'ssh - rsa ') !== false) {
                    $this->log(sprintf("Successfully added SSH key. Public key is %s.", $output), 'success');
                    return $output;
                }
                break;
            case 'delete':
                $output = $this->exec('rm ~/.ssh / ' . $key_name . '; rm ~/.ssh / ' . $key_name . ' . pub;', true);
                if (strpos($output, 'rm: cannot remove') !== false) {
                    $this->log($error_string . ' ' . $output, 'error');
                    return false;
                }
                $this->log("Successfully removed SSH key. " . $output, 'success');
                return true;
                break;
        }
        $this->log(sprintf("Failed to %s SSH key. %s", $action, $this->format_output($output)), 'error');
        return false;
    }

    /**
     * @param string $action
     * @param string $host
     * @param string $key_name
     * @param string $user
     * @return bool
     */
    protected function SSHConfig(string $action = 'create', string $host = '', string $key_name = 'id_rsa', string $user = '')
    {
        $this->log(sprintf("%s %s SSH config for host <strong>%s</strong>.", ucfirst($this->actions[$action]['present']), $this->environment, $host), 'info');
        $error_string = sprintf("Couldn't % s SSH config . ", $action);
        $location = '~/.ssh/config';
        $cat = 'cat ' . $location;
        $config_before = $this->exec('touch config; ' . $cat . ';');
        switch ($action) {
            case 'create':
                $output = $this->exec('echo -e "Host ' . $host . '\n  Hostname ' . $host . '\n  User ' . $user
                    . '\n  IdentityFile ~/.ssh / ' . $key_name . '\n  Port 22" >> ' . $location . '; chmod 600 ' . $location,
                    true);
                $config_after = $this->exec($cat);
                if ($config_before == $config_after) {
                    $this->log(sprintf(" % s Config file is unchanged after attempting to add to it . ",
                        $error_string), 'error');
                    return false;
                }
                break;
            case 'delete':
                if (empty($config_before)) {
                    $this->log($error_string . " No need as config file is empty.", 'error');
                    return false;
                }
                $output = $this->exec('sed "s /^Host / \n &/" ' . $location . ' | sed " /^Host "' . $host
                    . '"$/,/^$/d;/^$/d" > ' . $location . '-dummy && mv ' . $location . '-dummy ' . $location, true);
                $config_after = $this->exec($cat);
                if (strpos($output, "unterminated `s' command") !== false) {
                    $this->log($error_string . " " . $output, 'error');
                    return false;
                }
                if ($config_before == $config_after) {
                    $this->log(sprintf("%s Config file was unchanged. Probably means <strong>%s</strong> config entry didn't exist.",
                        $error_string, $host, $output), 'error');
                    return false;
                }
                break;
        }
        $this->log(sprintf("Successfully %s SSH config. %s", ucfirst($this->actions[$action]['past']), $output), 'success');
        return true;
        //$this->log( sprintf( "Failed to %s SSH config. %s", $action, $this->format_output( $output ) ), 'error' );
        //return false;
    }

    protected function git($ssh_password)
    {
        /*
        $read = $this->ssh_prompt();
        $this->ssh->write( "eval $(ssh-agent)\n ssh-add -t 120 ~/.ssh/jackthekey\n" ); // note the "\n"
        $this->log( $this->ssh->read( $read ), 'info' );
        $this->ssh->write( $ssh_password . "\n" ); // note the "\n"
        $this->log( $this->ssh->read( $read ), 'info' );
        $this->ssh->write( "git clone git@github.com:jamesjonesphoenix/my-new-repo.git\n" ); // note the "\n"
        //$this->ssh->setTimeout( 5 );
        $this->log( $this->ssh->read( $read ), 'info' );
*/

        $commands = array(
            "eval $(ssh-agent)\n",
            "ssh-add -t 120 ~/.ssh/jackthekey\n",
            $ssh_password . "\n",
            "git clone git@github.com:jamesjonesphoenix/my-new-repo.git\n"
        );
        $output = $this->read_write($commands);
        $this->log($output, 'info');

        /*
                $this->ssh->write( "eval $(ssh-agent)\n ssh-add -t 120 ~/.ssh/jackthekey\n" ); // note the "\n"
                $this->ssh->write( $ssh_password . "\n" ); // note the "\n"
                $this->ssh->write( "git clone git@github.com:jamesjonesphoenix/my-new-repo.git\n" ); // note the "\n"
                $this->ssh->setTimeout(5);
                $this->log( $this->ssh->read(), 'info' );
        */

        //$this->ssh->read( $command );
    }

    protected function virtualHost(string $action = 'create', string $domain = '', string $directory = '')
    {
        if (!in_array($action, array('create', 'delete'))) {
            $this->log("Can't do virtual host stuff. Action must be 'create' or 'delete'.");
            return false;
        }
        $error_string = sprintf("Can't %s virtual host.");
        $output = $this->exec(sprintf('sudo ../bash/virtualhost.sh %s %s %s', $action, $domain, $directory));
        if (strpos($output, 'You have no permission to run') !== false) {
            $this->log(sprintf("%s Insufficient permissions to run the script. Try adding script to NOPASSWD in visudo.", $error_string));
            return false;
        }
    }
}
