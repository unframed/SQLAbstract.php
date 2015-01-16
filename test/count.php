<?php

require_once('test/SQLAbstractTest.php');
require_once('src/SQLAbstractPDO.php');

function test ($sql) {
	$t = new TestMore();
	$t->plan(1);
	$t->is(TRUE, TRUE, 'TRUE');
}

test(new SQLAbstractPDO(SQLAbstractPDO::openMySQL(
	'wp', 'test', 'dummy'
	)));
