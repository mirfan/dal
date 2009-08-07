<?php
/**
 * Class MySQL
 * This class is an object-oriented wrapper to PHP's native mysql_* functions. The API is inspired
 * from JDBC and PDO. There are some helper methods to make developer's work easy.
 *
 * @compatibility >= PHP4
 * @author Mohammed Irfan Shaikh
 * @mail mirfan@semaphore-software.com
 * @version 0.2
 */

class MySQL {
    /**
     * Private variables
     */
    var $link; // MySQL connection link
	var $resource; //MySQL result resource
	var $parameters; // array for storing bind parameters
	var $errStr; // error string

    /**
     * Checks connection and database selection
     *
     * @access public
     * @param String hostname
     * @param String database username
     * @param String database password
     * @param String database name
     */
    function MySQL($hostname, $username, $password, $database) {
		$this->link = 0;
		$this->resource = 0;
		$this->parameters = array();
		$this->errStr = '';

		$this->link = mysql_connect($hostname, $username, $password);

		if (!$this->link)
			$this->halt('Connect failed.');

		if (!mysql_select_db($database, $this->link))
			$this->halt("Cannot use database '$database'.");
	}

	/**
     * Halts with error message
     * @access private
     * @param String message
	 */
    function halt($msg) {
		trigger_error(sprintf("Database error : %s<br>%s\n", $this->errStr, $msg), E_USER_ERROR);
	}

	/**
     * Escapes ' and " to prevent SQL injection.
     * @access private
     * @param String value
     * @return String escaped value
	 */
    function quote($str) {
		if (get_magic_quotes_gpc())
			$str = stripslashes($str);
		$str = mysql_real_escape_string($str, $this->link);

		if (!is_numeric($str) or (intval($str) != $str))
			$str = "'$str'";
		return $str;
	}

	/**
     * Binds parameter at given index in the SQL string and escapes value
     * @access public
     * @param Integer index of field in the SQL string
     * @param String value of field
	 */
    function bindParam($index, $val) {
		$this->parameters[$index] = $this->quote($val);
	}

	/**
     * Prepares SQL string from raw sql and bound parameters
     * @access public
     * @param String raw SQL string with '?'s
     * @return String SQL string with '?' replaced with actual field value
	 */
    function prepare($rawSql) {
		$sql_parts = explode('?', $rawSql);
		$sql = $sql_parts[0];
		for ($i = 1, $end = count($sql_parts); $i < $end; $i++) {
			$sql .= $this->parameters[$i] . $sql_parts[$i];
		}
		return $sql;
	}

	/**
     * Invokes native mysql_query to perform database queries
     * @access public
     * @param String prepared SQL string
	 */
    function query($sql) {
		$sql = chop($sql);

		$this->resource = mysql_query($sql, $this->link);

		$this->errStr = mysql_error();

		if (!is_resource($this->resource) && !$this->resource) {
			$this->halt('Invalid SQL: ' . $sql);
		}
	}

	/**
     * Helper method for fetching a single field
     * @access public
     * @param String prepared SQL string
     * @return Mixed field value
	 */
    function fetchOne($sql) {
		$this->query($sql);
		$result = mysql_fetch_row($this->resource);
		return $result[0] ? $result[0] : 0;
	}

	/**
     * Helper method for fetching a column of field
     * @access public
     * @param String prepared SQL string
     * @return Mixed array of field
     */
    function fetchCol($sql) {
		$this->query(sql);

		$col = array();

		while ($row = mysql_fetch_row($this->resource)) {
			$col[] = $row[0];
		}

		return $col;
	}

    /**
     * Helper method for fetching a single row
     * @access public
     * @param String prepared SQL string
     * @return Mixed associative array of record
     */
    function fetchRow($sql) {
		$this->query($sql);
		$result = mysql_fetch_assoc($this->resource);
		return $result ? $result : null;
	}

    /**
     * Helper method for fetching rows
     * @access public
     * @param String prepared SQL string
     * @return Mixed array of rows
     */
    function fetchAll($sql) {
		$this->query($sql);

		$results = array();

		while ($row = mysql_fetch_assoc($this->resource)) {
			$results[] = $row;
		}

		return $results;
	}

    /**
     * Helper method for inserting a record from array
     * @access public
     * @param String table name
     * @param Mixed array of field values
     * @return Integer id of last inserted record
     */
    function insert($table, $values) {
		$this->query(sprintf(
			'INSERT INTO `%s` (%s) VALUES (%s);',
			$table,
			join(
				', ',
				array_keys($values)
			),
			join(
				', ',
				array_map(
					array($this, 'quote'),
					array_values($values)
				)
			)
		));

		return mysql_insert_id($this->link);
	}

    /**
     * Helper method for updating a record from array
     * @access public
     * @param String table name
     * @param Mixed array of field values
     * @param Mixed associative array of field name and value to build a where string
     * @return Integer # of updated records
     */
    function update($table, $set, $where) {
		$fields = array_keys($set);
		$vals = array_map(
			array($this, 'quote'),
			array_values($set)
		);

		$setStr = '';

		for ($i = 0, $end = count($vals); $i < $end; $i++) {
			$setStr .= $fields[$i] . ' = ' . $vals[$i] . ' ,';
		}

		$setStr = substr($setStr, 0, -1);

		$whereField = array_keys($where);
		$whereVal = $this->quote($where[$whereField[0]]);

		$whereStr = $whereField[0] . ' = ' . $whereVal;

		$sql = sprintf('UPDATE `%s` SET %s WHERE %s;', $table, $setStr, $whereStr);

		$this->query($sql);

		return mysql_affected_rows($this->link);
	}

    /**
     * Helper method for deleting record(s)
     * @access public
     * @param String table name
     * @param Mixed associative array of field name and value to build a where string
     * @return Integer # of updated records
     */
	function delete($table, $where) {
		$whereField = array_keys($where);
		$whereVal = $this->quote($where[$whereField[0]]);

		$whereStr = $whereField[0] . ' = ' . $whereVal;

		$sql = sprintf('DELETE FROM `%s` WHERE %s;', $table, $whereStr);

		$this->query($sql);
		return mysql_affected_rows($this->link);
	}

    /**
     * Helper method for getting # of columns
     * @access public
     * @return Integer # of columns
     */
	function numCols() {
		return mysql_num_fields($this->resource);
	}

    /**
     * Helper method for getting # of rows
     * @access public
     * @return Integer # of rows
     */
	function numRows() {
		return mysql_num_rows($this->resource);
	}

    /**
     * Helper method for getting id of last inserted record
     * @access public
     * @return Integer id of last inserted record
     */
	function insertID() {
		return mysql_insert_id($this->link);
	}

    /**
     * Helper method for getting # of affected rows
     * @access public
     * @return Integer # of updated records
     */
	function affectedRows() {
		return mysql_affected_rows($this->link);
	}
}