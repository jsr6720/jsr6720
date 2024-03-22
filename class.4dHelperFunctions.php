<?php

/** 
 * Once upon a time I worked on a 4D backend/php frontend environement
 * These helper functions kept me sane. But I've added commentary in the 15 years since
 * 
 * created this gist on Oct 7, 2014
 * https://gist.github.com/jsr6720/348ff9107f5b3dd1d9e1
 * */

// set a default date, why not
date_default_timezone_set('America/New_York');
class HelperFunctions {
	
    // [new] commentary I guess I liked global variables at one point
	public $connect = null;
	public $soapConnect = null;
	public $odbc_string = null;
	public $production = false; // assume false
	public $last_error_message = '';
	public $record_locked = false;
	public $record_locked_by = '';
	
	public $suppress_email = false; // suppress e-mail, instead write to folder
	public $log_queries = false;
	
	/** one place to override any mirror settings */
	private $allow_mirror = true; // set to false to force all queries to 4D regardless of request
	private $mirror_odbc_resource = null;
	private $mirror_odbc_string = null;
	private $mirror_odbc_uid = null;
	private $mirror_odbc_pwd = null;
	
	function __construct() {
		// we define a production database connection by our local ip address, or computer name if running scripts locally
		$this->production = (($_SERVER["LOCAL_ADDR"] == "{ip}")
					|| ($_SERVER["COMPUTERNAME"] == "{computer-name}"));
		
        // [new] commentary this was the way to build connection strings to 4D and failover via Postgres. Same for WSDL File
		// initialize the $connect object
		if ($this->production) {
			$this->odbc_string = 'DRIVER={4D v13 ODBC Driver};SSL=false;SERVER=ip;PORT=19812;UID=user;PWD=password';
			$this->soapConnect = 'http://localhost:8080/4DWSDL';
			
			$this->mirror_odbc_string = 'DRIVER={PostgreSQL Unicode};SSL=false;SERVER=ip;PORT=5432;Database=db-name;Schema=public';
			$this->mirror_odbc_uid = 'user';
			$this->mirror_odbc_pwd = 'password';
		}
		else {
			$this->odbc_string = 'DRIVER={4D v13 ODBC Driver};SSL=false;SERVER=ip;PORT=19812;UID=user;PWD=password';
			$this->soapConnect = 'http://localhost:8080/4DWSDL';
			
			$this->mirror_odbc_string = 'DRIVER={PostgreSQL Unicode};SSL=false;SERVER=ip;PORT=5432;Database=db-name;Schema=public';
			$this->mirror_odbc_uid = 'user';
			$this->mirror_odbc_pwd = 'password';
			// disable mirror depending on data requirements
			//$this->allow_mirror = false;
		}
	}
	
	function get_allow_mirror() {
		return $this->allow_mirror;
	}
	
	public function newSoapClient(){
		$soap = new SoapClient($this->soapConnect);
		return $soap;
	}
	
	public function returnConnect(){
		
		if(!$this->valid_connection()) {
			$this->connect = odbc_connect($this->odbc_string,"","");
		}
		
		return $this->connect;
	}
	
	/** @return boolean whether or not the connection resource is valid */
	public function valid_connection() {
		return is_resource($this->connect);
	}
	
	public function get_mirror_odbc_resource(){
		
		if(!$this->valid_mirror_connection()) {
			$this->mirror_odbc_resource = odbc_connect($this->mirror_odbc_string,$this->mirror_odbc_uid,$this->mirror_odbc_pwd);
			if (!is_resource($this->mirror_odbc_resource))
				throw new Exception("Unable to connect to pgsql.");
		}
		
		return $this->mirror_odbc_resource;
	}
	
	/** @return boolean whether or not the connection resource is valid */
	public function valid_mirror_connection() {
		return is_resource($this->mirror_odbc_resource);
	}
	
	/**
	 * turn on debug messages.
	 * @param {boolean} $log_queries optional. default false. write out all queries to log file.
	*/
	public function debug($log_queries=false) {
		error_reporting(E_ALL);
		ini_set("display_errors", 1);
		
		$this->log_queries = $log_queries;
	}
	
