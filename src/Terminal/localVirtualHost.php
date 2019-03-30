<?php

namespace Phoenix\Terminal;

/**
 * Class localVirtualHost
 * @package Phoenix\Terminal
 */
class localVirtualHost extends AbstractTerminal
{


    public function check($wp_dir = '')
    {
        if (!$this->validate($wp_dir))
            return false;
        $output = $this->exec("cd " . $wp_dir . "; wp core is-installed;");
        $potential_errors = array(
            "This does not seem to be a WordPress install",
            "'wp-config.php' not found",
            "Error establishing a database connection",
            "The site you have requested is not installed"
        );
        foreach ($potential_errors as $potential_error) {
            if (stripos($output, $potential_error) !== false)
                return false;
        }
        return true;
    }


    public function create(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();

        if (!$this->validate($args))
            return false;

        $hostEntry = file_get_contents(CONFIG_DIR . 'virtual-host');
        $needles = [];
        $replaces = [];
        //Fill out virtual host template
        foreach ($args as $key => $arg) {
            if (in_array($key, ["admin_email", "domain", "web_dir"])) {
                $needles[] = '%' . $key . '%';
                $replaces[] = $arg;
            }
        }
        $hostEntry = str_replace($needles, $replaces, $hostEntry);

        //$apacheUser = $this->client->whoami();

        $command = 'sudo ' . BASH_WRAPPER
            //. ' blegh '
            . " virtualhost-create "
            . "'" . $args['domain'] . "' "
            . "'" . $args['sites_available_path'] . "' "
            . "'" . $hostEntry . "' 2>&1";
        //print_r($command . '<br><br>');

        //$output = $this->exec('sudo ' . BASH_WRAPPER . ' virtualhost-create 2>&1' );
        $output = $this->exec($command);
        $success = strpos($output, 'Successfully created virtual host for ' . $args['domain']) !== false ? true : false;
        //sudo: no tty present and no askpass program specified
        return $this->logFinish($success, $output, '');
    }

    /**
     * @param string $wp_dir
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    public function install(string $wp_dir = '', array $db_args = array(), array $wp_args = array())
    {
        return $this->create($wp_dir, $db_args, $wp_args);
    }


    public function delete(array $args = [])
    {

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $command = 'sudo ' . BASH_WRAPPER
            . " virtualhost-delete "
            . "'" . $args['domain'] . "' "
            . "'" . $args['sites_available_path'] . "' 2>&1";

        print_r($command . '<br><br>');

        //$output = $this->exec('sudo ' . BASH_WRAPPER . ' virtualhost-delete 2>&1' );
        $output = $this->exec($command);
        $success = strpos($output, 'Successfully removed Virtual Host for ' . $args['domain']) !== false ? true : false;
        //sudo: no tty present and no askpass program specified
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $wp_dir
     * @return bool
     */
    public function uninstall($wp_dir = '')
    {
        return $this->delete($wp_dir);
    }

    protected function validate(array $args = [])
    {
        if (empty($args))
            return $this->logError("No args inputted to method.");

        $argKeys = [
            'domain',
            'admin_email',
            'web_dir',
            'sites_available_path'
        ];

        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError($argKey . " argument missing from input");
        }

        return true;
    }

    /**
     * @param string $wp_dir
     * @return bool|string
     */
    protected
    function mainStr(array $args = [])
    {
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr))
                return $this->_mainStr;
        }

        $domain = !empty($args['domain']) ? sprintf(' for domain <strong>%s</strong>', $args['domain']) : '';
        $vhostFilePath = !empty($args['sites_available_path']) ? sprintf(' in virtual host file <strong>%s</strong>', $args['sites_available_path']) : '';
        $webDir = !empty($args['web_dir']) ? sprintf(' in web directory <strong>%s</strong>', $args['web_dir']) : '';

        return $this->_mainStr = sprintf("%s virtual host%s%s%s", $this->environment, $domain, $vhostFilePath, $webDir);
    }
}