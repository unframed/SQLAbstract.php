<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

$sql = $sqlAbstract->createViewStatement(
	'view',
	"SELECT * FROM ".$sqlAbstract->prefixedIdentifier('table')
	);

$t->is($sql, (
	"CREATE OR REPLACE VIEW `wp_view` AS SELECT * FROM `wp_table`"
), $sql);

$sql = $sqlAbstract->createViewStatement(
	'view',
	"SELECT * FROM ".$sqlAbstract->prefixedIdentifier('table'),
	"CREATE VIEW IF NOT EXISTS"
	);

$t->is($sql, (
	"CREATE VIEW IF NOT EXISTS `wp_view` AS SELECT * FROM `wp_table`"
), $sql);
