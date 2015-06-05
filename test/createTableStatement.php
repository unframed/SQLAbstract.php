<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(2);

$sql = $sqlAbstract->createTableStatement('table', array(
	'table_id' => "INTEGER AUTOINCREMENT NOT NULL",
	'table_key' => "INTEGER NOT NULL",
	'table_col' => "VARCHAR(255) DEFAULT ''"
), array(
	'table_id'
));

$t->is($sql, (
	"CREATE TABLE IF NOT EXISTS `wp_table` (\n"
	." `table_id` INTEGER AUTOINCREMENT NOT NULL,\n"
	." `table_key` INTEGER NOT NULL,\n"
	." `table_col` VARCHAR(255) DEFAULT '',\n"
	." PRIMARY KEY (`table_id`)\n"
	.")\n DEFAULT CHARSET=utf8"
), $sql);

$sql = $sqlAbstract->createTableStatement('relation', array(
	'relation_a' => "INTEGER NOT NULL",
	'relation_b' => "VARCHAR(255) NOT NULL DEFAULT ''"
), array(
	'relation_a', 'relation_b'
));

$t->is($sql, (
	"CREATE TABLE IF NOT EXISTS `wp_relation` (\n"
	." `relation_a` INTEGER NOT NULL,\n"
	." `relation_b` VARCHAR(255) NOT NULL DEFAULT '',\n"
	." PRIMARY KEY (`relation_a`, `relation_b`)\n"
	.")\n DEFAULT CHARSET=utf8"
), $sql);
