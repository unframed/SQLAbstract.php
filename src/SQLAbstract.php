<?php
/**
 * @version 0.8
 * @todo more test and documentation
 * @copyright Laurent Szyster 2014 - 2015
 * @author laurentszyster@gmail.com
 */

/**
 * An SQL data abstraction layer class.
 */
abstract class SQLAbstract {

    // The practical abstractions.

    /**
     * Return the database (PDO) driver's name: 'mysql', etc.
     *
     * @return string
     */
    abstract function driver ();
    /**
     * Return the SQL database's name: 'test_db', etc.
     *
     * @return string
     */
    abstract function databaseName ();
    /**
     * Invoke a callable with arguments inside an SQL transaction, rollback on
     * exception or commit and return the call's result.
     *
     * @param callable $callable
     * @param array $arguments
     * @return any
     */
    abstract function transaction ($callable, $arguments);
    /**
     * Prepare, bind and execute one SQL statement.
     */
    abstract function execute ($sql, $parameters=NULL);
    /**
     * Return the last inserted primary key.
     */
    abstract function lastInsertId ();
    /**
     * Prepare, bind and execute a SELECT statement, then return the first result array.
     */
    abstract function fetchOne ($sql, $parameters=NULL);
    /**
     * Prepare, bind and execute an SELECT statement, then return all results.
     */
    abstract function fetchAll ($sql, $parameters=NULL);
    /**
     * Prepare, bind and execute an SELECT statement, then return the first result value.
     */
    abstract function fetchOneColumn ($sql, $parameters=NULL);
    /**
     * Prepare, bind and execute an SELECT statement, then return all result values.
     */
    abstract function fetchAllColumn ($sql, $parameters=NULL);
    /**
     * Prefix $name.
     *
     * @param string $name
     * @return string
     */
    abstract function prefix($name='');
    /**
     * Validate $name as an SQL identifier and return the quoted identifier.
     *
     * @param string $name
     * @return string
     */
    abstract function identifier($name);
    /**
     * Return the placeholder for a value in an SQL statement.
     *
     * @param any $value
     * @return string
     */
    abstract function placeholder($value);

    // ...

    function exception ($message, $previous=NULL) {
        return new Exception($message, 0, $previous);
    }

    // The concrete conveniences: query builders and executers.

    final function quotes () {
        switch($this->driver()) {
            case 'mysql': return array('`', '`'); // MySQL
            case 'sqlsrv': return array('[', ']'); // Microsoft SQL Server
        }
        return array('"', '"'); // Postgresql, SQLite, etc ...
    }

    /**
     * Validate the prefixed $name return the quoted identifier.
     *
     * @param string $name
     * @return string
     * @deprecated use `prefixed` instead
     */
    function prefixedIdentifier ($name) {
        return $this->identifier($this->prefix($name));
    }

    /**
     * Validate the prefixed $name return the quoted identifier.
     *
     * @param string $name
     * @return string
     */
    function prefixed ($name) {
        return $this->identifier($this->prefix($name));
    }

