<?php

namespace Phoenix;

/**
 *
 * @property array $prompt
 * @property array $root
 * @property \phpseclib\Net\SSH2 $ssh
 *
 * Class Terminal
 */
class Terminal extends Base
{
    /**
     * @var \phpseclib\Net\SSH2
     */
    private $ssh;

    public $environment;

    private $_prompt;

    private $_root;

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
            return false;
        }
        $this->log($error_string . " No method available.");
        return false;
    }

    /**
     * Inject SSH implementation. Don't do this if running commands locally
     *
     * @param \phpseclib\Net\SSH2 $ssh
     * @return \phpseclib\Net\SSH2
     */
    public function setSSH(\phpseclib\Net\SSH2 $ssh = null)
    {
        if (!empty($this->ssh))
            return $this->ssh;
        $this->ssh = $ssh;
        sleep(1);
        $this->prompt();
        return $ssh;
    }

    /**
     * @return mixed
     */
    protected function root()
    {
        if (!empty($this->_root))
            return $this->_root;
        $pwd = trim($this->exec('pwd')) ?? false;
        return $this->_root = $pwd;
    }

    protected function whoami()
    {
        $whoami = trim($this->exec('whoami')) ?? false;
        return $whoami;
    }

    protected function prompt()
    {
        if (!empty($this->_prompt))
            return $this->_prompt;
        $prompt = $this->exec('echo "$PS1"');
        //$prompt = explode( " ", $prompt )[ 0 ];
        $prompt = str_replace('\u', trim($this->exec('whoami')), $prompt);
        $prompt = str_replace('\h', trim($this->exec('hostname -a')), $prompt);
        $prompt = str_replace('\w', '~', $prompt);
        $prompt = trim($prompt);
        //$prompt = $user . '@' . $hostname;
        //$prompt = '';
        if (empty($prompt)) {
            $this->log(sprintf("Couldn't work out the %s environment terminal prompt for read() commands.", $this->environment));
            return false;
        }
        $this->log(sprintf("Prompt string for %s environment terminal read() commands set to '<strong>%s</strong>'.", $this->environment, $prompt), 'info');
        return $this->_prompt = $prompt;
    }

    /**
     * @param string $command
     * @param bool $format
     * @return bool|string
     */
    protected function exec(string $command = '', bool $format = false)
    {
        if (isset($this->ssh)) {
            if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
                $this->log(sprintf("%s environment SSH exec() failed as you aren't authenticated. Exec() called by <code>%s()</code> function.",
                    ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
                return false;
            }
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

        if (!$this->ssh->isAuthenticated() && !empty(debug_backtrace()[1]['function'])) {
            $this->log(sprintf("%s environment SSH read_write() failed as you aren't authenticated. read_write() called by <code>%s()</code> function.",
                ucfirst($this->environment), debug_backtrace()[1]['function']), 'error');
            return false;
        }

        $prompt = $this->prompt();
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
        if (!$this->validate_action($action, array('check', 'install', 'delete'), sprintf("Can't do WP CLI stuff in %s environment.", $this->environment)))
            return false;
        $string = sprintf("WP CLI in %s environment", $this->environment);
        $error_string = sprintf("Can't %s %s.", $action, $string);
        if ($action != 'check')
            $this->log(sprintf('%s WordPress CLI in %s environment .', ucfirst($this->actions[$action]['present']), $this->environment), 'info');
        $output = '';
        switch ($action) {
            case 'check':
                $output = $this->exec('wp --info;');
                if (strpos($output, 'WP-CLI version:	2') !== false) //big space between : and 2
                    return true;
                return false;
                break;
            case 'install':
                if ($this->WPCLI('check')) {
                    $this->log(sprintf('%s already installed.', $string), 'info');
                    return true;
                }
                $output = $this->exec('curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; chmod +x wp-cli.phar; mkdir ~/bin; mv wp-cli.phar ~/bin/wp; echo -e "PATH=$PATH:$HOME/.local/bin:$HOME/bin\n\nexport PATH" >> ~/.bashrc;', true);
                if ($this->WPCLI('check'))
                    $success = true;
                break;
            case 'delete':
                if (!$this->WPCLI('check')) {
                    $this->log($error_string . ' WordPress CLI not installed.', 'info');
                    return true;
                }
                $output = $this->exec('rm ~/bin/wp', true);
                if (!$this->WPCLI('check'))
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
     * @param string $action
     * @param string $directory
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    protected function WordPress(string $action = 'install', string $directory = '', array $db_args = array(), array $wp_args = array())
    {
        if (!$this->validate_action($action, array('check', 'create', 'install', 'uninstall', 'delete'),
            sprintf("Can't do WordPress stuff in %s environment.", $this->environment)))
            return false;
        $action = $action == 'create' ? 'install' : $action;
        $action = $action == 'delete' ? 'uninstall' : $action;

        $string = sprintf("WordPress in %s environment", $this->environment);
        $error_string = sprintf("Can't %s %s.", $action, $string);
        if (!$this->WPCLI('check')) {
            if (!$this->WPCLI('install')) {
                $this->log($error_string . " WP CLI not installed and couldn't perform install.", 'error');
                return false;
            }
        }
        if (empty($directory)) {
            $this->log($error_string . " File directory missing from function input.", 'error');
            return false;
        }
        if ($action != 'check')
            $this->log(sprintf('%s %s in directory <strong>%s</strong>.', ucfirst($this->actions[$action]['present']), $string, $directory), 'info');
        if (in_array($directory, array('~/', $this->root))) {
            $this->log(sprintf($error_string . "Shouldn't be %s WordPress in root directory <strong>%s</strong>.", $this->actions[$action]['present'], $directory));
            return false;
        }
        if (!$this->dir_exists($directory)) {
            $this->log(sprintf($error_string . " Directory <strong>%s</strong> doesn't exist.", $directory));
            return false;
        }
        switch ($action) {
            case 'check':
                $output = $this->exec("cd " . $directory . "; wp core is-installed;");
                foreach (array("This does not seem to be a WordPress install", "'wp-config.php' not found") as $error) {
                    if (strpos($output, $error) !== false)
                        return false;
                }
                return true;
            case 'install':
                if ($this->WordPress('check', $directory)) {
                    $this->log(sprintf($error_string . ' WordPress already installed at <strong>%s</strong>.', $directory));
                    return false;
                }
                if (!isset($db_args['name'], $db_args['username'], $db_args['password'])) {
                    $this->log($error_string . " DB name, username and/or password are missing from config.");
                    return false;
                }
                if (!isset($wp_args['username'], $wp_args['password'], $wp_args['email'], $wp_args['url'], $wp_args['title'], $wp_args['prefix'])) {
                    $this->log($error_string . " WordPress username, password, email, url, title and/or prefix are missing from config. ");
                    return false;
                }
                $debug = !empty($wp_args->debug) && $wp_args->debug ? 'true' : 'false';
                $wp_plugins = !empty($wp_args['plugins']) ? sprintf('wp plugin install %s;', implode(' ', (array)$wp_args['plugins'])) : '';
                $this->ssh->setTimeout(240); //downloading WP can take a while
                $output = $this->exec("cd " . $directory . "; wp core download --skip-content;");
                if (stripos($output, 'success') === false && strpos($output, 'WordPress files seem to already be present here') === false) {
                    break;
                }
                $config_constants = array(
                    'AUTOSAVE_INTERVAL' => 300,
                    'WP_POST_REVISIONS' => 6,
                    'EMPTY_TRASH_DAYS' => 7,
                    'DISALLOW_FILE_EDIT' => 'true',
                    'WP_DEBUG' => $debug
                );
                $config_set = '';
                foreach ($config_constants as $config_constant => $constant)
                    $config_set .= sprintf("wp config set %s %s --raw --type=constant;", $config_constant, $constant);
                $output .= $this->exec("
                cd " . $directory . "; 
                wp config create --dbname=" . $db_args['name'] . " --dbuser=" . $db_args['username'] . " --dbpass=" . $db_args['password'] . " --dbprefix=" . rtrim($wp_args['prefix'], '_') . '_' . " --locale=en_AU;         
                " . $config_set . "
                wp core install --url=" . $wp_args['url'] . " --title=" . $wp_args['title'] . " --admin_user=" . $wp_args['username']
                    . " --admin_password=" . $wp_args['password'] . " --admin_email=" . $wp_args['email'] . ' --skip-email;'
                    . $wp_plugins . '
                wp plugin update --all; wp plugin activate --all; 
                wp post delete 1;
                wp widget delete $(wp widget list sidebar-1 --format=ids); 
                wp option update default_comment_status closed; 
                wp option update blogdescription "Enter tagline for ' . $wp_args['title'] . ' here";
                wp theme install twentyseventeen --activate               
                wp rewrite structure "/%postname%/";
                wp rewrite flush;
                find ' . $directory . ' -type d -exec chmod 755 {} \;
                find ' . $directory . ' -type f -exec chmod 644 {} \;  
                find ' . $directory . '/wp-content -type d -exec chmod 775 {} \;
                find ' . $directory . '/wp-content -type f -exec chmod 664 {} \;
                chmod 660 wp-config.php
                mv wp-config.php ../'
                );
                if ($this->WordPress('check', $directory))
                    $success = true;
                break;
            case 'uninstall':
                $db_clean = $this->WordPress('check', $directory) ? ' wp db clean --yes;' : '';
                $wp_files = array(
                    'wp-admin/',
                    'wp-content/',
                    'wp-includes/',
                    'index.php',
                    'license.txt',
                    'readme.html',
                    'wp-activate.php',
                    'wp-blog-header.php',
                    'wp-comments-post.php',
                    'wp-config.php',
                    '../wp-config.php',
                    'wp-config-sample.php',
                    'wp-cron.php',
                    'wp-links-opml.php',
                    'wp-load.php',
                    'wp-login.php',
                    'wp-mail.php',
                    'wp-settings.php',
                    'wp-signup.php',
                    'wp-trackback.php',
                    'xmlrpc.php'
                );
                $other_files = array(
                    '.htaccess',
                    '.htaccess_lscachebak_*',
                    'README.md',
                    'wordfence-waf.php'
                );
                $output = $this->exec("
                cd " . $directory . ";" . $db_clean . "                
                rm -R " . implode(' ', $wp_files) . "; 
                rm " . implode(' ', $other_files) . ";"
                );
                if (!$this->WordPress('check', $directory))
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
    }

    /**
     * @param string $separate_repo_location
     * @return bool
     */
    protected function deleteGit(string $separate_repo_location = '')
    {
        $message_string = sprintf("Git repository folder at <strong>%s</strong>.", $separate_repo_location);
        $this->log(sprintf("Deleting %s", $message_string), 'info');
        if (!$this->dir_exists($separate_repo_location)) {
            $this->log(sprintf("No need to delete %s Folder doesn't exist.", $message_string));
            return false;
        }
        $this->exec('rm -rf ' . $separate_repo_location . ';');
        if (!$this->dir_exists($separate_repo_location)) {
            $this->log(sprintf("Successfully deleted %s", $message_string), 'success');
            return true;
        }
        $this->log(sprintf("Failed to delete %s", $message_string));
        return false;
    }

    /**
     * @param string $worktree
     * @param string $separate_repo_location
     * @return bool
     */
    protected function moveGit(string $worktree = '', string $separate_repo_location = '')
    {
        $message_string = sprintf("Git repository to <strong>%s</strong> separate from worktree at <strong>%s</strong>.", $separate_repo_location, $worktree);
        $this->log(sprintf("Moving %s", $message_string), 'info');

        $error_message_string = "Can't move " . $message_string;
        $output = $this->exec('mkdir -p ' . $separate_repo_location . '; cd ' . $separate_repo_location . '; git rev-parse --is-inside-git-dir');
        d($output);
        if (strpos($output, 'true') !== false) {
            $this->log(sprintf("%s Git repository already exists at <strong>%s</strong>", $error_message_string, $separate_repo_location));
            return false;
        }
        $output = $this->exec('find ' . $separate_repo_location . ' -maxdepth 0 -empty -exec echo {} is empty. \;');
        d($output);
        if (strpos($output, $separate_repo_location . ' is empty') === false) {
            $this->log(sprintf("%s Directory already exists at <strong>%s</strong> and contains files.", $error_message_string, $separate_repo_location));
            return false;
        }
        $output = $this->exec('cd ' . $worktree . '; git init --separate-git-dir ' . $separate_repo_location, true);

        $message_string .= ' ' . $output;
        if (strpos($output, 'Reinitialized existing Git repository') !== false) {
            $this->log(sprintf("Successfully moved %s", $message_string), 'success');
            return true;
        }
        $this->log(sprintf("Failed to move %s", $message_string));
        return false;
    }

    /**
     * @param string $action
     * @param string $directory
     * @param string $message
     * @return bool
     */
    protected function gitAction($action = 'update', $directory = '', $message = 'automated update of WordPress, plugins and themes')
    {
        if (!$this->validate_action($action, array('update', 'commit', 'delete'), "Can't do Git stuff in the terminal."))
            return false;
        if (!$this->dir_exists($directory)) {
            $this->log(sprintf("Can't commit Git repository. Directory <strong>%s</strong> doesn't exist in %s environment.", $directory, $this->environment));
            return false;
        }
        $branch = 'master';
        $message = sprintf(" %s environment Git repo. ", $this->environment);
        $this->log("Committing and pushing" . $message, 'info');
        $output = '';
        $init = "cd " . $directory . "; git checkout " . $branch . ";";
        switch ($action) {
            case 'update':
                $output = $this->exec($init . "
        git fetch --all;
        git reset --hard origin/" . $branch . ";"
                );
                if (strpos($output, 'blegh') !== false) {
                    $success = true;
                }
                break;
            case 'commit':
                $output = $this->exec($init . "
        git add . --all;
        git commit -m '" . $message . "';
        git push origin " . $branch . ";"
                );
                if (strpos($output, 'blegh') !== false) {
                    $success = true;
                }
                break;
        }
        $message .= $output;
        if (!empty($success)) {
            $this->log("Successfully committed and pushed" . $message, 'success');
            return true;
        }
        $this->log("Failed to commit" . $message);
        return false;
    }

    /**
     * @param string $db_name
     * @param string $db_username
     * @param $db_password
     */
    protected function updateWordPress($directory = '')
    {
        $branch = 'master';
        $this->exec('
        cd ' . $directory . ';                
        
        
        wp core update --locale="en_AU"; 
        wp core update-db; 
        wp theme update --all; 
        wp plugin update --all; 
        wp core language update; 
        wp db optimize'
        );
    }

    /**
     * @param string $action
     * @param string $key_name
     * @param string $passphrase
     * @return bool|string
     */
    protected function SSHKey(string $action = 'create', string $key_name = 'id_rsa', string $passphrase = '')
    {
        $this->log(sprintf(" %s %s environment SSH key named <strong>%s</strong>.",
            ucfirst($this->actions[$action]['present']), $this->environment, $key_name), 'info');
        $error_string = sprintf("%s %s environment SSH key.", $action, $this->environment);
        if (empty($key_name)) {
            $this->log(sprintf("Can't %s Key name function input is missing.", $error_string));
            return false;
        }
        $error_string = "Couldn't " . $error_string;
        switch ($action) {
            case 'create':
                //$this->exec( sprintf( 'rm ~/.ssh /%s; rm ~/.ssh /%s . pub;', $name, $name ) );
                $output = $this->exec('ssh-keygen -q -t rsa -N "' . $passphrase . '" -f ~/.ssh/' . $key_name
                    . '; cat ~/.ssh/' . $key_name . '.pub');
                if (strpos($output, 'already exists') !== false) {
                    $this->log(sprintf("%s %s SSH key already exists. %s", $error_string, ucfirst($this->environment), $output));
                    return false;
                }
                if (strpos($output, 'ssh-rsa ') !== false) {
                    $this->log(sprintf("Successfully added SSH key. Public key is %s.", $output), 'success');
                    return $output;
                }
                break;
            case 'delete':
                $output = $this->exec('rm ~/.ssh/' . $key_name . '; rm ~/.ssh/' . $key_name . ' . pub;', true);
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

    protected function localSSHKey(string $action = 'create', string $key_name = 'id_rsa', string $passphrase = '')
    {
        $output = $this->exec(sprintf('sudo /home/james/PhpstormProjects/Deploy/Project/bash/ssh-keygen.sh %s %s /home/james/.ssh/',
            $passphrase, $key_name, '/home/james/.ssh/'));
        echo $output;
        return $output;
    }

    /**
     * @param string $action
     * @param string $host
     * @param string $hostname
     * @param string $key_name
     * @param string $user
     * @param int $port
     * @return bool
     */
    protected function SSHConfig(string $action = 'create',
                                 string $host = '',
                                 string $hostname = '',
                                 string $key_name = 'id_rsa',
                                 string $user = '',
                                 int $port = 22)
    {
        if (!$this->validate_action($action, array('create', 'delete'), sprintf("Can't do %s environment SSH config stuff.", $this->environment)))
            return false;
        $message_string = sprintf("%s environment SSH config", $this->environment);
        if (empty($host)) {
            $this->log(sprintf("Can't %s. Host missing from function input.", $message_string));
            return false;
        }
        $this->log(sprintf("%s %s for host <strong>%s</strong>.", ucfirst($this->actions[$action]['present']), $message_string, $host), 'info');
        $error_string = sprintf("Couldn't %s %s.", $action, $message_string);
        $location = '~/.ssh/config';
        $cat = 'cat ' . $location;
        $chmod = 'chmod 600 ' . $location;
        $config_before = $this->exec('touch config; ' . $cat . ';');
        $config_entry_exists = $this->exec('grep "Host ' . $host . '" ' . $location);
        $config_entry_exists = (strlen($config_entry_exists) > 0 && strpos($config_entry_exists, 'Host ' . $host) !== false) ? true : false;
        switch ($action) {
            case 'create':
                if ($config_entry_exists) {
                    $this->log(sprintf(" %s Config entry for <strong>%s</strong> already exists.", $error_string, $host), 'error');
                    return false;
                }
                $output = $this->exec('echo -e "Host ' . $host . '\n  Hostname ' . $hostname . '\n  User ' . $user
                    . '\n  IdentityFile ~/.ssh/' . $key_name . '\n  Port ' . $port . '" >> ' . $location . ';' . $chmod, true);
                $config_after = $this->exec($cat);
                if ($config_before == $config_after) {
                    $this->log(sprintf(" %s Config file is unchanged after attempting to add to it . ",
                        $error_string), 'error');
                    return false;
                }
                break;
            case 'delete':
                if (!$config_entry_exists) {
                    $this->log(sprintf("%s Config entry for <strong>%s</strong> doesn't exist.", $error_string, $host), 'error');
                    return false;
                }
                $output = $this->exec('sed "s/^Host/\n&/" ' . $location . ' | sed "/^Host "' . $host
                    . '"$/,/^$/d;/^$/d" > ' . $location . '-dummy; mv ' . $location . '-dummy ' . $location . ';' . $chmod, true);
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
        $this->log(sprintf("Successfully %s SSH config for host <strong>%s</strong>. %s", $this->actions[$action]['past'], $host, $output), 'success');
        return true;
        //$this->log( sprintf( "Failed to %s SSH config. %s", $action, $this->format_output( $output ) ), 'error' );
        //return false;
    }

    protected function dir_exists(string $dir = '')
    {
        if (empty($dir)) {
            $this->log(sprintf("Can't check if directory exists. No directory supplied to function. ", $message_string));
            return false;
        }
        $output = $this->exec('if test -d ' . $dir . '; then echo "exist"; fi');
        if (strpos($output, 'exist') !== false)
            return true;
        return false;
    }


    protected function git($action = 'create', $github_user, $github_project /*, $ssh_password = '', $git_url = ''*/)
    {
        /*
        $read = $this->prompt();
        $this->ssh->write( "eval $(ssh-agent)\n ssh-add -t 120 ~/.ssh/jackthekey\n" ); // note the "\n"
        $this->log( $this->ssh->read( $read ), 'info' );
        $this->ssh->write( $ssh_password . "\n" ); // note the "\n"
        $this->log( $this->ssh->read( $read ), 'info' );
        $this->ssh->write( "git clone git@github.com:jamesjonesphoenix/my-new-repo.git\n" ); // note the "\n"
        //$this->ssh->setTimeout( 5 );
        $this->log( $this->ssh->read( $read ), 'info' );
        */

        switch ($this->environment) {
            case 'local':
                //$this->exec('git clone' . $git_url);
                break;
            case 'staging':
            case 'live':
                $commands = array(
                    $ssh_password . "\n",
                    "git clone git@github.com:" . $github_user . "/" . $github_project . ".git\n"
                    //git@github.com:jamesjonesphoenix/ahtgroup.git
                );
                $output = $this->read_write($commands);
                $this->log($output, 'info');
                break;
        }

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
