SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

From CRUD to paginated search and filter without SQL injection.

Requirements
---
- provide conveniences to query SQL;
- safely, ie: with limits and without injections;
- covering applications from CRUD to paginated search and filter;
- with prefixed table and view names, guarded identifiers and custom placeholders;
- support PHP 5.3, MySQL, SQLite and Postgresql via PDO and WPDB.

Credits
---
To [badshark](https://github.com/badshark), [JoN1oP](https://github.com/JoN1oP) and [mrcasual](https://github.com/mrcasual) for requirements, code reviews, tests and reports.

Synopsis
---

* [Construct](#construct)
    - [SQLAbstractPDO](#sqlabstractpdo)
    - [SQLAbstractWPDB](#sqlabstractwpdb)
    - [SQLAbstract](#sqlabstract)
* [Execute](#execute)
* [Fetch](#fetch)
* [Insert](#insert)
* [Count And Column](#count-and-column)
* [Query Options](#query-options)
* [Select And Replace](#select-and-replace)
* [Update](#update)
* [Delete](#delete)
* [Safe Options](#safe-options)
* [Unsafe Options](#unsafe-options)
* [Replace Or Create View](#replace-or-create-view)
* [Create Table If Not Exists](#create-table-if-not-exists)
* [Show Tables And Columns](#show-tables-and-columns)
* [Alter Table](#alter-table)
* [Driver](#driver)

SQLAbstract is meant to safely query a single existing SQL database, eventually with prefixed table names.

So, let's assume a legacy table with a prefixed name :

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

Now, let's construct a concrete `SQLAbstract` class to access that table.

### Construct

The concrete classes available for now are: `SQLAbstractPDO` and `SQLAbstractWPDB`.

#### SQLAbstractPDO

The `SQLAbstractPDO` class is intended to be used outside of a (legacy) framework. It provides a general purpose PDO connection factory as well as conveniences to open PDO connections to SQLite and MySQL.

Use those factories to provide the first argument required by the constructor of `SQLAbstractPDO`. As second argument a table and view prefix can be given, it is optional and defaults to the empty string.

~~~php
<?php

$pdo = SQLAbstractPDO::openSQLite('test.db');
$sql = new SQLAbstractPDO($pdo, 'prefix_');
echo "Connected via the PDO driver named '".$sql->driver()."'.";

?>
~~~

Remember that it is not the purpose of `SQLAbstract` implementations to abstract the particulars of the SQL databases used by an application. The purpose of `SQLAbstract` is to safely support one common subset of SQL with wathever database API available be it PHP's own `PDO` or WordPress' `WPDB`.

#### SQLAbstractWPDB

For instance, we could also have used the `SQLAbstractWPDB` class instead of `SQLAbstractPDO` :

~~~php
<?php

$sql = new SQLAbstractWPDB();
echo "Connected via the WPDB to '".$sql->driver()."'.";

?>
~~~

Since WordPress provides its own global `$wpdb` instance, with an open connection to a database and a prefix, there is no need to supply anything to the constructor of `SQLAbstractWPDB`.

#### SQLAbstract

Extending your own `SQLAbstract` class for another framework than WordPress is relatively simple and you should look at the two concrete classes provided to see what must be implemented and how.

### Execute

Nothing in `SQLAbstract` prevents you to execute arbitrary SQL statements.

For instance, to create a view on the example legacy `tasks` table defined above :

~~~php
<?php

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

It is not the purpose of `SQLAbstract` to get in its applications' ways.

### Fetch

So application can also execute arbitrary parametrized statements and fetch row(s) and column(s).

To fetch all overdue tasks rows and columns from the view created above :

~~~php
<?php

$overdueTasks = $sq->fetchAll(
    "SELECT * FROM ".$sql->prefixedIdentifier('task_view')
    ." WHERE task_overdue IS TRUE"
    );

?>
~~~

You may use `execute` to insert, replace and update or use one of the four `fetch*` methods to select and count rows, but conveniences are provided to do many queries. And those general purpose conveniences can guard the most common queries against SQL injection by the application's user.

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

Note that selecting a column or rows with SQLAbstract *always* implies a `LIMIT` clause (with an `OFFSET` to zero by default).

Because safety does not stop at SQL injection, applications *must* avoid to fetch unlimited amount of data from the database.

### Query Options

Before we move on to `select` the inserted row, let's pause and consider the set of options used by SQAbstract methods to build an SQL statement safely.

Here are the safe defaults for the combined safe options used by `count`, `column`, `select`, `update` and `delete` :

~~~php
<?php

$options = array(
    'columns': array(),
    'filter': array(),
    'like': array(),
    'order': array(),
    'limit': 30,
    'offset': 0
)

?>
~~~

The options `offset`, `limit`, `order` and `columns` are simply ignored by the methods `delete`, `update` and `count`.

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
    LIMIT 30 OFFSET 0
;
~~~

Given all SQL views on tables these options could implement all selections.

There is a flag argument to assert safe options when calling the `count`, `column`, `select`, `update` and `delete` methods. Use it in functions handling user input in options directly to one of those `SQLAbstract` methods.

For instance :

~~~php
<?php

function userSelectTasks($sql, $options) {
    return $sql->select("task_view", $options, TRUE);
}

?>
~~~

Note that if an unsafe option is set, the call to `select` will throw an exception instead of letting the user input execute injected SQL. And since a `limit` default is always set, no unlimited amount of data will be queried.

This is what "safe" means here.

### Unsafe Options

The `where` and `params` options allow to specify an SQL expression and a list of execution parameters. And they are not safe. Applications are expected to use the `identifier` and `placeholder` methods to build the expression.

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

Use with care.

### Replace Or Create View

Whenever an unsafe option is required, more column(s) in more view(s) declared in the data model is probably a better solution than an SQL expression with positional parameters in a PHP function somewhere.

For instance, to filter tasks by various states, use `createViewStatement` :

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

### Driver

The `driver` method can eventually be used by applications of `SQLAbstractPDO` to support the different SQL dialects implemented by different databases (for instance, string concatenation in SQL is provided by the `||` operator for most SQL database but it is available as the `CONCAT()` function in MySQL).

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

