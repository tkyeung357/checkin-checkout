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
		3. working peroid hours for calculate working period 
			- period1: 5:00:00 - 12:00:00 (7hours)
			- period2: 12:00:00 - 18:00:00 (6hours)
			- period3: 18:00:00 - 23:00:00 (5hours)
			- period4: 23:00:00 - 5:00:00 (today: 1hour, tomorrow: 5)
		4. time insensitive calculation. 
			- return rounded hour with 1 decimal place.  2.98 hour = 3hour
	use cases:
		1. support multiple working day
		2. working day calculation:  use before and after day to calculate 
		3. period 4 could be cover 2 working day

	- user story (simulating real world time punch flow)
		- given an employee
		- when employee go to punch the clock (ok, this is not real world flow, because we punch in and out at same time.)
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

	public function get_date(): string
	{
		return $this->date;
	}

	public function get_total(): int
	{
		return $this->total;
	}

	public function get_timeslot(): array
	{
		return $this->timeslot;
	}

	public function update_timeslot(int $hours, $total_seconds)
	{
		// if (array_key_exists($hours, $this->timeslot)) {
		// 	throw new \Exception ("invalid timeslot");
		// }
		$tmp = $this->timeslot[$hours] + $total_seconds;

		if ($tmp > 3600) {
			//1 hour
			throw new \Exception ("max seconds reached to this timesplot, something wrong.");
		}

		$this->timeslot[$hours] = $tmp;
	}

	public function update_working_time()
	{
		$timeslot = $this->timeslot;
		$this->total = array_sum($this->timeslot);
		// 3. working peroid hours for calculate working period 
		// 	- period1: 5:00:00 - 12:00:00 (7hours)
		// 	- period2: 12:00:00 - 18:00:00 (6hours)
		// 	- period3: 18:00:00 - 23:00:00 (5hours)
		// 	- period4: 23:00:00 - 5:00:00 (today: 1hour, tomorrow: 5)

		// period1: timeslot 5 - 11
		$this->period1 = array_sum(array_slice($timeslot, 5, 7));
		// period2: timeslot 12 - 17
		$this->period2 = array_sum(array_slice($timeslot, 12, 6));
		// period3: 18 - 21
		$this->period3 = array_sum(array_slice($timeslot, 18, 5));
		// period4: 23
		$this->period4 = array_sum(array_slice($timeslot, 23, 1));
		$this->period4 += array_sum(array_slice($timeslot, 0, 4));

	}

	public function to_array(): array
	{
		//TODO: shoud use interface to handle output format
		return [
			"date" => $this->date,
			"total" => Labour::seconds_to_hours($this->total),
			"labour_by_time_period" => [
				"period1" => Labour::seconds_to_hours($this->period1),
				"period2" => Labour::seconds_to_hours($this->period2),
				"period3" => Labour::seconds_to_hours($this->period3),
				"period4" => Labour::seconds_to_hours($this->period4),
			]
		];
	}

	//support hours only
	static function seconds_to_hours(int $secs)
	{
		return round($secs / 3600, 1);
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

	public function get_in_time():\DateTime
	{
		return $this->in;
	}

	public function get_out_time():\DateTime
	{
		return $this->out;
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

	public function update_labours(\API\DataSet\Labours $labours)
	{
		$this->labours = $labours;
	}

	public function to_array(): array
	{
		return [
			"employee_id" => $this->id,
			"first_name" => $this->first_name,
			"last_name" => $this->last_name,
			"labour" => $this->labours->to_array(),
		];
	}
}

namespace API\DataSet;
class Employees
{
	private $employees = [];
	public function add_employee(\Data\Employee $e)
	{
		if (!array_key_exists($e->get_id(), $this->employees)) {
			//new employee 
			$this->employees[$e->get_id()] = $e;
		}

	}

	public function get_employee(int $id):\Data\Employee
	{
		return $this->employees[$id];
	}

	public function update_employee(\Data\Employee $e)
	{
		//TODO: add validation
		$this->employees[$e->get_id()] = $e;
	}

	public function puch_clock(\Data\Clock $clock)
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
		$labours = $this->filling_working_seconds_to_timeslot($labours, $clock);
		$employee->update_labours($labours);
		$this->update_employee($employee);
	}

	private function filling_working_seconds_to_timeslot(\API\DataSet\Labours $labours, \Data\Clock $clock): \API\DataSet\Labours
	{
		//0-23 hours
		$timeslot = [];
		//filling working seconds to timeslot
		//interal hour between in and out 
		$in = $clock->get_in_time();
		$out = $clock->get_out_time();
		$inHourInt = intval($in->format("G")); //24hour format and cast to int type for comparison
		$outHourInt = intval($out->format("G"));
		$inDateString = $in->format("Y-m-d");
		$outDateString = $out->format("Y-m-d");
		$head = new \DateTime($in->format("Y-m-d H:00:00"));
		$tail = new \DateTime($out->format("Y-m-d H:00:00"));
		$tail->modify("+1 hour"); //need to add extra hour to tail for interation

		//interate hourly betwen in and out
		$hourly = new \DatePeriod($head, new \DateInterval("PT1H"), $tail);
		$tmpLabourDate = null; //a cache obj
		foreach($hourly as $timeslot) {
			$tmpDateString = $timeslot->format("Y-m-d");
			$tmpHourInt = intval($timeslot->format("G")); //24 hour format without leading 0

			//get labour cache obj
			if (!$tmpLabourDate || $tmpLabourDate->get_date() !== $tmpDateString) {
				//cache not found or different date found.
				$tmpLabourDate = $labours->get_labour_date($timeslot);
				if (!$tmpLabourDate) {
					//create new labour date
					$tmpLabourDate = $labours->update_labour_date(new \Data\Labour($timeslot));
				}
			}

			//calculate working time (seconds)
			$headDiff = $timeslot->diff($in);
			$tailDiff = $timeslot->diff($out);
			if ($inDateString == $tmpDateString && $inHourInt == $tmpHourInt) {
				//first hour, we need to substract diff
				$secs = 3600 - $headDiff->i * 60 + $headDiff->s;
			} elseif ($outDateString == $tmpDateString && $outHourInt == $tmpHourInt) {
				//last hour, we need to add diff
				$secs = $tailDiff->i * 60 + $tailDiff->s;
			} else {
				//middle hour, full hour working time
				$secs = 3600;
			}
				
			//update labour and dataset
			$tmpLabourDate->update_timeslot($tmpHourInt, $secs);
			$tmpLabourDate->update_working_time();
			$labours->update_labour_date($tmpLabourDate);

		}

		return $labours;
	}

	public function search(int $id): \Data\Employee|null
	{
		$employee = $this->get_employee($id);
		if (!$employee) {
			return null;
		}

		return $employee;
	}

	public function export_to_json($fileName, array $data)
	{
		//TODO: validation
		//it is ok to pretty print for interview test
		return file_put_contents($fileName, json_encode($data, JSON_PRETTY_PRINT));
	}

	public function get_all_employee()
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

		return $l;
	}


	public function get_labour_date(\DateTime $d)
	{
		$date = $d->format("Y-m-d"); 
		if (!array_key_exists($date, $this->labours)) {
			return null;
		}

		return $this->labours[$date];
	}

	public function to_array(): array
	{
		$tmp = [];
		foreach ($this->labours as $l) {
			$tmp[] = $l->to_array();
		}

		return $tmp;
	}

}

