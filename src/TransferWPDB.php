<?php

namespace Phoenix;

/**
 * Class WordPress
 * @package Phoenix
 */
class TransferWPDB extends AbstractDeployer
{

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var
     */
    private $terminals;


    function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $fromEnviron
     * @param string $destEnviron
     * @param TerminalClient $fromTerminal
     * @param TerminalClient $destTerminal
     * @return bool|null
     */
    function transfer(
        string $fromEnviron = '',
        string $destEnviron = '',
        TerminalClient $fromTerminal = null,
        TerminalClient $destTerminal = null
    )
    {
        $this->mainStr($fromEnviron, $destEnviron);
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs($fromEnviron, $destEnviron);
        if (empty($args))
            return false;

        $fromFilepath = $this->getFilePath($fromEnviron, $args['from']['db_name']);

        if (!$fromTerminal->wp_db()->export($args['from']['dir'], $fromFilepath))
            return $this->logError("Export failed.");
        if (!$this->backup($destEnviron, $destTerminal))
            return $this->logError("Backup failed.");

        if (!$destTerminal->wp_db()->import(
            $args['dest']['dir'], $fromFilepath . '.gz', $args['from']['url'], $args['dest']['url']
        ))
            return $this->logError("Import failed.");

        $wpOption['directory'] = $args['dest']['dir'];
        $wpOption['option']['name'] = 'blog_public';
        $wpOption['option']['value'] = $destEnviron == 'live' ? 1 : 0;
        $updateSearchVisibility = $destTerminal->wp()->setOption($wpOption);

        $success = !empty($updateSearchVisibility) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param string $environ
     * @param TerminalClient|null $terminal
     * @return bool|null
     */
    function backup(string $environ = '', TerminalClient $terminal = null)
    {
        $this->mainStr($environ);
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs($environ);
        if (empty($args))
            return false;
        $success = $terminal->wp_db()->export($args['from']['dir'], $this->getFilePath($environ, $args['from']['db_name']));
        return $this->logFinish($success);

    }

    /**
     * @return bool
     */
    function validate()
    {
        return true;
    }

    protected
    function getFilePath(string $environ = '', string $db_name = '')
    {
        $filename = $db_name . '-' . $environ . date("-Y-m-d-H_i_s") . '.sql';
        return BASE_DIR . '/../backups/' . $filename;
    }

    /**
     * @param string $fromEnviron
     * @param string $destEnviron
     * @return mixed
     */
    protected
    function getArgs(string $fromEnviron = '', string $destEnviron = '')
    {

        $args['from']['environ'] = $fromEnviron;
        $args['from']['dir'] = ph_d()->get_environ_dir($fromEnviron, 'web');
        if (empty($args['from']['dir']))
            return $this->logError("Couldn't get web directory.");
        $args['from']['db_name'] = ph_d()->config->environ->$fromEnviron->db->name ?? '';
        if (empty($args['from']['db_name']))
            $this->logError(" DB name missing from config");

        if ($this->getCaller() == 'transfer') {
            $args['from']['url'] = ph_d()->get_environ_url($fromEnviron);
            $args['dest']['environ'] = $destEnviron;
            $args['dest']['dir'] = ph_d()->get_environ_dir($destEnviron, 'web');
            $args['dest']['url'] = ph_d()->get_environ_url($destEnviron);
        }

        return $args;
    }

    /**
     * @param string $environ
     * @param TerminalClient $terminal
     */
    /*
    public function setTerminal(string $environ = '', TerminalClient $terminal = null)
    {
        $this->terminals[$environ] = $terminal;
    }
*/
    /**
     * @param $environ
     * @return mixed
     */
    /*
    public function getTerminal($environ)
    {
        return $this->terminals[$environ];
    }
*/
    /**
     * @param string $fromEnviron
     * @param string $destEnviron
     * @return string
     */
    protected
    function mainStr(string $fromEnviron = '', string $destEnviron = '')
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $fromString = !empty($fromEnviron) ? ' ' . $fromEnviron . ' environ' : '';
        $destString = !empty($destEnviron) ? ' to ' . $destEnviron . ' environ' : '';
        return $this->_mainStr[$action] = sprintf('%s WordPress DB%s', $fromString, $destString);
    }
}