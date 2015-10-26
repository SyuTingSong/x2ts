<?php

namespace x2ts\app;

use ReflectionMethod;
use ReflectionParameter;
use x2ts\http\Request;
use x2ts\http\Response;
use x2ts\TGetterSetter;
use x2ts\Toolkit;
use x2ts\ComponentFactory;

/**
 * Class Action
 * @package x2ts\app
 */
abstract class Action {
    use TGetterSetter;
    /**
     * @var Request @req
     */
    protected $request;

    /**
     * @var Response $response
     */
    protected $response;

    protected $suffix;

    public function __construct($request, $response, $suffix = '') {
        $this->request = $request;
        $this->response = $response;
        $this->suffix = $suffix;
    }

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
        Toolkit::trace("App Start: " . $this->request->server('REQUEST_URI'));
        try {
            $this->_run($pArgs);
        } catch (ApplicationExitException $e) {
            if ($m = $e->getMessage()) {
                Toolkit::trace("App end with message " . $e->getMessage() . "\n" . $e->getTraceAsString());
            } else {
                Toolkit::trace('App end without message' . "\n" . $e->getTraceAsString());
            }
        } catch (\Exception $e) {
            Toolkit::log(sprintf(
                "%s is thrown at %s(%d) with message: %s\nCall stack:\n%s",
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            ), X_LOG_ERROR, 'x2ts\app\Action::run');
        }
        $this->response->response();
        Toolkit::trace("App Exit: " . $this->request->server('REQUEST_URI'));
    }

    /**
     * @param string $name
     * @param mixed $default
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
     * @param string $name
     * @param mixed $default
     * @return string
     */
    public function query($name = null, $default = null) {
        return $this->request->query($name, $default);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function post($name = null, $default = null) {
        return $this->request->post($name, $default);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function header($name = null, $default = null) {
        return $this->request->header($name, $default);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function cookie($name = null, $default = null) {
        return $this->request->cookie($name, $default);
    }

    /**
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function server($name = null, $default = null) {
        return $this->request->server($name, $default);
    }

    /**
     * @param string $name
     * @param array $default
     * @return mixed
     */
    public function file($name = null, $default = null) {
        return $this->request->file($name, $default);
    }

    /**
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @return $this
     */
    public function setCookie($key, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httpOnly = null) {
        $this->response->setCookie($key, $value, $expire, $path, $domain, $secure, $httpOnly);
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
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
     * @return $this
     */
    public function out($words) {
        $num = func_num_args();
        $args = func_get_args();
        for ($i = 0; $i < $num; $i++) {
            $this->response->appendBody(strval($args[$i]));
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
        var_dump($var);
        $this->out(ob_get_contents());
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
     * @param $ms
     * @param null $data
     * @param null $goto
     * @return $this
     */
    public function jsonEcho($msg, $data = null, $goto = null) {
        $this->jsonOutput(1, $msg, $data, $goto);
        return $this;
    }

    /**
     * output a json encoded object to report an error
     *
     * @param string $msg The human readable error message
     * @param mixed $data [optional]
     * @param string $goto [optional] The target url to redirect
     * @return $this
     */
    public function jsonError($msg, $data = null, $goto = null) {
        $this->jsonOutput(0, $msg, $data, $goto);
        return $this;
    }

    private function jsonOutput($ok, $msg, $data, $goto) {
        $res = array('OK' => $ok);
        $res['message'] = $msg;
        if (isset($data))
            $res['data'] = $data;
        if (!empty($goto))
            $res['goto'] = $goto;

        $this->setHeader('Content-Type', 'application/json')->out(json_encode($res));
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function setSession($name, $value) {
        $_SESSION[$name] = $value;
        return $this;
    }

    /**
     * Redirect user to the new url
     *
     * @param string $location
     * @param int $statusCode
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
     * @return $this
     */
    public function setTitle($title) {
        ComponentFactory::view()->setPageTitle($title);
        return $this;
    }

    /**
     * Render a template and returns html
     *
     * @param string $tpl
     * @param array $params
     * @param string $cacheId
     * @return string
     */
    public function render() {
        $args = func_get_args();
        if (isset($args[0]) && is_string($args[0])) {
            $tpl = array_shift($args);
        } else {
            $tpl = get_class($this);
            $tpl = substr($tpl, strrpos($tpl, '\\') + 1, -strlen(ComponentFactory::router()->conf['actionSuffix']));
            $tpl = Toolkit::to_snake_case($tpl);
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
     * @param array $params
     * @param string $cacheId
     * @return $this
     */
    public function display() {
        return $this->out(call_user_func_array([$this, 'render'], func_get_args()));
    }
}
