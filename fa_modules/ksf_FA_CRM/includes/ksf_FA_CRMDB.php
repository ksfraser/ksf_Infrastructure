<?php
/**
 * DB Service for ksfraser/Database wrapper
 */
namespace ksf;

class DatabaseService
{
    private static $instance = null;
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new \Ksfraser\Database\Db();
        }
        return self::$instance;
    }
}
