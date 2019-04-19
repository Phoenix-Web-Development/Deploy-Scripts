<?php

namespace Phoenix\Terminal;

/**
 * Class LocalVirtualHost
 * @package Phoenix\Terminal
 */
class LocalVirtualHost extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';


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
            $args['conf_path'],
            $hostEntry
        ]);

        $output = $this->exec($command);
        $success = strpos($output, 'Successfully created virtual host for ' . $args['domain']) !== false
        || strpos($output, 'This domain already exists.') !== false
            ? true : false;
        //sudo: no tty present and no askpass program specified
        return $this->logFinish($success, $output, '');
    }

    /**
     * @param string $wp_dir
     * @param array $db_args
     * @param array $wp_args
     * @return bool
     */
    public function install($args)
    {
        return $this->create($args);
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
            $args['conf_path']
        ]);

        $output = $this->exec($command);


        $success = strpos($output, 'Successfully removed Virtual Host for ' . $args['domain']) !== false ||
        strpos($output, 'No need to delete virtualhost. Domain <strong>' . $args['domain'] . '</strong> does not exist.') !== false
            ? true : false;
        //sudo: no tty present and no askpass program specified
        return $this->logFinish($success, $output);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function uninstall(array $args = [])
    {
        return $this->delete($args);
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
            'conf_path'
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
        $vhostFilePath = !empty($args['conf_path']) ? sprintf(' in virtual host config file <strong>%s</strong>', $args['conf_path']) : '';
        $webDir = !empty($args['web_dir']) ? sprintf(' for web directory <strong>%s</strong>', $args['web_dir']) : '';

        return $this->_mainStr = sprintf("%s virtual host%s%s%s", $this->environment, $domain, $vhostFilePath, $webDir);
    }
}