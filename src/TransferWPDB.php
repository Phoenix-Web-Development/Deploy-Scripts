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
     * TransferWPDB constructor.
     *
     * @param cPanelAccount|cPanelSubdomain|Environ $fromEnviron
     * @param TerminalClient $fromTerminal
     * @param cPanelAccount|cPanelSubdomain|Environ $destEnviron
     * @param TerminalClient|null $destTerminal
     */
    public function __construct($fromEnviron, TerminalClient $fromTerminal, $destEnviron = null, TerminalClient $destTerminal = null)
    {
        $this->fromEnviron = $fromEnviron;
        $this->fromTerminal = $fromTerminal;
        $this->destEnviron = $destEnviron;
        $this->destTerminal = $destTerminal;
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

        if (!$this->fromTerminal->wp_db()->export($args['from']['dir'], $fromFilepath))
            return $this->logError('Export failed.');

        $success['backup'] = $this->backup();
        if (!$success['backup'])
            return $this->logError('Backup failed.');

        $success['import'] = $this->destTerminal->wp_db()->import($args['dest']['dir'], $fromFilepath . '.gz');
        if (!$success['import'])
            return $this->logError('Import failed.');

        $success['replaceURLs'] = $this->destTerminal->wp_db()->replaceURLs($args['dest']['dir'], $args['from']['url'], $args['dest']['url']);


        $wpOptions = array(
            'option' => array(
                'name' => 'blog_public',
                'value' => $this->destEnviron->name === 'live' ? 1 : 0
            )
        );
        $wpOption = array(
            'directory' => $args['dest']['dir'],
            'option' => array(
                'name' => 'blog_public',
                'value' => $this->destEnviron->name === 'live' ? 1 : 0
            )
        );
        $success['search_visibility_option'] = $this->destTerminal->wp()->setOption($wpOption);

        //$this->destTerminal->wp()->setOptions($args);

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
        $args['from']['db_name'] = ph_d()->config->environ->$fromEnviron->db->name ?? '';

        if ($this->getCaller() === 'transfer') {
            $args['from']['url'] = $this->fromEnviron->getEnvironURL(true, true);
            $args['dest']['dir'] = $this->destEnviron->getEnvironDir('web');
            $args['dest']['url'] = $this->destEnviron->getEnvironURL(true, true);
        }
        return $args;
    }


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