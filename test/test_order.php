<?php

require_once('test/SQLAbstractTest.php');

$sqlAbstract = new SQLAbstractTest('wp_');

$t = new TestMore();

$t->plan(4);
$t->is($sqlAbstract->order('column ASC'), '`column` ASC', 'column ASC');
$t->is($sqlAbstract->order('column asc'), '`column` ASC', 'column asc');
$t->is($sqlAbstract->order('column'), '`column` ASC', 'column');
$t->is($sqlAbstract->order('column Desc'), '`column` DESC', 'column Desc');

