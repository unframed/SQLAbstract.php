SQLAbstract.php
---
[![Build Status](https://travis-ci.org/unframed/SQLAbstract.php.svg)](https://travis-ci.org/unframed/SQLAbstract.php)

Practical SQL abstractions with concrete conveniences for query building and execution.

Synopsis
---
...

### Create

~~~php
<?php

$sqlAbstract = new SQLAbstractPDO($pdo, 'prefix_');
// create a new table or add missing columns, replace a view.
$sqlAbstract->create(array(
    'task' => array(
        'task_id' => 'INTEGER AUTOINCREMENT PRIMARY KEY',
        'task_name' => 'VARCHAR(255) NOT NULL',
        'task_description' => 'VARCHAR(512)',
        'task_scheduled_for' => 'INTEGER UNSIGNED NOT NULL',
        'task_completed_at' => 'INTEGER UNSIGNED',
        'task_created_at' => 'INTEGER UNSIGNED NOT NULL',
        'task_modified_at' => 'INTEGER UNSIGNED NOT NULL',
        'task_deleted_at' => 'INTEGER UNSIGNED',
        'task_json' => 'MEDIUMTEXT'
        ),
    'task_view' => (
        "SELECT *, "
        ." (task_scheduled_for > NOW()) as task_due,"
        ." (task_completed_at IS NULL OR task_completed_at < NOW()) as task_completed"
        ." (task_deleted_at NOT NULL) as task_deleted"
        ." FROM ".$sqlAbstract->prefix('task')
        )
    ));

?>
~~~

...

### Execute

~~~php
<?php



?>
~~~

...

### Insert

~~~php
<?php

$sqlAbstract = new SQLAbstractPDO($pdo, 'prefix_');
// insert a new task
$now = time();
$task = array(
    'task_name' => 'new task',
    'task_created_at' => $now,
    'task_scheduled_for' => $now + 3600,
    'task_modified_at' => $now
    );
$tast['task_id'] = $sqlAbstract->insert('task', $task);

?>
~~~

...

### Replace

~~~php
<?php

$sqlAbstract = new SQLAbstractPDO($pdo, 'prefix_');
// get a task by id
$task = $sqlAbstract->getRowById('task', 1);
// update its description
$task['task_description'] = '...';
$task['task_modified_at'] = time();
$sqlAbstract->replace('task', $task);

?>
~~~

...

### Update

~~~php
<?php

$sqlAbstract = new SQLAbstractPDO($pdo, 'prefix_');
// get an identified task's description and modified time
$sqlAbstract->update('task', 1, array(
    'task_description' => '...',
    'task_modified_at' => time()
    ));

?>
~~~

...

### Select

~~~php
<?php

$sqlAbstract = new SQLAbstractPDO($pdo, 'prefix_');
// get a task by id
$dueTasks = $sqlAbstract->select('task_view', array(
    'columns' => array('task_id', 'task_name', 'task_scheduled_at'),
    'filter' => array('task_due' => TRUE)
    ));
echo "[".implode(",",array_map(json_encode, $dueTasks)."]";

?>
~~~

