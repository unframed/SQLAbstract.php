<?php

require_once('test/SQLAbstractTest.php');

$sql = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

list($expression, $params) = $sql->inSet(
	array(
		'a', 'b', 'c'
	),
	array(
		array(
			'a' => 1, 'b' => true, 'c' => 'test'
		),
		array(
			'a' => 2, 'b' => false, 'c' => 'again', 'ignore' => null
		)
	)
);
$t->is($expression[0], (
	"(a, b, c) IN ((?, ?, ?), (?, ?, ?))"
), $expression[0]);
$t->is($params, array(
	1, true, 'test', 2, false, 'again'
), json_encode($params));
