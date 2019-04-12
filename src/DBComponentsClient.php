<?php

namespace Phoenix;

/**
 *
 * @method DBComponents\Database database()
 * @method DBComponents\Database db()
 * @method DBComponents\User user()
 * @method DBComponents\UserPermissions userpermissions()
 *
 * @property PDOWrap $pdo
 *
 * Class Terminal
 */
class DBComponentsClient extends BaseClient
{
    /**
     * @var string
     */
    public $environment;

    /**
     * @var PDOWrap
     */
    protected $_pdo;

    /**
     * DBComponentsClient constructor.
     * @param string $environment
     * @param PDOWrap|\PDOException $pdo
     */
    public function __construct(string $environment = 'live', $pdo = null)
    {
        parent::__construct();
        $this->environment = $environment;
        $this->pdo = $pdo;
    }

    /**
     * @param PDOWrap|null $pdo
     * @return bool|null
     */
    protected function pdo($pdo = null)
    {

        if (func_num_args() == 0) {
            if (!empty($this->_pdo))
                return $this->_pdo;
            //return false;
        }
        return $this->_pdo = $pdo;
    }

    /**
     * @param string $name
     * @return DBComponents\AbstractDBComponents|DBComponents\Database|DBComponents\User|DBComponents\UserPermissions|ErrorAbstract
     */
    public function api($name = '')
    {
        $name = strtolower($name);
        switch ($name) {
            case 'db':
            case 'database':
                $api = new DBComponents\Database($this);
                break;
            case 'user':
                $api = new DBComponents\User($this);
                break;
            case 'userprivileges':
                $api = new DBComponents\UserPrivileges($this);
                break;
            case '':
                $api = new DBComponents\AbstractDBComponents($this);
        }
        if (!empty($api))
            return $api;
        return new ErrorAbstract($this);
    }
}
