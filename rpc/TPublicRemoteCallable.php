<?php
/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2016/8/25
 * Time: 下午12:14
 */
namespace x2ts\rpc;


use ReflectionObject;
use x2ts\Toolkit;

trait TPublicRemoteCallable {
    public function getRPCMethods() {
        Toolkit::log(__METHOD__, X_LOG_NOTICE);
        $rf = new ReflectionObject($this);
        $publicMethods = $rf->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($publicMethods as $method) {
            if ($method->name !== __METHOD__) {
                yield $method->name;
            }
        }
    }
}