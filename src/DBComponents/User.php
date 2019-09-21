<?php

namespace Phoenix\DBComponents;

/**
 * Class User
 *
 * @package Phoenix\DBComponents
 */
class User extends AbstractDBComponents
{
    public function check(array $args = []): bool
    {
        if (!$this->validate($args))
            return false;

        $existingUser = $this->pdo->run("SELECT User FROM mysql.user
            WHERE User = '" . $args['username'] . "'"
        );
        $result = $existingUser->fetch();
        $success = $result['User'] == $args['username'] ? true : false;
        return $success;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function create(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if ($this->check($args))
            return $this->logFinish(true, 'User already exists.');
        //VIA mysql_native_password
        $stmt = 'CREATE USER ' . $args['username'] . "@'localhost' IDENTIFIED BY '" . $args['password'] . "';";
        //$stmt = "CREATE USER " . $args['username'] . "@'localhost' IDENTIFIED VIA mysql_native_password USING '" . $args['password'] . "';";
        $this->pdo->run($stmt);
        $success = $this->check($args);

        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function delete(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (!$this->check($args))
            return $this->logFinish(true, 'No need to delete, user ' . $args['username'] . " doesn't exist to delete.");

        $this->pdo->run("DROP USER '" . $args['username'] . "'@'localhost';");
        $success = $this->check($args) ? false : true;

        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool
     */
    private function validate(array $args = []): bool
    {
        if (empty($args))
            return $this->logError('No args inputted to method.');

        $argKeys = [
            'username',
        ];
        if ($this->getCaller() == 'create')
            $argKeys[] = 'password';
        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError('Argument <strong>' . $argKey . '</strong> missing from input');
        }

        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function mainStr(array $args = []): string
    {
        $action = $this->getCaller();
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $dbUser = !empty($args['username']) ? ' <strong>' . $args['username'] . '</strong>' : '';

        return $this->_mainStr[$action] = sprintf('%s database user%s', $this->environment, $dbUser);
    }
}