	/** send out an e-mail. if it fails write to file
	  * @params mimics mail() command
	  * @return boolean true/false from mail() command
	  * */
	public function send_mail_helper($to, $subject, $body, $headers="") {
        // [new] commentary. yes lol. we used email alerts at this job
		$success = false; // assume false
		try {
			// ARE WE DEV OR PROD
			if (!($this->production)) {
				$subject = "DEV $subject";
				$body = "\n\n************* ALERT: Message sent from development server *************\n\n $body";
			}
			
			if($headers=="") { // setup default header block
				$headers = 'From: someone@example.com' . "\r\n" . // DO NOT INCLUDE name (see https://bugs.php.net/bug.php?id=28038)
				    'Reply-To: someone@example.com' . "\r\n" .
				    'X-Mailer: PHP/' . phpversion();
			}
			
			// mail out the results if we're not suppressing e-mails
			if ($this->suppress_email) {
				throw new Exception("Suppressing E-mail. writing error to file.");
			}
			else {
				$success = mail($to, $subject, $body, $headers);
				if ($success === false) {
					// if the above does not throw an exception and the e-mail was not accepted for deilvery
					throw new Exception("Failed to send e-mail");
				}
				
			}
			
		}
		catch(Exception $e) {
			// if we can't send mail. then at least write to file
			try{
				// log file is relative to inclusion of this file. This way we can have 
				// multiple projects with multiple log destinations (ie amfphp/services/logs and /schedule_list_views/csr/bulk_upload/logs)
				$unique_id = date('Ymd_Hi') . "_" . uniqid();
				$handle = fopen("./logs/{$unique_id}.mail.txt",'w');
				fwrite($handle, "To/Headers: {$to}\n {$headers} \n");
				fwrite($handle, "Subject: {$subject}\n\n");
				fwrite($handle, "Message: {$body}\n");
				fwrite($handle, "\n\n =========== EOF Email =========== \n\n");
				fwrite($handle, "\n\n =========== Exception =========== \n\n");
				
				$exceptionString = $e->getMessage() . 
							"\n\nCODE: " . $e->getCode() . 
							"\n\nWarning: file/line markers go to Database class. Refer to stack trace to find offending code." .
							"\n\nFILE: " . $e->getFile() . 
							"\n  LINE: " . $e->getLine() . 
							"\n\nTRACE: \n" . $e->getTraceAsString() . "\n";
				
				
				fwrite($handle, $exceptionString);
				fclose($handle);
			}
			catch(Exception $e) {
				// if we can't do this we have larger issues
                // [new] commentary but by all means don't do anything
			}
		}
		return $success; // return boolean of what mail returns.
	}
	
	function __destruct() {
		if ($this->valid_connection())
			odbc_close($this->connect);
		
		if ($this->valid_mirror_connection())
			odbc_close($this->mirror_odbc_resource);
		
		odbc_close_all();
	}
	
	function getLastError() {
		$error_message = '';
		
		if($this->valid_connection())
			$error_message .= "4D ODBC Error ID: ".odbc_error($this->connect)."\n" . "4D ODBC Error Message: ".odbc_errormsg($this->connect)."\n\n";
			
		if($this->valid_mirror_connection())
			$error_message .= "PgSQL ODBC Error ID: ".odbc_error($this->mirror_odbc_resource)."\n" . "PgSQL ODBC Error Message: ".odbc_errormsg($this->mirror_odbc_resource)."\n\n";
		
		return ($error_message);
	}
	
