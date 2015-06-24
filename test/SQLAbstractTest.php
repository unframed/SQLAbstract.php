<?php

require_once('deps/test-more-php/Test-More-OO.php');
require_once('deps/JSONMessage.php/src/JSONMessage.php');
require_once('src/SQLAbstract.php');

class SQLAbstractTest extends SQLAbstract {
	function __construct($prefix) {
		$this->_prefix = $prefix;
	}
    function driver () {
        return 'mysql';
    }
    function transaction ($callable, $arguments=NULL) {
        return call_user_func_array($callable, $arguments);
    }
    function execute ($sql, $parameters=NULL) {
        return TRUE;
    }
    function lastInsertId () {
        return 0;
    }
    function fetchOne ($sql, $parameters=NULL) {
        return array();
    }
    function fetchAll ($sql, $parameters=NULL) {
        return array();
    }
    function fetchOneColumn ($sql, $parameters=NULL) {
        return NULL;
    }
    function fetchAllColumn ($sql, $parameters=NULL) {
        return array();
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
    function databaseName () {
        return 'test';
    }
}