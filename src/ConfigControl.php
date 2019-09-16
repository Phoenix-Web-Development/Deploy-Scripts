<?php

namespace Phoenix;

/**
 * @property $config
 * @property $placeholders
 *
 * Class ConfigControl
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
    function __construct()
    {
        parent::__construct();
        $this->processRequest();
        $config = $this->config();
        $config = $this->substitutePlaceholders($config);
        $this->setConfig($config);
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
    private function processRequest()
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
     * @param object|null $config
     * @return object|stdClass
     */
    function setConfig(object $config = null)
    {
        if (!empty($config))
            return $this->_config = $config;

        $base_config = include CONFIG_DIR . 'base-config.php';
        $configSelected = $this->getConfigSelected();
        $site_config = [];
        if (file_exists($configSelected['path'])) {
            $site_config = include $configSelected['path'];
            $message = "<strong>" . ucfirst($configSelected['name']) . "</strong> site specific config loaded.";
        } else
            $message = "<h3>No site specific config currently loaded.</h3>";
        $this->log($message, 'info');

        $config = array_replace_recursive($base_config, $site_config);

        //Just overwrite plugins array, don't merge
        $config['wordpress']['plugins'] = $site_config['wordpress']['plugins'] ?? $site_config['wordpress']['plugins'] ?? $config['wordpress']['plugins'] ?? [];
        return $this->_config = array_to_object($config);
    }

    /**
     * @return stdClass
     */
    protected function config()
    {
        //print_r($this->_config);
        if (!empty($this->_config))
            return $this->_config;
        $this->setConfig();
        return $this->_config ?? false;
    }

    /**
     * @return array|bool
     */
    protected function placeholders()
    {
        if (!empty($this->_placeholders))
            return $this->_placeholders;
        /*
                $placeholders = array(
                    'project_name' => ucwords($this->_config['project']['name'] ?? ''),
                    'root_email_folder' => $this->_config['project']['root_email_folder'] ?? '',
                    //'staging_domain'=> ph_d()->getEnvironURL('staging') ?? '',
                    'live_domain' => ph_d()->getEnvironURL('live') ?? '',
                    'live_cpanel_username' => $this->_config['environ']['live']['cpanel']['account']['username'] ?? ''
                );
        */
        $placeholders = array(
            'project_name' => ucwords($this->_config->project->name ?? ''),
            'root_email_folder' => $this->_config->project->root_email_folder ?? '',
            'staging_domain' => ph_d()->getEnvironURL('staging') ?? '',
            'live_domain' => ph_d()->getEnvironURL('live') ?? '',
            'live_cpanel_username' => $this->_config->environ->live->cpanel->account->username ?? ''
        );
        $return = [];
        foreach ($placeholders as $placeholderName => $placeholder) {
            if (empty($placeholder)) {
                $this->log(sprintf("Couldn't obtain value for <strong>%s</strong> config placeholder.", $placeholderName));
                return [];
            }
            $return['%' . $placeholderName . '%'] = $placeholder;
        }
        return $this->_placeholders = $return;
    }


    /**
     * Recursive function to substitute placeholder strings in config with actual value
     *
     * @param object|null $config
     * @return object
     */
    function substitutePlaceholders(object $config = null)
    {
        foreach ($this->placeholders as $placeholder => $actualValue) {

            foreach ($config as $key => &$value) {

                if (is_array($value))
                    $value = $this->substitutePlaceholders($value);
                elseif (is_string($value)) {
                    if (strpos($value, $placeholder) !== false) {
                        $value = str_replace($placeholder, $actualValue, $value);
                    }
                }

                if (strpos($key, $placeholder) !== false) {
                    //d($key);
                    //d($placeholder . ' ' .$actualValue);
                    $newKey = str_replace($placeholder, $actualValue, $key);
                    //d($newKey);
                    $config[$newKey] = $value;
                    unset($config[$key]);
                }
            }
        }
        return $config;
    }
}