	/**
	 * NEW logging function to write sql to file. This is different than suppressing ERROR emails
	 *
	 * @param {datatype} $query 	sql statement. NOTE: I shove non-sql statements in here for non-query logging.
	 * @param {datatype} $result 	odbc_exec result object
	 *
	 * @return null
	 * */
	function log_query($query, $result) {
		try {
			/** no matter what action is called. we're going to log it */
			$ts = date('Y-m-d H:i:s'); // timestamp action
			
			$path = "logs/" . date("Y/m/d", strtotime($ts));
			
			$path_valid = true; // assume true
			if(!is_dir($path)){
				if(!mkdir($path,0777,true)){
					$path_valid = false;
				}
			}
			
			if ($path_valid) {
				// create a file based on current hour
				$fh = fopen("{$path}/".date('H00').".sql", 'a');
				
				// output to file as sql compliant (comments and semi-colons)
				$bytes = fwrite($fh, "\n\n-- {$ts} {$_SERVER['REMOTE_ADDR']}\n");
				$query = ($query == '') ? '-- $query blank' : "{$query};";
				$bytes = fwrite($fh, "{$query}\n-- \$result:{$result}\n-- odbc_num_rows:" . odbc_num_rows($result));
			}
		}
		catch (Exception $e) {
			// alert the developer
			$error = $e->getMessage() . 
				"\n\nCODE: " . $e->getCode() . 
				"\n\nFILE: " . $e->getFile() . 
				"\n  LINE: " . $e->getLine() . 
				"\n\nTRACE: \n" . $e->getTraceAsString() . "\n";
				
			$this->reportError($e->getMessage(), $error);
		}
	}
	
	/** Pass in a query string to get a result back
	 * @param $query {string} CRUD query string
	 * @param $mirorr {boolean} default false. use mirror as target
	 * 
	  * @returns record set
	  */
	function queryDatabase($query,$mirror=false) {
		// [new] commentary so if I remember correctly we had a pgsql mirrored schema but we could turn our queries to either env with this boolean flag
		try {
			// only process if query request mirror access and we're allowing it
			if ($mirror && ($this->allow_mirror)) {
				// lets try querying the mirror database first
				// need to replace [Field Name] with "field name"
				//preg_replace('/\[(.*?)\]/','"$1"',$query); // replaces without changing case
				// break it down '/    /' is the regex. we are searching for a special character square bracket so we escape it \[ \]
				// we want to get everything inside the square bracket, designated with (), into match[1] as match[0] is the whole match
				// we want everything (.*?) lazily, ie smallest block size possible. This will allow multiple replacements on one line
				$query = preg_replace_callback(
				        '/\[(.*?)\]/',
				        create_function(
				            // single quotes are essential here,
				            // or alternative escape all $ as \$
				            '$matches',
				            'return "\"".strtolower($matches[1])."\"";'
				        ),
				        $query
				    );
				
				$mirror_connect = $this->get_mirror_odbc_resource();
				
				if (is_resource($mirror_connect)) {
					$mirror_results = odbc_exec($mirror_connect,$query);
					
					if ($this->log_queries) { $this->log_query($query, $mirror_results); }
					
					if ($mirror_results !== false) // good job, not all bad sql generates exceptions, some drivers return false (different from no result)
						return ($mirror_results);
					else // or I could try hitting 4D
						return ($this->reportError("{$_SERVER["COMPUTERNAME"]} - ODBC Error - Postgres database", odbc_errormsg($mirror_connect) . " " . $_SERVER['PHP_SELF'], $query));
				}
				else 
					throw new Exception("ODBC could not connect to postgres ");
				
			}
			else {
				// query 4D directly
				$results = odbc_exec($this->returnConnect(),$query);
				if ($this->log_queries) { $this->log_query($query, $results); }
				
				if ($results !== false) // good job
					return ($results);
				else
					throw new Exception("odbc_exec returned false. failed to execute statement.");
			}
		}
		catch (Exception $e) {
			$exceptionString = $e->getMessage() . 
						"\n\nCODE: " . $e->getCode() . 
						"\n\nWarning: file/line markers go to Database class. Refer to stack trace to find offending code." .
						"\n\nFILE: " . $e->getFile() . 
						"\n  LINE: " . $e->getLine() . 
						"\n\nTRACE: \n" . $e->getTraceAsString() . "\n";
			
			$result = $this->reportError("{$_SERVER["COMPUTERNAME"]} - ODBC Error - Database", $exceptionString, $query);
			return(false);
		}
	}
	