    /**
     * Return an SQL list of validated column $names or '*' if the given $names
     * argument is empty of a NULL.
     *
     * @param array $names
     * @return string
     */
    function columns ($names) {
        return (($names === NULL) || (count($names) === 0) ? "*" : implode(
            ",", array_map(array($this, 'identifier'), $names)
            ));
    }
    /**
     *
     */
    function createViewStatement ($name, $select, $verb="CREATE OR REPLACE VIEW") {
        return (
            $verb." ".$this->prefixed($name)." AS ".$select
            );
    }
    /**
     *
     */
    function createTableStatement ($name, array $columns, array $constraints) {
        $lines = array();
        foreach ($columns as $column => $declaration) {
            array_push($lines, $this->identifier($column)." ".$declaration);
        }
        $statement = (
            "CREATE TABLE IF NOT EXISTS "
            .$this->prefixed($name)
            ." (\n ".implode(",\n ", array_merge($lines, $constraints))."\n)\n"
        );
        switch ($this->driver()) {
            case 'mysql':
                return $statement.' DEFAULT CHARSET=utf8';
            default:
                return $statement;
        }
    }
    function alterTableStatement ($name, array $columns, array $constraints=NULL) {
        $lines = array();
        foreach ($columns as $column => $declaration) {
            $lines[] = (
                "ADD COLUMN ".$this->identifier($column)." ".$declaration
            );
        }
        if ($constraints !== NULL) {
            foreach ($constraints as $constraint => $declaration) {
                $lines[] = (
                    "ADD CONSTRAINT ".$this->prefixed($constraint)." ".$declaration
                );
            }
        }
        return (
            "ALTER TABLE "
            .$this->prefixed($name)
            ."\n "
            .implode(",\n ", $lines)
            ."\n"
        );
    }
    function createIndexStatement ($name, $columns, $slug=NULL) {
        $indexOn = $this->columns($columns);
        if ($slug === NULL) {
            $indexName = $name.'_'.substr(sha1($indexOn), 0, 6);
        } else {
            $indexName = $name.'_'.$slug;
        }
        return (
            "CREATE INDEX "
            .$this->prefixed($indexName)
            ." ON ".$this->prefixed($name)
            ."(".$indexOn.")"
        );
    }

    static function joins ($sql, $primary, $foreign) {
        $joins = array();
        foreach ($foreign as $joined => $keys) {
            $onKeys = array();
            $L = min(count($primary), count($keys));
            for ($i=0; $i<$L; $i++) {
                $onKeys[] = (
                    $sql->indentifier($primary[$i])
                    ." = ".$sql->identifier($keys[$i])
                );
            }
            $joins[] = (
                " JOIN ".$sql->prefixed($joined)
                ." ON ".implode(" AND ", $onKeys)
            );
        }
        return $joins;
    }

    /**
     * Return an SQL statement with a list of parameters to select
     * given $columns (or all) from a $view where $column equals $key.
     *
     * @param string $view the unprefixed name of the view to select FROM
     * @param string $column the name of the column in the WHERE clause
     * @param any $key the key to select
     * @param array $columns a list of column names to SELECT
     */
    function selectByColumn ($view, $column, $key, $columns) {
        return array(
            "SELECT ".$this->columns($columns)
            ." FROM ".$this->prefixed($view)
            ." WHERE ".$this->identifier($column)
            ." = ".$this->placeholder($key),
            array($key)
            );
    }
    /**
     * Return a row selected in
     */
    function getRowById ($table, $column, $id, $columns=NULL) {
        list($sql, $params) = $this->selectByColumn(
            $table, $column, $id, $columns
            );
        return $this->fetchOne($sql, $params);
    }

    /**
     * Return an SQL statement with a list of parameters to select
     * given $columns (or all) from a $view where $column is in $keys.
     *
     * @param string $view the unprefixed name of the view to select FROM
     * @param string $column the name of the column in the WHERE clause
     * @param any $keys the keys to select
     * @param array $columns a list of column names to SELECT
     */
    function selectInColumn ($view, $column, $keys, $columns=NULL) {
        $count = count($keys);
        if ($count > 0) {
            $placeholders = implode(',', array_fill(
                0, $count, $this->placeholder($keys[0])
                ));
        } else {
            $placeholders = '';
        }
        return array((
            "SELECT ".$this->columns($columns)
            ." FROM ".$this->prefixed($view)
            ." WHERE ".$this->identifier($column)
            ." IN (".$placeholders.")"
            ), $keys);
    }

    function getRowsByIds ($table, $column, $ids, $columns=NULL) {
        list($sql, $params) = $this->selectInColumn($table, $column, $ids, $columns);
        return $this->fetchAll($sql, $params);
    }

