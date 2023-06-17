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
		3. period 4 cover 2 working day
*/
