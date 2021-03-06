SQLAbstract
===
Tutorial
---

* [Introduction](#introduction)
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
* [Index](#index)
* [Safe Options](#safe-options)
* [Unsafe Options](#unsafe-options)
* [Where Less Is More](#where-less-is-more)
* [Replace Or Create View](#replace-or-create-view)
* [Create Table If Not Exists](#create-table-if-not-exists)
* [Show Tables And Columns](#show-tables-and-columns)
* [Add Columns](#add-columns)
* [Transaction](#transaction)
* [Driver](#driver)

### Introduction

`SQLAbstract` was made to support the requirements for MailPoet: consistently execute injection-free SQL, in and out of WordPress.

`SQLAbstract` is meant to safely query an existing SQL database, eventually with prefixed table names. And do all that in and out of a framework, including WordPress.

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

There are two concrete classes of `SQLAbstract`: `SQLAbstractPDO` and `SQLAbstractWPDB`.

#### SQLAbstractPDO

The `SQLAbstractPDO` class is intended to be used outside of a legacy framework or in a PDO application. It provides conveniences to open PDO connections to SQLite and MySQL, with the error mode set to exceptions and UTF8 encoding.

For instance, to connect to an SQLite 'test.db' database:

~~~php
$pdo = SQLAbstractPDO::openSQLite('test.db');
~~~

Then construct a new `SQLAbstract` with that PDO connection:

~~~php
$sql = new SQLAbstractPDO($pdo, 'prefix_');
echo "Connected via the PDO driver named '".$sql->driver()."'.";
~~~

Note that a table and view prefix can be given as second argument.

It is optional and defaults to the empty string.

#### SQLAbstractWPDB

In WordPress we can use the `SQLAbstractWPDB` class instead:

~~~php
$sql = new SQLAbstractWPDB();
echo "Connected via the WPDB to '".$sql->driver()."'.";
~~~

Note that since WordPress provides its own global `$wpdb` instance - with an open connection to a database and a prefix - there is no need to supply anything to the constructor of `SQLAbstractWPDB`.

#### SQLAbstract

The `SQLAbstract` class was designed to support different SQL dialects and different PHP database APIs.

It has been tested with SQLite, MySQL, PDO and WordPress.

Extending your own concrete class for another database than MySQL or another framework than WordPress is relatively simple.

Have look at the sources of `SQLAbstractPDO` and `SQLAbstractWPDB` to see what abstract methods of `SQLAbstract` must be implemented and how.

### Execute

Nothing in `SQLAbstract` prevents you to execute arbitrary SQL statements.

For instance, to create a view on the example legacy `tasks` table defined above :

~~~php
$sql->execute("

CREATE OR REPLACE VIEW ".$sql->prefixed('task_view')." AS
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM ".$sql->prefixed('task')."

");
~~~

Allways use the `prefixed` method to quote, validate and prefix table or view names. Eventually use `identifier` to quote and validate column names. For statements with parameters, use `placeholder`.

For instance:

~~~php
$sql->execute((
    "SELECT * FROM ".$sql->prefixed('task_view')
    ." WHERE ".$sql->identifier('task_due')." = ".$sql->placeholder(TRUE)
), array(TRUE));
~~~

Note that only positional parameters are supported by `execute`.

### Fetch

It is not the purpose of `SQLAbstract` to get in its applications' ways. So application can also execute arbitrary parametrized statements and fetch row(s) and column(s).

To fetch all overdue tasks rows and columns from the view created above :

~~~php
$overdueTasks = $sql->fetchAll(
    "SELECT * FROM ".$sql->prefixed('task_view')
    ." WHERE task_overdue IS TRUE"
    );
~~~

You may use `execute` to insert, replace and update or use one of the four `fetch*` methods to select and count rows.

But conveniences are provided to do many queries. And they can guard the most common queries against SQL injection by the application's user.

### Insert

For instance, let's insert a `$task` array in the table `task` and update this task's identifier :

~~~php
$now = time();
$task = array(
    'task_name' => 'new task',
    'task_created_at' => $now,
    'task_scheduled_for' => $now + 3600,
    'task_modified_at' => $now
    );
$task['task_id'] = intval($sql->insert('task', $task));
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
$allTasksCount = $sql->count('task');
$allTasksIds = $sql->column('task', array(
    'columns' => array('task_id'),
    'limit' => $allTasksCount
));
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

Here are the options used by `count`, `column`, `select`, `update` and `delete`:

- `in` : column names and rows
- `where` : an SQL expression
- `params` : a list of parameter values
- `columns` : a list of column names to select
- `filter` : a map of column names and values to filter on
- `like` : a map of column names and strings to match
- `order_by` : a list of clauses
- `limit` : the number of relations selected
- `offset` : of the selection

The options `offset`, `limit`, `order` and `columns` are simply ignored by the methods `delete`, `update` and `count`.

### Select and Replace

Select the first task named 'new task', edit and replace :

~~~php
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
$sql->update('task', array(
    'task_modified_at' => time()
), array(
    'filter' => array(
        'task_name' => 'new name'
    )
));
~~~

Also, it executes a single SQL statement.

~~~sql
UPDATE `prefix_task` SET `task_modified_at` = ? WHERE `task_name` = ?
~~~

Beware, updates have no limits.

### Delete

Deleting rows at once follows the same pattern, using the same options as `update` and `count`.

~~~php
$sql->delete('task', array(
    'filter' => array(
        'task_name' => 'new name'
    )
));
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
function userSelectTasks($sql, $options) {
    return $sql->select("task_view", $options, TRUE);
}
~~~

Note that if an unsafe option is set, the call to `select` will throw an exception instead of letting the user input execute injected SQL. And since a `limit` default is always set, no unlimited amount of data will be queried.

This is what "safe" means here.

### Unsafe Options

The `where` and `params` options allow to specify an SQL expression and a list of parameters values. Applications are expected to use the `identifier` and `placeholder` methods to build the expression safely.

~~~php
$now = time();
$sql->select("task", array(
    "where" => (
        $sql->identifier('task_scheduled_for')
        ." > "
        .$sql->placeholder($now)
    ),
    "params" => array($now)
), FALSE);
~~~

Note that a `FALSE` flag has been added to the method call. It explicitely tells the `select` method not to assert safe options. Also, it will remind any code reviewer that the `where` option should be carefully inspected.

And remember that the SQL generated contains the literal `where` option.

~~~sql
SELECT * FROM `prefix_task` WHERE `task_scheduled_for` > ?
~~~

So, no user input should be passed as `where` option.

Ever.

### Where Less Is More

As a rule of thumb one or more SQL views should be created when anything more complex than a `WHERE` expression is required to query the database from PHP.

Leverage SQL views to avoid the time-sink of maintaining complicated SQL statements entangled in PHP code and dispersed in your application's sources.

For instance, instead of:

~~~php
$now = time();
$sql->select("task", array(
    "where" => (
        $sql->identifier('task_scheduled_for')
        ." > "
        .$sql->placeholder($now)
    ),
    "params" => array($now)
), FALSE);
~~~

You could have leveraged SQL to create a view:

~~~php
$sql->execute($sql->createViewStatement('task_todo', (
    "SELECT * FROM ".$sql->prefixed('task')
    ." WHERE ".$sql->identifier('task_scheduled_for')." > NOW()"
));
~~~

Then use it safely:

~~~php
$sql->select('task_todo', array());
~~~

Move as much relational algebra as possible out of your queries and into SQL views. This path yields a better database for simpler and faster applications. Because the more complex SQL statements have not to be generated in PHP then compiled and planned by the database server for each request.

### Replace Or Create View

Whenever an unsafe option is required, more column(s) in more view(s) declared in the data model is probably a better solution than an SQL expression with positional parameters in a PHP function somewhere.

For instance, to filter tasks by various states, use `createViewStatement` :

~~~php
echo $sql->createViewStatement('task_view', ("
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM ".$sql->prefixed('task')."
"));
~~~

The echo is equivalent to :

~~~sql
CREATE OR REPLACE VIEW `prefix_task_view` AS
    SELECT *,
        (task_scheduled_for < NOW ()) AS task_due
        (task_completed_at IS NULL) AS task_todo,
        (task_due AND task_todo) AS task_overdue,
        (task_deleted_at IS NOT NULL) AS task_deleted
    FROM `prefix_task`
~~~

Just idiomatic SQL, the safe way to replace a view.

### Create Table If Not Exists

A statement is also provided for table creation :

~~~php
echo $sql->createStatement('task', array(
    'task_id' => "INTEGER AUTOINCREMENT",
    'task_name' => "VARCHAR(255) NOT NULL",
    'task_scheduled_for' => "INTEGER UNSIGNED NOT NULL",
    'task_completed_at' => "INTEGER UNSIGNED",
    'task_created_at' => "INTEGER UNSIGNED NOT NULL",
    'task_modified_at' => "INTEGER UNSIGNED NOT NULL",
    'task_deleted_at' => "INTEGER UNSIGNED",
    'task_description' => "MEDIUMTEXT"
), array(
    'task_id'
));
~~~

Again, idiomatic SQL :

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

Note how strictly no assertion are made on the column type definitions.

Legacy rules and defining a collation for MySQL should be possible.

### Show Tables and Columns

The methods `showTables($like)` and `showColumns($table)` execute the two schema queries required to replace views, add new tables or add new columns to a database.

To collect a simple `$schema` of tables, views and columns, do :

~~~php
$schema = array();
foreach($sql->showTables() as $prefixedName) {
    $schema[$prefixedName] = $sql->showColumns($prefixedName);
}
~~~

For MySQL the following SQL statements will be executed :

~~~sql
SHOW TABLES LIKE 'prefix_%'
SHOW COLUMNS FROM `prefix_task`
~~~

For other databases the equivalent statements will be executed, returning a column of all matched tables and views, as `SHOW TABLES` does, and relations of `(Field, Type, Null, Default)`, a common subset of `SHOW COLUMNS`.

### Add Columns

The alter statement provided add columns to a table.

~~~php
echo $sql->alterTableStatement('tasks', array(
    'task_json' => 'MEDIUMTEXT'
));
~~~

Nothing more.

~~~sql
ALTER TABLE `prefix_tasks` ADD COLUMN `task_json` MEDIUMTEXT
~~~

And that's it for the last SQL verb abstracted.

### Transaction

Rolling back SQL statements on error is all what `transaction` try to do.

A transaction is opened with a BEGIN statement. Then the the transaction's closure is called. If an exception is throwed a ROLLBACK will be issued before a new `Exception` is rethrowed. Otherwise, a COMMIT is issued and the result of the closure is returned.

For instance, lets use a transaction to insert a task, count tasks and delete the first one if there are more than a thousand tasks:

~~~php
function insertTaskLimited ($sql, $task) {
    $sql->insert('tasks', $task);
    if ($sql->count('tasks') > 1000) {
        $sql->delete('tasks', array(
            'where' => 'task_id = MIN(task_id)'
        ));
    }
}
$sql->transaction('insertTaskLimited', array($sql, $task));
~~~

Note that some databases turn autocommit on for schema update query (any CREATE, ALTER, DROP and such statements). And so, SQL transactions are in effect practical only for sequences of INSERT, UPDATE and DELETE statements.

### Driver

Under this SQL abstraction is a database.

~~~php
$pdo = new SQLAbstractPDO::openSQLite('test.db');
$sql = new SQLAbstractPDO($pdo, 'prefix_');
echo "Connected via the PDO to '".$sql->driver()."'.";
~~~

The `driver` method can eventually be used by applications of `SQLAbstract` to support the different SQL dialects implemented by different databases.

For instance, string concatenation in SQL is provided by the `||` operator for most SQL database but it is available as the `CONCAT()` function in MySQL.