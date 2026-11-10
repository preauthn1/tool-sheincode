<?php
return [
    'app_name' => 'FraudulentOrderProcessing',
    'app_host' => '',
    'app_namespace' => 'app',
    'with_route' => true,
    'default_app' => '',
    'default_timezone' => 'Asia/Shanghai',
    'app_map' => [],
    'domain_bind' => [],
    'deny_app_list' => [],
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',
    'error_message' => '页面错误！请稍后再试～',
    'show_error_msg' => true,
];
