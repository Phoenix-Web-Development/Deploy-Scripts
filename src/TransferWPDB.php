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

        $args = $this->getArgs($fromEnviron, $destEnviron);
        $this->mainStr($fromEnviron, $destEnviron, $args['from']['url'], $args['dest']['url']);
        $this->logStart();
        if (!$this->validate($fromTerminal, $destTerminal))
            return false;
        if (empty($args))
            return false;
        $success = [];
        $fromFilepath = $this->getFilePath($fromEnviron, $args['from']['db_name']);

        if (!$fromTerminal->wp_db()->export($args['from']['dir'], $fromFilepath))
            return $this->logError("Export failed.");

        $success['backup'] = $this->backup($destEnviron, $destTerminal);
        if (!$success['backup'])
            return $this->logError("Backup failed.");

        $success['import'] = $destTerminal->wp_db()->import($args['dest']['dir'], $fromFilepath . '.gz');
        if (!$success['import'])
            return $this->logError("Import failed.");

        $success['replaceURLs'] = $destTerminal->wp_db()->replaceURLs($args['dest']['dir'], $args['from']['url'], $args['dest']['url']);

        $wpOption = array(
            'directory' => $args['dest']['dir'],
            'option' => array(
                'name' => 'blog_public',
                'value' => $destEnviron == 'live' ? 1 : 0
            )
        );
        $success['search_visibility_option'] = $destTerminal->wp()->setOption($wpOption);

        $success = !in_array(false, $success) ? true : false;
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

    /**
     * @param string $environ
     * @param string $db_name
     * @return string
     */
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
            $args['from']['url'] = ph_d()->get_environ_url($fromEnviron, true, true);
            $args['dest']['environ'] = $destEnviron;
            $args['dest']['dir'] = ph_d()->get_environ_dir($destEnviron, 'web');
            $args['dest']['url'] = ph_d()->get_environ_url($destEnviron, true, true);
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
     * @param string $fromURL
     * @param string $destURL
     * @return string
     */
    protected
    function mainStr(string $fromEnviron = '', string $destEnviron = '', string $fromURL = '', string $destURL = '')
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }


        $fromString = !empty($fromEnviron) ? ' ' . $fromEnviron . ' environ' : '';
        if (!empty($fromString) && !empty($fromURL))
            $fromString .= ' at ' . $fromURL;
        $destString = !empty($destEnviron) ? ' to ' . $destEnviron . ' environ' : '';
        if (!empty($destString) && !empty($fromURL))
            $destString .= ' at ' . $destURL;
        return $this->_mainStr[$action] = sprintf('%s WordPress DB%s', $fromString, $destString);
    }
}