<?php

namespace x2ts\app;

use x2ts\Component;
use x2ts\http\Request;
use x2ts\http\Response;
use x2ts\TEventDispatcher;
use x2ts\Toolkit;

class Router extends Component {
    use TEventDispatcher;
    protected static $_conf = array(
        'defaultAction' => '/index',
        'actionSuffix' => 'Action',
        'actionSuffixOmissible' => false,
        'baseUri' => '/',
    );

    /**
     * @param Request $req
     * @param Response $res
     * @return bool
     */
    public function route($req, $res) {
        $uri = $req->server('REQUEST_URI');
        if ($this->dispatch('PreRoute', $uri) === false) {
            return false;
        }
        list($path) = explode('?', $uri, 2);
        $path = trim(substr($path, strlen($this->conf['baseUri'])), '/');
        if ($path === '')
            $path = trim($this->conf['defaultAction'], '/');
        $pArgs = []; // position based arguments
        for (
            $parts = explode('/', $path), $lastPart=true;
            count($parts);
            array_unshift($pArgs, array_pop($parts))
        ) {
            $p = $parts;
            $classNameWithoutNS = array_pop($p);
            $suffix = '';
            if ($lastPart && ($lastDot = strrpos($classNameWithoutNS, '.')) !== false) {
                $suffix = substr($classNameWithoutNS, $lastDot + 1);
                $classNameWithoutNS = substr($classNameWithoutNS, 0, $lastDot);
                $lastPart = false;
            }
            array_push($p, Toolkit::toCamelCase("$classNameWithoutNS {$this->conf['actionSuffix']}", true));
            $class = 'action\\' . implode('\\', $p);
            if ($this->conf['actionSuffixOmissible'] && !class_exists($class)) {
                array_pop($p);
                array_push($p, Toolkit::toCamelCase($classNameWithoutNS, true));
                $class = 'action\\' . implode('\\', $p);
            }

            if (class_exists($class)) {
                $action = new $class($req, $res, $suffix);
                if ($action instanceof Action) {
                    if ($this->dispatch('PostRoute', $action, $suffix) === false) {
                        return false;
                    }
                    $action->run($pArgs);
                    return true;
                }
            }
        }
        $res->setHeader('Status', 404, true, 404);
        $res->setBody('Not Found');
        $res->response();
        Toolkit::trace("Action not found: $path");
        return false;
    }

    public function onPreRoute($callback, $state=null) {
        $this->on('PreRoute', $callback, $state);
        return $this;
    }

    public function onPostRoute($callback, $state=null) {
        $this->on('PostRoute', $callback, $state);
        return $this;
    }
}