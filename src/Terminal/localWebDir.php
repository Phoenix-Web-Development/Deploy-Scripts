<?php

namespace Phoenix\Terminal;

/**
 * Class localWebDir
 * @package Phoenix\Terminal
 */
class localWebDir extends AbstractTerminal
{


    public function check($wp_dir = '')
    {

    }


    public function create(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();

        if (!$this->validate($args))
            return false;

        $command = 'sudo ' . BASH_WRAPPER
            . " webdir-create "
            . "'" . $args['project_dir'] . "' "
            . "'" . $args['web_dir'] . "' "
            . "'" . $args['owner'] . "' "
            . "'" . $args['group'] . "' 2>&1";
        print_r($command);

        //$success = mkdir ($args['web_dir'],0777 , TRUE );
        //$this->ssh->mkdir($args['web_dir']);
        //$this->ssh->mkdir($args['project_dir']);

        $output = $this->exec($command);
        $success = strpos($output, 'Successfully created web directory at ' . $args['web_dir']) !== false ? true : false;


        return $this->logFinish($success, '', '');
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

        $output = $this->exec("rm -R " . $args['web_dir']);
        print_r($output);
        $success = $this->is_dir($args['web_dir']) ? false : true;
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
            'project_dir',
            'web_dir',
            'owner',
            'group'
        ];

        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError(" Argument <strong>" . $argKey . "</strong> missing from input.");
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

        $webDir = !empty($args['web_dir']) ? sprintf(' at <strong>%s</strong>', $args['web_dir']) : '';
        $permissions = !empty($args['owner']) && !empty($args['group']) ? sprintf(' with owner <strong>%s</strong> and group <strong>%s</strong>', $args['owner'], $args['group']) : '';
        return $this->_mainStr = sprintf("%s web directory%s%s", $this->environment, $webDir, $permissions);
    }
}