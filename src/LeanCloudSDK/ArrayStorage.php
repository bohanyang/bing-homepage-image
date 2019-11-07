<?php

namespace BohanCo\BingHomepageImage\LeanCloudSDK;

use LeanCloud\Storage\IStorage;

class ArrayStorage implements IStorage
{
    private $storage;

    public function __construct(iterable $init = [])
    {
        $this->clear();

        foreach ($init as $key => $val) {
            $this->set($key, $val);
        }
    }

    public function set($key, $val)
    {
        $this->storage[$key] = $val;
    }

    public function get($key)
    {
        return $this->storage[$key] ?? null;
    }

    public function remove($key)
    {
        unset($this->storage[$key]);
    }

    public function clear()
    {
        $this->storage = [];
    }
}
