<?php

namespace Phoenix;

/**
 * Class DatabaseComponents
 * @package Phoenix
 */
class DatabaseComponents extends AbstractDeployer
{

    public $environ;

    protected $logElement = 'h3';

    private $whm;

    private $pdo;

    function __construct($environ = 'live', WHM $whm = null, DBComponentsClient $pdo = null)
    {
        $this->environ = $environ;
        $this->logElement = 'h3';
        $this->pdo = $pdo;
        $this->whm = $whm;
        parent::__construct();
    }

    /**
     * @return bool|null
     */
    function create()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return $this->logError("Couldn't get args");

        if ($this->environ != 'local') {
            $created_db_user = $this->whm->create_db_user($args['username'], $args['password']);
            $created_db = $this->whm->create_db($args['name']);
            $added_user_to_db = $this->whm->db_user_privileges('set', $args['username'], $args['name']);
        } else {

            $created_db = $this->pdo->db()->create($args);
            $created_db_user = $this->pdo->user()->create($args);
            $added_user_to_db = true;
        }
        $success = !empty($created_db_user) && !empty($created_db) && !empty($added_user_to_db) ? true : false;

        return $this->logFinish($success);
    }

    /**
     * @return bool|null
     */
    function delete()
    {
        $this->mainStr();
        $this->logStart();
        if (!$this->validate())
            return false;
        $args = $this->getArgs();
        if (!$args)
            return $this->logError("Couldn't get args");

        if ($this->environ != 'local') {
            $deleted_db = $this->whm->delete_db($args['name']);
            $deleted_db_user = $this->whm->delete_db_user($args['username']);

        } else {
            $deleted_db = $this->pdo->db()->delete($args);
            $deleted_db_user = $this->pdo->user()->delete($args);
        }
        $success = !empty($deleted_db) && !empty($deleted_db_user) ? true : false;
        return $this->logFinish($success);

    }

    /**
     * @return bool
     */
    protected function validate()
    {
        if ($this->environ == 'local' && is_a($this->pdo->pdo, 'PDOException'))
            return $this->logError("Failed to establish database connection. " . $this->pdo->pdo->getMessage() . ' ' . (int)$this->pdo->pdo->getCode());
        return true;
    }

    /**
     * @return array|bool|null
     */
    protected function getArgs()
    {
        $environ = $this->environ;

        $args = (array)ph_d()->config->environ->$environ->db ?? null;
        if (!isset($args['name'], $args['username'], $args['password']))
            return $this->logError("DB name, username and/or password are missing from config.");

        if ($environ != 'local') {

            $args['cpanel_account'] = ph_d()->find_environ_cpanel($environ);
            if (!$args['cpanel_account'])
                return $this->logError(sprintf("Couldn't find %s cPanel account.", $environ));
        }
        return $args;
    }

    protected function mainStr()
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $cpanelStr = $this->environ != 'local' ? ' cPanel' : '';
        return $this->_mainStr[$action] = sprintf('%s%s database components', $this->environ, $cpanelStr);
    }
}