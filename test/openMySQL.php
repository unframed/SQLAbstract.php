<?php

require_once('test/SQLAbstractTest.php');
require_once('src/SQLAbstractPDO.php');

$t = new TestMore();

$t->plan(1);

$pdo = SQLAbstractPDO::openMySQL(
	'wp', 'test', 'dummy'
	);

$t->is(TRUE, TRUE, 'openMySQL did not fail');
