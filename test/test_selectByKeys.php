<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);
list($sql, $params) = $sqlAbstract->selectByKeys('view', array(
	'one' => 3, 'two' => 1, 'three' => 2
	));
$t->is($sql, (
    "SELECT * FROM `wp_view` WHERE `one` = ? AND `two` = ? AND `three` = ?"
	), $sql);
$t->is($params, array(3,1,2), json_encode($params));

