<?php

namespace Phoenix\Terminal;

/**
 * Class Gitignore
 * @package Phoenix\Terminal
 */
class Gitignore extends AbstractTerminal
{

    /**
     * @param string $directory
     * @return bool
     */
    public function create(string $directory = '')
    {
        $this->logStart();
        if (!$this->validate($directory))
            return false;
        $filepath = self::trailing_slash($directory) . '.gitignore';
        if ($this->ssh->file_exists($filepath) && strlen($this->ssh->get($filepath)) > 0)
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> already exists so no need to create.", $directory), 'warning');
        $success = $this->ssh->put($filepath, BASE_DIR . '/../configs/gitignore-template', \phpseclib\Net\SFTP::SOURCE_LOCAL_FILE) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $directory
     * @return bool
     */
    public function delete(string $directory = '')
    {
        $this->logStart();
        if (!$this->validate($directory))
            return false;
        $filepath = self::trailing_slash($directory) . '.gitignore';
        if (!$this->ssh->file_exists($filepath))
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> doesn't exist so no need to delete.", $directory), 'warning');
        $success = $this->ssh->delete($filepath) ? true : false;
        return $this->logFinish('', $success);
    }

    /**
     * @param string $directory
     * @return bool
     */
    protected function validate(string $directory = '')
    {
        if (!$this->ssh->is_dir($directory)) {
            return $this->logError(sprintf("Directory <strong>%s</strong> doesn't exist", $directory));
        }
        return true;
    }

    /**
     * @return string
     */
    protected
    function mainStr()
    {
        return sprintf("%s environment gitignore file", $this->environment);
    }
}