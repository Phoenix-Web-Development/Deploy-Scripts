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
        'authorize' => array(
            'present' => 'authorising',
            'past' => 'authorised',
            'action' => 'authorise'
        ),
        'check' => array(
            'present' => 'checking',
            'past' => 'checked',
            'action' => 'check'
        ),
        'clone' => array(
            'present' => 'cloning',
            'past' => 'cloned',
            'action' => 'clone'
        ),
        'commit' => array(
            'present' => 'committing',
            'past' => 'committed',
            'action' => 'commit'
        ),
        'create' => array(
            'present' => 'creating',
            'past' => 'created',
            'action' => 'create'
        ),
        'deauthorize' => array(
            'present' => 'deauthorising',
            'past' => 'deauthorised',
            'action' => 'deauthorise'
        ),
        'delete' => array(
            'present' => 'deleting',
            'past' => 'deleted',
            'action' => 'delete'
        ),
        'deploy' => array(
            'present' => 'deploying',
            'past' => 'deployed',
            'action' => 'deploy'
        ),
        'generate' => array(
            'present' => 'generating',
            'past' => 'generated',
            'action' => 'generate'
        ),
        'get' => array(
            'present' => 'getting',
            'past' => 'got',
            'action' => 'get'
        ),
        'install' => array(
            'present' => 'installing',
            'past' => 'installed',
            'action' => 'install'
        ),
        'move' => array(
            'present' => 'moving',
            'past' => 'moved',
            'action' => 'move'
        ),
        'remove' => array(
            'present' => 'removing',
            'past' => 'removed',
            'action' => 'remove'
        ),
        'set' => array(
            'present' => 'setting',
            'past' => 'set',
            'action' => 'set'
        ),
        'transfer' => array(
            'present' => 'transferring',
            'past' => 'transferred',
            'action' => 'transfer'
        ),
        'uninstall' => array(
            'present' => 'uninstalling',
            'past' => 'uninstalled',
            'action' => 'uninstall'
        ),
        'upload' => array(
            'present' => 'uploading',
            'past' => 'uploaded',
            'action' => 'upload'
        ),
    );

    public function __construct()
    {

        return true;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
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
    public function __get($name)
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

    static function validate_action($action = '', $actions = array(), $message = '')
    {
        if (empty($action)) {
            $fail = true;
            $fail_string = "No action inputted to %s.";
        } elseif (!in_array($action, $actions)) {
            $fail = true;
            $fail_string = "%s received '<strong>%s</strong>' as input action.";
        }
        if (!empty($fail)) {
            $debug_backtrace = !empty(debug_backtrace()[1]['function']) ? '<code>' . debug_backtrace()[1]['function'] . '()</code> function' : 'function';
            $fail_string = sprintf($fail_string, $debug_backtrace, $action, $debug_backtrace);
            Base::log(sprintf("%s Action must be %s. %s",
                $message, Base::implode_item_str($actions), $fail_string), 'error');
            return false;
        }
        return true;
    }

    static function implode_item_str(array $array = array())
    {
        foreach ($array as &$item) {
            $item = "'" . $item . "'";
        }
        $last = array_slice($array, -1);
        $first = join(", ", array_slice($array, 0, -1));
        $both = array_filter(array_merge(array($first), $last), 'strlen');
        return join(' or ', $both);
    }
}