<?php

namespace x2ts\view;

/**
 * Interface View
 * @package x2ts
 * @property string $_layout
 * @property-write string $pageTitle
 */
interface IView {
    /**
     * @param string $title
     * @return $this
     */
    public function setPageTitle($title);

    /**
     * @param string $layout
     * @return $this
     */
    public function setLayout($layout);

    /**
     * @return string
     */
    public function getLayout();

    /**
     * @param string $tpl
     * @param array $params [optional]
     * @param string $cacheId [optional]
     * @return string
     */
    public function render($tpl, $params = array(), $cacheId = null);

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function assign($name, $value);

    /**
     * @param array $params
     * @return $this
     */
    public function assignAll($params);
}