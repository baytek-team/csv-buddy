<?php

namespace Baytek;

use Exception;

/**
 * Class CsvBuddy.
 */
class CsvBuddy
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
    protected $headers = [];

    /**
     * CSV Column Names, not sure if required, we will see.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * CSV Data Store.
     *
     * @var array
     */
    protected $store = [];

    /**
     * Index of current row.
     *
     * @var int
     */
    protected $row = 0;

    /**
     * CSV Buddy creation method.
     *
     * @param array $table Table Schema in named array format
     */
    public function __construct(array $schema)
    {
        //Save the schema
        $this->schema = $schema;

        // Check to see that this is a sequential array and not a named key array
        $sequential = $this->isSequential($schema);

        // if ($sequential) {
        //     foreach ($schema as $column) {
        //         array_push($this->headers, $column);
        //         array_push($this->columns, $column);
        //         $this->store[] = [];
        //     }
        // } else {
            foreach ($schema as $column => $parameters) {
            	if(is_integer($column)) {
            		$column = $parameters;
            	}

                array_push($this->headers, isset($parameters['header']) ? $parameters['header'] : $column);
                array_push($this->columns, $column);
                // $this->store[] = [];
            }
        // }
    }

    /**
     * toString will return a valid CSV string.
     *
     * @return [string] CSV Result
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Magic method for setting a column in the data store
     *
     * @param string $column Column to set
     * @param mixed  $value  Data to set into column
     */
    public function __set($column, $value)
    {
        $this->put($column, $value);
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
        foreach ($columns as $column => $value) {
            $this->put($column, $value);
        }

        $this->newRow();

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
    private function put($column, $value)
    {
        if(! $this->validate($column, $value)) {
        	throw new Exception("Data not valid: \"$value\"");
        }

        if (!empty($this->store[$this->row][$column])) {
            throw new Exception('Cell already contains data, you cannot re-populate cells for the time being');
        }

        $this->store[$this->row][$column] = $value;

        return $this;
    }

    // $this->print_table($this->store);

    /**
     * Creates the actual CSV file based on columns and rows sent.
     *
     * @return [string] CSV Result
     */
    public function render()
    {
        ob_start();

        $handle = fopen('php://output', 'r+');

        if (count($this->headers)) {
            fputcsv($handle, $this->headers, $this->delimiter);
        }

        for ($x = 0; $x <= $this->row; ++$x) {
            $row = [];
            foreach ($this->columns as $column) {
                if (!isset($this->store[$x][$column])) {
                    $value = $this->defaults($column, $x);
                } else {
                    $value = $this->store[$x][$column];
                }

                array_push($row, $value);
            }

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
     * isSequential Checks to see if the array is sequential or is named keys
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
        //Add some checks to ensure that the current row isn't empty, causing empty rows
        ++$this->row;

        foreach ($this->columns as $column) {
        	$this->store[$this->row][$column] = null;

        	if($this->schema[$column]['required'] && $this->row - 1 > 0) {
        		if(is_null($this->store[$this->row - 1][$column])) {
        			throw new Exception("Cannot close row. Column \"$column\" is empty, this field is required.");
        		}
        	}

        }

        return $this; // Allow for method chaining
    }

    public function validate($column, $value)
    {
    	if(isset($this->schema[$column]['regex'])) {
    		return preg_match($this->schema[$column]['regex'], $value);
    	}

    	if(isset($this->schema[$column]['type'])) {
    		return gettype($value) === $this->schema[$column]['type'];
    	}

    	return true;
    }

    // public function query($column, $value)
    // {
    // 	if (is_callable($value)) {
    // 	    return $value($row);
    // 	} else {
    // 	    return $value;
    // 	}
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
