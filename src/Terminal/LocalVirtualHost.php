<?php

namespace Phoenix\Terminal;

/**
 * Class LocalVirtualHost
 * @package Phoenix\Terminal
 */
class LocalVirtualHost extends AbstractTerminal
{

    /**
     * @param string $wp_dir
     * @return bool
     */
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

    /**
     * @param array $args
     * @return bool|null
     */
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
            if (in_array($key, ["admin_email", "domain", "web_dir", "log_dir"])) {
                $needles[] = '%' . $key . '%';
                $replaces[] = $arg;
            }
        }
        $hostEntry = str_replace($needles, $replaces, $hostEntry);

        $command = $this->formatSudoCommand('virtualhost-create', [
            $args['domain'],
            $args['sites_available_path'],
            $hostEntry
        ]);

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

    /**
     * @param array $args
     * @return bool|null
     */
    public function delete(array $args = [])
    {

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $command = $this->formatSudoCommand('virtualhost-delete', [
            $args['domain'],
            $args['sites_available_path']
        ]);

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

    /**
     * @param array $args
     * @return bool
     */
    protected function validate(array $args = [])
    {
        if (empty($args))
            return $this->logError("No args inputted to method.");

        $argKeys = [
            'domain',
            'admin_email',
            'sites_available_path'
        ];

        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError("Argument <strong>" . $argKey . "</strong> missing from input");
        }

        return true;
    }

    /**
     * @param array $args
     * @return string
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