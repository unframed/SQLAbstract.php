<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);
list($sql, $params) = $sqlAbstract->selectInColumn('view', 'column', array(3,1,2));
$t->is($sql, (
    "SELECT * FROM `wp_view` WHERE `column` IN (?,?,?)"
	), $sql);
$t->is($params, array(3,1,2), json_encode($params));

