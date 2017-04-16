<?php

namespace Merchant\Foundation;

class Container
{
    protected $instances = [];

    protected $instance = null;

    public function instance($abstract, $instance)
    {
        if (!isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $instance;
        }
    }

    public function make($abstract)
    {
        if(isset($this->instances[$abstract])){
            return $this->instances[$abstract];
        }

        
    }
}