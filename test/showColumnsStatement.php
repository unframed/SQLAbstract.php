<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(1);

$sql = $sqlAbstract->showColumnsStatement($sqlAbstract->prefix('table'));

$t->is($sql, "SHOW COLUMNS FROM `wp_table`", $sql);