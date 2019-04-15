<?php

namespace Phoenix\Terminal;

/**
 * Class LocalProjectDirSetup
 * @package Phoenix\Terminal
 */
class LocalProjectDirSetup extends AbstractTerminal
{

    public $projectArgs;

    public function setProjectArgs(array $args = [])
    {
        $this->projectArgs = $args;
    }

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

        if ($this->is_dir($args['dir'])) {
            $owner = posix_getpwuid(fileowner($args['dir']))['name'];
            $group = posix_getgrgid(filegroup($args['dir']))['name'];
            if ($owner == $args['owner'] && $group == $args['group'])
                return $this->logFinish(true, "Directory already exists and has correct permissions");
            return $this->logError("Directory already exists and has wrong permissions.");
        }

        //check if project dir is a subdirectory of nominated dir
        if (strlen($this->projectArgs['dir']) > strlen($args['dir']) && strpos($args['dir'], $this->projectArgs['dir']) === 0)
            return $this->logError(sprintf("Project directory shouldn't be a subdirectory of <strong>%s</strong> directory.", $args['purpose']));

        $command = $this->formatSudoCommand('projectdir-setup', [
            $args['dir'],
            $args['owner'],
            $args['group'],
            $this->projectArgs['dir'],
            $this->projectArgs['owner'],
            $this->projectArgs['group']
        ]);
        $output = $this->exec($command);

        $needle = 'Successfully setup directory at ' . $args['dir'] . '.';
        if (strpos($output, $needle) !== false
            && $this->is_dir($args['dir'])
            && posix_getpwuid(fileowner($args['dir']))['name'] == $args['owner']
            && posix_getgrgid(filegroup($args['dir']))['name'] == $args['group']
        )
            $success = true;
        else
            $success = false;

        return $this->logFinish($success, $output, $command);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function install(array $args = [])
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
        if (!$this->deleteFile($args['dir'], true))
            return false;
        return $this->logFinish(true);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function uninstall($args = [])
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

        $action = $this->getCaller();
        $argKeys = ['dir'];
        if ($action == 'create') {
            $argKeys[] = 'owner';
            $argKeys[] = 'group';
        }

        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError("Argument <strong>" . $argKey . "</strong> missing from input.");
            if ($action == 'create' && empty($this->projectArgs[$argKey]))
                return $this->logError("Project argument <strong>" . $argKey . "</strong> missing from class.");
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

        $dirStr = !empty($args['dir']) ? sprintf(' at <strong>%s</strong>', $args['dir']) : '';
        $permissionsStr = !empty($args['owner']) && !empty($args['group']) ? sprintf(' with owner <strong>%s</strong> and group <strong>%s</strong>', $args['owner'], $args['group']) : '';
        $purposeStr = !empty($args['purpose']) ? ' <strong>' . ucfirst($args['purpose']) . '</strong>' : '';
        $environStr = ' ' . $this->environment . ' environ';

        return $this->_mainStr = sprintf("%s%s directory%s%s", $environStr, $purposeStr, $dirStr, $permissionsStr);
    }

}