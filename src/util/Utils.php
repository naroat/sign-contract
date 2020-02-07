<?php
namespace RanPack\SignContract;

class Utils
{
    public static function getMethodParams($class, $method_name)
    {
        $result = array();
        $method = new \ReflectionMethod($class, $method_name);
        $parameters = $method->getParameters();
        foreach ($parameters as $parameter)
        {
            $name = $parameter->getName();
            $result[] = $name;
        }
        return $result;
    }
    
    public static function isPdf($content)
    {
        $tmp = substr($content, 0, min(strlen($content), 16));
        $tmp = str_replace("\r", "\n", $tmp);
        $lines = explode("\n", $tmp);
        $line = $lines[0];
        return preg_match('/\%PDF-[0-9]+?\.[0-9]+?/', $line);
    }
}