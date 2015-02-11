<?php

require_once('test/SQLAbstractTest.php');
require_once('src/SQLAbstractPDO.php');

$t = new TestMore();

$t->plan(1);

$pdo = SQLAbstractPDO::openSQLite(':memory');

$t->is(TRUE, TRUE, 'openSQLite did not fail');
