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
        $args = array(
            'wp_dir' => $wp_dir,
            'local_dir' => $local_dest_filepath
        );

        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args)) {
            return false;
        }
        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($wp_dir) . basename($args['local_dir']));

        if ($this->file_exists($dest_paths['path']['uncompressed']) || $this->file_exists($dest_paths['path']['compressed']))
            return $this->logError("Backup file already exists in WordPress directory.");
        $exec_commands = "wp db export --add-drop-table " . $dest_paths['name']['uncompressed'] . ";
        tar -vczf " . $dest_paths['name']['compressed'] . " " . $dest_paths['name']['uncompressed'] . ";";
        $output = $this->exec($exec_commands, $wp_dir);

        $success = false;
        if (stripos($output, 'success') === false || stripos($output, 'error') !== false)
            return $this->logFinish($success, $output);
        if (
            $this->get($dest_paths['path']['compressed'], self::trailing_char($args['local_dir'], self::EXT))
            && $this->deleteFile($dest_paths['path']['compressed'], false)
            && $this->deleteFile($dest_paths['path']['uncompressed'], false)
        )
            $success = true;
        return $this->logFinish($success, $output);
    }

    /**
     * @param string $wp_dir
     * @param string $local_orig_filepath
     * @return bool
     */
    public function import(
        string $wp_dir = '',
        string $local_orig_filepath = ''
    )
    {
        $args = array(
            'wp_dir' => $wp_dir,
            'local_dir' => $local_orig_filepath
        );
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $dest_paths = $this->generateDBFilePaths(self::trailing_slash($args['wp_dir']) . basename($args['local_dir']));

        if (!$this->put($dest_paths['path']['compressed'], $args['local_dir'], 'file')) {
            if (!$this->put($dest_paths['path']['uncompressed'], $args['local_dir'], 'file'))
                return $this->logError("Uploading DB file via SFTP failed.");
            $uncompressed_upload = true;
        }

        $exec_commands = '';
        if (empty($uncompressed_upload))
            $exec_commands .= "tar -zxvf " . $dest_paths['name']['compressed'] . ' ' . $dest_paths['name']['uncompressed'] . ";";
        $exec_commands .= "wp db import " . $dest_paths['name']['uncompressed'] . ";";
        $output = $this->exec($exec_commands, $args['wp_dir']);

        $success = (stripos($output, 'success') !== false && stripos($output, 'error') === false) ? true : false;
        if ($success)
            if (!$this->deleteFile($dest_paths['path']['compressed'], false)
                || !$this->deleteFile($dest_paths['path']['uncompressed'], false)) {
                $success = false;
            }
        return $this->logFinish($success, $output);
    }


    /**
     * @param string $wp_dir
     * @param string $old_url
     * @param string $dest_url
     * @return bool
     */
    public function replaceURLs(
        string $wp_dir = '',
        string $old_url = '',
        string $dest_url = ''
    )
    {
        $args = array(
            'wp_dir' => $wp_dir,
            'old_url' => $old_url,
            'new_url' => $dest_url
        );
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        if (!empty($args['old_url']) && !empty($args['new_url'])) {
            $url_error = " URL doesn't contain https:// or http:// protocol.";
            if (strpos($args['new_url'], 'https://') !== 0 && strpos($args['new_url'], 'http://') !== 0)
                return $this->logError("Destination" . $url_error);
            if (strpos($args['old_url'], 'https://') !== 0 && strpos($args['old_url'], 'http://') !== 0)
                return $this->logError("Origin" . $url_error);
        }

        $exec_commands = $this->getSearchReplaceURLCommands($args['old_url'], $args['new_url']);

        $output = $this->exec($exec_commands, $args['wp_dir']);
        $success = strpos($output, 'Success') !== false && strpos($output, 'Fail') === false ? true : false;
        return $this->logFinish($success, $output, $exec_commands);
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

    /**
     * @param string $old_url
     * @param string $new_url
     * @return string
     */
    private
    function getSearchReplaceURLCommands(string $old_url = '', string $new_url = '')
    {
        //--skip-tables=<tables>
        if (empty($old_url) || empty($new_url))
            return false;
        $exec_commands = '';

        $search_replace_urls[$old_url] = $new_url;
        $old_url = rtrim($old_url, '/');
        $new_url = rtrim($new_url, '/');
        $search_replace_urls[$old_url] = $new_url;

        $old_url = ltrim(ltrim($old_url, 'https://'), 'http://');
        $new_url = ltrim(ltrim($new_url, 'https://'), 'http://');
        $search_replace_urls[$old_url] = $new_url;

        $old_url = ltrim($old_url, 'www.');
        $new_url = ltrim($new_url, 'www.');
        $search_replace_urls[$old_url] = $new_url;


        foreach ($search_replace_urls as $old => $dest) {
            $exec_commands .= 'wp search-replace "' . $old . '" "' . $dest . '" --all-tables-with-prefix --skip-tables="*_wfNotifications,*_wfHits,*_wfStatus";
                ';
        }
        d($exec_commands);
        //return false;
        return $exec_commands;
    }

    /**
     * @param $args
     * @return bool
     */
    protected
    function validate($args = [])
    {
        if (empty($args['wp_dir']))
            return $this->logError("WordPress directory wasn't supplied to function.");
        if (in_array($this->getCaller(), array('export', 'import')) && empty($args['local_dir']))
            return $this->logError("Local backup filepath wasn't supplied to function.");
        if (!$this->is_dir($args['wp_dir']))
            return $this->logError(sprintf(" WordPress directory <strong>%s</strong> doesn't exist.", $args['wp_dir']));
        if (!$this->client->WP_CLI()->check())
            return $this->logError("WP CLI missing.");
        if ($this->getCaller() == 'replaceURLs') {
            if (empty($args['old_url']))
                return $this->logError("URL to replace wasn't supplied to function.");
            if (empty($args['new_url']))
                return $this->logError("Replacing URL wasn't supplied to function.");
        }
        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected
    function mainStr($args = array())
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $string = sprintf("%s environment WordPress database", $this->environment);
        //$wp_dir = !empty($args['wp_dir']) ? ' in directory <strong>' . $args['wp_dir'] . '</strong>' : '';
        $filepath = !empty($args['local_dir']) ? ' local destination <strong>' . $args['local_dir'] . '</strong>' : ' a local destination';
        switch ($action) {
            case 'import':
                $string = 'to ' . $string . ' from' . $filepath;
                break;
            case 'export':
                $string = 'from ' . $string . ' to' . $filepath;
                break;
            case 'replaceURLs':
                $string = 'in ' . $string . ' from <strong>' . $args['old_url'] . '</strong> to <strong>' . $args['new_url'] . '</strong>';
                break;
            default:
                $string = '';
        }
        return $this->_mainStr[$action] = $string;
    }
}