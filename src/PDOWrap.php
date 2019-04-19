<?php

namespace Phoenix;

use \PDO;

/**
 * Class PDOWrap
 * @package Phoenix
 */
class PDOWrap extends PDO
{
    //protected $pdo;

    /**
     * PDOWrap constructor.
     * @param array $args
     */
    function __construct(array $args = [])
    {
        $default_options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $dbName = !empty($args['name']) ? 'dbname=' . $args['name'] . ';' : '';

        $dsn = 'mysql:host=' . $args['host'] . ';' . $dbName . 'port=' . $args['port'] . 'charset=utf8';

        parent::__construct($dsn, $args['user'], $args['password'], $default_options);


    }

    /**
     * helper function to run prepared statements
     *
     * @param $sql
     * @param null $args
     * @return bool|false|\PDOStatement
     */
    public function run($sql, $args = NULL)
    {
        if (empty($args)) {
            return $this->query($sql);
        }
        $stmt = $this->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }

    /**
     * returns 1 row
     *
     * @param $table
     * @param string $columns
     * @param null $query
     * @return array|bool|mixed
     */
    function get_row($table, $columns = 'all', $query = null)
    {
        return $this->get_data($table, $columns, $query, true);

    }

    /**
     * @param $table
     * @param string $columns
     * @param null $query
     * @return array|bool|mixed
     */
    function get_rows($table, $columns = 'all', $query = null)
    {
        return $this->get_data($table, $columns, $query, false);
    }

    /**
     * @param $table
     * @param string $columns
     * @param null $query_args
     * @param bool $single_row
     * @return array|bool|mixed
     */
    private function get_data($table, $columns = 'all', $query_args = null, $single_row = false)
    {
        $sql = 'SELECT ';
        if (is_array($columns)) {
            $sql .= implode(', ', $columns);

        } elseif (in_array($columns, array('all', '*'))) {
            $sql .= '*';
        } else {
            ph_d()->log('Wrong columns specified when getting row from DB.');
            return false;
        }
        $sql .= ' FROM ' . $table;
        $args = null;
        if (!empty($query_args)) {
            $sql .= ' WHERE ';
            if (is_string($query_args))
                $sql .= $query_args;
            elseif (is_array($query_args)) {
                $sql_where_strings = array();
                foreach ($query_args as $key => $query_arg) {
                    if (is_array($query_arg)) {
                        $operator = $query_arg['operator'];
                        $args[$key] = $query_arg['value'];
                    } else {
                        $operator = '=';
                        $args[$key] = $query_arg;
                    }
                    $sql_where_strings[] .= $key . $operator . ':' . $key;
                    //array( 'value' => 0, 'operator' => '!=' ) ))

                }
                $sql .= implode(' AND ', $sql_where_strings);
            }
        }
        $statement = $this->run($sql, $args);
        if ($single_row)
            $result = $statement->fetch();
        else
            $result = $statement->fetchAll();
        if (!empty($result)) {
            return $result;
        }
        return false;
    }
}