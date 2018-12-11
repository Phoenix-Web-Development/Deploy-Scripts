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
        return $this->uninstall();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        $this->logStart();
        if (!$this->check()) {
            $this->log("Can't delete " . $this->mainStr() . ' WordPress CLI not installed.', 'info');
            return true;
        }
        $success = $this->ssh->delete('~/bin/wp') ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @return bool
     */
    public function create()
    {
        return $this->install();
    }

    /**
     * @return bool
     */
    public function install()
    {
        $this->logStart();
        if ($this->check()) {
            $this->log(sprintf("No need to install %s It's already installed.", $this->mainStr()), 'info');
            return true;
        }
        $output = $this->exec(
            'curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; 
        chmod +x wp-cli.phar; 
        mkdir ~/bin; 
        mv wp-cli.phar ~/bin/wp; 
        echo -e "PATH=$PATH:$HOME/.local/bin:$HOME/bin\n\nexport PATH" >> ~/.bashrc;', true);
        $success = $this->check() ? true : false;
        return $this->logFinish($output, $success);
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
    protected function mainStr()
    {
        return sprintf("WP CLI in %s environment.", $this->environment);
    }
}