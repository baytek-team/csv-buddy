<?php

namespace Baytek;

use Exception;

/**
 * Class CsvBuddy
 * @package Baytek\CsvBuddy
 */
Class CsvBuddy {

	/**
	 * CSV Delimiter
	 * @var string
	 */
	protected $delimiter = ',';

	/**
	 * CSV Schema Definition
	 * @var null
	 */
	protected $schema = null;

	/**
	 * CSV Column Headers
	 * @var array
	 */
	protected $headers = [];

	/**
	 * CSV Column Names, not sure if required, we will see
	 * @var array
	 */
	protected $columns = [];

	/**
	 * CSV Data Store
	 * @var array
	 */
	protected $store = [];

	/**
	 * Index of current row
	 * @var integer
	 */
	protected $row = 0;

	/**
	 * CSV Buddy creation method
	 * @param Array $table Table Schema in named array format
	 */
	public function __construct(Array $schema)
	{
		//Save the schema
		$this->schema = $schema;

		// Check to see that this is a sequential array and not a named key array
		$sequential = $this->isSequential($schema);

		if($sequential) {
			foreach($schema as $column) {
				array_push($this->headers, $column);
				array_push($this->columns, $column);
				$this->store[] = [];
			}
		}
		else {
			foreach($schema as $column => $parameters) {
				array_push($this->headers, isset($parameters['header']) ? $parameters['header'] : $column);
				array_push($this->columns, $column);
				$this->store[] = [];
			}
		}
	}

	/**
	 * To String will return a valid CSV string
	 * @return [string] CSV Result
	 */
	public function __toString()
	{
		return $this->render();
	}

	/**
	 * To String will return a valid CSV string
	 * @return null
	 */
	public function __set($column, $value)
	{
		$this->store($column, $value);
	}

	/**
	 * Creates the actual CSV file based on columns and rows sent
	 * @return [string] CSV Result
	 */
	public function render()
	{
		ob_start();
		$handle = fopen('php://output', 'r+');
		if (count($this->headers))
		{
			fputcsv($handle, $this->headers, $this->delimiter);
		}

		foreach ($this->store as $index => $actualData)
		{
			$row = [];
			foreach ($this->columns as $column)
			{
				array_push($row, isset($this->store[$index][$column]) ? $this->store[$index][$column] : null);
			}

			fputcsv($handle, $row, $this->delimiter);
		}

		return ob_get_clean();
	}

	/**
	 * [isSequential description]
	 * @param  [type]  $array [description]
	 * @return boolean        [description]
	 */
	private function isSequential($array)
	{
		return array_keys($array) === range(0, count($array) - 1);
	}

	private function store($column, $value)
	{
		if(!empty($this->store[$this->row][$column])) {
			throw new Exception('Cell already contains data, you cannot re-populate cells for the time being');
		}

		$this->store[$this->row][$column] = $value;

		return $this;
	}

	/**
	 * New row will iterate through all of the columns and null fill each unfilled column and increment the row value
	 * @return $this for chaining
	 */
	public function endRow()
	{
		//Add some checks to ensure that the current row isn't empty, causing empty rows
		$this->row ++;
		return $this; // Allow for method chaining
	}



	/**
	 * Public method for filling a column with data
	 * @return $this for chaining
	 */
	// protected function fill() //$cell, $value
	// {
	// 	$args = func_get_args();
	// 	$data = is_array( $args[0] ) ? $args[0] : $args;

	// 	$sequential = $this->isSequential($data);

	// 	if($sequential) {
	// 		$this->store($data[0], $data[1])->endRowCheck();
	// 	}
	// 	else {
	// 		foreach($data as $column => $value) {
	// 			$this->store($column, $value);
	// 		}
	// 	}

	// 	return $this; // Allow for method chaining
	// }


	public function setColumn($column, $value)
	{
		$this->store($column, $value);
		return $this;
	}

	public function addRow($columns)
	{
		foreach($columns as $column => $value) {
			$this->store($column, $value);
		}

		$this->endRow();
		return $this;
		// return call_user_func_array(array($this, 'fill'), func_get_args());
	}


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
		echo('<tr>');
		array_walk($item, array('self', 'print_cell'));
		echo('</tr>');
	}

	private function print_cell(&$item)
	{
		echo('<td>');
		echo($item);
		echo('</td>');
	}

}