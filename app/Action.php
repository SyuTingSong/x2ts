<?php

namespace x2ts\app;

use Exception;
use ReflectionMethod;
use ReflectionParameter;
use x2ts\http\Request;
use x2ts\http\Response;
use x2ts\TEventDispatcher;
use x2ts\TGetterSetter;
use x2ts\Toolkit;
use x2ts\ComponentFactory;

/**
 * Class Action
 *
 * @package x2ts\app
 */
abstract class Action {
    use TGetterSetter;
    use TEventDispatcher;

    /**
     * @var Request @req
     */
    public $request;

    /**
     * @var Response $response
     */
    public $response;

    public $suffix;

    public function __construct($request, $response, $suffix = '') {
        $this->request = $request;
        $this->response = $response;
        $this->suffix = $suffix;
        $this->init();
    }

    public function init() { }

    private function _run(&$pArgs) {
        $method = Toolkit::toCamelCase('http ' . strtolower($this->server('REQUEST_METHOD', 'GET')));
        if (!method_exists($this, $method)) {
            if ($this->server('REQUEST_METHOD') === 'HEAD' && method_exists($this, 'httpGet')) {
                $method = 'httpGet';
            } else {
                $this->setStatus(405)->out('Method Not Allowed');
                return;
            }
        }
        $pArgsCount = count($pArgs);
        $rf = new ReflectionMethod($this, $method);
        if ($pArgsCount) {
            $requiredParamsNum = $rf->getNumberOfRequiredParameters();
            if ($requiredParamsNum <= $pArgsCount) {
                $rf->invokeArgs($this, $pArgs);
            } else {
                $this->setStatus(400)
                    ->out("Expecting $requiredParamsNum parameters while $pArgsCount given.");
                return;
            }
        } else {
            $params = $rf->getParameters();
            $args = [];
            foreach ($params as $param) {
                /** @var ReflectionParameter $param */
                $name = $param->getName();
                if (!is_null($this->request->query($name))) {
                    $args[] = $this->request->query($name);
                } else if ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    $this->setStatus(400)->out("missing query parameter $name");
                    return;
                }
            }
            $rf->invokeArgs($this, $args);
        }
    }

    public function run($pArgs = array()) {
        Toolkit::trace("App Start: "
            . $this->server('REQUEST_METHOD') . ' ' . $this->server('REQUEST_URI')
        );
        $this->dispatch('PreAction', $this);
        try {
            $this->dispatch('PreRun', $this);
            $this->_run($pArgs);
            $this->dispatch('PostRun', $this);
        } catch (ApplicationExitException $e) {
            if ($m = $e->getMessage()) {
                Toolkit::trace("App end with message " . $e->getMessage() . "\n" . $e->getTraceAsString());
            } else {
                Toolkit::trace('App end without message' . "\n" . $e->getTraceAsString());
            }
        } catch (Exception $e) {
            Toolkit::log($e, X_LOG_ERROR);
        }
        $this->dispatch('PostAction', $this);
        $this->response->response();
        Toolkit::trace("App Exit: "
            . $this->server('REQUEST_METHOD') . ' ' . $this->server('REQUEST_URI')
        );
    }

    public function onPreAction(callable $callback, $state = null) {
        $this->on('PreAction', $callback, $state);
        return $this;
    }

    public function onPreRun(callable $callback, $state = null) {
        $this->on('PreRun', $callback, $state);
        return $this;
    }

    public function onPostRun(callable $callback, $state = null) {
        $this->on('PostRun', $callback, $state);
        return $this;
    }

    public function onPostAction(callable $callback, $state = null) {
        $this->on('PostAction', $callback, $state);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function session($name = null, $default = null) {
        if (is_null($name))
            return $_SESSION;
        if (isset($_SESSION[$name]))
            return $_SESSION[$name];
        return $default;
    }

    /**
     * Fetch the $name var from query string
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function query($name = null, $default = null) {
        return $this->request->query($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function post($name = null, $default = null) {
        return $this->request->post($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function header($name = null, $default = null) {
        return $this->request->header($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function cookie($name = null, $default = null) {
        return $this->request->cookie($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function server($name = null, $default = null) {
        return $this->request->server($name, $default);
    }

    /**
     * @param string $name
     * @param array  $default
     *
     * @return mixed
     */
    public function file($name = null, $default = null) {
        return $this->request->file($name, $default);
    }

    /**
     * @param string $key
     * @param string $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httpOnly
     *
     * @return $this
     */
    public function setCookie($key, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httpOnly = null) {
        $this->response->setCookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return $this
     */
    public function setHeader() {
        $args = func_get_args();
        if (is_array($args[0])) {
            foreach ($args[0] as $key => $value) {
                $this->response->setHeader($key, $value);
            }
        } else {
            list($key, $value) = $args;
            $this->response->setHeader($key, $value);
        }
        return $this;
    }

    public function setStatus($statusCode) {
        $this->response->setHeader('Status', $statusCode, true, $statusCode);
        return $this;
    }

    /**
     * @param string $words
     *
     * @return $this
     */
    public function out($words) {
        $num = func_num_args();
        $args = func_get_args();
        for ($i = 0; $i < $num; $i++) {
            $this->response->appendBody((string) $args[$i]);
        }
        return $this;
    }

    public function jout($data, $options = 0, $depth = 512) {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setBody(json_encode($data, $options, $depth));
        return $this;
    }

    public function dump($var) {
        ob_start();
        /** @noinspection ForgottenDebugOutputInspection */
        var_dump($var);
        $this->out('<pre>' . ob_get_contents());
        ob_end_clean();
        return $this;
    }

    /**
     * @throws ApplicationExitException
     */
    public function end($msg = '', $logLevel = X_LOG_DEBUG) {
        throw new ApplicationExitException($msg, $logLevel);
    }

    /**
     * @param string $msg
     * @param array  $data
     * @param string $goto
     *
     * @return $this
     */
    public function jsonEcho($msg = null, $data = null, $goto = null) {
        $this->jsonOutput(0, $msg, $data, $goto);
        return $this;
    }

    /**
     * output a json encoded object to report an error
     *
     * @param int    $code
     * @param string $msg  The human readable error message
     * @param mixed  $data [optional]
     * @param string $goto [optional] The target url to redirect
     *
     * @return $this
     */
    public function jsonError($code, $msg = null, $data = null, $goto = null) {
        $this->jsonOutput($code, $msg, $data, $goto);
        return $this;
    }

    private function jsonOutput($code, $msg, $data, $goto) {
        $res = array('code' => $code);
        if (isset($msg))
            $res['message'] = (string) $msg;
        if (isset($data))
            $res['data'] = $data;
        if (!empty($goto))
            $res['goto'] = $goto;

        $this->setHeader('Content-Type', 'application/json')->out(json_encode($res));
    }

    /**
     * Redirect user to the new url
     *
     * @param string $location
     * @param int    $statusCode
     *
     * @return $this
     */
    public function redirect($location, $statusCode = 302) {
        $this->response->setHeader('Location', $location, true, $statusCode);
        return $this;
    }

    /**
     * Set view layout
     *
     * @param string $layout
     *
     * @return $this
     */
    public function setLayout($layout) {
        ComponentFactory::view()->setLayout($layout);
        return $this;
    }

    /**
     * Set page title
     *
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title) {
        ComponentFactory::view()->setPageTitle($title);
        return $this;
    }

    public function assign(...$args) {
        ComponentFactory::view()->assign(...$args);
        return $this;
    }

    /**
     * Render a template and returns html
     *
     * @param string $tpl
     * @param array  $params
     * @param string $cacheId
     *
     * @return string
     */
    public function render() {
        $args = func_get_args();
        if (isset($args[0]) && is_string($args[0])) {
            $tpl = array_shift($args);
        } else {
            $tpl = get_class($this);
            $paths = explode('\\', $tpl);
            array_shift($paths);
            $className = array_pop($paths);
            $actionSuffix = ComponentFactory::router()->conf['actionSuffix'];
            if (substr($className, strrpos($className, $actionSuffix)) === $actionSuffix) {
                $basename = substr($className, 0, -strlen($actionSuffix));
            } else {
                $basename = $className;
            }
            array_push($paths, $basename);
            foreach ($paths as &$path) {
                $path = Toolkit::to_snake_case($path);
            }
            $tpl = implode(DIRECTORY_SEPARATOR, $paths);
        }
        if (isset($args[0]) && is_array($args[0])) {
            $params = array_shift($args);
        } else {
            $params = [];
        }
        if (isset($args[0]) && is_string($args[0])) {
            $cacheId = array_shift($args);
        } else {
            $cacheId = null;
        }

        return ComponentFactory::view()->render($tpl, $params, $cacheId);
    }

    /**
     * Render a template and send html to response
     *
     * @param string $tpl
     * @param array  $params
     * @param string $cacheId
     *
     * @return $this
     */
    public function display() {
        return $this->out(call_user_func_array([$this, 'render'], func_get_args()));
    }
}
