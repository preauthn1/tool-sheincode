<?php
namespace app\middleware;

class Cors
{
    public function handle($request, \Closure $next)
    {
        $origin = (string) $request->header('Origin', '*');
        $headers = [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Methods' => 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization,Content-Type,X-Requested-With',
            'Access-Control-Max-Age' => '86400',
        ];

        if (strtoupper($request->method()) === 'OPTIONS') {
            return response('', 204, $headers);
        }

        $response = $next($request);
        foreach ($headers as $key => $value) {
            $response->header([$key => $value]);
        }

        return $response;
    }
}
