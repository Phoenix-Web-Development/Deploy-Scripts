<?php

namespace Phoenix\Terminal;

/**
 * @property string $filepath
 *
 * Class WP_CLI
 * @package Phoenix\Terminal
 */
class WPCLIConfig extends AbstractTerminal
{

    /**
     * @var string
     */
    protected $logElement = 'h4';

    /**
     * @var
     */
    public $dirPath;

    /**
     * @var
     */
    private $_filepath;

    /**
     * @return bool
     */
    public function check(): bool
    {
        if (!$this->validate())
            return false;
        if ($this->file_exists($this->filepath))
            return true;

        return false;
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        $this->logStart();
        if (!$this->validate())
            return false;
        if (!$this->check())
            return $this->logFinish(true, 'No need to delete ' . $this->mainStr() . ' as file doesn\'t exist.');
        $success = $this->deleteFile($this->filepath) ? true : false;
        return $this->logFinish($success);

    }

    /**
     * @return bool
     */
    public function uninstall(): bool
    {
        return $this->delete();
    }

    /**
     * @return bool
     */
    public function create(): bool
    {
        $this->logStart();
        if (!$this->validate())
            return false;
        if ($this->check()) {
            $this->log(sprintf('No need to create %s. It already exists.', $this->mainStr()), 'success');
            return true;
        }

        $filepath = $this->filepath;
        $dirpath = dirname($filepath);
        if (!$this->is_dir($dirpath)) {
            $this->mkdir($dirpath);
            if (!$this->is_dir($dirpath))
                return $this->logError('Directory ' . $dirpath . " doesn't exist.");
        }
        $CLIConfig = 'apache_modules:
  - mod_rewrite';
        $success = $this->put($filepath, $CLIConfig);
        return $this->logFinish($success);
    }

    /**
     * @return bool
     */
    public function install(): bool
    {
        return $this->create();
    }


    /**
     * @return string
     */
    protected function mainStr(): string
    {
        $dirStr = !empty($this->filepath) ? sprintf(' at path <strong>%s</strong>', $this->filepath) : '';
        return sprintf('WP CLI config file in %s environ%s', $this->environ, $dirStr);
    }

    /**
     * @return string
     */
    protected function validate(): string
    {
        if (empty($this->filepath()))
            return $this->logError(sprintf("Couldn't get path to %s environ WP CLI config.", $this->environ));
        return true;
    }

    /**
     * @return string
     */
    protected function filepath(): string
    {
        if (!empty($this->_filepath))
            return $this->_filepath;

        if ($this->root != false)
            return $this->_filepath = self::trailing_slash($this->root) . '.wp-cli/config.yml';

        if (!empty($this->dirPath)) {
            return $this->_filepath = self::trailing_slash($this->dirPath) . 'wp-cli.yml';
        }
        return false;
    }
}