<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(1);

$sql = $sqlAbstract->alterTableStatement('table', array(
	'table_key' => "INTEGER NOT NULL",
	'table_col' => "VARCHAR(255) DEFAULT ''"
));

$t->is($sql, (
	"ALTER TABLE `wp_table`\n"
	." ADD COLUMN `table_key` INTEGER NOT NULL,\n"
	." ADD COLUMN `table_col` VARCHAR(255) DEFAULT ''\n"
));