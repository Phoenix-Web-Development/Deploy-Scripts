<?php

namespace Phoenix;

/**
 * Class DatabaseComponents
 *
 * @package Phoenix
 */
class DatabaseComponents extends AbstractDeployer
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var cPanelAccount|cPanelSubdomain|Environ
     */
    private $environ;

    /**
     * @var string
     */
    protected $logElement = 'h3';

    /**
     * @var WHM
     */
    private $whm;

    /**
     * @var DBComponentsClient
     */
    private $pdo;

    public function __construct($environ = null, $config, WHM $whm = null, DBComponentsClient $pdo = null)
    {
        $this->environ = $environ;
        $this->config = (array)$config;
        $this->pdo = $pdo;
        $this->whm = $whm;
        parent::__construct();
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


        if ($this->environ->name !== 'local') {
            $success['created_db_user'] = $this->whm->create_db_user($args['username'], $args['password'], $args['cpanel_account']['user']);
            $success['created_db'] = $this->whm->create_db($args['name'], $args['cpanel_account']['user']);
            $success['added_user_to_db'] = $this->whm->db_user_privileges('set', $args['username'], $args['name'], $args['cpanel_account']['user']);
        } else {
            $success['created_db'] = $this->pdo->db()->create($args);
            $success['created_db_user'] = $this->pdo->user()->create($args);
            $success['added_user_to_db'] = $this->pdo->userprivileges()->create($args);
        }
        $success = !in_array(false, $success, true) ? true : false;

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


        if ($this->environ->name !== 'local') {
            $success['deleted_db'] = $this->whm->delete_db($args['name'], $args['cpanel_account']['user']);
            $success['deleted_db_user'] = $this->whm->delete_db_user($args['username'], $args['cpanel_account']['user']);
        } else {
            $success['deleted_db'] = $this->pdo->db()->delete($args);
            $success['deleted_db_user'] = $this->pdo->user()->delete($args);
        }

        $success = !in_array(false, $success, true) ? true : false;
        return $this->logFinish($success);
    }

    /**
     * @param $args
     * @return bool
     */
    protected function validate($args): bool
    {
        if ($this->environ->name === 'local' && is_a($this->pdo->pdo, 'PDOException'))
            return $this->logError('Failed to establish database connection. ' . $this->pdo->pdo->getMessage() . ' ' . (int)$this->pdo->pdo->getCode());
        if (empty($args))
            return $this->logError("Couldn't get args");

        if (empty($args['name']))
            return $this->logError('DB name is missing from config.');
        if (empty($args['username']))
            return $this->logError('DB username is missing from config.');
        if (empty($args['password']))
            return $this->logError('DB password is missing from config.');
        return true;
    }

    /**
     * @return array|bool|null
     */
    protected function getArgs()
    {
        $args = $this->config;


        if ($this->environ->name !== 'local') {
            $args['cpanel_account'] = $this->environ->findcPanel();
            if (!$args['cpanel_account'])
                return $this->logError(sprintf("Couldn't find %s cPanel account.", $this->environ->name));
        }
        return $args;
    }

    /**
     * @return string
     */
    protected function mainStr(): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $cpanelStr = $this->environ->name !== 'local' ? ' cPanel' : '';
        return $this->_mainStr[$action] = sprintf('%s%s database components', $this->environ->name, $cpanelStr);
    }
}