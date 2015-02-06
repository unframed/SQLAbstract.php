<?php

class SQLAbstractPDO extends SQLAbstract {
    /**
     *
     */
    final static function open ($dsn, $username=NULL, $password=NULL, $options=array()) {
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_WARNING;
        $pdo = new PDO($dsn, $username, $password, $options);
        return $pdo;
    }
    /**
     *
     */
    final static function openMySQL ($name, $user, $password, $host='localhost', $port='3306') {
        $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name;
        $pdo = self::open(
            $dsn, $user, $password,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
        return $pdo;
    }
    private $_pdo;
    private $_prefix;
    private $_quotes;
    final function __construct ($pdo, $prefix='') {
        $this->_pdo = $pdo;
        $this->_prefix = $prefix;
        $this->_quotes = $this->quotes();
    }
    final function pdo () {
        return $this->_pdo;
    }
    final function driver () {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    final function transaction ($callable, $arguments=NULL) {
        $transaction = FALSE;
        if ($arguments === NULL) {
            $arguments = array($this);
        }
        $pdo = $this->pdo();
        try {
            $transaction = $pdo->beginTransaction();
            $result = call_user_func_array($callable, $arguments);
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            if ($transaction) {
                $pdo->rollBack();
            }
            throw $this->exception($e->getMessage(), $e);
        }
    }
    final private function _bindValue ($st, $index, $value) {
        if (is_int($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_INT);
        } elseif (is_bool($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_BOOL);
        } elseif (is_null($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_NULL);
        } elseif (!is_scalar($value)) {
            throw $this->exception('cannot bind non scalar '.json_encode($value));
        } else {
            return $st->bindValue($index, $value); // String
        }
    }
    final private function _statement ($sql, $parameters) {
        try {
            $st = $this->pdo()->prepare($sql);
            if ($parameters !== NULL) {
                if (JSONMessage::is_list($parameters)) {
                    $index = 1;
                    foreach ($parameters as $value) {
                        $this->_bindValue($st, $index, $value);
                        $index = $index + 1;
                    }
                } else {
                    throw $this->exception('Type Error - $parameters not a List');
                }
            }
            if ($st->execute() !== FALSE) {
                return $st;
            }
            $info = $st->errorInfo();
            $exception = $this->exception(
                $info[2]."\n".$sql."\n".json_encode($parameters)
                );
        } catch (PDOException $e) {
            $exception = $this->exception(
                $e->getMessage()."\n".$sql."\n".json_encode($parameters), $e
                );
        }
        throw $exception;
    }
    final function execute ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->rowCount();
    }
    final function lastInsertId () {
        return $this->pdo()->lastInsertId();
    }
    final function fetchOne ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_ASSOC);
    }
    final function fetchAll ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }
    final function fetchOneColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_COLUMN);
    }
    final function fetchAllColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }
    final function prefix($name='') {
        return $this->_prefix.$name;
    }
    final function identifier($name) {
        list($openQuote, $closeQuote) = $this->_quotes;
        if (strpos($closeQuote, $name) !== FALSE) {
            throw $this->exception('possible SQL injection in: '.json_encode($name));
        }
        return $openQuote.$name.$closeQuote;
    }
    final function placeholder($value) {
        return '?';
    }
}