<?php

namespace x2ts\app;

use x2ts\Component;
use x2ts\Toolkit;

class Router extends Component {
    protected static $_conf = array(
        'defaultAction' => '/index',
        'actionSuffix' => 'Action',
    );

    /**
     * @param \x2ts\http\Request $req
     * @param \x2ts\http\Response $res
     */
    public function route($req, $res) {
        $uri = $req->server('REQUEST_URI');
        list($path, $query) = explode('?', $uri, 2);
        if ($path === '/')
            $path = $this->conf['defaultAction'];
        $path = trim($path, '/');
        $pArgs = []; // position based arguments
        for ($parts = explode('/', $path); count($parts); array_unshift($pArgs, array_pop($parts))) {
            $class = 'action\\' . implode('\\', $parts) . $this->conf['actionSuffix'];
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