<?php

namespace Phoenix\DBComponents;

/**
 * Class User
 * @package Phoenix\DBComponents
 */
class UserPrivileges extends AbstractDBComponents
{
    function check(array $args = [])
    {
        if (!$this->validate($args))
            return false;

        $existingUser = $this->pdo->run("SELECT User FROM mysql.user
            WHERE User = '" . $args['username'] . "'"
        );
        $result = $existingUser->fetch();
        d($result);
        $success = $result['User'] == $args['username'] ? true : false;
        return $success;
        //GRANT USAGE ON *.* TO 'potentialabilitygroup'@'loc...
    }

    /**
     * @param array $args
     * @return bool|null
     */
    function create(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if ($this->check($args))
            return $this->logError("User already exists.");

        $this->pdo->run("GRANT ALL ON '" . $args['name'] . "'.* TO '" . $args['username'] . "'@'localhost'; FLUSH PRIVILEGES;");
        $success = $this->check($args);

        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool|null
     */
    function delete(array $args = [])
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if (!$this->check($args))
            return $this->logError("User doesn't exist to delete.");

        $this->pdo->run("DROP USER '" . $args['username'] . "'@localhost;");
        $success = $this->check($args) ? false : true;

        return $this->logFinish($success);
    }

    /**
     * @param array $args
     * @return bool
     */
    function validate(array $args = [])
    {
        if (empty($args))
            return $this->logError("No args inputted to method.");

        $argKeys = [
            'username',
        ];
        if ($this->getCaller() == 'create')
            $argKeys[] = 'password';
        foreach ($argKeys as $argKey) {
            if (empty($args[$argKey]))
                return $this->logError("Argument <strong>" . $argKey . "</strong> missing from input");
        }

        return true;
    }

    /**
     * @param array $args
     * @return string
     */
    protected function mainStr(array $args = [])
    {
        $action = $this->getCaller();
        if (func_num_args() == 0) {
            if (!empty($this->_mainStr[$action]))
                return $this->_mainStr[$action];
        }
        $dbUser = !empty($args['username']) ? ' <strong>' . $args['username'] . '</strong>' : '';

        return $this->_mainStr[$action] = sprintf('%s database user%s', $this->environment, $dbUser);
    }
}