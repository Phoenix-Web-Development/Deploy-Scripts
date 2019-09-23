<?php

namespace Phoenix;

/**
 * Class cPanelAccount
 *
 * @package Phoenix
 */
class cPanelAccount extends Environ
{
    /**
     * @var ActionRequests
     */
    private $actionRequests;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var WHM
     */
    private $whm;

    /**
     * cPanelAccount constructor.
     *
     * @param string $environName
     * @param null $config
     * @param WHM|null $whm
     * @param ActionRequests|null $actionRequests
     */
    public function __construct($environName = 'live', $config = null, WHM $whm = null, ActionRequests $actionRequests = null)
    {
        $this->whm = $whm;
        //$this->actionRequests = $actionRequests;
        parent::__construct($environName, $config);
    }

    public function install(): void
    {
        $this->create();
    }

    /**
     * @return bool|null
     */
    public function create(): ?bool
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;

        //check for existing cPanel account
        $cPanelAccount = $this->getcPanel();
        if (!empty($cPanelAccount))
            return $this->logFinish(true, 'cPanel account already exists.');

        $success = !empty($this->whm->create_cpanel_account($args['username'], $args['domain'], (array)$args['cpanel']->create_account_args)) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    public function delete(): ?bool
    {
        $this->mainStr();
        $this->logStart();
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;


        //find cPanel account
        $cPanelAccount = $this->getcPanel();
        if (empty($cPanelAccount))
            return $this->logFinish(true, "cPanel account doesn't exist so no need to delete.");

        if ($this->canDelete($cPanelAccount))
            return false;

        $success = $this->whm->delete_cpanel_account($cPanelAccount['domain'], 'domain');
        return $this->logFinish($success);
    }

    /**
     * @param array $cPanelAccount
     * @return bool
     */
    protected function canDelete(array $cPanelAccount = []): bool
    {
        $protectedAccounts = $this->getArgs()['$protectedAccounts'] ?? [];
        if (empty($protectedAccounts))
            return $this->logError('No protected cPanel accounts. You probably want to at least protect the primary WHM cPanel account');
        foreach ($protectedAccounts as $protectedAccount) {
            $errorStr = 'cPanel Account was flagged as protected.';
            if ($protectedAccount['username'] === $cPanelAccount['user'] && $protectedAccount['domain'] === $cPanelAccount['domain'])
                return $this->logError($errorStr);
            if ($protectedAccount['username'] === $cPanelAccount['user'])
                return $this->logError($errorStr . ' Protected account has same username but different domain <strong>' . $protectedAccount['domain'] . '</strong>. Investigation warranted.');
            if ($protectedAccount['domain'] === $cPanelAccount['domain'])
                return $this->logError($errorStr . ' Protected account has same domain but different username <strong>' . $protectedAccount['username'] . '</strong>. Investigation warranted.');
        }
        return true;
    }

    /**
     * @param string $operator
     * @return array|bool
     */
    public
    function getcPanel($operator = 'AND')
    {
        $args = $this->getArgs();
        if (!$this->validate($args))
            return false;
        $cPanelAccount = $this->whm->get_cpanel_account($args['username']);
        if (empty($cPanelAccount))
            $cPanelAccount = $this->whm->get_cpanel_account($args['domain'], 'domain');
        if (empty($cPanelAccount))
            return false;

        if ($operator === 'AND' && ($cPanelAccount['domain'] !== $args['domain'] || $cPanelAccount['user'] !== $args['username']))
            return false;
        return $cPanelAccount;
    }

    /**
     * @param $args
     * @return bool
     */
    protected
    function validate(array $args = []): bool
    {
        if (empty($this->name))
            return $this->logError('Environment has no name.');
        if (empty($args))
            return $this->logError('No args supplied .');
        if (empty($args['domain']))
            return $this->logError('Domain missing from config .');
        if (empty($args['username']))
            return $this->logError('cPanel username missing from config.');
        if ($this->getCaller() !== 'getcPanel') {
            $cPanelAccount = $this->getcPanel('OR');
            if (!empty($cPanelAccount)) {
                if ($cPanelAccount['domain'] !== $args['domain'])
                    return $this->logError('Found existing cPanel account with requested user <strong>' . $cPanelAccount['user'] . '</strong> but differing domain <strong>' . $cPanelAccount['domain'] . '</strong>. Investigation warranted.');
                if ($cPanelAccount['user'] !== $args['username'])
                    return $this->logError('Found existing cPanel account with requested domain <strong>' . $cPanelAccount['domain'] . '</strong> but differing user <strong>' . $cPanelAccount['user'] . '</strong>. Investigation warranted.');
            }
        }
        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected
    function mainStr(array $args = []): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $domainStr = !empty($args['domain']) ? ' with domain <strong>' . $args['domain'] . '</strong>' : '';
        $usernameStr = !empty($args['username']) ? ' with account username <strong>' . $args['username'] . '</strong>' : '';
        return $this->_mainStr[$action] = $this->name . ' environ cPanel account' . $domainStr . $usernameStr;
    }
}