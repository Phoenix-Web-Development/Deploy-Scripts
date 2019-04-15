<?php

namespace Phoenix\DBComponents;

/**
 * Class DatabaseComponents
 * @package Phoenix
 */
class Database extends AbstractDBComponents
{
    function check(array $args = [])
    {
        if (!$this->validate($args))
            return false;

        $existingDB = $this->pdo->run("SELECT SCHEMA_NAME
            FROM INFORMATION_SCHEMA.SCHEMATA
            WHERE SCHEMA_NAME = '" . $args['name'] . "'"
        );
        $success = $existingDB->fetch()['SCHEMA_NAME'] == $args['name'] ? true : false;
        return $success;
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
            return $this->logError("DB already exists.");

        $this->pdo->run("CREATE DATABASE " . $args['name'] . ";");
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
            return $this->logError("DB doesn't exist to delete.");

        $this->pdo->run('DROP DATABASE ' . $args['name'] . ';');
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
            'name',
        ];

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
        $dbName = !empty($args['name']) ? ' <strong>' . $args['name'] . '</strong>' : '';

        return $this->_mainStr[$action] = sprintf('%s database schema%s', $this->environment, $dbName);
    }
}