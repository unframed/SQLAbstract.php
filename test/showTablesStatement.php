<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

$sql = $sqlAbstract->showTablesStatement();

$t->is($sql, "SHOW TABLES LIKE 'wp_%'", $sql);

$sql = $sqlAbstract->showTablesStatement('domain_');

$t->is($sql, "SHOW TABLES LIKE 'wp_domain_%'", $sql);