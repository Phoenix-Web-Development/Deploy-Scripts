<?php

namespace Phoenix;

/**
 * Class WordPress
 *
 * @package Phoenix
 */
class TransferWPDB extends AbstractDeployer
{
    /**
     * @var
     */
    private $config;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var cPanelAccount|cPanelSubdomain|Environ
     */
    private $fromEnviron;

    /**
     * @var TerminalClient|null
     */
    private $fromTerminal;

    /**
     * @var cPanelAccount|cPanelSubdomain|Environ
     */
    private $destEnviron;

    /**
     * @var TerminalClient|null
     */
    private $destTerminal;

    /**
     * @var ActionRequests|null
     */
    private $actionRequests;

    /**
     * TransferWPDB constructor.
     *
     * @param $config
     * @param $fromEnviron
     * @param TerminalClient $fromTerminal
     * @param null $destEnviron
     * @param TerminalClient|null $destTerminal
     * @param ActionRequests|null $actionRequests
     */
    public function __construct($config, $fromEnviron, TerminalClient $fromTerminal, $destEnviron = null, TerminalClient $destTerminal = null, ActionRequests $actionRequests = null)
    {
        $this->config = $config;
        $this->fromEnviron = $fromEnviron;
        $this->fromTerminal = $fromTerminal;
        $this->destEnviron = $destEnviron;
        $this->destTerminal = $destTerminal;
        $this->actionRequests = $actionRequests;
        parent::__construct();
    }

    /**
     * @return bool|null
     */
    public function transfer(): bool
    {
        $args = $this->getArgs();
        $this->mainStr($this->fromEnviron->name, $this->destEnviron->name, $args['from']['url'], $args['dest']['url']);
        $this->logStart();
        if (!$this->validate($args))
            return false;

        $success = [];
        $fromFilepath = self::getFilePath($this->fromEnviron->name, $args['from']['db_name']);

        $setCustomDBOptions = $this->actionRequests->canDo('transfer_wpdb_' . $this->fromEnviron->name . '_to_' . $this->destEnviron->name . '_options');

        if ($setCustomDBOptions) {
            $args['directory'] = $args['dest']['dir'];

            foreach ($args['options'] as $optionName => $option) {
                if ($option['value'] === '%existing_value%')
                    $existingOptions[$optionName] = $option;
            }
            if (!empty($existingOptions)) {
                $argsExistingOptions = $args;
                $argsExistingOptions['options'] = $existingOptions;
                d($argsExistingOptions);
                $existingOptions = $this->destTerminal->wp_options()->getOptions($argsExistingOptions);
                d($existingOptions);
                if (!empty($existingOptions)) {
                    foreach ($existingOptions as $existingOptionName => $existingOption) {
                        if (empty($existingOption) || $existingOption['value'] === '%existing_value%')
                            unset($args['options'][$existingOptionName]);
                        else
                            $args['options'][$existingOptionName]['value'] = $existingOption['value'];
                    }
                }
                d($args);
            }
        }
        if ($this->actionRequests->canDo('transfer_wpdb_' . $this->fromEnviron->name . '_to_' . $this->destEnviron->name . '_db')) {
            if (!$this->fromTerminal->wp_db()->export($args['from']['dir'], $fromFilepath))
                return $this->logError('Export failed.');

            $success['backup'] = $this->backup();
            if (!$success['backup'])
                return $this->logError('Backup failed.');

            $success['import'] = $this->destTerminal->wp_db()->import($args['dest']['dir'], $fromFilepath . '.gz');
            if (!$success['import'])
                return $this->logError('Import failed.');
        }
        if ($this->actionRequests->canDo('transfer_wpdb_' . $this->fromEnviron->name . '_to_' . $this->destEnviron->name . '_replace_urls'))
            $success['replaceURLs'] = $this->destTerminal->wp_db()->replaceURLs($args['dest']['dir'], $args['from']['url'], $args['dest']['url']);

        if ($setCustomDBOptions && !empty($args['options']))
            $success['setOptions'] = $this->destTerminal->wp_options()->setOptions($args);

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function backup(): ?bool
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        $success = $this->fromTerminal->wp_db()->export($args['from']['dir'], self::getFilePath($this->fromEnviron->name, $args['from']['db_name']));
        return $this->logFinish($success);
    }

    /**
     * @param $args
     * @return bool
     */
    private function validate($args): bool
    {
        if (empty($args))
            return false;
        if (empty($args['from']['dir']))
            return $this->logError("Couldn't get from environment web directory.");
        if (empty($args['from']['db_name']))
            return $this->logError('From environment DB name missing from config.');
        if ($this->getCaller() === 'transfer') {
            if (empty($args['from']['url']))
                return $this->logError('From environment URL missing from config.');
            if (empty($args['dest']['dir']))
                return $this->logError('Destination environment directory missing from config.');
            if (empty($args['dest']['url']))
                return $this->logError('Destination environment URL missing from config.');
        }
        return true;
    }

    /**
     * @param string $environ
     * @param string $dbName
     * @return string
     */
    protected static
    function getFilePath(string $environ = '', string $dbName = ''): string
    {
        $filename = $dbName . '-' . $environ . date('-Y-m-d-H_i_s') . '.sql';
        return BASE_DIR . '/../backups/' . $filename;
    }

    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        $args['from']['dir'] = $this->fromEnviron->getEnvironDir('web');
        $fromEnviron = $this->fromEnviron->name;
        $args['from']['db_name'] = $this->config->environ->$fromEnviron->db->name ?? '';

        if ($this->getCaller() === 'transfer') {
            $args['from']['url'] = $this->fromEnviron->getEnvironURL(true, true);
            $args['dest']['dir'] = $this->destEnviron->getEnvironDir('web');
            $args['dest']['url'] = $this->destEnviron->getEnvironURL(true, true);
        }
        $args['options'] = $this->destEnviron->getWPOptions('transfer') ?? [];
        return $args;
    }

    /**
     * @param string $fromEnviron
     * @param string $destEnviron
     * @param string $fromURL
     * @param string $destURL
     * @return string
     */
    protected
    function mainStr(string $fromEnviron = '', string $destEnviron = '', string $fromURL = '', string $destURL = ''): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];


        $fromString = !empty($this->fromEnviron->name) ? ' ' . $this->fromEnviron->name . ' environ' : '';
        if (!empty($fromString) && !empty($fromURL))
            $fromString .= ' at ' . $fromURL;
        $destString = !empty($destEnviron) ? ' to ' . $destEnviron . ' environ' : '';
        if (!empty($destString) && !empty($fromURL))
            $destString .= ' at ' . $destURL;
        return $this->_mainStr[$action] = sprintf('%s WordPress DB%s', $fromString, $destString);
    }
}