namespace Test;
class Test
{
	public $employeeDataSet;
	public function setup($fileName)
	{
		//TODO: validation
		$dataSource = json_decode(file_get_contents($fileName), true);
		#import data 
		$employeeDataSet = self::init_employees($dataSource['employees']);
		$employeeDataSet = self::init_clocks($employeeDataSet, $dataSource['clocks']);
		$this->employeeDataSet = $employeeDataSet;
	}

	private function init_employees(array $employees): \API\DataSet\Employees
	{
		$employeeDataSet = new \API\DataSet\Employees();
		foreach($employees as $e) {
			$tmpLabours = new \API\DataSet\Labours();  
			$tmpClocks = new \API\DataSet\Clocks();
			$tmpEmployee = new \Data\Employee($e["id"], $e["first_name"], $e["last_name"], $tmpClocks, $tmpLabours);
			$employeeDataSet->add_employee($tmpEmployee);
		}

		return $employeeDataSet;
	}

	private function init_clocks(\API\DataSet\Employees $employees, array $clocks): \API\DataSet\Employees
	{
		foreach($clocks as $c) {
			$in = new \DateTime($c["clock_in_datetime"]);
			$out = new \DateTime($c["clock_out_datetime"]);
			$tmpClock = new \Data\Clock($c["employee_id"], $in, $out);
			$employees->puch_clock($tmpClock);
		}

		return $employees;
	}
}

$test = new Test();
$test->setup("apertureLabsClocks.json");
$data = $test->employeeDataSet->search(0);
$test->employeeDataSet->export_to_json("labour_hours_employee_0.json", $data->to_array());
$data = $test->employeeDataSet->search(1);
$test->employeeDataSet->export_to_json("labour_hours_employee_1.json", $data->to_array());
$data = $test->employeeDataSet->search(2);
$test->employeeDataSet->export_to_json("labour_hours_employee_2.json", $data->to_array());
$data = $test->employeeDataSet->search(3);
$test->employeeDataSet->export_to_json("labour_hours_employee_3.json", $data->to_array());

exit;
//test case: peroid 4
$test->setup("test_case_period_4.json");
$data = $test->employeeDataSet->search(1);
$labours = $data->get_labours();

//check today's working time and timeslot
$today = new \DateTime("2023-06-16 00:00:00");
$date = $labours->get_labour_date($today);
$todayTimeslot = $date->get_timeslot();
if ($date->get_total() == 3600) {
	print_r("period4 - today total working time correct\n");
}
if ($todayTimeslot[22] == 0 && $todayTimeslot[23] == 3600) {
	print_r("period4 - today time slot correct\n");
}

//check tomorrow's working time and timeslot
$tomorrow = new \DateTime("2023-06-17 00:00:00");
$date = $labours->get_labour_date($tomorrow);
$todayTimeslot = $date->get_timeslot();
if ($date->get_total() == 3600 * 5) {
	print_r("period4 - tomorrow total working time correct\n");
}
if ($todayTimeslot[4] == 3600 && $todayTimeslot[5] == 0) {
	print_r("period4 - tomorrow time slot correct\n");
}

//test case period 1
$test->setup("test_case_period_1.json");
$data = $test->employeeDataSet->search(1);
$labours = $data->get_labours();
$today = new \DateTime("2023-06-16 00:00:00");
$date = $labours->get_labour_date($today);
$todayTimeslot = $date->get_timeslot();
if ($date->get_total() == 3600 * 7) {
	print_r("period1 - today total working time correct\n");
}
if ($todayTimeslot[5] == 3600 && $todayTimeslot[11] == 3600) {
	print_r("period1 - today time slot correct\n");
}