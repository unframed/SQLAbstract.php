<?php

require_once('test/SQLAbstractTest.php');

$t = new TestMore();

$t->plan(3);

$rows = array(
	array('index' => 1, 'value' => 3),
	array('index' => 1, 'value' => 2),
	array('index' => 2, 'value' => 0),
	array('index' => 1, 'value' => 4),
	array('index' => 2, 'value' => 5),
	array('index' => 3, 'value' => 2)
);

list($keyColumn, $valueColumn) = SQLAbstract::indexColumns(array_keys($rows[0]));

$t->is($keyColumn, 'index', "SQLAbstract::indexColumns");
$t->is($valueColumn, 'value', "SQLAbstract::indexColumns");

$index = new JSONMessage(SQLAbstract::indexRows($rows));

$t->is($index->uniform(), (
	'{'
	.'"1":[3,2,4],'
	.'"2":[0,5],'
	.'"3":[2]'
	.'}'
), "SQLAbstract::indexRows");