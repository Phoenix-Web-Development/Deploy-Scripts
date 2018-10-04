<?php

namespace Phoenix;

/**
 * @property array $actions
 *
 * Class Base
 */
class Base
{

    /**
     * @var array
     */
    private $_actions = array(
        'create' => array(
            'present' => 'creating',
            'past' => 'created',
            'action' => 'create'
        ),
        'delete' => array(
            'present' => 'deleting',
            'past' => 'deleted',
            'action' => 'delete'
        ),
        'get' => array(
            'present' => 'getting',
            'past' => 'got',
            'action' => 'get'
        ),
        'set' => array(
            'present' => 'setting',
            'past' => 'set',
            'action' => 'set'
        ),
        'install' => array(
            'present' => 'installing',
            'past' => 'installed',
            'action' => 'install'
        )
    );

    public function __construct()
    {

        return true;
    }

    /**
     * @param $name
     * @param $value
     */
    function __set($name, $value)
    {
        if (method_exists($this, $name)) {
            $this->$name($value);
        } else {
            // Getter/Setter not defined so set as property of object
            $this->$name = $value;
        }
    }

    /**
     * @param $name
     * @return null
     */
    function __get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        } elseif (property_exists($this, $name)) {
            // Getter/Setter not defined so return property if it exists
            return $this->$name;
        }
        return null;
    }

    public function actions(array $actions = array())
    {
        if (!empty($actions)) {
            $this->_actions = $actions;
        } else if (!empty($this->_actions)) {
            return $this->_actions;
        }
        return false;
    }

    /**
     * @param string $message_string
     * @param string $message_type
     * @return bool
     */
    public
    function log(string $message_string = '', string $message_type = 'error')
    {
        return logger()->add($message_string, $message_type);
    }
}