<?php

namespace Phoenix;

/**
 * Class ConfigControl
 *
 * @property $config
 * @property $placeholders
 *
 * @package Phoenix
 */
class ConfigControl extends Base
{
    /**
     * @var
     */
    protected $_config;
    /**
     * @var
     */
    private $configFileList;
    /**
     * @var
     */
    private $configSelected;

    /**
     * @var
     */
    protected $_placeholders;

    /**
     *
     * ConfigControl constructor.
     */
    public function __construct()
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
     * @return array|bool
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
    private function processRequest()
    {
        $configSelected = $_POST['config-select'] ?? '';
        if (empty($configSelected))
            return false;
        $fileList = $this->getConfigFileList();
        foreach ($fileList as $file) {
            if ($configSelected === $file['name'])
                return $this->configSelected = $file;
        }
        return false;
    }

    /**
     * @param object|null $config
     * @return object|stdClass
     */
    public function setConfig(object $config = null)
    {
        if (!empty($config))
            return $this->_config = $config;

        $base_config = include CONFIG_DIR . 'base-config.php';
        $configSelected = $this->getConfigSelected();
        $site_config = [];
        if (file_exists($configSelected['path'])) {
            $site_config = include $configSelected['path'];
            $message = '<strong>' . ucfirst($configSelected['name']) . '</strong> site specific config loaded.';
        } else
            $message = '<h3>No site specific config currently loaded.</h3>';
        $this->log($message, 'info');

        $config = array_replace_recursive($base_config, $site_config);

        //Just overwrite plugins array, don't merge
        $config['wordpress']['plugins'] = $site_config['wordpress']['plugins'] ?? $site_config['wordpress']['plugins'] ?? $config['wordpress']['plugins'] ?? [];
        return $this->_config = array_to_object($config);
    }

    /**
     * @return \stdClass
     */
    protected function config(): \stdClass
    {
        //print_r($this->_config);
        if (!empty($this->_config))
            return $this->_config;
        return $this->setConfig();
        //$this->_config ?? new \stdClass;
    }

    /**
     * Recursive function to substitute placeholder strings in config with actual value
     *
     * @param object|null $config
     * @param array $placeholders
     * @return object
     */
    public function substitutePlaceholders(object $config = null, array $placeholders = [])
    {
        foreach ($placeholders as $placeholder => $actualValue) {

            foreach ($config as $key => &$value) {

                if (is_array($value) || is_object($value))
                    $value = $this->substitutePlaceholders($value, $placeholders);
                elseif (is_string($value)) {
                    if (strpos($value, $placeholder) !== false) {
                        $value = str_replace($placeholder, $actualValue, $value);
                    }
                }

                if (strpos($key, $placeholder) !== false) {
                    $newKey = str_replace($placeholder, $actualValue, $key);
                    if (is_array($config)) {
                        $config[$newKey] = $value;
                        unset($config[$key]);
                    }
                    if (is_object($value)) {
                        $config->$newKey = $value;
                        unset($config->$key);
                    }
                }
            }
        }
        return $config;
    }
}