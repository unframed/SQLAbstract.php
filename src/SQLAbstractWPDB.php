<?php

class SQLAbstractWPDB extends SQLAbstract {
    final function driver () {
        return 'mysql';
    }
    final function transaction ($callable, $arguments=NULL) {
        global $wpdb;
        $transaction = FALSE;
        if ($arguments === NULL) {
            $arguments = array($this);
        }
        try {
            $wpdb->query('START TRANSACTION');
            $result = call_user_func_array($callable, $arguments);
            $wpdb->query('COMMIT');
            return $result;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }
    final static private function _prepare ($sql, $parameters) {
        if ($parameters !== NULL && count($parameters) > 0) {
            global $wpdb;
            $arguments = array_merge(array($sql), $parameters);
            return call_user_func_array(array($wpdb, 'prepare'), $arguments);
        }
        return $sql;
    }
    final function execute ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->query(self::_prepare($sql, $parameters));
    }
    final function lastInsertId () {
        global $wpdb;
        return $wpdb->insert_id;
    }
    final function fetchOne ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_row(self::_prepare($sql, $parameters), ARRAY_A);
    }
    final function fetchAll ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_results(self::_prepare($sql, $parameters), ARRAY_A);
    }
    final function fetchOneColumn ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_var(self::_prepare($sql, $parameters));
    }
    final function fetchAllColumn ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_col(self::_prepare($sql, $parameters));
    }
    final function prefix ($name='') {
        global $wpdb;
        return $wpdb->prefix.$name;
    }
    final function identifier ($name) {
        if (strpos('`', $name) !== FALSE) {
            throw $this->exception('possible SQL injection in: '.json_encode($name));
        } elseif (count($name) > 64) {
            throw $this->exception('too long SQL identifier: '.json_encode($name));
        }
        return '`'.$name.'`';
    }
    final function placeholder ($value) {
        if(is_null($value)) {
            return 'NULL';
        } elseif (!is_scalar($value)) {
            throw $this->exception("SQL query parameter not a scalar: ".json_encode($value));
        } elseif (is_integer($value) || is_bool($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        }
        return '%s';
    }

}