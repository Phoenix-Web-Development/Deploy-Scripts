<?php

namespace Phoenix;

/**
 * Class cPanelAccount
 * @package Phoenix
 */
class cPanelAccount extends AbstractDeployer
{
    /**
     * @var ActionRequests
     */
    private $actionRequests;

    /**
     * @var string
     */
    public $config;

    /**
     * @var string
     */
    public $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var TerminalClient
     */
    private $terminal;

    /**
     * @var WHM
     */
    private $whm;

    /**
     * cPanelAccount constructor.
     * @param string $environ
     * @param null $config
     * @param TerminalClient|null $terminal
     * @param ActionRequests|null $actionRequests
     * @param WHM|null $whm
     */
    public function __construct($environ = 'live', $config = null, TerminalClient $terminal = null, ActionRequests $actionRequests = null, WHM $whm = null)
    {
        $this->config = $config;
        $this->environ = $environ;
        $this->terminal = $terminal;
        $this->whm = $whm;
        $this->actionRequests = $actionRequests;
        parent::__construct();
    }

    public function install()
    {
        $this->create();
    }

    /**
     * @return bool|null
     */
    public function create()
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        if (!empty($this->whm->get_cpanel_account($args['username'])))
            return $this->logFinish(true, "cPanel account with user <strong>" . $args['username'] . "</strong> already exists so no need to create one.");
        if (!empty($this->whm->get_cpanel_account($args['domain'], 'domain')))
            return $this->logFinish(true, "cPanel account with domain <strong>" . $args['domain'] . "</strong> already exists so no need to create one.");

        $success = $this->whm->create_cpanel_account($args['username'], $args['domain'], (array)$args['create_account_args']);
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function delete()
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;


        //find cPanel account
        $cPanelAccount = $this->findcPanelAccount($args['domain'], $args['username']);
        if (!$cPanelAccount)
            return $this->logFinish(true, "Apparently account doesn't exist so no need to delete.");

        foreach ($args['protected_accounts'] as $protected_account) {
            if ($protected_account['username'] == $cPanelAccount['username'] || $protected_account['domain'] == $cPanelAccount['domain'])
                return $this->logFinish(false, "Account was flagged as protected.");
        }

        $success = $this->whm->delete_cpanel_account($cPanelAccount['domain'], 'domain');
        return $this->logFinish($success);
    }


    /**
     * @param $domain
     * @param $username
     * @param string $operator
     * @return bool
     */
    public function findcPanelAccount($domain, $username, $operator = 'AND')
    {
        if (!isset($domain, $username)) {
            $this->log("Can't find cPanel account. Domain and/or username input missing. If you only have one or the other just use WHM get_cpanel_account method. ");
            return false;
        }
        $cPanel_account = $this->whm->get_cpanel_account($username);
        if (empty($cPanel_account))
            $cPanel_account = $this->whm->get_cpanel_account($domain, 'domain');
        if (empty($cPanel_account)) {
            $this->log(sprintf("Can't find existing cPanel with domain <strong>%s</strong> and/or username <strong>%s</strong>.", $domain, $username), 'info');
            return false;
        }
        if ($cPanel_account['domain'] != $domain) {
            if ($operator == 'AND') {
                $this->log(sprintf("Found cPanel account with matching username <strong>%s</strong> but different domain name. Domain is <strong>%s</strong>, searched for <strong>%s</strong>.",
                    $username, $cPanel_account['domain'], $domain), 'error');
                return false;
            }
            return $cPanel_account;
        }
        if ($cPanel_account['user'] != $username) {
            if ($operator == 'AND') {
                $this->log(sprintf("Found cPanel account with matching domain <strong>%s</strong> but different username. Username is <strong>%s</strong>, searched for <strong>%s</strong>.",
                    $domain, $cPanel_account['user'], $username), 'error');
                return false;
            }
            return $cPanel_account;
        }
        return $cPanel_account;
    }


    /**
     * @param $args
     * @return bool
     */
    protected function validate(array $args = [])
    {
        if (empty($args))
            return $this->logError("No args supplied .");
        if (empty($args['domain']))
            return $this->logError("Domain missing from config .");
        if (empty($args['username']))
            return $this->logError("cPanel username missing from config.");
        return true;
    }

    /**
     * @return array|bool
     */
    protected
    function getArgs()
    {
        //$environ = $this->environ;
        $args['domain'] = $this->config->environ->live->domain ?? '';
        $args['username'] = $this->config->environ->live->cpanel->account->username ?? '';
        $args['create_account_args'] = $this->config->environ->live->cpanel->create_account_args ?? array();
        $args['protected_accounts'] = $this->config->whm->protected_accounts ?? array();
        return $args;
    }


    /**
     * @param array $args
     * @return string
     */
    protected
    function mainStr(array $args = [])
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $domainStr = !empty($args['domain']) ? ' for domain <strong>' . $args['domain'] . '</strong>' : '';
        $usernameStr = !empty($args['username']) ? ' with account username <strong>' . $args['username'] . '</strong>' : '';
        return $this->_mainStr[$action] = 'live cPanel account' . $domainStr . $usernameStr;
    }
}