<?php

namespace Baytek;

use ArrayAccess;
use ErrorException;
use Iterator;
use Countable;

/**
 * Class CsvBuddy.
 */
class CsvBuddy implements Iterator, ArrayAccess, Countable
{
    /**
     * CSV Delimiter.
     *
     * @var string
     */
    protected $delimiter = ',';

    /**
     * CSV Schema Definition.
     *
     * @var null
     */
    protected $schema = null;

    /**
     * CSV Column Headers.
     *
     * @var array
     */
    protected $headers = array();

    /**
     * CSV Column Names, not sure if required, we will see.
     *
     * @var array
     */
    protected $columns = array();

    /**
     * CSV Data Store.
     *
     * @var array
     */
    protected $store = array();

    /**
     * Index of current row.
     *
     * @var int
     */
    protected $row = 0;



    /**
     * CSV Buddy constructor.
     *
     * The array passed here can be fairly complex,
     *
     * @param array $table Table Schema in named array format
     * @param boolean $skips skips setting
     */
    public function __construct(array $schema)
    {
        // Set the internal column count to zero
        $this->row = 0;

        // Save the schema
        $this->schema = $schema;

        // Check to see that this is a sequential array and not a named key array
        $sequential = $this->isSequential($schema);

        foreach ($schema as $column => $parameters) {
            if (is_integer($column)) {
                if (is_string($parameters) || is_array($parameters)) {
                    $column = $parameters;
                } else {
                    throw new ErrorException("Schema not valid, $parameters is not expected.", 0, E_USER_ERROR);
                }
            }

            array_push($this->headers, isset($parameters['header']) ? $parameters['header'] : $column);
            array_push($this->columns, $column);
        }

        return $this;
    }

    /**
     * [load description]
     * @param  [type] $file [description]
     * @return [type]       [description]
     */
    public function load($file)
    {
        $csv = str_getcsv($file, "\n");
        $headers = str_getcsv(array_shift($csv), $this->delimiter);
        $columns = count($headers);

        // Check that the number of columns matches the schema headers
        // if (count($this->schema) !== $columns && !$skips) {
        //     throw new ErrorException('Supplied CSV columns does not match schema columns', 0, E_USER_ERROR);
        // }

        // Make sure that all of the headers match up, and if they are using a named column, save the column key instead as headers

        foreach($headers as &$header) {
            foreach ($this->schema as $column => $parameters) {
                // Check if the column is an int, if so we know its not a named key
                if (is_integer($column)) {
                    // Check if the header matches the schema, if not this is an error
                    // if (is_string($parameters) && $header != $parameters) {
                    //     throw new ErrorException("Column \"$parameters\" does not match \"{$header}\".", 0, E_USER_ERROR);
                    // }
                } elseif ($header != $column) {
                    // Check if the header schema value was set
                    if(isset($parameters['header'])) {
                        // Check that the header matches the schema header, if so remap
                        if ($header == $parameters['header']) {
                            $header = $column;
                        }
                    }
                }
            }
        }

        // dd($this->headers);
        foreach ($csv as $row) {
            $this->newRow();

            $row = str_getcsv($row, $this->delimiter);

            // Loop though the columns and set their data into the store
            for ($i = 0; $i < $columns; ++$i) {
                if(in_array($headers[$i], $this->columns)) {
                    $this->put($headers[$i], $row[$i], false);
                }
            }
        }
    }

    /**
     * toString will return a valid CSV string.
     *
     * @return [string] CSV Result
     */
    public function __toString()
    {
        return $this->toCsv();
    }

    /**
     * Magic method for setting a column in the data store.
     *
     * @param string $column Column to set
     * @param mixed  $value  Data to set into column
     */
    public function __set($column, $value)
    {
        $this->put($column, $value);
    }


    /**
     * Magic method for setting a column in the data store.
     *
     * @param string $column Column to set
     * @param mixed  $value  Data to set into column
     */
    public function __get($column)
    {
        return $this->store[$this->row][$column];
    }

    /**
     * setColumn method takes a key and value pair and sets that to the data store.
     *
     * @param string $column Column to set
     * @param mixed  $value  Data to set into column
     */
    public function setColumn($column, $value)
    {
        $this->put($column, $value);

        return $this;
    }

    /**
     * addRow takes an array of fields to set into the data store.
     *
     * @param array $columns An named array of values to be set in the data store
     *
     * @return $this Return self to allow for chaining
     */
    public function addRow($columns)
    {
        // $this->newRow();

        foreach ($columns as $column => $value) {
            $this->put($column, $value);
        }

        return $this;
        // return call_user_func_array(array($this, 'fill'), func_get_args());
    }

    /**
     * [put description].
     *
     * @param string $column Column to set
     * @param mixed  $value  Data to set into column
     *
     * @return [type] [description]
     */
    private function put($column, $value, $validate = true)
    {
        if ($validate) {
            if (!$this->validate($column, $value)) {
                throw new ErrorException("Data not valid: \"$value\"", 0, E_USER_ERROR);
            }

            if (!empty($this->store[$this->row][$column])) {
                throw new ErrorException('Cell already contains data, you cannot re-populate cells for the time being', 0, E_WARNING);
            }
        }

        $this->store[$this->row][$column] = $value;

        return true;
    }

