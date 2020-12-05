<?php

namespace TorneLIB\Compatibility\NetCurl;

abstract class Methods
{
    private static $methodList = ['getParsedResponse' => 'getParsed'];

    public static function getCompatibilityMethods()
    {
        return self::$methodList;
    }
}