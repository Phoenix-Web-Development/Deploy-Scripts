<?php

namespace Phoenix\Terminal;

/**
 *
 * Class WP_CLI
 * @package Phoenix\Terminal
 */
class WP_CLI extends AbstractTerminal
{

    /**
     * @return bool
     */
    public function check()
    {
        $output = $this->exec('wp --info;');
        if (strpos($output, 'WP-CLI version:	2') !== false) //big space between : and 2
            return true;
        return false;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        $this->logStart('delete');
        $error_string = "Can't delete " . $this->getMainString();
        if (!$this->check()) {
            $this->log($error_string . ' WordPress CLI not installed.', 'info');
            return true;
        }
        $output = $this->exec('rm ~/bin/wp', true);
        $success = $this->check() ? false : true;
        return $this->logFinish('delete', $output, $success);
    }

    /**
     * @return bool
     */
    public function install()
    {
        $this->logStart('install');
        $error_string = "Can't install " . $this->getMainString();
        if ($this->check()) {
            $this->log($error_string . ' already installed.', 'info');
            return true;
        }
        $output = $this->exec(
            'curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; 
        chmod +x wp-cli.phar; 
        mkdir ~/bin; 
        mv wp-cli.phar ~/bin/wp; 
        echo -e "PATH=$PATH:$HOME/.local/bin:$HOME/bin\n\nexport PATH" >> ~/.bashrc;', true);
        $success = $this->check() ? true : false;
        return $this->logFinish('install', $output, $success);
    }

    /**
     * @return bool
     */
    public function install_if_missing()
    {
        if ($this->check())
            return true;
        $this->log(sprintf("WPCLI missing from %s environment so let's install it.", $this->environment), 'info');
        if ($this->install())
            return true;
        return false;
    }

    /**
     * @return string
     */
    private function getMainString()
    {
        return sprintf("WP CLI in %s environment.", $this->environment);
    }

    /**
     * @param $action
     */
    private function logStart($action)
    {
        $this->log(ucfirst($this->actions[$action]['present']) . ' ' . $this->getMainString(), 'info');
    }

    /**
     * @param string $action
     * @param string $output
     * @param string $success
     * @return bool
     */
    private function logFinish($action = '', $output = '', $success = 'false')
    {
        if (!empty($action)) {
            if (!empty($success)) {
                $this->log(sprintf('Successfully %s %s. %s', $this->actions[$action]['past'], $this->getMainString(), $output), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s. %s', $action, $this->getMainString(), $output));
            return false;
        }
        return null;
    }

}