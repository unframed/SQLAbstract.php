<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(10);

list($sql, $params) = $sqlAbstract->filterLike(
	array(
		'column' => 'key'
		)
	);
$t->is($sql, (
	"`column` = ?"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));

list($sql, $params) = $sqlAbstract->filterLike(
	array(
		'column' => array(1,2,3)
		)
	);
$t->is($sql, (
	"`column` IN (?, ?, ?)"
	), $sql);
$t->is($params, array(1,2,3), json_encode($params));

list($sql, $params) = $sqlAbstract->filterLike(
	array(
		'one' => 3,
		'two' => 1
		)
	);
$t->is($sql, (
	"`one` = ? AND `two` = ?"
	), $sql);
$t->is($params, array(3, 1), json_encode($params));

list($sql, $params) = $sqlAbstract->filterLike(
	array(
		'one' => 3,
		'two' => 1
		),
	array(
		'column' => 'like%'
		)
	);
$t->is($sql, (
	"`one` = ? AND `two` = ? AND (`column` LIKE ?)"
	), $sql);
$t->is($params, array(3, 1, 'like%'), json_encode($params));

list($sql, $params) = $sqlAbstract->filterLike(
	array(
		'one' => 3,
		'two' => 1
		),
	array(
		'column' => 'like%',
		'three' => 'search%'
		)
	);
$t->is($sql, (
	"`one` = ? AND `two` = ? AND (`column` LIKE ? OR `three` LIKE ?)"
	), $sql);
$t->is($params, array(3, 1, 'like%', 'search%'), json_encode($params));
