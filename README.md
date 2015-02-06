SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

From CRUD to paginated search and filter without SQL injection.

Requirements
---
- provide conveniences to query SQL without injection
- for CREATE, ALTER, SELECT, INSERT, REPLACE, UPDATE and DELETE statements.
- covering applications from CRUD to paginated search and filter
- with prefixed table names, guarded identifiers and custom placeholders
- support PHP 5.3, MySQL, PDO and WPDB.

Credits
---
To [badshark](https://github.com/badshark), [JoN1oP](https://github.com/JoN1oP) and [mrcasual](https://github.com/mrcasual) for code reviews, tests and reports.

Synopsis
---
SQLAbstract is meant to safely query a single existing SQL database, eventually with prefixed table names.

So, let's assume a legacy 'task' table.

~~~sql
CREATE TABLE IF NOT EXISTS `prefix_task` (
    `task_id` INTEGER AUTOINCREMENT PRIMARY KEY,
    `task_name` VARCHAR(255) NOT NULL,
    `task_scheduled_for` INTEGER UNSIGNED NOT NULL,
    `task_completed_at` INTEGER UNSIGNED,
    `task_created_at` INTEGER UNSIGNED NOT NULL,
    `task_modified_at` INTEGER UNSIGNED NOT NULL,
    `task_deleted_at` INTEGER UNSIGNED,
    `task_description` MEDIUMTEXT
    );
~~~

Because you will find no `create` methods in SQLAbstract.

### Execute

Although nothing prevents you to execute arbitrary SQL statements.

For instance, to create a view:

~~~php
<?php

$sql = new SQLAbstractPDO($pdo, 'prefix_');
$sq->execute("

CREATE OR REPLACE VIEW ".$sql->prefixedIdentifier('task_view')." AS 
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM ".$sql->prefixedIdentifier('task')."

");

?>
~~~

Or fetch all tasks rows at once:

~~~php
<?php

$sql = new SQLAbstractPDO($pdo, 'prefix_');
$sq->fetchAll(
    "SELECT * FROM ".$sql->prefixedIdentifier('task_view')
    );

?>
~~~

You may use `execute` to insert, replace and update or use one of the four `fetch*` methods to select and count rows, but conveniences are provided to do many queries. 

Those general purpose conveniences also guard the most common queries against SQL injection by the application's user.

### Insert

For instance, let's insert a `$task` array in the table `task` and update this task's identifier :

~~~php
<?php

$now = time();
$task = array(
    'task_name' => 'new task',
    'task_created_at' => $now,
    'task_scheduled_for' => $now + 3600,
    'task_modified_at' => $now
    );
$task['task_id'] = intval($sql->insert('task', $task));

?>
~~~

The following SQL statement will be executed, with safely bound parameters.

~~~sql
INSERT INTO `prefix_task` (
    `task_name`, 
    `task_created_at`, 
    `task_scheduled_for`, 
    `task_modified_at`, 
    ) VALUES (?, ?, ?, ?)
~~~

Use input may be safely passed to `insert`, all keys and values are guarded against SQL injections.

### Options

Before we move on to `select` the inserted row, let's pause and consider the set of options used by SQAbstract methods to build an SQL statement, eventually safely.

Here are the defaults for the safe options :

~~~php
<?php

$options = array(
    "columns": array(),
    "filter": array(),
    "like": array(),
    "order": array(),
    "limit": 30,
    "offset": 0
)

?>
~~~

We will see examples below.

### Count and Column

To count all tasks and fetch the whole `task_id` column, do :

~~~php
<?php

$allTasksCount = $sql->count('task');
$allTasksIds = $sql->column('task', array(
    'columns' => array('task_id'),
    'limit' => $allTasksCount
));

?>
~~~

Here are the two SQL `SELECT` statements executed.

~~~sql
SELECT COUNT(*) FROM `prefix_task`;
SELECT `task_id` FROM `prefix_task` LIMIT 1;
~~~

Note that selecting a column or rows with SQLAbstract *always* implies a `LIMIT` clause (with an `OFFSET` to zero by default). Safety does not stop at SQL injection, applications *must* avoid to fetch unlimited amount of data from the database.

### Select and Replace

Select the first task named 'new task', edit and replace :

~~~php
<?php

$tasks = $sql->select('task', array(
    'filter' => array(
        'task_name' => 'new task'
        ),
    'limit' => 1
));
foreach($tasks as $task) {
    $task['task_modified_at'] = time();
    $sql->replace('task', $task);
}

?>
~~~

The following SQL statements will be executed, with safely bound parameters.

~~~sql
SELECT * FROM `prefix_task` WHERE `task_name` = ? LIMIT 1;
REPLACE INTO `prefix_task` (
    `task_name`, 
    `task_created_at`, 
    `task_scheduled_for`, 
    `task_modified_at`, 
    ) VALUES (?, ?, ?, ?)
;
~~~

Not very elegant in this particular case, but demonstrative of a common pattern when there is more than one row to read and replace.

### Update

Updating rows selected by options with the same data is actually much simpler.

~~~php
<?php

$sql->update('task', array(
    'task_modified_at' => time()
), array(
    'filter' => array(
        'task_name' => 'new name'
    )
));

?>
~~~

Also, it executes a single SQL statement.

~~~sql
UPDATE `prefix_task` SET `task_modified_at` = ? WHERE `task_name` = ? 
~~~

Beware, updates have no limits.

### Delete

Deleting rows at once follows the same pattern, using the same options as `update` and `count`.

~~~php
<?php

$sql->delete('task', array(
    'filter' => array(
        'task_name' => 'new name'
    )
));

?>
~~~

Again beware `delete` yields no `LIMIT` clause.

~~~sql
DELETE FROM `prefix_task` WHERE `task_name` = ? 
~~~

To use with care in any cases.

### Safe Options

The safe options to generate a WHERE clause are `filter` and `like`.

For instance, here is a bit more complex select statement. 

~~~php
<?php

$sql->select("task", array(
    "filter" => array(
        "task_id" => array(1,2,3),
        "task_deleted_at" => null
    ),
    "like" => array(
        "task_name" => "new%"
        "task_description" => "new%"
    )
));

?>
~~~

This implements the typical filter and search feature found in most database application and executes the following SQL :

~~~sql
SELECT * FROM `prefix_task` WHERE 
    `task_in` in (?, ?, ?) 
    AND task_delete_at = ?
    AND (
        `task_name` like ? 
        OR `task_description` like ?
        ) 
;
~~~

Given all SQL views on tables these options could implement all selections.

But we don't have *all* views to select from.

### Unsafe Options

The `where` and `params` options allow to specify an SQL expression and a list of execution parameters.

Note that applications are expected to use the `identifier` and `placeholder` methods to build the expression.

~~~php
<?php

$now = time();
$sql->select("task", array(
    "where" => (
        $sql->identifier('task_scheduled_for')
        ." > "
        .$sql->placeholder($now)
    ),
    "params" => array($now)
));

?>
~~~

Remember that the SQL generated contains the literal `where` option.

~~~sql
SELECT * FROM `prefix_task` WHERE `task_scheduled_for` > ?
~~~

So, no user input should be passed as `where` option.

### Replace Or Create View

...

~~~php
<?php

$sql->createViewStatement('task_view', ("
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM ".$sql->prefixedIdentifier('task')."
"));

?>
~~~

...

~~~sql
CREATE OR REPLACE VIEW `prefix_task_view` AS 
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM `prefix_task`
~~~

...

### Create Table If Not Exists

...

~~~php
<?php

$sql->createStatement('task', array(
    'task_id' => "INTEGER AUTOINCREMENT",
    'task_name' => "VARCHAR(255) NOT NULL",
    'task_scheduled_for' => "INTEGER UNSIGNED NOT NULL",
    'task_completed_at' => "INTEGER UNSIGNED",
    'task_created_at' => "INTEGER UNSIGNED NOT NULL",
    'task_modified_at' => "INTEGER UNSIGNED NOT NULL",
    'task_deleted_at' => "INTEGER UNSIGNED",
    'task_description' => "MEDIUMTEXT"
), array('task_id');

?>
~~~

...

~~~sql
CREATE TABLE IF NOT EXISTS `prefix_task` (
    `task_id` INTEGER AUTOINCREMENT,
    `task_name` VARCHAR(255) NOT NULL,
    `task_scheduled_for` INTEGER UNSIGNED NOT NULL,
    `task_completed_at` INTEGER UNSIGNED,
    `task_created_at` INTEGER UNSIGNED NOT NULL,
    `task_modified_at` INTEGER UNSIGNED NOT NULL,
    `task_deleted_at` INTEGER UNSIGNED,
    `task_description` MEDIUMTEXT,
    PRIMARY KEY (`task_id`)
    );
~~~

### Show Tables and Columns

The methods `showTables($like)` and `showColumns($table)` execute the two schema queries required to replace views, add new tables or add new columns to a database.

To collect a simple `$schema` of tables, views and columns, do :

~~~php
<?php

$schema = array();
foreach($sql->showTables() as $prefixedName) {
    $schema[$prefixedName] = $sql->showColumns($prefixedName);
}

?>
~~~

For MySQL the following SQL statements will be executed :

~~~sql
SHOW TABLES LIKE 'prefix_%'
SHOW COLUMNS FROM `prefix_task`
~~~

For other databases the equivalent statements will be executed, returning a column of all matched tables and views, as `SHOW TABLES` does, and relations of `(Field, Type, Null, Default)`, a common subset of `SHOW COLUMNS`.

### Alter Table

...

Use Case
---
The `SQLAbstract` abstract class provides support for the common feature requests in any SQL database application: Create, Read, Update, Delete, seach, filter and paginate results in variable orders and size.

Without SQL injection, if you care.

And eventually with database table prefixes, because legacy rules.

The implementations of `SQLAbstract` allow their applications to access the database consistently, safely, limitedly and using the same code, regardless of the context of execution, inside or outside of a legacy framework.

For instance, `SQLAbstractPDO` and `SQLAbstractWPDB` support plugins that can run the same code in and out of WordPress.

Also, `SQLAbstract` works great with [JSONModel.php](https://github.com/unframed/JSONModel.php).

### Where Less Is More

I avoided the time-sink of writing yet another buggy SQL compiler by making the assumtion that when anything more complex than an SQL `WHERE` expression is required to query the database, then one or more SQL views must be created.

Create as much SQL views as required and you will avoid the time-sink of maintaining complicated SQL statements entangled in PHP code and dispersed in your application's sources.

