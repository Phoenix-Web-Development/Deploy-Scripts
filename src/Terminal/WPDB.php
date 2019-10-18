<?php

namespace Phoenix\Terminal;

use phpseclib\Net\SFTP;

/**
 * Class WPDB
 *
 * @package Phoenix\Terminal
 */
class WPDB extends AbstractTerminal
{
    /**
     * @var string
     */
    protected $logElement = 'h4';

    private const EXT = '.gz';

    /**
     * @param string $wp_dir
     * @param string $local_dest_filepath
     * @return bool
     */
    public function export(
        string $wp_dir = '',
        string $local_dest_filepath = ''): bool
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
            return $this->logError('Backup file already exists in WordPress directory.');
        $commands = 'wp db export --add-drop-table ' . $dest_paths['name']['uncompressed'] . ' --exclude_tables=bbi_parts,suzuki_parts;
        tar -vczf ' . $dest_paths['name']['compressed'] . ' ' . $dest_paths['name']['uncompressed'] . ';';
        $output = $this->exec($commands, $wp_dir);

        $success['commands'] = $this->checkWPCLI($output, true);

        if ($success['commands']) {
            $success['get'] = $this->get($dest_paths['path']['compressed'], self::trailing_char($args['local_dir'], self::EXT));
            $success['deleteCompressedFile'] = $this->deleteFile($dest_paths['path']['compressed'], false);
            $success['deleteUncompressedFile'] = $this->deleteFile($dest_paths['path']['uncompressed'], false);
        }
        $success = !in_array(false, $success, true);
        return $this->logFinish($success, $output, $commands);
    }

    /**
     * @param string $wp_dir
     * @param string $local_orig_filepath
     * @return bool
     */
    public function import(
        string $wp_dir = '',
        string $local_orig_filepath = ''
    ): bool
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
                return $this->logError('Uploading DB file via SFTP failed.');
            $uncompressed_upload = true;
        }

        $commands = '';
        if (empty($uncompressed_upload))
            $commands .= 'tar -zxvf ' . $dest_paths['name']['compressed'] . ' ' . $dest_paths['name']['uncompressed'] . ';';
        $commands .= 'wp db import ' . $dest_paths['name']['uncompressed'] . ';';
        $output = $this->exec($commands, $args['wp_dir']);

        $success['commands'] = $this->checkWPCLI($output, true);
        if ($success['commands']) {
            $success['deleteCompressedFile'] = $this->deleteFile($dest_paths['path']['compressed'], false);
            $success['deleteUncompressedFile'] = $this->deleteFile($dest_paths['path']['uncompressed'], false);
        }
        $success = !in_array(false, $success, true);
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
    ): bool
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
                return $this->logError('Destination' . $url_error);
            if (strpos($args['old_url'], 'https://') !== 0 && strpos($args['old_url'], 'http://') !== 0)
                return $this->logError('Origin' . $url_error);
        }

        $commands = $this->getSearchReplaceURLCommands($args['old_url'], $args['new_url']);

        $output = $this->exec($commands, $args['wp_dir']);
        $success = (strpos($output, 'Success') !== false && strpos($output, 'Fail') === false);
        return $this->logFinish($success, $output, $commands);
    }


    /**
     * @param string $filePath
     * @return array|bool
     */
    private
    function generateDBFilePaths(string $filePath = '')
    {
        if (empty($filePath))
            return false;
        $filename = basename($filePath);
        $filePaths['name']['uncompressed'] = rtrim($filename, self::EXT);
        $filePaths['path']['uncompressed'] = rtrim($filePath, self::EXT);
        $filePaths['name']['compressed'] = $filePaths['name']['uncompressed'] . self::EXT;
        $filePaths['path']['compressed'] = $filePaths['path']['uncompressed'] . self::EXT;
        return $filePaths;
    }

    /**
     * @param string $old_url
     * @param string $new_url
     * @return string
     */
    private
    function getSearchReplaceURLCommands(string $old_url = '', string $new_url = ''): string
    {
        //--skip-tables=<tables>
        if (empty($old_url) || empty($new_url))
            return false;
        $commands = '';

        $search_replace_urls[$old_url] = $new_url;
        $old_url = rtrim($old_url, '/');
        $new_url = rtrim($new_url, '/');
        $search_replace_urls[$old_url] = $new_url;
        $old_url = $this->removePrefix($old_url, array('http://', 'https://'));
        $new_url = $this->removePrefix($new_url, array('http://', 'https://'));
        $search_replace_urls[$old_url] = $new_url;
        $old_url = $this->removePrefix($old_url, 'www.');
        $new_url = $this->removePrefix($new_url, 'www.');

        $search_replace_urls[$old_url] = $new_url;

        foreach ($search_replace_urls as $old => $dest) {
            $commands .= 'wp search-replace "' . $old . '" "' . $dest . '" --all-tables-with-prefix --skip-tables="*_wfNotifications,*_wfHits,*_wfStatus";
                ';
        }
        return $commands;
    }

    /**
     * @param $string
     * @param $prefixes
     * @return false|string
     */
    public function removePrefix($string, $prefixes)
    {
        if (is_string($prefixes))
            $prefixes = array($prefixes);

        foreach ($prefixes as $prefix) {
            if (strpos($string, $prefix) === 0)
                $string = substr($string, strlen($prefix));
        }
        return $string;
    }

    /**
     * @param $args
     * @return bool
     */
    protected
    function validate($args = []): bool
    {
        if (empty($args['wp_dir']))
            return $this->logError("WordPress directory wasn't supplied to function.");
        if (in_array($this->getCaller(), array('export', 'import')) && empty($args['local_dir']))
            return $this->logError("Local backup filepath wasn't supplied to function.");
        if (!$this->is_dir($args['wp_dir']))
            return $this->logError(sprintf(" WordPress directory <strong>%s</strong> doesn't exist.", $args['wp_dir']));
        if (!$this->client->WP_CLI()->check())
            return $this->logError('WP CLI missing.');
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
    function mainStr($args = array()): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $string = sprintf('%s environment WordPress database', $this->environ);
        //$wp_dir = !empty($args['wp_dir']) ? ' in directory <strong>' . $args['wp_dir'] . '</strong>' : '';
        $filePath = !empty($args['local_dir']) ? ' local destination <strong>' . $args['local_dir'] . '</strong>' : ' a local destination';
        switch($action) {
            case 'import':
                $string = 'to ' . $string . ' from' . $filePath;
                break;
            case 'export':
                $string = 'from ' . $string . ' to' . $filePath;
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