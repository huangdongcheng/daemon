<?php

namespace Merchant\Config;

use ArrayAccess;

class LoadConfig implements ArrayAccess
{
    public $items = [];

    public function __construct($item)
    {
        $this->items = $item;
    }

    public function offsetExists($offset)
    {
        
    }

    public function offsetGet($offset)
    {

    }

    public function offsetSet($offset, $value)
    {

    }

    public function offsetUnset($offset)
    {

    }

    public function set($key, $value)
    {
        if(!isset($this->items[$key])){
            $this->items[$key] = $value;
        }
    }

    public function get($key)
    {
        if(isset($this->items[$key])){
            return $this->items[$key];
        }

        return;
    }
}