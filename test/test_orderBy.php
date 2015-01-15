<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(3);
$t->is(
	$sqlAbstract->orderBy(array()),
	'',
	"SQLAbstractTest::orderBy(array()) returns an empty string"
	);
$t->is(
	$sqlAbstract->orderBy(array('column')),
	" ORDER BY `column` ASC",
	"SQLAbstractTest::orderBy quotes and use ASC as default"
	);
$t->is(
	$sqlAbstract->orderBy(array('column', 'one', 'two DESC')),
	" ORDER BY `column` ASC, `one` ASC, `two` DESC",
	"SQLAbstractTest::orderBy quotes and validate many orders"
	);