    function selectByKeys ($view, $keys, $columns=NULL) {
        $expressions = array();
        $params = array();
        foreach ($keys as $key => $value) {
            array_push(
                $expressions,
                $this->identifier($key)." = ".$this->placeholder($key)
                );
            array_push($params, $value);
        }
        return array((
            "SELECT ".$this->columns($columns)
            ." FROM ".$this->prefixed($view)
            ." WHERE ".implode(" AND ", $expressions)
            ), $params);

    }

    /**
     * Validate a single element of an ORDER BY clause.
     *
     * @param string $order
     * @return string
     */
    function order($order) {
        if (preg_match('/^(\S+)(?:$|\s+(DESC|ASC)$)/i', $order, $matches) !== 1) {
            throw new Exception("Invalid SQL order by clause: ".$order."");
        }
        if (count($matches) === 3) {
            return $this->identifier($matches[1]).' '.strtoupper($matches[2]);
        } else {
            return $this->identifier($matches[1]).' ASC';
        }
    }
    /**
     * Return an ORDER BY clause for the given $orders.
     *
     * @param array $orders
     * @return string
     */
    function orderBy($orders) {
        if ($orders === NULL || count($orders) === 0) {
            return "";
        }
        return " ORDER BY ".implode(", ", array_map(array($this, 'order'), $orders));
    }

    function inSet ($columns, $rows, $whereAnd=array(), $params=array()) {
        if (count($columns) === 0 || count($rows) === 0) {
            return array($whereAnd, $params);
        }
        $offset = count($params);
        $keys = array_combine(
            $columns, array_fill(0, count($columns), NULL)
        );
        // map to positional parameters
        foreach ($rows as $row) {
            $params = array_merge($params, array_values(array_intersect_key($row, $keys)));
        }
        // get the placeholders from the first row
        $placeholders = implode(', ', array_map(
            array($this, 'placeholder'),
            array_slice($params, $offset, count($columns))
        ));
        $whereAnd[] = (
            "(".implode(', ', $columns).") IN (("
                .implode('), (', array_fill(0, count($rows), $placeholders))
            ."))"
        );
        // return the statement and the updated parameters
        return array($whereAnd, $params);
    }
    /**
     * Return an SQL expression with positional placeholders and a list of parameters
     * for the given $filter and $like array.
     *
     * @param array $filter
     * @param array $like
     * @param array $whereAnd
     * @param array $params
     * @return array ($where, $params)
     */
    function filterLike ($filter, $like=NULL, $whereAnd=array(), $params=array()) {
        foreach ($filter as $column => $value) {
            if ($value === NULL) {
                array_push(
                    $whereAnd,
                    $this->identifier($column)." IS NULL"
                );
            } elseif (!JSONMessage::is_list($value)) {
                array_push(
                    $whereAnd,
                    $this->identifier($column)." = ".$this->placeholder($value)
                    );
                array_push($params, $value);
            } elseif (count($value) > 0) {
                array_push($whereAnd, (
                    $this->identifier($column)
                    ." IN (".implode(', ', array_map(
                        array($this, 'placeholder'), $value
                        )).")"
                    ));
                $params = array_merge($params, $value);
            }
        }
        if ($like !== NULL && count($like) > 0) {
            $whereOr = array();
            foreach ($like as $column => $value) {
                if (!JSONMessage::is_list($value)) {
                    array_push(
                        $whereOr,
                        $this->identifier($column)." LIKE ".$this->placeholder($value)
                    );
                    array_push($params, $value);
                } elseif (count($value) > 0) {
                    $columnLike = $this->identifier($column)." LIKE ";
                    array_push($whereOr, (
                        "(".$columnLike.implode(" AND ".$columnLike, array_map(
                            array($this, 'placeholder'), $value
                        )).")"
                    ));
                    $params = array_merge($params, $value);
                }
            }
            if (count($whereOr) > 0) {
                array_push($whereAnd, "(".implode(" OR ", $whereOr).")");
            }
        }
        return array(implode(" AND ", $whereAnd), $params);
    }
    /**
     *
     */
    function whereParams ($message) {
        if ($message->has('where')) {
            $whereAnd = array($message->getString('where'));
            $params = $message->getList('params', array());
        } else {
            $whereAnd = array();
            $params = array();
        }
        if ($message->has('in')) {
            list($columns, $rows) = $message->getList('in');
            list($whereAnd, $params) = $this->inSet($columns, $rows, $whereAnd, $params);
        }
        return $this->filterLike(
            $message->getMap('filter', array()),
            $message->getMap('like', array()),
            $whereAnd,
            $params
        );
    }

