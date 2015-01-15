<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(4);

list($sql, $params) = $sqlAbstract->insertStatement('view', array(
	'column' => 'key',
	'one' => 3,
	'two' => 1
	));
$t->is($sql, (
	"INSERT INTO `wp_view` (`column`, `one`, `two`) VALUES (?, ?, ?)"
	), $sql);
$t->is($params, array(
	'key', 3, 1
	), json_encode($params));

list($sql, $params) = $sqlAbstract->insertStatement('view', array(
	'column' => 'key',
	'one' => 3,
	'two' => 1
	), 'REPLACE');
$t->is($sql, (
	"REPLACE INTO `wp_view` (`column`, `one`, `two`) VALUES (?, ?, ?)"
	), $sql);
$t->is($params, array(
	'key', 3, 1
	), json_encode($params));