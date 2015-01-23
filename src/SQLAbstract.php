<?php

/**
 * An SQL data abstraction layer class.
 */
abstract class SQLAbstract {

    // The practical abstractions.

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

    // The concrete conveniences: query builders and executers.

    /**
     * Validate the prefixed $name return the quoted identifier.
     *
     * @param string $name
     * @return string
     */
    function prefixedIdentifier ($name) {
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
            ." FROM ".$this->prefixedIdentifier($view)
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
            ." FROM ".$this->prefixedIdentifier($view)
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
            ." FROM ".$this->prefixedIdentifier($view)
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
    /**
     * Return an SQL expression with positional placeholders and a list of parameters
     * for the given $filter and $like array.
     *
     * @param array $filter
     * @param array $like
     * @return array ($where, $params)
     */
    function filterLike($filter, $like=NULL) {
        $whereFilter = array();
        $params = array();
        foreach ($filter as $column => $value) {
            if (!JSONMessage::is_list($value)) {
                array_push(
                    $whereFilter,
                    $this->identifier($column)." = ".$this->placeholder($value)
                    );
                array_push($params, $value);
            } elseif (count($value) > 0) {
                array_push($whereFilter, (
                    $this->identifier($column)
                    ." IN (".implode(', ', array_map(
                        array($this, 'placeholder'), $value
                        )).")"
                    ));
                $params = array_merge($params, $value);
            }
        }
        if ($like !== NULL && count($like) > 0) {
            $whereLike = array();
            foreach ($like as $column => $value) {
                array_push(
                    $whereLike,
                    $this->identifier($column)." LIKE ".$this->placeholder($value)
                    );
                array_push($params, $value);
            }
            if (count($whereLike)>0) {
                array_push($whereFilter, "(".implode(" OR ", $whereLike).")");
            }
        }
        return array(implode(" AND ", $whereFilter), $params);
    }
    /**
     *
     */
    function whereParams ($message) {
        if ($message->has('where')) {
            return array(
                $message->getString('where'),
                $message->getList('params', array())
                );
        } else {
            return $this->filterLike(
                $message->getMap('filter', array()),
                $message->getMap('like', array())
                );
        }
    }

    static function assertSafe ($options) {
        if (
            array_key_exists('where', $options) ||
            array_key_exists('params', $options)
            ) {
            throw $this->exception('unsafe options defined');
        }
    }

    function countStatement ($view, $options) {
        list($where, $params) = $this->whereParams(new JSONMessage($options));
        $sql = (
            "SELECT COUNT(*) FROM ".$this->prefixedIdentifier($view)
            .($where === '' ? "" : " WHERE ".$where)
            );
        return array($sql, $params);
    }

    function count ($view, $options, $safe=FALSE) {
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
        $orders = $m->getList('orders', array());
        $sql = (
            "SELECT ".$this->columns($columns)
            ." FROM ".$this->prefixedIdentifier($view)
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

    function column ($view, $options, $safe=FALSE) {
        if (
            array_key_exists('columns', $options) &&
            count($options['columns'])===1
            ) {
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
            .$this->prefixedIdentifier($table)
            ." (".implode(", ", array_map(array($this, 'identifier'), $keys)).")"
            ." VALUES (".implode(", ",
                array_map(array($this, 'placeholder'), $params)
                ).")",
            $params
            );
    }

    function insert ($table, $map) {
        list($sql, $params) = $this->insertStatement($table, $map, 'INSERT');
        $this->execute($sql, $params);
        return $this->lastInsertId();
    }

    function replace ($table, $map) {
        list($sql, $params) = $this->insertStatement($table, $map, 'REPLACE');
        return $this->execute($sql, $params);
    }

    function updateStatement($table, $map, $options) {
        $setExpressions = array();
        $setParams = array();
        foreach($map as $name => $value) {
            array_push(
                $setExpressions,
                $this->identifier($name)." = ".$this->placeholder($value)
                );
            array_push($setParams, $value);
        }
        list($whereExpression, $whereParams) = $this->whereParams(new JSONMessage($options));
        return array((
            "UPDATE "
            .$this->prefixedIdentifier($table)
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
        list($whereExpression, $whereParams) = $this->whereParams(new JSONMessage($options));
        return array((
            "DELETE FROM "
            .$this->prefixedIdentifier($table)
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

}