    static function assertSafe ($options) {
        if (
            array_key_exists('where', $options) ||
            array_key_exists('params', $options)
            ) {
            throw new Exception('unsafe options defined');
        }
    }

    function countStatement ($view, $options) {
        list($where, $params) = $this->whereParams(new JSONMessage($options));
        $sql = (
            "SELECT COUNT(*) FROM ".$this->prefixed($view)
            .($where === '' ? "" : " WHERE ".$where)
            );
        return array($sql, $params);
    }

    function count ($view, $options=array(), $safe=FALSE) {
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        list($sql, $params) = $this->countStatement($view, $options);
        return intval($this->fetchOneColumn($sql, $params));
    }

    function selectStatement ($view, $options) {
        $m = new JSONMessage($options);
        $columns = $m->getList('columns', array());
        list($where, $params) = $this->whereParams($m);
        $orders = $m->getList('order_by', $m->getList('orders', array()));
        $sql = (
            "SELECT ".$this->columns($columns)
            ." FROM ".$this->prefixed($view)
            .($where === '' ? "" : " WHERE ".$where)
            .$this->orderBy($orders)
            );
        $limit = $m->getInt('limit', 30);
        if ($limit > 0) {
            $offset = $m->getInt('offset', 0);
            $sql = $sql." LIMIT ".strval($limit)." OFFSET ".strval($offset);
        }
        return array($sql, $params);
    }

    function select ($view, $options, $safe=FALSE) {
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        list($sql, $params) = $this->selectStatement($view, $options);
        return $this->fetchAll($sql, $params);
    }

    static function indexColumns ($keys) {
        return (count($keys) === 2) ? array($keys[0], $keys[1]) : array($keys[0], NULL);
    }

    static function indexMap ($rows, $keyColumn, $fun, &$index) {
        $index = array();
        foreach ($rows as $row) {
            $key = strval($row[$keyColumn]);
            if (!array_key_exists($key, $index)) {
                $index[$key] = array();
            }
            call_user_func_array($fun, array($key, $row, $index));
        }
    }

    static function indexRows ($rows, $keyColumn, $valueColumn, &$index) {
        if ($valueColumn === NULL) { // index the whole row
            foreach ($rows as $row) {
                $key = strval($row[$keyColumn]);
                if (array_key_exists($key, $index)) {
                    $index[$key][] = $row;
                } else {
                    $index[$key] = array($row);
                }
            }
        } else {
            foreach ($rows as $row) { // index one column only
                $key = strval($row[$keyColumn]);
                if (array_key_exists($key, $index)) {
                    $index[$key][] = $row[$valueColumn];
                } else {
                    $index[$key] = array($row[$valueColumn]);
                }
            }
        }
    }

    function index ($view, $options, $safe=FALSE) {
        $rows = $this->select($view, $options, $safe);
        $index = array();
        if (count($rows) > 0) {
            list($keyColumn, $valueColumn) = self::indexColumns(array_keys($rows[0]));
            self::indexRows($rows, $keyColumn, $valueColumn, $index);
        }
        return $index;
    }

    function column ($view, $options, $safe=FALSE) {
        if (!(
            array_key_exists('columns', $options) &&
            count($options['columns'])===1
            )) {
            throw $this->exception(
                "Expected an array with a single column in the 'columns' option"
                );
        }
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        list($sql, $params) = $this->selectStatement($view, $options);
        return $this->fetchAllColumn($sql, $params);
    }

