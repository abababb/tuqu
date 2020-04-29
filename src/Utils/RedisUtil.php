<?php

namespace App\Utils;

class RedisUtil
{
    /**
     * 命令转可以直接写REDIS的格式
     *
     * @param mixed $command
     * @param mixed $arguments
     * @return void
     */
    public static function writeRedisProtocol($command, $arguments)
    {
        $cmdlen = strlen($command);
        $reqlen = count($arguments) + 1;

        $buffer = "*{$reqlen}\r\n\${$cmdlen}\r\n{$command}\r\n";

        foreach ($arguments as $argument) {
            $arglen = strlen($argument);
            $buffer .= "\${$arglen}\r\n{$argument}\r\n";
        }

        return $buffer;
    }

    public static function readRedisProtocol($raw)
    {
    }
}
