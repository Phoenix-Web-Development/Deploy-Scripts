<?php

namespace Phoenix;

/**
 * Class ConfigControl
 * @package Phoenix
 */
class ConfigControl extends Base
{
    /**
     * @var
     */
    private $config;
    /**
     * @var
     */
    private $configFileList;
    /**
     * @var
     */
    private $configSelected;

    /**
     * ConfigControl constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->processRequest();
    }

    /**
     * @return array|bool
     */
    public function getConfigFileList()
    {

        if (!empty($this->configFileList))
            return $this->configFileList;
        $glob = glob(CONFIG_DIR . 'sites/*.php');
        if (empty($glob))
            return false;
        $fileList = array();
        foreach ($glob as $filepath) {
            $filename = basename($filepath);
            $filename = pathinfo($filename, PATHINFO_FILENAME);
            $fileList[$filename] = array(
                'path' => $filepath,
                'name' => $filename
            );
        }
        return $this->configFileList = $fileList;
    }

    /**
     * @return bool
     */
    public function getConfigSelected()
    {
        if (!empty($this->configSelected))
            return $this->configSelected;
        return false;
    }

    /**
     * @return bool|mixed
     */
    function processRequest()
    {
        $configSelected = $_POST['config-select'] ?? false;
        if (empty($configSelected))
            return false;
        $fileList = $this->getConfigFileList();
        foreach ($fileList as $file) {
            if ($configSelected == $file['name'])
                return $this->configSelected = $file;
        }
        return false;
    }

    /**
     * @return \stdClass
     */
    function getConfig()
    {
        if (!empty($this->config))
            return $this->config;
        $base_config = include CONFIG_DIR . 'base-config.php';
        $configSelected = $this->getConfigSelected();
        $site_config = array();
        if (file_exists($configSelected['path'])) {
            $site_config = include $configSelected['path'];
            $this->log("<strong>" . ucfirst($configSelected['name']) . "</strong> site specific config loaded.", 'info');
        } else {
            $this->log("No site specific config currently loaded.", 'info');
        }

        $config = array_merge_recursive($base_config, $site_config);
        return $this->config = array_to_object($config);
    }
}