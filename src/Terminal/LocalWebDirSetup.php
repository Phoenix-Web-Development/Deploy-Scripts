<?php

namespace Phoenix\Terminal;

/**
 * Class LocalWebDirSetup
 * @package Phoenix\Terminal
 */
class LocalWebDirSetup extends AbstractTerminal
{

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

        $command = $this->formatSudoCommand('webdir-setup', [
            $args['web_dir'],
            $args['web_owner'],
            $args['web_group'],
            $args['project_dir'],
            $args['project_owner'],
            $args['project_group'],
            $args['log_dir']
        ]);

        $output = $this->exec($command);
        $needle = 'Successfully setup web directory at ' . $args['web_dir'] . '.';
        if (!empty($args['project_dir']))
            $needle .= ' Successfully setup project directory at ' . $args['project_dir'] . '.';
        $success = strpos($output, $needle) !== false ? true : false;

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
        $successWebDir = $this->deleteFile($args['web_dir'], true);
        $successLogDir = empty($args['log_dir']) || $this->deleteFile($args['log_dir'], true) ? true : false;
        $successProjectDir = empty($args['project_dir']) || $this->deleteFile($args['project_dir'], true) ? true : false;
        $success = $successWebDir && $successLogDir && $successProjectDir ? true : false;
        return $this->logFinish($success);
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

        $argKeys = [
            'web_dir',
            'web_owner',
            'web_group'
        ];
        if (!empty($args['log_dir']))
            $argKeys[] = 'project_dir';
        if (!empty($args['project_dir'])) {
            $argKeys = array_merge($argKeys, ['project_owner', 'project_group']);
        }

        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError(" Argument <strong>" . $argKey . "</strong> missing from input.");
        }
        if (!empty($args['project_dir'])) {
            if (strpos($args['web_dir'], $args['project_dir']) !== 0) {
                return $this->logError("Web directory is not sub-directory of project directory.");
            }
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

        $webDir = !empty($args['web_dir']) ? sprintf(' at <strong>%s</strong>', $args['web_dir']) : '';
        $logDir = !empty($args['log_dir']) ? sprintf(' and log directory at <strong>%s</strong>', $args['log_dir']) : '';
        $webPermissions = !empty($args['web_owner']) && !empty($args['web_group']) ? sprintf(' with owner <strong>%s</strong> and group <strong>%s</strong>', $args['web_owner'], $args['web_group']) : '';

        $projectDir = !empty($args['project_dir']) ? sprintf('and project directory at <strong>%s</strong>', $args['project_dir']) : '';
        $projectPermissions = !empty($args['project_owner']) && !empty($args['project_group']) ? sprintf(' with owner <strong>%s</strong> and group <strong>%s</strong>', $args['project_owner'], $args['project_group']) : '';

        return $this->_mainStr = sprintf("%s web directory%s%s%s%s%s", $this->environment, $webDir, $logDir, $webPermissions, $projectDir, $projectPermissions);
    }

}