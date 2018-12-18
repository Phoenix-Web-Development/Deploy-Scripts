<?php

namespace Phoenix\Terminal;

/**
 *
 * Class WP_CLI
 * @package Phoenix\Terminal
 */
class WPCLIConfig extends AbstractTerminal
{

    /**
     * @return bool
     */
    public function check()
    {
        if ($this->ssh->file_exists($this->filepath()))
            return true;
        return false;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        $this->logStart();
        if (!$this->check()) {
            $this->log("No need to delete " . $this->mainStr() . ' File doesn\'t exist.', 'info');
            return true;
        }
        $success = $this->ssh->delete($this->filepath()) ? true : false;
        return $this->logFinish('', $success);

    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return $this->delete();
    }

    /**
     * @return bool
     */
    public function create()
    {
        $this->logStart();
        if ($this->check()) {
            $this->log(sprintf("No need to create %s It already exists.", $this->mainStr()), 'success');
            return true;
        }

        if (!$this->ssh->is_dir(dirname($this->filepath())))
            $this->ssh->mkdir(dirname($this->filepath()));
        $CLIConfig = "apache_modules:
  - mod_rewrite";
        $success = $this->ssh->put($this->filepath(), $CLIConfig);
        return $this->logFinish('', $success);
    }

    /**
     * @return bool
     */
    public function install()
    {
        return $this->create();
    }


    /**
     * @return string
     */
    protected function mainStr()
    {
        return sprintf("WP CLI config file in %s environment in directory <strong>%s</strong>.", $this->environment, $this->filepath());
    }

    /**
     * @return string
     */
    protected function filepath()
    {
        return self::trailing_slash($this->client->root) . '.wp-cli/config.yml';
    }
}