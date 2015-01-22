SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

A practical SQL abstraction class with concrete conveniences for query building and execution.

Usefull to plugin and extend legacy PHP database applications.

Requirements
---
- provide practical conveniences to query tables and views
- with prefixed table names, guarded identifiers and custom placeholders
- support PHP 5.3, PDO and WPDB.

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

REPLACE VIEW ".$sql->prefixedIdentifier('task_view')." AS 
    SELECT 
        *,
        (task_scheduled_for > NOW()) 
        as task_due,
        (task_completed_at IS NULL OR task_completed_at < NOW()) 
        as task_completed
        (task_deleted_at NOT NULL) 
        as task_deleted
    FROM ".$sql->prefixedIdentifier('task').";

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

You may use `execute` to insert, replace, update, select and count rows, but safe conveniences are provided.

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
$task['task_id'] = $sql->insert('task', $task);

?>
~~~

The use of `insert` is allways safe.

### Select and Replace

Let's now use safe options to select all tasks named 'new task', edit and then replace each task :

~~~php
<?php

foreach($sql->select('task', array(
    'filter' => array(
        'task_name' => 'new task'
        )
    )) as $task) {
    $task['task_modified_at'] = time();
    $sql->replace('task', $task);
}

?>
~~~

Not very elegant in this case, but demonstrative of a common pattern.

### Update

As safe and more efficient way to update filtered rows is way simpler.

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

### Delete

Deleting rows at once follows the same pattern, using the same options as `select`, `update` and `count`.

~~~php
<?php

$sql->delete('task', array(
    'filter' => array(
        'task_name' => 'new name'
    )
));

?>
~~~

Note the absence of litteral SQL, this code is free of SQL injection.

### Safe Options

~~~json
{
    "columns": [],
    "filter": {},
    "like": {},
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

### Unsafe Options

~~~json
{
    "where": "",
    "params": []
}
~~~

