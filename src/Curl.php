<?php

namespace Phoenix;

/**
 * Class Curl
 */
class Curl
{

    /**
     * @var
     */
    private $handler;

    /**
     * @var array
     */
    public $headers = array();

    /**
     * @var string
     */
    public $userpwd = '';

    /**
     * @var string
     */
    private $base_query_url = '';

    /**
     * Curl constructor.
     * @param $base_query_url
     * @param bool $authorisation
     * @param bool $userpwd
     * @param string $content_type
     */
    public function __construct($base_query_url, $authorisation = false, $userpwd = false, $content_type = 'application/json')
    {
        if (empty($base_query_url) || empty($base_query_url))
            return false;

        return $this->init($base_query_url, $authorisation, $userpwd, $content_type);
    }

    /**
     * @param $base_query_url
     * @param bool $authorisation
     * @param bool $userpwd
     * @param string $content_type
     * @return bool
     */
    public function init($base_query_url, $authorisation = false, $userpwd = false, $content_type = 'application/json')
    {
        if (empty($base_query_url) || empty($base_query_url))
            return false;

        $this->base_query_url = $base_query_url;

        if (!empty($authorisation)) {
            if (is_string($authorisation))
                $auth_string = $authorisation;
            elseif (is_array($authorisation)) {
                $auth_string = $authorisation['type'] . ' ';
                if (!empty($authorisation['user']) && !empty($authorisation['password']))
                    $auth_string .= $authorisation['user'] . ':' . $authorisation['password'];
                elseif (empty($authorisation['user']) && !empty($authorisation['password']))
                    $auth_string .= $authorisation['password'];
                elseif (!empty($authorisation['user']) && empty($authorisation['password']))
                    $auth_string .= $authorisation['user'];
            }
            $this->headers['auth'] = 'Authorization: ' . $auth_string;
        }

        if (!empty($content_type))
            $this->headers['content_type'] = 'Content-Type: ' . $content_type;

        if (!empty($userpwd)) {
            if (is_string($userpwd))
                $this->userpwd = $userpwd;
            elseif (is_array($userpwd) && count($userpwd) > 0)
                $this->userpwd = $userpwd['user'] . ':' . $userpwd['password'];
        }

        if (empty($this->handler)) {
            $this->handler = curl_init();
            curl_setopt($this->handler, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($this->handler, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->handler, CURLOPT_RETURNTRANSFER, true);
            if (!empty($this->userpwd))
                curl_setopt($this->handler, CURLOPT_USERPWD, $this->userpwd);
        }
    }

    /**
     * @param bool $header
     */
    public function set_header($header = false)
    {

    }

    /**
     * @param string $query_url
     * @param bool $args
     * @param string $arg_type
     * @param string $request_type
     * @return array|bool
     */
    public function api_call($query_url = '', $args = false, $arg_type = 'json', $request_type = 'GET')
    {
        if (empty($this->handler)) {
            logger()->add('Curl hasn\'t been initialised yet.');
            return false;
        }

        $query = $this->base_query_url . $query_url;
        //print_r($query . '<br>');
        if (!empty($args)) {
            if (is_string($args))
                $arg_string = $args;
            elseif (is_array($args)) {

                if (empty($arg_type) || !in_array($arg_type, array('json', 'get'))) {
                    logger()->add('Unexpected arg type setting.');
                    return false;
                }

                if ($arg_type == 'json') {
                    $arg_string = json_encode($args);
                    $this->headers['content-length'] = 'Content-Length: ' . strlen($arg_string);
                } elseif ($arg_type == 'get') {
                    $arg_string = http_build_query($args);
                    $query .= '?' . $arg_string;
                }
            }
        } else {
            if (!empty($this->headers['content-length']))
                unset($this->headers['content-length']);
        }

        switch ($request_type) {
            case 'PUT':
                curl_setopt($this->handler, CURLOPT_POST, false);
                curl_setopt($this->handler, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'POST':
                curl_setopt($this->handler, CURLOPT_POST, true);
                curl_setopt($this->handler, CURLOPT_CUSTOMREQUEST, NULL);
                break;
            case 'DELETE':
                curl_setopt($this->handler, CURLOPT_POST, false);
                curl_setopt($this->handler, CURLOPT_CUSTOMREQUEST, 'DELETE');
            case 'GET':
                break;
            default:
                logger()->add('Curl request type must be either PUT, POST, DELETE or GET.', 'error');
                return false;
                break;
        }

        if (!empty($arg_string))
            curl_setopt($this->handler, CURLOPT_POSTFIELDS, $arg_string);

        if (DEBUG == true) {
            $debug_arg_string = !empty($arg_string) ? $arg_string : 'N/A';
            $debug_message = '<p><strong>Debug:</strong><br>Curl URL - ' . $query . '.<br>Arg string - ' . $debug_arg_string . '.<br>Request type - ' . $request_type . '.<br>';
            if (!empty($this->headers)) {
                $debug_message .= 'Headers -<br>';
                foreach ($this->headers as $name => $header) {
                    $debug_message .= '&nbsp;&nbsp;&nbsp;&nbsp;' . $header . '<br>';
                }
            }
            $debug_message .= '</p>';
            if (function_exists('logger')) {
                logger()->add($debug_message, 'debug');
            } else
                echo $debug_message;
        }

        if (!empty($this->headers))
            curl_setopt($this->handler, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($this->handler, CURLOPT_URL, $query);

        $result = json_decode(curl_exec($this->handler), true);

        $http_status = curl_getinfo($this->handler, CURLINFO_HTTP_CODE);

        return array('result' => $result, 'http_status' => $http_status);
    }
}