    /**
     * toJson method returns a JSON string of the store
     * @return string JSON Result
     */
    public function toJson()
    {
        return json_encode($this->store);
    }

    /**
     * Returns an HTML table of the store
     * @return string HTML table
     */
    public function toHtml()
    {
        ob_start();
        $this->print_table($this->store);
        return ob_get_clean();
    }

    /**
     * Creates the actual CSV file based on columns and rows sent.
     *
     * @return [string] CSV Result
     */
    public function toCsv()
    {
        ob_start();

        // Create a local handle
        $handle = fopen('php://output', 'r+');

        if (count($this->headers)) {
            fputcsv($handle, $this->headers, $this->delimiter);
        }

        // Loop though the rows
        for ($x = 0; $x <= $this->row; ++$x) {
            // Use local array to ensure proper ordering
            $row = array();
            // Loop though the columns
            foreach ($this->columns as $column) {
                // If empty use the default value if it exists
                if (!isset($this->store[$x][$column])) {
                    $value = $this->defaults($column, $x);
                } else {
                    $value = $this->store[$x][$column];
                }

                // Push the value to the local array
                array_push($row, $value);
            }
            // Put the CSV content
            fputcsv($handle, $row, $this->delimiter);
        }

        return ob_get_clean();
    }

    /**
     * Default will check based on the column name if there is a default value if non is set.
     *
     * @param string $column Column to check the default value for
     *
     * @return mixed The default value
     */
    public function defaults($column, $row)
    {
        if (isset($this->schema[$column]['default'])) {
            $default = $this->schema[$column]['default'];

            if (is_callable($default)) {
                return $default($row);
            } else {
                return $default;
            }
        } else {
            return;
        }
    }

    /**
     * isSequential Checks to see if the array is sequential or is named keys.
     *
     * @param array $array The array to check
     *
     * @return bool isSequential?
     */
    private function isSequential($array)
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * newRow will iterate through all of the columns and null fill each unfilled column and increment the row value.
     *
     * @return $this for chaining
     */
    public function newRow()
    {
        // if (!isset($this->store[$this->row])) {
        //     return $this;
        // }

        ++$this->row;

        foreach ($this->columns as $column) {
            $this->store[$this->row][$column] = null;

            // Check the rows to see if there are required fields and throw user error if something is missing
            if (!empty($this->schema[$column]['required']) && $this->row - 1 > 0) {
                if (is_null($this->store[$this->row - 1][$column])) {
                    throw new ErrorException("Cannot close row. Column \"$column\" is empty, this field is required.", 0, E_USER_ERROR);
                }
            }
        }

        return $this; // Allow for method chaining
    }

    /**
     * [validate description]
     * @param  [type] $column [description]
     * @param  [type] $value  [description]
     * @return [type]         [description]
     */
    public function validate($column, $value)
    {
        // Check for regular expression and return the match
        if (isset($this->schema[$column]['regex'])) {
            return preg_match($this->schema[$column]['regex'], $value);
        }
        // Check the column type and return the match
        elseif (isset($this->schema[$column]['type'])) {
            return gettype($value) === $this->schema[$column]['type'];
        }

        return true;
    }

    //Iterator Methods

    /**
     * [rewind description]
     * @return [type] [description]
     */
    function rewind() {
        $this->row = 0;
    }

    /**
     * [current description]
     * @return [type] [description]
     */
    function current() {
        return $this->store[$this->row];
    }

    /**
     * [key description]
     * @return [type] [description]
     */
    function key() {
        return $this->row;
    }

    /**
     * [next description]
     * @return function [description]
     */
    function next() {
        ++$this->row;
    }

    /**
     * [valid description]
     * @return [type] [description]
     */
    function valid() {
        return isset($this->store[$this->row]);
    }

    /**
     * Implements ArrayAccess Interface
     *
     * @param integer Return the number of rows
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->store[] = $value;
        } else {
            $this->store[$offset] = $value;
        }
    }

    /**
     * [offsetExists description]
     * @param  [type] $offset [description]
     * @return [type]         [description]
     */
    public function offsetExists($offset) {
        return isset($this->store[$offset]);
    }

    /**
     * [offsetUnset description]
     * @param  [type] $offset [description]
     * @return [type]         [description]
     */
    public function offsetUnset($offset) {
        unset($this->store[$offset]);
    }

    /**
     * [offsetGet description]
     * @param  [type] $offset [description]
     * @return [type]         [description]
     */
    public function offsetGet($offset) {
        return isset($this->store[$offset]) ? $this->store[$offset] : null;
    }

    /**
     * Implements countable interface
     *
     * @param integer Return the number of rows
     */
    public function count()
    {
        return count($this->store);
    }

    // public function query($column, $value)
    // {
    //  if (is_callable($value)) {
    //      return $value($row);
    //  } else {
    //      return $value;
    //  }
    //     return true;
    // }

    ///////////////////////////////////////////////////////////////////////////
    /// DEBUGGING FUNCTIONS ///////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////

    public function print_table($table)
    {
        echo '<table border="1">';
        array_walk($table, array('self', 'print_row'));
        echo '</table>';
    }

    private function print_row(&$item)
    {
        echo '<tr>';
        array_walk($item, array('self', 'print_cell'));
        echo '</tr>';
    }

    private function print_cell(&$item)
    {
        echo '<td>';
        echo $item;
        echo '</td>';
    }
}
