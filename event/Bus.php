<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/2/12
 * Time: 上午9:27
 */

namespace x2ts\event;

use x2ts\Component;

class Bus extends Component {
    protected $_events = [];

    /**
     * @param string   $eventName
     * @param callable $callback
     * @param mixed    $state
     */
    public function on(string $eventName, callable $callback, $state = null) {
        $this->_events[$eventName][] = array(
            'callback' => $callback,
            'state'    => $state,
        );
    }

    /**
     * @param Event $event
     *
     * @return int the number of handler calls
     */
    public function dispatch(Event $event): int {
        if (!isset($this->_events[$event->name])) {
            return 0;
        }
        $i = 0;
        foreach ($this->_events[$event->name] as $listener) {
            if (is_callable($listener['callback'])) {
                if (null !== $listener['state']) {
                    $event->state = $listener['state'];
                }
                call_user_func($listener['callback'], $event);
                $i++;
                if ($event->isAttachingStopped()) {
                    return $i;
                }
            }
        }
        return $i;
    }
}