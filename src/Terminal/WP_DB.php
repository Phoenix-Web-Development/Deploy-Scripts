<?php

namespace Phoenix\Terminal;

use phpseclib\Net\SFTP;

/**
 *
 * @property string $main_string
 *
 * Class WP_DB
 * @package Phoenix\Terminal
 */
class WP_DB extends AbstractTerminal
{
    const EXT = '.gz';

    private $_main_string;

    /**
     * @param string $wp_dir
     * @param string $local_dest_filepath
     * @return bool
     */
    function export(
        string $wp_dir = '',
        string $local_dest_filepath = '')
    {
        $error_string = "Can't export WP DB. ";
        if (!$this->validate($wp_dir, $local_dest_filepath, $error_string)) {
            return false;
        }
        $error_string = "Can't export " . $this->main_string('export');
        $this->logStart("export", $wp_dir, $local_dest_filepath);
        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($wp_dir) . basename($local_dest_filepath));

        if ($this->file_exists($dest_paths['path']['uncompressed']) || $this->file_exists($dest_paths['path']['compressed'])) {
            $this->log($error_string . " Backup file already exists in WordPress directory.");
            return false;
        }
        $this->ssh->setTimeout(240); //exporting DB can take a while
        $exec_commands = "cd " . $wp_dir . "; 
        wp db export --add-drop-table " . $dest_paths['name']['uncompressed'] . ";
        tar -vczf " . $dest_paths['name']['compressed'] . " " . $dest_paths['name']['uncompressed'] . ";";
        d($exec_commands);
        $output = $this->exec($exec_commands);
        $this->ssh->setTimeout(false); //exporting DB can take a while
        d($output);
        if (stripos($output, 'success') === false || stripos($output, 'error') !== false) {
            d('fail');
            $this->log($error_string . " WP DB export failed." . $output);
            return false;
        }
        $success = false;
        if ($this->ssh->get($dest_paths['path']['compressed'], self::trailing_char($local_dest_filepath, self::EXT))
            && $this->ssh->delete($dest_paths['path']['compressed'], false)
            && $this->ssh->delete($dest_paths['path']['uncompressed'], false))
            $success = true;

