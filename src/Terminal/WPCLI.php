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
    public function check(): bool
    {
        $output = $this->exec('wp --info;');
        if (strpos($output, 'WP-CLI version:	2') !== false) //big space between : and 2
            return true;
        return false;
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        return $this->uninstall();
    }

    /**
     * @return bool
     */
    public function uninstall(): bool
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
    public function create(): bool
    {
        return $this->install();
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        $this->logStart();
        if ($this->check())
            return $this->logFinish(true, 'WP CLI already installed');
        if (!$this->validate())
            return false;

        if (!is_dir($this->dirpath())) {
            $this->mkdir(dirname($this->dirpath()));
            if (!is_dir($this->dirpath()))
                return $this->logError("Couldn't create directory <strong>" . $this->dirpath() . '</strong>.');
        }
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
    public function installOrUpdate(): bool
    {
        if ($this->check()) {
            return $this->update();
        }
        //$this->log(sprintf("WPCLI missing from %s environment so let's install it.", $this->environ), 'info');
        return $this->install();

    }

    /**
     * @return bool|null
     */
    public function update(): ?bool
    {
        $this->logStart();
        if (!$this->check())
            return $this->logError('WP CLI not installed');
        //if (!$this->validate())
        //return false;
        $output = $this->exec('wp cli check-update', $this->dirpath());
        if (strpos($output, 'WP-CLI is at the latest version') !== false)
            return $this->logFinish(true, $output);

        $output = $this->exec('wp cli update --stable --yes', $this->dirpath());
        $success = strpos($output, 'Updated WP-CLI to the latest stable release') !== false;

        return $this->logFinish($success, $output);
    }

    /**
     * @return string
     */
    protected function validate(): string
    {
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get %s environ home directory.", $this->environ));
        return true;
    }


    /**
     * @return string
     */
    protected function mainStr(): string
    {
        $dirStr = !empty($this->filepath()) ? sprintf(' at path <strong>%s</strong>', $this->filepath()) : '';
        return sprintf('WP CLI in %s environment%s', $this->environ, $dirStr);
    }

    /**
     * @return string
     */
    protected function dirpath(): string
    {
        if ($this->root != false)
            return self::trailing_slash($this->root) . 'bin/';
        return false;
    }

    /**
     * @return string
     */
    protected function filepath(): string
    {
        if ($this->root != false)
            return self::trailing_slash($this->root) . 'bin/wp';
        return false;
    }
}