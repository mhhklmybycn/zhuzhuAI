<?php
/**
 * 数据库连接类（PDO 单例）
 * 读取 config/config.php 中的数据库配置
 */
class DB
{
    /** @var PDO|null */
    private static $pdo = null;

    /**
     * 获取 PDO 连接实例
     * @return PDO
     */
    public static function pdo()
    {
        if (self::$pdo === null) {
            $cfg = require dirname(__DIR__) . '/config/config.php';
            $db  = $cfg['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 出错抛异常
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 默认关联数组
                PDO::ATTR_EMULATE_PREPARES   => false,                  // 使用真实预处理
            ));
        }
        return self::$pdo;
    }

    /** 查询单行 */
    public static function one($sql, $params = array())
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ? $row : null;
    }

    /** 查询多行 */
    public static function all($sql, $params = array())
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** 执行写操作，返回受影响行数 */
    public static function run($sql, $params = array())
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** 插入并返回自增ID */
    public static function insert($sql, $params = array())
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return self::pdo()->lastInsertId();
    }
}