        return $this->logFinish('export', $output, $success);
        /*
        $this->log("Successfully exported " . $message, 'success');
        return true;
    } else
{
$this->log($error_string . "  CLI export succeeded but couldn't download backup file." . $output);
return false;
        */

//$this->log("Failed to export " . $message . $output);
//return false;
    }

    /**
     * @param string $wp_dir
     * @param string $local_orig_filepath
     * @param string $old_url
     * @param string $dest_url
     * @return bool
     */
    function import(
        string $wp_dir = '',
        string $local_orig_filepath = '',
        string $old_url = '',
        string $dest_url = ''
    )
    {
        $error_string = "Can't import WP DB. ";
        if (!$this->validate($wp_dir, $local_orig_filepath, $error_string)) {
            return false;
        }
        $error_string = "Can't import " . $this->main_string('import');
        $this->logStart("import", $wp_dir, $local_orig_filepath);

        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($wp_dir) . basename($local_orig_filepath));
        d($dest_paths['path']['compressed']);
        d($dest_paths['path']['uncompressed']);
        d($local_orig_filepath);

        if (!$this->ssh->put($dest_paths['path']['compressed'], $local_orig_filepath, SFTP::SOURCE_LOCAL_FILE)) {
            if (!$this->ssh->put($dest_paths['path']['uncompressed'], $local_orig_filepath, SFTP::SOURCE_LOCAL_FILE)) {
                $this->log($error_string . " Uploading DB file via SFTP failed.");
                return false;
            }
            $uncompressed_upload = true;
        }
        if (!empty($old_url) && !empty($dest_url)) {
            if (strpos($dest_url, 'https://') !== 0 && strpos($dest_url, 'http://') !== 0) {
                $this->log($error_string . " Destination URL doesn't contain https:// or http:// protocol.");
                return false;
            }
            if (strpos($old_url, 'https://') !== 0 && strpos($old_url, 'http://') !== 0) {
                $this->log($error_string . " Origin URL string doesn't contain https:// or http:// protocol.");
                return false;
            }
        }
        $exec_commands = "cd " . $wp_dir . ";";
        if (empty($uncompressed_upload))
            $exec_commands .= "tar -zxvf " . $dest_paths['name']['compressed'] . ' ' . $dest_paths['name']['uncompressed'] . ";";
        $exec_commands .= "wp db import " . $dest_paths['name']['uncompressed'] . ";";
        $exec_commands .= $this->getSearchReplaceURLCommands($old_url, $dest_url);

        d($exec_commands);

        $output = $this->exec($exec_commands, true);
        $success = (stripos($output, 'success') !== false && stripos($output, 'error') === false) ? true : false;
        if ($success)
            if (!$this->ssh->delete($dest_paths['path']['compressed'], false) || !$this->ssh->delete($dest_paths['path']['uncompressed'], false)) {
                $success = false;
            }
        //return $this->logFinish('import', );
        return $this->logFinish('import', $output, $success);
    }


    /**
     * @param string $filepath
     * @return bool
     */
    private
    function generateDBFilePaths(string $filepath = '')
    {
        if (empty($filepath))
            return false;
        $filename = basename($filepath);
        $filepaths['name']['uncompressed'] = rtrim($filename, self::EXT);
        $filepaths['path']['uncompressed'] = rtrim($filepath, self::EXT);
        $filepaths['name']['compressed'] = $filepaths['name']['uncompressed'] . self::EXT;;
        $filepaths['path']['compressed'] = $filepaths['path']['uncompressed'] . self::EXT;
        return $filepaths;
    }


    private
    function getSearchReplaceURLCommands(string $old_url = '', string $dest_url = '')
    {
        $exec_commands = '';
        if (!empty($old_url) && !empty($dest_url)) {
            $search_replace_urls[$old_url] = $dest_url;
            $old_url = rtrim($old_url, '/');
            $dest_url = rtrim($dest_url, '/');
            $search_replace_urls[$old_url] = $dest_url;
            $search_replace_urls[ltrim(ltrim($old_url, 'https://'), 'http://')] = ltrim(ltrim($dest_url, 'https://'), 'http://');
            foreach ($search_replace_urls as $old => $dest) {
                $exec_commands .= " wp search-replace '" . $old . "' '" . $dest . "';";
            }
        }
        return $exec_commands;
    }

    /**
     * @param string $wp_dir
     * @param string $local_filepath
     * @param string $error_string
     * @return bool
     */
    private
    function validate(string $wp_dir = '', string $local_filepath = '', string $error_string = '')
    {
        if (empty($wp_dir)) {
            $this->log($error_string . " WordPress directory wasn't supplied to function.");
            return false;
        }
        if (empty($local_filepath)) {
            $this->log($error_string . " Local backup filepath wasn't supplied to function.");
            return false;
        }
        if (!$this->dir_exists($wp_dir)) {
            $this->log($error_string . sprintf(" WordPress directory <strong>%s</strong> doesn't exist.", $wp_dir));
            return false;
        }
        if (!$this->client->api('WP_CLI')->install_if_missing()) {
            $this->log($error_string . " WP CLI not installed.");
            return false;
        }
        return true;
    }

    /**
     * @param string $action
     * @param string $wp_dir
     * @param string $filepath
     * @return bool|string
     */
    private
    function main_string(string $action = '', string $wp_dir = '', string $filepath = '')
    {
        if (func_num_args() == 0) {
            $this->log('No action supplied to main string function.');
            return false;
        }
        if (func_num_args() == 1) {
            if (!empty($this->_main_string[$action]))
                return $this->_main_string[$action];
            return false;
        }
        $string = "%s %s environment WordPress database in directory <strong>%s</strong> %s local destination <strong>%s</strong>.";
        switch ($action) {
            case 'import':
                $direction1 = "to";
                $direction2 = "from";
                break;
            case 'export':
                $direction1 = "from";
                $direction2 = "to";
                break;
        }
        return $this->_main_string[$action] = sprintf($string, $direction1, $this->environment, $wp_dir, $direction2, $filepath);
    }

    /**
     * @param string $action
     * @param string $wp_dir
     * @param string $filepath
     */
    private
    function logStart(string $action = '', string $wp_dir = '', string $filepath = '')
    {
        $this->log(ucfirst($this->actions[$action]['present']) . ' ' . $this->main_string($action, $wp_dir, $filepath), 'info');
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
                $this->log(sprintf('Successfully %s %s. %s', $this->actions[$action]['past'], $this->main_string($action), $output), 'success');
                return true;
            }
            $this->log(sprintf('Failed to %s %s. %s', $action, $this->main_string($action), $output));
            return false;
        }
        return null;
    }
}