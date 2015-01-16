<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(6);

list($sql, $params) = $sqlAbstract->selectStatement('view', array(
	'filter' => array(
		'column' => 'key'
		)
	));
$t->is($sql, (
	"SELECT * FROM `wp_view` WHERE `column` = ? LIMIT 30 OFFSET 0"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));

list($sql, $params) = $sqlAbstract->selectStatement('view', array(
	'filter' => array(
		'column' => array(1,2,3)
		),
	'orders' => array('one', 'two Desc')
	));
$t->is($sql, (
	"SELECT * FROM `wp_view`"
	." WHERE `column` IN (?, ?, ?)"
	." ORDER BY `one` ASC, `two` DESC"
	." LIMIT 30 OFFSET 0"
	), $sql);
$t->is($params, array(
	1, 2, 3
	), json_encode($params));

list($sql, $params) = $sqlAbstract->selectStatement('view', array(
	'where' => (
		$sqlAbstract->identifier('column')
		." = "
		.$sqlAbstract->placeholder('key')
		),
	'params' => array('key'),
	'orders' => array('one', 'two Desc'),
	'limit' => 10,
	'offset' => 20
	));
$t->is($sql, (
	"SELECT * FROM `wp_view`"
	." WHERE `column` = ?"
	." ORDER BY `one` ASC, `two` DESC"
	." LIMIT 10 OFFSET 20"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));