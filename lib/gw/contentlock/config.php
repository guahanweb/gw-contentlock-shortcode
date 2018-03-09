<?php
namespace GW\ContentLock;

class Config {
    static private $instances = array();
    private $data;

    static public function instance($name) {
        $instance = isset(self::$instances[$name]) ? self::$instances[$name] : null;
        if ($instance === null) {
            $instance = new Config();
            self::$instances[$name] = $instance;
        }
        return $instance;
    }

    private function __construct() {
        $this->data = array();
    }

    public function __get($k) {
        return isset($this->data[$k]) ? $this->data[$k] : null;
    }

    // is this necessary, or should it be only noop?
    public function __set($k, $v) {
        // we can only update existing values
        if ($this->data[$k] !== null) {
            $this->data[$k] = $v;
        }
    }

    public function add($k, $v) {
        if (null === $this->$k) {
            $this->data[$k] = $v;
        }
    }

    public function update($k, $v) {
        if (isset($this->data[$k])) {
            $this->data[$k] = $v;
        }
    }
}
