<?php

namespace Phoenix\Terminal;

use phpseclib\Net\SFTP;

/**
 * Class WPDB
 * @package Phoenix\Terminal
 */
class WPDB extends AbstractTerminal
{
    /**
     * @var string
     */
    protected $logElement = 'h4';

    const EXT = '.gz';

    /**
     * @param string $wp_dir
     * @param string $local_dest_filepath
     * @return bool
     */
    public function export(
        string $wp_dir = '',
        string $local_dest_filepath = '')
    {
        $this->mainStr($wp_dir, $local_dest_filepath);
        $this->logStart();
        if (!$this->validate($wp_dir, $local_dest_filepath)) {
            return false;
        }
        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($wp_dir) . basename($local_dest_filepath));

        if ($this->file_exists($dest_paths['path']['uncompressed']) || $this->file_exists($dest_paths['path']['compressed']))
            return $this->logError("Backup file already exists in WordPress directory.");
        //$this->ssh->setTimeout(240); //exporting DB can take a while
        $exec_commands = "cd " . $wp_dir . "; 
        wp db export --add-drop-table " . $dest_paths['name']['uncompressed'] . ";
        tar -vczf " . $dest_paths['name']['compressed'] . " " . $dest_paths['name']['uncompressed'] . ";";
        $output = $this->exec($exec_commands);
        //$this->ssh->setTimeout(false); //exporting DB can take a while
        $success = false;
        if (stripos($output, 'success') === false || stripos($output, 'error') !== false)
            return $this->logFinish($success, $output);
        if (
            $this->ssh->get($dest_paths['path']['compressed'], self::trailing_char($local_dest_filepath, self::EXT))
            && $this->deleteFile($dest_paths['path']['compressed'], false)
            && $this->deleteFile($dest_paths['path']['uncompressed'], false)
        )
            $success = true;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $wp_dir
     * @param string $local_orig_filepath
     * @param string $old_url
     * @param string $dest_url
     * @return bool
     */
    public function import(
        string $wp_dir = '',
        string $local_orig_filepath = '',
        string $old_url = '',
        string $dest_url = ''
    )
    {
        $this->mainStr($wp_dir, $local_orig_filepath);
        $this->logStart();
        if (!$this->validate($wp_dir, $local_orig_filepath))
            return false;
        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($wp_dir) . basename($local_orig_filepath));

        if (!$this->put($dest_paths['path']['compressed'], $local_orig_filepath, 'file')) {
            if (!$this->put($dest_paths['path']['uncompressed'], $local_orig_filepath, 'file'))
                return $this->logError("Uploading DB file via SFTP failed.");
            $uncompressed_upload = true;
        }
        if (!empty($old_url) && !empty($dest_url)) {
            $url_error = " URL doesn't contain https:// or http:// protocol.";
            if (strpos($dest_url, 'https://') !== 0 && strpos($dest_url, 'http://') !== 0)
                return $this->logError("Destination" . $url_error);
            if (strpos($old_url, 'https://') !== 0 && strpos($old_url, 'http://') !== 0)
                return $this->logError("Origin" . $url_error);
        }
        $exec_commands = "cd " . $wp_dir . ";";
        if (empty($uncompressed_upload))
            $exec_commands .= "tar -zxvf " . $dest_paths['name']['compressed'] . ' ' . $dest_paths['name']['uncompressed'] . ";";
        $exec_commands .= "wp db import " . $dest_paths['name']['uncompressed'] . ";";
        $exec_commands .= $this->getSearchReplaceURLCommands($old_url, $dest_url);

        $output = $this->exec($exec_commands);
        $success = (stripos($output, 'success') !== false && stripos($output, 'error') === false) ? true : false;
        if ($success)
            if (!$this->deleteFile($dest_paths['path']['compressed'], false) || !$this->ssh->delete($dest_paths['path']['uncompressed'], false)) {
                $success = false;
            }
        return $this->logFinish($success, $output);
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
     * @return bool
     */
    protected
    function validate(string $wp_dir = '', string $local_filepath = '')
    {
        if (empty($wp_dir))
            return $this->logError("WordPress directory wasn't supplied to function.");
        if (empty($local_filepath))
            return $this->logError("Local backup filepath wasn't supplied to function.");
        if (!$this->is_dir($wp_dir))
            return $this->logError(sprintf(" WordPress directory <strong>%s</strong> doesn't exist.", $wp_dir));
        if (!$this->client->WP_CLI()->install_if_missing())
            return $this->logError("WP CLI missing and install failed.");
        return true;
    }

    /**
     * @param string $wp_dir
     * @param string $filepath
     * @return bool|string
     */
    protected
    function mainStr(string $wp_dir = '', string $filepath = '')
    {
        $action = $this->getCaller();

        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $string = "%s %s environment WordPress database%s %s local destination%s.";
        $wp_dir = !empty($wp_dir) ? ' in directory <strong>' . $wp_dir . '</strong>' : '';
        $filepath = !empty($filepath) ? ' <strong>' . $filepath . '</strong>' : '';
        switch ($action) {
            case 'import':
                $direction1 = "to";
                $direction2 = "from";
                break;
            case 'export':
                $direction1 = "from";
                $direction2 = "to";
                break;
            default:
                $direction1 = $direction2 = '';
        }
        return $this->_mainStr[$action] = sprintf($string, $direction1, $this->environment, $wp_dir, $direction2, $filepath);
    }
}