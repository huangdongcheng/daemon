<?php

namespace Merchant\Foundation;

class Application extends Container
{
    protected $basePath;

    public function __construct($basePath)
    {
        $this->instance = $this;

        $this->bindInContainer($basePath);

        return $this;
    }

    public function bindInContainer($basePath)
    {
        $this->basePath = ltrim($basePath, '\/');

        $this->instance('path.app', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.Config', $this->configPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
    }

    public function path()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app';
    }

    public function basePath()
    {
        return $this->basePath;
    }

    public function configPath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'Config';
    }

    public function storagePath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'storage';
    }

    public function databasePath()
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'database';
    }
}