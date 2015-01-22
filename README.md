SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

Practical SQL abstractions with concrete conveniences for query building and execution.

Requirements
---
- provide practical conveniences to query tables and views
- with prefixed table names, guarded identifiers and custom placeholders
- support PHP 5.3, PDO and WPDB.

Synopsis
---
SQLAbstract is meant to safely query a single existing SQL database, eventually with prefixed table names. It provides methods to: execute arbitrary statements, select and count rows in views and tables; as well methods to insert, replace, update and delete rows in tables.

### Create

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

?>
~~~

### Execute

Though nothing prevents you to execute arbitrary SQL statements.

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

You may use `SQLAbstract::execute` to insert, replace, update, select, filter, search and count rows, but conveniences are provided. 

### Insert

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

...

### Replace

~~~php
<?php

$task = $sql->getRowById('task', 'task_id', 1);

?>
~~~

...

~~~php
<?php

$task['task_description'] = '...';
$task['task_modified_at'] = time();
$sql->replace('task', $task);

?>
~~~

...

~~~php
<?php

foreach($sql->getRowsByIds('task', 'task_id', array(1,2,3)) as $task) {
    $task['task_description'] = '...';
    $task['task_modified_at'] = time();
    $sql->replace('task', $task);
}

?>
~~~

...

### Update

~~~php
<?php

$sql->update(
    'task', 
    array(
        'task_description' => '...',
        'task_modified_at' => time()
    ), 
    array(
        'filter' => array('task'=>1)
    )
);

?>
~~~

...

### Select

...

~~~php
<?php

$dueTasks = $sql->select('task_view', array(
    'columns' => array('task_id', 'task_name', 'task_scheduled_at'),
    'filter' => array('task_due' => TRUE)
    ));
echo "[".implode(",",array_map(json_encode, $dueTasks)."]";

?>
~~~

...

~~~json
{
    "columns": [],
    "where": "",
    "params": [],
    "order": [],
    "limit": 30,
    "offset": 0
}
~~~

...

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

...

Applications
---
SQLAbstract can be applied in application plugins that require consistent database access in and out of the scope of execution of the extended applications.

Unlike many database abstraction layers, SQLAbstract limits the query building and execution conveniences to a practical minimum, leaving room for a maximum of concrete applications.

SQLAbstract does not serve the "purely" theoretical case of an undefined SQL database for a fully object oriented implementation. Also, it won't solve the entreprise case of multiple databases with more object oriented doctrine.

This library just provides as little abstraction is required to solve the  case of legacy application plugins and extensions for WordPress.

You may find it usefull to extend your legacy database application.