<?php

namespace Phoenix;

/**
 *
 * @method DBComponents\Database database()
 * @method DBComponents\Database db()
 * @method DBComponents\User user()
 * @method DBComponents\UserPrivileges userprivileges()
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
    public $environ;

    /**
     * @var PDOWrap
     */
    protected $_pdo;

    /**
     * DBComponentsClient constructor.
     *
     * @param string $environ
     * @param PDOWrap|\PDOException $pdo
     */
    public function __construct(string $environ = 'live', $pdo = null)
    {
        parent::__construct();
        $this->environ = $environ;
        $this->pdo = $pdo;
    }

    /**
     * @param PDOWrap|null $pdo
     * @return bool|null
     */
    protected function pdo($pdo = null): ?bool
    {

        if (!empty($this->_pdo) && func_num_args() === 0) return $this->_pdo;
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
