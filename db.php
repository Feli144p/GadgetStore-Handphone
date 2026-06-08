<?php
// Database connection settings. Ubah sesuai environment MySQL kamu.
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gadget_store');

function dbConnect()
{
    static $connection;
    if ($connection === null) {
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($connection->connect_error) {
            die('Database connection failed: ' . $connection->connect_error);
        }
        $connection->set_charset('utf8mb4');
    }
    return $connection;
}

function dbEscape($value)
{
    return dbConnect()->real_escape_string($value);
}

function dbQuery($sql)
{
    $result = dbConnect()->query($sql);
    if ($result === false) {
        trigger_error('Database query error: ' . dbConnect()->error . '\nSQL: ' . $sql, E_USER_WARNING);
    }
    return $result;
}

function dbFetchAll($result)
{
    $items = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $result->free();
    }
    return $items;
}

function dbFetchRow($result)
{
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $result->free();
        return $row;
    }
    return null;
}

function dbInsertId()
{
    return dbConnect()->insert_id;
}

function dbBeginTransaction()
{
    dbConnect()->begin_transaction();
}

function dbCommit()
{
    dbConnect()->commit();
}

function dbRollback()
{
    dbConnect()->rollback();
}
