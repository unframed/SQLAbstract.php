<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

list($sql, $params) = $sqlAbstract->countStatement('view', array(
	'filter' => array(
		'column' => 'key'
		)
	));
$t->is($sql, (
	"SELECT COUNT(*) FROM `wp_view` WHERE `column` = ?"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));