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

/**
 * Class RPCWrapper
 * @package x2ts\rpc
 */
abstract class RPCWrapper extends Component {
    protected $package = null;

    public function __call($name, $arguments) {
        if ($this->package === null) {
            $class = get_class($this);
            $this->package = Toolkit::to_snake_case(ltrim(substr($class, strrpos($class, '\\')), '\\'));
        }
        Toolkit::trace("Package: {$this->package}");
        return ComponentFactory::rpc($this->package)->call($name, ...$arguments);
    }
}