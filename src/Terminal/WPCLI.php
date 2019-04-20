<?php

namespace Phoenix\Terminal;

/**
 * Installs or uninstalls WP command line interface
 *
 * Class WPCLI
 * @package Phoenix\Terminal
 */
class WPCLI extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';

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
        if (!$this->check())
            return $this->logFinish(true, "No need to uninstall as WordPress CLI isn't installed");
        if (!$this->validate())
            return false;
        $success = $this->deleteFile($this->filepath()) ? true : false;
        return $this->logFinish($success);
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
        if ($this->check())
            return $this->logFinish(true, 'WP CLI already installed');
        if (!$this->validate())
            return false;

        $this->mkdir(dirname($this->filepath()));
        if (!is_dir($this->filepath()))
            return $this->logError("Couldn't create directory <strong>" . $this->filepath() . "</strong>.");
        $output = $this->exec(
            'curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; 
        chmod +x wp-cli.phar;
        mv wp-cli.phar ' . $this->filepath() . '; 
        echo -e "PATH=$PATH:$HOME/.local/bin:$HOME/bin\n\nexport PATH" >> ~/.bashrc;'
        );


        $success = $this->check() ? true : false;

        return $this->logFinish($success, $output);
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
    protected function validate()
    {
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get %s environ home directory.", $this->environment));
        return true;
    }


    /**
     * @return string
     */
    protected function mainStr()
    {
        $dirStr = !empty($this->filepath) ? sprintf(" at path <strong>%s</strong>", $this->filepath) : '';
        return sprintf("WP CLI in %s environment%s", $this->environment, $dirStr);
    }

    /**
     * @return string
     */
    protected function filepath()
    {
        $root = $this->root;
        if (!empty($root))
            return self::trailing_slash($root) . 'bin/wp';
        return false;
    }
}