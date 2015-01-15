<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();
$t->plan(3);
$t->is(
	$sqlAbstract->columns(array()),
	'*',
	"SQLAbstractTest::columns(array()) returns '*'"
	);
$t->is(
	$sqlAbstract->columns(array('identifier')),
	'`identifier`',
	"SQLAbstractTest::columns(array('column')) returns the quoted column"
	);
$t->is(
	$sqlAbstract->columns(array('one', 'two', 'three')),
	'`one`,`two`,`three`',
	"SQLAbstractTest::columns(array('column')) list the quoted columns"
	);

