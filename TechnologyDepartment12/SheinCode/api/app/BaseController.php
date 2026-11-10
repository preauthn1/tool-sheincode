<?php

namespace app;

use think\App;
use think\Request;

abstract class BaseController
{
    protected Request $request;
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;

        $this->initialize();
    }

    protected function initialize(): void
    {
    }
}