    function insertStatement ($table, $map, $verb='INSERT') {
        $keys = array_keys($map);
        $params = array_values($map);
        return array(
            $verb." INTO "
            .$this->prefixed($table)
            ." (".implode(", ", array_map(array($this, 'identifier'), $keys)).")"
            ." VALUES (".implode(", ",
                array_map(array($this, 'placeholder'), $params)
                ).")",
            $params
            );
    }

    function insert ($table, $map, $verb="INSERT") {
        list($sql, $params) = $this->insertStatement($table, $map, $verb);
        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    function insertAllStatement ($table, $rows, $columns, $verb) {
        $keys = array_keys($columns);
        $placeholders = array_map(array($this, 'placeholder'), array_values($columns));
        $params = array();
        foreach ($rows as $row) {
            $values = array_values(array_intersect_key(
                array_merge($columns, $row), $columns
            ));
            $params = array_merge($params, $values);
        }
        return array((
            $verb." INTO ".$this->prefixed($table)
            ." (".implode(", ", array_map(array($this, 'identifier'), $keys)).")"
            ." VALUE (".implode(
                "), (", array_fill(0, count($rows), implode(', ', $placeholders))
            ).")"
        ), $params);
    }

    function insertAll ($table, $rows, $columns=NULL, $verb='INSERT') {
        if (count($rows) === 0) {
            return 0;
        }
        list($sql, $params) = $this->insertAllStatement(
            $table, $rows, ($columns === NULL? $rows[0]: $columns), $verb
        );
        return $this->execute($sql, $params);
    }

    function replace ($table, $map) {
        list($sql, $params) = $this->insertStatement($table, $map, 'REPLACE');
        return $this->execute($sql, $params);
    }

    function replaceAll ($table, $rows, $columns=NULL) {
        return $this->insertAll($table, $rows, $columns, 'REPLACE');
    }

    function updateStatement($table, $map, $options) {
        $setExpressions = array();
        $setParams = array();
        foreach($map as $name => $value) {
            $placeholder = $this->placeholder($value);
            array_push(
                $setExpressions,
                $this->identifier($name)." = ".$placeholder
                );
            if ($placeholder !== 'NULL') {
                array_push($setParams, $value);
            }
        }
        list($whereExpression, $whereParams) = $this->whereParams(new JSONMessage($options));
        return array((
            "UPDATE "
            .$this->prefixed($table)
            ." SET "
            .implode(", ", $setExpressions)
            ." WHERE ".$whereExpression
            ), array_merge($setParams, $whereParams));
    }

    function update ($table, $map, $options, $safe=FALSE) {
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        list($sql, $params) = $this->updateStatement($table, $map, $options);
        return $this->execute($sql, $params);
    }

    function deleteStatement($table, $options) {
        $message = new JSONMessage($options);
        list($whereExpression, $whereParams) = $this->whereParams($message);
        return array((
            "DELETE FROM "
            .$this->prefixed($table)
            ." WHERE ".$whereExpression
            ), $whereParams);
    }

    function delete ($table, $options, $safe=FALSE) {
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        list($sql, $params) = $this->deleteStatement($table, $options);
        return $this->execute($sql, $params);
    }

    function temporary ($name, $view, $options, $safe=FALSE) {
        if ($safe === TRUE) {
            self::assertSafe($options);
        }
        $options['limit'] = 0;
        unset($options['offset']);
        list($select, $params) = $this->selectStatement($view, $options);
        return $this->execute((
            "CREATE TEMPORARY TABLE ".$this->prefixed($name)." ".$select
        ), $params);
    }

    /**
     * Return a SHOW TABLES LIKE equivalent SQL statement.
     *
     * @param string $like
     * @return string
     */
    function showTablesStatement ($like='') {
        switch($this->driver()) {
            case 'mysql': return (
                "SHOW TABLES LIKE '".$this->prefix($like)."%'"
            );
            case 'sqlite': return (
                "SELECT name FROM sqlite_master WHERE type='table'"
                ." AND name LIKE '".$this->prefix($like)."%'"
            );
        }
        return (
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES"
            ." WHERE TABLE_NAME LIKE '".$this->prefix($like)."%'"
        );
    }

    final function showTables ($like='') {
        return $this->fetchAllColumn($this->showTablesStatement($like));
    }

    /**
     * Return a SHOW COLUMNS FROM $table equivalent SQL statement.
     *
     * @param string $table
     * @return string
     */
    function showColumnsStatement ($name) {
        switch($this->driver()) {
            case 'mysql': return (
                "SHOW COLUMNS FROM ".$this->prefixed($name)
            );
            case 'sqlite': return (
                "SELECT"
                ." name AS Field,"
                ." type AS Type,"
                ." notnull AS Null,"
                ." dflt_value AS Default"
                ." FROM PRAGMA table_info('".$this->prefix($name)."')"
            );
        }
        return (
            "SELECT"
            ." COLUMN_NAME AS Field,"
            ." DATA_TYPE AS Type,"
            ." IS_NULLABLE AS Null"
            ." COLUMN_DEFAULT AS Default,"
            ." FROM INFORMATION_SCHEMA.COLUMNS"
            ." WHERE TABLE_NAME = '".$this->prefix($name)."'"
            ." AND TABLE_SCHEMA = '".$this->databaseName()."'"
            ." ORDER BY ORDINAL_POSITION"
        );
    }

    final function showColumns ($name, $key='Field') {
        $colums = array();
        $rows = $this->fetchAll($this->showColumnsStatement($name));
        foreach ($rows as $row) {
            $columns[$row[$key]] = $row;
        }
        return $columns;
    }
    function showIndexes ($name) {
        switch($this->driver()) {
            case 'mysql':
                $indexes = array();
                $rows = $this->fetchAll(
                    "SHOW INDEXES FROM ".$this->prefixed($name)
                    ." WHERE Non_unique = 1"
                );
                foreach ($rows as $row) {
                    $indexName = $row['Key_name'];
                    if (!in_array($indexName, $indexes)) {
                        $indexes[] = $indexName;
                    }
                }
                return $indexes;
            case 'sqlite':
                return $this->fetchAllColumn(
                    "SELECT name FROM sqlite_master"
                    ." WHERE type = 'index'"
                    ." AND sql = NULL"
                    ." AND tbl_name = '".$this->prefix($name)."'"
                );
            case 'pgsql':
                return $this->fetchAllColumn(
                    "SELECT ci.relname"
                    ." FROM pg_index i, pg_class ci, pg_class ct"
                    ." WHERE i.indexrelid = ci.oid"
                    ." AND i.indrelid = ct.oid"
                    ." AND ct.relname='".$this->prefix($name)."'"
                );
        }
        throw $this->exception("Not implemented for ".$this->driver());
    }
    function showForeign ($name) {
        $rows = $this->fetchAll((
            "SELECT"
            ." TABLE_NAME,"
            ." COLUMN_NAME,"
            ." CONSTRAINT_NAME,"
            ." REFERENCED_TABLE_NAME,"
            ." REFERENCED_COLUMN_NAME"
            ." FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE"
            ." WHERE TABLE_NAME = ?"
            ." AND REFERENCED_TABLE_NAME IS NOT NULL"
            ." AND CONSTRAINT_SCHEMA = ?"
        ), array(
            $this->prefix($name),
            $this->databaseName()
        ));
        $foreign = array();
        SQLAbstract::indexMap($rows, 'REFERENCED_TABLE_NAME', function ($key, $row, &$index) {
            if (count($index[$key]) === 0) {
                $index[$key][0] = array();
                $index[$key][1] = array();
            }
            $index[$key][0][] = $row['COLUMN_NAME'];
            $index[$key][1][] = $row['REFERENCED_COLUMN_NAME'];
        }, $foreign);
        return $foreign;
    }
}
