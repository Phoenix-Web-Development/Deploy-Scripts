<?php

namespace Phoenix\DBComponents;

/**
 * Class DatabaseComponents
 *
 * @package Phoenix
 */
class Database extends AbstractDBComponents
{
    public function check(array $args = []): bool
    {
        if (!$this->validate($args))
            return false;

        $existingDB = $this->pdo->run("SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME = '" . $args['name'] . "'"
        );
        return $existingDB->fetch()['SCHEMA_NAME'] === $args['name'];
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
            return $this->logFinish(true, 'DB already exists.');

        $this->pdo->run('CREATE DATABASE ' . $args['name'] . ';');
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
            return $this->logFinish(true, 'No need to delete, DB ' . $args['name'] . " doesn't exist to delete.");

        $this->pdo->run('DROP DATABASE ' . $args['name'] . ';');
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
        if (!empty($this->_mainStr[$action]) && func_num_args() === 0)
            return $this->_mainStr[$action];
        $dbName = !empty($args['name']) ? ' <strong>' . $args['name'] . '</strong>' : '';

        return $this->_mainStr[$action] = sprintf('%s database schema%s', $this->environment, $dbName);
    }
}