<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(14);

function _whereParams ($sqlAbstract, $map) {
	return $sqlAbstract->whereParams(new JSONMessage($map));
}

list($sql, $params) = _whereParams($sqlAbstract, array(
	'where' => $sqlAbstract->identifier('column')." = ?",
	'params' => array('key')
	));
$t->is($sql, (
	"`column` = ?"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'column' => 'key'
		)
	));
$t->is($sql, (
	"`column` = ?"
	), $sql);
$t->is($params, array(
	'key'
	), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'column' => array(1,2,3)
		)
	));
$t->is($sql, (
	"`column` IN (?, ?, ?)"
	), $sql);
$t->is($params, array(1,2,3), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'one' => 3,
		'two' => 1
		)
	));
$t->is($sql, (
	"`one` = ? AND `two` = ?"
	), $sql);
$t->is($params, array(3, 1), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'one' => 3,
		'two' => 1
		),
	'like' => array(
		'column' => 'like%'
		)
	));
$t->is($sql, (
	"`one` = ? AND `two` = ? AND (`column` LIKE ?)"
	), $sql);
$t->is($params, array(3, 1, 'like%'), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'one' => 3,
		'two' => NULL
		),
	'like' => array(
		'column' => 'like%',
		'three' => 'search%'
		)
	));
$t->is($sql, (
	"`one` = ? AND `two` IS NULL AND (`column` LIKE ? OR `three` LIKE ?)"
	), $sql);
$t->is($params, array(3, 'like%', 'search%'), json_encode($params));

list($sql, $params) = _whereParams($sqlAbstract, array(
	'filter' => array(
		'one' => 3
		),
	'where' => "`two` > ?",
	'params' => array(1),
	'like' => array(
		'column' => 'like%',
		'three' => 'search%'
		)
	));
$t->is($sql, (
	"`two` > ? AND `one` = ? AND (`column` LIKE ? OR `three` LIKE ?)"
	), $sql);
$t->is($params, array(1, 3, 'like%', 'search%'), json_encode($params));
