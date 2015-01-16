<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

list($sql, $params) = $sqlAbstract->updateStatement('view', 'column', 'key', array(
	'one' => 3,
	'two' => 1
	));
$t->is($sql, (
	"UPDATE `wp_view` SET `one` = ?, `two` = ? WHERE `column` = ?"
	), $sql);
$t->is($params, array(
	3, 1, 'key'
	), json_encode($params));

