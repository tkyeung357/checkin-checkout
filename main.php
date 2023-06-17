<?php
/*
	Goal: calculate employee's working time
	Requirement: 
		- total working time for working day
		- list out working time to 4 working period 
			1. Morning (5:00 AM - 12:00 PM)
			2. Afternoon (12:00 PM - 6:00 PM)
			3. Evening (6:00 PM - 11:00 PM)
			4. Late Night (11:00 PM - 5:00 AM)

	Expected result:
		1. able to search by employee id
		2. calculate and group by work day 
		3. stored result as json format 

	Data analysis: 
		1. source data: 
			- employees
				- id (key)
				- first & last name
			- clocks
				- employee_id (key)
				- clock in and out (Y-m-d h:i:s)
	Assumptions: 
		1. one to many relationship between 2 objects 
		2. no time overlap for same employee
		3. time sensitive to calculate working period 
			- period1: 5:00:00 - 12:00:00
			- period2: 12:00:00 - 18:00:00
			- period3: 18:00:00 - 23:00:00
			- period4: 23:00:00 - 5:00:00
	use cases:
		1. support multiple working day
		2. working day calculation:  use before and after day to calculate 
		3. period 4 could be cover 2 working day

	- user story (simulating real world time punch flow)
		- given an employee
		- the employee go to punch the clock (ok, this is not real world flow, because we punch in and out at same time.)
		- then the Labour API receilve the clock and allocate to the timeslot with working time (seconds)
*/

namespace Data;
//working time
class Labour
{
	private $date; //y-m-d format
	private $total; //total working hrs
	// time shift
	private $period1; //5:00:00 - 12:00:00
	private $period2; //12:00:00 - 18:00:00
	private $period3; //18:00:00 - 23:00:00
	private $period4; //23:00:00 - 23:59:59 (1hour)

	//table of timeslot and store the actual working seconds
	private $timeslot = [];

	public function __construct(\DateTime $date)
	{
		//init date 
		$this->date = $date->format('Y-m-d');
		//init create 24 hours timeslot
		for($i = 0; $i <= 23; $i++) {
			$this->timeslot[$i] = 0;
		}

	}

	public function get_date()
	{
		return $this->date;
	}

	public function add_timeslot($hours, $total_seconds)
	{
		if (array_key_exists($hours, $this->timeslot)) {
			throw new \Exception ("invalid timeslot");
		}
		$tmp = $this->timeslot[$hours] + $total_seconds;

		if ($tmp > 3600) {
			//1 hour
			throw new \Exception ("max seconds reached to this timesplot, something wrong.");
		}

		$this->timeslot[$hours] = $tmp;
	}
}

namespace Data;
//punch time
class Clock
{
	private $id;
	private $in;
	private $out;
	public function __construct(int $id, \DateTime $in, \DateTime $out)
	{
		$this->id = $id;
		$this->in = $in;
		$this->out = $out;
	}

	public function get_id()
	{
		return $this->id;
	}
}

namespace Data;

class Employee
{
	private $id;
	private $first_name;
	private $last_name;

	private $labours;
	private $clocks;

	public function __construct($id, $first, $last, \API\DataSet\Clocks $clocks, \API\DataSet\Labours $labours)
	{
		$this->id = $id;
		$this->first_name = $first;
		$this->last_name = $last;
		//inject employee informations
		//we could use builder or decorator to improve various of employee
		$this->labours = $labours;
		$this->clocks = $clocks;
	}

	public function get_id()
	{
		return $this->id;
	}

	public function get_clocks(): \API\DataSet\Clocks
	{
		return $this->clocks;
	}

	public function get_labours(): \API\DataSet\Labours
	{
		return $this->labours;
	}
}

namespace API\Export;
class Employee
{
	public function export(int $id)
	{
	}
}

namespace API\DataSet;
class Employees
{
	private $employees = [];
	public function addEmployee(\Data\Employee $e)
	{
		if (!array_key_exists($e->get_id(), $this->employees)) {
			//new employee 
			$this->employees[] = $e;
		}

	}

	public function get_employee(int $id):\Data\Employee
	{
		return $this->employees[$id];
	}

	public function puchClock(\Data\Clock $clock)
	{
		if (!array_key_exists($clock->get_id(), $this->employees)) {
			throw new \Exception ("employee not found");
		}
		$employee = $this->get_employee($clock->get_id());
		//store the clock
		$clocks = $employee->get_clocks();
		$clocks->add_clock($clock);
		//update labour records
		$labours = $employee->get_labours();
	}

	public function search(int $id)
	{
	}

	public function getAllEmployee()
	{
		return $this->employees;
	}
}

namespace API\DataSet;
class Clocks
{
	private $clocks = [];

	public function add_clock (\Data\Clock $c)
	{
		$this->clocks[] = $c;
	}
}

namespace API\DataSet;
class Labours
{
	private $labours = [];

	// public function add_labour_date(\Data\Labour $l)
	// {
	// 	$date = $l->get_date();
	// 	if (array_key_exists($date, $this->labours)) {
	// 		throw new \Exception ("labour date exists");
	// 	}

	// 	$this->labours[$date] = $l;
	// }

	public function update_labour_date(\Data\Labour $l)
	{
		$date = $l->get_date();

		$this->labours[$date] = $l;
	}


	public function get_labour_date(\DateTime $d)
	{
		$date = $d->format("Y-m-d"); 
		if (!array_key_exists($date, $this->labours)) {
			throw new \Exception ("labour date not found");
		}

		return $this->labours[$date];
	}

}

namespace API\Search;
class Employee
{
	public function __construct()
	{
	}
	public function search(int $id)
	{
	}
}

namespace Test;
class Test
{
	static public function main()
	{
		$dataSource = json_decode(file_get_contents("test_data.json"), true);
		#import data 
		$employeeDataSet = self::init_employees($dataSource['employees']);
		$employeeDataSet = self::init_clocks($employeeDataSet, $dataSource['clocks']);
		print_r($employeeDataSet->getAllEmployee());
	}

	static function init_employees(array $employees): \API\DataSet\Employees
	{
		$employeeDataSet = new \API\DataSet\Employees();
		foreach($employees as $e) {
			$tmpLabours = new \API\DataSet\Labours();  
			$tmpClocks = new \API\DataSet\Clocks();
			$tmpEmployee = new \Data\Employee($e["id"], $e["first_name"], $e["last_name"], $tmpClocks, $tmpLabours);
			$employeeDataSet->addEmployee($tmpEmployee);
		}

		return $employeeDataSet;
	}

	static function init_clocks(\API\DataSet\Employees $employees, array $clocks): \API\DataSet\Employees
	{
		foreach($clocks as $c) {
			$in = new \DateTime($c["clock_in_datetime"]);
			$out = new \DateTime($c["clock_out_datetime"]);
			$tmpClock = new \Data\Clock($c["employee_id"], $in, $out);
			$employees->puchClock($tmpClock);
		}

		return $employees;
	}

}

Test::main();
