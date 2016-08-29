<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/6/17
 * Time: 下午2:32
 */

namespace x2ts\rpc;


use ReflectionObject;
use x2ts\Component;
use x2ts\ComponentFactory;
use x2ts\Toolkit;

/**
 * Class RPCWrapper
 *
 * @package x2ts\rpc
 */
abstract class RPCWrapper extends Component {
    protected $package = null;

    public function __construct() {
        parent::__construct();
        if ($this->package === null) {
            $this->package = Toolkit::to_snake_case(
                (new ReflectionObject($this))->getShortName()
            );
        }
    }

    public function __call($name, $arguments) {
        Toolkit::trace("Package: {$this->package}");
        return ComponentFactory::rpc($this->package)->call($name, ...$arguments);
    }
}