<?php

namespace Phoenix\DBComponents;

/**
 * Class User
 *
 * @package Phoenix\DBComponents
 */
class UserPrivileges extends AbstractDBComponents
{
    /**
     * @param array $args
     * @return bool
     */
    public function check(array $args = []): bool
    {
        if (!$this->validate($args))
            return false;
        $existingUser = $this->pdo->run("SHOW GRANTS FOR '" . $args['username'] . "'@'localhost'");
        $results = $existingUser->fetchAll();
        foreach ($results as $result) {
            if (strpos($result['Grants for ' . $args['username'] . '@localhost'],
                    'GRANT ALL PRIVILEGES ON `' . $args['name'] . "`.* TO '" . $args['username'] . "'@'localhost'") !== false)
                return true;
        }
        return false;
    }

    /**
     * @param array $args
     * @return bool|null
     */
    public function give(array $args = []): ?bool
    {
        $this->mainStr($args);
        $this->logStart();
        if (!$this->validate($args))
            return false;
        if ($this->check($args))
            return $this->logFinish(true, 'User already has DB privileges.');
        $stmt = 'GRANT ALL PRIVILEGES ON ' . $args['name'] . ".* TO '" . $args['username'] . "'@'localhost';";
        //d($stmt);
        $this->pdo->run($stmt);
        $success = $this->check($args);

        return $this->logFinish($success);
    }

    /**
     * Alias for give
     *
     * @param array $args
     * @return bool|null
     */
    public function create(array $args = []): ?bool
    {
        return $this->give($args);
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
            'name'
        ];

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
        if (func_num_args() === 0) {
            return $this->_mainStr[$action];
        }
        $dbUser = !empty($args['username']) ? ' to user <strong>' . $args['username'] . '</strong>' : '';
        $dbName = !empty($args['name']) ? ' for database <strong>' . $args['name'] . '</strong>' : '';
        //%s
        return $this->_mainStr[$action] = sprintf('%s database permissions%s%s', $this->environ, $dbUser, $dbName);
    }
}