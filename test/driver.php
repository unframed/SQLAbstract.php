<?php

require_once('test/SQLAbstractTest.php');
require_once('src/SQLAbstractPDO.php');

$t = new TestMore();

$t->plan(2);

$pdo = SQLAbstractPDO::openMySQL('wp', 'test', 'dummy');
$sql = new SQLAbstractPDO($pdo);

$t->is($sql->driver(), 'mysql', "\$sql->driver() === 'mysql'");

$pdo = SQLAbstractPDO::openSQLite(':memory');
$sql = new SQLAbstractPDO($pdo);

$t->is($sql->driver(), 'sqlite', "\$sql->driver() === 'sqlite'");