	/** This function should be called from die statements to alert MIS department to their occurrences
	  *
	  * @param $odbcErrorId integer
	  * @param $odbcErrorMsg string
	  * @param $customErrorMsg string
	  * */
	function reportError($subject, $customErrorMsg, $query="") {
		
		$to = "someone@example.com";

		// see if it is an update statement
		$resultMsg="";
		if (strpos($query, "UPDATE") !== false) { // find if it is an UPDATE statement
			// get the where clause
			$whereClause = trim(substr(trim(stristr($query, "where")),5));
			$whereClause = str_replace("'","''",$whereClause);

			// get the table name (the '6' removes the 'UPDATE ' and adjusts the strpos number)
			$tableName = trim(substr($query,6,strpos($query, "SET")-6));

			$query_lock_test = "SELECT {fn RecordLocked('$tableName','$whereClause') as TEXT} FROM ODBCTest LIMIT 1";
			
			try {
				$resultSet = odbc_exec($this->connect, $query_lock_test);
				$tempArray = odbc_fetch_array($resultSet);
				$resultMsg = array_pop($tempArray);
				
				if ($resultMsg != "false") { // record locked
					$this->record_locked = true;
					$this->record_locked_by = $resultMsg;
				}
				else {
					$this->record_locked = false;
					$this->record_locked_by = '';
				}
			}
			catch (Exception $e) {
				$dummy = $e->getMessage() . 
					"\n\nCODE: " . $e->getCode() . 
					"\n\nFILE: " . $e->getFile() . 
					"\n  LINE: " . $e->getLine() . 
					"\n\nTRACE: \n" . $e->getTraceAsString() . "\n";
			}
            // [new] commentary, 4d had this nasty habit of "locking" records from a fat client terminal experience. This was to catch that
			if($resultMsg != "False") {
				// change the subject line
				$subject .= ": Locked Record";
			}
		}
		else {
			$resultMsg = "No update query found";
		}

		// set up variables for the message
		$phpErrorInfo = error_get_last();
		$timestamp = date('l jS \of F Y h:i:s A');
		$unique_id = uniqid();
		
		$postContents = var_export($_POST, true); // true back to variable instead of output
		$getContents = var_export($_GET, true); // true back to variable instead of output
		
		// append any odbc error messages
		$customErrorMsg .= $this->getLastError();
		// [new] commentary oh dear, what I was thinking.. in a word before datadog, sentry or splunk
		$message = "This is an auto generated message. Do not reply\n
					------------------------------------------------\n
					********** Error Message ************\n
					QUERY: $query \n
					Custom Error Message: $customErrorMsg\n
					********** PHP Message  ************\n
					This file is currently executing\n
					Relative Path: {$_SERVER['PHP_SELF']}\n
					Absolute Path: {$phpErrorInfo['file']}\n
					Line (accurate): {$phpErrorInfo['line']}\n
					Message: {$phpErrorInfo['message']}\n
					Type: {$phpErrorInfo['type']}\n [Lookup http://us2.php.net/manual/en/errorfunc.constants.php] \n\n
					Refered by: {$_SERVER['HTTP_REFERER']}\n
					\n\n
					********** POST/GET Contents  *******\n
					POST: $postContents\n
					GET: $getContents\n
					********** Locked Record   **********\n
					Update Statement used for processing: $query\n
					Result Message: $resultMsg\n
					\n
					********** Misc Information *********\n
					This message generated at: $timestamp\n
					on: {$_SERVER['SERVER_NAME']} / {$_SERVER["COMPUTERNAME"]} / {$_SERVER["LOCAL_ADDR"]} \n
					from: {$_SERVER['REMOTE_ADDR']}\n
					Unique id: $unique_id\n
					------------------------------------------------\n
					";
					
		// get rid of tabs used to make code look better
		$message = str_replace("\t","", $message);
		
		$this->last_error_message = $message;
		
		$this->send_mail_helper($to, $subject, $message);
		
		return $message;
	}
	
	function is_holiday($date) {
		// return if this is a holiday or not.
		// @TODO could go to database. Too much overhead. Just put here on

		// what are my holidays for this year
		$holidays = array("New Year's Day 2014" => "01/01/14",
					"Good Friday" => "04/18/14",
					"Memorial Day" => "05/26/14",
					"Independence Day" => "07/04/14",
					"Labor Day" => "09/01/14",
					"Thanksgiving Day" => "11/27/14",
					"Day After Thanksgiving Day" => "11/28/14",
					"Christmas Eve" => "12/24/14",
					"Christmas Day" => "12/25/14",
					"Floating Holiday" => "12/31/14",
					"New Year's Day 2015" => "01/01/15"
					);
		
		return(in_array(date('m/d/y', strtotime($date)),$holidays));
	}
	
	function is_weekend($date) {
		// return if this is a weekend date or not.
		return (date('N', strtotime($date)) >= 6);
	}
	
	function is_weekday($date) {
		// return if this is a weekend date or not.
		return (date('N', strtotime($date)) < 6);
	}
	
	/** Given a number days out, what day is that when counting by 'business' days
	  * get the next business day. by default it looks for next business day
	  * ie calling 	$date = get_next_busines_day(); on monday will return tuesday
	  * 			$date = get_next_busines_day(2); on monday will return wednesday
	  * 			$date = get_next_busines_day(2); on friday will return tuesday
	  *
	  * @param $number_of_business_days (integer)	 	how many business days out do you want
	  * @param $start_date (string) 				strtotime parseable time value
	  * @param $ignore_holidays (boolean)				true/false to ignore holidays
	  * @param $return_format (string)				as specified in php.net/date
	 */
	function get_next_business_day($number_of_business_days=1,$start_date='today',$ignore_holidays=false,$return_format='m/d/y') {
		
		// get the start date as a string to time
		$result = strtotime($start_date);
		$max_reps = abs($number_of_business_days) + 100;
		// now keep adding to today's date until number of business days is 0 and we land on a business day
		while ($number_of_business_days != 0 && $max_reps != 0) {
			// +/- one day to the start date
			$date_step = ($number_of_business_days <= 0) ? "-" : "+";
			
			$result = strtotime(date('Y-m-d',$result) . " {$date_step}1 day");
			
			// this day counts as a business day if it's a weekend and not a holiday, or if we choose to ignore holidays
			if ($this->is_weekday(date('Y-m-d',$result)) && (!($this->is_holiday(date('Y-m-d',$result))) || $ignore_holidays) ) 
				$number_of_business_days = ($date_step == "+") ? ($number_of_business_days - 1) : ($number_of_business_days + 1);
			
			$max_reps--;
			
		}
		
		// when my $number of business days is exausted I have my final date
		
		return(date($return_format,$result));
	}
	
	/** New function to return an array filled with valid date range
	 * Can count({array}) to get days in range
	 * @param {date} $start_date
	 * @param {date} $end_date
	 * @param {string} $format for passing to date() function
	 * @return {array} filled with dates in range
	 **/
	function get_date_range($start_date, $end_date, $format='m/d/Y') {
		
		$date_range[] = date($format, strtotime($start_date)); // always with a start date
		
		// get the delta
		$ts_delta = $this->date_delta($start_date, $end_date);
		
		// we only care about the days. start with 1 index
		for ($i = 1; $i <= $ts_delta["days"]; ++$i) {
			$date_range[] = date($format, strtotime("{$start_date} + {$i} days"));
		}
		
		return ($date_range);
	}
	
	/*
	*    This function figures out what fiscal year a specified date is in.
	*    @param {string} $inputDate - the date you wish to find the fiscal year for. defaults today. (12/4/08)
	*    @param {string} $format defaults to "m/d/Y" for return values
	*    @param {string} $fy_start M/d of fiscal calendar
	*    @param {string} $fy_end M/d of fiscal calendar
	*    
	*    @return {array} $fy["start"] - the month and day your fiscal year starts.
	*    			$fy["end"] - the month and day your fiscal an array
	*/
	function get_fiscal_date_range_for_date($date='today', $format="m/d/Y", $fy_start="12/01", $fy_end="11/30") {
		$date = strtotime($date);
		$input_year = intval(strftime('%Y',$date));
		
		if ($date >= strtotime($fy_start."/".$input_year)) {
			// i'm still in the same calendar year
			$fy_start_date = date($format, strtotime($fy_start."/".$input_year));
			$fy_end_date = date($format, strtotime($fy_end."/".($input_year + 1)));
		}
		else {
			// in the new calendar year
			$fy_start_date = date($format, strtotime($fy_start."/".($input_year -1)));
			$fy_end_date = date($format, strtotime($fy_end."/".$input_year));
		}
		
		$fy["start"] = $fy_start_date;
		$fy["end"] = $fy_end_date;
		
		return $fy;
	}
	
	/** given a time return A,B, or C shift
	 * @param $time format parsed by strtotime "H:i" required
	 *
	 * @return {string} IN ("A", "B", "C")
	 * */
	function get_shift($time) {
		$shift = "";
		
		if (strtotime($time) < strtotime("07:00:00") || strtotime($time) >= strtotime("23:00:00"))
			$shift = "C";
		else if (strtotime($time) >= strtotime("07:00:00") && strtotime($time) < strtotime("15:00:00"))
			$shift = "A";
		else if (strtotime($time) >= strtotime("15:00:00") && strtotime($time) < strtotime("23:00:00"))
			$shift = "B";
		else 
			$shift = "E"; // for error
			
		return $shift;
	}

	// [new] obligatory https://www.explainxkcd.com/wiki/index.php/2867:_DateTime
	/** cribbing php 5.3 date_diff() functionality
	  * @param $datetime1 strtotime recognizable value
	  * @param $datetime2 strtotime recognizable value
	  * 
	  * @returns  */
	function date_delta($datetime1,$datetime2) {
		// first calculate the delta
		$result["datetime1_milliseconds"] = strtotime($datetime1);
		$result["datetime2_milliseconds"] = strtotime($datetime2);
		
		$ts_delta = $result["datetime1_milliseconds"] - $result["datetime2_milliseconds"];
		
		$result = $this->ts_delta_format($ts_delta);
		
		return $result;
	}
	
	function ts_delta_format($ts_delta) {
		$result["ts_delta"] = $ts_delta;
		
		$ts_delta = abs($ts_delta); // positive values
		
		$result["seconds"] = $ts_delta % 60;
		
		$ts_delta /= 60;
		$result["minutes"] = $ts_delta % 60;
		
		$ts_delta /= 60;
		$result["hours"] = $ts_delta % 24;
		
		$ts_delta /= 24;
		$result["days"] = intval($ts_delta); // we only care about whole days
		
		$result["delta_text_all"] = "{$result["days"]} day(s) {$result["hours"]} hour(s) {$result["minutes"]} min(s)";
		$result["delta_text_days_hours"] = "{$result["days"]} day(s) {$result["hours"]} hour(s)";
		
		return $result;
	}
	
	/** just a helper function to format byte values */
	function format_bytes($bytes, $precision = 2) { 
	    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

	    $bytes = max($bytes, 0); 
	    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
	    $pow = min($pow, count($units) - 1); 

	    // Uncomment one of the following alternatives
	    $bytes /= pow(1024, $pow);
	    // $bytes /= (1 << (10 * $pow)); 

	    return round($bytes, $precision) . ' ' . $units[$pow]; 
	}
	
	/** just a for each to get out a single dimensional array from 
	  * a multi-dimensional array
	  * @param $source (multi-dimensional array)
	  * @param $key_name to aggregate
	  *
	  * @returns array $results
	  * */
	function multidim_array_extract($source, $key_name) {
		$results = array();
			
		foreach ($source as $index => $set) {
			if (isset($set[$key_name]))
				$results[$index] = $set[$key_name];
		}
		
		return $results;
	}
}?>