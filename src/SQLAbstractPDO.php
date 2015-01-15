<?php

class SQLAbstractPDO extends SQLAbstract {
    protected $_pdo;
    function __construct ($pdo, $prefix='') {
        $this->_pdo = $pdo;
        $this->_prefix = $prefix;
    }
    function pdo () {
        return $this->_pdo;
    }
    // ? TODO: leave to the Unframed application ?
    function transaction ($callable, $arguments=NULL) {
        $transaction = FALSE;
        if ($arguments === NULL) {
            $arguments = array($this->_pdo);
        }
        try {
            $transaction = $this->_pdo->beginTransaction();
            $result = call_user_func_array($callable, $arguments);
            $this->_pdo->commit();
            return $result;
        } catch (Exception $e) {
            if ($transaction) {
                $this->_pdo->rollBack();
            }
            throw $e;
        }
    }
    private static function _bindValue ($st, $index, $value) {
        if (!is_scalar($value)) {
            throw new Unframed('cannot bind non scalar '.json_encode($value));
        } elseif (is_int($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_INT);
        } elseif (is_bool($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_BOOL);
        } elseif (is_null($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_NULL);
        } else {
            return $st->bindValue($index, $value); // String
        }
    }
    private function _statement ($sql, $parameters) {
        $st = $this->_pdo->prepare($sql);
        if ($parameters !== NULL) {
            if (JSONMessage::is_list($parameters)) {
                $index = 1;
                foreach ($parameters as $value) {
                    self::_bindValue($st, $index, $value);
                    $index = $index + 1;
                }
            } elseif (JSONMessage::is_map($parameters)) {
                foreach ($parameters as $key => $value) {
                    self::_bindValue($st, $key, $value);
                }
            } else {
                throw new Exception('Type Error - $parameters not an array');
            }
        }
        if ($st->execute()) {
            return $st;
        }
        $info = $st->errorInfo();
        throw new Exception($info[2]);
    }
    function execute ($sql, $parameters=NULL) {
        $this->_statement($sql, $parameters);
        return TRUE;
    }
    function lastInsertId () {
        return $this->_pdo->lastInsertId();
    }
    function fetchOne ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_ASSOC);
    }
    function fetchAll ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }
    function fetchOneColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_COLUMN);
    }
    function fetchAllColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }
    function prefix($name='') {
        return $this->_prefix.$name;
    }
    function identifier($name) {
        return "`".$name."`";
    }
    function placeholder($value) {
        return '?';
    }
}