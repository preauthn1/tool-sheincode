<?php

namespace app\middleware;

use app\utils\JwtTokenUtil;

class TokenParser
{
    public static function parse(string $token): array
    {
        return JwtTokenUtil::parse($token);
    }
}
