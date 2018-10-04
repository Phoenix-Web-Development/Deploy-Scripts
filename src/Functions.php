<?php

namespace Phoenix;

function validate_number(integer $number)
{
    $number = (int)$number;
    if (!is_numeric($number))
        return false;
    if (!is_int($number))
        return false;
    if ($number < 1)
        return false;
    return $number;
}

function array_to_object(array $array)
{
    $object = new \stdClass();
    foreach ($array as $key => $value) {
        if (is_array($value))
            $object->$key = array_to_object($value);
        else
            $object->$key = $value;
    }
    return $object;
}

function build_recursive_list($iterable = array())
{
    if (!empty($iterable) && (is_array($iterable) || is_object($iterable))) {
        $str = '';
        foreach ($iterable as $key => $value) {
            $str .= '<li>';
            if (is_array($value) || is_object($value))
                $str .= '<span class="ph_log-list-key">' . $key . '</span>' . build_recursive_list($value);
            else
                $str .= '<span class="ph_log-list-key">' . $key . ':</span> ' . $value;
            $str .= '</li>';
        }
        return sprintf('<ul class="ph_log-list">%s</ul>', $str);
    }
    return false;
}

function build_recursive_checkboxes(iterable $array = array())
{
    if (!empty($array)) {
        foreach ($array as $key => $checkbox) {
            ?>
            <div class="custom-control custom-checkbox ml-3">
                <input type="checkbox" class="custom-control-input" name="<?php echo $key; ?>"
                       value="<?php echo $key; ?>" id="<?php echo $key; ?>">
                <label class="custom-control-label"
                       for="<?php echo $key; ?>"><?php echo $checkbox['label']; ?></label>
                <?php
                if (!empty($checkbox['children'])) { ?>
                    <div style="margin-left: 1em;"><?php echo build_recursive_checkboxes($checkbox['children']); ?></div>
                <?php } ?>
            </div>
            <?php
        }
    }
}

function sort_recursive_actions(array $actions = array())
{
    $sorted_actions = array();
    foreach ($actions as $key => $action) {
        if (empty($action['condition'])) {
            $sorted_actions[$key] = $action;
        }
    }

    $sorted_actions_keys = array_keys($sorted_actions);
    foreach ($actions as $key => $action) {
        if (!empty($action['condition'])) {
            if (is_array($action['condition']))
                foreach ($action['condition'] as $con_key => $condition) {
                    if (in_array($condition, $sorted_actions_keys)) {
                        unset($action['condition'][$con_key]);
                        $sorted_actions[$condition]['children'][$key] = $action;
                    }
                }
            else {
                if (in_array($action['condition'], $sorted_actions_keys)) {
                    $dummy = $action;
                    unset($dummy['condition']);
                    $sorted_actions[$action['condition']]['children'][$key] = $dummy;
                }
            }
        }
    }

    foreach ($sorted_actions as $key => &$action) {
        if (!empty($action['children']))
            $action['children'] = sort_recursive_actions($action['children']);
    }
    return $sorted_actions;
}
