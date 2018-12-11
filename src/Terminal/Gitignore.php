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
    public function create($directory = '')
    {
        $this->logStart();
        if (!$this->validate($directory))
            return false;
        $filepath = self::trailing_slash($directory) . '.gitignore';
        if ($this->ssh->file_exists($filepath))
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> already exists so no need to create.", $directory));
        $success = $this->ssh->put($filepath, dirname(__FILE__) . '/../configs/gitignore-template', SFTP::SOURCE_LOCAL_FILE) ? true : false;
        $this->logFinish('', $success);
    }

    /**
     * @param string $directory
     * @return bool
     */
    public function delete($directory = '')
    {
        $this->logStart();
        if (!$this->validate($directory))
            return false;
        $filepath = self::trailing_slash($directory) . '.gitignore';
        if (!$this->ssh->file_exists($file))
            return $this->logError(sprintf("Gitignore file at <strong>%s</strong> doesn't exist so no need to delete.", $directory));
        $success = $this->ssh->delete($filepath) ? true : false;
        $this->logFinish('', $success);
    }

    /**
     * @param string $directory
     * @return bool
     */
    protected function validate($directory = '')
    {
        if (!$this->dir_exists($directory)) {
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
        return sprintf("%s environment gitignore file.", $this->environment);
    }
}