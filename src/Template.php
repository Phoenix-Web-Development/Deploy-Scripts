<?php

namespace Phoenix;

/**
 * @property array $actions
 *
 * Class Template
 */
class Template
{
    /**
     * @var null
     */
    protected static $_instance = null;

    /**
     */
    public static function instance($stuff)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($stuff);
        }
        return self::$_instance;
    }

    /**
     * Template constructor.
     */
    public function __construct()
    {
        return true;
    }

    /**
     * @param $template_name
     * @param array $args
     */
    function get($template_name, $args = array())
    {
        if (!empty($args) && is_array($args)) {
            extract($args);
        }
        $path = '../templates/' . $template_name . '.php';
        if (!file_exists($path)) {
            printf('%s does not exist.<br>', '<code>' . $path . '</code>');
            return;
        }
        include $path;
    }

    /**
     * @return bool
     */
    public function radios()
    {
        $actions = array(
            'action_create' => array('label' => 'Create'),
            'action_delete' => array('label' => 'Delete'),

        );
        foreach ($actions as $key => $action) { ?>
            <div class="custom-control custom-radio">
                <input id="<?php echo $key; ?>" name="<?php echo $key; ?>" type="radio" class="custom-control-input"
                       required>
                <label class="custom-control-label" for="<?php echo $key; ?>"><?php echo $action['label']; ?></label>
            </div>
            <?php
        }
        return true;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function checkboxes($type = 'create')
    {
        $actions = ph_d()->actionRequests->permissions;
        $filtered_actions = [];
        if (!empty($actions)) {
            foreach ($actions as $key => $action) {
                if (strpos($key, $type) !== false) {
                    $filtered_actions[$key] = $action;
                    continue;
                }
                if (!empty($action['condition']))
                    if (is_array($action['condition']))
                        foreach ($action['condition'] as $condition) {
                            if (strpos($condition, $type) !== false) {
                                $filtered_actions[$key] = $action;
                                break;
                            }
                        }
                    else
                        if (strpos($action['condition'], $type) !== false) {
                            $filtered_actions[$key] = $action;
                        }
            }
        }
        $sorted_actions = sort_recursive_actions($filtered_actions);
        build_recursive_checkboxes($sorted_actions);
        return true;
    }

    public function configRadios()
    {
        $file_list = ph_d()->configControl->getConfigFileList();
        $config_selected = ph_d()->configControl->getConfigSelected();
        foreach ($file_list as $file): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="config-select"
                       id="<?php echo $file['name']; ?>"
                       value="<?php echo $file['name']; ?>"
                    <?php if (!empty($config_selected) && $config_selected['name'] == $file['name']) echo ' checked'; ?>>
                <label class="form-check-label" for="<?php echo $file['name']; ?>">
                    <?php echo $file['name']; ?></label>
            </div>
        <?php endforeach;
    }

    public function configRadioHidden()
    {

    }
}

/**
 */
function template($stuff = false)
{
    return Template::instance($stuff);
}