<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2017/2/10
 * Time: 下午9:07
 */

namespace x2ts\event;


use x2ts\Toolkit;

class Event {
    public $name;

    public $dispatcher;

    public $action;

    public $state;

    public $data;

    private $stopped = false;

    public function __construct(array $props = []) {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }

    public function __toString() {
        return $this->name ?? '';
    }

    public function stopAttaching($message) {
        Toolkit::log("Event {$this->name} stopped attaching with message $message", X_LOG_NOTICE);
        $this->stopped = true;
        return $this;
    }

    public function isAttachingStopped(): bool {
        return (bool) $this->stopped;
    }
}