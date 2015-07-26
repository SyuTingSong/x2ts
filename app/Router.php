<?php

namespace x2ts\app;

use x2ts\Component;
use x2ts\http\Request;
use x2ts\http\Response;
use x2ts\Toolkit;

class Router extends Component {
    protected static $_conf = array(
        'defaultAction' => '/index',
        'actionSuffix' => 'Action',
        'baseUri' => '/',
    );

    /**
     * @param Request $req
     * @param Response $res
     * @return bool
     */
    public function route($req, $res) {
        $uri = $req->server('REQUEST_URI');
        list($path, $query) = explode('?', $uri, 2);
        $path = trim(substr($path, strlen($this->conf['baseUri'])), '/');
        if ($path === '')
            $path = trim($this->conf['defaultAction'], '/');
        $pArgs = []; // position based arguments
        for ($parts = explode('/', $path); count($parts); array_unshift($pArgs, array_pop($parts))) {
            $p = $parts;
            $classNameWithoutNS = array_pop($p);
            array_push($p, Toolkit::toCamelCase("$classNameWithoutNS {$this->conf['actionSuffix']}", true));
            $class = 'action\\' . implode('\\', $p);
            if (class_exists($class)) {
                $action = new $class($req, $res);
                if ($action instanceof Action) {
                    $action->run($pArgs);
                    return true;
                }
            }
        }
        $res->setHeader('Status', 404, true, 404);
        $res->setBody('Not Found');
        $res->response();
        Toolkit::trace("Action not fount: $path");
        return false;
    }
}