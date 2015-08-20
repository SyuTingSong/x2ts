<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 15/8/20
 * Time: 下午3:45
 */

namespace x2ts;


trait TEventDispatcher {
    protected $_events = array();
    public function on($event, $callback, $state=null) {
        $this->_events[$event][] = array(
            'callback' => $callback,
            'state' => $state,
        );
    }
    public function dispatch($event) {
        $args = func_get_args();
        array_shift($args);
        $i = 0;
        if (isset($this->_events[$event])) {
            foreach ($this->_events[$event] as $listener) {
                if (is_callable($listener['callback'])) {
                    $p = $args;
                    array_unshift($p, $listener['state']);
                    $r = call_user_func_array($listener['callback'], $p);
                    if ($r === false) {
                        return false;
                    }
                    $i++;
                }
            }
        }
        return $i;
    }
}