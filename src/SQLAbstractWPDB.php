<?php

class SQLAbstractWPDB extends SQLAbstract {
    function __construct ($prefix='') {
        $this->_prefix = $prefix;
    }
    function transaction ($callable, $arguments=NULL) {
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
    function execute ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->query($wpdb->prepare($sql, $parameters));
    }
    function lastInsertId () {
        global $wpdb;
        return $wpdb->insert_id;
    }
    function fetchOne ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare($sql, $parameters), ARRAY_A);
    }
    function fetchAll ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare($sql, $parameters), ARRAY_A);
    }
    function fetchOneColumn ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare($sql, $parameters));
    }
    function fetchAllColumn ($sql, $parameters=NULL) {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare($sql, $parameters));
    }
    function prefix ($name='') {
        global $wpdb;
        return $wpdb->prefix.$name;
    }
    function identifier ($name) {
        return "`".$name."`";
    }
    function placeholder ($value) {
        if (!is_scalar($value)) {
            throw $this->exception("SQL query parameter not a scalar: ".json_encode($value));
        } elseif (is_integer($value) || is_bool($value)) {
            return '%d';
        } elseif (is_float($value)) {
            return '%f';
        }
        return '%s';
    }
}