<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/17
 * Time: 下午2:32
 */

namespace x2ts\rpc;


use x2ts\Component;
use x2ts\ComponentFactory;
use x2ts\Toolkit;

abstract class RPCWrapper extends Component {
    public function __call($name, $arguments) {
        $class = get_class($this);
        $package = Toolkit::to_snake_case(ltrim(substr($class, strrpos($class, '\\')), '\\'));
        Toolkit::trace("Package: $package");
        return ComponentFactory::rpc($package)->call($name, ...$arguments);
    }
}