<?php 
//ini_set('memory_limit', '-1');

require_once("../../../includes/MPDF57/mpdf.php");
// require_once("../../../ratchet/bin/chat-server.php");

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use \Ratchet\Client as rClient;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\PHPMailerAutoload;
use PHPMailer\PHPMailer\Exception as MailerException;
//use Date;

class CustomFunctions {
	var $db, $_msg; 

	function __construct($db) {		
		$this->db = $db;	
	}

	public function getMsg(){
		return $this->_msg;
	}
	public function setMsg($msg){
		$this->_msg = $msg;
	}	
	public function print_to_Printer($text) {
		try {
			$printer_name = $this->get_global_variables('printer_name');
			$connector = new WindowsPrintConnector($printer_name);

			$printer = new Printer($connector);
			$printer->text($text);
			$printer->feed(2);
			$printer->cut();
			
			/* Close printer */
			$printer->close();
		} catch(Exception $e) {
			echo "Couldn't print to this printer: " . $e->getMessage() . "\n";
		}
	}
	public function sendMessage($msg = false){ # Execute Mysql Query
		$web_socket_url = $this->get_global_variables('websocket_url');
		/* $this->setMsg($msg); */
		rClient\connect($web_socket_url)->then(
			function($conn) {
		    	$conn->send($this->getMsg());
				$conn->close();
			}, function ($e) {
		    	/* echo "Could not connect: {$e->getMessage()}\n"; */
		});

	}
	public function insertNotification($message) {
		$sql = "INSERT INTO 
			notifications
			(
				notification_id,
				action,
				message,
				host,
				inserted_by,
				reservation_link,
				inserted_datetime,
				notification_type
			) 
			VALUES
			(
				:notification_id,
				:action,
				:message,
				:host,
				:inserted_by,
				:reservation_link,
				NOW() + INTERVAL :inserted_datetime HOUR,
				:notification_type
			)";
		$stmt = $this->db->prepare($sql);
		try {
			$stmt->execute(array(
				':notification_id' => NULL,
				':action' => $message['action'],
				':message' => $message['message'],
				':host' => $message['host'],
				':inserted_by' => $message['inserted_by'],
				':reservation_link' => $message['reservation_link'],
				':inserted_datetime' => C_DIFF_ORE,
				':notification_type' => $message['notification_type']
			));
			$resp = array( "status"=> "success", "message" => "notification inserted" );
			/* echo json_ecode($resp); */
		} catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			/* echo json_ecode($resp);
			http_response_code(500); */
		}
	}
	public function insertNotification_return_id($message) {
		$sql_notif = "INSERT INTO 
			notifications
			(
				notification_id,
				action,
				message,
				host,
				inserted_by,
				reservation_link,
				inserted_datetime,
				notification_type
			) 
			VALUES
			(
				:notification_id,
				:action,
				:message,
				:host,
				:inserted_by,
				:reservation_link,
				NOW() + INTERVAL :inserted_datetime HOUR,
				:notification_type
			)";
		try {
			$data_notif = array(
				':notification_id' => NULL,
				':action' => $message['action'],
				':message' => $message['message'],
				':host' => $message['host'],
				':inserted_by' => $message['inserted_by'],
				':reservation_link' => $message['reservation_link'],
				':inserted_datetime' => C_DIFF_ORE,
				':notification_type' => $message['notification_type']
			);
			$notif_id = $this->execute_insert_getId($sql_notif,$data_notif);
			return $notif_id;
			//$resp = array( "status"=> "success", "message" => "notification inserted" );
			/* echo json_ecode($resp); */
		} catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			/* echo json_ecode($resp);
			http_response_code(500); */
		}
	}
	public function getRoomName($room_id = 1) {
		$myDatabase = $this->db;
		
		$sql = "SELECT apartment_name FROM apartments WHERE apartment_id = :id";
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute(
			array(
				':id' => $room_id
			)
		);
		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $results[0]['apartment_name'];
	}
	public function getResIdResConnId($refnumber) {
		$myDatabase = $this->db;
		
		$sql = "SELECT a.refnumber, IFNULL(b.reservation_id, 0) AS reservation_id, IFNULL(b.reservation_conn_id, 0) AS reservation_conn_id FROM `bookings` a
				LEFT JOIN reservation b ON b.reservation_id = a.reservation_id 
				WHERE a.refnumber = :id";
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute(
			array(
				':id' => $refnumber
			)
		);
		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $results;
	}
	public function executeQuery($sql){ # Execute Mysql Query
		$myDatabase= $this->db;
	    $stmt = $myDatabase->prepare($sql);
	    $stmt->execute();
	    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $result;
	}

	public function getCurrent_Year(){
	    //$sql = "SELECT * FROM years WHERE status = 'current'";
	    try {
	        /*$result = $this->executeQuery($sql);
	        $cnt = count($result);
	        if($cnt > 0){
	            return $result[0]['years_id'];
	        }
	        else{
	            return 0;
	        }*/
	        $current_year = date("Y",(time() + (C_DIFF_ORE * 3600)));
			return $current_year;
	    } catch(PDOException $e) {
	        return 0;
	    }
	}

	public function getActive_Years(){
	    //$sql = "SELECT * FROM years WHERE status = 'current' or status = 'active'";
	    try {
	        /*$result = $this->executeQuery($sql);
	        $cnt = count($result);
	        if($cnt > 0){
	            return $result;
	        }
	        else{
	            return 0;
	        }*/
	        $sql = "SELECT (SELECT `start_date` FROM `periods` ORDER BY `periods_id` LIMIT 1) as first,
					   (SELECT `start_date` FROM `periods` ORDER BY `periods_id` DESC LIMIT 1) as last";
			$result1 = $this->executeQuery($sql);
			$first_date = $result1[0]["first"];
			$last_date = $result1[0]["last"];
			$year_start = date('Y', strtotime($first_date));
			$year_end = date('Y', strtotime($last_date));
			$current_year = date("Y",(time() + (C_DIFF_ORE * 3600)));
			$years;
			$cnt = $year_end - $year_start;
			$temp_year = $year_start;
			if($cnt > 0){
				for($x=0; $x<$cnt+1; $x++){
					$years[$x]["years_id"] = (string)$temp_year;
					$years[$x]["type_periods"] = "g";
					if($temp_year == $current_year){
						$years[$x]["status"] = "current";
					}
					else{
						$years[$x]["status"] = "active";
					}
					$temp_year++;
				}
			}else{
				$years[0]["years_id"] = $temp_year;
				$years[0]["type_periods"] = "g";
				if($temp_year == $current_year){
					$years[0]["status"] = "current";
				}
				else{
					$years[0]["status"] = "active";
				}
			}
			return $years;
	    } catch(PDOException $e) {
	        return 0;
	    }
	}

	public function get_currentdate_periodid($year){
	    $table_period = "periods";
	    $sql = "SELECT * FROM $table_period WHERE start_date = CAST(NOW() + INTERVAL ".C_DIFF_ORE ." HOUR AS date)";
	    $result = $this->executeQuery($sql);
	    return $result[0]['periods_id'];
	}

	public function get_currentdate($year){
		$table_period = "periods";
		$sql = "SELECT * FROM $table_period WHERE start_date = CAST(NOW() + INTERVAL ".C_DIFF_ORE ." HOUR AS date)";
		$result = $this->executeQuery($sql);
		return $result[0]['start_date'];
	}

	/*public function getIdperiod($date, $startorend){
		$table_periods = "";
		$active_years = $this->getActive_Years();
		$cnt = count($active_years);
		for($x=0; $x<$cnt; $x++){
			if($x == 0){
				$table_periods = "SELECT * FROM periods".$active_years[$x]['years_id'];
			}
			else{
				$table_periods .= " UNION SELECT * FROM periods".$active_years[$x]['years_id'];
			}
		}
		$sql = "SELECT table_periods.periods_id As periods_id FROM ($table_periods) As table_periods WHERE table_periods.$startorend = '$date'";
		try {
			$result = $this->executeQuery($sql);
			return $result[0]['periods_id'];
		} catch(PDOException $e) {
			return 0;
		}
	} */

	public function getIdperiod($date, $startorend){
		$table_periods = "periods";
		$sql = "SELECT table_periods.periods_id As periods_id FROM $table_periods As table_periods WHERE table_periods.$startorend = '$date'";
		try {
			$result = $this->executeQuery($sql);
			if(count($result) > 0 ){ return $result[0]['periods_id']; }
			else return 0;
			
		} catch(PDOException $e) {
			return 0;
		}
	}

	public function execute_update($sql){
		$myDatabase = $this->db;
		$myDatabase->query($sql);
	}

	public function execute_insert($sql, $data){
		$myDatabase = $this->db;
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute($data);
	}

	public function execute_insert_getId($sql, $data){
		$myDatabase = $this->db;
		$stmt 		= $myDatabase->prepare($sql);
		$stmt->execute($data);
		$result 	= $myDatabase->lastInsertId();
		return $result;
	}

	public function update_old_Rate($id_date_start, $id_date_end, $new_rate, $room_type_id, $room_rate_id){

		$sql = "SELECT * FROM room_rates WHERE room_type_id = $room_type_id and ((periods_id_start <= $id_date_start and periods_id_end >= $id_date_start) or (periods_id_start <= $id_date_end and periods_id_end >= $id_date_end)) and rate_type_id = 1 and status = 'active'";
		$result = $this->executeQuery($sql);
		$cnt = count($result);

		$active_years = $this->getActive_Years();
		$cnt1 = count($active_years);

		$table_periods = "";
		$sql = "SELECT * FROM room_types WHERE room_type_id = '$room_type_id'";
		$result1 = $this->executeQuery($sql);
		$rate = $result1[0]['associated_column'];
		for($x=0; $x<$cnt; $x++){
			$start_id = $result[$x]['periods_id_start'];
			$end_id = $result[$x]['periods_id_end'];
			$rate_orig = $result[$x]['rate'];
			if(($start_id <= $id_date_start && $end_id >= $id_date_start) && ($start_id <= $id_date_end && $end_id >= $id_date_end)){
				# $id_date_start to $id_date_end
				$table_periods = "periods";
				$sql = "UPDATE $table_periods SET $rate = '$rate_orig' WHERE periods_id >= $id_date_start and periods_id <= $id_date_end"; # Update Rate for the Created Columns
				$this->execute_update($sql);
			}
			else if(($start_id >= $id_date_start && $end_id >= $id_date_start) && ($start_id <= $id_date_end && $end_id >= $id_date_end)){
				# $start_id to $id_date_end
				$table_periods = "periods";
				$sql = "UPDATE $table_periods SET $rate = '$rate_orig' WHERE periods_id >= $start_id and periods_id <= $id_date_end"; # Update Rate for the Created Columns
				$this->execute_update($sql);
			}
			else if(($start_id <= $id_date_start && $end_id >= $id_date_start) && ($start_id <= $id_date_end && $end_id >= $id_date_end)){
				# $id_date_start to $end_id
				$table_periods = "periods";
				$sql = "UPDATE $table_periods SET $rate = '$rate_orig' WHERE periods_id >= $id_date_start and periods_id <= $end_id"; # Update Rate for the Created Columns
				$this->execute_update($sql);
			}
		} # for($x=0; $x<$cnt; $x++)
	}

	public function change_rates($req_date_start, $req_id_room_type, $req_new_rate){
		$table_periods = "periods";
		$active_years = $this->getActive_Years();
		$cnt = count($active_years);

		$sql = "SELECT table_periods.periods_id As periods_id FROM $table_periods As table_periods WHERE table_periods.start_date = '$req_date_start'";
		$result = $this->executeQuery($sql);
		if(count($result) > 0){
			$period_id_start = $result[0]['periods_id'];
			$sql = "SELECT * FROM room_rates WHERE room_type_id = '$req_id_room_type' and rate_type_id != 1 and status = 'active'";
			$result_room_rates = $this->executeQuery($sql);
			$cnt_result_room_rates = count($result_room_rates);
			$not_include = "";
			for($x=0; $x<$cnt_result_room_rates; $x++){
				$not_include .= "and !(periods_id >= ".$result_room_rates[$x]['periods_id_start']." and periods_id <= ".$result_room_rates[$x]['periods_id_end'].") ";
			}
			$table_periods = "";
			$sql = "SELECT * FROM room_types WHERE room_type_id = '$req_id_room_type'";
			$result1 = $this->executeQuery($sql);
			$rate = $result1[0]['associated_column'];

			$table_periods = "periods";
			$sql = "UPDATE $table_periods SET $rate = '$req_new_rate' WHERE periods_id >= $period_id_start $not_include"; # Update Rate for the Created Columns
			$this->execute_update($sql);

			$periods_id = $this->getActive_Period_id_start_end();
			$data = array(
				':room_type_id' => $req_id_room_type,
				':rate' => $req_new_rate,
				':available_to_web' => 0,
				':periods_id_start' => $period_id_start,
				':periods_id_end' => $periods_id['period_id_end'],
				':date_created' => C_DIFF_ORE,
				':date_last_mod' => C_DIFF_ORE,
				':rate_type_id' => 1,
				':status' => 'active'
			);

			$sql = "UPDATE room_rates SET status = 'inactive' WHERE room_type_id = '$req_id_room_type' and periods_id_start >= '$period_id_start' and periods_id_end >= '$period_id_start' and rate_type_id = 1 and status = 'active'"; # Update Rate for the Created Columns
			$this->execute_update($sql);
			$sql = "INSERT INTO room_rates(room_type_id, name, rate, available_to_web, periods_id_start, periods_id_end, date_created, date_last_mod, rate_type_id, status) VALUES (:room_type_id, NULL, :rate, :available_to_web, :periods_id_start, :periods_id_end, NOW() + INTERVAL :date_created HOUR, NOW() + INTERVAL :date_last_mod HOUR, :rate_type_id, :status)";
			$this->execute_insert($sql, $data);
			$period_id_end = ($period_id_start - 1);
			$sql = "UPDATE room_rates SET periods_id_end = '$period_id_end' WHERE room_type_id = '$req_id_room_type' and periods_id_start < '$period_id_end' and periods_id_end > '$period_id_end' and rate_type_id = 1 and status = 'active'"; # Update Rate for the Created Columns
			$this->execute_update($sql);
		} # if(count($result) > 0)
		return "successful";
	}

	
	public function getActive_Period_id_start_end(){
		$period_id_min = 0;
		$period_id_max = 0;
		try {
			$table_period = "periods";
			$sql = "SELECT MIN(periods_id) As periods_id FROM $table_period";
			$result1 = $this->executeQuery($sql);
			$period_id_min = $result1[0]['periods_id'];
		} catch(PDOException $e) {

		}
		try {
			$table_period = "periods";
			$sql = "SELECT MAX(periods_id) As periods_id FROM $table_period";
			$result1 = $this->executeQuery($sql);
			$period_id_max = $result1[0]['periods_id'];
		} catch(PDOException $e1) {

		}
		$result2 = array();
		$result2['period_id_start'] = $period_id_min;
		$result2['period_id_end'] = $period_id_max;

		return $result2;
	}
	
	/* housekeeping */
	public function cleaning_requirements() {
		$cleaning = array('deep_clean' => array(), 'regular_clean' => array(), 'checkout_clean' => array(), 'transfer_clean' => array());
		$sql = "SELECT * FROM hotel_cleaning";
		$stmt = $this->db->prepare($sql);
		try {
			$stmt->execute();
			$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		} catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			return $response->withJson( $resp )->withStatus(500);
		}
		foreach($results as $result) {
			if($result['hotel_cleaning_id'] == '1') {
				//Deep Clean
				array_push(
					$cleaning['deep_clean'],
					array(
						'id' => (int)$result['hotel_cleaning_id'],
						'interval' => (int)$result['uncleaneddays']
					)
				);
			}
			if($result['hotel_cleaning_id'] == '3') {
				//Regular Clean
				array_push(
					$cleaning['regular_clean'],
					array(
						'id' => (int)$result['hotel_cleaning_id'],
						'interval' => (int)$result['uncleaneddays']
					)
				);
			}
			if($result['hotel_cleaning_id'] == '4') {
				//Checkout Clean
				array_push(
					$cleaning['checkout_clean'],
					array(
						'id' => (int)$result['hotel_cleaning_id'],
						'interval' => (int)$result['uncleaneddays']
					)
				);
			}
			if($result['hotel_cleaning_id'] == '5') {
				//Transfer Clean
				array_push(
					$cleaning['transfer_clean'],
					array(
						'id' => (int)$result['hotel_cleaning_id'],
						'interval' => (int)$result['uncleaneddays']
					)
				);
			}
		}
		/* var_dump($cleaning); */	
		return $cleaning;
	}
	public function generateDeepCleaningFromTransfer($appartment_id, $start, $end, $cleaning_id, $interval) {
		$schedules = array();
		$begin = new DateTime($start);
		$begin = $begin->modify('+1 day');
		$end = new DateTime($end);
		$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
		$count = 0;
		foreach($periods as $period) {
			$count++;
			if($count == $interval) {
				$schedule_by_room = array(
					'hotel_cleaning_id' => $cleaning_id,
					'appartment_id' => $appartment_id,
					'hotel_cleaning_personel_id' => 0,
					'date' => $period->format("Y-m-d"),
					'year' => $period->format("Y"),
					'hotel_cleaning_status' => "Open"
				);
				array_push($schedules, $schedule_by_room);
				$count = 0;
			}
		}
		return $schedules;
	}
	public function deleteCleaningfromTransfer($appartment_id, $start, $end, $cleaning_id) {
		$sql = "DELETE FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND hotel_cleaning_id = :id";
		$stmt = $this->db->prepare($sql);
		$begin = new DateTime($start);
		$end = new DateTime($end);
		$end = $end->modify('+1 day');
		$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
		foreach($periods as $period) {
			/* echo $period->format('Y-m-d'); */
			try {
				$stmt->execute(
					array(
						':appartment_id' => $appartment_id,
						':date' => $period->format('Y-m-d'),
						':id' => $cleaning_id
					)
				);
			} catch(PDOException $e) {
				$resp = array( "status"=> "error", "message" => $e->getMessage() );
				return $response->withJson( $resp )->withStatus(500);
			}
		}
	}
	public function transferCleaningtoRoom($check_in, $check_out, $new_room_id, $old_room_id) {
		$sql = "UPDATE hotel_cleaning_schedule SET appartment_id = :new_id WHERE date = :date AND appartment_id = :old_room_id";
		$stmt = $this->db->prepare($sql);
		$begin = new DateTime($check_in);
		$end = new DateTime($check_out);
		$end = $end->modify('+1 day');
		$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
		foreach($periods as $period) { 
			/* echo $period->format('Y-m-d') . ' '; */
			if(!$this->existCleaning($period->format('Y-m-d'), $new_room_id)) {
				$stmt->execute(
					array(
						':old_room_id' => $old_room_id,
						':date' => $period->format('Y-m-d'),
						':new_id' => $new_room_id
					)
				);
				/* echo $period->format('Y-m-d') . ' false '; */
			} else {
				/* echo 'exist'; */
				/* echo $period->format('Y-m-d') . ' true '; */
			}
		}
	}
	public function existCleaning($date, $room_id) {
		$sql = "SELECT * FROM hotel_cleaning_schedule WHERE date = :date AND appartment_id = :room_id";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':date' => $date,
				':room_id' => $room_id
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		if(count($result) > 0) {
			return true;
		} else {
			return false;
		}
	}
	public function generateDeepCleaningFromExtendPeriod($deep_clean_schedule, $cleaning_id, $interval) {
		$schedules = array();
		foreach($deep_clean_schedule as $schedule) {
			$begin = new DateTime($schedule['check_in']);
			$end = new DateTime($schedule['check_out']);
			$end = $end->modify('+1 day');
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			$count = 0;
			foreach($periods as $period) {
				$count++;
				if($count == $interval) {
					$schedule_by_room = array(
						'hotel_cleaning_id' => $cleaning_id,
						'appartment_id' => $schedule['appartments_id'],
						'hotel_cleaning_personel_id' => 0,
						'date' => $period->format("Y-m-d"),
						'year' => $period->format("Y"),
						'hotel_cleaning_status' => "Open"
					);
					array_push($schedules, $schedule_by_room);
					$count = 0;
				}
			}
		}
		return $schedules;
	}
	public function generateCleaningFromExtendPeriod($reservation_data, $check_in, $check_out, $cleaning_id, $interval) {
		$sql = "SELECT * FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND hotel_cleaning_id = :id";
		$stmt = $this->db->prepare($sql);
		$schedules = array();
		foreach($reservation_data as $reservation) {
			if($reservation['Selected'] == 'true') {
				$apartment_id = $reservation['appartments_id'];
				$begin = new DateTime($check_in);
				$end = new DateTime($check_out);
				$end = $end->modify('+1 day');
				$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
				$count = 0;
				foreach($periods as $period) {
					$count++;
					if($count == $interval) {
						/* echo $period->format('Y-m-d') . '-'; */
						$stmt->execute(
							array(
								':appartment_id' => $apartment_id,
								':date' => $period->format('Y-m-d'),
								':id' => $cleaning_id
							)
						);
						$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
						if(count($result) > 0) {
							/* echo 'exist '; */
						} else {
							$schedule_by_room = array(
								'hotel_cleaning_id' => $cleaning_id,
								'appartment_id' => $apartment_id,
								'hotel_cleaning_personel_id' => 0,
								'date' => $period->format("Y-m-d"),
								'year' => $period->format("Y"),
								'hotel_cleaning_status' => "Open"
							);
							array_push($schedules, $schedule_by_room);
						}
						$count = 0;
					}
				}
			}
		}
		return $schedules;
	}
	public function deleteCleaningFromExtendPeriod($reservation_data, $check_in, $check_out, $cleaning_id) {
		$sql = "DELETE FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND hotel_cleaning_id = :id";
		$stmt = $this->db->prepare($sql);
		foreach($reservation_data as $reservation) {
			if($reservation['Selected'] == 'true') {
				$apartment_id = $reservation['appartments_id'];
				$begin = new DateTime($check_in);
				$end = new DateTime($check_out);
				$end = $end->modify('+1 day');
				$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
				foreach($periods as $period) {
					/* echo $period->format('Y-m-d'); */
					try {
						$stmt->execute(
							array(
								':appartment_id' => $apartment_id,
								':date' => $period->format('Y-m-d'),
								':id' => $cleaning_id
							)
						);
					} catch(PDOException $e) {
						$resp = array( "status"=> "error", "message" => $e->getMessage() );
						return $response->withJson( $resp )->withStatus(500);
					}
				}
			}
		}
		
	}
	public function cleanCleaning($schedule_details) {
		$sql = "DELETE FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND (hotel_cleaning_id = 1 OR hotel_cleaning_id = 3)";
		$stmt = $this->db->prepare($sql);
		foreach($schedule_details as $schedule) {
			$begin = new DateTime($schedule['new_checkin']);
			$end = new DateTime($schedule['new_checkout']);
			$end = $end->modify('+1 day');
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			foreach($periods as $period) {
				try {
					$stmt->execute(
						array(
							':appartment_id' => $schedule['appartment_id'],
							':date' => $period->format('Y-m-d')
						)
					);
				} catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage() );
					return $response->withJson( $resp )->withStatus(500);
				}
			}
		}
	}
	public function generateDeepCleaningSchedule($schedule_details, $interval) {
		$schedules = array();
		foreach($schedule_details as $detail) {
			$begin = new DateTime($detail['check_in']);
			$end = new DateTime($detail['check_out']);
			$end = $end->modify('+1 day');
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			$count = 0;
			foreach($periods as $period) {
				$count++;
				if($count == $interval) {
					$count = 0;
					if($period->format('Y-m-d') < Date('Y-m-d')) {
						$schedule_by_room = array(
							'hotel_cleaning_id' => 1,
							'appartment_id' => $detail['appartments_id'],
							'hotel_cleaning_personel_id' => 0,
							'date' => $period->format("Y-m-d"),
							'year' => $period->format("Y"),
							'hotel_cleaning_status' => "Done"
						);
					} else {
						$schedule_by_room = array(
							'hotel_cleaning_id' => 1,
							'appartment_id' => $detail['appartments_id'],
							'hotel_cleaning_personel_id' => 0,
							'date' => $period->format("Y-m-d"),
							'year' => $period->format("Y"),
							'hotel_cleaning_status' => "Open"
						);	
					}
					array_push($schedules, $schedule_by_room);
				}
			}
		}
		return $schedules;
	}
	public function generateRegularCleaningSchedule($schedule_details, $interval) {
		$schedules = array();
		foreach($schedule_details as $detail) {
			$begin = new DateTime($detail['new_checkin']);
			$end = new DateTime($detail['new_checkout']);
			$end = $end->modify('+1 day');
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			$count = 0;
			foreach($periods as $period) {
				$count++;
				if($count == $interval) {
					$count = 0;
					if($period->format('Y-m-d') < Date('Y-m-d')) {
						$schedule_by_room = array(
							'hotel_cleaning_id' => 3,
							'appartment_id' => $detail['appartment_id'],
							'hotel_cleaning_personel_id' => 0,
							'date' => $period->format("Y-m-d"),
							'year' => $period->format("Y"),
							'hotel_cleaning_status' => "Done"
						);
					} else {
						$schedule_by_room = array(
							'hotel_cleaning_id' => 3,
							'appartment_id' => $detail['appartment_id'],
							'hotel_cleaning_personel_id' => 0,
							'date' => $period->format("Y-m-d"),
							'year' => $period->format("Y"),
							'hotel_cleaning_status' => "Open"
						);	
					}
					array_push($schedules, $schedule_by_room);
				}
			}
		}
		return $schedules;
	}
	public function insertCleaningSchedule($schedules) {
		/* var_dump($schedules); */
		$sql = "INSERT INTO 
				hotel_cleaning_schedule
				(
					hotel_cleaning_schedule_id, 
					hotel_cleaning_id, 
					appartment_id, 
					hotel_cleaning_personel_id, 
					date, 
					year, 
					hotel_cleaning_status
				) 
				VALUES
				(
					:hotel_cleaning_schedule_id, 
					:hotel_cleaning_id, 
					:appartment_id, 
					:hotel_cleaning_personel_id, 
					:date, 
					:year, 
					:hotel_cleaning_status
				)";
		$stmt = $this->db->prepare($sql);
		foreach($schedules as $schedule) {
			try {
				$stmt->execute(
					array(
						':hotel_cleaning_schedule_id' => NULL,
						':hotel_cleaning_id' => $schedule['hotel_cleaning_id'],
						':appartment_id' => $schedule['appartment_id'],
						':hotel_cleaning_personel_id' => $schedule['hotel_cleaning_personel_id'],
						':date' => $schedule['date'],
						':year' => $schedule['year'],
						':hotel_cleaning_status' => $schedule['hotel_cleaning_status']
					)
				);
			} catch(PDOException $e) {
				$resp = array( "status"=> "error", "message" => $e->getMessage() );
				return $response->withJson( $resp )->withStatus(500);
			}
		}
		/* $resp = array( "status"=> "success", "message" => "Schedule Inserted" );
		return $response->withJson( $resp ); */
	}
	public function inReservation($period_id, $appartment_id, $start_or_end) {
		if($start_or_end == 'start') {
			$sql = "SELECT * FROM reservation WHERE `appartments_id` = :appartment_id AND `date_start_id` = :period_id AND `status` = 'active'";
		}
		if($start_or_end == 'end') {
			$sql = "SELECT * FROM reservation WHERE `appartments_id` = :appartment_id AND `date_end_id` = :period_id AND `status` = 'active'";
		}
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':appartment_id' => $appartment_id,
				':period_id' => $period_id
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		if(count($result) > 0) {
			return true;
		} else {
			return false;
		}
	}
	public function deepCleaningExist($date, $appartment_id) {
		$sql = "SELECT * FROM `hotel_cleaning_schedule` WHERE `appartment_id` = :appartment_id AND `date` = :date AND `hotel_cleaning_id` = 1";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':appartment_id' => $appartment_id,
				':date' => $date
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		if(count($result) > 0) {	
			return true;
		} else {
			return false;
		}
	}
	public function getPeriodID($date, $end_or_start) {
		if($end_or_start == "start") {
			$sql = "SELECT periods_id FROM periods WHERE start_date = :date";
		}
		if($end_or_start == "end") {
			$sql = "SELECT periods_id FROM periods WHERE end_date = :date";
		}
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':date' => $date
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		if(count($result) > 0) {	
			return $result[0]['periods_id'];
		} else {
		}
	}
	public function deleteExistingDeepCleaning($roomdata, $trigger = '') {
		$sql = "DELETE FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND hotel_cleaning_id = :cleaning_id";
		$stmt = $this->db->prepare($sql);
		foreach($roomdata as $room) {
			if($trigger == '') {
				$begin = new DateTime($room['start_date']);
				$begin = $begin->modify('+1 day');
				$end = new DateTime($room['end_date']);
				$end = $end->modify('+1 day');
				$appartment_id = $room['appartment_id'];
			}
			if($trigger == 'edit_period') {
				if($room['Selected'] == 'true') {
					$begin = new DateTime($room['check_in']);
					$end = new DateTime($room['check_out']);
					$end = $end->modify('+1 day');
					$appartment_id = $room['appartments_id'];
				} else {
					$begin = new DateTime($room['check_in']);
					$end = new DateTime($room['check_out']);
					$end = $end->modify('+1 day');
					$appartment_id = 0;
				}
			}
			if($trigger == 'edit_period_1') {
				/* echo $room['appartments_id'] . ' ' . $room['check_in'] . ' ' . $room['check_out'] . ' '; */
				$begin = new DateTime($room['check_in']);
				$end = new DateTime($room['check_out']);
				/* $end = $end->modify('+1 day'); */
				$appartment_id = $room['appartments_id'];
			}
			if($trigger == 'extend_period') {
				$begin = new DateTime($room['check_in']);
				$end = new DateTime($room['check_out']);
				$appartment_id = $room['appartments_id'];
			}
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			foreach($periods as $period) {
				try {
					$stmt->execute(
						array(
							':appartment_id' => $appartment_id,
							':date' => $period->format('Y-m-d'),
							':cleaning_id' => 1
						)
					);
				} catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage() );
					return $response->withJson( $resp )->withStatus(500);
				}
			}
		}
	}
	public function deleteExistingRegularCleaning($roomdata, $trigger = '') {
		$sql = "DELETE FROM hotel_cleaning_schedule WHERE appartment_id = :appartment_id AND date = :date AND hotel_cleaning_id = :cleaning_id";
		$stmt = $this->db->prepare($sql);
		foreach($roomdata as $room) {
			if($trigger == '') {
				$begin = new DateTime($room[0]['start_date']);
				$begin = $begin->modify('+1 day');
				$end = new DateTime($room[0]['end_date']);
				$end = $end->modify('+1 day');
				$apartment_id = $room[0]['appartments_id'];
			}
			if($trigger == 'edit_period') {
				if($room['Selected'] == 'true') {
					$begin = new DateTime($room['check_in']);
					$begin = $begin->modify('+1 day');
					$end = new DateTime($room['check_out']);
					$end = $end->modify('+1 day');
					$apartment_id = $room['appartments_id'];
				} else {
					$begin = new DateTime($room['check_in']);
					$begin = $begin->modify('+1 day');
					$end = new DateTime($room['check_out']);
					$end = $end->modify('+1 day');
					$apartment_id = 0;
				}
			}
			$periods = new DatePeriod($begin, new DateInterval('P1D'), $end);
			foreach($periods as $period) {
				//echo $period->format('Y-m-d') . ' ';
				try {
					$stmt->execute(
						array(
							':appartment_id' => $apartment_id,
							':date' => $period->format('Y-m-d'),
							':cleaning_id' => 3
						)
					);
				} catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage() );
					return $response->withJson( $resp )->withStatus(500);
				}
			}
		}
	}
	public function getReservationInfo($reservation_data) {
		$sql = "SELECT a.`appartments_id`, c.`start_date`, d.`end_date` FROM reservation a
			LEFT JOIN `reservation_conn` b ON a.`reservation_conn_id` = b.`reservation_conn_id`
			LEFT JOIN `periods` c ON b.`date_start_id` = c.`periods_id`
			LEFT JOIN `periods` d ON b.`date_end_id` = d.`periods_id`
			WHERE a.`reservation_id` = :reservation_id";
		$stmt = $this->db->prepare($sql);
		$roomdata = array();
		foreach($reservation_data as $room) {
			if($room['Selected'] == "true") {
				try {
					$stmt->execute(
						array(
							':reservation_id' => $room['reservation_id']
						)
					);
					$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
					array_push(
						$roomdata, 
						$result
					);
				} catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage() );
					return $response->withJson( $resp )->withStatus(500);
				}
				
			}
		}
		return $roomdata;
	}
	/* end of housekeeping */


	/* ------ START : RESERVATIONS LIST -------- */
	public function get_period_date($periods_id, $startorend){
		$table_periods = "periods";
		$sql = "SELECT * FROM $table_periods As table_periods WHERE table_periods.`periods_id` = '$periods_id'";
		try {
			$result = $this->executeQuery($sql);
			return $result[0][$startorend];
		} catch(PDOException $e) {
			return 0;
		}
	}
	/* ------ END : RESERVATIONS LIST ---------- */


	public function save_maintenance($reservation_data) {

		$mnt_sql = "INSERT INTO `maintenance` ( `maintenance_id`, `maintenance_name`, `hexcolor`, `repeat_days`, `status` )
					VALUES ( :maintenance_id, :maintenance_name, :hexcolor, :repeat_days, :status )
					ON DUPLICATE KEY UPDATE `maintenance_id` = :maintenance_id, `maintenance_name` = :maintenance_name, 
						`hexcolor` = :hexcolor, `repeat_days` = :repeat_days, `status` = :status";

		$mnt_stmt = $this->db->prepare($mnt_sql);
		$mnt_stmt->execute(
					array(
						":maintenance_id"		=> $data['maintenance_id'], 
						":maintenance_name"		=> $data['maintenance_name'], 
						":hexcolor"				=> $data['maintenance_hexcolor'], 
						":repeat_days"			=> $data['maintenance_repeat_days'], 
						":status"				=> 'active' 
					)
				);

		return $roomdata;
	}	
	
	public function auto_allocateRoom($room_type_id, $year, $checkin_id, $checkout_id){
		$result = 0;
		$get_rooms = '';
		
		$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
		$id_property = $getPropertyId[0]['property_id'];
		
		if($id_property == '19994'){
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' AND apartment_id != '16' AND apartment_id != '24' AND apartment_id != '26' AND apartment_id != '27' ORDER BY `apartments`.`priority` ASC");
		}else{
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' ORDER BY `apartments`.`priority` ASC");
		}

		$cnt = count($get_rooms);
		for($x=0; $x<$cnt; $x++){
			$idappartamenti = $get_rooms[$x]['apartment_id'];
			$is_available = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$idappartamenti' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))");
			$is_available_block = $this->executeQuery("SELECT * FROM blocking WHERE status = 'active' AND appartment_id = '$idappartamenti' AND 
									(((blocking_start_date_id <= '$checkin_id' AND blocking_end_date_id >= '$checkin_id') OR 
									(blocking_start_date_id <= '$checkout_id' AND blocking_end_date_id >= '$checkout_id')) OR 
									((blocking_start_date_id >= '$checkin_id' AND blocking_start_date_id <= '$checkout_id') OR 
									(blocking_end_date_id >= '$checkin_id' AND blocking_end_date_id <= '$checkout_id')))");
			if(count($is_available) == 0 && count($is_available_block) == 0){
				$result = $idappartamenti;
				break;
			}
		}
		return $result;
	}
	
	public function count_block_and_reservation($count_room_occupied, $date_id, $room_type_id) {
		$room_ids = $this->executeQuery("SELECT apartment_id, 0 as count FROM apartments WHERE roomtype_id = '$room_type_id'");
		$count_block_room = $this->executeQuery("SELECT * FROM blocking WHERE STATUS = 'active' AND appartment_id IN (SELECT apartment_id FROM apartments WHERE roomtype_id = '$room_type_id') AND 
						(((blocking_start_date_id <= '$date_id' AND blocking_end_date_id >= '$date_id') OR 
						(blocking_start_date_id <= '$date_id' AND blocking_end_date_id >= '$date_id')) OR 
						((blocking_start_date_id >= '$date_id' AND blocking_start_date_id <= '$date_id') OR 
						(blocking_end_date_id >= '$date_id' AND blocking_end_date_id <= '$date_id')))");
		$count = 0;
		for($y=0;$y<count($room_ids);$y++) {
			for($x=0;$x<count($count_room_occupied);$x++) {
				if($room_ids[$y]['apartment_id'] == $count_room_occupied[$x]['appartments_id'] && $room_ids[$y]['count'] == 0) {
					$room_ids[$y]['count']++;
				}
			}
			
			for($x=0;$x<count($count_block_room);$x++) {
				if($room_ids[$y]['apartment_id'] == $count_block_room[$x]['appartment_id'] && $room_ids[$y]['count'] == 0) {
					$room_ids[$y]['count']++;
				}
			}
		}
		
		$total = 0;
		foreach($room_ids as $room) {
			$total += $room['count'];
		}
		return $total;
	}
	
	public function check_conflict_reservation($block_id, $room_id, $start_date, $end_date, $id_property) {
		//balik diri
		$start_date_period_id = $this->getPeriodID($start_date, 'start');
		$end_date_period_id = $this->getPeriodID($end_date, 'end');
		
		$reservation_conflicts = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$room_id' AND 
								(((date_start_id <= '$start_date_period_id' and date_end_id >= '$start_date_period_id') OR 
								(date_start_id <= '$end_date_period_id' and date_end_id >= '$end_date_period_id')) OR 
								((date_start_id >= '$start_date_period_id' and date_start_id <= '$end_date_period_id') OR 
								(date_end_id >= '$start_date_period_id' and date_end_id <= '$end_date_period_id')))");
		$getOctoRoom = $this->executeQuery("SELECT `octorate_roomtype`.`idroomtype_octo` As octo_id, apartments.roomtype_id FROM `apartments` LEFT JOIN `octorate_roomtype` on `octorate_roomtype`.`roomtype_id` = `apartments`.`roomtype_id` WHERE `apartments`.`apartment_id` = '".$room_id."'");						
		/* HTL-766 */
		$max_room_request = $this->executeQuery("SELECT a.roomtype_id, COUNT(*) AS max_rooms, b.name FROM apartments a
							LEFT JOIN room_types b ON b.room_type_id = a.roomtype_id
							GROUP BY a.roomtype_id");
							
		foreach($max_room_request as $max_room) {
			if($max_room['roomtype_id'] == $getOctoRoom[0]["roomtype_id"]) {
				$max_rooms = $max_room['max_rooms'];
			}
		}
		/* HTL-766 */
		
		foreach($reservation_conflicts as $record) {
			$blocked_period = $this->get_blocked_period($room_id, $record['date_start_id'], $record['date_end_id']);
			for($x=0; $x<count($blocked_period); $x++){
				# execute : incrementAvailability($relate_id,$property_id,$checkinDecr,$checkoutDecr)
				$room_id_octo = $getOctoRoom[0]["octo_id"];
				$room_type_id = $getOctoRoom[0]['roomtype_id'];
				foreach($max_room_request as $max_room) {
					if($max_room['roomtype_id'] == $room_type_id) {
						$max_rooms = $max_room['max_rooms'];
					}
				}
				/* $date_start = $this->get_period_date($blocked_period[$x]['date_start_id'], 'start_date');
				$date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date');
				$this->incrementAvailability($room_id_octo, $id_property, $date_start, $date_end); */
				for($y=$blocked_period[$x]['date_start_id']; $y<=$blocked_period[$x]['date_end_id']; $y++) {
					$count_room_occupied = $this->executeQuery("SELECT * FROM reservation WHERE STATUS = 'active' AND appartments_id IN (SELECT apartment_id FROM apartments WHERE roomtype_id = '$room_type_id') AND 
											(((date_start_id <= '$y' AND date_end_id >= '$y') OR 
											(date_start_id <= '$y' AND date_end_id >= '$y')) OR 
											((date_start_id >= '$y' AND date_start_id <= '$y') OR 
											(date_end_id >= '$y' AND date_end_id <= '$y')))");
					if($max_rooms != count($count_room_occupied)) {
						$date_start = $this->get_period_date($y, 'start_date');
						$count_room_occupied_1 = $this->executeQuery("SELECT * FROM reservation WHERE STATUS = 'active' AND appartments_id ='$room_id' AND 
											(((date_start_id <= '$y' AND date_end_id >= '$y') OR 
											(date_start_id <= '$y' AND date_end_id >= '$y')) OR 
											((date_start_id >= '$y' AND date_start_id <= '$y') OR 
											(date_end_id >= '$y' AND date_end_id <= '$y')))");
						/* $date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date'); */
						if(count($count_room_occupied_1) > 0) {
							//count block rooms and reservation if not exceeded to max rooms
							$count = $this->count_block_and_reservation($count_room_occupied, $y, $room_type_id);
				
							if($max_rooms != $count) {
								$this->incrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
							}
							else {
								$this->decrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
							}
						}
						else {
							$this->decrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
						}
					}
				}
			}
		}
	}
	/* HTL-766 */
	public function htl_increment($date_id, $room_id, $roomtypeId, $room_id_octo, $id_property, $max_rooms) {
		$count_room_occupied = $this->executeQuery("SELECT * FROM reservation WHERE STATUS = 'active' AND appartments_id IN (SELECT apartment_id FROM apartments WHERE roomtype_id = '$roomtypeId') AND 
											(((date_start_id <= '$date_id' AND date_end_id >= '$date_id') OR 
											(date_start_id <= '$date_id' AND date_end_id >= '$date_id')) OR 
											((date_start_id >= '$date_id' AND date_start_id <= '$date_id') OR 
											(date_end_id >= '$date_id' AND date_end_id <= '$date_id')))");
		if($max_rooms != count($count_room_occupied)) {
			$date_start = $this->get_period_date($date_id, 'start_date');
			$count_room_occupied_1 = $this->executeQuery("SELECT * FROM reservation WHERE STATUS = 'active' AND appartments_id ='$room_id' AND 
								(((date_start_id <= '$date_id' AND date_end_id >= '$date_id') OR 
								(date_start_id <= '$date_id' AND date_end_id >= '$date_id')) OR 
								((date_start_id >= '$date_id' AND date_start_id <= '$date_id') OR 
								(date_end_id >= '$date_id' AND date_end_id <= '$date_id')))");
			/* $date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date'); */
			if(count($count_room_occupied_1) > 0) {
				//count block rooms and reservation if not exceeded to max rooms
				$count = $this->count_block_and_reservation($count_room_occupied, $date_id, $roomtypeId);
	
				/* if($max_rooms != $count) {
					//echo $max_rooms . ' ' . $count . ' ';
					$this->incrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
				}
				else {
					$this->decrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
				} */
				$this->setAvailability($room_id_octo, $id_property, $date_start, $date_start, ($max_rooms-$count));
				
			}
			else {
				$this->decrementAvailability($room_id_octo, $id_property, $date_start, $date_start);
			}
		}
		/* return $count_room_occupied; */
	}
	/* HTL-766 */
	public function setAvailability($room_id_octo, $id_property, $date_start, $date_end, $unit) {
		$url = "http://octorate.com/api/live/callApi.php?method=UpdateAvailbb";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>
					  <SetAllocation>
					   <Auth>
						<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>
						  <PropertyId>' . $id_property . '</PropertyId>
					   </Auth>
					   <UpdateMethod>availbb</UpdateMethod>
					   <Channels>
						  <Channel>0</Channel>
					   </Channels>
					   <DateRange>
						  <StartDate>' . $date_start . '</StartDate>
						  <EndDate>' . $date_end . '</EndDate>
					   </DateRange>
					   <Allocations>
						  <Allocation>
							 <RoomTypeId>' . $room_id_octo . '</RoomTypeId>
							 <Units>' . $unit . '</Units>
						</Allocation>
					   </Allocations>
					 </SetAllocation>';
					 
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		$array_data = json_decode(json_encode(simplexml_load_string($data, null, LIBXML_NOCDATA)), true);
	}
  public function check_conflict_reservation_modify_blocking($block_id, $room_id, $start_date, $end_date, $id_property, $action) {
		//balik diri
		$start_date_period_id = $this->getPeriodID($start_date, 'start');
		$end_date_period_id = $this->getPeriodID($end_date, 'end');
		   
		$reservation_conflicts = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$room_id' AND 
								(((date_start_id <= '$start_date_period_id' and date_end_id >= '$start_date_period_id') OR 
								(date_start_id <= '$end_date_period_id' and date_end_id >= '$end_date_period_id')) OR 
								((date_start_id >= '$start_date_period_id' and date_start_id <= '$end_date_period_id') OR 
								(date_end_id >= '$start_date_period_id' and date_end_id <= '$end_date_period_id')))");
		$getOctoRoom = $this->executeQuery("SELECT `octorate_roomtype`.`idroomtype_octo` As octo_id FROM `apartments` LEFT JOIN `octorate_roomtype` on `octorate_roomtype`.`roomtype_id` = `apartments`.`roomtype_id` WHERE `apartments`.`apartment_id` = '".$room_id."'");						
		foreach($reservation_conflicts as $record) {
   
      if($record['date_start_id'] < $start_date_period_id) {
        $start_date_id = $start_date_period_id;
      } else {
        $start_date_id = $record['date_start_id'];
      }
      
      if($record['date_end_id'] < $end_date_period_id) {
        $end_date_id = $record['date_end_id'];
      } else {
        $end_date_id = $end_date_period_id;
      }
      
      if($action == 'increment') {
			$blocked_period = $this->get_blocked_period($room_id, $start_date_id, $end_date_id);
      } else {
			$blocked_period = $this->get_reservation_period($room_id, $start_date_id, $end_date_id);
      }
			for($x=0; $x<count($blocked_period); $x++){
				# execute : incrementAvailability($relate_id,$property_id,$checkinDecr,$checkoutDecr)
				$room_id_octo = $getOctoRoom[0]["octo_id"];
				$date_start = $this->get_period_date($blocked_period[$x]['date_start_id'], 'start_date');
				$date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date');
        
        if($action == 'increment') {
          $this->incrementAvailability($room_id_octo, $id_property, $date_start, $date_end);
        } else {
          $this->decrementAvailability($room_id_octo, $id_property, $date_start, $date_end);
        }
			}
		}
    return $blocked_period;
	}
	
	public function randomString($length = 4) {
		$str = "";
		$characters = array_merge(range('a','z'));
		$max = count($characters) - 1;
		for ($i = 0; $i < $length; $i++) {
			$rand = mt_rand(0, $max);
			$str .= $characters[$rand];
		}
		return $str;
	}

	/* start of reservation calendar */
	public function get_calendardata($raw_Periods, $data, $section){ # CHANGE RATES
		$raw_Reservations;
		if($section == "Monthly"){
			$raw_Reservations = $this->get_calendar_reservations($data["year"], $data["month"]);
		}
		else if($section == "Weekly"){
			$raw_Reservations = $this->get_calendar_reservations_weekly($data["date_range"]);
		}
		else if($section == "Daily"){
			$raw_Reservations = $this->get_calendar_reservations_weekly($data["date_range"]);
		}
		
		$raw_Rooms = $this->get_calendar_rooms();

		$cnt_rooms = count($raw_Rooms);
        $cnt_periods = count($raw_Periods);
        $cnt_reservations = count($raw_Reservations);

        if($cnt_periods == 0) return '';

        $Period_id_start = $raw_Periods[0]['periods_id'];
        $Period_id_end = $raw_Periods[$cnt_periods-1]['periods_id'];


        $final_data;
        $final_data_indx = 0;

        for($indx=0; $indx<$cnt_rooms; $indx++){
            $rooms_id = $raw_Rooms[$indx]['apartment_id'];
            $rooms_name = $raw_Rooms[$indx]['apartment_name'];
            for($indx1=0; $indx1<$cnt_periods; $indx1++){

                $tempPeriod_id = $raw_Periods[$indx1]['periods_id'];
                $reservation_id = "";
                $reservation_conn_id = "";
                $apartment_id = $rooms_id;
                $date_start_id = "";
                $date_end_id = "";
                $colspan = 2;
                $client_id = "";
                $client_surname = "";
                $client_name = "";
                $status = "";
                $status_color = "#cccccc";
                $status_startend = "";
                $start_date = "";
                $end_date = "";
                $period_start = $raw_Periods[$indx1]['start_date'];
                $period_end = $raw_Periods[$indx1]['end_date'];
                $reference_num = "";
                $booking_source = "";
                $stay_status = "";
                $period_id_start = "";

                $hasdata = 0;
                for($indx2=0; $indx2<$cnt_reservations; $indx2++){
                	
                    $checkin_date_id = $raw_Reservations[$indx2]['date_start_id'];
                    $checkout_date_id = $raw_Reservations[$indx2]['date_end_id'];
                    if($raw_Reservations[$indx2]['appartments_id'] == $rooms_id){
                    	//echo $rooms_name.": ".$checkin_date_id." - ".$checkout_date_id." = ".$Period_id_start." - ".$Period_id_end."<br>";
                    	if($checkin_date_id == $tempPeriod_id){ // if checkin date is equal to current selected date
	                        if($checkout_date_id <= $Period_id_end){
	                            // if checkout date is less than the end date

	                            $discount = 0;
	                            if($raw_Reservations[$indx2]['discount'] != null  &&
	                            	$raw_Reservations[$indx2]['discount'] != ''){ 
	                            	$discount = $raw_Reservations[$indx2]['discount']; 
	                        	}
	                            $totdays = ($checkout_date_id - $checkin_date_id) + 1;
	                            
	                            $reservation_id = $raw_Reservations[$indx2]['reservation_id'];
	                            $reservation_conn_id = $raw_Reservations[$indx2]['reservation_conn_id'];
	                            $date_start_id = $checkin_date_id;
				                $date_end_id = $checkout_date_id;
				                $colspan = $totdays*2;
				                $client_id = $raw_Reservations[$indx2]['clients_id'];
				                $client_surname = $raw_Reservations[$indx2]['client_surname'];
                				$client_name = $raw_Reservations[$indx2]['client_name'];
                				$deposit = $raw_Reservations[$indx2]['deposit'];
                				$rate_total = $raw_Reservations[$indx2]['rate_total'] - $discount;
                				$paid = $raw_Reservations[$indx2]['paid'];
                				$confirmation = $raw_Reservations[$indx2]['confirmation'];
                				$status_startend = "";
                				$start_date = $raw_Reservations[$indx2]['start_date_date'];
                				$end_date = $raw_Reservations[$indx2]['end_date_date'];
                				$reference_num = $raw_Reservations[$indx2]['reference_num'];
                				$booking_source = $raw_Reservations[$indx2]['booking_source_name'];
                				$period_id_start = $checkin_date_id;

                				if($deposit == null || $deposit == ''){ $deposit = 0; }
                				if($rate_total == null || $rate_total == ''){ $rate_total = 0; }
                				if($paid == null || $paid == ''){ $paid = 0; }

                				if($paid >= $rate_total){
                					# paid
                					$status = "All Paid";
                					$status_color = "#1762d4";
                				}
                				else if($paid >= $deposit){
                					# deposit paid
                					$status = "Deposit paid";
                					$status_color = "#FFD800";
                				}
                				else if($paid < $deposit && $confirmation == 'S'){
                					# Reservation confirmed, Deposit not paid
                					$status = "Reservation confirmed, Deposit not paid";
                					$status_color = "#FF9900";
                				}
                				else if($confirmation == 'N' || $confirmation != 'S'){
                					# Reservation not confirmed
                					$status = "Reservation not confirmed";
                					$status_color = "#FF3115";
                				}
                				if($raw_Reservations[$indx2]['checkin'] != null && $raw_Reservations[$indx2]['checkin'] != ''){
                					if($raw_Reservations[$indx2]['checkout'] != null && $raw_Reservations[$indx2]['checkout'] != ''){
                						$stay_status = "checked_out";
                					}
                					else{
                						$stay_status = "checked_in";
                					}
                				}
                				else{
                					$stay_status = "";
                				}

				                $indx1 = $indx1 + ($checkout_date_id - $checkin_date_id);
				                //$period_start = $raw_Periods[$indx1]['start_date'];
                				$period_end = $raw_Periods[$indx1]['end_date'];
	                            $hasdata = 1;
	                            break;
	                        }else{
	                            // if checkout date is greater than the end date

	                            $discount = 0;
	                            if($raw_Reservations[$indx2]['discount'] != null  &&
	                            	$raw_Reservations[$indx2]['discount'] != ''){ 
	                            	$discount = $raw_Reservations[$indx2]['discount']; 
	                        	}
	                            $totdays = ($Period_id_end - $checkin_date_id) + 1;
	                            
	                            $reservation_id = $raw_Reservations[$indx2]['reservation_id'];
	                            $reservation_conn_id = $raw_Reservations[$indx2]['reservation_conn_id'];
	                            $date_start_id = $checkin_date_id;
				                $date_end_id = $checkout_date_id;
				                $colspan = $totdays*2;
				                $client_id = $raw_Reservations[$indx2]['clients_id'];
				                $client_surname = $raw_Reservations[$indx2]['client_surname'];
                				$client_name = $raw_Reservations[$indx2]['client_name'];
                				$deposit = $raw_Reservations[$indx2]['deposit'];
                				$rate_total = $raw_Reservations[$indx2]['rate_total'] - $discount;
                				$paid = $raw_Reservations[$indx2]['paid'];
                				$confirmation = $raw_Reservations[$indx2]['confirmation'];
                				$status_startend = "border-right: 1px dashed red !important;";
                				$start_date = $raw_Reservations[$indx2]['start_date_date'];
                				$end_date = $raw_Reservations[$indx2]['end_date_date'];
                				$reference_num = $raw_Reservations[$indx2]['reference_num'];
                				$booking_source = $raw_Reservations[$indx2]['booking_source_name'];
                				$period_id_start = $checkin_date_id;

                				if($deposit == null || $deposit == ''){ $deposit = 0; }
                				if($rate_total == null || $rate_total == ''){ $rate_total = 0; }
                				if($paid == null || $paid == ''){ $paid = 0; }

                				if($paid >= $rate_total){
                					# paid
                					$status = "All Paid";
                					$status_color = "#1762d4";
                				}
                				else if($paid >= $deposit){
                					# deposit paid
                					$status = "Deposit paid";
                					$status_color = "#FFD800";
                				}
                				else if($paid < $deposit && $confirmation == 'S'){
                					# Reservation confirmed, Deposit not paid
                					$status = "Reservation confirmed, Deposit not paid";
                					$status_color = "#FF9900";
                				}
                				else if($confirmation == 'N' || $confirmation != 'S'){
                					# Reservation not confirmed
                					$status = "Reservation not confirmed";
                					$status_color = "#FF3115";
                				}
                				if($raw_Reservations[$indx2]['checkin'] != null && $raw_Reservations[$indx2]['checkin'] != ''){
                					if($raw_Reservations[$indx2]['checkout'] != null && $raw_Reservations[$indx2]['checkout'] != ''){
                						$stay_status = "checked_out";
                					}
                					else{
                						$stay_status = "checked_in";
                					}
                				}
                				else{
                					$stay_status = "";
                				}

				                $indx1 = $indx1 + ($Period_id_end - $checkin_date_id);
				                //$period_start = $raw_Periods[$indx1]['start_date'];
                				$period_end = $raw_Periods[$indx1]['end_date'];
				                //echo $indx1."yohoo<br>";
	                            $hasdata = 1;
	                            break;
	                        }
	                    }
	                    else if($checkin_date_id < $Period_id_start && $checkout_date_id >= $tempPeriod_id){ // if checkin date is less than the start date
	                        if($checkout_date_id < $Period_id_end){
	                            $discount = 0;
	                            if($raw_Reservations[$indx2]['discount'] != null  &&
	                            	$raw_Reservations[$indx2]['discount'] != ''){ 
	                            	$discount = $raw_Reservations[$indx2]['discount']; 
	                        	}
	                            // if checkout date is less than the end date
	                            $totdays = ($checkout_date_id - $Period_id_start) + 1;
	                            
	                            $reservation_id = $raw_Reservations[$indx2]['reservation_id'];
	                            $reservation_conn_id = $raw_Reservations[$indx2]['reservation_conn_id'];
	                            $date_start_id = $checkin_date_id;
				                $date_end_id = $checkout_date_id;
				                $colspan = $totdays*2;
				                $client_id = $raw_Reservations[$indx2]['clients_id'];
				                $client_surname = $raw_Reservations[$indx2]['client_surname'];
                				$client_name = $raw_Reservations[$indx2]['client_name'];
                				$deposit = $raw_Reservations[$indx2]['deposit'];
                				$rate_total = $raw_Reservations[$indx2]['rate_total'] - $discount;
                				$paid = $raw_Reservations[$indx2]['paid'];
                				$confirmation = $raw_Reservations[$indx2]['confirmation'];
                				$status_startend = "border-left: 1px dashed red !important;";
                				$start_date = $raw_Reservations[$indx2]['start_date_date'];
                				$end_date = $raw_Reservations[$indx2]['end_date_date'];
                				$reference_num = $raw_Reservations[$indx2]['reference_num'];
                				$booking_source = $raw_Reservations[$indx2]['booking_source_name'];
                				$period_id_start = $Period_id_start;

                				if($deposit == null || $deposit == ''){ $deposit = 0; }
                				if($rate_total == null || $rate_total == ''){ $rate_total = 0; }
                				if($paid == null || $paid == ''){ $paid = 0; }

                				if($paid >= $rate_total){
                					# paid
                					$status = "All Paid";
                					$status_color = "#1762d4";
                				}
                				else if($paid >= $deposit){
                					# deposit paid
                					$status = "Deposit paid";
                					$status_color = "#FFD800";
                				}
                				else if($paid < $deposit && $confirmation == 'S'){
                					# Reservation confirmed, Deposit not paid
                					$status = "Reservation confirmed, Deposit not paid";
                					$status_color = "#FF9900";
                				}
                				else if($confirmation == 'N' || $confirmation != 'S'){
                					# Reservation not confirmed
                					$status = "Reservation not confirmed";
                					$status_color = "#FF3115";
                				}
                				if($raw_Reservations[$indx2]['checkin'] != null && $raw_Reservations[$indx2]['checkin'] != ''){
                					if($raw_Reservations[$indx2]['checkout'] != null && $raw_Reservations[$indx2]['checkout'] != ''){
                						$stay_status = "checked_out";
                					}
                					else{
                						$stay_status = "checked_in";
                					}
                				}
                				else{
                					$stay_status = "";
                				}

				                $indx1 = $indx1 + ($checkout_date_id - $Period_id_start);
				                //$period_start = $raw_Periods[$indx1]['start_date'];
                				$period_end = $raw_Periods[$indx1]['end_date'];
				                //echo $indx1."yohoo<br>";
	                            $hasdata = 1;
	                            break;
	                        }else{
	                            // if checkout date is greater than the end date
	                            $discount = 0;
	                            if($raw_Reservations[$indx2]['discount'] != null  &&
	                            	$raw_Reservations[$indx2]['discount'] != ''){ 
	                            	$discount = $raw_Reservations[$indx2]['discount']; 
	                        	}
	                            $totdays = ($Period_id_end - $Period_id_start) + 1;
	                            
	                            $reservation_id = $raw_Reservations[$indx2]['reservation_id'];
	                            $reservation_conn_id = $raw_Reservations[$indx2]['reservation_conn_id'];
	                            $date_start_id = $checkin_date_id;
				                $date_end_id = $checkout_date_id;
				                $colspan = $totdays*2;
				                $client_id = $raw_Reservations[$indx2]['clients_id'];
				                $client_surname = $raw_Reservations[$indx2]['client_surname'];
                				$client_name = $raw_Reservations[$indx2]['client_name'];
                				$deposit = $raw_Reservations[$indx2]['deposit'];
                				$rate_total = $raw_Reservations[$indx2]['rate_total'] - $discount;
                				$paid = $raw_Reservations[$indx2]['paid'];
                				$confirmation = $raw_Reservations[$indx2]['confirmation'];
                				$status_startend = "border-left: 1px dashed red !important; border-right: 1px dashed red !important;";
                				$start_date = $raw_Reservations[$indx2]['start_date_date'];
                				$end_date = $raw_Reservations[$indx2]['end_date_date'];
                				$reference_num = $raw_Reservations[$indx2]['reference_num'];
                				$booking_source = $raw_Reservations[$indx2]['booking_source_name'];
                				$period_id_start = $Period_id_start;

                				if($deposit == null || $deposit == ''){ $deposit = 0; }
                				if($rate_total == null || $rate_total == ''){ $rate_total = 0; }
                				if($paid == null || $paid == ''){ $paid = 0; }

                				if($paid >= $rate_total){
                					# paid
                					$status = "All Paid";
                					$status_color = "#1762d4";
                				}
                				else if($paid >= $deposit){
                					# deposit paid
                					$status = "Deposit paid";
                					$status_color = "#FFD800";
                				}
                				else if($paid < $deposit && $confirmation == 'S'){
                					# Reservation confirmed, Deposit not paid
                					$status = "Reservation confirmed, Deposit not paid";
                					$status_color = "#FF9900";
                				}
                				else if($confirmation == 'N' || $confirmation != 'S'){
                					# Reservation not confirmed
                					$status = "Reservation not confirmed";
                					$status_color = "#FF3115";
                				}
                				if($raw_Reservations[$indx2]['checkin'] != null && $raw_Reservations[$indx2]['checkin'] != ''){
                					if($raw_Reservations[$indx2]['checkout'] != null && $raw_Reservations[$indx2]['checkout'] != ''){
                						$stay_status = "checked_out";
                					}
                					else{
                						$stay_status = "checked_in";
                					}
                				}
                				else{
                					$stay_status = "";
                				}

				                $indx1 = $indx1 + ($Period_id_end - $Period_id_start);
				                //$period_start = $raw_Periods[$indx1]['start_date'];
                				$period_end = $raw_Periods[$indx1]['end_date'];
				                //echo $indx1."yohoo<br>";
	                            $hasdata = 1;
	                            break;
	                        }
	                    }
	                    //else if($checkin_date_id < $Period_id_start && $checkout_date_id > $Period_id_end){ // if checkin and checkout date are beyond the start and end date

	                        //$hasdata = 1;
	                        //break;
	                    //}
                    }
                }
                // push data in the array
                $final_data[$final_data_indx]["reservation_id"] = $reservation_id;
                $final_data[$final_data_indx]["reservation_conn_id"] = $reservation_conn_id;
                $final_data[$final_data_indx]["apartment_id"] = $apartment_id;
                $final_data[$final_data_indx]["date_start_id"] = $date_start_id;
                $final_data[$final_data_indx]["date_end_id"] = $date_end_id;
                $final_data[$final_data_indx]["colspan"] = $colspan;
                $final_data[$final_data_indx]["client_id"] = $client_id;
                $final_data[$final_data_indx]["client_surname"] = $client_surname;
                $final_data[$final_data_indx]["client_name"] = $client_name;
                $final_data[$final_data_indx]["status"] = $status;
                $final_data[$final_data_indx]["status_color"] = $status_color;
                $final_data[$final_data_indx]["status_startend"] = $status_startend;
                $final_data[$final_data_indx]["start_date"] = $start_date;
                $final_data[$final_data_indx]["end_date"] = $end_date;
                $final_data[$final_data_indx]["period_start"] = $period_start;
                $final_data[$final_data_indx]["period_end"] = $period_end;
                $final_data[$final_data_indx]["reference_num"] = $reference_num;
                $final_data[$final_data_indx]["booking_source_name"] = $booking_source;
                $final_data[$final_data_indx]["stay_status"] = $stay_status;
                $final_data[$final_data_indx]["period_id_start"] = $period_id_start;
                $final_data_indx++;
            }
        }

        return $final_data;

	}

	public function get_calendar_reservations($year, $month){ # MONTHLY
		//$year = $this->getCurrent_Year();
		if($year != 0){
			try{
				$year_month = $year."-".$month; # Year-Month
				$tablereservation = "reservation";
				$tableperiod = "periods";
				$sql = "SELECT Distinct tbl_res.*, clients.surname As client_surname, clients.name As client_name, (SELECT start_date FROM $tableperiod WHERE periods_id = tbl_res.date_start_id) As start_date_date, (SELECT end_date FROM $tableperiod WHERE periods_id = tbl_res.date_end_id) As end_date_date, tbl_res_con.reference_num As reference_num, booking_source.booking_source_name As booking_source_name
				FROM $tablereservation As tbl_res, apartments, $tableperiod As period1, $tableperiod As period2, clients, reservation_conn As tbl_res_con, booking_source
				WHERE apartments.apartment_id = tbl_res.appartments_id and 
					  period1.start_date LIKE '$year_month%' and 
					  period2.start_date LIKE '$year_month%' and 
					  (tbl_res.date_start_id <= period1.periods_id and tbl_res.date_end_id >= period2.periods_id) and
					  clients.clients_id = tbl_res.clients_id and
					  tbl_res_con.reservation_conn_id = tbl_res.reservation_conn_id and
					  tbl_res.status = 'active' and
					  tbl_res_con.bookingsource_id = booking_source.booking_source_id";
				$result = $this->executeQuery($sql);

				$sql = "SELECT Distinct tbl_res.*, clients.surname As client_surname, clients.name As client_name, (SELECT start_date FROM $tableperiod WHERE periods_id = tbl_res.date_start_id) As start_date_date, (SELECT end_date FROM $tableperiod WHERE periods_id = tbl_res.date_end_id) As end_date_date, tbl_res_con.reference_num As reference_num, booking_source.booking_source_name As booking_source_name
				FROM transfer_room_history As tbl_res, apartments, $tableperiod As period1, $tableperiod As period2, clients, reservation_conn As tbl_res_con, booking_source
				WHERE apartments.apartment_id = tbl_res.appartments_id and 
					  period1.start_date LIKE '$year_month%' and 
					  period2.start_date LIKE '$year_month%' and 
					  (tbl_res.date_start_id <= period1.periods_id and tbl_res.date_end_id >= period2.periods_id) and
					  clients.clients_id = tbl_res.clients_id and
					  tbl_res_con.reservation_conn_id = tbl_res.reservation_conn_id and 
					  tbl_res.transfer_status = 'checkin' and
					  tbl_res.status = 'active' and
					  tbl_res_con.bookingsource_id = booking_source.booking_source_id";
				$result1 = $this->executeQuery($sql);

				//echo json_encode( $result );
				$merged_result = array_merge($result,$result1);
				return $merged_result;
			}
			catch(PDOException $e) {
				return "error";
			} # catch
		}
	}
	/* end of reservation calendar */
	public function get_calendar_reservations_weekly($date_range){ # WEEKLY
		$year = $this->getCurrent_Year();
		if($year != 0){
			try{
				$start_idPeriod = $this->getIdperiod($date_range[0], "start_date");
				$end_idPeriod = $this->getIdperiod($date_range[1], "end_date");

				$table_periods = "periods";
				$table_reservations = "reservation";
				$sql = "SELECT Distinct tbl_res.*, clients.surname As client_surname, clients.name As client_name, (SELECT tbl_periods1.start_date FROM $table_periods As tbl_periods1 WHERE tbl_periods1.periods_id = tbl_res.date_start_id) As start_date_date, (SELECT tbl_periods2.end_date FROM $table_periods As tbl_periods2 WHERE tbl_periods2.periods_id = tbl_res.date_end_id) As end_date_date, reservation_conn.reference_num As reference_num, booking_source.booking_source_name As booking_source_name
				FROM $table_reservations As tbl_res, apartments, clients, reservation_conn, booking_source
				WHERE apartments.apartment_id = tbl_res.appartments_id and 
					  ((tbl_res.date_start_id >= $start_idPeriod and tbl_res.date_start_id <= $end_idPeriod) or (tbl_res.date_end_id >= $start_idPeriod and tbl_res.date_end_id <= $end_idPeriod) or (tbl_res.date_start_id <= $start_idPeriod and tbl_res.date_end_id >= $start_idPeriod) or (tbl_res.date_start_id <= $end_idPeriod and tbl_res.date_end_id >= $end_idPeriod))and
					  clients.clients_id = tbl_res.clients_id and
					  tbl_res.status = 'active' and
					  tbl_res.reservation_conn_id = reservation_conn.reservation_conn_id and
					  reservation_conn.bookingsource_id = booking_source.booking_source_id";
				$result = $this->executeQuery($sql);

				$sql = "SELECT Distinct tbl_res.*, clients.surname As client_surname, clients.name As client_name, (SELECT tbl_periods1.start_date FROM $table_periods As tbl_periods1 WHERE tbl_periods1.periods_id = tbl_res.date_start_id) As start_date_date, (SELECT tbl_periods2.end_date FROM $table_periods As tbl_periods2 WHERE tbl_periods2.periods_id = tbl_res.date_end_id) As end_date_date, reservation_conn.reference_num As reference_num, booking_source.booking_source_name As booking_source_name
				FROM transfer_room_history As tbl_res, apartments, clients, reservation_conn, booking_source
				WHERE apartments.apartment_id = tbl_res.appartments_id and 
					  ((tbl_res.date_start_id >= $start_idPeriod and tbl_res.date_start_id <= $end_idPeriod) or (tbl_res.date_end_id >= $start_idPeriod and tbl_res.date_end_id <= $end_idPeriod) or (tbl_res.date_start_id <= $start_idPeriod and tbl_res.date_end_id >= $start_idPeriod) or (tbl_res.date_start_id <= $end_idPeriod and tbl_res.date_end_id >= $end_idPeriod))and
					  clients.clients_id = tbl_res.clients_id and 
					  tbl_res.transfer_status = 'checkin' and
					  tbl_res.status = 'active' and
					  tbl_res.reservation_conn_id = reservation_conn.reservation_conn_id and
					  reservation_conn.bookingsource_id = booking_source.booking_source_id";
				$result1 = $this->executeQuery($sql);
				
				$merged_result = array_merge($result,$result1);
				return $merged_result;
			}
			catch(PDOException $e) {
				//echo json_encode( $e->getMessage() );
				return "error";
			} # catch
		}
	}

	public function get_calendar_rooms(){ # CHANGE RATES
		$year = $this->getCurrent_Year();
		if($year != 0){
			try{
				$sql = "SELECT `apartment_id`, `apartment_name`, `roomtype_id` FROM apartments";


				$result = $this->executeQuery($sql);
				return $result;
			}
			catch(PDOException $e) {
				return "error";
			} # catch
		}
	}

	public function week_range_today($date) {
	    $ts = strtotime($date);
	    $start = (date('w', $ts) == 0) ? $ts : strtotime('last monday', $ts);
	    return array(date('Y-m-d', $start), date('Y-m-d', strtotime('next sunday', $start)));
	}
	
	public function week_range($week, $year) { # $week, $year
		$dto = new DateTime();
		//$week = 44;
		//$year = 2017;
		$result[0] = $dto->setISODate($year, $week, 1)->format('Y-m-d');
		$result[1] = $dto->setISODate($year, $week, 7)->format('Y-m-d');
		return $result;
	} 

	public function get_weeks1($year){ 
		if($year == ''){
			$year = $this->getCurrent_Year();
		}
		$date = new DateTime;
		$date->setISODate($year, 53);
		$tot_weeks = ($date->format("W") === "53" ? 53 : 52);
		$result;
		for($x=1; $x<=$tot_weeks; $x++){
		    $week_date_range = $this->week_range($x, $year);
		    $result[$x-1]["week"] = $x;
		    $result[$x-1]["date_start"] = $week_date_range[0];
		   	$result[$x-1]["date_end"] = $week_date_range[1];
		}
		return $result;
	}

	public function get_list_reservations($date_start, $date_end, $filter, $filter1){
		# bookingsource_id
		$date_start_id = $this->getIdperiod($date_start, "start_date");
		$date_end_id = $this->getIdperiod($date_end, "start_date");
		# res_con.status='active' and
		# res_con.status = '$filter1' and
		$sql = "SELECT DISTINCT res_con.*, clients.surname As client_lname, clients.name As client_fname, (SELECT Count(reservation_id) FROM reservation WHERE reservation.reservation_conn_id = res_con.reservation_conn_id $filter1) As num_rooms, (SELECT booking_source_name FROM booking_source WHERE booking_source.booking_source_id = res_con.bookingsource_id) As booking_source_name, (SELECT booking_source.icon_src FROM booking_source WHERE booking_source.booking_source_id = res_con.bookingsource_id) As booking_src_icon
				FROM reservation_conn AS res_con, reservation AS tbl_res, clients
				WHERE 
					  res_con.client_id = clients.clients_id and
					  tbl_res.reservation_conn_id = res_con.reservation_conn_id and
					  ((tbl_res.date_start_id >= $date_start_id and tbl_res.date_start_id <= $date_end_id) or (tbl_res.date_end_id >= $date_start_id and tbl_res.date_end_id <= $date_end_id) or (tbl_res.date_start_id <= $date_start_id and tbl_res.date_end_id >= $date_start_id) or (tbl_res.date_start_id <= $date_end_id and tbl_res.date_end_id >= $date_end_id)) $filter";
		$result = $this->executeQuery($sql);
		return $result;
	}

	public function get_list_reservation_rooms($date_start, $date_end, $filter, $filter1){
		$date_start_id = $this->getIdperiod($date_start, "start_date");
		$date_end_id = $this->getIdperiod($date_end, "start_date");

		$sql = "SELECT DISTINCT tbl_res.*, apartments.apartment_name As room_name, room_types.name As room_type_name, (SELECT CONCAT(IFNULL(clients.surname, ''), ', ', IFNULL(clients.name, '')) FROM clients WHERE clients.clients_id = tbl_res.clients_id) as client_name, (SELECT start_date FROM periods WHERE periods_id = tbl_res.date_start_id) As start_date_date, (SELECT end_date FROM periods WHERE periods_id = tbl_res.date_end_id) As end_date_date, (SELECT COALESCE(SUM(transfer_room_history.rate_total),0) FROM transfer_room_history WHERE transfer_room_history.reservation_id = tbl_res.reservation_id and transfer_room_history.transfer_status = 'checkin') As tot_transfer
				FROM reservation As tbl_res, apartments, room_types, clients
				WHERE ((tbl_res.date_start_id >= $date_start_id and tbl_res.date_start_id <= $date_end_id) or (tbl_res.date_end_id >= $date_start_id and tbl_res.date_end_id <= $date_end_id) or (tbl_res.date_start_id <= $date_start_id and tbl_res.date_end_id >= $date_start_id) or (tbl_res.date_start_id <= $date_end_id and tbl_res.date_end_id >= $date_end_id)) and
					  tbl_res.appartments_id = apartments.apartment_id and
					  apartments.roomtype_id = room_types.room_type_id $filter";
		$result = $this->executeQuery($sql);
		for($ff = 0; $ff < count($result); $ff++){
			$resv_id = $result[$ff]['reservation_id'];
			$comm_amt = $result[$ff]['commissions'];
			
			$resultBookingSrc = $this->executeQuery("SELECT booking_source.commission_paid FROM bookings LEFT JOIN reservation on reservation.reservation_id = bookings.reservation_id LEFT JOIN booking_source on booking_source.booking_source_id = bookings.bookingsource_id WHERE reservation.reservation_id = '$resv_id'");
			
			if($resultBookingSrc[0]['commission_paid'] == 'yes'){
				$result[$ff]['comm_paid'] = $comm_amt;
			}else{
				$result[$ff]['comm_paid'] = 0;
			}
		}
		
		return $result;
	}
	// orig space
	public function get_print_calendar($calendar_data, $dates, $calendar, $period){
		$sql = "SELECT * FROM apartments";
		$rooms = $this->executeQuery($sql);

		$cnt1 = count($dates);
		$table_th = "<th></th>";
		$table_row_date = "<td></td>";
		for($x=0; $x<$cnt1; $x++){
			$table_th .= "<th></th><th></th>";
			$start_date = date('Y-m-d', strtotime($dates[$x]["start_date"]));
			$day_temp = date('d', strtotime($dates[$x]["start_date"]));
			$day_temp1 = date('l', strtotime($dates[$x]["start_date"]));
			$day_temp2 = substr($day_temp1, 0, 3);
			$textcol = "";
			if($day_temp2 == "Sun"){
				$textcol = "color: #ff0000";
			}
			$table_row_date .= "<td colspan='2' style='width: 20px; padding-top:2px; padding-bottom:2px; border: 0.5px solid #ccc; text-align: center; $textcol'><b>".$day_temp."<br>".$day_temp2."</b></td>";
		}
		$cnt2 = count($rooms);
		$cnt3 = count($calendar_data);
		$table_row = "";
		for($x=0; $x<$cnt2; $x++){
			$table_row .= "<tr style='border: 1px solid #ccc;'>";
			$table_row .= "<td style='padding-top:2px; padding-bottom:2px; min-width: 70px; height: 15px; border: 0.5px solid #ccc;' class='textflow'><b>".$rooms[$x]["apartment_name"]."</b></td>
						   <td style='border: 1px solid #ccc; width: 10px;'></td>";
			for($x1=0; $x1<$cnt1; $x1++){
				$searchFor = $dates[$x1]["periods_id"];
				$filteredArray = array();
				$filteredArray = array_filter($calendar_data, function($element) use($searchFor){
						return isset($element['period_id_start']) && $element['period_id_start'] == $searchFor;
					 });
				//$cnt_filtArray = count($filteredArray);
				$counter = 0;
				foreach ($filteredArray as $arr) {
					if($arr["apartment_id"] == $rooms[$x]["apartment_id"]){
						$colspan = $arr["colspan"];
						$statcol = $arr["status_color"];
						$min_width = (10*$colspan);
						$min_width1 = "width: ".$min_width."px;";
						$table_row .= "<td colspan='".$colspan."' style='border: 0.5px solid #ccc; $min_width1' valign='top' >
											<div style='width: 100%; height: 15px; border-top: 3px solid $statcol !important;' class='textflow'>".
													$arr["client_surname"].", ".$arr["client_name"]
											."</div>
									   </td>";
						$x1 = ($x1+($colspan/2)) - 1;
						$counter = 1;
					}
				}
				if($counter == 0){
					$table_row .= "<td colspan='2' style='border: 1px solid #ccc; width: 20px;'></td>";
				}
			}	
			$table_row .= "</tr>";
		}

		$final_data = "<center><p style='font-family: Arial; font-size: 14px;'>".$calendar." Calendar (".$period.")</p></center>
						<center>
							<table style='font-family: Arial; font-size: 10px; border-spacing: 0; border-collapse: collapse;' border='0'>
			 				  <thead>
			 				  	  <tr>".
			 				  	  	  $table_th
			 				  	  ."</tr>
			 				  </thead>
			 				  <tbody>
			 				  	  <tr>".
			 				  	  	  $table_row_date
			 				  	  ."
			 				  	  </tr>".
			 				  	  $table_row
			 				  ."</tbody>
			 			   </table>
			 			</center>
			 			<br>
			 			<center>
			 			   <div style='width: 500px; font-family: Arial; font-size: 10px;'>
								<div style='float: left;
										    border-top: 4px solid #FF3115;
										    width: 100px;
										    font-size: 10px;
										    margin: 0px 9px;
										    text-align: center;'>
									Reservation not confirmed
								</div>
								<div style='float: left;
										    border-top: 4px solid #FF9900;
										    width: 100px;
										    font-size: 10px;
										    margin: 0px 9px;
										    text-align: center;'>
									Reservation confirmed, Deposit not paid
								</div>
								<div style='float: left;
										    border-top: 4px solid #FFD800;
										    width: 100px;
										    font-size: 10px;
										    margin: 0px 9px;
										    text-align: center;'>
									Deposit paid
								</div>
								<div style='float: left;
										    border-top: 4px solid #1762d4;
										    width: 100px;
										    font-size: 10px;
										    margin: 0px 9px;
										    text-align: center;'>
									All paid
								</div>
						   </div>
						</center>
					   ";
		$final_data = "<html>
						  <head>
							 <style media='print'>
								 .bgcol{
								 	background-color: #ccc;
								 }
								 .textflow{
								 	overflow: hidden;
								 	overflow-wrap: break-word;
								 	word-break: break-all;
								 }
							 </style>
						  </head>
						  <body>".$final_data."</body>
					   </html>";

		return $final_data;
		
	}


	/* Email Invoice : start ------------------------------------ */
	public function send_email($fields){

		if( ! empty( $fields ) ) {
				$to = ""; 
				$body = "";
				$headers = "";
				$replyto = "";
				$subject = $fields["subject"];
				$fromemail = $fields["from_email"]; # sender email
				$to = $fields["rec_email"]; # recipient email
				$cc = $fields["cc_email"]; # cc email
				$message = $fields["message"];
				$from_name = $fields["from_name"];

				$filename = $fields["filename"];
				$file_path = "../../../".$fields["file_path"];
				if( ! empty( $filename ) ) { 
					$file = $file_path; //$file = $file_path.$filename; 
					$content = file_get_contents( $file);
					$content = chunk_split(base64_encode($content));
					$uid = md5(uniqid(time()));
					$name = basename($file);

						// header
					$headers = "From: ".$from_name." <".$fromemail.">\r\n";
					$headers .= "Reply-To: ".$replyto."\r\n";
					$headers .= "MIME-Version: 1.0\r\n";
					$headers .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";

						// message & attachment
					$body = "--".$uid."\r\n";
					$body .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
					$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
					$body .= $message."\r\n\r\n";
					$body .= "--".$uid."\r\n";
					$body .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n";
					$body .= "Content-Transfer-Encoding: base64\r\n";
					$body .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
					$body .= $content."\r\n\r\n";
					$body .= "--".$uid."--";
				} else {
					$headers = "From:" . $fromemail . "\r\n".
				    "Content-Type: text/html; charset=ISO-8859-1\r\n" .
				    "Reply-To: " . $fromemail . "\r\n" .
					"CC: ".$cc."\r\n" .
				    "X-Mailer: PHP/" . phpversion();
				        
				    $body = $message;
				}
				
				if ( mail( $to, $subject, $body, $headers ) ) {
					return "true"; // Or do something here
				} else {
					return "false";
				}
		}
	}
	public function invoice_email_message(){
		$sql = "SELECT * 
				FROM `invoice_email_config`
				WHERE `id` = 1";
		$result = $this->executeQuery($sql);
		return $result;
	}
	/* Email Invoice : end   ------------------------------------ */
	
	/* start add octorate booking OTA */
	public function octorate_booking($bookings, $id){
		$result = $bookings;
		
		$year = date("Y",(time() + (C_DIFF_ORE * 3600)));	#--Selected Year
		$date_raw = date("Y-m-d");
		$date_raw_now = date("Y-m-d H:i:s");
		$book_id = 0;
		
		$sqlReservation = "INSERT INTO 
				reservation(
					reservation_conn_id,
					clients_id, 
					appartments_id, 
					date_start_id, 
					date_end_id, 
					assign_app, 
					pax,
					original_rate,
					rate, 
					weekly_rates,
					commissions,
					rate_total,
					deposit,
					paid,
					code,
					confirmation,
					split_from,
					status,
					inserted_date,
					inserted_host,
					inserted_by
				) 
				VALUES(
					:reservation_conn_id,
					:clients_id, 
					:appartments_id, 
					:date_start_id, 
					:date_end_id, 
					:assign_app, 
					:pax, 
					:original_rate,
					:rate, 
					:weekly_rates,
					:commissions,
					:rate_total,
					:deposit,
					:paid,
					:code,
					:confirmation,
					:split_from,
					:status,
					:inserted_date,
					:inserted_host,
					:inserted_by
				)";
				
		$sqlClient = "INSERT INTO 
						clients(
							surname, 
							home_address,
							street,
							city,
							nation,
						 	nationality,
							phone,
							reference_email,
							inserted_date, 
							inserted_host, 
							inserted_by
						) 
						VALUES(
							:surname,
							:home_address,
							:street,
							:city,
							:nation,
							:nationality,
							:phone,
							:email,
							:inserted_date,
							:inserted_host,
							:inserted_by
						)";
						
		$sqlReservConn = "INSERT INTO 
					reservation_conn(
						client_id, 
						date_start_id,
						date_end_id,
						bookingsource_id,
						reference_num,
						status,
						date_inserted,
						inserted_by
					) 
					VALUES(
						:client_id, 
						:date_start_id,
						:date_end_id,
						:bookingsource_id,
						:reference_num,
						:status,
						NOW() + INTERVAL :date_inserted HOUR, 
						:inserted_by
					)";
					
		$sqlReservOctorateBook = "INSERT INTO 
					octorate_bookings(
						idbooking_octo, 
						idprenota,
						year,
						status
					) 
					VALUES(
						:idbooking_octo, 
						:idprenota,
						:year,
						:status
					)";
		
		$sqlBookings = "INSERT INTO 
					bookings(
						reservation_id, 
						bookingsource_id,
						refnumber,
						chnnl_manager_id_res,
						year,
						status
					) 
					VALUES(
						:reservation_id, 
						:bookingsource_id,
						:refnumber,
						:chnnl_manager_id_res,
						:year,
						:status
					)";
					
		$sqlNotes = "INSERT INTO 
					reservation_notes(
						reservation_id, 
						note_type_id,
						est_hours,
						est_mins,
						note,
						status
					) 
					VALUES(
						:reservation_id, 
						:note_type_id,
						:est_hours,
						:est_mins,
						:note,
						:status
					)";
		
		
		try{
			$choices = array();
			$resv_sched_cleaning = array();
			$client_notes = "";
			$client_phone = "";
			$client_city = "";
			$client_address = "";
			$client_nationality = "";
			$client_country = "";
			
			$date_created = $result['Bookings']['Booking']['ResCreationDate'];
			$id_reservation = $result['Bookings']['Booking']['ResId'];
			$bbliverateId = $result['Bookings']['Booking']['BbliverateId'];
			if(isset($result['Bookings']['Booking']['BbliverateTimestamp'])) {
				$date_time = new DateTime($result['Bookings']['Booking']['BbliverateTimestamp']);
				$date_time = $date_time->modify('+6 hour');
        
				$booking_date_time = $date_time->format('Y-m-d H:i:s');
			} else {
				$t_difference = C_DIFF_ORE;
				$time = $this->executeQuery("SELECT NOW() + INTERVAL '$t_difference' HOUR AS datetime");
				$booking_date_time = $time[0]['datetime'];
			}
			
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'])) {
				$client_name = $result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'];
			}else{
				$client_name = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerFName'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerNote'])){
				$client_notes = $result['Bookings']['Booking']['Customers']['Customer']['CustomerNote'];
			}else{
				$client_notes = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerNote'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerPhone'])){
				$client_phone = $result['Bookings']['Booking']['Customers']['Customer']['CustomerPhone'];
			}else{
				$client_phone = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerPhone'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerCity'])){
				$client_city = $result['Bookings']['Booking']['Customers']['Customer']['CustomerCity'];
			}else{
				$client_city = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerCity'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerAddress'])){
				$client_address = $result['Bookings']['Booking']['Customers']['Customer']['CustomerAddress'];
			}else{
				$client_address = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerAddress'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerNationality'])){
				$client_nationality = $result['Bookings']['Booking']['Customers']['Customer']['CustomerNationality'];
			}else{
				$client_nationality = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerNationality'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerCountry'])){
				$client_country = $result['Bookings']['Booking']['Customers']['Customer']['CustomerCountry'];
			}else{
				$client_country = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerCountry'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerEmail'])){
				$client_email = $result['Bookings']['Booking']['Customers']['Customer']['CustomerEmail'];
			}else{
				$client_email = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerEmail'];
			}
			
			$channel_id = $result['Bookings']['Booking']['Channel'];
			if(!isset($result['Bookings']['Booking']['Channel'])) {
				$channel_id = 96;
			}
			
			$start_date = $result['Bookings']['Booking']['StartDate'];
			$checkin_id = $this->getIdperiod($start_date, "start_date");
			$end_date = $result['Bookings']['Booking']['EndDate'];
			$checkout_id = $this->getIdperiod($end_date, "end_date");
			$channel_source = $result['Bookings']['Booking']['ResSource'];
			$check_res =  $this->executeQuery("SELECT * FROM octorate_bookings WHERE idbooking_octo = '$id_reservation'");
			$host = $_SERVER['SERVER_NAME'];
			$lastClientId = 0;
			$lastresvConnId = 0;
			
			if($channel_source != '294e1dc2b907ed0496e51572c3ef081e'){
				//if(count($check_res) == 0){
					$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
					$id_property = $getPropertyId[0]['property_id'];
					
					if($client_country != ""){
						$getFullCountry = $this->executeQuery("SELECT * FROM nations WHERE code2_nation LIKE '%$client_country%'");
						if(count($getFullCountry) > 0){
							$country_client = $getFullCountry[0]['nation_name'];
						}else{
							$country_client = "";
						}
					}else{
						$country_client = "";
					}
					if($client_nationality != ""){
						$getFullNation = $this->executeQuery("SELECT * FROM nations WHERE code2_nation LIKE '%$client_nationality%'");
						if(count($getFullNation) > 0){
							$nation_client = $getFullNation[0]['nation_name'];
						}else{
							$nation_client = "";
						}
					}else{
						$nation_client = "";
					}
					
					$get_clients =  $this->executeQuery("SELECT * FROM clients WHERE surname = '$client_name'");
					if(count($get_clients) == 0){
						$clientInsert = array(
								':surname' => $client_name,
								':home_address' => $client_address,
								':street' => $client_address,
								':city' => $client_city,
								':nation' => $country_client,
								':nationality' => $nation_client,
								':phone' => $client_phone,
								':email' => $client_email,
								':inserted_date' => $date_raw_now,
								':inserted_host' => $host,
								':inserted_by' => '1'
							);
						$lastClientId = $this->execute_insert_getId($sqlClient,$clientInsert);
					}
					else{
						$lastClientId = $get_clients[0]['clients_id'];
					}
					
					/*HTL issue HTL-476 start*/
					$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
					$book_id = $getBookingChannel[0]['booking_source_id'];
					/*HTL issue HTL-476 end*/
					
					/* if($channel_source == 'booking_xml'){
						$book_id = 3;
					}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
						$book_id = 6;
					}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
						$book_id = 8;
					}else if($channel_source == 'hostelworld_xml' || $channel_source == 'hostelworld'){
						$book_id = 10;
					} */
					
					$getExistingResvConn = $this->executeQuery("SELECT * FROM reservation_conn WHERE reference_num = '$id_reservation'");
					if(count($getExistingResvConn) > 0){
						$lastresvConnId = $getExistingResvConn[0]['reservation_conn_id'];
					}else{
						$resvConnInsert = array(
									':client_id' => $lastClientId,
									':date_start_id' => $checkin_id,
									':date_end_id' => $checkout_id,
									':bookingsource_id' => $book_id,
									':reference_num' => $id_reservation,
									':status' => 'active',
									':date_inserted' => C_DIFF_ORE,
									':inserted_by' => 1
								);
						$lastresvConnId = $this->execute_insert_getId($sqlReservConn,$resvConnInsert);
					}
					
					$rooms = $result['Bookings']['Booking']['Rooms']['Room'];
					$room_cnt = count($rooms);

					if(isset($rooms['StartDate'])) {	
						$roomtype_id = $rooms['RoomTypeIds']['RoomTypeId'];
						$roomtype_id_bb = $rooms['BbliverateNumberId'];
						$roomtype_pax = $rooms['Pax'];
						$roomtype_price = $rooms['Price'];
						
						if($id_property == '274690'){
							/* if($roomtype_id == '148589'){ */
							if($roomtype_id == '148590'){
								if($roomtype_pax < 3 || $roomtype_pax < '3'){
									$roomtype_pax = 3;
								}
							}else if($roomtype_id == '148593'){
								if($roomtype_pax < 3 || $roomtype_pax < '3'){
									$roomtype_pax = 3;
								}
							}
						}
						
						$getExistingBb_id = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = $roomtype_id_bb");
						if(count($getExistingBb_id) == 0){
							$get_roomtype =  $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
							$roomtypeId = $get_roomtype[0]['roomtype_id'];

							$get_roomtype_name =  $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
							$roomtype_name = $get_roomtype_name[0]['name'];
							$roomtype_column = $get_roomtype_name[0]['associated_column'];

							$get_roomtype_rate =  $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
							$totalamnt = 0;
							$tariffesettimanali = "";
							$commission = 0;
							
							if($id_property == '274690'){
								/*$cntDaily = count($rooms['DayByDayPrice']['price']);
								for($xx = 0; $xx < $cntDaily; $xx++){
									$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
									if($xx==0){
										$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
									}else{
										$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
									}
								}*/
								// HTL-626 fix : START
								if(array_key_exists("DayByDayPrice",$rooms)){
									$cntDaily = count($rooms['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
										}
									}
								}
								else{
									$rate_column_temp = $roomtype_column."_pax_".$roomtype_pax;
									$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$rate_column_temp'");
									if(count($column_is_exist) == 0){
										$rate_column_temp = $roomtype_column;
									}
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$rate_column_temp];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$rate_column_temp];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$rate_column_temp];
										}
									}
								}
								// HTL-626 fix : END
							}
							else if($id_property == '962924' || $id_property == '395239'){ // temporary code for AMI and CCQ. until the new Rates system i done.
								if(array_key_exists("DayByDayPrice",$rooms) && $channel_source == 'booking_xml' && $id_property == '395239'){
									$cntDaily = count($rooms['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
										}
									}
								}
								else{
									$correction_ratio_type = $getBookingChannel[0]['correction_ratio_type'];
									$correction_ratio_value = $getBookingChannel[0]['correction_ratio_value'];
									$tax_included = $getBookingChannel[0]['tax_included'];
									$tax_type = $getBookingChannel[0]['tax_type'];
									$tax_value = $getBookingChannel[0]['tax_value'];
									// --
									$roomtype_column_p = $roomtype_column."_pax_".$roomtype_pax;
									$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$roomtype_column_p'");
									if(count($column_is_exist) == 0){
										$roomtype_column_p = $roomtype_column;
									}
									// --
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										if($correction_ratio_type == "percentage"){ // percentage amount correction ratio
											$get_roomtype_rate[$x2][$roomtype_column_p] = $get_roomtype_rate[$x2][$roomtype_column_p] + ($get_roomtype_rate[$x2][$roomtype_column_p] * ($correction_ratio_value/100));
										}
										else{ // fixed amount correction ratio
											$get_roomtype_rate[$x2][$roomtype_column_p] += $correction_ratio_value;
										}

										if($tax_included == "no"){
											if($tax_type == "percentage"){ // percentage amount tax
												$get_roomtype_rate[$x2][$roomtype_column_p] = $get_roomtype_rate[$x2][$roomtype_column_p] + ($get_roomtype_rate[$x2][$roomtype_column_p] * ($tax_value/100));
											}
											else{ // fixed amount tax
												$get_roomtype_rate[$x2][$roomtype_column_p] += $tax_value;
											}
										}
										$totalamnt += $get_roomtype_rate[$x2][$roomtype_column_p];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column_p];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column_p];
										}
									}
								}
							}else{
								$cnt2 = count($get_roomtype_rate);
								for($x2=0; $x2<$cnt2; $x2++){
									$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
									if($x2==0){
										$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
									}else{
										$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
									}
								}
							}
							
							/*HTL issue HTL-476 start*/
							$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
							$cost_type = $getBookingChannel[0]['comm_type'];
							$cost_comm = $getBookingChannel[0]['cost_comm'];
							$deposit_status = $getBookingChannel[0]['deposit_status'];
							$comm_status = $getBookingChannel[0]['commission_paid'];
							
							$deposit_total = 0;
							$paid_total = 0;
							$paid_comm = 0;
							
							if($deposit_status == 'paid'){
								$deposit_total = $roomtype_price;
							}
							
							if($cost_type == 'percent'){
								$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
							}else if($cost_type == 'fix'){
								$commission = floatval($cost_comm) + floatval($totalamnt);
							}else{
								$commission = 0;
							}
							
							if($comm_status == 'yes'){
								$paid_comm = $commission;
							}
							
							$paid_total = floatval($paid_comm) + floatval($deposit_total);
							/*HTL issue HTL-476 end*/
							
							/* if($channel_source == 'booking_xml'){
								$commission = $roomtype_price * 0.15;
							}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
								$commission = 0;
							}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
								$commission = 0;
							}else{
								$commission = 0;
							} */
							
							$tariffa = $roomtype_name."#@&".$totalamnt;
							$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);
							$ran_code = $this->randomString();
					
							/*$reservationInsert = array(
										':reservation_conn_id' => $lastresvConnId,
										':clients_id' => $lastClientId, 
										':appartments_id' => $idappartamenti,
										':date_start_id' => $checkin_id, 
										':date_end_id' => $checkout_id, 
										':assign_app' => 'k', 
										':pax' => $roomtype_pax, 
										':original_rate' => $tariffa,
										':rate' => $tariffa, 
										':weekly_rates' => $tariffesettimanali,
										':commissions' => $commission,
										':rate_total' => $totalamnt,
										':deposit' => $deposit_total,
										':paid' => $paid_total, 
										':code' => $ran_code,
										':confirmation' => 'S',
										':status' => 'active',
										':inserted_date' => C_DIFF_ORE,
										':inserted_host' => $host,
										':inserted_by' => '1'
									);
							$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
							
							$reservOctorateBookInsert = array(
									':idbooking_octo' => $id_reservation,
									':idprenota' => $lastReservationId,
									':year' => $year,
									':status' => 'active'
								);
							$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
							
							$bookingsInsert = array(
									':reservation_id' => $lastReservationId,
									':bookingsource_id' => $book_id,
									':refnumber' => $id_reservation,
									':chnnl_manager_id_res' => $roomtype_id_bb,
									':year' => $year,
									':status' => 'active'
								);
							$this->execute_insert($sqlBookings,$bookingsInsert);
							
							$bookingNotesInsert = array(
									':reservation_id' => $lastReservationId,
									':note_type_id' => 1,
									':est_hours' => "",
									':est_mins' => "",
									':note' => $client_notes,
									':status' => 'active'
								);
							$this->execute_insert($sqlNotes,$bookingNotesInsert);*/

							//orig space before room split
							if($idappartamenti > 0){
								$reservationInsert = array(
											':reservation_conn_id' => $lastresvConnId,
											':clients_id' => $lastClientId, 
											':appartments_id' => $idappartamenti,
											':date_start_id' => $checkin_id, 
											':date_end_id' => $checkout_id, 
											':assign_app' => 'k', 
											':pax' => $roomtype_pax, 
											':original_rate' => $tariffa,
											':rate' => $tariffa, 
											':weekly_rates' => $tariffesettimanali,
											':commissions' => $commission,
											':rate_total' => $totalamnt,
											':deposit' => $deposit_total,
											':paid' => $paid_total,
											':code' => $ran_code,
											':confirmation' => 'S',
											':split_from' => 0,
											':status' => 'active',
											':inserted_date' => $booking_date_time,
											':inserted_host' => $host,
											':inserted_by' => '1'
											);
								$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	

								$reservOctorateBookInsert = array(
											':idbooking_octo' => $id_reservation,
											':idprenota' => $lastReservationId,
											':year' => $year,
											':status' => 'active'
										);
								$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
									
								$bookingsInsert = array(
											':reservation_id' => $lastReservationId,
											':bookingsource_id' => $book_id,
											':refnumber' => $id_reservation,
											':chnnl_manager_id_res' => $roomtype_id_bb,
											':year' => $year,
											':status' => 'active'
										);
								$this->execute_insert($sqlBookings,$bookingsInsert);
									
								$bookingNotesInsert = array(
											':reservation_id' => $lastReservationId,
											':note_type_id' => 1,
											':est_hours' => "",
											':est_mins' => "",
											':note' => $client_notes,
											':status' => 'active'
										);
								$this->execute_insert($sqlNotes,$bookingNotesInsert);
							}
							else{
								$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

								if($split_rooms == false){
									# create the booking but with no room allocated
									$reservationInsert = array(
												':reservation_conn_id' => $lastresvConnId,
												':clients_id' => $lastClientId, 
												':appartments_id' => 0,
												':date_start_id' => $checkin_id, 
												':date_end_id' => $checkout_id, 
												':assign_app' => 'k', 
												':pax' => $roomtype_pax, 
												':original_rate' => $tariffa,
												':rate' => $tariffa, 
												':weekly_rates' => $tariffesettimanali,
												':commissions' => $commission,
												':rate_total' => $totalamnt,
												':deposit' => $deposit_total,
												':paid' => $paid_total,
												':code' => $ran_code,
												':confirmation' => 'S',
												':split_from' => 0,
												':status' => 'active',
												':inserted_date' => $booking_date_time,
												':inserted_host' => $host,
												':inserted_by' => '1'
												);
									$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	

									$reservOctorateBookInsert = array(
												':idbooking_octo' => $id_reservation,
												':idprenota' => $lastReservationId,
												':year' => $year,
												':status' => 'active'
											);
									$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
										
									$bookingsInsert = array(
												':reservation_id' => $lastReservationId,
												':bookingsource_id' => $book_id,
												':refnumber' => $id_reservation,
												':chnnl_manager_id_res' => $roomtype_id_bb,
												':year' => $year,
												':status' => 'active'
											);
									$this->execute_insert($sqlBookings,$bookingsInsert);
										
									$bookingNotesInsert = array(
												':reservation_id' => $lastReservationId,
												':note_type_id' => 1,
												':est_hours' => "",
												':est_mins' => "",
												':note' => $client_notes,
												':status' => 'active'
											);
									$this->execute_insert($sqlNotes,$bookingNotesInsert);

									$socket_json_message = array(
										'action' => 'notify',
										'message' => "There's a reservation with unallocated room! REF#".$id_reservation.", Client: ".$client_name,
										'host' => $_SERVER['SERVER_NAME'],
										'inserted_by' => 1,
										'reservation_link' => '#/modify-booking/$lastresvConnId/$id_reservation',
										'notification_type' => '1'
									);

									$this->log_activity($socket_json_message, true);
								}
								else{
									# proceed splitting the rooms
									$split_from = 0;
									$period_Arr = range($checkin_id, $checkout_id);
									$weekly_rates_Arr = explode(",",$tariffesettimanali);
									$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
									$commission_temp = $commission;
									$deposit_total_temp = $deposit_total;
									$paid_total_temp = $paid_total;
										
									for($a=0; $a<count($split_rooms); $a++){
										$room_id_split = $split_rooms[$a]["room_id"];
										$date_start_id_split = $split_rooms[$a]["date_start_id"];
										$date_end_id_split = $split_rooms[$a]["date_end_id"];
										$weekly_rates_s = ""; # weekly_rates
										$rate_total_s = 0; # rate_total
										$commission_s = 0; # commission
										$deposit_total_s = 0; # deposit_total
										$paid_total_s = 0; # 

										for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
											$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
											if($b==$date_start_id_split){ 
												$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
											}
											else{ 
												$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
											}
										}
										$rate_s = $roomtype_name."#@&".$rate_total_s;

										if($rate_total_s >= $commission_temp){ # recalculate commissions
											$commission_s = $commission_temp;
											$commission_temp = 0;
										}
										else{
											$commission_s = $rate_total_s;
											$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
											$deposit_total_s = $deposit_total_temp;
											$deposit_total_temp = 0;
										}
										else{
											$deposit_total_s = $rate_total_s;
											$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $paid_total_temp){ # recalculate paid
											$paid_total_s = $paid_total_temp;
											$paid_total_temp = 0;
										}
										else{
											$paid_total_s = $rate_total_s;
											$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
										}

										$reservationInsert = array(
													':reservation_conn_id' => $lastresvConnId,
													':clients_id' => $lastClientId, 
													':appartments_id' => $room_id_split,
													':date_start_id' => $date_start_id_split, 
													':date_end_id' => $date_end_id_split, 
													':assign_app' => 'k', 
													':pax' => $roomtype_pax, 
													':original_rate' => $rate_s,
													':rate' => $rate_s, 
													':weekly_rates' => $weekly_rates_s,
													':commissions' => $commission_s,
													':rate_total' => $rate_total_s,
													':deposit' => $deposit_total_s,
													':paid' => $paid_total_s,
													':code' => $ran_code,
													':confirmation' => 'S',
													':split_from' => $split_from,
													':status' => 'active',
													':inserted_date' => $booking_date_time,
													':inserted_host' => $host,
													':inserted_by' => '1'
												);
										$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
											
										if($a == 0){ 
											$split_from = $lastReservationId; 
										}
											
										$bookingNotesInsert = array(
													':reservation_id' => $lastReservationId,
													':note_type_id' => 1,
													':est_hours' => "",
													':est_mins' => "",
													':note' => $client_notes,
													':status' => 'active'
												);
										$this->execute_insert($sqlNotes,$bookingNotesInsert);

									} # for($a=0; $a<count($split_rooms); $a++)

									$reservOctorateBookInsert = array(
													':idbooking_octo' => $id_reservation,
													':idprenota' => $split_from,
													':year' => $year,
													':status' => 'active'
												);
									$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
											
									$bookingsInsert = array(
													':reservation_id' => $split_from,
													':bookingsource_id' => $book_id,
													':refnumber' => $id_reservation,
													':chnnl_manager_id_res' => $roomtype_id_bb,
													':year' => $year,
													':status' => 'active'
												);
									$this->execute_insert($sqlBookings,$bookingsInsert);

									$activity_log_message = array(
										'action' => "notify",
										'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
										'host' => $_SERVER['SERVER_NAME'],
										'inserted_by' => '1',
										'reservation_link' => '#/split_rooms/[room_split_id]',
										'notification_type' => '1'
									);
									$this->split_rooms_log_activity($split_from, $activity_log_message);
								}

							}	
								
							$this->val_rules_engine(2, $roomtypeId, $checkin_id, $checkout_id); # room optimize ni
						}
					} 
					else {
						for($x=0; $x<$room_cnt; $x++){
							$roomtype_id = $rooms[$x]['RoomTypeIds']['RoomTypeId'];
							$roomtype_id_bb = $rooms[$x]['BbliverateNumberId'];
							$roomtype_pax = $rooms[$x]['Pax'];
							$roomtype_price = $rooms[$x]['Price'];
							
							if($id_property == '274690'){
								/* if($roomtype_id == '148589'){ */
								if($roomtype_id == '148590'){
									if($roomtype_pax < 3 || $roomtype_pax < '3'){
										$roomtype_pax = 3;
									}
								}else if($roomtype_id == '148593'){
									if($roomtype_pax < 3 || $roomtype_pax < '3'){
										$roomtype_pax = 3;
									}
								}
							}
							
							$getExistingBb_id = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = $roomtype_id_bb");
							if(count($getExistingBb_id) == 0){
								$get_roomtype = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
								$roomtypeId = $get_roomtype[0]['roomtype_id'];

								$get_roomtype_name = $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
								$roomtype_name = $get_roomtype_name[0]['name'];
								$roomtype_column = $get_roomtype_name[0]['associated_column'];

								$get_roomtype_rate = $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
								$totalamnt = 0;
								$tariffesettimanali = "";
								$commission = 0;
								
								if($id_property == '274690'){
									/*$cntDaily = count($rooms[$x]['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms[$x]['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms[$x]['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms[$x]['DayByDayPrice']['price'][$xx];
										}
									}*/
									// HTL-626 fix : START
									if(array_key_exists("DayByDayPrice",$rooms)){
										$cntDaily = count($rooms['DayByDayPrice']['price']);
										for($xx = 0; $xx < $cntDaily; $xx++){
											$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
											if($xx==0){
												$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
											}
										}
									}
									else{
										$rate_column_temp = $roomtype_column."_pax_".$roomtype_pax;
										$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$rate_column_temp'");
										if(count($column_is_exist) == 0){
											$rate_column_temp = $roomtype_column;
										}
										$cnt2 = count($get_roomtype_rate);
										for($x2=0; $x2<$cnt2; $x2++){
											$totalamnt += $get_roomtype_rate[$x2][$rate_column_temp];
											if($x2==0){
												$tariffesettimanali = $get_roomtype_rate[$x2][$rate_column_temp];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$rate_column_temp];
											}
										}
									}
									// HTL-626 fix : END
								}
								else if($id_property == '962924' || $id_property == '395239'){ // temporary code for AMI and CCQ. until the new Rates system i done.
									if(array_key_exists("DayByDayPrice",$rooms) && $channel_source == 'booking_xml' && $id_property == '395239'){
										$cntDaily = count($rooms['DayByDayPrice']['price']);
										for($xx = 0; $xx < $cntDaily; $xx++){
											$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
											if($xx==0){
												$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
											}
										}
									}
									else{
										$correction_ratio_type = $getBookingChannel[0]['correction_ratio_type'];
										$correction_ratio_value = $getBookingChannel[0]['correction_ratio_value'];
										$tax_included = $getBookingChannel[0]['tax_included'];
										$tax_type = $getBookingChannel[0]['tax_type'];
										$tax_value = $getBookingChannel[0]['tax_value'];
										// --
										$roomtype_column_p = $roomtype_column."_pax_".$roomtype_pax;
										$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$roomtype_column_p'");
										if(count($column_is_exist) == 0){
											$roomtype_column_p = $roomtype_column;
										}
										// --
										$cnt2 = count($get_roomtype_rate);
										for($x2=0; $x2<$cnt2; $x2++){
											if($correction_ratio_type == "percentage"){ // percentage amount correction ratio
												$get_roomtype_rate[$x2][$roomtype_column_p] = $get_roomtype_rate[$x2][$roomtype_column_p] + ($get_roomtype_rate[$x2][$roomtype_column_p] * ($correction_ratio_value/100));
											}
											else{ // fixed amount correction ratio
												$get_roomtype_rate[$x2][$roomtype_column_p] += $correction_ratio_value;
											}

											if($tax_included == "no"){
												if($tax_type == "percentage"){ // percentage amount tax
													$get_roomtype_rate[$x2][$roomtype_column_p] = $get_roomtype_rate[$x2][$roomtype_column_p] + ($get_roomtype_rate[$x2][$roomtype_column_p] * ($tax_value/100));
												}
												else{ // fixed amount tax
													$get_roomtype_rate[$x2][$roomtype_column_p] += $tax_value;
												}
											}
											$totalamnt += $get_roomtype_rate[$x2][$roomtype_column_p];
											if($x2==0){
												$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column_p];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column_p];
											}
										}
									}
								}
								else{
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
										}
									}
								}
								
								/*HTL issue HTL-476 start*/
								$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
								$cost_type = $getBookingChannel[0]['comm_type'];
								$cost_comm = $getBookingChannel[0]['cost_comm'];
								$deposit_status = $getBookingChannel[0]['deposit_status'];
								$comm_status = $getBookingChannel[0]['commission_paid'];
							
								$deposit_total = 0;
								$paid_total = 0;
								$paid_comm = 0;
								
								if($deposit_status == 'paid'){
									$deposit_total = $roomtype_price;
								}
								
								if($cost_type == 'percent'){
									$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
								}else if($cost_type == 'fix'){
									$commission = floatval($cost_comm) + floatval($totalamnt);
								}else{
									$commission = 0;
								}
								
								if($comm_status == 'yes'){
									$paid_comm = $commission;
								}
								
								$paid_total = floatval($paid_comm) + floatval($deposit_total);
								/*HTL issue HTL-476 end*/
								
								/* if($channel_source == 'booking_xml'){
									$commission = $roomtype_price * 0.15;
								}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
									$commission = 0;
								}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
									$commission = 0;
								}else{
									$commission = 0;
								} */
								
								$tariffa = $roomtype_name."#@&".$totalamnt;
								$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);
								$ran_code = $this->randomString();
						
								/*$reservationInsert = array(
											':reservation_conn_id' => $lastresvConnId,
											':clients_id' => $lastClientId, 
											':appartments_id' => $idappartamenti,
											':date_start_id' => $checkin_id, 
											':date_end_id' => $checkout_id, 
											':assign_app' => 'k', 
											':pax' => $roomtype_pax, 
											':original_rate' => $tariffa,
											':rate' => $tariffa, 
											':weekly_rates' => $tariffesettimanali,
											':commissions' => $commission,
											':rate_total' => $totalamnt,
											':deposit' => $deposit_total,
											':paid' => $paid_total,
											':code' => $ran_code,
											':confirmation' => 'S',
											':status' => 'active',
											':inserted_date' => C_DIFF_ORE,
											':inserted_host' => $host,
											':inserted_by' => '1'
										);
								$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
								
								
								$reservOctorateBookInsert = array(
										':idbooking_octo' => $id_reservation,
										':idprenota' => $lastReservationId,
										':year' => $year,
										':status' => 'active'
									);
								$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
								
								$bookingsInsert = array(
										':reservation_id' => $lastReservationId,
										':bookingsource_id' => $book_id,
										':refnumber' => $id_reservation,
										':chnnl_manager_id_res' => $roomtype_id_bb,
										':year' => $year,
										':status' => 'active'
									);
								$this->execute_insert($sqlBookings,$bookingsInsert);
								
								$bookingNotesInsert = array(
										':reservation_id' => $lastReservationId,
										':note_type_id' => 1,
										':est_hours' => "",
										':est_mins' => "",
										':note' => $client_notes,
										':status' => 'active'
									);
								$this->execute_insert($sqlNotes,$bookingNotesInsert);*/
								//orig space before room split
								if($idappartamenti > 0){
									$reservationInsert = array(
												':reservation_conn_id' => $lastresvConnId,
												':clients_id' => $lastClientId, 
												':appartments_id' => $idappartamenti,
												':date_start_id' => $checkin_id, 
												':date_end_id' => $checkout_id, 
												':assign_app' => 'k', 
												':pax' => $roomtype_pax, 
												':original_rate' => $tariffa,
												':rate' => $tariffa, 
												':weekly_rates' => $tariffesettimanali,
												':commissions' => $commission,
												':rate_total' => $totalamnt,
												':deposit' => $deposit_total,
												':paid' => $paid_total,
												':code' => $ran_code,
												':confirmation' => 'S',
												':split_from' => 0,
												':status' => 'active',
												':inserted_date' => $booking_date_time,
												':inserted_host' => $host,
												':inserted_by' => '1'
											);
									$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
									
									
									$reservOctorateBookInsert = array(
											':idbooking_octo' => $id_reservation,
											':idprenota' => $lastReservationId,
											':year' => $year,
											':status' => 'active'
										);
									$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
									
									$bookingsInsert = array(
											':reservation_id' => $lastReservationId,
											':bookingsource_id' => $book_id,
											':refnumber' => $id_reservation,
											':chnnl_manager_id_res' => $roomtype_id_bb,
											':year' => $year,
											':status' => 'active'
										);
									$this->execute_insert($sqlBookings,$bookingsInsert);
									
									$bookingNotesInsert = array(
											':reservation_id' => $lastReservationId,
											':note_type_id' => 1,
											':est_hours' => "",
											':est_mins' => "",
											':note' => $client_notes,
											':status' => 'active'
										);
									$this->execute_insert($sqlNotes,$bookingNotesInsert);
								}
								else{
									$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

									if($split_rooms == false){
										# create the booking with no allocated room
										$reservationInsert = array(
													':reservation_conn_id' => $lastresvConnId,
													':clients_id' => $lastClientId, 
													':appartments_id' => 0,
													':date_start_id' => $checkin_id, 
													':date_end_id' => $checkout_id, 
													':assign_app' => 'k', 
													':pax' => $roomtype_pax, 
													':original_rate' => $tariffa,
													':rate' => $tariffa, 
													':weekly_rates' => $tariffesettimanali,
													':commissions' => $commission,
													':rate_total' => $totalamnt,
													':deposit' => $deposit_total,
													':paid' => $paid_total,
													':code' => $ran_code,
													':confirmation' => 'S',
													':split_from' => 0,
													':status' => 'active',
													':inserted_date' => $booking_date_time,
													':inserted_host' => $host,
													':inserted_by' => '1'
												);
										$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
										
										
										$reservOctorateBookInsert = array(
												':idbooking_octo' => $id_reservation,
												':idprenota' => $lastReservationId,
												':year' => $year,
												':status' => 'active'
											);
										$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
										
										$bookingsInsert = array(
												':reservation_id' => $lastReservationId,
												':bookingsource_id' => $book_id,
												':refnumber' => $id_reservation,
												':chnnl_manager_id_res' => $roomtype_id_bb,
												':year' => $year,
												':status' => 'active'
											);
										$this->execute_insert($sqlBookings,$bookingsInsert);
										
										$bookingNotesInsert = array(
												':reservation_id' => $lastReservationId,
												':note_type_id' => 1,
												':est_hours' => "",
												':est_mins' => "",
												':note' => $client_notes,
												':status' => 'active'
											);
										$this->execute_insert($sqlNotes,$bookingNotesInsert);

										$socket_json_message = array(
											'action' => 'notify',
											'message' => "There's a reservation with unallocated room! REF#".$id_reservation.", Client: ".$client_name,
											'host' => $_SERVER['SERVER_NAME'],
											'inserted_by' => 1,
											'reservation_link' => '#/modify-booking/$lastresvConnId/$id_reservation',
											'notification_type' => '1'
										);

										$this->log_activity($socket_json_message, true);
									}
									else{
										# proceed splitting the room
										$split_from = 0;
										$period_Arr = range($checkin_id, $checkout_id);
										$weekly_rates_Arr = explode(",",$tariffesettimanali);
										$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
										$commission_temp = $commission;
										$deposit_total_temp = $deposit_total;
										$paid_total_temp = $paid_total;
										
										for($a=0; $a<count($split_rooms); $a++){
											$room_id_split = $split_rooms[$a]["room_id"];
											$date_start_id_split = $split_rooms[$a]["date_start_id"];
											$date_end_id_split = $split_rooms[$a]["date_end_id"];
											$weekly_rates_s = ""; # weekly_rates
											$rate_total_s = 0; # rate_total
											$commission_s = 0; # commission
											$deposit_total_s = 0; # deposit_total
											$paid_total_s = 0; # 

											for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
												$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
												if($b==$date_start_id_split){ 
													$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
												}
												else{ 
													$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
												}
											}
											$rate_s = $roomtype_name."#@&".$rate_total_s;

											if($rate_total_s >= $commission_temp){ # recalculate commissions
												$commission_s = $commission_temp;
												$commission_temp = 0;
											}
											else{
												$commission_s = $rate_total_s;
												$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
											}
											if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
												$deposit_total_s = $deposit_total_temp;
												$deposit_total_temp = 0;
											}
											else{
												$deposit_total_s = $rate_total_s;
												$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
											}
											if($rate_total_s >= $paid_total_temp){ # recalculate paid
												$paid_total_s = $paid_total_temp;
												$paid_total_temp = 0;
											}
											else{
												$paid_total_s = $rate_total_s;
												$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
											}

											$reservationInsert = array(
														':reservation_conn_id' => $lastresvConnId,
														':clients_id' => $lastClientId, 
														':appartments_id' => $room_id_split,
														':date_start_id' => $date_start_id_split, 
														':date_end_id' => $date_end_id_split, 
														':assign_app' => 'k', 
														':pax' => $roomtype_pax, 
														':original_rate' => $rate_s,
														':rate' => $rate_s, 
														':weekly_rates' => $weekly_rates_s,
														':commissions' => $commission_s,
														':rate_total' => $rate_total_s,
														':deposit' => $deposit_total_s,
														':paid' => $paid_total_s,
														':code' => $ran_code,
														':confirmation' => 'S',
														':split_from' => $split_from,
														':status' => 'active',
														':inserted_date' => $booking_date_time,
														':inserted_host' => $host,
														':inserted_by' => '1'
													);
											$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
											
											if($a == 0){ 
												$split_from = $lastReservationId; 
											}
											
											$bookingNotesInsert = array(
													':reservation_id' => $lastReservationId,
													':note_type_id' => 1,
													':est_hours' => "",
													':est_mins' => "",
													':note' => $client_notes,
													':status' => 'active'
												);
											$this->execute_insert($sqlNotes,$bookingNotesInsert);

										} # for($a=0; $a<count($split_rooms); $a++)

										$reservOctorateBookInsert = array(
													':idbooking_octo' => $id_reservation,
													':idprenota' => $split_from,
													':year' => $year,
													':status' => 'active'
												);
										$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
											
										$bookingsInsert = array(
													':reservation_id' => $split_from,
													':bookingsource_id' => $book_id,
													':refnumber' => $id_reservation,
													':chnnl_manager_id_res' => $roomtype_id_bb,
													':year' => $year,
													':status' => 'active'
												);
										$this->execute_insert($sqlBookings,$bookingsInsert);
										// HTL-612 fix ------------
										$activity_log_message = array(
											'action' => "notify",
											'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
											'host' => $_SERVER['SERVER_NAME'],
											'inserted_by' => '1',
											'reservation_link' => '#/split_rooms/[room_split_id]',
											'notification_type' => '1'
										);
										$this->split_rooms_log_activity($split_from, $activity_log_message);
										// HTL-612 fix ------------
									}

								}

								$this->val_rules_engine(2, $roomtypeId, $checkin_id, $checkout_id); # room optimize ni
							}
						}
					}
				//}
			}
			echo "success";
			/* send email notification for new reservation with OTA */
			$this->prepare_new_reservation_email($lastresvConnId);	
		}catch(PDOException $e) {
			/* var_dump($result); */
			$socket_json_message = array(
				'action' => 'reservation',
				'message' => "Error Receiving New Reservation, REF#" . $id . " with error message: " . $e->getMessage(),
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => 1,
				'reservation_link' => '#/notification',
				'notification_type' => '1'
			);

			$this->log_activity($socket_json_message, false);
			
			/* echo $room_cnt; */
		}
	}
	/* end add octorate booking OTA */
	
	/* start add octorate booking OTA */
	public function octorate_booking_1($bookings, $id){
		$result = $bookings;
		
		$year = date("Y",(time() + (C_DIFF_ORE * 3600)));	#--Selected Year
		$date_raw = date("Y-m-d");
		$date_raw_now = date("Y-m-d H:i:s");
		$book_id = 0;
		
		$sqlReservation = "INSERT INTO 
				reservation(
					reservation_conn_id,
					clients_id, 
					appartments_id, 
					date_start_id, 
					date_end_id, 
					assign_app, 
					pax,
					original_rate,
					rate, 
					weekly_rates,
					commissions,
					rate_total,
					deposit,
					paid,
					code,
					confirmation,
					split_from,
					status,
					inserted_date,
					inserted_host,
					inserted_by
				) 
				VALUES(
					:reservation_conn_id,
					:clients_id, 
					:appartments_id, 
					:date_start_id, 
					:date_end_id, 
					:assign_app, 
					:pax, 
					:original_rate,
					:rate, 
					:weekly_rates,
					:commissions,
					:rate_total,
					:deposit,
					:paid,
					:code,
					:confirmation,
					:split_from,
					:status,
					:inserted_date,
					:inserted_host,
					:inserted_by
				)";
				
		$sqlClient = "INSERT INTO 
						clients(
							surname, 
							home_address,
							street,
							city,
							nation,
						 	nationality,
							phone,
							reference_email,
							inserted_date, 
							inserted_host, 
							inserted_by
						) 
						VALUES(
							:surname,
							:home_address,
							:street,
							:city,
							:nation,
							:nationality,
							:phone,
							:email,
							:inserted_date,
							:inserted_host,
							:inserted_by
						)";
						
		$sqlReservConn = "INSERT INTO 
					reservation_conn(
						client_id, 
						date_start_id,
						date_end_id,
						bookingsource_id,
						reference_num,
						status,
						date_inserted,
						inserted_by
					) 
					VALUES(
						:client_id, 
						:date_start_id,
						:date_end_id,
						:bookingsource_id,
						:reference_num,
						:status,
						NOW() + INTERVAL :date_inserted HOUR, 
						:inserted_by
					)";
					
		$sqlReservOctorateBook = "INSERT INTO 
					octorate_bookings(
						idbooking_octo, 
						idprenota,
						year,
						status
					) 
					VALUES(
						:idbooking_octo, 
						:idprenota,
						:year,
						:status
					)";
		
		$sqlBookings = "INSERT INTO 
					bookings(
						reservation_id, 
						bookingsource_id,
						refnumber,
						chnnl_manager_id_res,
						year,
						status
					) 
					VALUES(
						:reservation_id, 
						:bookingsource_id,
						:refnumber,
						:chnnl_manager_id_res,
						:year,
						:status
					)";
					
		$sqlNotes = "INSERT INTO 
					reservation_notes(
						reservation_id, 
						note_type_id,
						est_hours,
						est_mins,
						note,
						status
					) 
					VALUES(
						:reservation_id, 
						:note_type_id,
						:est_hours,
						:est_mins,
						:note,
						:status
					)";
		
		
		try{
			$choices = array();
			$resv_sched_cleaning = array();
			$client_notes = "";
			$client_phone = "";
			$client_city = "";
			$client_address = "";
			$client_nationality = "";
			$client_country = "";
			
			$date_created = $result['Bookings']['Booking']['ResCreationDate'];
			$id_reservation = $result['Bookings']['Booking']['ResId'];
			$bbliverateId = $result['Bookings']['Booking']['BbliverateId'];
			if(isset($result['Bookings']['Booking']['BbliverateTimestamp'])) {
				$date_time = new DateTime($result['Bookings']['Booking']['BbliverateTimestamp']);
				$date_time = $date_time->modify('+6 hour');
        
				$booking_date_time = $date_time->format('Y-m-d H:i:s');
			} else {
				$t_difference = C_DIFF_ORE;
				$time = $this->executeQuery("SELECT NOW() + INTERVAL '$t_difference' HOUR AS datetime");
				$booking_date_time = $time[0]['datetime'];
			}
			
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'])) {
				$client_name = $result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'];
			}else{
				$client_name = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerFName'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerNote'])){
				$client_notes = $result['Bookings']['Booking']['Customers']['Customer']['CustomerNote'];
			}else{
				$client_notes = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerNote'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerPhone'])){
				$client_phone = $result['Bookings']['Booking']['Customers']['Customer']['CustomerPhone'];
			}else{
				$client_phone = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerPhone'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerCity'])){
				$client_city = $result['Bookings']['Booking']['Customers']['Customer']['CustomerCity'];
			}else{
				$client_city = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerCity'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerAddress'])){
				$client_address = $result['Bookings']['Booking']['Customers']['Customer']['CustomerAddress'];
			}else{
				$client_address = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerAddress'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerNationality'])){
				$client_nationality = $result['Bookings']['Booking']['Customers']['Customer']['CustomerNationality'];
			}else{
				$client_nationality = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerNationality'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerCountry'])){
				$client_country = $result['Bookings']['Booking']['Customers']['Customer']['CustomerCountry'];
			}else{
				$client_country = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerCountry'];
			}
			if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerEmail'])){
				$client_email = $result['Bookings']['Booking']['Customers']['Customer']['CustomerEmail'];
			}else{
				$client_email = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerEmail'];
			}
			
			$channel_id = $result['Bookings']['Booking']['Channel'];
			if(!isset($result['Bookings']['Booking']['Channel'])) {
				$channel_id = 96;
			}
			
			$start_date = $result['Bookings']['Booking']['StartDate'];
			$checkin_id = $this->getIdperiod($start_date, "start_date");
			$end_date = $result['Bookings']['Booking']['EndDate'];
			$checkout_id = $this->getIdperiod($end_date, "end_date");
			$channel_source = $result['Bookings']['Booking']['ResSource'];
			$check_res =  $this->executeQuery("SELECT * FROM octorate_bookings WHERE idbooking_octo = '$id_reservation'");
			$host = $_SERVER['SERVER_NAME'];
			$lastClientId = 0;
			$lastresvConnId = 0;
			
			if($channel_source != '294e1dc2b907ed0496e51572c3ef081e'){
				//if(count($check_res) == 0){
					$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
					$id_property = $getPropertyId[0]['property_id'];
					
					if($client_country != ""){
						$getFullCountry = $this->executeQuery("SELECT * FROM nations WHERE code2_nation LIKE '%$client_country%'");
						if(count($getFullCountry) > 0){
							$country_client = $getFullCountry[0]['nation_name'];
						}else{
							$country_client = "";
						}
					}else{
						$country_client = "";
					}
					if($client_nationality != ""){
						$getFullNation = $this->executeQuery("SELECT * FROM nations WHERE code2_nation LIKE '%$client_nationality%'");
						if(count($getFullNation) > 0){
							$nation_client = $getFullNation[0]['nation_name'];
						}else{
							$nation_client = "";
						}
					}else{
						$nation_client = "";
					}
					
					$get_clients =  $this->executeQuery("SELECT * FROM clients WHERE surname = '$client_name'");
					if(count($get_clients) == 0){
						$clientInsert = array(
								':surname' => $client_name,
								':home_address' => $client_address,
								':street' => $client_address,
								':city' => $client_city,
								':nation' => $country_client,
								':nationality' => $nation_client,
								':phone' => $client_phone,
								':email' => $client_email,
								':inserted_date' => $date_raw_now,
								':inserted_host' => $host,
								':inserted_by' => '1'
							);
						$lastClientId = $this->execute_insert_getId($sqlClient,$clientInsert);
					}
					else{
						$lastClientId = $get_clients[0]['clients_id'];
					}
					
					/*HTL issue HTL-476 start*/
					$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
					$book_id = $getBookingChannel[0]['booking_source_id'];
					/*HTL issue HTL-476 end*/
					
					/* if($channel_source == 'booking_xml'){
						$book_id = 3;
					}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
						$book_id = 6;
					}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
						$book_id = 8;
					}else if($channel_source == 'hostelworld_xml' || $channel_source == 'hostelworld'){
						$book_id = 10;
					} */
					
					$getExistingResvConn = $this->executeQuery("SELECT * FROM reservation_conn WHERE reference_num = '$id_reservation'");
					if(count($getExistingResvConn) > 0){
						$lastresvConnId = $getExistingResvConn[0]['reservation_conn_id'];
					}else{
						$resvConnInsert = array(
									':client_id' => $lastClientId,
									':date_start_id' => $checkin_id,
									':date_end_id' => $checkout_id,
									':bookingsource_id' => $book_id,
									':reference_num' => $id_reservation,
									':status' => 'active',
									':date_inserted' => C_DIFF_ORE,
									':inserted_by' => 1
								);
						$lastresvConnId = $this->execute_insert_getId($sqlReservConn,$resvConnInsert);
					}
					
					$rooms = $result['Bookings']['Booking']['Rooms']['Room'];
					$room_cnt = count($rooms);

					if(isset($rooms['StartDate'])) {	
						$roomtype_id = $rooms['RoomTypeIds']['RoomTypeId'];
						$roomtype_id_bb = $rooms['BbliverateNumberId'];
						$roomtype_pax = $rooms['Pax'];
						$roomtype_price = $rooms['Price'];
						
						if($id_property == '274690'){
							/* if($roomtype_id == '148589'){ */
							if($roomtype_id == '148590'){
								if($roomtype_pax < 3 || $roomtype_pax < '3'){
									$roomtype_pax = 3;
								}
							}else if($roomtype_id == '148593'){
								if($roomtype_pax < 3 || $roomtype_pax < '3'){
									$roomtype_pax = 3;
								}
							}
						}
						
						$getExistingBb_id = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = $roomtype_id_bb");
						if(count($getExistingBb_id) == 0){
							$get_roomtype =  $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
							$roomtypeId = $get_roomtype[0]['roomtype_id'];

							$get_roomtype_name =  $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
							$roomtype_name = $get_roomtype_name[0]['name'];
							$roomtype_column = $get_roomtype_name[0]['associated_column'];

							$get_roomtype_rate =  $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
							$totalamnt = 0;
							$tariffesettimanali = "";
							$commission = 0;
							
							if($id_property == '274690'){
								/*$cntDaily = count($rooms['DayByDayPrice']['price']);
								for($xx = 0; $xx < $cntDaily; $xx++){
									$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
									if($xx==0){
										$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
									}else{
										$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
									}
								}*/
								// HTL-626 fix : START
								if(array_key_exists("DayByDayPrice",$rooms)){
									$cntDaily = count($rooms['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
										}
									}
								}
								else{
									$rate_column_temp = $roomtype_column."_pax_".$roomtype_pax;
									$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$rate_column_temp'");
									if(count($column_is_exist) == 0){
										$rate_column_temp = $roomtype_column;
									}
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$rate_column_temp];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$rate_column_temp];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$rate_column_temp];
										}
									}
								}
								// HTL-626 fix : END
							}else{
								$cnt2 = count($get_roomtype_rate);
								for($x2=0; $x2<$cnt2; $x2++){
									$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
									if($x2==0){
										$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
									}else{
										$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
									}
								}
							}
							
							/*HTL issue HTL-476 start*/
							$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
							$cost_type = $getBookingChannel[0]['comm_type'];
							$cost_comm = $getBookingChannel[0]['cost_comm'];
							$deposit_status = $getBookingChannel[0]['deposit_status'];
							$comm_status = $getBookingChannel[0]['commission_paid'];
							
							$deposit_total = 0;
							$paid_total = 0;
							$paid_comm = 0;
							
							if($deposit_status == 'paid'){
								$deposit_total = $roomtype_price;
							}
							
							if($cost_type == 'percent'){
								$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
							}else if($cost_type == 'fix'){
								$commission = floatval($cost_comm) + floatval($totalamnt);
							}else{
								$commission = 0;
							}
							
							if($comm_status == 'yes'){
								$paid_comm = $commission;
							}
							
							$paid_total = floatval($paid_comm) + floatval($deposit_total);
							/*HTL issue HTL-476 end*/
							
							/* if($channel_source == 'booking_xml'){
								$commission = $roomtype_price * 0.15;
							}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
								$commission = 0;
							}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
								$commission = 0;
							}else{
								$commission = 0;
							} */
							
							$tariffa = $roomtype_name."#@&".$totalamnt;
							$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);
							$ran_code = $this->randomString();
					
							/*$reservationInsert = array(
										':reservation_conn_id' => $lastresvConnId,
										':clients_id' => $lastClientId, 
										':appartments_id' => $idappartamenti,
										':date_start_id' => $checkin_id, 
										':date_end_id' => $checkout_id, 
										':assign_app' => 'k', 
										':pax' => $roomtype_pax, 
										':original_rate' => $tariffa,
										':rate' => $tariffa, 
										':weekly_rates' => $tariffesettimanali,
										':commissions' => $commission,
										':rate_total' => $totalamnt,
										':deposit' => $deposit_total,
										':paid' => $paid_total, 
										':code' => $ran_code,
										':confirmation' => 'S',
										':status' => 'active',
										':inserted_date' => C_DIFF_ORE,
										':inserted_host' => $host,
										':inserted_by' => '1'
									);
							$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
							
							$reservOctorateBookInsert = array(
									':idbooking_octo' => $id_reservation,
									':idprenota' => $lastReservationId,
									':year' => $year,
									':status' => 'active'
								);
							$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
							
							$bookingsInsert = array(
									':reservation_id' => $lastReservationId,
									':bookingsource_id' => $book_id,
									':refnumber' => $id_reservation,
									':chnnl_manager_id_res' => $roomtype_id_bb,
									':year' => $year,
									':status' => 'active'
								);
							$this->execute_insert($sqlBookings,$bookingsInsert);
							
							$bookingNotesInsert = array(
									':reservation_id' => $lastReservationId,
									':note_type_id' => 1,
									':est_hours' => "",
									':est_mins' => "",
									':note' => $client_notes,
									':status' => 'active'
								);
							$this->execute_insert($sqlNotes,$bookingNotesInsert);*/

							//orig space before room split
							if($idappartamenti > 0){
								$reservationInsert = array(
											':reservation_conn_id' => $lastresvConnId,
											':clients_id' => $lastClientId, 
											':appartments_id' => $idappartamenti,
											':date_start_id' => $checkin_id, 
											':date_end_id' => $checkout_id, 
											':assign_app' => 'k', 
											':pax' => $roomtype_pax, 
											':original_rate' => $tariffa,
											':rate' => $tariffa, 
											':weekly_rates' => $tariffesettimanali,
											':commissions' => $commission,
											':rate_total' => $totalamnt,
											':deposit' => $deposit_total,
											':paid' => $paid_total,
											':code' => $ran_code,
											':confirmation' => 'S',
											':split_from' => 0,
											':status' => 'active',
											':inserted_date' => $booking_date_time,
											':inserted_host' => $host,
											':inserted_by' => '1'
											);
								$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	

								$reservOctorateBookInsert = array(
											':idbooking_octo' => $id_reservation,
											':idprenota' => $lastReservationId,
											':year' => $year,
											':status' => 'active'
										);
								$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
									
								$bookingsInsert = array(
											':reservation_id' => $lastReservationId,
											':bookingsource_id' => $book_id,
											':refnumber' => $id_reservation,
											':chnnl_manager_id_res' => $roomtype_id_bb,
											':year' => $year,
											':status' => 'active'
										);
								$this->execute_insert($sqlBookings,$bookingsInsert);
									
								$bookingNotesInsert = array(
											':reservation_id' => $lastReservationId,
											':note_type_id' => 1,
											':est_hours' => "",
											':est_mins' => "",
											':note' => $client_notes,
											':status' => 'active'
										);
								$this->execute_insert($sqlNotes,$bookingNotesInsert);
							}
							else{
								$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

								$split_from = 0;
								$period_Arr = range($checkin_id, $checkout_id);
								$weekly_rates_Arr = explode(",",$tariffesettimanali);
								$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
								$commission_temp = $commission;
								$deposit_total_temp = $deposit_total;
								$paid_total_temp = $paid_total;
									
								for($a=0; $a<count($split_rooms); $a++){
									$room_id_split = $split_rooms[$a]["room_id"];
									$date_start_id_split = $split_rooms[$a]["date_start_id"];
									$date_end_id_split = $split_rooms[$a]["date_end_id"];
									$weekly_rates_s = ""; # weekly_rates
									$rate_total_s = 0; # rate_total
									$commission_s = 0; # commission
									$deposit_total_s = 0; # deposit_total
									$paid_total_s = 0; # 

									for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
										$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
										if($b==$date_start_id_split){ 
											$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
										}
										else{ 
											$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
										}
									}
									$rate_s = $roomtype_name."#@&".$rate_total_s;

									if($rate_total_s >= $commission_temp){ # recalculate commissions
										$commission_s = $commission_temp;
										$commission_temp = 0;
									}
									else{
										$commission_s = $rate_total_s;
										$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
									}
									if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
										$deposit_total_s = $deposit_total_temp;
										$deposit_total_temp = 0;
									}
									else{
										$deposit_total_s = $rate_total_s;
										$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
									}
									if($rate_total_s >= $paid_total_temp){ # recalculate paid
										$paid_total_s = $paid_total_temp;
										$paid_total_temp = 0;
									}
									else{
										$paid_total_s = $rate_total_s;
										$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
									}

									$reservationInsert = array(
												':reservation_conn_id' => $lastresvConnId,
												':clients_id' => $lastClientId, 
												':appartments_id' => $room_id_split,
												':date_start_id' => $date_start_id_split, 
												':date_end_id' => $date_end_id_split, 
												':assign_app' => 'k', 
												':pax' => $roomtype_pax, 
												':original_rate' => $rate_s,
												':rate' => $rate_s, 
												':weekly_rates' => $weekly_rates_s,
												':commissions' => $commission_s,
												':rate_total' => $rate_total_s,
												':deposit' => $deposit_total_s,
												':paid' => $paid_total_s,
												':code' => $ran_code,
												':confirmation' => 'S',
												':split_from' => $split_from,
												':status' => 'active',
												':inserted_date' => $booking_date_time,
												':inserted_host' => $host,
												':inserted_by' => '1'
											);
									$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
										
									if($a == 0){ 
										$split_from = $lastReservationId; 
									}
										
									$bookingNotesInsert = array(
												':reservation_id' => $lastReservationId,
												':note_type_id' => 1,
												':est_hours' => "",
												':est_mins' => "",
												':note' => $client_notes,
												':status' => 'active'
											);
									$this->execute_insert($sqlNotes,$bookingNotesInsert);

								} # for($a=0; $a<count($split_rooms); $a++)

								$reservOctorateBookInsert = array(
												':idbooking_octo' => $id_reservation,
												':idprenota' => $split_from,
												':year' => $year,
												':status' => 'active'
											);
								$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
										
								$bookingsInsert = array(
												':reservation_id' => $split_from,
												':bookingsource_id' => $book_id,
												':refnumber' => $id_reservation,
												':chnnl_manager_id_res' => $roomtype_id_bb,
												':year' => $year,
												':status' => 'active'
											);
								$this->execute_insert($sqlBookings,$bookingsInsert);

								$activity_log_message = array(
									'action' => "notify",
									'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
									'host' => $_SERVER['SERVER_NAME'],
									'inserted_by' => '1',
									'reservation_link' => '#/split_rooms/[room_split_id]',
									'notification_type' => '1'
								);
								$this->split_rooms_log_activity($split_from, $activity_log_message);

							}	
								
							$this->val_rules_engine(2, $roomtypeId, $checkin_id, $checkout_id); # room optimize ni
						}
					} 
					else {
						for($x=0; $x<$room_cnt; $x++){
							$roomtype_id = $rooms[$x]['RoomTypeIds']['RoomTypeId'];
							$roomtype_id_bb = $rooms[$x]['BbliverateNumberId'];
							$roomtype_pax = $rooms[$x]['Pax'];
							$roomtype_price = $rooms[$x]['Price'];
							
							if($id_property == '274690'){
								/* if($roomtype_id == '148589'){ */
								if($roomtype_id == '148590'){
									if($roomtype_pax < 3 || $roomtype_pax < '3'){
										$roomtype_pax = 3;
									}
								}else if($roomtype_id == '148593'){
									if($roomtype_pax < 3 || $roomtype_pax < '3'){
										$roomtype_pax = 3;
									}
								}
							}
							
							$getExistingBb_id = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = $roomtype_id_bb");
							if(count($getExistingBb_id) == 0){
								$get_roomtype = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
								$roomtypeId = $get_roomtype[0]['roomtype_id'];

								$get_roomtype_name = $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
								$roomtype_name = $get_roomtype_name[0]['name'];
								$roomtype_column = $get_roomtype_name[0]['associated_column'];

								$get_roomtype_rate = $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
								$totalamnt = 0;
								$tariffesettimanali = "";
								$commission = 0;
								
								if($id_property == '274690'){
									/*$cntDaily = count($rooms[$x]['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms[$x]['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms[$x]['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms[$x]['DayByDayPrice']['price'][$xx];
										}
									}*/
									// HTL-626 fix : START
									if(array_key_exists("DayByDayPrice",$rooms)){
										$cntDaily = count($rooms['DayByDayPrice']['price']);
										for($xx = 0; $xx < $cntDaily; $xx++){
											$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
											if($xx==0){
												$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
											}
										}
									}
									else{
										$rate_column_temp = $roomtype_column."_pax_".$roomtype_pax;
										$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$rate_column_temp'");
										if(count($column_is_exist) == 0){
											$rate_column_temp = $roomtype_column;
										}
										$cnt2 = count($get_roomtype_rate);
										for($x2=0; $x2<$cnt2; $x2++){
											$totalamnt += $get_roomtype_rate[$x2][$rate_column_temp];
											if($x2==0){
												$tariffesettimanali = $get_roomtype_rate[$x2][$rate_column_temp];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$rate_column_temp];
											}
										}
									}
									// HTL-626 fix : END
								}else{
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
										}
									}
								}
								
								/*HTL issue HTL-476 start*/
								$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
								$cost_type = $getBookingChannel[0]['comm_type'];
								$cost_comm = $getBookingChannel[0]['cost_comm'];
								$deposit_status = $getBookingChannel[0]['deposit_status'];
								$comm_status = $getBookingChannel[0]['commission_paid'];
							
								$deposit_total = 0;
								$paid_total = 0;
								$paid_comm = 0;
								
								if($deposit_status == 'paid'){
									$deposit_total = $roomtype_price;
								}
								
								if($cost_type == 'percent'){
									$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
								}else if($cost_type == 'fix'){
									$commission = floatval($cost_comm) + floatval($totalamnt);
								}else{
									$commission = 0;
								}
								
								if($comm_status == 'yes'){
									$paid_comm = $commission;
								}
								
								$paid_total = floatval($paid_comm) + floatval($deposit_total);
								/*HTL issue HTL-476 end*/
								
								/* if($channel_source == 'booking_xml'){
									$commission = $roomtype_price * 0.15;
								}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
									$commission = 0;
								}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
									$commission = 0;
								}else{
									$commission = 0;
								} */
								
								$tariffa = $roomtype_name."#@&".$totalamnt;
								$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);
								$ran_code = $this->randomString();
						
								/*$reservationInsert = array(
											':reservation_conn_id' => $lastresvConnId,
											':clients_id' => $lastClientId, 
											':appartments_id' => $idappartamenti,
											':date_start_id' => $checkin_id, 
											':date_end_id' => $checkout_id, 
											':assign_app' => 'k', 
											':pax' => $roomtype_pax, 
											':original_rate' => $tariffa,
											':rate' => $tariffa, 
											':weekly_rates' => $tariffesettimanali,
											':commissions' => $commission,
											':rate_total' => $totalamnt,
											':deposit' => $deposit_total,
											':paid' => $paid_total,
											':code' => $ran_code,
											':confirmation' => 'S',
											':status' => 'active',
											':inserted_date' => C_DIFF_ORE,
											':inserted_host' => $host,
											':inserted_by' => '1'
										);
								$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
								
								
								$reservOctorateBookInsert = array(
										':idbooking_octo' => $id_reservation,
										':idprenota' => $lastReservationId,
										':year' => $year,
										':status' => 'active'
									);
								$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
								
								$bookingsInsert = array(
										':reservation_id' => $lastReservationId,
										':bookingsource_id' => $book_id,
										':refnumber' => $id_reservation,
										':chnnl_manager_id_res' => $roomtype_id_bb,
										':year' => $year,
										':status' => 'active'
									);
								$this->execute_insert($sqlBookings,$bookingsInsert);
								
								$bookingNotesInsert = array(
										':reservation_id' => $lastReservationId,
										':note_type_id' => 1,
										':est_hours' => "",
										':est_mins' => "",
										':note' => $client_notes,
										':status' => 'active'
									);
								$this->execute_insert($sqlNotes,$bookingNotesInsert);*/
								//orig space before room split
								if($idappartamenti > 0){
									$reservationInsert = array(
												':reservation_conn_id' => $lastresvConnId,
												':clients_id' => $lastClientId, 
												':appartments_id' => $idappartamenti,
												':date_start_id' => $checkin_id, 
												':date_end_id' => $checkout_id, 
												':assign_app' => 'k', 
												':pax' => $roomtype_pax, 
												':original_rate' => $tariffa,
												':rate' => $tariffa, 
												':weekly_rates' => $tariffesettimanali,
												':commissions' => $commission,
												':rate_total' => $totalamnt,
												':deposit' => $deposit_total,
												':paid' => $paid_total,
												':code' => $ran_code,
												':confirmation' => 'S',
												':split_from' => 0,
												':status' => 'active',
												':inserted_date' => $booking_date_time,
												':inserted_host' => $host,
												':inserted_by' => '1'
											);
									$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
									
									
									$reservOctorateBookInsert = array(
											':idbooking_octo' => $id_reservation,
											':idprenota' => $lastReservationId,
											':year' => $year,
											':status' => 'active'
										);
									$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
									
									$bookingsInsert = array(
											':reservation_id' => $lastReservationId,
											':bookingsource_id' => $book_id,
											':refnumber' => $id_reservation,
											':chnnl_manager_id_res' => $roomtype_id_bb,
											':year' => $year,
											':status' => 'active'
										);
									$this->execute_insert($sqlBookings,$bookingsInsert);
									
									$bookingNotesInsert = array(
											':reservation_id' => $lastReservationId,
											':note_type_id' => 1,
											':est_hours' => "",
											':est_mins' => "",
											':note' => $client_notes,
											':status' => 'active'
										);
									$this->execute_insert($sqlNotes,$bookingNotesInsert);
								}
								else{
									$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

									$split_from = 0;
									$period_Arr = range($checkin_id, $checkout_id);
									$weekly_rates_Arr = explode(",",$tariffesettimanali);
									$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
									$commission_temp = $commission;
									$deposit_total_temp = $deposit_total;
									$paid_total_temp = $paid_total;
									
									for($a=0; $a<count($split_rooms); $a++){
										$room_id_split = $split_rooms[$a]["room_id"];
										$date_start_id_split = $split_rooms[$a]["date_start_id"];
										$date_end_id_split = $split_rooms[$a]["date_end_id"];
										$weekly_rates_s = ""; # weekly_rates
										$rate_total_s = 0; # rate_total
										$commission_s = 0; # commission
										$deposit_total_s = 0; # deposit_total
										$paid_total_s = 0; # 

										for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
											$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
											if($b==$date_start_id_split){ 
												$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
											}
											else{ 
												$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
											}
										}
										$rate_s = $roomtype_name."#@&".$rate_total_s;

										if($rate_total_s >= $commission_temp){ # recalculate commissions
											$commission_s = $commission_temp;
											$commission_temp = 0;
										}
										else{
											$commission_s = $rate_total_s;
											$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
											$deposit_total_s = $deposit_total_temp;
											$deposit_total_temp = 0;
										}
										else{
											$deposit_total_s = $rate_total_s;
											$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $paid_total_temp){ # recalculate paid
											$paid_total_s = $paid_total_temp;
											$paid_total_temp = 0;
										}
										else{
											$paid_total_s = $rate_total_s;
											$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
										}

										$reservationInsert = array(
													':reservation_conn_id' => $lastresvConnId,
													':clients_id' => $lastClientId, 
													':appartments_id' => $room_id_split,
													':date_start_id' => $date_start_id_split, 
													':date_end_id' => $date_end_id_split, 
													':assign_app' => 'k', 
													':pax' => $roomtype_pax, 
													':original_rate' => $rate_s,
													':rate' => $rate_s, 
													':weekly_rates' => $weekly_rates_s,
													':commissions' => $commission_s,
													':rate_total' => $rate_total_s,
													':deposit' => $deposit_total_s,
													':paid' => $paid_total_s,
													':code' => $ran_code,
													':confirmation' => 'S',
													':split_from' => $split_from,
													':status' => 'active',
													':inserted_date' => $booking_date_time,
													':inserted_host' => $host,
													':inserted_by' => '1'
												);
										$lastReservationId = $this->execute_insert_getId($sqlReservation,$reservationInsert);	
										
										if($a == 0){ 
											$split_from = $lastReservationId; 
										}
										
										$bookingNotesInsert = array(
												':reservation_id' => $lastReservationId,
												':note_type_id' => 1,
												':est_hours' => "",
												':est_mins' => "",
												':note' => $client_notes,
												':status' => 'active'
											);
										$this->execute_insert($sqlNotes,$bookingNotesInsert);

									} # for($a=0; $a<count($split_rooms); $a++)

									$reservOctorateBookInsert = array(
												':idbooking_octo' => $id_reservation,
												':idprenota' => $split_from,
												':year' => $year,
												':status' => 'active'
											);
									$this->execute_insert($sqlReservOctorateBook,$reservOctorateBookInsert);	
										
									$bookingsInsert = array(
												':reservation_id' => $split_from,
												':bookingsource_id' => $book_id,
												':refnumber' => $id_reservation,
												':chnnl_manager_id_res' => $roomtype_id_bb,
												':year' => $year,
												':status' => 'active'
											);
									$this->execute_insert($sqlBookings,$bookingsInsert);
									// HTL-612 fix ------------
									$activity_log_message = array(
										'action' => "notify",
										'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
										'host' => $_SERVER['SERVER_NAME'],
										'inserted_by' => '1',
										'reservation_link' => '#/split_rooms/[room_split_id]',
										'notification_type' => '1'
									);
									$this->split_rooms_log_activity($split_from, $activity_log_message);
									// HTL-612 fix ------------
								}

								$this->val_rules_engine(2, $roomtypeId, $checkin_id, $checkout_id); # room optimize ni
							}
						}
					}
				//}
			}
		}catch(PDOException $e) {
			/* var_dump($result); */
			$socket_json_message = array(
				'action' => 'reservation',
				'message' => "Error Receiving New Reservation, REF#" . $id . " with error message: " . $e->getMessage(),
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => 1,
				'reservation_link' => '#/notification',
				'notification_type' => '1'
			);

			$this->log_activity($socket_json_message, false);
			
			/* echo $room_cnt; */
		}
	}
	/* end add octorate booking OTA */
	
	/* start cancel or modified booking from OTA */
	public function octorate_booking_modified($bookings, $dbname, $id) {
		$result = $bookings;
		
		$year = date("Y",(time() + (C_DIFF_ORE * 3600))); #--Selected Year
		$date_raw = date("Y-m-d");
		$book_id = 0;
		$idprenota = 0;
		$client_name = '';
		
		if(isset($result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'])) {
			$client_name = $result['Bookings']['Booking']['Customers']['Customer']['CustomerFName'];
		} else {
			$client_name = $result['Bookings']['Booking']['Customers']['Customer'][0]['CustomerFName'];
		}
		$channel_id = $result['Bookings']['Booking']['Channel'];
		$channel_source = $result['Bookings']['Booking']['ResSource'];
		$id_resv = $result['Bookings']['Booking']['ResId'];
		$rooms = $result['Bookings']['Booking']['Rooms']['Room'];
		$room_cnt = count($rooms);
		
		try{
			if(isset($rooms['StartDate'])) {
				$isCancel = $rooms['isRoomCancel'];
				$start_date = $rooms['StartDate'];
				$checkin_id = $this->getIdperiod($start_date, "start_date");
				$end_date = $rooms['EndDate'];
				$checkout_id = $this->getIdperiod($end_date, "end_date");
				
				$roomtype_id = $rooms['RoomTypeIds']['RoomTypeId'];
				$roomtype_id_bb = $rooms['BbliverateNumberId'];
				$roomtype_pax = $rooms['Pax'];
				$roomtype_price = $rooms['Price'];
				
				if($isCancel != true || $isCancel != 'true'){
					$get_octorate_bookings = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = '$roomtype_id_bb' AND status = 'active'");
					if(count($get_octorate_bookings) > 0){
						$idprenota = $get_octorate_bookings[0]['reservation_id'];
					}else{
						$get_octo_bookings = $this->executeQuery("SELECT * FROM octorate_bookings WHERE idbooking_octo = '$id_resv'");
						$idprenota = $get_octo_bookings[0]['idprenota'];
					}
					
					$get_reservation = $this->executeQuery("SELECT distinct * FROM reservation WHERE reservation_id = '$idprenota'");
					if(count($get_reservation) > 0){

						$room_id = $get_reservation[0]['appartments_id'];
						$iddatainizio1 = $get_reservation[0]['date_start_id'];
						$iddatafine1 = $get_reservation[0]['date_end_id'];
						
						# for email notifications modify extend
						$list_id = array($idprenota);
						$checkin_old = $iddatainizio1;
						$checkout_old = $iddatafine1;
						$old_checkin_date = $this->executeQuery("SELECT start_date FROM periods WHERE periods_id = '$iddatainizio1' ");
						$old_checkout_date = $this->executeQuery("SELECT end_date FROM periods WHERE periods_id = '$iddatafine1' ");
						$socket_data = array('old_check_in_date' => $old_checkin_date[0]['start_date'] ,'old_check_out_date' => $old_checkout_date[0]['end_date']);
						if($checkin_id == $checkin_old && $checkout_old < $checkout_id){
							$reservation_type = "extend_period";
						}else{
							$reservation_type = "full_period";
						}
						$res_data = array('reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], 'reservation_period_type' => $reservation_type, 'socket_data' => $socket_data);
						$for_email_data[0] = array('list_id' => $list_id, 'res_data' => $res_data); 
						# end email notification data

						$get_roomtype = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = '$roomtype_id'");
						$roomtypeId = $get_roomtype[0]['roomtype_id'];
						$roomtype_column = $get_roomtype[0]['column_rate'];
						
						$get_roomtype_name = $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
						$roomtype_name = $get_roomtype_name[0]['name'];

						$get_roomtype_rate = $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
						$totalamnt = 0;
						$tariffesettimanali = "";
						$commission = 0;
						
						$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
						$id_property = $getPropertyId[0]['property_id'];
						
						if($id_property == '274690'){
							$roomtype_column_right = $roomtype_column; # original na rate column
							$column_tobecheck = $roomtype_column."_pax_".$roomtype_pax;
							$sqlll = "SHOW COLUMNS FROM `periods` LIKE '$column_tobecheck'";
							$check_column = $this->executeQuery($sqlll); # check kung naay rate column na naay pax
							if(count($check_column) > 0){
								$roomtype_column_right = $column_tobecheck; # Utrohon ang rate column nga naay pax.
							}
							$cnt2 = count($get_roomtype_rate);
							for($x2=0; $x2<$cnt2; $x2++){
								$totalamnt += $get_roomtype_rate[$x2][$roomtype_column_right];
								if($x2==0){
									$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column_right];
								}else{
									$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column_right];
								}
							}
							/*$cntDaily = count($rooms['DayByDayPrice']['price']);
							for($xx = 0; $xx < $cntDaily; $xx++){
								$totalamnt += $rooms['DayByDayPrice']['price'][$xx];
								if($xx==0){
									$tariffesettimanali = $rooms['DayByDayPrice']['price'][$xx];
								}else{
									$tariffesettimanali = $tariffesettimanali.",".$rooms['DayByDayPrice']['price'][$xx];
								}
							}*/
						}else{
							$cnt2 = count($get_roomtype_rate);
							for($x2=0; $x2<$cnt2; $x2++){
								$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
								if($x2==0){
									$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
								}else{
									$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
								}
							}
						}
						
						/*HTL issue HTL-476 start*/
						$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
						$cost_type = $getBookingChannel[0]['comm_type'];
						$cost_comm = $getBookingChannel[0]['cost_comm'];
						$deposit_status = $getBookingChannel[0]['deposit_status'];
						$comm_status = $getBookingChannel[0]['commission_paid'];
							
						$deposit_total = 0;
						$paid_total = 0;
						$paid_comm = 0;
						
						if($deposit_status == 'paid'){
							$deposit_total = $roomtype_price;
						}
						
						if($cost_type == 'percent'){
							$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
						}else if($cost_type == 'fix'){
							$commission = floatval($cost_comm) + floatval($totalamnt);
						}else{
							$commission = 0;
						}
						
						if($comm_status == 'yes'){
							$paid_comm = $commission;
						}
						
						$paid_total = floatval($paid_comm) + floatval($deposit_total);
						/*HTL issue HTL-476 end*/
						
						/* if($channel_source == 'booking_xml'){
							$commission = $roomtype_price * 0.15;
						}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
							$commission = 0;
						}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
							$commission = 0;
						}else{
							$commission = 0;
						} */
						
						$sql = "SELECT room.* 
						FROM `reservation` As res, `apartments` As room
						WHERE res.`reservation_id` = '$idprenota' and
							  res.`appartments_id` = room.`apartment_id`";
						$get_currentroomtype = $this->executeQuery($sql);
						// orig space
						/*if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){
							$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
							$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
							$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
						}
						
						$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
										
						if($is_room_available == false){
							$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
							$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
						}
						
						$tariffa = $roomtype_name."#@&".$totalamnt;
						$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET commissions='$commission' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb' WHERE reservation_id = '$idprenota'");
						
						$this->execute_update("UPDATE reservation SET deposit='$deposit_total' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET paid='$paid_total' WHERE reservation_id = '$idprenota'");*/
						# -------------------------------
						// orig space
						if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){ # nachange ang room type
							$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
							if($idappartamenti != 0){ # single room
								$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
								$tariffa = $roomtype_name."#@&".$totalamnt;
								$sqlReservation = "UPDATE `reservation` 
												   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
												   WHERE `reservation_id` = :reservation_id";
								$reservationUpdate = array(
															':rate' => $tariffa,
															':weekly_rates' => $tariffesettimanali,
															':rate_total' => $totalamnt, 
															':date_start_id' => $checkin_id,
															':date_end_id' => $checkout_id, 
															':commissions' => $commission,
															':date_modified' => $data_modifica1,
															':deposit' => $deposit_total,
															':paid' => $paid_total,
															':reservation_id' => $idprenota
														);
								$this->execute_insert($sqlReservation,$reservationUpdate);
								$this->room_split_trans_room($idappartamenti, $idprenota);
								$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
							}
							else{ # split rooms
								$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
													FROM `reservation`
													WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
								$res_check_result = $this->executeQuery($sql_check_res);
								if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
									# action: rebalance the splitted rooms
									$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
								}
								$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
								$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

								if($split_rooms == false){
									# create the booking with unallocated room
									// -- execute activity log
									$socket_json_message = array(
										'action' => 'notify',
										'message' => "There's a reservation with unallocated room! REF#".$idprenota.", Client: ".$client_name,
										'host' => $_SERVER['SERVER_NAME'],
										'inserted_by' => 1,
										'reservation_link' => '#/modify-booking/'.$get_reservation[0]['reservation_conn_id'].'/$idprenota',
										'notification_type' => '1'
									);
								
									$this->log_activity($socket_json_message, true);
								}
								else{
									# proceed splitting the rooms
									//$split_from = 0;
									$period_Arr = range($checkin_id, $checkout_id);
									$weekly_rates_Arr = explode(",",$tariffesettimanali);
									$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
									$commission_temp = $commission;
									$deposit_total_temp = $deposit_total;
									$paid_total_temp = $paid_total;
															
									for($a=0; $a<count($split_rooms); $a++){
										$room_id_split = $split_rooms[$a]["room_id"];
										$date_start_id_split = $split_rooms[$a]["date_start_id"];
										$date_end_id_split = $split_rooms[$a]["date_end_id"];
										$weekly_rates_s = ""; # weekly_rates
										$rate_total_s = 0; # rate_total
										$commission_s = 0; # commission
										$deposit_total_s = 0; # deposit_total
										$paid_total_s = 0; # 

										for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
											$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
											if($b==$date_start_id_split){ 
												$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
											}
											else{ 
												$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
											}
										}
										$rate_s = $roomtype_name."#@&".$rate_total_s;

										if($rate_total_s >= $commission_temp){ # recalculate commissions
											$commission_s = $commission_temp;
											$commission_temp = 0;
										}
										else{
											$commission_s = $rate_total_s;
											$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
											$deposit_total_s = $deposit_total_temp;
											$deposit_total_temp = 0;
										}
										else{
											$deposit_total_s = $rate_total_s;
											$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
										}
										if($rate_total_s >= $paid_total_temp){ # recalculate paid
											$paid_total_s = $paid_total_temp;
											$paid_total_temp = 0;
										}
										else{
											$paid_total_s = $rate_total_s;
											$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
										}

										if($a == 0){ # first room allocation
											$sqlReservation = "UPDATE `reservation` 
																SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
															   WHERE `reservation_id` = :reservation_id";
											$reservationUpdate = array(
																':reservation_id' => $idprenota,
																':appartments_id' => $room_id_split,
																':date_start_id' => $date_start_id_split, 
																':date_end_id' => $date_end_id_split,
																':rate' => $rate_s, 
																':weekly_rates' => $weekly_rates_s,
																':commissions' => $commission_s,
																':rate_total' => $rate_total_s,
																':deposit' => $deposit_total_s,
																':paid' => $paid_total_s,
																':date_modified' => $data_modifica1
															);

											$this->execute_insert($sqlReservation,$reservationUpdate);
										}
										else{
											$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																			             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
											$reservationInsert = array(
																		':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																		':clients_id' => $get_reservation[0]['clients_id'], // 
																		':appartments_id' => $room_id_split,
																		':date_start_id' => $date_start_id_split, 
																		':date_end_id' => $date_end_id_split, 
																		':assign_app' => 'k',  
																		':pax' => $get_reservation[0]['pax'], // 
																		':original_rate' => $rate_s, 
																		':rate' => $rate_s, 
																		':weekly_rates' => $weekly_rates_s,
																		':commissions' => $commission_s,
																		':rate_total' => $rate_total_s,
																		':deposit' => $deposit_total_s,
																		':paid' => $paid_total_s,
																		':code' => $get_reservation[0]['code'], // 
																		':confirmation' => 'S',  
																		':split_from' => $idprenota, // 
																		':status' => 'active',
																		':inserted_date' => C_DIFF_ORE,
																		':inserted_host' => $get_reservation[0]['inserted_host'], //
																		':date_modified' => $data_modifica1,
																		':inserted_by' => '1'
															);
											$this->execute_insert($sqlReservation,$reservationInsert);	
										}

									} # for($a=0; $a<count($split_rooms); $a++)
									// HTL-637 fix START
									if(count($res_check_result) == 1){ # single room
										$sql = "SELECT `reference_num` FROM `reservation_conn` WHERE `reservation_conn_id` = '".$get_reservation[0]['reservation_conn_id']."'";
										$check_ref_num = $this->executeQuery($sql);
										$ref_num_temp = '';
										if(count($check_ref_num) > 0){
											$ref_num_temp = $check_ref_num[0]['reference_num'];
										}
										$activity_log_message = array(
													'action' => "notify",
													'message' => "New booking with split rooms. REF#".$ref_num_temp.", Client: ".$client_name,
													'host' => $_SERVER['SERVER_NAME'],
													'inserted_by' => '1',
													'reservation_link' => '#/split_rooms/[room_split_id]',
													'notification_type' => '1'
										);
										$this->split_rooms_log_activity($idprenota, $activity_log_message);
									}
									// HTL-637 fix END
								}

							}
						}
						else{ # wala ma.change ang room type
							if($checkin_id == $get_reservation[0]['date_start_id'] && $checkout_id == $get_reservation[0]['date_end_id']){ 

							}
							else{ # changes on period balik diri
								$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
								$is_blocked = $this->check_blocking($room_id, $checkin_id, $checkout_id, $idprenota);
								if(!($is_room_available && $is_blocked)){ # no room(s) available for new period
									
									$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
									if($idappartamenti_2 == 0){ # could be split
										$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
														FROM `reservation`
														WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
										$res_check_result = $this->executeQuery($sql_check_res);
										$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
										if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
											# action: rebalance the splitted rooms
											$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
										}
										$split_rooms = $this->get_split_rooms($get_currentroomtype[0]["roomtype_id"], $checkin_id, $checkout_id);

										//$split_from = 0;
										$period_Arr = range($checkin_id, $checkout_id);
										$weekly_rates_Arr = explode(",",$tariffesettimanali);
										$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
										$commission_temp = $commission;
										$deposit_total_temp = $deposit_total;
										$paid_total_temp = $paid_total;
														
										for($a=0; $a<count($split_rooms); $a++){
											$room_id_split = $split_rooms[$a]["room_id"];
											$date_start_id_split = $split_rooms[$a]["date_start_id"];
											$date_end_id_split = $split_rooms[$a]["date_end_id"];
											$weekly_rates_s = ""; # weekly_rates
											$rate_total_s = 0; # rate_total
											$commission_s = 0; # commission
											$deposit_total_s = 0; # deposit_total
											$paid_total_s = 0; # 

											for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
												$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
												if($b==$date_start_id_split){ 
													$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
												}
												else{ 
													$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
												}
											}
											$rate_s = $roomtype_name."#@&".$rate_total_s;

											if($rate_total_s >= $commission_temp){ # recalculate commissions
												$commission_s = $commission_temp;
												$commission_temp = 0;
											}
											else{
												$commission_s = $rate_total_s;
												$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
											}
											if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
												$deposit_total_s = $deposit_total_temp;
												$deposit_total_temp = 0;
											}
											else{
												$deposit_total_s = $rate_total_s;
												$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
											}
											if($rate_total_s >= $paid_total_temp){ # recalculate paid
												$paid_total_s = $paid_total_temp;
												$paid_total_temp = 0;
											}
											else{
												$paid_total_s = $rate_total_s;
												$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
											}

											if($a == 0){ # first room allocation
												$sqlReservation = "UPDATE `reservation` 
																			   SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
																			   WHERE `reservation_id` = :reservation_id";
												$reservationUpdate = array(
																	':reservation_id' => $idprenota,
																	':appartments_id' => $room_id_split,
																	':date_start_id' => $date_start_id_split, 
																	':date_end_id' => $date_end_id_split,
																	':rate' => $rate_s, 
																	':weekly_rates' => $weekly_rates_s,
																	':commissions' => $commission_s,
																	':rate_total' => $rate_total_s,
																	':deposit' => $deposit_total_s,
																	':paid' => $paid_total_s,
																	':date_modified' => $data_modifica1
																);

												$this->execute_insert($sqlReservation,$reservationUpdate);
											}
											else{
												$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																		             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
												$reservationInsert = array(
																	':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																	':clients_id' => $get_reservation[0]['clients_id'], // 
																	':appartments_id' => $room_id_split,
																	':date_start_id' => $date_start_id_split, 
																	':date_end_id' => $date_end_id_split, 
																	':assign_app' => 'k',  
																	':pax' => $get_reservation[0]['pax'], // 
																	':original_rate' => $rate_s, 
																	':rate' => $rate_s, 
																	':weekly_rates' => $weekly_rates_s,
																	':commissions' => $commission_s,
																	':rate_total' => $rate_total_s,
																	':deposit' => $deposit_total_s,
																	':paid' => $paid_total_s,
																	':code' => $get_reservation[0]['code'], // 
																	':confirmation' => 'S',  
																	':split_from' => $idprenota, // 
																	':status' => 'active',
																	':inserted_date' => C_DIFF_ORE,
																	':inserted_host' => $get_reservation[0]['inserted_host'], //
																	':date_modified' => $data_modifica1,
																	':inserted_by' => '1'
														);
												$this->execute_insert($sqlReservation,$reservationInsert);	
											}

										} # for($a=0; $a<count($split_rooms); $a++)

										if(count($res_check_result) == 1){ # single room
											$activity_log_message = array(
												'action' => "notify",
												'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
												'host' => $_SERVER['SERVER_NAME'],
												'inserted_by' => '1',
												'reservation_link' => '#/split_rooms/[room_split_id]',
												'notification_type' => '1'
											);
											$this->split_rooms_log_activity($idprenota, $activity_log_message);
										}
									}
									else{ # can allocate to a single room
										$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
										$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
										$tariffa = $roomtype_name."#@&".$totalamnt;
										$sqlReservation = "UPDATE `reservation` 
														   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
														   WHERE `reservation_id` = :reservation_id";
										$reservationUpdate = array(
																	':rate' => $tariffa,
																	':weekly_rates' => $tariffesettimanali,
																	':rate_total' => $totalamnt, 
																	':date_start_id' => $checkin_id,
																	':date_end_id' => $checkout_id, 
																	':commissions' => $commission,
																	':date_modified' => $data_modifica1,
																	':deposit' => $deposit_total,
																	':paid' => $paid_total,
																	':reservation_id' => $idprenota
																);
										$this->execute_insert($sqlReservation,$reservationUpdate);
										$this->room_split_trans_room($idappartamenti_2, $idprenota);
									}
											
								}
								else{
									$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
									$tariffa = $roomtype_name."#@&".$totalamnt;
									$sqlReservation = "UPDATE `reservation` 
													   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
													   WHERE `reservation_id` = :reservation_id";
									$reservationUpdate = array(
																':rate' => $tariffa,
																':weekly_rates' => $tariffesettimanali,
																':rate_total' => $totalamnt, 
																':date_start_id' => $checkin_id,
																':date_end_id' => $checkout_id, 
																':commissions' => $commission,
																':date_modified' => $data_modifica1,
																':deposit' => $deposit_total,
																':paid' => $paid_total,
																':reservation_id' => $idprenota
															);
									$this->execute_insert($sqlReservation,$reservationUpdate);
								}
		
							}
						}
						/* $tariffa = $roomtype_name."#@&".$totalamnt;
						$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);
								
						$get_roomtype_id = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE roomtype_id = '$roomtypeId' ");
						$roomtype_column_id = $get_roomtype_id[0]['idroomtype_octo'];
						
						$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
						$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb' WHERE reservation_id = '$idprenota'"); */
					}
				}else{
					$roomtype_id_bb2 = $rooms['BbliverateNumberId'];
					$get_octorate_booking_cancelled = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = '$roomtype_id_bb2' AND status = 'active'");
					if(count($get_octorate_booking_cancelled) > 0){
						$idprenota2 = $get_octorate_booking_cancelled[0]['reservation_id'];
						$this->checkblocked_room($idprenota2);
						$this->execute_update("UPDATE reservation SET status='cancelled' WHERE reservation_id = '$idprenota2'");
						// HTL-610 fix START
						$this->execute_update("UPDATE reservation SET status='cancelled' WHERE split_from = '$idprenota2' and status = 'active'");
						// HTL-610 fix END
						/* Part of email notification */
						$list_id_cancelled[0] = $idprenota2;
						// $this->prepare_cancel_noshow_email("cancel", $res_con_id, $list_id, $cancelled_from);
						/* end part of email notification */
					}
				}
			} 
			else {
				$countFalse = 0;
				$idprenota = 0;
				$octo_array = array();
				for($xy = 0; $xy < $room_cnt; $xy++){
					$cancelIs = $rooms[$xy]['isRoomCancel'];
					if($cancelIs != 'true' || $cancelIs != true){
						$countFalse++;
					}
				}
				for($x = 0; $x < $room_cnt; $x++){
					$isCancel = $rooms[$x]['isRoomCancel'];
					$start_date = $rooms[$x]['StartDate'];
					$checkin_id = $this->getIdperiod($start_date, "start_date");
					$end_date = $rooms[$x]['EndDate'];
					$checkout_id = $this->getIdperiod($end_date, "end_date");
					
					$roomtype_id = $rooms[$x]['RoomTypeIds']['RoomTypeId'];
					$roomtype_id_bb = $rooms[$x]['BbliverateNumberId'];
					$roomtype_pax = $rooms[$x]['Pax'];
					$roomtype_price = $rooms[$x]['Price'];
					
					if($isCancel != true || $isCancel != 'true'){		
						if($countFalse > 1){
							/** Update Existing booking**/
							$get_octorate_bookings = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = '$roomtype_id_bb' AND status = 'active'");
							if(count($get_octorate_bookings) > 0){
								$idprenota = $get_octorate_bookings[0]['reservation_id'];
								
								$get_reservation = $this->executeQuery("SELECT distinct * FROM reservation WHERE reservation_id = '$idprenota'");
								if(count($get_reservation) > 0){
									$room_id = $get_reservation[0]['appartments_id'];
									$iddatainizio1 = $get_reservation[0]['date_start_id'];
									$iddatafine1 = $get_reservation[0]['date_end_id'];
									
									# for email notifications modify extend
									$list_id[$x] = $idprenota;
									$checkin_old = $iddatainizio1;
									$checkout_old = $iddatafine1;
									$old_checkin_date = $this->executeQuery("SELECT start_date FROM periods WHERE periods_id = '$iddatainizio1' ");
									$old_checkout_date = $this->executeQuery("SELECT end_date FROM periods WHERE periods_id = '$iddatafine1' ");
									$socket_data = array('old_check_in_date' => $old_checkin_date[0]['start_date'] ,'old_check_out_date' => $old_checkout_date[0]['end_date']);
									if($checkin_id == $checkin_old && $checkout_old < $checkout_id){
										$reservation_type = "extend_period";
									}else{
										$reservation_type = "full_period";
									}
									$res_data = array('reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], 'reservation_period_type' => $reservation_type, 'socket_data' => $socket_data);
									$for_email_data[$x] = array('list_id' => $list_id, 'res_data' => $res_data); 
									# end email notification data

									$get_roomtype = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
									$roomtypeId = $get_roomtype[0]['roomtype_id'];
									$roomtype_column = $get_roomtype[0]['column_rate'];

									$get_roomtype_name =  $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
									$roomtype_name = $get_roomtype_name[0]['name'];

									$get_roomtype_rate = $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
									$totalamnt = 0;
									$tariffesettimanali = "";
									$commission = 0;
									
									$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
									$id_property = $getPropertyId[0]['property_id'];
									
									if($id_property == '274690'){
										$roomtype_column_right = $roomtype_column; # original na rate column
										$column_tobecheck = $roomtype_column."_pax_".$roomtype_pax;
										$sqlll = "SHOW COLUMNS FROM `periods` LIKE '$column_tobecheck'";
										$check_column = $this->executeQuery($sqlll); # check kung naay rate column na naay pax
										if(count($check_column) > 0){
											$roomtype_column_right = $column_tobecheck; # Utrohon ang rate column nga naay pax.
										}
										$cnt2 = count($get_roomtype_rate);
										for($x2=0; $x2<$cnt2; $x2++){
											$totalamnt += $get_roomtype_rate[$x2][$roomtype_column_right];
											if($x2==0){
												$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column_right];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column_right];
											}
										}
										/*$cntDaily = count($rooms[$x]['DayByDayPrice']['price']);
										for($xx = 0; $xx < $cntDaily; $xx++){
											$totalamnt += $rooms[$x]['DayByDayPrice']['price'][$xx];
											if($xx==0){
												$tariffesettimanali = $rooms[$x]['DayByDayPrice']['price'][$xx];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$rooms[$x]['DayByDayPrice']['price'][$xx];
											}
										}*/
									}else{
										$cnt2 = count($get_roomtype_rate);
										for($x2=0; $x2<$cnt2; $x2++){
											$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
											if($x2==0){
												$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
											}else{
												$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
											}
										}
									}
									
									/*HTL issue HTL-476 start*/
									$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
									$cost_type = $getBookingChannel[0]['comm_type'];
									$cost_comm = $getBookingChannel[0]['cost_comm'];
									$deposit_status = $getBookingChannel[0]['deposit_status'];
									$comm_status = $getBookingChannel[0]['commission_paid'];
										
									$deposit_total = 0;
									$paid_total = 0;
									$paid_comm = 0;
									
									if($deposit_status == 'paid'){
										$deposit_total = $roomtype_price;
									}
									
									if($cost_type == 'percent'){
										$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
									}else if($cost_type == 'fix'){
										$commission = floatval($cost_comm) + floatval($totalamnt);
									}else{
										$commission = 0;
									}
									
									if($comm_status == 'yes'){
										$paid_comm = $commission;
									}
									
									$paid_total = floatval($paid_comm) + floatval($deposit_total);
									/*HTL issue HTL-476 end*/
									
									/* if($channel_source == 'booking_xml'){
										$commission = $roomtype_price * 0.15;
									}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
										$commission = 0;
									}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
										$commission = 0;
									}else{
										$commission = 0;
									} */
									
									$sql = "SELECT room.* 
									FROM `reservation` As res, `apartments` As room
									WHERE res.`reservation_id` = '$idprenota' and
										  res.`appartments_id` = room.`apartment_id`";
									$get_currentroomtype = $this->executeQuery($sql);
									// orig space
									/*if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){
										$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
										$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
										$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
									}
								
									$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
										
									if($is_room_available == false){
										$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
										$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
									}

									$tariffa = $roomtype_name."#@&".$totalamnt;
									$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET commissions='$commission' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
									
									$this->execute_update("UPDATE reservation SET deposit='$deposit_total' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET paid='$paid_total' WHERE reservation_id = '$idprenota'");*/
									//$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb' WHERE reservation_id = '$idprenota'");
									# -------------------------------
									// orig space
									if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){ # nachange ang room type
										$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
										if($idappartamenti != 0){ # single room
											$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
											$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
											$tariffa = $roomtype_name."#@&".$totalamnt;
											$sqlReservation = "UPDATE `reservation` 
															   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
															   WHERE `reservation_id` = :reservation_id";
											$reservationUpdate = array(
																		':rate' => $tariffa,
																		':weekly_rates' => $tariffesettimanali,
																		':rate_total' => $totalamnt, 
																		':date_start_id' => $checkin_id,
																		':date_end_id' => $checkout_id, 
																		':commissions' => $commission,
																		':date_modified' => $data_modifica1,
																		':deposit' => $deposit_total,
																		':paid' => $paid_total,
																		':reservation_id' => $idprenota
																	);
											$this->execute_insert($sqlReservation,$reservationUpdate);
											$this->room_split_trans_room($idappartamenti, $idprenota);
											$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
										}
										else{ # split rooms
											$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
																FROM `reservation`
																WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
											$res_check_result = $this->executeQuery($sql_check_res);
											if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
												# action: rebalance the splitted rooms
												$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
											}
											$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
											$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

											if($split_rooms == false){
												# create the booking with unallocated room
												// -- execute activity log
												$socket_json_message = array(
													'action' => 'notify',
													'message' => "There's a reservation with unallocated room! REF#".$idprenota.", Client: ".$client_name,
													'host' => $_SERVER['SERVER_NAME'],
													'inserted_by' => 1,
													'reservation_link' => '#/modify-booking/'.$get_reservation[0]['reservation_conn_id'].'/$idprenota',
													'notification_type' => '1'
												);
												$this->log_activity($socket_json_message, true);
											}
											else{
												# proceed splitting the rooms
												//$split_from = 0;
												$period_Arr = range($checkin_id, $checkout_id);
												$weekly_rates_Arr = explode(",",$tariffesettimanali);
												$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
												$commission_temp = $commission;
												$deposit_total_temp = $deposit_total;
												$paid_total_temp = $paid_total;
																		
												for($a=0; $a<count($split_rooms); $a++){
													$room_id_split = $split_rooms[$a]["room_id"];
													$date_start_id_split = $split_rooms[$a]["date_start_id"];
													$date_end_id_split = $split_rooms[$a]["date_end_id"];
													$weekly_rates_s = ""; # weekly_rates
													$rate_total_s = 0; # rate_total
													$commission_s = 0; # commission
													$deposit_total_s = 0; # deposit_total
													$paid_total_s = 0; # 

													for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
														$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
														if($b==$date_start_id_split){ 
															$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
														}
														else{ 
															$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
														}
													}
													$rate_s = $roomtype_name."#@&".$rate_total_s;

													if($rate_total_s >= $commission_temp){ # recalculate commissions
														$commission_s = $commission_temp;
														$commission_temp = 0;
													}
													else{
														$commission_s = $rate_total_s;
														$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
													}
													if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
														$deposit_total_s = $deposit_total_temp;
														$deposit_total_temp = 0;
													}
													else{
														$deposit_total_s = $rate_total_s;
														$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
													}
													if($rate_total_s >= $paid_total_temp){ # recalculate paid
														$paid_total_s = $paid_total_temp;
														$paid_total_temp = 0;
													}
													else{
														$paid_total_s = $rate_total_s;
														$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
													}

													if($a == 0){ # first room allocation
														$sqlReservation = "UPDATE `reservation` 
																			SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
																		   WHERE `reservation_id` = :reservation_id";
														$reservationUpdate = array(
																			':reservation_id' => $idprenota,
																			':appartments_id' => $room_id_split,
																			':date_start_id' => $date_start_id_split, 
																			':date_end_id' => $date_end_id_split,
																			':rate' => $rate_s, 
																			':weekly_rates' => $weekly_rates_s,
																			':commissions' => $commission_s,
																			':rate_total' => $rate_total_s,
																			':deposit' => $deposit_total_s,
																			':paid' => $paid_total_s,
																			':date_modified' => $data_modifica1
																		);

														$this->execute_insert($sqlReservation,$reservationUpdate);
													}
													else{
														$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																			             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
														$reservationInsert = array(
																					':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																					':clients_id' => $get_reservation[0]['clients_id'], // 
																					':appartments_id' => $room_id_split,
																					':date_start_id' => $date_start_id_split, 
																					':date_end_id' => $date_end_id_split, 
																					':assign_app' => 'k',  
																					':pax' => $get_reservation[0]['pax'], // 
																					':original_rate' => $rate_s, 
																					':rate' => $rate_s, 
																					':weekly_rates' => $weekly_rates_s,
																					':commissions' => $commission_s,
																					':rate_total' => $rate_total_s,
																					':deposit' => $deposit_total_s,
																					':paid' => $paid_total_s,
																					':code' => $get_reservation[0]['code'], // 
																					':confirmation' => 'S',  
																					':split_from' => $idprenota, // 
																					':status' => 'active',
																					':inserted_date' => C_DIFF_ORE,
																					':inserted_host' => $get_reservation[0]['inserted_host'], //
																					':date_modified' => $data_modifica1,
																					':inserted_by' => '1'
																		);
														$this->execute_insert($sqlReservation,$reservationInsert);	
													}

												} # for($a=0; $a<count($split_rooms); $a++)
												// HTL-637 fix START
												if(count($res_check_result) == 1){ # single room
													$sql = "SELECT `reference_num` FROM `reservation_conn` WHERE `reservation_conn_id` = '".$get_reservation[0]['reservation_conn_id']."'";
													$check_ref_num = $this->executeQuery($sql);
													$ref_num_temp = '';
													if(count($check_ref_num) > 0){
														$ref_num_temp = $check_ref_num[0]['reference_num'];
													}
													$activity_log_message = array(
																'action' => "notify",
																'message' => "New booking with split rooms. REF#".$ref_num_temp.", Client: ".$client_name,
																'host' => $_SERVER['SERVER_NAME'],
																'inserted_by' => '1',
																'reservation_link' => '#/split_rooms/[room_split_id]',
																'notification_type' => '1'
													);
													$this->split_rooms_log_activity($idprenota, $activity_log_message);
												}
												// HTL-637 fix END
											}
											
										}
									}
									else{ # wala ma.change ang room type
										if($checkin_id == $get_reservation[0]['date_start_id'] && $checkout_id == $get_reservation[0]['date_end_id']){ 

										}
										else{ # changes on period
											$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
											if($is_room_available == false){ # no room(s) available for new period
												
												$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
												if($idappartamenti_2 == 0){ # could be split
													$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
																	FROM `reservation`
																	WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
													$res_check_result = $this->executeQuery($sql_check_res);
													$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
													if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
														# action: rebalance the splitted rooms
														$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
													}
													$split_rooms = $this->get_split_rooms($get_currentroomtype[0]["roomtype_id"], $checkin_id, $checkout_id);

													//$split_from = 0;
													$period_Arr = range($checkin_id, $checkout_id);
													$weekly_rates_Arr = explode(",",$tariffesettimanali);
													$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
													$commission_temp = $commission;
													$deposit_total_temp = $deposit_total;
													$paid_total_temp = $paid_total;
																	
													for($a=0; $a<count($split_rooms); $a++){
														$room_id_split = $split_rooms[$a]["room_id"];
														$date_start_id_split = $split_rooms[$a]["date_start_id"];
														$date_end_id_split = $split_rooms[$a]["date_end_id"];
														$weekly_rates_s = ""; # weekly_rates
														$rate_total_s = 0; # rate_total
														$commission_s = 0; # commission
														$deposit_total_s = 0; # deposit_total
														$paid_total_s = 0; # 

														for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
															$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
															if($b==$date_start_id_split){ 
																$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
															}
															else{ 
																$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
															}
														}
														$rate_s = $roomtype_name."#@&".$rate_total_s;

														if($rate_total_s >= $commission_temp){ # recalculate commissions
															$commission_s = $commission_temp;
															$commission_temp = 0;
														}
														else{
															$commission_s = $rate_total_s;
															$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
														}
														if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
															$deposit_total_s = $deposit_total_temp;
															$deposit_total_temp = 0;
														}
														else{
															$deposit_total_s = $rate_total_s;
															$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
														}
														if($rate_total_s >= $paid_total_temp){ # recalculate paid
															$paid_total_s = $paid_total_temp;
															$paid_total_temp = 0;
														}
														else{
															$paid_total_s = $rate_total_s;
															$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
														}

														if($a == 0){ # first room allocation
															$sqlReservation = "UPDATE `reservation` 
																						   SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
																						   WHERE `reservation_id` = :reservation_id";
															$reservationUpdate = array(
																				':reservation_id' => $idprenota,
																				':appartments_id' => $room_id_split,
																				':date_start_id' => $date_start_id_split, 
																				':date_end_id' => $date_end_id_split,
																				':rate' => $rate_s, 
																				':weekly_rates' => $weekly_rates_s,
																				':commissions' => $commission_s,
																				':rate_total' => $rate_total_s,
																				':deposit' => $deposit_total_s,
																				':paid' => $paid_total_s,
																				':date_modified' => $data_modifica1
																			);

															$this->execute_insert($sqlReservation,$reservationUpdate);
														}
														else{
															$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																		             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
															$reservationInsert = array(
																				':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																				':clients_id' => $get_reservation[0]['clients_id'], // 
																				':appartments_id' => $room_id_split,
																				':date_start_id' => $date_start_id_split, 
																				':date_end_id' => $date_end_id_split, 
																				':assign_app' => 'k',  
																				':pax' => $get_reservation[0]['pax'], // 
																				':original_rate' => $rate_s, 
																				':rate' => $rate_s, 
																				':weekly_rates' => $weekly_rates_s,
																				':commissions' => $commission_s,
																				':rate_total' => $rate_total_s,
																				':deposit' => $deposit_total_s,
																				':paid' => $paid_total_s,
																				':code' => $get_reservation[0]['code'], // 
																				':confirmation' => 'S',  
																				':split_from' => $idprenota, // 
																				':status' => 'active',
																				':inserted_date' => C_DIFF_ORE,
																				':inserted_host' => $get_reservation[0]['inserted_host'], //
																				':date_modified' => $data_modifica1,
																				':inserted_by' => '1'
																	);
															$this->execute_insert($sqlReservation,$reservationInsert);	
														}

													} # for($a=0; $a<count($split_rooms); $a++)

													if(count($res_check_result) == 1){ # single room
														$activity_log_message = array(
															'action' => "notify",
															'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
															'host' => $_SERVER['SERVER_NAME'],
															'inserted_by' => '1',
															'reservation_link' => '#/split_rooms/[room_split_id]',
															'notification_type' => '1'
														);
														$this->split_rooms_log_activity($idprenota, $activity_log_message);
													}
												}
												else{ # can allocate to a single room
													$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
													$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
													$tariffa = $roomtype_name."#@&".$totalamnt;
													$sqlReservation = "UPDATE `reservation` 
																	   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
																	   WHERE `reservation_id` = :reservation_id";
													$reservationUpdate = array(
																				':rate' => $tariffa,
																				':weekly_rates' => $tariffesettimanali,
																				':rate_total' => $totalamnt, 
																				':date_start_id' => $checkin_id,
																				':date_end_id' => $checkout_id, 
																				':commissions' => $commission,
																				':date_modified' => $data_modifica1,
																				':deposit' => $deposit_total,
																				':paid' => $paid_total,
																				':reservation_id' => $idprenota
																			);
													$this->execute_insert($sqlReservation,$reservationUpdate);
													$this->room_split_trans_room($idappartamenti_2, $idprenota);
												}
														
											}
											else{
												$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
												$tariffa = $roomtype_name."#@&".$totalamnt;
												$sqlReservation = "UPDATE `reservation` 
																   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
																   WHERE `reservation_id` = :reservation_id";
												$reservationUpdate = array(
																			':rate' => $tariffa,
																			':weekly_rates' => $tariffesettimanali,
																			':rate_total' => $totalamnt, 
																			':date_start_id' => $checkin_id,
																			':date_end_id' => $checkout_id, 
																			':commissions' => $commission,
																			':date_modified' => $data_modifica1,
																			':deposit' => $deposit_total,
																			':paid' => $paid_total,
																			':reservation_id' => $idprenota
																		);
												$this->execute_insert($sqlReservation,$reservationUpdate);
											}
					
										}
									}
									/* $tariffa = $roomtype_name."#@&".$totalamnt;
									$idappartamenti = $this->auto_allocateRoom($roomtypeId, 2017, $checkin_id, $checkout_id);
											
									$get_roomtype_id = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE roomtype_id = $roomtypeId");
									$roomtype_column_id = $get_roomtype_id[0]['idroomtype_octo'];
									
									$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
									$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb' WHERE reservation_id = '$idprenota'"); */
								}
							}else{
								/** Add new booking **/
								$this->octorate_booking($result);
							}
						}else{
							/** Update Existing booking **/
							$roomtype_id_bb3 = $rooms[$x]['BbliverateNumberId'];
							$get_octorate_bookings2 = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = '$roomtype_id_bb3' AND status = 'active'");
							if(count($get_octorate_bookings2) > 0){
								$idprenota = $get_octorate_bookings2[0]['reservation_id'];
							}else{
								$get_octo_bookings = $this->executeQuery("SELECT * FROM octorate_bookings WHERE idbooking_octo = '$id_resv'");
								$idprenota = $get_octo_bookings[0]['idprenota'];
								$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb3' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET status='active' WHERE reservation_id = '$idprenota'");
							}
							
							$get_reservation = $this->executeQuery("SELECT distinct * FROM reservation WHERE reservation_id = '$idprenota'");
							if(count($get_reservation) > 0){
								$room_id = $get_reservation[0]['appartments_id'];
								$iddatainizio1 = $get_reservation[0]['date_start_id'];
								$iddatafine1 = $get_reservation[0]['date_end_id'];
								
								# for email notifications modify extend
								$list_id = array($idprenota);
								$checkin_old = $iddatainizio1;
								$checkout_old = $iddatafine1;
								$old_checkin_date = $this->executeQuery("SELECT start_date FROM periods WHERE periods_id = '$iddatainizio1' ");
								$old_checkout_date = $this->executeQuery("SELECT end_date FROM periods WHERE periods_id = '$iddatafine1' ");
								$socket_data = array('old_check_in_date' => $old_checkin_date[0]['start_date'] ,'old_check_out_date' => $old_checkout_date[0]['end_date']);
								if($checkin_id == $checkin_old && $checkout_old < $checkout_id){
									$reservation_type = "extend_period";
								}else{
									$reservation_type = "full_period";
								}
								$res_data = array('reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], 'reservation_period_type' => $reservation_type, 'socket_data' => $socket_data);
								$for_email_data[$x] = array('list_id' => $list_id, 'res_data' => $res_data); 
								# end email notification data

								$get_roomtype = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE idroomtype_octo = $roomtype_id");
								$roomtypeId = $get_roomtype[0]['roomtype_id'];
								$roomtype_column = $get_roomtype[0]['column_rate'];

								$get_roomtype_name =  $this->executeQuery("SELECT * FROM room_types WHERE room_type_id = '$roomtypeId' ");
								$roomtype_name = $get_roomtype_name[0]['name'];

								$get_roomtype_rate = $this->executeQuery("SELECT * FROM periods WHERE periods_id >= '$checkin_id' and periods_id <= '$checkout_id' ");
								$totalamnt = 0;
								$tariffesettimanali = "";
								$commission = 0;
								
								$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
								$id_property = $getPropertyId[0]['property_id'];
								
								if($id_property == '274690'){
									$roomtype_column_right = $roomtype_column; # original na rate column
									$column_tobecheck = $roomtype_column."_pax_".$roomtype_pax;
									$sqlll = "SHOW COLUMNS FROM `periods` LIKE '$column_tobecheck'";
									$check_column = $this->executeQuery($sqlll); # check kung naay rate column na naay pax
									if(count($check_column) > 0){
										$roomtype_column_right = $column_tobecheck; # Utrohon ang rate column nga naay pax.
									}
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$roomtype_column_right];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column_right];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column_right];
										}
									}
									/*$cntDaily = count($rooms[$x]['DayByDayPrice']['price']);
									for($xx = 0; $xx < $cntDaily; $xx++){
										$totalamnt += $rooms[$x]['DayByDayPrice']['price'][$xx];
										if($xx==0){
											$tariffesettimanali = $rooms[$x]['DayByDayPrice']['price'][$xx];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$rooms[$x]['DayByDayPrice']['price'][$xx];
										}
									}*/
								}else{
									$cnt2 = count($get_roomtype_rate);
									for($x2=0; $x2<$cnt2; $x2++){
										$totalamnt += $get_roomtype_rate[$x2][$roomtype_column];
										if($x2==0){
											$tariffesettimanali = $get_roomtype_rate[$x2][$roomtype_column];
										}else{
											$tariffesettimanali = $tariffesettimanali.",".$get_roomtype_rate[$x2][$roomtype_column];
										}
									}
								}
								
								/*HTL issue HTL-476 start*/
								$getBookingChannel = $this->executeQuery("SELECT * FROM booking_source WHERE channel_id = '$channel_id' AND status = 'active'");
								$cost_type = $getBookingChannel[0]['comm_type'];
								$cost_comm = $getBookingChannel[0]['cost_comm'];
								$deposit_status = $getBookingChannel[0]['deposit_status'];
								$comm_status = $getBookingChannel[0]['commission_paid'];
										
								$deposit_total = 0;
								$paid_total = 0;
								$paid_comm = 0;
								
								if($deposit_status == 'paid'){
									$deposit_total = $roomtype_price;
								}
								
								if($cost_type == 'percent'){
									$commission = (floatval($cost_comm) / 100) * floatval($totalamnt);
								}else if($cost_type == 'fix'){
									$commission = floatval($cost_comm) + floatval($totalamnt);
								}else{
									$commission = 0;
								}
								
								if($comm_status == 'yes'){
									$paid_comm = $commission;
								}
								
								$paid_total = floatval($paid_comm) + floatval($deposit_total);
								/*HTL issue HTL-476 end*/
								
								/* if($channel_source == 'booking_xml'){
									$commission = $roomtype_price * 0.15;
								}else if($channel_source == 'airbnb' || $channel_source == 'airbnb_xml'){
									$commission = 0;
								}else if($channel_source == 'expedia' || $channel_source == 'Expedia'){
									$commission = 0;
								}else{
									$commission = 0;
								} */
								
								$sql = "SELECT room.* 
								FROM `reservation` As res, `apartments` As room
								WHERE res.`reservation_id` = '$idprenota' and
									  res.`appartments_id` = room.`apartment_id`";
								$get_currentroomtype = $this->executeQuery($sql);
								// orig space
								/*if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){
									$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
									$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
									$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
								}
								
								$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
										
								if($is_room_available == false){
									$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
									$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
								}
								
								$tariffa = $roomtype_name."#@&".$totalamnt;
								$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET commissions='$commission' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
								
								$this->execute_update("UPDATE reservation SET deposit='$deposit_total' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET paid='$paid_total' WHERE reservation_id = '$idprenota'");*/
								# -------------------------------
								// orig space
								// temp codes start
								if($roomtypeId != $get_currentroomtype[0]["roomtype_id"]){ # nachange ang room type
									$idappartamenti = $this->auto_allocateRoom($roomtypeId, $year, $checkin_id, $checkout_id);		
									if($idappartamenti != 0){ # single room
										$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
										$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
										$tariffa = $roomtype_name."#@&".$totalamnt;
										$sqlReservation = "UPDATE `reservation` 
														   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
														   WHERE `reservation_id` = :reservation_id";
										$reservationUpdate = array(
																	':rate' => $tariffa,
																	':weekly_rates' => $tariffesettimanali,
																	':rate_total' => $totalamnt, 
																	':date_start_id' => $checkin_id,
																	':date_end_id' => $checkout_id, 
																	':commissions' => $commission,
																	':date_modified' => $data_modifica1,
																	':deposit' => $deposit_total,
																	':paid' => $paid_total,
																	':reservation_id' => $idprenota
																);
										$this->execute_insert($sqlReservation,$reservationUpdate);
										$this->room_split_trans_room($idappartamenti, $idprenota);
										$this->val_rules_engine(4, $roomtypeId, $iddatainizio1, $iddatafine1); # optimize ni
									}
									else{ # split rooms
										$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
														  FROM `reservation`
														  WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
										$res_check_result = $this->executeQuery($sql_check_res);
										if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
											# action: rebalance the splitted rooms
										    $this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
										}
										$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
										$split_rooms = $this->get_split_rooms($roomtypeId, $checkin_id, $checkout_id);

										if($split_rooms == false){
											# create the booking with unallocated room
											// -- execute activity log
											$socket_json_message = array(
												'action' => 'notify',
												'message' => "There's a reservation with unallocated room! REF#".$idprenota.", Client: ".$client_name,
												'host' => $_SERVER['SERVER_NAME'],
												'inserted_by' => 1,
												'reservation_link' => '#/modify-booking/'.$get_reservation[0]['reservation_conn_id'].'/$idprenota',
												'notification_type' => '1'
											);
											$this->log_activity($socket_json_message, true);
										}
										else{
											# proceed splitting the rooms
											//$split_from = 0;
											$period_Arr = range($checkin_id, $checkout_id);
											$weekly_rates_Arr = explode(",",$tariffesettimanali);
											$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
											$commission_temp = $commission;
											$deposit_total_temp = $deposit_total;
											$paid_total_temp = $paid_total;
															
											for($a=0; $a<count($split_rooms); $a++){
												$room_id_split = $split_rooms[$a]["room_id"];
												$date_start_id_split = $split_rooms[$a]["date_start_id"];
												$date_end_id_split = $split_rooms[$a]["date_end_id"];
												$weekly_rates_s = ""; # weekly_rates
												$rate_total_s = 0; # rate_total
												$commission_s = 0; # commission
												$deposit_total_s = 0; # deposit_total
												$paid_total_s = 0; # 

												for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
													$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
													if($b==$date_start_id_split){ 
														$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
													}
													else{ 
														$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
													}
												}
												$rate_s = $roomtype_name."#@&".$rate_total_s;

												if($rate_total_s >= $commission_temp){ # recalculate commissions
													$commission_s = $commission_temp;
													$commission_temp = 0;
												}
												else{
													$commission_s = $rate_total_s;
													$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
												}
												if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
													$deposit_total_s = $deposit_total_temp;
													$deposit_total_temp = 0;
												}
												else{
													$deposit_total_s = $rate_total_s;
													$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
												}
												if($rate_total_s >= $paid_total_temp){ # recalculate paid
													$paid_total_s = $paid_total_temp;
													$paid_total_temp = 0;
												}
												else{
													$paid_total_s = $rate_total_s;
													$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
												}

												if($a == 0){ # first room allocation
													$sqlReservation = "UPDATE `reservation` 
																	   SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
																	   WHERE `reservation_id` = :reservation_id";
													$reservationUpdate = array(
																		':reservation_id' => $idprenota,
																		':appartments_id' => $room_id_split,
																		':date_start_id' => $date_start_id_split, 
																		':date_end_id' => $date_end_id_split,
																		':rate' => $rate_s, 
																		':weekly_rates' => $weekly_rates_s,
																		':commissions' => $commission_s,
																		':rate_total' => $rate_total_s,
																		':deposit' => $deposit_total_s,
																		':paid' => $paid_total_s,
																		':date_modified' => $data_modifica1
																	);

													$this->execute_insert($sqlReservation,$reservationUpdate);
												}
												else{
													$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																			             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
													$reservationInsert = array(
																		':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																		':clients_id' => $get_reservation[0]['clients_id'], // 
																		':appartments_id' => $room_id_split,
																		':date_start_id' => $date_start_id_split, 
																		':date_end_id' => $date_end_id_split, 
																		':assign_app' => 'k',  
																		':pax' => $get_reservation[0]['pax'], // 
																		':original_rate' => $rate_s, 
																		':rate' => $rate_s, 
																		':weekly_rates' => $weekly_rates_s,
																		':commissions' => $commission_s,
																		':rate_total' => $rate_total_s,
																		':deposit' => $deposit_total_s,
																		':paid' => $paid_total_s,
																		':code' => $get_reservation[0]['code'], // 
																		':confirmation' => 'S',  
																		':split_from' => $idprenota, // 
																		':status' => 'active',
																		':inserted_date' => C_DIFF_ORE,
																		':inserted_host' => $get_reservation[0]['inserted_host'], //
																		':date_modified' => $data_modifica1,
																		':inserted_by' => '1'
																	);
													$this->execute_insert($sqlReservation,$reservationInsert);	
												}

											} # for($a=0; $a<count($split_rooms); $a++)
											// HTL-637 fix START
											if(count($res_check_result) == 1){ # single room
												$sql = "SELECT `reference_num` FROM `reservation_conn` WHERE `reservation_conn_id` = '".$get_reservation[0]['reservation_conn_id']."'";
												$check_ref_num = $this->executeQuery($sql);
												$ref_num_temp = '';
												if(count($check_ref_num) > 0){
													$ref_num_temp = $check_ref_num[0]['reference_num'];
												}
												$activity_log_message = array(
															'action' => "notify",
															'message' => "New booking with split rooms. REF#".$ref_num_temp.", Client: ".$client_name,
															'host' => $_SERVER['SERVER_NAME'],
															'inserted_by' => '1',
															'reservation_link' => '#/split_rooms/[room_split_id]',
															'notification_type' => '1'
												);
												$this->split_rooms_log_activity($idprenota, $activity_log_message);
											}
											// HTL-637 fix END											
										}
										
									}
								}
								else{ # wala ma.change ang room type
									if($checkin_id == $get_reservation[0]['date_start_id'] && $checkout_id == $get_reservation[0]['date_end_id']){ 

									}
									else{ # changes on period
										$is_room_available = $this->check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota);
										if($is_room_available == false){ # no room(s) available for new period
											
											$idappartamenti_2 = $this->auto_allocateRoom($get_currentroomtype[0]["roomtype_id"], $year, $checkin_id, $checkout_id);		
											if($idappartamenti_2 == 0){ # could be split
												$sql_check_res = "SELECT COUNT(`reservation_id`) AS res_assoc
																  FROM `reservation`
																  WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."')";
												$res_check_result = $this->executeQuery($sql_check_res);
												$this->execute_update("UPDATE reservation SET appartments_id=0 WHERE reservation_id = '$idprenota' and status = 'active'");
												if($res_check_result[0]['res_assoc'] > 1){ # splitted rooms
													# action: rebalance the splitted rooms
													$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
												}
												$split_rooms = $this->get_split_rooms($get_currentroomtype[0]["roomtype_id"], $checkin_id, $checkout_id);

												//$split_from = 0;
												$period_Arr = range($checkin_id, $checkout_id);
												$weekly_rates_Arr = explode(",",$tariffesettimanali);
												$weekly_rates_Arr = array_combine($period_Arr, $weekly_rates_Arr); # change keys to period id
												$commission_temp = $commission;
												$deposit_total_temp = $deposit_total;
												$paid_total_temp = $paid_total;
														
												for($a=0; $a<count($split_rooms); $a++){
													$room_id_split = $split_rooms[$a]["room_id"];
													$date_start_id_split = $split_rooms[$a]["date_start_id"];
													$date_end_id_split = $split_rooms[$a]["date_end_id"];
													$weekly_rates_s = ""; # weekly_rates
													$rate_total_s = 0; # rate_total
													$commission_s = 0; # commission
													$deposit_total_s = 0; # deposit_total
													$paid_total_s = 0; # 

													for($b=$date_start_id_split; $b<=$date_end_id_split; $b++){
														$rate_total_s += floatval($weekly_rates_Arr[$b.""]);
														if($b==$date_start_id_split){ 
															$weekly_rates_s .= $weekly_rates_Arr[$b.""]; 
														}
														else{ 
															$weekly_rates_s .= ",".$weekly_rates_Arr[$b.""]; 
														}
													}
													$rate_s = $roomtype_name."#@&".$rate_total_s;

													if($rate_total_s >= $commission_temp){ # recalculate commissions
														$commission_s = $commission_temp;
														$commission_temp = 0;
													}
													else{
														$commission_s = $rate_total_s;
														$commission_temp = floatval($commission_temp) - floatval($rate_total_s);
													}
													if($rate_total_s >= $deposit_total_temp){ # recalculate deposit total
														$deposit_total_s = $deposit_total_temp;
														$deposit_total_temp = 0;
													}
													else{
														$deposit_total_s = $rate_total_s;
														$deposit_total_temp = floatval($deposit_total_temp) - floatval($rate_total_s);
													}
													if($rate_total_s >= $paid_total_temp){ # recalculate paid
														$paid_total_s = $paid_total_temp;
														$paid_total_temp = 0;
													}
													else{
														$paid_total_s = $rate_total_s;
														$paid_total_temp = floatval($paid_total_temp) - floatval($rate_total_s);
													}

													if($a == 0){ # first room allocation
														$sqlReservation = "UPDATE `reservation` 
																			   SET `appartments_id`=:appartments_id, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `rate`=:rate, `weekly_rates`=:weekly_rates, `commissions`=:commissions, `rate_total`=:rate_total, `deposit`=:deposit, `paid`=:paid, `date_modified` = :date_modified
																			   WHERE `reservation_id` = :reservation_id";
														$reservationUpdate = array(
																	':reservation_id' => $idprenota,
																	':appartments_id' => $room_id_split,
																	':date_start_id' => $date_start_id_split, 
																	':date_end_id' => $date_end_id_split,
																	':rate' => $rate_s, 
																	':weekly_rates' => $weekly_rates_s,
																	':commissions' => $commission_s,
																	':rate_total' => $rate_total_s,
																	':deposit' => $deposit_total_s,
																	':paid' => $paid_total_s,
																	':date_modified' => $data_modifica1
																);

														$this->execute_insert($sqlReservation,$reservationUpdate);
													}
													else{
														$sqlReservation = "INSERT INTO reservation(reservation_conn_id, clients_id, appartments_id, date_start_id, date_end_id, assign_app, pax, original_rate, rate, weekly_rates, commissions, rate_total, deposit, paid, code, confirmation, split_from, status, inserted_date, inserted_host, date_modified, inserted_by) 
																		             VALUES(:reservation_conn_id, :clients_id, :appartments_id, :date_start_id, :date_end_id, :assign_app, :pax, :original_rate, :rate, :weekly_rates, :commissions, :rate_total, :deposit, :paid, :code, :confirmation, :split_from, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_host, :date_modified, :inserted_by)";
														$reservationInsert = array(
																	':reservation_conn_id' => $get_reservation[0]['reservation_conn_id'], // 
																	':clients_id' => $get_reservation[0]['clients_id'], // 
																	':appartments_id' => $room_id_split,
																	':date_start_id' => $date_start_id_split, 
																	':date_end_id' => $date_end_id_split, 
																	':assign_app' => 'k',  
																	':pax' => $get_reservation[0]['pax'], // 
																	':original_rate' => $rate_s, 
																	':rate' => $rate_s, 
																	':weekly_rates' => $weekly_rates_s,
																	':commissions' => $commission_s,
																	':rate_total' => $rate_total_s,
																	':deposit' => $deposit_total_s,
																	':paid' => $paid_total_s,
																	':code' => $get_reservation[0]['code'], // 
																	':confirmation' => 'S',  
																	':split_from' => $idprenota, // 
																	':status' => 'active',
																	':inserted_date' => C_DIFF_ORE,
																	':inserted_host' => $get_reservation[0]['inserted_host'], //
																	':date_modified' => $data_modifica1,
																	':inserted_by' => '1'
																);
														$this->execute_insert($sqlReservation,$reservationInsert);	
													}

												} # for($a=0; $a<count($split_rooms); $a++)

												if(count($res_check_result) == 1){ # single room
													$activity_log_message = array(
														'action' => "notify",
														'message' => "New booking with split rooms. REF#".$id_reservation.", Client: ".$client_name,
														'host' => $_SERVER['SERVER_NAME'],
														'inserted_by' => '1',
														'reservation_link' => '#/split_rooms/[room_split_id]',
														'notification_type' => '1'
													);
													$this->split_rooms_log_activity($idprenota, $activity_log_message);
												}
											}
											else{ # can allocate to a single room
												$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti_2' WHERE reservation_id = '$idprenota'");
												$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
												$tariffa = $roomtype_name."#@&".$totalamnt;
												$sqlReservation = "UPDATE `reservation` 
																   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
																   WHERE `reservation_id` = :reservation_id";
												$reservationUpdate = array(
																			':rate' => $tariffa,
																			':weekly_rates' => $tariffesettimanali,
																			':rate_total' => $totalamnt, 
																			':date_start_id' => $checkin_id,
																			':date_end_id' => $checkout_id, 
																			':commissions' => $commission,
																			':date_modified' => $data_modifica1,
																			':deposit' => $deposit_total,
																			':paid' => $paid_total,
																			':reservation_id' => $idprenota
																		);
												$this->execute_insert($sqlReservation,$reservationUpdate);
												$this->room_split_trans_room($idappartamenti_2, $idprenota);
											}
											
										}
										else{
											$this->execute_update("UPDATE reservation SET status='' WHERE split_from = '$idprenota'");
											$tariffa = $roomtype_name."#@&".$totalamnt;
											$sqlReservation = "UPDATE `reservation` 
															   SET `rate`=:rate, `weekly_rates`=:weekly_rates, `rate_total`=:rate_total, `date_start_id`=:date_start_id, `date_end_id`=:date_end_id, `commissions`=:commissions, `date_modified`=:date_modified, `deposit`=:deposit, `paid`=:paid
															   WHERE `reservation_id` = :reservation_id";
											$reservationUpdate = array(
																		':rate' => $tariffa,
																		':weekly_rates' => $tariffesettimanali,
																		':rate_total' => $totalamnt, 
																		':date_start_id' => $checkin_id,
																		':date_end_id' => $checkout_id, 
																		':commissions' => $commission,
																		':date_modified' => $data_modifica1,
																		':deposit' => $deposit_total,
																		':paid' => $paid_total,
																		':reservation_id' => $idprenota
																	);
											$this->execute_insert($sqlReservation,$reservationUpdate);
										}

										
									}
								}
								/* $tariffa = $roomtype_name."#@&".$totalamnt;
								$idappartamenti = $this->auto_allocateRoom($roomtypeId, 2017, $checkin_id, $checkout_id);
										
								$get_roomtype_id = $this->executeQuery("SELECT * FROM octorate_roomtype WHERE roomtype_id = $roomtypeId");
								$roomtype_column_id = $get_roomtype_id[0]['idroomtype_octo'];
								
								$this->execute_update("UPDATE reservation SET appartments_id='$idappartamenti' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_start_id='$checkin_id' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_end_id='$checkout_id' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET rate='$tariffa' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET weekly_rates='$tariffesettimanali' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET rate_total='$totalamnt' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE reservation SET date_modified='$data_modifica1' WHERE reservation_id = '$idprenota'");
								$this->execute_update("UPDATE bookings SET chnnl_manager_id_res='$roomtype_id_bb' WHERE reservation_id = '$idprenota'"); */
							}
						}
					}else{
						$roomtype_id_bb2 = $rooms[$x]['BbliverateNumberId'];
						$get_octorate_booking_cancelled = $this->executeQuery("SELECT * FROM bookings WHERE chnnl_manager_id_res = '$roomtype_id_bb2' AND status = 'active'");
						if(count($get_octorate_booking_cancelled) > 0){
							$idprenota2 = $get_octorate_booking_cancelled[0]['reservation_id'];
							$this->execute_update("UPDATE reservation SET status='cancelled' WHERE reservation_id = '$idprenota2'");
							// HTL-610 fix START
							$this->execute_update("UPDATE reservation SET status='cancelled' WHERE split_from = '$idprenota2' and status = 'active'");
							// HTL-610 fix END
							/* Part of email notification */
							$list_id_cancelled[$x] = $idprenota2;
							/* end part of email notification */
						}
					}
				}
			}
			/* Part of email notification */
			if(isset($for_email_data)){
				$email_notif_count = count($for_email_data);
				for($em=0; $em<$email_notif_count; $em++){
					$res_data = $for_email_data[$em]['res_data'];
					$list_id = $for_email_data[$em]['list_id'];
					// $reservation_conn_id = $res_data['reservation_conn_id'];
					// $reservation_period_type = $res_data['reservation_period_type'];
					// $socket_data = $res_data['socket_data'];
					$modified_from = "OTA";
					$data = $this->prepare_full_extend_period_email($list_id, $res_data, $modified_from);				
				}
				var_dump($for_email_data);
			}
			/* end part of email notification */
			
			/*for email cancellation*/
			$cancelled_from = "OTA";
			$selected_id = $this->select_con_id($id_resv);
			$res_con_id  = $selected_id[0]['res_con_id'];
			$cancelled_count = count($list_id_cancelled);
			if($cancelled_count > 0 || $list_id_cancelled != null ){
				$this->prepare_cancel_noshow_email("cancel", $res_con_id, $list_id_cancelled, $cancelled_from);
			}
			/*end email cancellation*/
			echo "success";
		}catch(PDOException $e) {
			/* var_dump($result); */
			$socket_json_message = array(
				'action' => 'reservation',
				'message' => "Error Modifying Reservation, REF#" . $id . "with error message: " . $e->getMessage(),
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => 1,
				'reservation_link' => '#/notification',
				'notification_type' => '1'
			);

			$this->log_activity($socket_json_message, false);
			/* echo "Error"; */
		}
	}
	/* end cancel or modified booking from OTA */
	
	public function octorate_booking_cancelled($reservation_id){
		$id_reservation = $reservation_id;
		$get_octorate_bookings = $this->executeQuery("SELECT * FROM octorate_bookings WHERE idbooking_octo = '$id_reservation'");
		$cnt1 = count($get_octorate_bookings);
		for($x1=0; $x1<$cnt1; $x1++){
			$idprenota = $get_octorate_bookings[$x1]['idprenota'];	
			$this->checkblocked_room($idprenota);
			$this->execute_update("UPDATE reservation SET status='cancelled' WHERE reservation_id = '$idprenota'");
			// HTL-610 fix START
			$this->execute_update("UPDATE reservation SET status='cancelled' WHERE split_from = '$idprenota' and status = 'active'");
			// HTL-610 fix END
			// HTL-636 fix START
			$this->room_split_trans_room(-1, $idprenota);
			// HTL-636 fix END
			/* Part of email notification */	
			$list_id[$x1] = $idprenota;
			/* end part of email notification */
		}
		// part of email notification
		$cancelled_from = "OTA";
		$res_con_id = $id_reservation;
		$cancel_msg = $this->prepare_cancel_noshow_email("cancel", $res_con_id, $list_id ,$cancelled_from);
		// end part of email notification
		echo "success";
		var_dump($cancel_msg);
	}
	
	public function checkblocked_room($reservation_id) {
		$get_reservation_details = $this->executeQuery("SELECT appartments_id, date_start_id, date_end_id FROM reservation WHERE reservation_id = '$reservation_id'");
		$room_id = $get_reservation_details[0]['appartments_id'];
		$date_start_id = $get_reservation_details[0]['date_start_id'];
		$date_end_id = $get_reservation_details[0]['date_end_id'];
		$room_is_blocked = $this->executeQuery("SELECT * FROM blocking a WHERE a.appartment_id = '$room_id' AND a.status = 'active' AND
							(((a.blocking_start_date_id <= '$date_start_id' AND a.blocking_end_date_id >= '$date_start_id') OR 
							(a.blocking_start_date_id <= '$date_end_id' AND a.blocking_end_date_id >= '$date_end_id')) OR 
							((a.blocking_start_date_id >= '$date_start_id' AND a.blocking_start_date_id <= '$date_end_id') OR 
							(a.blocking_end_date_id >= '$date_start_id' AND a.blocking_end_date_id <= '$date_end_id')))");
		if(count($room_is_blocked) > 0) {
			$period_id = $this->decrement_periods_cancelled($room_is_blocked, $date_start_id, $date_end_id, $room_id);
			$room_name = $this->getRoomName($room_id);
			foreach($room_is_blocked as $data) {
				$start = $this->get_period_date($data['blocking_start_date_id'], 'start_date');
				$end = $this->get_period_date($data['blocking_end_date_id'], 'end_date');
				$periods = $start . ' to ' . $end . ', ';
			}
			$socket_json_message = array(
				'action' => 'reservation',
				'message' => $room_name . " is automatically blocked for the period(s) of: " . $periods,
				/* 'message' => $period_id, */
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => 1,
				'reservation_link' => '#/room-blocking',
				'notification_type' => '1'
			);

			$this->log_activity($socket_json_message, true);
		}
	}
	
	public function add_octorate_booking($booking){
		$reservation = $booking;
		
		$lname = $reservation[0]['lname'];
		$fname = $reservation[0]['fname'];
		$telepone = $reservation[0]['telepone'];
		$nation = $reservation[0]['nation'];
		$check_in = $reservation[0]['check_in'];
		$check_out = $reservation[0]['check_out'];
		$room_id = $reservation[0]['room_id'];
		$pax = $reservation[0]['pax'];
		$total = $reservation[0]['total'];
		$ref = $reservation[0]['reference'];
		$resv_id = $reservation[0]['resv_id'];
		$id_property = $reservation[0]['id_property'];
		
		$fullName = $fname." ".$lname;
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
						'<BookReservationRequest>'.
						  '<Auth>'.
							  '<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
							  '<PropertyId>'.$id_property.'</PropertyId>'.
						  '</Auth>'.
						  '<Reservations>'.
						  '<Reservation>'.
							  '<From>'.$check_in.'</From>'.
							  '<To>'.$check_out.'</To>'.
							  '<Rooms>'.
								  '<Room>'.
									 '<Roomid>'.$room_id.'</Roomid>'.
									 '<Pax>'.$pax.'</Pax>'.
									 '<Total>'.$total.'</Total>'.
									 '<Guestname>'.$fullName.'</Guestname>'.
									 '<Telephone>'.$telepone.'</Telephone>'.
									 '<Provenienza>'.$nation.'</Provenienza>'.
									 '<Status>Confirmed</Status>'.
								  '</Room>'.
							  '</Rooms>'.
							  '</Reservation>'.
							  '</Reservations>'.
						 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		$array_data = json_decode(json_encode(simplexml_load_string($data)), true);
		
		$bb_id = $array_data['RoomUpdateMessage']['Bbliverateresvid'];
		
		$sqlBbUpdate = "UPDATE bookings SET chnnl_manager_id_res = '$bb_id' WHERE reservation_id = '$resv_id'";
		$this->execute_update($sqlBbUpdate);
	}
	
	public function incrementAvailability($relate_id,$property_id,$checkinDecr,$checkoutDecr){
		$propertyId = $property_id;
		$room_id = $relate_id;
		$check_in = $checkinDecr;
		$check_out = $checkoutDecr;
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directincrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<IncrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'.
								'<Room>'.
									'<RoomId>'.$room_id.'</RoomId>'.
									'<From>'.$check_in.'</From>'.
									'<To>'.$check_out.'</To>'.
								'</Room>'.
							'</Rooms>'.
							'</IncrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function decrementAvailability($relate_id,$property_id,$checkinDecr,$checkoutDecr){
		$propertyId = $property_id;
		$room_id = $relate_id;
		$check_in = $checkinDecr;
		$check_out = $checkoutDecr;
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directdecrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<DecrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'.
								'<Room>'.
									'<RoomId>'.$room_id.'</RoomId>'.
									'<From>'.$check_in.'</From>'.
									'<To>'.$check_out.'</To>'.
								'</Room>'.
							'</Rooms>'.
							'</DecrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function incrementAvail($relate_id,$checkinDecr,$checkoutDecr,$property_id){
		$propertyId = $property_id;
		$room_id = $relate_id;
		$check_in = $checkinDecr;
		$check_out = $checkoutDecr;

		$url = "https://www.octorate.com/api/live/callApi.php?method=directincrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<IncrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'.
								'<Room>'.
									'<RoomId>'.$room_id.'</RoomId>'.
									'<From>'.$check_in.'</From>'.
									'<To>'.$check_out.'</To>'.
								'</Room>'.
							'</Rooms>'.
							'</IncrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function decrementAvail($relate_id,$checkinDecr,$checkoutDecr,$property_id){
		$propertyId = $property_id;
		$room_id = $relate_id;
		$check_in = $checkinDecr;
		$check_out = $checkoutDecr;

		$url = "https://www.octorate.com/api/live/callApi.php?method=directdecrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<DecrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'.
								'<Room>'.
									'<RoomId>'.$room_id.'</RoomId>'.
									'<From>'.$check_in.'</From>'.
									'<To>'.$check_out.'</To>'.
								'</Room>'.
							'</Rooms>'.
							'</DecrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function getRoomAvailability($startDate, $endDate, $tableprenota, $room_type, $rate_total){
		$availRoomArr = array();
		$cntrRoom = 0;
		$index = 0;
		
		if($room_type == null){
			$sql ="SELECT * FROM apartments ORDER BY apartment_id ASC";
		}else{
			$sql ="SELECT * FROM apartments WHERE comment LIKE '".$room_type."%' AND apartment_id != '16' AND apartment_id != '24' AND apartment_id != '26' AND apartment_id != '27' ORDER BY apartment_id ASC";
		}
		$rooms = $this->executeQuery($sql);
		$cntRoom = count($rooms); 
					
		for($cntr = 0; $cntr < $cntRoom; $cntr++){
			$roomID = $rooms[$cntr]['apartment_id'];
			$commento = explode(" 3", $rooms[$cntr]['comment']);
				
			$sqlBook = "SELECT * FROM ".$tableprenota." WHERE appartments_id = '".$roomID."' ";
			$bookings = $this->executeQuery($sqlBook);
			$cntBooking = count($bookings);
					
				for($cntr2 = 0; $cntr2 < $cntBooking; $cntr2++){
					$dateInizio = $bookings[$cntr2]['date_start_id'];
					$dateFine = $bookings[$cntr2]['date_end_id'];
					if((($startDate >= $dateInizio && $startDate <= $dateFine) || ($endDate >= $dateInizio && $endDate <= $dateFine)) || ($startDate <= $dateInizio && $endDate >= $dateFine)){
						$cntrRoom++;
					}
				}
					
				if($cntrRoom == 0){
					$availRoomArr[$index]['room_num'] = $roomID;
					$availRoomArr[$index]['room_type'] = $commento[0];
					$availRoomArr[$index]['room_rate'] = $rate_total;
					$index++;
				}
					
			$cntrRoom = 0;
		}
			
		array_multisort(array_column($availRoomArr, 'room_type'), SORT_ASC, $availRoomArr);
		$result = array_column($availRoomArr, null, 'room_type');
			
		$resultArr = $this->unique_multidim_array($result,"room_type");
			
		return $resultArr;
	}
	
	public function unique_multidim_array($array, $key) {
		$temp_array = array();
		$i = 0;
		$key_array = array();
	   
		foreach($array as $val) {
			if (!in_array($val[$key], $key_array)) {
				$key_array[$i] = $val[$key];
				array_push($temp_array,$val);
			}
			$i++;
		}
		return $temp_array;
	}

	/* Reports : START */
	public function sales_inventory($start_date, $end_date, $filter){ # $start_date, $end_date : format date : Y-m-d
		$result;
		$sql = "SELECT CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   SUM((SELECT COALESCE(SUM(`item_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As tot_ni,
					   SUM((SELECT COALESCE(SUM(`item_orig_price`-`item_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As discounts,
					   SUM((SELECT COALESCE(SUM(`item_orig_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As gross_sales
				FROM `costs` As costs
				WHERE CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
				GROUP BY CAST(costs.`inserted_date` AS DATE);";
		//CAST(costs.`inserted_date` AS DATE) As inserted_date, (SELECT COALESCE(SUM(`item_price`*`item_qty`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`) As tot_ni
		$result_sales = $this->executeQuery($sql); # sales on cash
		/*$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, IF(costs.`type` = 'sg', COALESCE(SUM(costs.`value`*costs.`quantity`), 0), COALESCE(SUM(costs.`value`*costs.`quantity`*costs.`days`), 0)) As tot_ni
				FROM `reservation_costs` As costs
				WHERE CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);";
		$result_sales1 = $this->executeQuery($sql);*/ # sales on reservation
		$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   COALESCE(SUM(costs.`value`*costs.`quantity`), 0) As tot_ni, 
					   COALESCE(SUM(costs.`discount`), 0) As discounts, 
					   COALESCE(SUM((costs.`value`*costs.`quantity`) + costs.`discount`), 0) As gross_sales
				FROM `reservation_costs` As costs
				WHERE costs.`type` = 'sg' and
					  CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);"; // COALESCE(SUM((costs.`value`*costs.`quantity`)+costs.`discount`), 0) As gross_sale
		$result_sales1_sg = $this->executeQuery($sql); # sales on reservation
		$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   COALESCE(SUM(costs.`value`*costs.`quantity`*costs.`days`), 0) As tot_ni, 
					   COALESCE(SUM(costs.`discount`), 0) As discounts, 
					   COALESCE(SUM((costs.`value`*costs.`quantity`*costs.`days`) + costs.`discount`), 0) As gross_sales
				FROM `reservation_costs` As costs
				WHERE costs.`type` = 'dl' and
					  CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);"; // COALESCE(SUM((costs.`value`*costs.`quantity`*costs.`days`)+costs.`discount`), 0) As gross_sales
		$result_sales1_dl = $this->executeQuery($sql); # sales on reservation

		$datetime1 = date_create($start_date);
	    $datetime2 = date_create($end_date);
	    $interval = date_diff($datetime1, $datetime2);
	    $days = $interval->format("%a");	
		$temp_date = date('Y-m-d',strtotime ($start_date));
		for($x=0; $x<$days+1; $x++){
			$key = '';
			$key = array_search($temp_date, array_column($result_sales, 'inserted_date'));
			$key = (string) $key;
			$result[$x]["inserted_date"] = $temp_date;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] = $result_sales[$key]["tot_ni"];
				$result[$x]["discounts"] = $result_sales[$key]["discounts"];
				$result[$x]["gross_sales"] = $result_sales[$key]["gross_sales"];
			}
			else{
				$result[$x]["net_sales"] = 0;
				$result[$x]["discounts"] = 0;
				$result[$x]["gross_sales"] = 0;
			}
			$key = '';
			$key = array_search($temp_date, array_column($result_sales1_sg, 'inserted_date'));
			$key = (string) $key;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] += $result_sales1_sg[$key]["tot_ni"];
				$result[$x]["discounts"] += $result_sales1_sg[$key]["discounts"];
				$result[$x]["gross_sales"] += $result_sales1_sg[$key]["gross_sales"];
			}
			$key = '';
			$key = array_search($temp_date, array_column($result_sales1_dl, 'inserted_date'));
			$key = (string) $key;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] += $result_sales1_dl[$key]["tot_ni"];
				$result[$x]["discounts"] += $result_sales1_dl[$key]["discounts"];
				$result[$x]["gross_sales"] += $result_sales1_dl[$key]["gross_sales"];
			}

			$temp_date = date('Y-m-d',strtotime ( '+1 day' , strtotime ( $temp_date ) ));
		}
		return $result;
    }
    public function sales_inventory1($start_date, $end_date, $filter){ # $start_date, $end_date : format date : Y-m-d
		$result;
		$sql = "SELECT CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   SUM((SELECT COALESCE(SUM(`item_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As tot_ni,
					   SUM((SELECT COALESCE(SUM(`item_orig_price`-`item_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As discounts,
					   SUM((SELECT COALESCE(SUM(`item_orig_price`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`)) As gross_sales
				FROM `costs` As costs
				WHERE CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
				GROUP BY CAST(costs.`inserted_date` AS DATE);";
		//CAST(costs.`inserted_date` AS DATE) As inserted_date, (SELECT COALESCE(SUM(`item_price`*`item_qty`), 0) FROM `cost_item` WHERE `cost_item`.`cost_id` = costs.`cost_id`) As tot_ni
		$result_sales = $this->executeQuery($sql); # sales on cash
		/*$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, IF(costs.`type` = 'sg', COALESCE(SUM(costs.`value`*costs.`quantity`), 0), COALESCE(SUM(costs.`value`*costs.`quantity`*costs.`days`), 0)) As tot_ni
				FROM `reservation_costs` As costs
				WHERE CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);";
		$result_sales1 = $this->executeQuery($sql);*/ # sales on reservation
		$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   COALESCE(SUM(costs.`value`*costs.`quantity`), 0) As tot_ni, 
					   COALESCE(SUM(costs.`discount`), 0) As discounts, 
					   COALESCE(SUM((costs.`value`*costs.`quantity`) + costs.`discount`), 0) As gross_sales
				FROM `reservation_costs` As costs
				WHERE costs.`type` = 'sg' and
					  CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);"; // COALESCE(SUM((costs.`value`*costs.`quantity`)+costs.`discount`), 0) As gross_sale
		$result_sales1_sg = $this->executeQuery($sql); # sales on reservation
		$sql = "SELECT Distinct CAST(costs.`inserted_date` AS DATE) As inserted_date, 
					   COALESCE(SUM(costs.`value`*costs.`quantity`*costs.`days`), 0) As tot_ni, 
					   COALESCE(SUM(costs.`discount`), 0) As discounts, 
					   COALESCE(SUM((costs.`value`*costs.`quantity`*costs.`days`) + costs.`discount`), 0) As gross_sales
				FROM `reservation_costs` As costs
				WHERE costs.`type` = 'dl' and
					  CAST(costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and
        			  CAST(costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) $filter
					  GROUP BY CAST(costs.`inserted_date` AS DATE);"; // COALESCE(SUM((costs.`value`*costs.`quantity`*costs.`days`)+costs.`discount`), 0) As gross_sales
		$result_sales1_dl = $this->executeQuery($sql); # sales on reservation

		$datetime1 = date_create($start_date);
	    $datetime2 = date_create($end_date);
	    $interval = date_diff($datetime1, $datetime2);
	    $days = $interval->format("%a");	
		$temp_date = date('Y-m-d',strtotime ($start_date));
		for($x=0; $x<$days+1; $x++){
			$key = '';
			$key = array_search($temp_date, array_column($result_sales, 'inserted_date'));
			$key = (string) $key;
			$result[$x]["inserted_date"] = $temp_date;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] = $result_sales[$key]["tot_ni"];
				$result[$x]["discounts"] = $result_sales[$key]["discounts"];
				$result[$x]["gross_sales"] = $result_sales[$key]["gross_sales"];
			}
			else{
				$result[$x]["net_sales"] = 0;
				$result[$x]["discounts"] = 0;
				$result[$x]["gross_sales"] = 0;
			}
			$key = '';
			$key = array_search($temp_date, array_column($result_sales1_sg, 'inserted_date'));
			$key = (string) $key;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] += $result_sales1_sg[$key]["tot_ni"];
				$result[$x]["discounts"] += $result_sales1_sg[$key]["discounts"];
				$result[$x]["gross_sales"] += $result_sales1_sg[$key]["gross_sales"];
			}
			$key = '';
			$key = array_search($temp_date, array_column($result_sales1_dl, 'inserted_date'));
			$key = (string) $key;
			if(isset($key) && ($key != '' && $key != null)){ # !empty($key) && !is_null($key)
				$result[$x]["net_sales"] += $result_sales1_dl[$key]["tot_ni"];
				$result[$x]["discounts"] += $result_sales1_dl[$key]["discounts"];
				$result[$x]["gross_sales"] += $result_sales1_dl[$key]["gross_sales"];
			}
			$result_addon = $this->get_tot_varaddon($temp_date);
			$result[$x]["net_sales"] += $result_addon[1];
			$result[$x]["discounts"] += 0;
			$result[$x]["gross_sales"] += $result_addon[1];
			$temp_date = date('Y-m-d',strtotime ( '+1 day' , strtotime ( $temp_date ) ));
		}
		return $result;
    }
    public function get_tot_varaddon($sel_date){
    	$result = array();
		$sql = "SELECT *
				FROM `reservation_costs2` As addon
				WHERE addon.`status` = 'active' and 
					  CAST(addon.`inserted_date` AS DATE) = CAST('".$sel_date."' AS DATE)";
		$result_addon = $this->executeQuery($sql);
		$cnt = count($result_addon);
		$total = 0;
		$cost_total = 0;
		for($x=0; $x<$cnt; $x++){
			$addon_type = $result_addon[$x]["addon_type"];
			$price = $result_addon[$x]["price"];
			$cost = $result_addon[$x]["cost"];
			$quantity = $result_addon[$x]["quantity"];
			$multiply_by = $result_addon[$x]["multiply_by"];
			if($addon_type == "single"){ # single addon type
				if($multiply_by == 0){
					$total += ($price*$quantity);
					$cost_total += ($cost*$quantity);
				}
				else{
					$total += ($price*$quantity)*$multiply_by;
					$cost_total += ($cost*$quantity)*$multiply_by;
				}
			}
			else{ # daily addon type
				$price_array = explode(",",$price);
				$multiply_by_array = explode(",",$multiply_by);
				$cnt1 = count($price_array);
				for($y=0; $y<$cnt1; $y++){
					$price1 = $price_array[$y];
					$multiply_by1 = $multiply_by_array[$y];
					if($multiply_by == 0){
						$total += ($price1*$quantity);
						$cost_total += ($cost*$quantity);
					}
					else{
						$total += ($price1*$quantity)*$multiply_by1;
						$cost_total += ($cost*$quantity)*$multiply_by1;
					}
				}

			}
		}

		# return total for var addon
		$result[] = $cost_total;
		$result[] = $total;
		return $result;

	}
    public function get_res_num($year, $month, $status){
		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];

		$sql = "SELECT count(`reservation_id`) As res
				FROM `reservation`
				WHERE `status` = '".$status."' and 
					  ((`date_start_id` >= $start_id and `date_start_id` <= $end_id) or 
					  (`date_end_id` >= $start_id and `date_end_id` <= $end_id) or 
					  (`date_start_id` <= $start_id and `date_end_id` >= $start_id) or 
					  (`date_start_id` <= $end_id and `date_end_id` >= $end_id) )  AND appartments_id != 0";
		$result = $this->executeQuery($sql); 

		return $result[0]["res"];
	}
	public function get_booking_source_percentage($tot_res_month, $year, $month, $status){
		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];

		$result = array();
		$sql = "SELECT *  FROM `booking_source` WHERE `status` = 'active'";
		$result_booking_source = $this->executeQuery($sql);
		$cnt1 = count($result_booking_source);
		for($x=0; $x<$cnt1; $x++){
			$sql = "SELECT count(`reservation_id`) As res
					FROM `reservation` As res, `reservation_conn` As res_con
					WHERE res.`status` = '".$status."' and 
						  res.`reservation_conn_id` = res_con.`reservation_conn_id` and
						  res_con.`bookingsource_id` = '".$result_booking_source[$x]["booking_source_id"]."' and
						  ((res.`date_start_id` >= $start_id and res.`date_start_id` <= $end_id) or (res.`date_end_id` >= $start_id and res.`date_end_id` <= $end_id) or (res.`date_start_id` <= $start_id and res.`date_end_id` >= $start_id) or (res.`date_start_id` <= $end_id and res.`date_end_id` >= $end_id) )";
			$result_temp = $this->executeQuery($sql);
			$percent = 0;
			if($tot_res_month > 0){
				$percent = ($result_temp[0]["res"]/$tot_res_month)*100;
			}
			$result[$x]["name"] = $result_booking_source[$x]["booking_source_name"];
			$result[$x]["percent"] = $percent;
		}
		return $result;
	}
	public function get_total_commisions($year, $month, $status){
		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];

		$sql = "SELECT COALESCE(SUM(`commissions`), 0) As total_commissions
				FROM `reservation`
				WHERE `status` = '".$status."' and ((`date_start_id` >= $start_id and `date_start_id` <= $end_id) or (`date_end_id` >= $start_id and `date_end_id` <= $end_id) or (`date_start_id` <= $start_id and `date_end_id` >= $start_id) or (`date_start_id` <= $end_id and `date_end_id` >= $end_id) )";
		$result = $this->executeQuery($sql); 
		
		return $result[0]["total_commissions"];
	}
	public function get_occupancy_rate($year, $month){
		$lastDay = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
		//$totDaysMon = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];

		$sql = "SELECT COALESCE(COUNT(`apartment_id`), 0) as totRoom FROM `apartments`";
		$result = $this->executeQuery($sql);
		$arrDaily = array();
		$totPercentage = 0;
		for($num = 1; $num<=$lastDay; $num++){
			$sql2 = "SELECT COALESCE(Count(`reservation_id`), 0) as totDaily FROM `reservation` WHERE `date_start_id` <= $start_id and `date_end_id` >= $start_id and `status` = 'active'";
			$result2 = $this->executeQuery($sql2);
			$sql3 = "SELECT COALESCE(Count(`reservation_id`), 0) as totDaily FROM `transfer_room_history` WHERE `date_start_id` <= $start_id and `date_end_id` >= $start_id and `status` = 'active' and `transfer_status` = 'checkin' and `rate_total` != 0";
			$result3 = $this->executeQuery($sql3);

			if(($result2[0]['totDaily'] + $result3[0]['totDaily']) > 0){
				$totPercentage = (($result2[0]['totDaily'] + $result3[0]['totDaily'])/ $result[0]['totRoom']) * 100;
			}
			$arrDaily[$num-1]['day'] = $num;
			$arrDaily[$num-1]['percentage'] = round($totPercentage);
			$start_id++;
		}
		return $arrDaily;
	}
	public function get_section_sales($year, $month){
		$result = array();
		$year_month = $year."-".$month."-";
		$start_date = $year."-".$month."-01";
		$end_date = date("Y-m-t", strtotime($start_date));
		//$colors = array("background: #7db5ec;border-color: #5096da;","background: #7cbe88;border-color: #166e26;","background: #ff707a;border-color: #ff2b3a;","background: #55d9ff;border-color: #30b4d9;","background: #fcaf17;border-color: #a77412;","background: #ea404c;border-color: #be3e46;");
		$sql = "SELECT *  FROM `price_section` WHERE `status` = 'active'";
		$result_price_section = $this->executeQuery($sql);
		$cnt = count($result_price_section);
		for($x=0; $x<$cnt; $x++){
			# get the sales
			/*$sql = "SELECT IF(res_costs.`type` = 'sg', COALESCE(SUM(res_costs.`value`*res_costs.`quantity`), 0), COALESCE(SUM(res_costs.`value`*res_costs.`quantity`*res_costs.`days`), 0)) As sales
					FROM `reservation_costs` As res_costs, `rates` As prices
					WHERE CAST(res_costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
						  CAST(res_costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
						  prices.`rates_id` = res_costs.`rates_id` and
						  prices.`price_section_id` = '".$result_price_section[$x]["price_section_id"]."'";
			$result_sales = $this->executeQuery($sql);*/
			$sql = "SELECT COALESCE(SUM(res_costs.`value`*res_costs.`quantity`), 0) As sales
					FROM `reservation_costs` As res_costs, `rates` As prices, `reservation` As res
					WHERE CAST(res_costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
						  CAST(res_costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
						  prices.`rates_id` = res_costs.`rates_id` and
						  prices.`price_section_id` = '".$result_price_section[$x]["price_section_id"]."' and
						  res_costs.`type` = 'sg' and
					      res.`reservation_id` = res_costs.`reservation_id` and
					      res.`status` = 'active'"; // HTL-442 fix: included reservation db table
			$result_sales = $this->executeQuery($sql);
			$sql = "SELECT COALESCE(SUM(res_costs.`value`*res_costs.`quantity`*res_costs.`days`), 0) As sales
					FROM `reservation_costs` As res_costs, `rates` As prices, `reservation` As res
					WHERE CAST(res_costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
						  CAST(res_costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
						  prices.`rates_id` = res_costs.`rates_id` and
						  prices.`price_section_id` = '".$result_price_section[$x]["price_section_id"]."' and
						  res_costs.`type` = 'dl' and
					      res.`reservation_id` = res_costs.`reservation_id` and
					      res.`status` = 'active'"; // HTL-442 fix: included reservation db table
			$result_sales1 = $this->executeQuery($sql);


			$result[$x]["id"] = $result_price_section[$x]["price_section_id"];
			$result[$x]["name"] = $result_price_section[$x]["section_label"];
			$result[$x]["sales"] = $result_sales[0]["sales"]+$result_sales1[0]["sales"];
			$result[$x]["color"] = ""; #$colors[array_rand($colors)];
		}

		$sql = "SELECT *
				FROM `reservation_costs2` As addon, `reservation` As res
				WHERE addon.`status` = 'active' and 
					  CAST(addon.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
					  CAST(addon.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
					  res.`reservation_id` = addon.`reservation_id` and
					  res.`status` = 'active'"; // HTL-442 fix: included reservation db table
		$result_addon = $this->executeQuery($sql);
		$cnt = count($result_addon);
		$total = 0;
		for($x=0; $x<$cnt; $x++){
			$addon_type = $result_addon[$x]["addon_type"];
			$price = $result_addon[$x]["price"];
			$quantity = $result_addon[$x]["quantity"];
			$multiply_by = $result_addon[$x]["multiply_by"];
			if($addon_type == "single"){ # single addon type
				if($multiply_by == 0){
					$total += ($price*$quantity);
				}
				else{
					$total += ($price*$quantity)*$multiply_by;
				}
			}
			else{ # daily addon type
				$price_array = explode(",",$price);
				$multiply_by_array = explode(",",$multiply_by);
				$cnt1 = count($price_array);
				for($y=0; $y<$cnt1; $y++){
					$price1 = $price_array[$y];
					$multiply_by1 = $multiply_by_array[$y];
					if($multiply_by == 0){
						$total += ($price1*$quantity);
					}
					else{
						$total += ($price1*$quantity)*$multiply_by1;
					}
				}

			}
		}
		$cnt1 = count($result);
		$result[$cnt1]["id"] = 0;
		$result[$cnt1]["name"] = "Variable Add-on";
		$result[$cnt1]["sales"] = $total;
		$result[$cnt1]["color"] = ""; #$colors[array_rand($colors)];

		return $result;
	}
	public function get_total_receivable_month($year, $month){
		$result = array();
		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];
		$start_date = $this->get_period_date($start_id, "start_date");
		$end_date = $this->get_period_date($end_id, "start_date");

		$total_receivable = 0;

		$sql = "SELECT `date_start_id`, `weekly_rates`, `reservation_id`
				FROM `reservation`
				WHERE `status` = 'active' and 
					  ((`date_start_id` >= $start_id and `date_start_id` <= $end_id) or (`date_end_id` >= $start_id and `date_end_id` <= $end_id) or (`date_start_id` <= $start_id and `date_end_id` >= $start_id) or (`date_start_id` <= $end_id and `date_end_id` >= $end_id) )";
		$result_res = $this->executeQuery($sql); 
		$cnt = count($result_res);
		for($x=0; $x<$cnt; $x++){
			$reservation_id = $result_res[$x]["reservation_id"];
			$date_start_id = $result_res[$x]["date_start_id"];
			$date_end_id = $result_res[$x]["date_start_id"];
			$weekly_rates = $result_res[$x]["weekly_rates"];
			$rates_Array = explode(',', $weekly_rates);
			$cnt_rates = count($rates_Array);
			$temp_cnt = $end_id - $date_end_id;
			if($temp_cnt < 0){
				$rates_Array += $temp_cnt;
			}
			$x1 = 0;
			$temp_x1 = $start_id - $date_start_id;
			if($temp_x1 > 0){
				$x1 = $temp_x1;
			}
			for($x2=$x1; $x2<$cnt_rates; $x2++){
				$total_receivable += $rates_Array[$x2];
			}
		}
		$sql = "SELECT `date_start_id`, `weekly_rates`, `reservation_id`
				FROM `transfer_room_history`
				WHERE `status` = 'active' and 
					  ((`date_start_id` >= $start_id and `date_start_id` <= $end_id) or (`date_end_id` >= $start_id and `date_end_id` <= $end_id) or (`date_start_id` <= $start_id and `date_end_id` >= $start_id) or (`date_start_id` <= $end_id and `date_end_id` >= $end_id) ) and 
					  `transfer_status` = 'checkin' and 
					  `rate_total` != 0";
		$result_res1 = $this->executeQuery($sql); 
		$cnt = count($result_res1);
		for($x=0; $x<$cnt; $x++){
			$reservation_id = $result_res1[$x]["reservation_id"];
			$date_start_id = $result_res1[$x]["date_start_id"];
			$date_end_id = $result_res1[$x]["date_start_id"];
			$weekly_rates = $result_res1[$x]["weekly_rates"];
			$rates_Array = explode(',', $weekly_rates);
			$cnt_rates = count($rates_Array);
			$temp_cnt = $end_id - $date_end_id;
			if($temp_cnt < 0){
				$rates_Array += $temp_cnt;
			}
			$x1 = 0;
			$temp_x1 = $start_id - $date_start_id;
			if($temp_x1 > 0){
				$x1 = $temp_x1;
			}
			for($x2=$x1; $x2<$cnt_rates; $x2++){
				$total_receivable += $rates_Array[$x2];
			}
		}
		//reservation_costs
		$sql = "SELECT COALESCE(SUM(res_costs.`value`*res_costs.`quantity`), 0) As total_charges
				FROM `reservation_costs` As res_costs
				WHERE CAST(res_costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
					  CAST(res_costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
					  res_costs.`type` = 'sg'";
		$result_res_costs = $this->executeQuery($sql);
		$sql = "SELECT COALESCE(SUM(res_costs.`value`*res_costs.`quantity`*res_costs.`days`), 0) As total_charges
				FROM `reservation_costs` As res_costs
				WHERE CAST(res_costs.`inserted_date` AS DATE) >= CAST('".$start_date."' AS DATE) and 
					  CAST(res_costs.`inserted_date` AS DATE) <= CAST('".$end_date."' AS DATE) and
					  res_costs.`type` = 'dl'";
		$result_res_costs1 = $this->executeQuery($sql);
		$total_receivable += $result_res_costs[0]["total_charges"]+$result_res_costs1[0]["total_charges"];

		return $total_receivable;

	}
	public function get_total_received_month($year, $month){
		$year_month = $year."-".$month."-";
		$sql = "SELECT COALESCE(MIN(`periods_id`), 0) As start_id, COALESCE(MAX(`periods_id`), 0) As end_id  FROM `periods` WHERE `start_date` LIKE '$year_month%'";
		$result_date_id = $this->executeQuery($sql);
		$start_id = $result_date_id[0]["start_id"];
		$end_id = $result_date_id[0]["end_id"];
		$start_date = $this->get_period_date($start_id, "start_date");
		$end_date = $this->get_period_date($end_id, "start_date");

		$sql = "SELECT COALESCE(SUM(`paid`), 0) As tot_paid
				FROM `res_payment_history`
				WHERE CAST(`date_inserted` AS DATE) >= CAST('".$start_date."' AS DATE) and 
					  CAST(`date_inserted` AS DATE) <= CAST('".$end_date."' AS DATE)";
		$result_res_payment_history = $this->executeQuery($sql);
		return $result_res_payment_history[0]["tot_paid"];

	}
	public function get_total_received_today(){
		$cashbox_id = $this->get_global_variables('res_cashbox_id');
		$date_today = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
		/* $sql = "SELECT COALESCE(SUM(`paid`), 0) As tot_paid
				FROM `res_payment_history`
				WHERE CAST(`date_inserted` AS DATE) = CAST('".$date_today."' AS DATE)";
		$result_res_payment_history = $this->executeQuery($sql); */
		$sql = "SELECT COALESCE(SUM(val_cost), 0) AS income_total FROM costs WHERE CAST(inserted_date AS DATE) = CAST('".$date_today."' AS DATE) AND cost_type = 'e'";
		$income_total = $this->executeQuery($sql);
		
		$sql = "SELECT COALESCE(SUM(val_cost), 0) AS expense_total FROM costs WHERE CAST(inserted_date AS DATE) = CAST('".$date_today."' AS DATE) AND cost_type = 's'";
		$expense_total = $this->executeQuery($sql);
		
		$recieved_today = $income_total[0]['income_total'] - $expense_total[0]['expense_total'];
		
		return $recieved_today;

	}
    /* Reports : END */

    /* variable Add on START */
    public function get_res_rates($reservation_id){
    	$sql = "SELECT res.*
				FROM `reservation` As res
				WHERE res.`reservation_id` = $reservation_id";
		$result_res = $this->executeQuery($sql);
		$sql = "SELECT res_trans.*
				FROM `transfer_room_history` As res_trans
				WHERE res_trans.`reservation_id` = $reservation_id AND
					  res_trans.`status` = 'active' and
					  res_trans.`transfer_status` = 'checkin' ORDER BY res_trans.`reservation_id`";
		$result_res_trans = $this->executeQuery($sql);

		$result = [];
		for($x=0; $x<count($result_res_trans); $x++){
			$date_start_id = $result_res_trans[$x]["date_start_id"];
			$date_end_id = $result_res_trans[$x]["date_end_id"];
			$rates_ni = explode(',', $result_res_trans[$x]["weekly_rates"]);
			$temp_start = $date_start_id;
			for($y=0; $y<count($rates_ni); $y++){
				$result[$temp_start] = $rates_ni[$y];
				$temp_start++;
			}
		}
		for($x=0; $x<count($result_res); $x++){
			$date_start_id = $result_res[$x]["date_start_id"];
			$date_end_id = $result_res[$x]["date_end_id"];
			$rates_ni = explode(',', $result_res[$x]["weekly_rates"]);
			$temp_start = $date_start_id;
			for($y=0; $y<count($rates_ni); $y++){
				$result[$temp_start] = $rates_ni[$y];
				$temp_start++;
			}
		}

		return $result;
	}
	public function get_res_dates($reservation_id){
    	$sql = "SELECT res.*
				FROM `reservation` As res
				WHERE res.`reservation_id` = $reservation_id";
		$result_res = $this->executeQuery($sql);
		$sql = "SELECT res_trans.*
				FROM `transfer_room_history` As res_trans
				WHERE res_trans.`reservation_id` = $reservation_id AND
					  res_trans.`status` = 'active' and
					  res_trans.`transfer_status` = 'checkin' ORDER BY res_trans.`reservation_id`";
		$result_res_trans = $this->executeQuery($sql);

		$result = [];
		for($x=0; $x<count($result_res_trans); $x++){
			$date_start_id = $result_res_trans[$x]["date_start_id"];
			$date_end_id = $result_res_trans[$x]["date_end_id"];
			$temp_start = $date_start_id;
			for($y=0; $y<(($date_end_id-$date_start_id)+1); $y++){
				$result[$temp_start] = $this->get_period_date($temp_start, "start_date");
				$temp_start++;
			}
		}
		for($x=0; $x<count($result_res); $x++){
			$date_start_id = $result_res[$x]["date_start_id"];
			$date_end_id = $result_res[$x]["date_end_id"];
			$temp_start = $date_start_id;
			for($y=0; $y<(($date_end_id-$date_start_id)+1); $y++){
				$result[$temp_start] = $this->get_period_date($temp_start, "start_date");
				$temp_start++;
			}
		}

		return $result;
	}

	public function get_res_total_w_disc($reservation_id){
    	$sql = "SELECT res.*
				FROM `reservation` As res
				WHERE res.`reservation_id` = $reservation_id";
		$result_res = $this->executeQuery($sql);
		$discount = $result_res[0]["discount"];
		$sql = "SELECT res_trans.*
				FROM `transfer_room_history` As res_trans
				WHERE res_trans.`reservation_id` = $reservation_id AND
					  res_trans.`status` = 'active' and
					  res_trans.`transfer_status` = 'checkin' ORDER BY res_trans.`reservation_id`";
		$result_res_trans = $this->executeQuery($sql);

		$result_tot_price = 0;
		for($x=0; $x<count($result_res_trans); $x++){
			$rates_ni = explode(',', $result_res_trans[$x]["weekly_rates"]);
			for($y=0; $y<count($rates_ni); $y++){
				$result_tot_price += $rates_ni[$y];
			}
		}
		for($x=0; $x<count($result_res); $x++){
			$rates_ni = explode(',', $result_res[$x]["weekly_rates"]);
			for($y=0; $y<count($rates_ni); $y++){
				$result_tot_price += $rates_ni[$y];
			}
		}
		$result_tot_price = $result_tot_price - $discount;
		return $result_tot_price;
	}

	public function get_res_varaddon_price($roomtype_id, $pax, $addon_id){
    	$sql = "SELECT DISTINCT var_addon_price.*
				FROM `var_addon` As var_addon, `var_addon_price` As var_addon_price
				WHERE var_addon.`id` = $addon_id and
					  var_addon_price.`var_addon_id` = var_addon.`id` and 
					  var_addon.`status` = 'active' and 
					  var_addon.`disp_checkin` = 'Yes' and 
					  var_addon_price.`status` = 'active' and
					  (var_addon_price.`rules_allowed_pax` = '$pax' or var_addon_price.`rules_allowed_pax` = 'None') and
					  (find_in_set($roomtype_id,var_addon_price.`rules_allowed_roomtypes`) or var_addon_price.`rules_allowed_roomtypes` = 'None')";
		$result_var_addon_price = $this->executeQuery($sql);

		return $result_var_addon_price;
	}

	public function get_date_id($date, $end_or_start) {
		$sql = "";
		if($end_or_start == "start") {
			$sql = "SELECT `periods_id` FROM `periods` WHERE CAST(`start_date` AS DATE) = CAST('$date' AS DATE)";
		}
		if($end_or_start == "end") {
			$sql = "SELECT `periods_id` FROM `periods` WHERE CAST(`end_date` AS DATE) = CAST('$date' AS DATE)";
		}
		$result = $this->executeQuery($sql);
		if(count($result) > 0){
			return $result[0]["periods_id"];
		}
		else{
			return 1;
		}
	}
	public function get_res_tot_varaddon_price($res_cost_addon_id){
    	$sql = "SELECT *
				FROM `reservation_costs2` As res_cost
				WHERE res_cost.`id` = $res_cost_addon_id";
		$result = $this->executeQuery($sql);
		$total_price = 0;
		$total_price_temp = explode(',', $result[0]["price"]);
		$quantity_temp = explode(',', $result[0]["multiply_by"]);
		$days_temp = explode(',', $result[0]["days"]);
		for($y=0; $y<count($days_temp); $y++){
			$total_price += $result[0]["quantity"]*($quantity_temp[$y]*$total_price_temp[$y]); # total price
		}
		return $total_price;
	}
    /* variable Add on END */
	
	/* Room Optimization start */
	
	public function get_rooms_to_rebalance($room_type_id, $room_filter){
		$sql = "SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' $room_filter";
		$result = $this->executeQuery($sql);

		return $result;
	}
	
	public function check_allocateRoom($room_type_id, $checkin_id, $checkout_id, $recom_array, $room_filter, $res_filter){
		$result = 0;
		$get_rooms = '';
		
		$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
		$id_property = $getPropertyId[0]['property_id'];
		
		if($id_property == '19994'){
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' AND apartment_id != '16' AND apartment_id != '24' AND apartment_id != '26' AND apartment_id != '27' $room_filter");
		}else{
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' $room_filter");
		}

		$cnt = count($get_rooms);
		for($x=0; $x<$cnt; $x++){
			$idappartamenti = $get_rooms[$x]['apartment_id'];
			$is_available = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$idappartamenti' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id'))) $res_filter");
			$is_available1 = array();
			
			$cnt2 = count($recom_array);
			for($y=0; $y<$cnt2; $y++){
				$temp_action = $recom_array[$y]["action"];
				$temp_data = $recom_array[$y]["data"];
				if($temp_action == "move"){ # action = move
					$data_r = explode(";",$temp_data);
					$data_r1 = $data_r[1];
					$data_r2 = explode(":",$data_r[0]);
					$res_id = $data_r2[0];
					$rm_id = $data_r2[1];

					if($idappartamenti == $data_r[1]){
						$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
						$temp_lang = $this->executeQuery($sql);
						if(count($temp_lang) > 0){
							$is_available1 = $temp_lang;
							break;
						}
					}
				}
				else{ # action = swap
					$data_r = explode(";",$temp_data);
					$data_r1 = explode(":",$data_r[0]);
					$room_id1 =  $data_r1[1];
					$res_id1 =  $data_r1[0];

					$data_r2 = explode(",",$data_r[1]);
					$room_id2 =  "";
					$res_id2 =  "";
					
					for($asd=0; $asd<count($data_r2); $asd++){
						$temp_ex = explode(":",$data_r2[$asd]);
						$room_id2 =  $temp_ex[1];
						$res_id2 =  $temp_ex[0];
						if($idappartamenti == $room_id1){
							$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id2' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
							$temp_lang = $this->executeQuery($sql);
							if(count($temp_lang) > 0){
								$is_available1 = $temp_lang;
								break;
							}
						}
					}
					if($idappartamenti == $room_id2){
						$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id1' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
						$temp_lang = $this->executeQuery($sql);
						if(count($temp_lang) > 0){
							$is_available1 = $temp_lang;
							break;
						}
					}

				}
			}

			if(count($is_available) == 0 && count($is_available1) == 0){
				$result = $idappartamenti;
				break;
			}
		}
		return $result;
	}
	
	public function check_rooms($room_id, $room_type_id, $start_id, $end_id, $room_filter, $res_filter){

		$recom_array = array();
		$ind = 0;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id, $room_filter);
		$cnt1 = count($rooms_to_rebalance);

		for($x=$start_id; $x<=$end_id; $x++){
			$sql = "SELECT *
					FROM `reservation`
					WHERE `appartments_id` = $room_id and
						  (`date_start_id` <= $x and `date_end_id` >= $x) and
						  `status` = 'active' $res_filter";
			$result = $this->executeQuery($sql);
			if(count($result) > 0){ # kung naay booking ani na date ug ani na room
				# pangitaan ug kabalhinan
				
				if($result[0]["checkin"] == NULL || $result[0]["checkin"] == ''){ # kung wala pa naka.checkin
					$temp_res_id = $result[0]["reservation_id"];
					$temp_room_id = $result[0]["appartments_id"];
					$temp_startdate = $result[0]["date_start_id"];
					$temp_enddate = $result[0]["date_end_id"];
					$to_room_id = $this->check_allocateRoom($room_type_id, $temp_startdate, $temp_enddate, $recom_array, $room_filter, $res_filter);

					if($to_room_id != 0){
						$recom_array[$ind]["action"] = "move";
						$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$to_room_id;
						$ind++;
						$x = $temp_enddate;
					}
					else{
						$try_lang_sa = $this->check_to_swap1($room_id, $room_type_id, $temp_startdate, $temp_enddate, $recom_array, $start_id, $end_id, $room_filter, $res_filter);
						//orig space
						if(count($try_lang_sa) > 0){
							$res_filter_res1 = str_replace("reservation_id","res1.`reservation_id`",$res_filter);
							$res_filter_res = str_replace("reservation_id","res.`reservation_id`",$res_filter);
							$sql = "SELECT res1.`reservation_id`, res1.`appartments_id`
									FROM `reservation` As res1
									WHERE res1.`appartments_id` = '".$try_lang_sa[0]["room"]."' and
										  (res1.`date_start_id` > $end_id or res1.`date_end_id` < $start_id) and
										  res1.`status` = 'active' and
										  res1.`reservation_id` IN (SELECT res.`reservation_id`
																	FROM `reservation` As res
																	WHERE res.`appartments_id` = '".$try_lang_sa[0]["room"]."' and
																		  ((res.`date_start_id` >= $temp_startdate and res.`date_start_id` <= $temp_enddate) or (res.`date_end_id` >= $temp_startdate and res.`date_end_id` <= $temp_enddate) or (res.`date_start_id` <= $temp_startdate and res.`date_end_id` >= $temp_startdate) or (res.`date_start_id` <= $temp_enddate and res.`date_end_id` >= $temp_enddate)) and
																		  res.`status` = 'active' $res_filter_res) $res_filter_res1";
							$result1 = $this->executeQuery($sql);
							if(count($result1) == 0){
								$recom_array[$ind]["action"] = $try_lang_sa[0]["action"];
								$recom_array[$ind]["data"] = $try_lang_sa[0]["data"];
								$ind++;
								$recom_array[$ind]["action"] = "move";
								$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$try_lang_sa[0]["room"];
								$ind++;
							}
						}
						//orig sapce
						$x = $temp_enddate;
					}
				}
				else{ # kung naka.checkin na
					$recom_array = array();
					break;
				}
			}
			else{

			}
		}
		return $recom_array;
	}
	
	public function check_to_swap1($temp_room_id, $room_type_id, $temp_date_start_id, $temp_date_end_id, $recom_array, $start_id, $end_id, $room_filter, $res_filter){
		$result = array();
		$ind1 = 0;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id, $room_filter);
		$cnt1 = count($rooms_to_rebalance);

		for($x=0; $x<$cnt1; $x++){
			$room_id = $rooms_to_rebalance[$x]["apartment_id"];
			$sql = "SELECT *
 				FROM `reservation`
				WHERE `reservation`.`appartments_id` = $room_id and
					  `reservation`.`status` = 'active' and
					  ((`reservation`.`date_start_id` >= $temp_date_start_id and `reservation`.`date_start_id` <= $temp_date_end_id) or (`reservation`.`date_end_id` >= $temp_date_start_id and `reservation`.`date_end_id` <= $temp_date_end_id) or (`reservation`.`date_start_id` <= $temp_date_start_id and `reservation`.`date_end_id` >= $temp_date_start_id) or (`reservation`.`date_start_id` <= $temp_date_end_id and `reservation`.`date_end_id` >= $temp_date_end_id)) and
					  `reservation`.`appartments_id` != $temp_room_id $res_filter";
			$result1 = $this->executeQuery($sql);
			$cnt2 = count($result1);

			for($y=0; $y<$cnt2; $y++){
				$y_res_id = $result1[$y]["reservation_id"]; # nangita ni ug kabalhinan para makasulod ang isa ka booking sa nafucos na room
				$y_room_id = $result1[$y]["appartments_id"];
				$y_startdate = $result1[$y]["date_start_id"];
				$y_enddate = $result1[$y]["date_end_id"];
				$check_daw = 0;

				if($result1[$y]["checkin"] == NULL || $result1[$y]["checkin"] == ''){
					$temp_recom_action = "";#array();
					$temp_recom_data = "";

					for($z=0; $z<$cnt1; $z++){
						$z_room_id = $rooms_to_rebalance[$z]["apartment_id"];
						if($z_room_id != $y_room_id){ # $z_room_id != $temp_room_id && 

							$sql = "SELECT *
				 					FROM `reservation`
									WHERE `reservation`.`appartments_id` = $z_room_id and
									  	  `reservation`.`status` = 'active' and
									  	  ((`reservation`.`date_start_id` >= $y_startdate and `reservation`.`date_start_id` <= $y_enddate) or (`reservation`.`date_end_id` >= $y_startdate and `reservation`.`date_end_id` <= $y_enddate) or (`reservation`.`date_start_id` <= $y_startdate and `reservation`.`date_end_id` >= $y_startdate) or (`reservation`.`date_start_id` <= $y_enddate and `reservation`.`date_end_id` >= $y_enddate)) and
					  					  `reservation`.`appartments_id` != $y_room_id $res_filter"; #res.`appartments_id` != $temp_room_id and 
							$result2 = $this->executeQuery($sql);
							$cnt3 = count($result2);

							# ------ sdfsdfsdfsdf ------------------
							for($v=0; $v<$cnt3; $v++){
								$w_res_id = $result2[$v]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
								$w_room_id = $result2[$v]["appartments_id"];
								$w_startdate = $result2[$v]["date_start_id"];
								$w_enddate = $result2[$v]["date_end_id"];

								# ------------------------------------------------------------
								$cnt5 = count($recom_array);
								for($h=0; $h<$cnt5; $h++){
									$temp_action = $recom_array[$h]["action"];
									$temp_data = $recom_array[$h]["data"];
									if($temp_action == "move"){ # action = move
										$data_r = explode(";",$temp_data);
										$data_r1 = $data_r[1];
										$data_r2 = explode(":",$data_r[0]);
										$res_id = $data_r2[0];
										$rm_id = $data_r2[1];
										
										if($w_res_id == $res_id){
											if($w_room_id == $rm_id){
												# delete sa array
												$slice = array_slice($result2, $v, null, true);
											}
											else if($w_room_id == $data_r1){
												# isulod sa array
												$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id";
												$temp_lang = $this->executeQuery($sql);
												$temp_lang[0]["appartments_id"] = $data_r1;
												$result2[] = $temp_lang[0];
											}
										}
									}
									else{ # action = swap
										$data_r = explode(";",$temp_data);
										$data_r1 = explode(":",$data_r[0]);
										$room_id1 =  $data_r1[1];
										$res_id1 =  $data_r1[0];

										$data_r2 = explode(",",$data_r[1]);
										$room_id2 =  "";
										$res_id2 =  "";
										
										for($asd=0; $asd<count($data_r2); $asd++){
											$temp_ex = explode(":",$data_r2[$asd]);
											$room_id2 =  $temp_ex[1];
											$res_id2 =  $temp_ex[0];
											if($w_res_id == $res_id2){
												if($w_room_id == $room_id2){
													# delete sa array
													$slice = array_slice($result2, $v, null, true);
													//echo "hahaha";
												}
												else if($w_room_id == $room_id1){
													# isulod sa array
													$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id2";
													$temp_lang = $this->executeQuery($sql);
													$temp_lang[0]["appartments_id"] = $room_id1;
													$result2[] = $temp_lang[0];
													
												}
											}
										}
										if($w_res_id == $res_id2){
											if($w_room_id == $room_id1){
												# delete sa array
												$slice = array_slice($result2, $v, null, true);
											}
											else if($w_room_id == $room_id2){
												# isulod sa array
												$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id1";
												$temp_lang = $this->executeQuery($sql);
												$temp_lang[0]["appartments_id"] = $room_id2;
												$result2[] = $temp_lang[0];
											}
										}

									}
								}
								# ------------------------------------------------------------

							}
							# ------ sdfsdfsdfsdf ------------------

							$cnt3 = count($result2);

							$check_daw = 0;
							for($w=0; $w<$cnt3; $w++){

								$w_res_id = $result2[$w]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
								$w_room_id = $result2[$w]["appartments_id"];
								$w_startdate = $result2[$w]["date_start_id"];
								$w_enddate = $result2[$w]["date_end_id"];

								$sql = "SELECT *
					 					FROM `reservation`
										WHERE `reservation`.`appartments_id` = $y_room_id and
											  `reservation`.`reservation_id` != $y_res_id and
										  	  `reservation`.`status` = 'active' and
										  	  ((`reservation`.`date_start_id` >= $w_startdate and `reservation`.`date_start_id` <= $w_enddate) or (`reservation`.`date_end_id` >= $w_startdate and `reservation`.`date_end_id` <= $w_enddate) or (`reservation`.`date_start_id` <= $w_startdate and `reservation`.`date_end_id` >= $w_startdate) or (`reservation`.`date_start_id` <= $w_enddate and `reservation`.`date_end_id` >= $w_enddate)) $res_filter"; #res.`appartments_id` != $temp_room_id and 
								$result5 = $this->executeQuery($sql);
								$cnt5 = count($result5);

								if(($temp_date_start_id > $w_enddate || $temp_date_end_id < $w_startdate) && ($result2[$w]["checkin"] == NULL || $result2[$w]["checkin"] == '') && $cnt5 == 0){

								}
								else{ # impossible
									$check_daw = 1;
									break;
								}
								//echo " ".$w_res_id." ";
							}
							if($check_daw == 0){ # ok ok ni ha
								$temp_recom_action = "swap";
								$temp_recom_data = $y_res_id.":".$y_room_id.";";
								for($w=0; $w<$cnt3; $w++){
									$w_res_id = $result2[$w]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
									if($w==0){
										$temp_recom_data .= $w_res_id.":".$w_room_id;
									}
									else{
										$temp_recom_data .= ",".$w_res_id.":".$w_room_id;
									}
									
								}
								
								break;
							}
						}
						
					}
					
					if($check_daw == 0){ 
						$result[$ind1]["action"] = $temp_recom_action;
						$result[$ind1]["data"] = $temp_recom_data;
						$result[$ind1]["room"] = $room_id;

						$ind1++;
						break;
					}
					
				}
				else{ # impossible
					break;
				}
			}

		}
		return $result;
	}
	// orig space
	public function check_rooms2($room_id, $room_type_id, $start_id, $end_id, $room_filter, $res_filter){

		$recom_array = array();
		$ind = 0;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id, $room_filter);
		$cnt1 = count($rooms_to_rebalance);

		$counter_sccsfl_dt = 0;
		for($x=$start_id; $x<=$end_id; $x++){
			$sql = "SELECT *
					FROM `reservation`
					WHERE `appartments_id` = $room_id and
						  (`date_start_id` <= $x and `date_end_id` >= $x) and
						  `status` = 'active' $res_filter";
			$result = $this->executeQuery($sql);
			if(count($result) > 0){ # kung naay booking ani na date ug ani na room
				# pangitaan ug kabalhinan
				
				if($result[0]["checkin"] == NULL || $result[0]["checkin"] == ''){ # kung wala pa naka.checkin
					$temp_res_id = $result[0]["reservation_id"];
					$temp_room_id = $result[0]["appartments_id"];
					$temp_startdate = $result[0]["date_start_id"];
					$temp_enddate = $result[0]["date_end_id"];
					$to_room_id = $this->check_allocateRoom($room_type_id, $temp_startdate, $temp_enddate, $recom_array, $room_filter, $res_filter);

					if($to_room_id != 0){
						$recom_array[$ind]["action"] = "move";
						$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$to_room_id;
						$ind++;
						$x = $temp_enddate;
					}
					else{
						$try_lang_sa = $this->check_to_swap1($room_id, $room_type_id, $temp_startdate, $temp_enddate, $recom_array, $start_id, $end_id, $room_filter, $res_filter);
						//orig space
						if(count($try_lang_sa) > 0){
							$res_filter_res1 = str_replace("reservation_id","res1.`reservation_id`",$res_filter);
							$res_filter_res = str_replace("reservation_id","res.`reservation_id`",$res_filter);
							$sql = "SELECT res1.`reservation_id`, res1.`appartments_id`
									FROM `reservation` As res1
									WHERE res1.`appartments_id` = '".$try_lang_sa[0]["room"]."' and
										  (res1.`date_start_id` > $end_id or res1.`date_end_id` < $start_id) and
										  res1.`status` = 'active' and
										  res1.`reservation_id` IN (SELECT res.`reservation_id`
																	FROM `reservation` As res
																	WHERE res.`appartments_id` = '".$try_lang_sa[0]["room"]."' and
																		  ((res.`date_start_id` >= $temp_startdate and res.`date_start_id` <= $temp_enddate) or (res.`date_end_id` >= $temp_startdate and res.`date_end_id` <= $temp_enddate) or (res.`date_start_id` <= $temp_startdate and res.`date_end_id` >= $temp_startdate) or (res.`date_start_id` <= $temp_enddate and res.`date_end_id` >= $temp_enddate)) and
																		  res.`status` = 'active' $res_filter_res) $res_filter_res1";
							$result1 = $this->executeQuery($sql);
							if(count($result1) == 0){
								$recom_array[$ind]["action"] = $try_lang_sa[0]["action"];
								$recom_array[$ind]["data"] = $try_lang_sa[0]["data"];
								$ind++;
								$recom_array[$ind]["action"] = "move";
								$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$try_lang_sa[0]["room"];
								$ind++;
							}
						}
						else{
							$res_data2 = $this->check_to_swap2($temp_res_id, $room_id, $room_type_id, $temp_startdate, $temp_enddate, $recom_array, $start_id, $end_id, $room_filter, $res_filter);
							$temp_swap = "";
							for($xy=0; $xy<count($res_data2); $xy++){
								if($xy > 0){
									$temp_swap .= ",";
								}
								$temp_swap .= $res_data2[$xy]["reservation_id"].":".$res_data2[$xy]["appartments_id"];
									
							}
							$recom_array[$ind]["action"] = "swap";
							$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$temp_swap;
							$ind++;
						}
						//orig sapce
						$x = $temp_enddate;
					}
				}
				else{ # kung naka.checkin na
					$recom_array = array();
					break;
				}
			}
			else{
				$counter_sccsfl_dt++;
			}
		}
		$dt_count_status = "invalid";
		$dt_tot = ($end_id - $start_id) + 1;
		if($dt_tot == $counter_sccsfl_dt){
			$dt_count_status = "valid";
		}
		$result_daw = array("dt_count" => $dt_count_status, "recom_array" => $recom_array);
		return $result_daw;
	}

	public function check_to_swap2($temp_res_id, $temp_room_id, $room_type_id, $temp_date_start_id, $temp_date_end_id, $recom_array, $start_id, $end_id, $room_filter, $res_filter){
		$result1 = array();
		$is_possible = false;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id, $room_filter);
		$cnt3 = count($rooms_to_rebalance);
		for($x3=0; $x3<$cnt3; $x3++){
			$room_id = $rooms_to_rebalance[$x3]["apartment_id"];

			$sql = "SELECT *
 				FROM `reservation`
				WHERE `reservation`.`appartments_id` = $room_id and
					  `reservation`.`status` = 'active' and
					  ((`reservation`.`date_start_id` >= $temp_date_start_id and `reservation`.`date_start_id` <= $temp_date_end_id) or (`reservation`.`date_end_id` >= $temp_date_start_id and `reservation`.`date_end_id` <= $temp_date_end_id) or (`reservation`.`date_start_id` <= $temp_date_start_id and `reservation`.`date_end_id` >= $temp_date_start_id) or (`reservation`.`date_start_id` <= $temp_date_end_id and `reservation`.`date_end_id` >= $temp_date_end_id)) and
					  `reservation`.`appartments_id` != $temp_room_id $res_filter";
			$result1 = $this->executeQuery($sql);
			$cnt1 = count($result1);

			for($x1=0; $x1<$cnt1; $x1++){
				$w_res_id = $result2[$x1]["reservation_id"];
				$w_room_id = $result2[$x1]["appartments_id"];
				$w_startdate = $result2[$x1]["date_start_id"];
				$w_enddate = $result2[$x1]["date_end_id"];
				$cnt5 = count($recom_array);
				for($h=0; $h<$cnt5; $h++){
					$temp_action = $recom_array[$h]["action"];
					$temp_data = $recom_array[$h]["data"];
					if($temp_action == "move"){ # action = move
						$data_r = explode(";",$temp_data);
						$data_r1 = $data_r[1];
						$data_r2 = explode(":",$data_r[0]);
						$res_id = $data_r2[0];
						$rm_id = $data_r2[1];
											
						if($w_res_id == $res_id){
							if($w_room_id == $rm_id){
								# delete sa array
								$slice = array_slice($result1, $v, null, true);
							}
							else if($w_room_id == $data_r1){
								# isulod sa array
								$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id";
								$temp_lang = $this->executeQuery($sql);
								$temp_lang[0]["appartments_id"] = $data_r1;
								$result1[] = $temp_lang[0];
							}
						}
					}
					else{ # action = swap
						$data_r = explode(";",$temp_data);
						$data_r1 = explode(":",$data_r[0]);
						$room_id1 =  $data_r1[1];
						$res_id1 =  $data_r1[0];

						$data_r2 = explode(",",$data_r[1]);
						$room_id2 =  "";
						$res_id2 =  "";
											
						for($asd=0; $asd<count($data_r2); $asd++){
							$temp_ex = explode(":",$data_r2[$asd]);
							$room_id2 =  $temp_ex[1];
							$res_id2 =  $temp_ex[0];
							if($w_res_id == $res_id2){
								if($w_room_id == $room_id2){
									# delete sa array
									$slice = array_slice($result1, $v, null, true);
									//echo "hahaha";
								}
								else if($w_room_id == $room_id1){
									# isulod sa array
									$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id2";
									$temp_lang = $this->executeQuery($sql);
									$temp_lang[0]["appartments_id"] = $room_id1;
									$result1[] = $temp_lang[0];
								}
							}
						}
						if($w_res_id == $res_id2){
							if($w_room_id == $room_id1){
								# delete sa array
								$slice = array_slice($result1, $v, null, true);
							}
							else if($w_room_id == $room_id2){
								# isulod sa array
								$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id1";
								$temp_lang = $this->executeQuery($sql);
								$temp_lang[0]["appartments_id"] = $room_id2;
								$result1[] = $temp_lang[0];
							}
						}

					}
				}

			}

			$cnt2 = count($result1);
			for($w=0; $w<$cnt2; $w++){
				$w_res_id = $result1[$w]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
				$w_room_id = $result1[$w]["appartments_id"];
				$w_startdate = $result1[$w]["date_start_id"];
				$w_enddate = $result1[$w]["date_end_id"];

				if( $w_startdate > $end_id ){
					$sql = "SELECT count(`reservation`.`reservation_id`) As res_num
						FROM `reservation`
						WHERE `reservation`.`appartments_id` = $temp_room_id and
								`reservation`.`reservation_id` != $temp_res_id and
								`reservation`.`status` = 'active' and
								((`reservation`.`date_start_id` >= $w_startdate and `reservation`.`date_start_id` <= $w_enddate) or (`reservation`.`date_end_id` >= $w_startdate and `reservation`.`date_end_id` <= $w_enddate) or (`reservation`.`date_start_id` <= $w_startdate and `reservation`.`date_end_id` >= $w_startdate) or (`reservation`.`date_start_id` <= $w_enddate and `reservation`.`date_end_id` >= $w_enddate)) $res_filter"; #res.`appartments_id` != $temp_room_id and 
					$result2 = $this->executeQuery($sql);
					if($result2[0]["res_num"] == 0){
						# ok
						$is_possible = true;
					}
					else{
						$is_possible = false;
						break;
					}
				}
				else{
					$is_possible = false;
					break;
				}
			}

			if($is_possible == true){
				break;
			}

		}

		if($is_possible == true){
			return $result1;
		}
		else{
			return array();
		}

	}
	//----------
	public function getOptimizeReservation($reservation_id){
		
		$reservation_to_rebalance = $this->executeQuery("SELECT reservation.*, clients.surname, clients.name, apartments.apartment_name, reservation_conn.reference_num FROM reservation INNER JOIN clients on clients.clients_id = reservation.clients_id INNER JOIN apartments on apartments.apartment_id = reservation.appartments_id INNER JOIN reservation_conn on reservation_conn.reservation_conn_id = reservation.reservation_conn_id WHERE reservation.reservation_id = '$reservation_id'");
		
		return $reservation_to_rebalance;
	}
	
	public function getRoomInformation($room_id){
		
		$room_information = $this->executeQuery("SELECT * FROM apartments WHERE apartment_id = '$room_id'");
		
		return $room_information;
	}
	
	/* Room Optimization end */

    /* log to file */
	public function logToFile($message){
		$default_time_zone = date_default_timezone_get();
		date_default_timezone_set('asia/manila');
	
		$time_diff = $this->get_global_variables('time_difference');
		$log_msg = "[" . date('D, d M Y H:i:s') . "] " . $message;
	    $log_filename = __DIR__ . "/../../../logs/";

	    $log_file_data = $log_filename.'/log_' . date('d-M-Y') . '.log';
	    
	    $this->addLogToFile($log_file_data, $log_msg, true);

	    date_default_timezone_set($default_time_zone);
	}
	public function addLogToFile($log_file_data, $log_msg, $prepend){
		if($prepend && file_exists($log_file_data) ) { // prepend
		    $src = fopen($log_file_data, 'r+');
			$dest = fopen('php://temp', 'w');

			fwrite($dest, $log_msg . PHP_EOL);

			stream_copy_to_stream($src, $dest);
			rewind($dest);
			rewind($src);
			stream_copy_to_stream($dest, $src);

			fclose($src);
			fclose($dest);
		} else { // append, if false or file doesn't exist
			file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
		}
	}

	public function get_global_variables($key = 'time_difference') {
		$myDatabase = $this->db; //variable to access your database
		$sql = "SELECT a.value FROM global_variables a WHERE a.key=:key";
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute(array(
			':key' => $key
		));
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$value = '';
		if(count($result) > 0){
			$value = $result[0]['value'];
		}
		return $value;
	}
	public function getRefNumberAndGuest($res_id, $res_conn_id) {
		$myDatabase = $this->db;
		$sql = "SELECT CONCAT(IFNULL(b.surname, ''), ', ', IFNULL(b.name, '')) AS client_name, c.refnumber FROM reservation a 
				LEFT JOIN clients b ON a.clients_id = b.clients_id
				LEFT JOIN bookings c ON a.reservation_id = c.reservation_id
				WHERE a.reservation_id = :res_id AND a.reservation_conn_id = :res_conn_id";
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute(
			array(
				':res_id' => $res_id,
				':res_conn_id' => $res_conn_id
			)
		);
		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $results;
	}
	public function sendNotificationtoHTL($booking_status, $booking_data, $booking_src, $reservation_id) {	
		if(isset($booking_data['Bookings']['Booking']['Customers']['Customer']['CustomerFName'])) {
			$client_name = $booking_data['Bookings']['Booking']['Customers']['Customer']['CustomerFName'];
		} else {
			$client_name = $booking_data['Bookings']['Booking']['Customers']['Customer'][0]['CustomerFName'];
		}
		
		$msg = '';
		$booking_src = $booking_src == '294e1dc2b907ed0496e51572c3ef081e' ? 'Walk-in' : $booking_src;
		$sDate = isset($booking_data['Bookings']['Booking']['StartDate']) ? $booking_data['Bookings']['Booking']['StartDate'] : '';
		$eDate = isset($booking_data['Bookings']['Booking']['EndDate']) ? $booking_data['Bookings']['Booking']['EndDate'] : '' ;
		/* if booking_src is not equal to Walk-in*/
		$sql_request = $this->executeQuery("SELECT booking_source_name FROM booking_source WHERE channel_name = '$booking_src'");
		$booking_source_name = $sql_request[0]['booking_source_name'];
		if($booking_src != 'Walk-in') {
			$res_data = $this->getResIdResConnId($reservation_id);
			if(count($res_data) > 0) { 
				$url = '#/modify-booking/' . $res_data[0]['reservation_conn_id'] . '/' . $res_data[0]['reservation_id']; 
			}
			else { $url = '#!'; }
			if($booking_status == 'new') {
				$msg = 'New Reservation Source: '; 
				$msg .= $booking_source_name . ' ' . $client_name . ' ';
				$msg .= 'REF#' . $reservation_id;
				
			} else if($booking_status == 'cancellation') {
				$msg = 'New Cancellation Source: '; 
				$msg .= $booking_source_name . ' ' . $client_name . ' ';
				$msg .= 'REF#' . $reservation_id;
				
			} else if($booking_status == 'modification') {
				$msg = 'New Modification Source: ';
				$msg .= $booking_source_name . ' ' . $client_name . ' ';
				$msg .= 'REF#' . $reservation_id;
			} else {
				$msg = 'Unknown booking status: ' . $booking_status . ' ';
				$msg .= $booking_source_name . ' ' . $client_name . ' ';
				$msg .= 'REF#' . $reservation_id;
			}
			/* intended for notification */
			$socket_json_message = array(
				'action' => 'reservation',
				'message' => $msg,
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => '101', // HTL-944 : changed id from 1 to 101 for OTA
				'reservation_link' => $url,
				'notification_type' => '1'
			);
			$this->insertNotification($socket_json_message);
			$this->setMsg(json_encode($socket_json_message, JSON_UNESCAPED_SLASHES));
			/* send data to web socket */
			$this->sendMessage();
		}
	}
	public function log_ota_reservation($booking_status, $booking_data, $booking_src, $reservation_id){

		if(isset($booking_data['Bookings']['Booking']['Customers']['Customer']['CustomerFName'])) {
			$client_name = $booking_data['Bookings']['Booking']['Customers']['Customer']['CustomerFName'];
		} else {
			$client_name = $booking_data['Bookings']['Booking']['Customers']['Customer'][0]['CustomerFName'];
		}
		
		if($booking_data) {
			$msg = '';
			$booking_src = $booking_src == '294e1dc2b907ed0496e51572c3ef081e' ? 'Walk-in' : $booking_src;
			$sDate = isset($booking_data['Bookings']['Booking']['StartDate']) ? $booking_data['Bookings']['Booking']['StartDate'] : '';
			$eDate = isset($booking_data['Bookings']['Booking']['EndDate']) ? $booking_data['Bookings']['Booking']['EndDate'] : '' ;
			
			if($booking_status == 'new') {
				$msg = 'NEW RESERVATION '; 
				$msg .= $booking_src . ' ' . $client_name . ' ';
				$msg .= date( 'd/m/y', strtotime( $sDate ) ) . ' - '; 
				$msg .= date( 'd/m/y', strtotime( $eDate ) ) . ' ';
				$msg .= 'REF#' . $reservation_id;
				
			} else if($booking_status == 'cancellation'){
				$msg = 'NEW CANCELLATION '; 
				$msg .= $booking_src . ' ' . $client_name . ' ';
				$msg .= date( 'd/m/y', strtotime( $sDate ) ) . ' - '; 
				$msg .= date( 'd/m/y', strtotime( $eDate ) ) . ' ';
				$msg .= 'REF#' . $reservation_id;
				
			} else if($booking_status == 'modification'){
				$msg = 'NEW MODIFICATION';
				$msg .= $booking_src . ' ' . $client_name . ' ';
				$msg .= 'REF#' . $reservation_id;
			} else {
				$msg = 'Unknown booking status: ' . $booking_status . ' ';
				$msg .= $booking_src . ' ';
				$msg .= 'REF#' . $reservation_id;
			}
		} else {
			$msg = 'Error: Booking Data is Corrupted for Reservation: ' . $reservation_id . '; Source: ' . $booking_src . ' If Source is Walk-in Please neglect otherwise it is from OTA';
		}

		$this->logToFile($msg);

	}
	/* end of log to file */	
// orig space
	/* Room Optimization : START */
    public function val_rules_engine($rule_id, $room_type_id, $start_date_id, $end_date_id){
    	$sql = "SELECT *
    			FROM `rules_engine`
    			WHERE `id` = '$rule_id'";
    	$res_rules_engine = $this->executeQuery($sql);
    	if(count($res_rules_engine) > 0){
    		if($res_rules_engine[0]["status"] == "active"){
    			# ok siya then e.check ang calendar if pwede maka.optomize
    			$canOptimize = $this->check_room_optim($room_type_id, $start_date_id, $end_date_id);
  				if($canOptimize == true){
  					# run notification function . .yohooo..
  					$room_type_name = $this->executeQuery("SELECT `name` FROM `room_types` WHERE `room_type_id` = '$room_type_id'");
  					$start_date = $this->get_period_date($start_date_id, "start_date");
  					$end_date = $this->get_period_date($end_date_id, "end_date");

  					$to_socket_check_in = new DateTime($start_date);
					$socket_check_in = $to_socket_check_in->format('d/m/Y');
					$to_socket_check_out = new DateTime($end_date);
					$socket_check_out = $to_socket_check_out->format('d/m/Y');

  					$socket_json_message = array(
						'action' => 'reservation',
						'message' => 'Room Optimization! Room type: '.$room_type_name[0]["name"].' From '.$socket_check_in.' to '.$socket_check_out.'.',
						'host' => $_SERVER['SERVER_NAME'],
						'inserted_by' => '1',
						'reservation_link' => '#/optimize-rooms/[room_optim_id]/'.$room_type_id.'/'.$start_date_id.'/'.$end_date_id,
						'notification_type' => '1'
					);
					$this->insertNotification_roomOptim($socket_json_message);
					$this->setMsg(json_encode($socket_json_message, JSON_UNESCAPED_SLASHES));
					/* send data to web socket */
					$this->sendMessage();
  				}
    		}
    	}
	}

	public function insertNotification_roomOptim($message) {
		$sql_notif = "INSERT INTO 
			notifications
			(
				notification_id,
				action,
				message,
				host,
				inserted_by,
				reservation_link,
				inserted_datetime,
				notification_type
			) 
			VALUES
			(
				:notification_id,
				:action,
				:message,
				:host,
				:inserted_by,
				:reservation_link,
				NOW() + INTERVAL :inserted_datetime HOUR,
				:notification_type
			)";
		$sql_rOptim = "INSERT INTO 
			room_optimization
			(
				notification_id,
				optimize_date,
				isOptimize,
				optimized_by,
				status
			) 
			VALUES
			(
				:notification_id,
				:optimize_date,
				:isOptimize,
				:optimized_by,
				:status
			)";
		try {
			$data_notif = array(
				':notification_id' => NULL,
				':action' => $message['action'],
				':message' => $message['message'],
				':host' => $message['host'],
				':inserted_by' => $message['inserted_by'],
				':reservation_link' => $message['reservation_link'],
				':inserted_datetime' => C_DIFF_ORE,
				':notification_type' => $message['notification_type']
			);
			$notif_id = $this->execute_insert_getId($sql_notif,$data_notif);
			$data_rOptim = array(
				':notification_id' => $notif_id,
				':optimize_date' => NULL,
				':isOptimize' => 'No',
				':optimized_by' => '0',
				':status' => 'active'
			);
			//$this->execute_insert($sql_rOptim, $data_rOptim);
			// -- HTL-368 : added db column 'status' --
			$rmoptim_id = $this->execute_insert_getId($sql_rOptim, $data_rOptim);
			$sql = "UPDATE `notifications` SET `reservation_link` = REPLACE(`reservation_link`, '[room_optim_id]', '$rmoptim_id') WHERE `notification_id` = '$notif_id'";
			$this->execute_update($sql);

			$resp = array( "status"=> "success", "message" => "notification inserted" );
			/* echo json_ecode($resp); */
		} catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			/* echo json_ecode($resp);
			http_response_code(500); */
		}
	}

	public function check_room_optim($room_type_id, $start_date_id, $end_date_id){
		$check_val = 0;

		$sql = "SELECT count(`apartment_id`) As num_rooms
				FROM `apartments`
				WHERE `roomtype_id` = $room_type_id";
		$res_rooms = $this->executeQuery($sql);
    	for($x=$start_date_id; $x<=$end_date_id; $x++){
    		$sql = "SELECT count(`reservation_id`) As num_reservations
					FROM `reservation`, `apartments`
					WHERE `reservation`.`appartments_id` = `apartments`.`apartment_id` and
						  `apartments`.`roomtype_id` = '$room_type_id' and
					      (`reservation`.`date_start_id` <= $x and `reservation`.`date_end_id` >= $x) and
						  `reservation`.`status` = 'active'";
			$res_reservations = $this->executeQuery($sql);
			if($res_rooms[0]["num_rooms"] <= $res_reservations[0]["num_reservations"]){
				$check_val = 1;
			}
    	}	
    	$sql = "SELECT `apartment_id`
				FROM `apartments`
				WHERE `roomtype_id` = '$room_type_id'";
		$res_rooms1 = $this->executeQuery($sql);
    	for($x=0; $x<count($res_rooms1); $x++){
    		$room_id = $res_rooms1[$x]["apartment_id"];
    		$sql = "SELECT count(`reservation_id`) As num_reservations
					FROM `reservation`
					WHERE `appartments_id` = '$room_id' and
							(((`date_start_id` <= $start_date_id and `date_end_id` >= $start_date_id) or (`date_start_id` <= $end_date_id and `date_end_id` >= $end_date_id)) or ((`date_start_id` >= $start_date_id and `date_start_id` <= $end_date_id) or (`date_end_id` >= $start_date_id and date_end_id <= $end_date_id))) and
							 `status` = 'active'";
			$res_reservations1 = $this->executeQuery($sql);
			if($res_reservations1[0]["num_reservations"] == 0){
				$check_val = 1;
			}
    	}

    	$check_balik = 1;
    	if($check_val == 0){
    		# ok siya, check ang recommendation niya if naa
    		$recom_room_id = $this->check_room_recom($room_type_id, $start_date_id, $end_date_id);
    		if($recom_room_id != ""){
    			# naay siyay pwede ma.optimize
    			$check_balik = 0;
    		}
    	}

    	if($check_balik == 0){
    		# ok
    		return true;
    	}
    	else{
    		# dili ok
    		return false;
    	}

	}

	public function check_room_recom($room_type_id, $start_id, $end_id){
		$room_daw_niya = "";
		$recommend_daw = array();

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id,"");
		$cnt1 = count($rooms_to_rebalance);

		for($x=0; $x<$cnt1; $x++){ # check the rooms
			$room_id = $rooms_to_rebalance[$x]["apartment_id"];
			//$temp_isAvail = $result[$room_id][$y]; # 1: available, 0: unavailable
			$result = $this->check_rooms($room_id, $room_type_id, $start_id, $end_id, "");
			$check_daw = 0;
			if(count($result) > 0){
				$cnt2 = count($result);
				for($y=0; $y<$cnt2; $y++){
					$temp_action = $result[$y]["action"];
					$temp_data = $result[$y]["data"];
					if($temp_action == "move"){ # action = move
						$data_r = explode(";",$temp_data);

						if($data_r[0] == ""){
							$check_daw = 1;
						}
					}
					else{ # action = swap
						$data_r = explode(";",$temp_data);
						$data_r1 = explode(",",$data_r[1]);
						
						if($data_r[0] == "" || $data_r[1] == ""){
							$check_daw = 1;
						}
					}
				}
			}
			else{
				$check_daw = 1;
			}
			if($check_daw == 0){
				$room_daw_niya = $room_id;
				$recommend_daw = $result;
				break;
			}

		}

		return $room_daw_niya;

	}
	
	public function check_blocking($room_id, $checkin_id, $checkout_id, $idprenota) {
		$room_is_available = true;
		
		$is_available_block = $this->executeQuery("SELECT * FROM blocking WHERE status = 'active' AND appartment_id = '$room_id' AND 
												(((blocking_start_date_id <= '$checkin_id' AND blocking_end_date_id >= '$checkin_id') OR 
												(blocking_start_date_id <= '$checkout_id' AND blocking_end_date_id >= '$checkout_id')) OR 
												((blocking_start_date_id >= '$checkin_id' AND blocking_start_date_id <= '$checkout_id') OR 
												(blocking_end_date_id >= '$checkin_id' AND blocking_end_date_id <= '$checkout_id')))");
		
		if(count($is_available_block) > 0) {
			$room_is_available = false;
		} else {
			$room_is_available = true;
		}
		
		return $room_is_available;
	}
	
	public function check_room_allocation($room_id, $checkin_id, $checkout_id, $idprenota){
		$room_is_available = true;
		
		$is_available_2 = $this->executeQuery("SELECT * FROM reservation WHERE reservation_id != '$idprenota' and status = 'active' and appartments_id = '$room_id' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))");
		/*$is_available_block = $this->executeQuery("SELECT * FROM blocking WHERE status = 'active' AND appartment_id = '$room_id' AND 
													(((blocking_start_date_id <= '$checkin_id' AND blocking_end_date_id >= '$checkin_id') OR 
													(blocking_start_date_id <= '$checkout_id' AND blocking_end_date_id >= '$checkout_id')) OR 
													((blocking_start_date_id >= '$checkin_id' AND blocking_start_date_id <= '$checkout_id') OR 
													(blocking_end_date_id >= '$checkin_id' AND blocking_end_date_id <= '$checkout_id')))");*/
		if(count($is_available_2) > 0){ // || count($is_available_block) > 0
			$room_is_available = false;
		}else{
			$room_is_available = true;
		}
		
		return $room_is_available;
	}
	
	public function check_room_allocation_2($room_id, $checkin_id, $checkout_id){
		
		$room_is_available = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$room_id' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))");
		
		return $room_is_available;
	}
    
	//Reymark
	/* public function get_rooms_to_rebalance($room_type_id, $room_filter){
		$sql = "SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' $room_filter";
		$result = $this->executeQuery($sql);

		return $result;
	}
	
	public function check_allocateRoom($room_type_id, $checkin_id, $checkout_id, $recom_array, $room_filter){
		$result = 0;
		$get_rooms = '';
		
		$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
		$id_property = $getPropertyId[0]['property_id'];
		
		if($id_property == '19994'){
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' AND apartment_id != '16' AND apartment_id != '24' AND apartment_id != '26' AND apartment_id != '27'");
		}else{
			$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' $room_filter");
		}

		$cnt = count($get_rooms);
		for($x=0; $x<$cnt; $x++){
			$idappartamenti = $get_rooms[$x]['apartment_id'];
			$is_available = $this->executeQuery("SELECT * FROM reservation WHERE status = 'active' and appartments_id = '$idappartamenti' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))");
			$is_available1 = array();
			
			$cnt2 = count($recom_array);
			for($y=0; $y<$cnt2; $y++){
				$temp_action = $recom_array[$y]["action"];
				$temp_data = $recom_array[$y]["data"];
				if($temp_action == "move"){ # action = move
					$data_r = explode(";",$temp_data);
					$data_r1 = $data_r[1];
					$data_r2 = explode(":",$data_r[0]);
					$res_id = $data_r2[0];
					$rm_id = $data_r2[1];

					if($idappartamenti == $data_r[1]){
						$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
						$temp_lang = $this->executeQuery($sql);
						if(count($temp_lang) > 0){
							$is_available1 = $temp_lang;
							break;
						}
					}
				}
				else{ # action = swap
					$data_r = explode(";",$temp_data);
					$data_r1 = explode(":",$data_r[0]);
					$room_id1 =  $data_r1[1];
					$res_id1 =  $data_r1[0];

					$data_r2 = explode(",",$data_r[1]);
					$room_id2 =  "";
					$res_id2 =  "";
					
					for($asd=0; $asd<count($data_r2); $asd++){
						$temp_ex = explode(":",$data_r2[$asd]);
						$room_id2 =  $temp_ex[1];
						$res_id2 =  $temp_ex[0];
						if($idappartamenti == $room_id1){
							$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id2' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
							$temp_lang = $this->executeQuery($sql);
							if(count($temp_lang) > 0){
								$is_available1 = $temp_lang;
								break;
							}
						}
					}
					if($idappartamenti == $room_id2){
						$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = '$res_id1' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))";
						$temp_lang = $this->executeQuery($sql);
						if(count($temp_lang) > 0){
							$is_available1 = $temp_lang;
							break;
						}
					}

				}
			}

			if(count($is_available) == 0 && count($is_available1) == 0){
				$result = $idappartamenti;
				break;
			}
		}
		return $result;
	}
	
	public function check_rooms($room_id, $room_type_id, $start_id, $end_id, $room_filter){

		$recom_array = array();
		$ind = 0;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id,$room_filter);
		$cnt1 = count($rooms_to_rebalance);

		for($x=$start_id; $x<=$end_id; $x++){
			$sql = "SELECT *
					FROM `reservation`
					WHERE `appartments_id` = $room_id and
						  (`date_start_id` <= $x and `date_end_id` >= $x) and
						  `status` = 'active'";
			$result = $this->executeQuery($sql);
			if(count($result) > 0){ # kung naay booking ani na date ug ani na room
				# pangitaan ug kabalhinan
				
				if($result[0]["checkin"] == NULL || $result[0]["checkin"] == ''){ # kung wala pa naka.checkin
					$temp_res_id = $result[0]["reservation_id"];
					$temp_room_id = $result[0]["appartments_id"];
					$temp_startdate = $result[0]["date_start_id"];
					$temp_enddate = $result[0]["date_end_id"];
					$to_room_id = $this->check_allocateRoom($room_type_id, $temp_startdate, $temp_enddate, $recom_array, $room_filter);

					if($to_room_id != 0){
						$recom_array[$ind]["action"] = "move";
						$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$to_room_id;
						$ind++;
						$x = $temp_enddate;
					}
					else{
						$try_lang_sa = $this->check_to_swap1($room_id, $room_type_id, $temp_startdate, $temp_enddate, $recom_array, $start_id, $end_id, $room_filter);

						$recom_array[$ind]["action"] = $try_lang_sa[0]["action"];
						$recom_array[$ind]["data"] = $try_lang_sa[0]["data"];
						$ind++;
						$recom_array[$ind]["action"] = "move";
						$recom_array[$ind]["data"] = $temp_res_id.":".$temp_room_id.";".$try_lang_sa[0]["room"];
						$ind++;

						$x = $temp_enddate;
					}
				}
				else{ # kung naka.checkin na
					$recom_array = array();
					break;
				}
			}
			else{

			}
		}
		return $recom_array;
	}
	
	public function check_to_swap1($temp_room_id, $room_type_id, $temp_date_start_id, $temp_date_end_id, $recom_array, $start_id, $end_id, $room_filter){ # $temp_room_id, $room_type_id, $temp_date_start_id, $temp_date_end_id, $recom_array, $orig_start_id, $orig_end_id
		$result = array();
		$ind1 = 0;

		$rooms_to_rebalance = $this->get_rooms_to_rebalance($room_type_id, $room_filter);
		$cnt1 = count($rooms_to_rebalance);

		for($x=0; $x<$cnt1; $x++){
			$room_id = $rooms_to_rebalance[$x]["apartment_id"];
			$sql = "SELECT *
 				FROM `reservation` As res
				WHERE res.`appartments_id` = $room_id and
					  res.`status` = 'active' and
					  ((res.`date_start_id` >= $temp_date_start_id and res.`date_start_id` <= $temp_date_end_id) or (res.`date_end_id` >= $temp_date_start_id and res.`date_end_id` <= $temp_date_end_id) or (res.`date_start_id` <= $temp_date_start_id and res.`date_end_id` >= $temp_date_start_id) or (res.`date_start_id` <= $temp_date_end_id and res.`date_end_id` >= $temp_date_end_id)) and
					  res.`appartments_id` != $temp_room_id";
			$result1 = $this->executeQuery($sql);
			$cnt2 = count($result1);

			for($y=0; $y<$cnt2; $y++){
				$y_res_id = $result1[$y]["reservation_id"]; # nangita ni ug kabalhinan para makasulod ang isa ka booking sa nafucos na room
				$y_room_id = $result1[$y]["appartments_id"];
				$y_startdate = $result1[$y]["date_start_id"];
				$y_enddate = $result1[$y]["date_end_id"];
				$check_daw = 0;

				if($result1[$y]["checkin"] == NULL || $result1[$y]["checkin"] == ''){
					$temp_recom_action = "";#array();
					$temp_recom_data = "";

					for($z=0; $z<$cnt1; $z++){
						$z_room_id = $rooms_to_rebalance[$z]["apartment_id"];
						if($z_room_id != $y_room_id){ # $z_room_id != $temp_room_id && 

							$sql = "SELECT *
				 					FROM `reservation` As res
									WHERE res.`appartments_id` = $z_room_id and
									  	  res.`status` = 'active' and
									  	  ((res.`date_start_id` >= $y_startdate and res.`date_start_id` <= $y_enddate) or (res.`date_end_id` >= $y_startdate and res.`date_end_id` <= $y_enddate) or (res.`date_start_id` <= $y_startdate and res.`date_end_id` >= $y_startdate) or (res.`date_start_id` <= $y_enddate and res.`date_end_id` >= $y_enddate)) and
					  					  res.`appartments_id` != $y_room_id"; #res.`appartments_id` != $temp_room_id and 
							$result2 = $this->executeQuery($sql);
							$cnt3 = count($result2);

							# ------ sdfsdfsdfsdf ------------------
							for($v=0; $v<$cnt3; $v++){
								$w_res_id = $result2[$v]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
								$w_room_id = $result2[$v]["appartments_id"];
								$w_startdate = $result2[$v]["date_start_id"];
								$w_enddate = $result2[$v]["date_end_id"];

								# ------------------------------------------------------------
								$cnt5 = count($recom_array);
								for($h=0; $h<$cnt5; $h++){
									$temp_action = $recom_array[$h]["action"];
									$temp_data = $recom_array[$h]["data"];
									if($temp_action == "move"){ # action = move
										$data_r = explode(";",$temp_data);
										$data_r1 = $data_r[1];
										$data_r2 = explode(":",$data_r[0]);
										$res_id = $data_r2[0];
										$rm_id = $data_r2[1];
										
										if($w_res_id == $res_id){
											if($w_room_id == $rm_id){
												# delete sa array
												$slice = array_slice($result2, $v, null, true);
											}
											else if($w_room_id == $data_r1){
												# isulod sa array
												$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id";
												$temp_lang = $this->executeQuery($sql);
												$temp_lang[0]["appartments_id"] = $data_r1;
												$result2[] = $temp_lang[0];
											}
										}
									}
									else{ # action = swap
										$data_r = explode(";",$temp_data);
										$data_r1 = explode(":",$data_r[0]);
										$room_id1 =  $data_r1[1];
										$res_id1 =  $data_r1[0];

										$data_r2 = explode(",",$data_r[1]);
										$room_id2 =  "";
										$res_id2 =  "";
										
										for($asd=0; $asd<count($data_r2); $asd++){
											$temp_ex = explode(":",$data_r2[$asd]);
											$room_id2 =  $temp_ex[1];
											$res_id2 =  $temp_ex[0];
											if($w_res_id == $res_id2){
												if($w_room_id == $room_id2){
													# delete sa array
													$slice = array_slice($result2, $v, null, true);
													//echo "hahaha";
												}
												else if($w_room_id == $room_id1){
													# isulod sa array
													$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id2";
													$temp_lang = $this->executeQuery($sql);
													$temp_lang[0]["appartments_id"] = $room_id1;
													$result2[] = $temp_lang[0];
													
												}
											}
										}
										if($w_res_id == $res_id2){
											if($w_room_id == $room_id1){
												# delete sa array
												$slice = array_slice($result2, $v, null, true);
											}
											else if($w_room_id == $room_id2){
												# isulod sa array
												$sql = "SELECT * FROM `reservation` WHERE `status` = 'active' and `reservation_id` = $res_id1";
												$temp_lang = $this->executeQuery($sql);
												$temp_lang[0]["appartments_id"] = $room_id2;
												$result2[] = $temp_lang[0];
											}
										}

									}
								}
								# ------------------------------------------------------------

							}
							# ------ sdfsdfsdfsdf ------------------

							$cnt3 = count($result2);

							$check_daw = 0;
							for($w=0; $w<$cnt3; $w++){

								$w_res_id = $result2[$w]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
								$w_room_id = $result2[$w]["appartments_id"];
								$w_startdate = $result2[$w]["date_start_id"];
								$w_enddate = $result2[$w]["date_end_id"];

								$sql = "SELECT *
					 					FROM `reservation` As res
										WHERE res.`appartments_id` = $y_room_id and
											  res.`reservation_id` != $y_res_id and
										  	  res.`status` = 'active' and
										  	  ((res.`date_start_id` >= $w_startdate and res.`date_start_id` <= $w_enddate) or (res.`date_end_id` >= $w_startdate and res.`date_end_id` <= $w_enddate) or (res.`date_start_id` <= $w_startdate and res.`date_end_id` >= $w_startdate) or (res.`date_start_id` <= $w_enddate and res.`date_end_id` >= $w_enddate))"; #res.`appartments_id` != $temp_room_id and 
								$result5 = $this->executeQuery($sql);
								$cnt5 = count($result5);

								if(($temp_date_start_id > $w_enddate || $temp_date_end_id < $w_startdate) && ($result2[$w]["checkin"] == NULL || $result2[$w]["checkin"] == '') && $cnt5 == 0){

								}
								else{ # impossible
									$check_daw = 1;
									break;
								}
								//echo " ".$w_res_id." ";
							}
							if($check_daw == 0){ # ok ok ni ha
								$temp_recom_action = "swap";
								$temp_recom_data = $y_res_id.":".$y_room_id.";";
								for($w=0; $w<$cnt3; $w++){
									$w_res_id = $result2[$w]["reservation_id"]; # mao ni ang possibly na kabalhinan ug kabaylo ato
									if($w==0){
										$temp_recom_data .= $w_res_id.":".$w_room_id;
									}
									else{
										$temp_recom_data .= ",".$w_res_id.":".$w_room_id;
									}
									
								}
								
								break;
							}
						}
						
					}
					
					if($check_daw == 0){ 
						$result[$ind1]["action"] = $temp_recom_action;
						$result[$ind1]["data"] = $temp_recom_data;
						$result[$ind1]["room"] = $room_id;

						$ind1++;
						break;
					}
					
				}
				else{ # impossible
					break;
				}
			}

		}
		return $result;
	} */
	/* Room Optimization : END */
// orig space
	/*---  --*/
	public function getCalendar_booking($start_idPeriod, $end_idPeriod, $apps_id){
		$sql = "SELECT res.`reservation_id`, res.`reservation_conn_id`, res.`appartments_id`, res.`date_start_id`, 
				        res.`date_end_id`, `clients`.`clients_id`, `clients`.`name` As client_fname, `clients`.`surname` As client_lname, 
				        `booking_source`.`booking_source_name`, `reservation_conn`.`reference_num`,
				        (((CAST(COALESCE((SELECT SUM(trans_res.`rate_total`) AS price_trans FROM `transfer_room_history` AS trans_res WHERE trans_res.`reservation_id` = res.`reservation_id` and trans_res.`transfer_status` = 'checkin'),0) AS DECIMAL(10, 2)) + CAST(COALESCE(res.`rate_total`,0) AS DECIMAL(10, 2))) - CAST(COALESCE(res.`discount`,0) AS DECIMAL(10, 2))) - CAST(COALESCE(res.`paid`,0) AS DECIMAL(10, 2))) As remaining_balance,
				        (SELECT `start_date` FROM `periods` As s_period WHERE s_period.`periods_id` = res.`date_start_id`) As start_date, (SELECT `end_date` FROM `periods` As s_period WHERE s_period.`periods_id` = res.`date_end_id`) As end_date,
				        IF(res.`checkout` IS NULL, IF(res.`checkin` IS NULL, 'Pending', 'Checked In'), 'Checked Out') As status,
				        ((res.`date_end_id` - res.`date_start_id`) + 1) As nights,
				        CASE WHEN res.`deposit` <= res.`paid` or res.`deposit` IS NULL or res.`deposit` = '' THEN true 
				        	 ELSE false
					    END AS is_deposit_paid,
						IFNULL(res.paid, 0) as paid
				FROM `reservation` AS res
				     LEFT JOIN `clients` ON res.`clients_id` = `clients`.`clients_id`
				     LEFT JOIN `reservation_conn` ON res.`reservation_conn_id` = `reservation_conn`.`reservation_conn_id`
				     LEFT JOIN `booking_source` ON `reservation_conn`.`bookingsource_id` = `booking_source`.`booking_source_id`
				WHERE res.`status` = :status and
				      ((res.`date_start_id` >= :start_idPeriod and res.`date_start_id` <= :end_idPeriod) or ((res.`date_end_id`+1) >= :start_idPeriod and (res.`date_end_id`+1) <= :end_idPeriod) or (res.`date_start_id` <= :start_idPeriod and (res.`date_end_id`+1) >= :start_idPeriod) or (res.`date_start_id` <= :end_idPeriod and (res.`date_end_id`+1) >= :end_idPeriod)) and FIND_IN_SET( res.`appartments_id`, :apps_id)";
		//$result1 = $this->executeQuery($sql); # reservation table
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':status' => 'active',
				':start_idPeriod' => $start_idPeriod,
				':end_idPeriod' => $end_idPeriod,
				':apps_id' => $apps_id
			)
		);
		$result1 = $stmt->fetchAll( PDO::FETCH_ASSOC ); # reservation table

		$sql = "SELECT res.`reservation_id`, res.`reservation_conn_id`, res.`appartments_id`, res.`date_start_id`, 
				        res.`date_end_id`, `clients`.`clients_id`, `clients`.`name` As client_fname, `clients`.`surname` As client_lname, 
				        `booking_source`.`booking_source_name`, `reservation_conn`.`reference_num`,
				        ((((SELECT SUM(trans_res.`rate_total`) AS price_trans FROM `transfer_room_history` AS trans_res WHERE trans_res.`reservation_id` = res.`reservation_id`) + (SELECT SUM(res1.`rate_total`) AS price_trans FROM `reservation` AS res1 WHERE res1.`reservation_id` = res.`reservation_id`)) - (SELECT SUM(res1.`discount`) AS price_trans FROM `reservation` AS res1 WHERE res1.`reservation_id` = res.`reservation_id`)) - (SELECT SUM(res1.`paid`) AS price_trans FROM `reservation` AS res1 WHERE res1.`reservation_id` = res.`reservation_id`)) As remaining_balance,
				        (SELECT `start_date` FROM `periods` As s_period WHERE s_period.`periods_id` = res.`date_start_id`) As start_date, (SELECT `end_date` FROM `periods` As s_period WHERE s_period.`periods_id` = res.`date_end_id`) As end_date,
				        IF(res.`checkout` IS NULL, IF(res.`checkin` IS NULL, 'Pending', 'Checked In'), 'Checked Out') As status,
				        ((res.`date_end_id` - res.`date_start_id`) + 1) As nights,
				        (SELECT CASE WHEN res1.`deposit` <= res1.`paid` or res1.`deposit` IS NULL or res1.`deposit` = '' THEN true ELSE false END FROM `reservation` AS res1 WHERE res1.`reservation_id` = res.`reservation_id`) As is_deposit_paid,
						IFNULL(res.paid, 0) as paid
				FROM `transfer_room_history` AS res
				     LEFT JOIN `clients` ON res.`clients_id` = `clients`.`clients_id`
				     LEFT JOIN `reservation_conn` ON res.`reservation_conn_id` = `reservation_conn`.`reservation_conn_id`
				     LEFT JOIN `booking_source` ON `reservation_conn`.`bookingsource_id` = `booking_source`.`booking_source_id`
				WHERE res.`status` = :status and res.`transfer_status` = :transfer_status and
				      ((res.`date_start_id` >= :start_idPeriod and res.`date_start_id` <= :end_idPeriod) or ((res.`date_end_id`+1) >= :start_idPeriod and (res.`date_end_id`+1) <= :end_idPeriod) or (res.`date_start_id` <= :start_idPeriod and (res.`date_end_id`+1) >= :start_idPeriod) or (res.`date_start_id` <= :end_idPeriod and (res.`date_end_id`+1) >= :end_idPeriod)) and FIND_IN_SET( res.`appartments_id`, :apps_id)";
		//$result2 = $this->executeQuery($sql); # transfer_room_history table
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':status' => 'active',
				':transfer_status' => 'checkin',
				':start_idPeriod' => $start_idPeriod,
				':end_idPeriod' => $end_idPeriod,
				':apps_id' => $apps_id
			)
		);
		$result2 = $stmt->fetchAll( PDO::FETCH_ASSOC ); # transfer_room_history table

		$merged_result = array_merge($result1,$result2);

		return $merged_result;
	}
	public function getCalendar_blocking($start_idPeriod, $end_idPeriod, $apps_id){
		$sql = 'SELECT a.appartment_id AS appartments_id, "Room Blocked" AS booking_source_name, a.blocking_reason AS client_fname, "" AS client_lname, "" AS clients_id, a.blocking_end_date_id AS date_end_id, a.blocking_start_date_id AS date_start_id, "1" AS is_deposit_paid, "" AS reference_num, "" AS remaining_balance, "" AS reservation_conn_id, "" AS reservation_id, "Block" AS status, (SELECT `start_date` FROM `periods` AS s_period WHERE s_period.`periods_id` = a.`blocking_start_date_id`) AS start_date, 
				(SELECT `end_date` FROM `periods` AS s_period WHERE s_period.`periods_id` =a.`blocking_end_date_id`) AS end_date,
				((a.`blocking_end_date_id` - a.`blocking_start_date_id`) + 1) As nights 
				FROM blocking a WHERE a.status = "active" AND 
				((a.blocking_start_date_id >= :start_idPeriod AND a.blocking_start_date_id <= :end_idPeriod) OR
				((a.blocking_end_date_id+1) >= :start_idPeriod AND (a.blocking_end_date_id+1) <= :end_idPeriod) OR
				(a.blocking_start_date_id <= :start_idPeriod AND (blocking_end_date_id+1) >= :start_idPeriod) OR
				(a.blocking_start_date_id <= :end_idPeriod AND (a.blocking_end_date_id+1) >= :end_idPeriod)) AND
				FIND_IN_SET(a.appartment_id, :apps_id)';
		//$result1 = $this->executeQuery($sql); # reservation table
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':status' => 'active',
				':start_idPeriod' => $start_idPeriod,
				':end_idPeriod' => $end_idPeriod,
				':apps_id' => $apps_id
			)
		);
		$result1 = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $result1;
	}
	public function week_range_date($week, $year) { # $week, $year
		$dto = new DateTime();
		//$week = 44;
		//$year = 2017;
		$result[0] = $dto->setISODate($year, $week, 0)->format('Y-m-d');
		$result[1] = $dto->setISODate($year, $week, 6)->format('Y-m-d');
		return $result;
	} 
	public function get_weeks_period($year){ 
		if($year == ''){
			$year = $this->getCurrent_Year();
		}
		$date = new DateTime;
		$date->setISODate($year, 53);
		$tot_weeks = ($date->format("W") === "53" ? 53 : 52);
		$result;
		for($x=1; $x<=$tot_weeks; $x++){
		    $week_date_range = $this->week_range_date($x, $year);
		    $result[$x-1]["start_date"] = $week_date_range[0];
		   	$result[$x-1]["end_date"] = $week_date_range[1];
		   	$result[$x-1]["label"] = date('M d, Y',strtotime($week_date_range[0]))." - ".date('M d, Y',strtotime($week_date_range[1]));
		}
		return $result;
	}
	public function get_months_period($year){ 
		if($year == ''){
			$year = $this->getCurrent_Year();
		}
		$result;
		$sql = "SELECT date_add(CAST(:date AS date),interval -DAY(CAST(:date AS date))+1 DAY) AS start_date, LAST_DAY(CAST(:date AS date)) AS end_date, MONTHNAME(:date) As month";
		for($x=1; $x<=12; $x++){
			$temp_date = $year."-".sprintf("%02d", $x)."-01";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(
				array(
					':date' => $temp_date
				)
			);
			$month_num = sprintf("%02d", $x);
			$result1 = $stmt->fetchAll( PDO::FETCH_ASSOC );
			$result[$x-1]["start_date"] = $result1[0]["start_date"];
			$result[$x-1]["end_date"] = $result1[0]["end_date"];
			$result[$x-1]["label"] = $result1[0]["month"];
		}
		return $result;
	}
	public function get_periods($start_date_id, $end_date_id){ 
		$sql = "SELECT `periods_id`, `start_date`, `end_date` FROM `periods` WHERE `periods_id` >= :start_idPeriod and `periods_id` <= :end_idPeriod";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':start_idPeriod' => $start_date_id,
				':end_idPeriod' => $end_date_id
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC ); 
		return $result;
	}
	public function get_rooms(){ 
		$sql = "SELECT `apartment_id`, `apartment_name` FROM `apartments` ORDER BY `apartment_name` ASC";
		$result = $this->executeQuery($sql);
		return $result;
	}// orig space : activity log reminder

	public function get_client_info_modifications($client_info){
		$sql_compare = "SELECT CONCAT(
							       CASE WHEN (`surname` != :surname or `name` != :name) THEN (CONCAT(' Client Name \"',`name`,' ',`surname`,'\" to \"',:name,' ',:surname,'\";')) ELSE CONCAT(' Client Name ',`name`,' ',`surname`,'.') END,
							       CASE WHEN `nickname` != :nickname THEN (CONCAT(' Nickname \"',`nickname`,'\" ','to \"',:nickname,'\";')) WHEN `nickname` IS NULL and :nickname IS NOT NULL THEN (CONCAT(' Nickname \"',:nickname,'\";')) ELSE '' END, 
							       CASE WHEN `sex` != :sex THEN (CONCAT(' Gender \"',`sex`,'\" ','to \"',:sex,'\";')) WHEN `sex` IS NULL and :sex IS NOT NULL THEN (CONCAT(' Gender \"',:sex,'\";')) ELSE '' END, 
							       CASE WHEN `language` != :language THEN (CONCAT(' Language \"',`language`,'\" ','to \"',:language,'\";')) WHEN `language` IS NULL and :language IS NOT NULL THEN (CONCAT(' Language \"',:language,'\";')) ELSE '' END, 
							       CASE WHEN `date_of_birth` != :date_of_birth THEN (CONCAT(' Date of birth \"',`date_of_birth`,'\" ','to \"',:date_of_birth,'\";')) WHEN `date_of_birth` IS NULL and :date_of_birth IS NOT NULL THEN (CONCAT(' Date od birth \"',:date_of_birth,'\";')) ELSE '' END, 
							       CASE WHEN `nationality` != :nationality THEN (CONCAT(' Nationality \"',`nationality`,'\" ','to \"',:nationality,'\";')) WHEN `nationality` IS NULL and :nationality IS NOT NULL THEN (CONCAT(' Nationality \"',:nationality,'\";')) ELSE '' END, 
							       CASE WHEN `phone` != :phone THEN (CONCAT(' Phone \"',`phone`,'\" ','to \"',:phone,'\";')) WHEN `phone` IS NULL and :phone IS NOT NULL THEN (CONCAT(' Phone \"',:phone,'\";')) ELSE '' END, 
							       CASE WHEN `phone2` != :phone2 THEN (CONCAT(' Phone \"',`phone2`,'\" ','to \"',:phone2,'\";')) WHEN `phone2` IS NULL and :phone2 IS NOT NULL THEN (CONCAT(' Phone \"',:phone2,'\";')) ELSE '' END, 
							       CASE WHEN `email` != :email THEN (CONCAT(' Email \"',`email`,'\" ','to \"',:email,'\";')) WHEN `email` IS NULL and :email IS NOT NULL THEN (CONCAT(' Email \"',:email,'\";')) ELSE '' END, 
							       CASE WHEN `title` != :title THEN (CONCAT(' Title \"',`title`,'\" ','to \"',:title,'\";')) WHEN `title` IS NULL and :title IS NOT NULL THEN (CONCAT(' Title \"',:title,'\";')) ELSE '' END, 
							       CASE WHEN `home_address` != :home_address THEN (CONCAT(' Home address \"',`home_address`,'\" ','to \"',:home_address,'\";')) WHEN `home_address` IS NULL and :home_address IS NOT NULL THEN (CONCAT(' Home address \"',:home_address,'\";')) ELSE '' END, 
							       CASE WHEN `work_address` != :work_address THEN (CONCAT(' Work address \"',`work_address`,'\" ','to \"',:work_address,'\";')) WHEN `work_address` IS NULL and :work_address IS NOT NULL THEN (CONCAT(' Work address \"',:work_address,'\";')) ELSE '' END, 
							       CASE WHEN `comment` != :comment THEN (CONCAT(' Comment \"',`comment`,'\" ','to \"',:comment,'\";')) WHEN `comment` IS NULL and :comment IS NOT NULL THEN (CONCAT(' Comment \"',:comment,'\";')) ELSE '' END
					      ) As modifications
						FROM `clients`
						WHERE `clients_id` = :clients_id";
		$stmt = $this->db->prepare($sql_compare);
		if( isset($client_info['language']) ){
			$language = implode(", ",$client_info['language']);

		}else {
			$language = null;
		}
		$stmt->execute(
			array(
				":clients_id"		=> $client_info['clients_id'], 
				":surname"			=> $client_info['surname'] ? $client_info['surname'] : null,
				":name"				=> $client_info['name'] ? $client_info['name'] : null,
				":nickname"			=> $client_info['nickname'] ? $client_info['nickname'] : null,
				":sex"				=> $client_info['sex'] ? $client_info['sex'] : null,
				":language"			=> $language, 
				":date_of_birth"	=> $client_info['date_of_birth'] ? $client_info['date_of_birth'] : null, 
				":nationality"		=> $client_info['nationality'] ? $client_info['nationality'] : null,
				":phone"			=> $client_info['phone'] ? $client_info['phone'] : null, 
				":phone2"			=> $client_info['phone2'] ? $client_info['phone2'] : null, 
				":email"			=> $client_info['email'] ? $client_info['email'] : null, 
				":title"			=> $client_info['title'] ? $client_info['title'] : null, 
				":home_address"		=> $client_info['home_address'] ? $client_info['home_address'] : null, 
				":work_address"		=> $client_info['work_address'] ? $client_info['work_address'] : null, 
				":comment"			=> $client_info['comment'] ? $client_info['comment'] : null
			)
		);
		$modifications = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $modifications[0]["modifications"];
	}
	/* -- START : Activity Log -- */
	public function log_activity($log_data, $sendNotif){ # $sendNotif (bolean, true: notify | false: don't notify)
		$this->insertNotification($log_data);
		if($sendNotif == true){ # send notification(s)
			$this->setMsg(json_encode($log_data, JSON_UNESCAPED_SLASHES));
			/* send data to web socket */
			$this->sendMessage();
		}
	}
	
	public function log_activity_cashbox($log_data, $sendNotif){ # $sendNotif (bolean, true: notify | false: don't notify)
		/* $this->insertNotification($log_data); */
		if($sendNotif == true){ # send notification(s)
			$this->setMsg(json_encode($log_data, JSON_UNESCAPED_SLASHES));
			/* send data to web socket */
			$this->sendMessage();
		}
	}

	public function log_activity_return_id($log_data, $sendNotif){ # $sendNotif (bolean, true: notify | false: don't notify)
		$notif_id = $this->insertNotification_return_id($log_data);
		if($sendNotif == true){ # send notification(s)
			$this->setMsg(json_encode($log_data, JSON_UNESCAPED_SLASHES));
			/* send data to web socket */
			$this->sendMessage();
		}
		return $notif_id;
	}
	/* -- END : Activity Log -- */
	
	public function getPeriodi($idperiodi, $year, $startend){
		$myDatabase= $this->db; #--Get database connection
		$tableperiodi = "periods";
					
		$sql = "SELECT * FROM $tableperiodi WHERE periods_id = '$idperiodi'";
		$stmt = $myDatabase->prepare( $sql );
		$stmt->execute();
		$DatePeriodi = $stmt->fetchAll( PDO::FETCH_ASSOC );

		return $DatePeriodi[0][$startend];		   
	}

	public function getIdPeriodi($periodi, $year, $startend){
		$myDatabase= $this->db; #--Get database connection
		$tableperiodi = "periods";
					
		$sql = "SELECT * FROM ".$tableperiodi." WHERE ".$startend." = '".$periodi."' ";
		$stmt = $myDatabase->prepare( $sql );
		$stmt->execute();
		$datePeriodiId = $stmt->fetchAll( PDO::FETCH_ASSOC );

		return $datePeriodiId[0]['periods_id'];		   

	}
	
	public function getRoom_Name($room_id) {
		$myDatabase = $this->db;
		
		$sql = "SELECT apartment_name FROM apartments WHERE apartment_id = :room_id";
		$stmt = $myDatabase->prepare($sql);
		$stmt->execute(
			array(
				':room_id' => $room_id
			)
		);
		$results = $stmt->fetchAll( PDO::FETCH_ASSOC );
		
		return $results[0]['apartment_name'];
	}
	
	public function invoice_pdf_template1($res_info, $rooms_info, $invoice_num, $extra_charges, $addon_charges){ # $res_info, $invoice_num, $extra_charges
	
		$bill_to = $res_info[0]["surname"].", ".$res_info[0]["name"];
		$invoice_no = $invoice_num;
		$date_created = $res_info[0]["date_created"];

		$booking_source = $res_info[0]["booking_source_name"];
		$reference_no = $res_info[0]["reference_num"];
		$room = $res_info[0]["apartment_name"]." (".$res_info[0]["room_type_name"].")";
		$orig_rate = explode("#@&", $res_info[0]["original_rate"]);
		$cost = $orig_rate[1];
		$checkin_date = $res_info[0]["start_date"];
		$checkout_date = $res_info[0]["end_date"];
		
		$getCurrency = $this->executeQuery("SELECT * FROM global_variables WHERE `key` = 'currency'");
		$getHotelPhone = $this->executeQuery("SELECT * FROM global_variables WHERE `key` = 'phone'");
		$getHotelAddress = $this->executeQuery("SELECT * FROM global_variables WHERE `key` = 'address'");
		$getHotelName = $this->executeQuery("SELECT * FROM global_variables WHERE `key` = 'site_title'");
		$getPdfLogo = $this->executeQuery("SELECT * FROM global_variables WHERE `key` = 'pdf_logo'");
		
		if(count($getPdfLogo) > 0){
			$hotel_pdf_logo = $getPdfLogo[0]['value'];
		}else{
			$hotel_pdf_logo = "http://localhost/HTL_v2/images/htl-logo.png";
		}
		
		if(count($getHotelName) > 0){
			$hotel_name = $getHotelName[0]['value'];
		}else{
			$hotel_name = "Hotel PMS";
		}
		
		if(count($getCurrency) > 0){
			$explode_currency = explode(",", $getCurrency[0]["value"]);
			$currency_sign = $explode_currency[1];
		}else{
			$currency_sign = "PHP";
		}
		
		if(count($getHotelPhone) > 0){
			$hotel_phone = $getHotelPhone[0]['value'];
		}else{
			$hotel_phone = "+999999999999";
		}
		
		if(count($getHotelAddress) > 0){
			$hotel_address = $getHotelAddress[0]['value'];
		}else{
			$hotel_address = "Hotel Address, Philippines";
		}
		
		
		$days_orig = ($res_info[0]["date_end_id_orig"] - $res_info[0]["date_start_id_orig"]) + 1;
		$days = "".$days_orig;
		if($res_info[0]["date_end_id_orig"] < $res_info[0]["date_end_id"]){
			$days = ($res_info[0]["date_end_id"] - $res_info[0]["date_start_id_orig"]) + 1;
			//$days = "<span style='text-decoration: line-through;'>".$days."</span>"." <span>Extended to ".$days_new."</span>";
		}
		$discount = $res_info[0]["discount"];

		$cost_rooms = 0; 
		$cnt1 = count($rooms_info);
		$rooms = "";
		for($x=0; $x<$cnt1; $x++){
			$room_temp = $rooms_info[$x]["room_name"];
			$rate_temp1 = explode("#@&", $rooms_info[$x]["rate"]);
			$room_type_temp = $rate_temp1[0];
			$rate_temp = $rate_temp1[1];
			$period_temp = $rooms_info[$x]["start_date"]." - ".$rooms_info[$x]["end_date"];
			$cost_rooms += $rate_temp;
			$trans = "";
			if($x > 0){
				$trans = " (transferred)";
			}
			$rooms .= "<tr style=''>
						  <td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>".$room_temp." ".$trans."</p></td>
						  <td style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>".$room_type_temp."</p></td>
						  <td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$rate_temp,2)." ".$currency_sign."</p></td>
						  <td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".$period_temp."</p></td>
					  </tr>";
		}

		$cnt = count($extra_charges);
		$extra_charges_html = "";
		$total_extra_charge = 0;
		for($x=0; $x<$cnt; $x++){
			$Item_Name = $extra_charges[$x]["item_name"];
			$Category = $extra_charges[$x]["category"];
			$Quantity = $extra_charges[$x]["quantity"];
			$Unit_Price = $extra_charges[$x]["unit_price"];
			$Total_Price = $extra_charges[$x]["total_price"];
			$Date_Charged = $extra_charges[$x]["date_charged"];

			$total_extra_charge += $Total_Price;

			$style_ni = "";
			if($x%2 == 0){
				$style_ni = "";
			}
			else{
				$style_ni = "background-color:#EDFCFF;";
			}
			$extra_charges_html .= "<tr style='".$style_ni."'>
							  			<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>".$Item_Name."</p></td>
										<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>".$Category."</p></td>
							  			<td style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>".$Quantity."</p></td>
							  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$Unit_Price,2)." ".$currency_sign."</p></td>
							  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$Total_Price,2)." ".$currency_sign."</p></td>
							  			<td style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>".$Date_Charged."</p></td>
							  		</tr>";
		}
		$cnt123 = count($addon_charges);
		$addon_charges_html = "";
		for($x=0; $x<$cnt123; $x++){
			$Item_Name = $addon_charges[$x]["item_name"];
			$Quantity = $addon_charges[$x]["quantity"];
			//$Unit_Price = $addon_charges[$x]["unit_price"];
			$Total_Price = $addon_charges[$x]["total_price"];
			$Date_Charged = $addon_charges[$x]["date_charged"];

			$total_extra_charge += $Total_Price;

			$style_ni = "";
			if($x%2 == 0){
				$style_ni = "";
			}
			else{
				$style_ni = "background-color:#EDFCFF;";
			}
			$addon_charges_html .= "<tr style='".$style_ni."'>
							  			<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>".$Item_Name."</p></td>
							  			<td style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>".$Quantity."</p></td>
							  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$Total_Price,2)." ".$currency_sign."</p></td>
							  			<td style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>".$Date_Charged."</p></td>
							  		</tr>";
		}
		//$grand_total = ($cost + $total_extra_charge) - $discount;
		$grand_total = ($cost_rooms + $total_extra_charge) - $discount;

		$html1 = "<div style='border-bottom: 1px solid #c0c0c0; width: 100%; float: left'>
						<div style='float: left; width: 50%;'>
							<img style='float: left;' src='".$hotel_pdf_logo."' alt='' width='170' height='70' />
						</div>
						<div style='float: left; width: 50%; text-align: right;'>
							<span style='font-size: 38pt; color: #c0c0c0;'>
								INVOICE
							</span>
						</div>
					</div>";
		$html2 = "<div style='width: 100%; float: left'>
						<div style='float: left; width: 100%;'>
							<h3><b>".$hotel_name."</b></h3>
						</div>
					</div>
					<div style='width: 100%; float: left'>
						<div style='float: left; width: 50%;'>
							<p style='font-size: xx-small;'>
								".$hotel_address."
							</p>
							<p style='font-size: xx-small;'>
								".$hotel_phone."
							</p>
						</div>
						<div style='float: left; width: 50%; text-align: left;'>
							<p>
										
							</p>
						</div>
					</div>
					<div style='width: 100%; float: left'>
						<br>
					</div>
					<div style='width: 100%; float: left'>
						<table style='font-size: xx-small; font-family: Arial, Helvetica, sans-serif; width: 100%;'>
						  	<tbody>
						  		<tr>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>BILL TO</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$bill_to."</p></td>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Invoice No.</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$invoice_no."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'></p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'></p></td>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Date</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$date_created."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='' colspan='4'><p style='margin: 8px;'></p></td>
						  		</tr>
						  		<tr>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Booking Source</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$booking_source."</p></td>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Checkin Date</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$checkin_date."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Reference No.</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$reference_no."</p></td>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Checkout Date</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$checkout_date."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'>Day(s)</p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'>".$days."</p></td>
						  			<td style='background-color: #EDFCFF; width: 25px;'><p style='font-size: xx-small; margin: 8px;'></p></td>
						  			<td style='width: 75px; border-bottom: 1px solid #EDFCFF'><p style='font-size: xx-small; margin: 8px;'></p></td>
						  		</tr>
						  	</tbody>
						</table>
					</div>";
		$html3 = "<div style='width: 100%; float: left'>
						<br>
					</div>
					<div style='width: 100%; float: left'>
						<p style='font-size: xx-small;'>ROOM COST</p>
						<table style='font-size: xx-small; font-family: Arial, Helvetica, sans-serif; width: 100%;'>
						  	<thead>
						  		<tr style='background-color:#EDFCFF;'>
						  			<th style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Room(s)</p></th>
						  			<th style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>Room Type</p></th>
						  			<th style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>Rate</p></th>
						  			<th style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>Period</p></th>
						  		</tr>
						  	</thead>
						  	<tbody>".
						  		$rooms	
						  	."</tbody>
						</table>
					</div>
					<div style='width: 100%; float: left'>
						<br>
					</div>
					<div style='width: 100%; float: left'>
						<p style='font-size: xx-small;'>EXTRA CHARGES</p>
						<table style='font-size: xx-small; font-family: Arial, Helvetica, sans-serif; width: 100%;'>
						  	<thead>
						  		<tr style='background-color:#EDFCFF;'>
						  			<th style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Item Name</p></th>
									<th style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Category</p></th>
						  			<th style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>Quantity</p></th>
						  			<th style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>Unit Price</p></th>
						  			<th style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>Total Price</p></th>
						  			<th style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>Date Charged</p></th>
						  		</tr>
						  	</thead>
						  	<tbody>".
						  		$extra_charges_html
						  	."</tbody>
						</table>
						<p style='font-size: xx-small; margin-left:3px; margin-bottom:0px;'><small>Add-on Charges</small></p>
						<table style='font-size: xx-small; font-family: Arial, Helvetica, sans-serif; width: 100%;'>
						  	<thead>
						  		<tr style='background-color:#EDFCFF;'>
						  			<th style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Item Name</p></th>
						  			<th style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>Quantity</p></th>
						  			<th style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>Total Price</p></th>
						  			<th style='text-align: center;'><p style='font-size: xx-small; margin: 8px;'>Date Charged</p></th>
						  		</tr>
						  	</thead>
						  	<tbody>".
						  		$addon_charges_html
						  	."</tbody>
						</table>
					</div>
					<div style='width: 100%; float: left'>
						<br>
					</div>
					<div style='width: 100%; float: left'>
						<table style='font-size: xx-small; font-family: Arial, Helvetica, sans-serif; width: 25%;'>
						  	<tbody>
						  		<tr>
						  			<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Room Cost</p></td>
						  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$cost_rooms,2)." ".$currency_sign."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Extra Charges</p></td>
						  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$total_extra_charge,2)." ".$currency_sign."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='text-align: left;'><p style='font-size: xx-small; margin: 8px;'>Discount</p></td>
						  			<td style='text-align: right;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$discount,2)." ".$currency_sign."</p></td>
						  		</tr>
						  		<tr>
						  			<td style='text-align: left; border-top: 2px solid black;'><p style='font-size: xx-small; margin: 8px;'>TOTAL</p></td>
						  			<td style='text-align: right; border-top: 2px solid black;'><p style='font-size: xx-small; margin: 8px;'>".number_format((float)$grand_total,2)." ".$currency_sign."</p></td>
						  		</tr>
						  	</tbody>
						</table>
					</div>";
		return "<div style='font-family: Arial, Helvetica, sans-serif; width: 100%;'>".$html1.$html2.$html3."</div>";
	}

	public function create_pdf($dir, $html, $filename, $papersize){ # $dir, $html, $filename, $papersize='Letter'
		try{
			//$dir = "docs/invoice/PDF";
			$mpdf=new mPDF('c',$papersize,'','' , 20 , 20 , 5 , 0 , 0 , 0); 
			$mpdf->SetDisplayMode('fullpage');
			$mpdf->list_indent_first_level = 0;  // 1 or 0 - whether to indent the first level of a list
			$mpdf->WriteHTML($html);
			$mpdf->Output('../../../'.$dir.'/'.$filename.'.pdf' , 'F' );
			//echo "success";
			return "success";
		}
		catch (PDOException $e) {
			//echo "failed";
			return "failed";
		}
	}
	
	public function get_rate_type_custom(){
		$sql = "SELECT *
				FROM `rate_type`
				WHERE `status` = 'active' and `id` != 1";
		$result = $this->executeQuery($sql);
		return $result;
	}
	
	public function get_last_date_period(){
		$sql = "SELECT *
				FROM `periods`
				WHERE `periods_id` = (SELECT max(`periods_id`) FROM `periods`)";
		$result = $this->executeQuery($sql);
		//echo json_encode($result);
		return $result;
	}
	
	public function get_current_rate(){
		// `room_types`.`status` = 'active' and
		$sql = "SELECT `room_types`.`room_type_id` As room_type_id, `room_types`.`associated_column` As associated_column, `room_rates`.`rate` As rate
				FROM `room_types`, `room_rates`
				WHERE `room_rates`.`room_type_id` = `room_types`.`room_type_id` and
				      `room_rates`.`status` = 'active' and
				      `room_rates`.`rate_type_id` = '1' and
				      `room_rates`.`periods_id_end` = (SELECT MAX(`periods_id`) FROM `periods`)";
		$result = $this->executeQuery($sql);
		//echo json_encode($result);
		return $result;
	}
	
	public function octorate_room_blocking($block_id, $room_id, $date_start_id, $date_end_id, $reason, $id_property){
		
		$getOctoRooms = $this->executeQuery("SELECT octorate_roomtype.idroomtype_octo FROM apartments LEFT JOIN octorate_roomtype on octorate_roomtype.roomtype_id = apartments.roomtype_id WHERE apartment_id = '$room_id'");
		$octo_room_id = $getOctoRooms[0]['idroomtype_octo'];
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
						'<BookReservationRequest>'.
						  '<Auth>'.
							  '<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
							  '<PropertyId>'.$id_property.'</PropertyId>'.
						  '</Auth>'.
						  '<Reservations>'.
						  '<Reservation>'.
							  '<From>'.$date_start_id.'</From>'.
							  '<To>'.$date_end_id.'</To>'.
							  '<Rooms>'.
								  '<Room>'.
									 '<Roomid>'.$octo_room_id.'</Roomid>'.
									 '<Pax>2</Pax>'.
									 '<Total>0</Total>'.
									 '<Guestname>'.$reason.'</Guestname>'.
									 '<Status>Confirmed</Status>'.
								  '</Room>'.
							  '</Rooms>'.
							  '</Reservation>'.
							  '</Reservations>'.
						 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		$array_data = json_decode(json_encode(simplexml_load_string($data)), true);
		
		$bb_id = $array_data['RoomUpdateMessage']['Bbliverateresvid'];
		
		$sqlBbUpdate = "UPDATE blocking SET octorate_bb_id = '$bb_id' WHERE blocking_id = '$block_id'";
		$this->execute_update($sqlBbUpdate);
	}
	
	public function octorate_room_unblock($bb_id, $room_id, $date_start_id, $date_end_id, $id_property){
		
		$getOctoRooms = $this->executeQuery("SELECT octorate_roomtype.idroomtype_octo FROM apartments LEFT JOIN octorate_roomtype on octorate_roomtype.roomtype_id = apartments.roomtype_id WHERE apartment_id = '$room_id'");
		$octo_room_id = $getOctoRooms[0]['idroomtype_octo'];
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<BookReservationRequest>'.
							  '<Auth>'.
									'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
									'<PropertyId>'.$id_property.'</PropertyId>'.
							  '</Auth>'.
							  '<Reservations>'.
							  '<Reservation>'.
								  '<From>'.$date_start_id.'</From>'.
								  '<To>'.$date_end_id.'</To>'.
								  '<Rooms>'.
									  '<Room>'.
										 '<Bbliverateresvid>'.$bb_id.'</Bbliverateresvid>'.
										 '<Status>Cancelled</Status>'.
									  '</Room>'.
								  '</Rooms>'.
								  '</Reservation>'.
								  '</Reservations>'.
							 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function decrement_periods_cancelled($datas, $start_id, $end_id, $room_id) {
		$output = array();
		$request = $this->executeQuery('SELECT property_id FROM hotel_property');
		$property_id = $request[0]['property_id'];
		$request = $this->executeQuery("SELECT b.* FROM apartments a
										LEFT JOIN octorate_roomtype b ON b.roomtype_id = a.roomtype_id
										WHERE a.apartment_id = '$room_id'");
		$octo_room_id = $request[0]['idroomtype_octo'];
		/* $periods = '<Room>
						 <RoomId>' . $octo_room_id . '</RoomId>
						 <From></From>
						 <To></To>
					</Room>'; */
		$periods = '';
		foreach($datas as $data) {
			if($data['blocking_start_date_id'] <= $start_id) {
				$output_start_id = $start_id;
			}
			if($data['blocking_start_date_id'] > $start_id) {
				$output_start_id = $data['blocking_start_date_id'];
			}
			if($data['blocking_end_date_id'] <= $end_id) {
				$output_end_id = $data['blocking_end_date_id'];
			}
			if($data['blocking_end_date_id'] > $end_id) {
				$output_end_id = $end_id;
			}
			$start = $this->get_period_date($output_start_id, 'start_date');
			$d = new DateTime($this->get_period_date($output_end_id , 'end_date'));
			$d = $d->modify('-1 day');
			$end = $d->format('Y-m-d');
			$periods .= '<Room>';
			$periods .= '<RoomId>' . $octo_room_id . '</RoomId>';
			$periods .= '<From>' . $start . '</From>';
			$periods .= '<To>' . $end . '</To>';
			/* array_push($output, array('start_id' => $output_start_id, 'end_id' => $output_end_id)); */
			$periods .= '</Room>';
			
		}
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directdecrementroom";
		$payload_xml = '<?xml version="1.0" encoding="UTF-8"?>
					  <DecrementRoomRequest>
					  <Auth>
						  <ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>
						  <PropertyId>' . $property_id . '</PropertyId>
					  </Auth>
						  <Rooms>'
						  . $periods .
						  '</Rooms>
					 </DecrementRoomRequest>';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $payload_xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $payload_xml;
	}
	
	public function decrement_periods($datas, $start_id, $end_id, $room_id) {
		$output = array();
		$request = $this->executeQuery('SELECT property_id FROM hotel_property');
		$property_id = $request[0]['property_id'];
		$request = $this->executeQuery("SELECT b.* FROM apartments a
										LEFT JOIN octorate_roomtype b ON b.roomtype_id = a.roomtype_id
										WHERE a.apartment_id = '$room_id'");
		$octo_room_id = $request[0]['idroomtype_octo'];
		/* $periods = '<Room>
						 <RoomId>' . $octo_room_id . '</RoomId>
						 <From></From>
						 <To></To>
					</Room>'; */
		$periods = '';
		foreach($datas as $data) {
			if($data['date_start_id'] <= $start_id) {
				$output_start_id = $start_id;
			}
			if($data['date_start_id'] > $start_id) {
				$output_start_id = $data['date_start_id'];
			}
			if($data['date_end_id'] <= $end_id) {
				$output_end_id = $data['date_end_id'];
			}
			if($data['date_end_id'] > $end_id) {
				$output_end_id = $end_id;
			}
			$start = $this->get_period_date($output_start_id, 'start_date');
			$d = new DateTime($this->get_period_date($output_end_id , 'end_date'));
			$d = $d->modify('-1 day');
			$end = $d->format('Y-m-d');
			$periods .= '<Room>';
			$periods .= '<RoomId>' . $octo_room_id . '</RoomId>';
			$periods .= '<From>' . $start . '</From>';
			$periods .= '<To>' . $end . '</To>';
			/* array_push($output, array('start_id' => $output_start_id, 'end_id' => $output_end_id)); */
			$periods .= '</Room>';
			
		}
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directdecrementroom";
		$payload_xml = '<?xml version="1.0" encoding="UTF-8"?>
					  <DecrementRoomRequest>
					  <Auth>
						  <ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>
						  <PropertyId>' . $property_id . '</PropertyId>
					  </Auth>
						  <Rooms>'
						  . $periods .
						  '</Rooms>
					 </DecrementRoomRequest>';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $payload_xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $payload_xml;
	}// orig space before room splitting
	/* ---- Room Splitting : START ----- */
	public function get_split_rooms($roomtype_id, $date_start_id, $date_end_id) {
		/*$roomtype_id = 2;
		$date_start_id = 674;
		$date_end_id = 680;*/
		$result = [];
		$temp_date_start_id = $date_start_id;
		$temp_date_end_id = $date_start_id;
		$temp_rooms = [];
		do {
		    $rooms = $this->rooms_avialable($roomtype_id, $temp_date_start_id, $temp_date_end_id);
		    if(count($rooms) > 0 && $temp_date_end_id <= $date_end_id){
		    	$temp_rooms[0]["room_id"] = $rooms[0];
		    	$temp_rooms[0]["date_start_id"] = $temp_date_start_id;
		    	$temp_rooms[0]["date_end_id"] = $temp_date_end_id;
		    	$temp_date_end_id++;
		    }
		    else{ # get one of any rooms from the previous cycle
		    	if(count($temp_rooms) > 0){ # panigurado lang.^^
		    		$result[] = $temp_rooms[0];
		    		$temp_rooms = [];
		    	}
		    	else{ # wala gyud ni available ba
		    		$result = false;
		    		break;
		    	}
		    	$temp_date_start_id = $temp_date_end_id;
		    }
		} while ($temp_date_end_id <= ($date_end_id + 1) && $temp_date_start_id <= $date_end_id );

	    return $result;
	}

	public function rooms_avialable($room_type_id, $date_start_id, $date_end_id){
		$result = [];
		$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$room_type_id' ORDER BY `apartments`.`priority` ASC");

		$cnt = count($get_rooms);
		for($x=0; $x<$cnt; $x++){
			$idappartamenti = $get_rooms[$x]['apartment_id'];
			$is_available = $this->executeQuery("SELECT reservation_id FROM reservation WHERE status = 'active' and appartments_id = '$idappartamenti' and (((date_start_id <= '$date_start_id' and date_end_id >= '$date_start_id') or (date_start_id <= '$date_end_id' and date_end_id >= '$date_end_id')) or ((date_start_id >= '$date_start_id' and date_start_id <= '$date_end_id') or (date_end_id >= '$date_start_id' and date_end_id <= '$date_end_id')))");
			$is_available_trans = $this->executeQuery("SELECT reservation_id FROM transfer_room_history WHERE status = 'active' and transfer_status = 'checkin' and appartments_id = '$idappartamenti' and (((date_start_id <= '$date_start_id' and date_end_id >= '$date_start_id') or (date_start_id <= '$date_end_id' and date_end_id >= '$date_end_id')) or ((date_start_id >= '$date_start_id' and date_start_id <= '$date_end_id') or (date_end_id >= '$date_start_id' and date_end_id <= '$date_end_id')))");
			$is_available_block = $this->executeQuery("SELECT * FROM blocking WHERE STATUS = 'active' AND appartment_id = '$idappartamenti' AND 
									(((blocking_start_date_id <= '$date_start_id' AND blocking_end_date_id >= '$date_start_id') OR 
									(blocking_start_date_id <= '$date_end_id' AND blocking_end_date_id >= '$date_end_id')) OR 
									((blocking_start_date_id >= '$date_start_id' AND blocking_start_date_id <= '$date_end_id') OR 
									(blocking_end_date_id >= '$date_start_id' AND blocking_end_date_id <= '$date_end_id')))");
			if(count($is_available) == 0 && count($is_available_block) == 0 && count($is_available_trans) == 0){
				$result[] = $idappartamenti;
				//break;
			}
		}
		return $result;
	}

	public function split_rooms_log_activity($reservation_id, $log_data) {
		$notif_id = $this->log_activity_return_id($log_data, true);
		//$this->val_rules_engine($rule_id, $room_type_id, $start_date_id, $end_date_id, "room_split");
		$sql = "INSERT INTO `room_split` (
				`notification_id`,
				`reservation_id`,
				`isSettled`,
				`settled_by`,
				`settled_date`,
				`action`,
				`status`
			) 
			VALUES
			(
				:notification_id,
				:reservation_id,
				:isSettled,
				:settled_by,
				:settled_date,
				:action,
				:status
			)";
		$data_split = array(
				':notification_id' => $notif_id,
				':reservation_id' => $reservation_id,
				':isSettled' => "No",
				':settled_by' => 0,
				':settled_date' => NULL,
				':action' => "",
				':status' => "active"
			);
		$room_split_id = $this->execute_insert_getId($sql, $data_split);
		$sql = "UPDATE `notifications` SET `reservation_link` = REPLACE(`reservation_link`, '[room_split_id]', '$room_split_id') WHERE `notification_id` = '$notif_id'";
		$this->execute_update($sql);
	}

	public function list_splitted_rooms($main_reservation_id){
		$sql2 = "SELECT a.reservation_id FROM reservation a WHERE a.status = 'active' AND a.split_from = :reservation_id";
		$stmt2 = $this->db->prepare($sql2);
		$stmt2->execute(
			array( ':reservation_id' =>  $main_reservation_id )
		);
		return $stmt2->fetchAll(PDO::FETCH_COLUMN);
	}
	
	public function checkRoomAvailabiltyEditPeriod($roomtype_id,$checkin_id,$checkout_id) {
		$result = array();
		$year = date("Y",(time() + (C_DIFF_ORE * 3600)));
		
		$reservation = "reservation";
		
		$checkin_id = $this->getIdPeriodi($checkin_id, $year, 'start_date'); #---- Start Date
		$checkout_id = $this->getIdPeriodi($checkout_id, $year, 'end_date'); #---- End Date
		
		$get_rooms = $this->executeQuery("SELECT * FROM apartments WHERE roomtype_id = '$roomtype_id'");
		$cnt = count($get_rooms);

		for($x=0; $x<$cnt; $x++){
			$apartment_id = $get_rooms[$x]['apartment_id'];
			$is_available = $this->executeQuery("SELECT * FROM $reservation WHERE status = 'active' and appartments_id = '$apartment_id' and (((date_start_id <= $checkin_id and date_end_id >= $checkin_id) or (date_start_id <= $checkout_id and date_end_id >= $checkout_id)) or ((date_start_id >= $checkin_id and date_start_id <= $checkout_id) or (date_end_id >= $checkin_id and date_end_id <= $checkout_id)))");
			if(count($is_available) == 0){
				$result[] = $get_rooms[$x];
			}
		}
		
		$json = json_encode($result);
		return $json;
	}
	
	public function getRelatedReservation($reservation_conn_id){
		$getRelatedReserv = $this->executeQuery("SELECT * FROM `reservation` WHERE reservation_conn_id = '$reservation_conn_id' AND status = 'active' AND split_from = '0'");
		
		$cnt = count($getRelatedReserv);
		for($ff = 0; $ff < $cnt; $ff++){
			$appartments_id = $getRelatedReserv[$ff]['appartments_id'];
			$getRoomDetails = $this->executeQuery("SELECT * FROM `apartments` WHERE `apartment_id` = '$appartments_id' ");
			$getRelatedReserv[$ff]['apartment_name'] = $getRoomDetails[0]['apartment_name'];
		}
		
		return $getRelatedReserv;
	}
	
	public function get_extras_price_section(){
		//price_section
		$sql = "SELECT *
				FROM price_section
				WHERE status = 'active'";
		$result = $this->executeQuery($sql);
		return $result;
	}
	
	public function get_extras_price_category(){
		$sql = "SELECT Distinct rates.price_section_id, rates.price_category_id, price_category.category_label
				FROM rates As rates, price_category
				WHERE price_category.price_category_id = rates.price_category_id";
		$result = $this->executeQuery($sql);
		return $result;
	}
	
	public function get_extras_prices(){
		//price_section
		$sql = "SELECT *
				FROM rates";
		$result = $this->executeQuery($sql);
		return $result;
	}
	
	public function rooms_details($reservation_id){
		$sql2 = "SELECT a.`reservation_id`, a.`reservation_conn_id`, a.`clients_id`, a.`date_start_id`, a.`date_end_id`, 
					a.`discount`,  a.`rate_total`, IFNULL(a.`deposit`,0) AS deposit, IFNULL(a.`paid`, 0) AS paid,
					res_conn.`reference_num` AS ref_num, CONCAT(client.`name`,' ',client.`surname`) AS client_name,
					p1.`start_date`, p2.`end_date`
				FROM `reservation` a
				LEFT JOIN `reservation_conn` AS res_conn ON res_conn.`reservation_conn_id` = a.`reservation_conn_id` 
				LEFT JOIN `clients` AS `client` ON client.`clients_id` = a.`clients_id`
				LEFT JOIN `periods` AS p1 ON p1.periods_id = a.`date_start_id`
				LEFT JOIN `periods` AS p2 ON p2.periods_id = a.`date_end_id`
				WHERE a.`reservation_id` = :reservation_id LIMIT 1";

		$stmt2 = $this->db->prepare($sql2);
		$stmt2->execute(
			array( ':reservation_id' =>  $reservation_id )
		);
		$ret = $stmt2->fetchAll( PDO::FETCH_ASSOC );
		return $ret[0];
	}
	
	public function save_table_res_payment_history($payment_action, $reservation_id, $splitted_from_reservation_id, $payment_value, $outstanding_balance, $client_id, $date_start_id, $date_end_id, $user_id, $paid, $currency, $payment_method){
		try {
			$table_res_payment_history = "res_payment_history";
			$year = null;
			if($payment_action == "add"){
				$table_temp = "reservation";
				$sql = "UPDATE $table_temp 
						SET paid = (IFNULL(paid, 0) + :payment_value)
						WHERE reservation_id = :reservation_id";

				$stmt = $this->db->prepare($sql);
				$stmt->execute(
					array( ':reservation_id' =>  $reservation_id,
						':payment_value' => $payment_value
					)
				);
				$data = array(
					':client_id' => $client_id,
					':checkin_date_id' => $date_start_id,
					':checkout_date_id' => $date_end_id,
					':reservation_id' => $reservation_id,
					':year' => $year,
					':paid' => $payment_value,
					':outstanding_balance' => $outstanding_balance,
					':current_paid' => $paid,
					':date_now' => C_DIFF_ORE,
					':host' => $_SERVER['SERVER_NAME'],
					':user_id' => $user_id,
					':payment_method' => $payment_method
				);
				$sql = "INSERT INTO $table_res_payment_history(client_id, checkin_date_id, checkout_date_id, reservation_id, year, paid, outstanding_balance, current_paid, date_inserted, host,user_insertion,payment_method) VALUES(:client_id, :checkin_date_id, :checkout_date_id, :reservation_id, :year, :paid, :outstanding_balance, :current_paid, NOW() + INTERVAL :date_now HOUR, :host,:user_id, :payment_method)";
				$this->execute_insert($sql, $data);		
			} else if($payment_action == "change_to"){ 
				$table_temp = "reservation";
				$sql = "UPDATE $table_temp 
						SET paid = :payment_value
						WHERE reservation_id = :reservation_id";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(
					array( ':reservation_id' =>  $reservation_id,
						':payment_value' => $payment_value
					)
				);
				$data = array(
					':client_id' => $client_id,
					':checkin_date_id' => $date_start_id,
					':checkout_date_id' => $date_end_id,
					':reservation_id' => $reservation_id,
					':year' => $year,
					':paid' => ($payment_value - $paid),
					':outstanding_balance' => $outstanding_balance,
					':current_paid' => $paid,
					':date_now' => C_DIFF_ORE,
					':host' => $_SERVER['SERVER_NAME'],
					':user_id' => $user_id,
					':payment_method' => $payment_method
				);
				$sql = "INSERT INTO $table_res_payment_history(client_id, checkin_date_id, checkout_date_id, reservation_id, year, paid, outstanding_balance, current_paid, date_inserted, host,user_insertion,payment_method) VALUES(:client_id, :checkin_date_id, :checkout_date_id, :reservation_id, :year, :paid, :outstanding_balance, :current_paid, NOW() + INTERVAL :date_now HOUR, :host,:user_id, :payment_method)";
				$this->execute_insert($sql, $data);
			} else if($payment_action == "deposit_paid"){
				$table_temp = "reservation";
				$sql = "UPDATE $table_temp 
						SET paid = :payment_value
						WHERE reservation_id = :reservation_id";
				$stmt = $this->db->prepare($sql);
				$stmt->execute(
					array( ':reservation_id' =>  $reservation_id,
						':payment_value' => $payment_value
					)
				);
				$data = array(
					':client_id' => $client_id,
					':checkin_date_id' => $date_start_id,
					':checkout_date_id' => $date_end_id,
					':reservation_id' => $reservation_id,
					':year' => $year,
					':paid' => ($payment_value - $paid),
					':outstanding_balance' => $outstanding_balance,
					':current_paid' => $paid,
					':date_now' => C_DIFF_ORE,
					':host' => $_SERVER['SERVER_NAME'],
					':user_id' => $user_id,
					':payment_method' => $payment_method
				);
				$sql = "INSERT INTO $table_res_payment_history(client_id, checkin_date_id, checkout_date_id, reservation_id, year, paid, outstanding_balance, current_paid, date_inserted, host,user_insertion) VALUES(:client_id, :checkin_date_id, :checkout_date_id, :reservation_id, :year, :paid, :outstanding_balance, :current_paid, NOW() + INTERVAL :date_now HOUR, :host,:user_id,:payment_method)";
				$this->execute_insert($sql, $data);
			} else if($payment_action == "all_paid"){
				$table_temp = "reservation";
				$sql = "UPDATE $table_temp 
						SET paid = :payment_value 
						WHERE reservation_id = :reservation_id";

				$stmt = $this->db->prepare($sql);
				$stmt->execute(
					array( ':reservation_id' =>  $reservation_id,
						':payment_value' => $payment_value - $discount
					)
				);
				$data = array(
					':client_id' => $client_id,
					':checkin_date_id' => $date_start_id,
					':checkout_date_id' => $date_end_id,
					':reservation_id' => $reservation_id,
					':year' => $year,
					':paid' => (($payment_value - $discount) - $paid),
					':outstanding_balance' => $outstanding_balance,
					':current_paid' => $paid,
					':date_now' => C_DIFF_ORE,
					':host' => $_SERVER['SERVER_NAME'],
					':user_id' => $user_id,
					':payment_method' => $payment_method
				);
				$sql = "INSERT INTO $table_res_payment_history(client_id, checkin_date_id, checkout_date_id, reservation_id, year, paid, outstanding_balance, current_paid, date_inserted, host,user_insertion,payment_method) VALUES(:client_id, :checkin_date_id, :checkout_date_id, :reservation_id, :year, :paid, :outstanding_balance, :current_paid, NOW() + INTERVAL :date_now HOUR, :host,:user_id,:payment_method)";
				$this->execute_insert($sql, $data);

			}

		} catch (PDOException $e){
			/* $resp = array( "status"=> "error", "message" => $e->getMessage() );
			echo json_encode( $resp );
			http_response_code(500); */
		}
	}
	
	public function add_to_Cost($cost, $cost_name, $cashbox_id, $cashbox_name, $user_id, $cost_type, $payment_method) {
		$host = $_SERVER['SERVER_NAME'];
		$myDatabase = $this->db;
		$sql = "INSERT INTO 
				costs(
					cost_id, 
					cost_name, 
					val_cost, 
					cost_type, 
					cashbox_id, 
					cash_register_name, 
					inserted_date, 
					inserted_host, 
					inserted_by,
					payment_method
				)
				VALUES(
					:cost_id,
					:cost_name,
					:val_cost,
					:cost_type,
					:cashbox_id,
					:cash_register_name,
					NOW() + INTERVAL :inserted_date HOUR,
					:inserted_host,
					:inserted_by,
					:payment_method
				)"; #Gwapo
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':cost_id' => NULL,
				':cost_name' => $cost_name,
				':val_cost' => $cost,
				':cost_type' => $cost_type,
				':cashbox_id' => $cashbox_id,
				':cash_register_name' => $cashbox_name,
				':inserted_date' => C_DIFF_ORE,
				':inserted_host' => $host,
				':inserted_by' => $user_id,
				':payment_method' => $payment_method
			)
		);
	}
	
	public function octorate_no_show($booking, $id_property){
		$hotel_id = $id_property;
		$reservation = $booking;
		$resId = $reservation['idbooking_octo'];
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=BookingSpecialRequest";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?><BookingSpecialRequest><Auth>'.
							'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
							'<PropertyId>'.$hotel_id.'</PropertyId>'.
						  '</Auth>'.
							  '<ReservationId>'.$resId.'</ReservationId>'.
							  '<Request>No show</Request>'.
						 '</BookingSpecialRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function octorate_cancelled($booking, $id_property){
		$hotel_id = $id_property;
		$reservation = $booking;
		$bb_id = $reservation['chnnl_manager_id_res'];
		$check_in = $reservation['date_start_id'];
		$check_out = $reservation['date_end_id'];
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
								'<BookReservationRequest>'.
								  '<Auth>'.
										'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
										'<PropertyId>'.$hotel_id.'</PropertyId>'.
								  '</Auth>'.
								  '<Reservations>'.
								  '<Reservation>'.
									  '<From>'.$check_in.'</From>'.
									  '<To>'.$check_out.'</To>'.
									  '<Rooms>'.
										  '<Room>'.
											 '<Bbliverateresvid>'.$bb_id.'</Bbliverateresvid>'.
											 '<Status>Cancelled</Status>'.
										  '</Room>'.
									  '</Rooms>'.
									  '</Reservation>'.
									  '</Reservations>'.
								 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	
	public function get_dates_from_include_blocking($existing_blocking, $date_start_id, $date_end_id, $octo_roomtype_id)	{
		$dates = array();
		foreach($existing_blocking as $blocking) {
			if($date_start_id < $blocking['blocking_start_date_id'] && $date_end_id <= $blocking['blocking_end_date_id']) {
				$dates = array(
					"octo_roomtype_id" => $octo_roomtype_id,
					"start" => $this->get_period_date($blocking['blocking_start_date_id'], 'start_date'),
					"end" => $this->get_period_date($date_end_id, 'end_date')
				);
			}
			if($date_start_id >= $blocking['blocking_start_date_id'] && $date_end_id > $blocking['blocking_end_date_id']) {
				$dates = array(
					"octo_roomtype_id" => $octo_roomtype_id,
					"start" => $this->get_period_date($date_start_id, 'start_date'),
					"end" => $this->get_period_date($blocking['blocking_end_date_id'], 'end_date')
				);
			}
			if($date_start_id >= $blocking['blocking_start_date_id'] && $date_end_id <= $blocking['blocking_end_date_id']) {
				$dates = array(
					"octo_roomtype_id" => $octo_roomtype_id,
					"start" => $this->get_period_date($date_start_id, 'start_date'),
					"end" => $this->get_period_date($date_end_id, 'end_date')
				);
			}
			if($date_start_id <= $blocking['blocking_start_date_id'] && $date_end_id >= $blocking['blocking_end_date_id']) {
				$dates = array(
					"octo_roomtype_id" => $octo_roomtype_id,
					"start" => $this->get_period_date($blocking['blocking_start_date_id'], 'start_date'),
					"end" => $this->get_period_date($blocking['blocking_end_date_id'], 'end_date')
				);
			}
		}
		return $dates;
	}
	
	public function decrementAvail_from_blocking($dates, $property_id, $relate_id = false) {
		$propertyId = $property_id;
		$rooms = '';
		if($relate_id == false) {
			if(count($dates) > 0) {
			foreach($dates as $date) {
					$rooms .= '<Room>';
					$rooms .= '<RoomId>' . $date['octo_roomtype_id'] . '</RoomId>';
					$rooms .= '<From>' . $date['start'] . '</From>';
					$rooms .= '<To>' . date('Y-m-d', strtotime($date['end'] . '-1 day')) . '</To>';
					$rooms .= '</Room>';
				}
			}
		} else {
			$room_id = $relate_id;
			if(count($dates) > 0) {
			foreach($dates as $date) {
					$rooms .= '<Room>';
					$rooms .= '<RoomId>' . $room_id . '</RoomId>';
					$rooms .= '<From>' . $date['start'] . '</From>';
					$rooms .= '<To>' . date('Y-m-d', strtotime($date['end'] . '-1 day')) . '</To>';
					$rooms .= '</Room>';
				}
			}
		}
		/* $check_in = $checkinDecr;
		$check_out = $checkoutDecr; */
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directdecrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<DecrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'. $rooms .'</Rooms>'.
							'</DecrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $xml_string;
	}
	
	public function incrementAvail_from_blocking($relate_id, $dates, $property_id) {
		$propertyId = $property_id;
		$rooms = '';
		if($relate_id == false) {
			if(count($dates) > 0) {
			foreach($dates as $date) {
					$rooms .= '<Room>';
					$rooms .= '<RoomId>' . $date['octo_roomtype_id'] . '</RoomId>';
					$rooms .= '<From>' . $date['start'] . '</From>';
					$rooms .= '<To>' . date('Y-m-d', strtotime($date['end'] . '-1 day')) . '</To>';
					$rooms .= '</Room>';
				}
			}
		} else {
			$room_id = $relate_id;
			if(count($dates) > 0) {
			foreach($dates as $date) {
					$rooms .= '<Room>';
					$rooms .= '<RoomId>' . $room_id . '</RoomId>';
					$rooms .= '<From>' . $date['start'] . '</From>';
					$rooms .= '<To>' . date('Y-m-d', strtotime($date['end'] . '-1 day')) . '</To>';
					$rooms .= '</Room>';
				}
			}
		}
		/* $check_in = $checkinDecr;
		$check_out = $checkoutDecr; */
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=directincrementroom";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<IncrementRoomRequest>'.
							'<Auth>'.
								'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
								'<PropertyId>'.$propertyId.'</PropertyId>'.
							'</Auth>'.
							'<Rooms>'. $rooms .'</Rooms>'.
							'</IncrementRoomRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		return $xml_string;
	}
	
	public function roomRates($res_id, $rate_type, $checkin, $checkout, $room_type_name) {
		$room_rates_value = array();
		
		$getDailyRate = $this->executeQuery("SELECT `periods_id`, `$rate_type` FROM `periods` WHERE `periods_id` >= '$checkin' AND `periods_id` <= '$checkout' ");
		$cntDailyRate = count($getDailyRate);
		
		$getPropertyId =  $this->executeQuery("SELECT * FROM hotel_property WHERE status = 'active' ");
		$id_property = $getPropertyId[0]['property_id'];
		/* if($id_property == '274690'){
			
		} */
		$sql = "SELECT `date_start_id`, `date_end_id`, `weekly_rates`, `reservation_conn_id` FROM `reservation` WHERE `reservation_id` = '$res_id'";
		$get_res_data = $this->executeQuery($sql);
		$date_start_id = $get_res_data[0]['date_start_id'];
		$date_end_id = $get_res_data[0]['date_end_id'];
		$reservation_conn_id = $get_res_data[0]['reservation_conn_id'];
		//$cnt_length = ($date_end_id - $date_start_id) + 1;
		$weekly_rates = explode(",",$get_res_data[0]['weekly_rates']);
		$current_rates = array();
		for($x = 0; $x < count($weekly_rates); $x++){
			$tmp_id = $date_start_id + $x;
			$current_rates[$tmp_id] = $weekly_rates[$x];
		}
		
		$comma = ",";
		$combineText = "";
		$total_amt = 0;
		for($ss = 0; $ss < $cntDailyRate; $ss++){
			$room_amt = $getDailyRate[$ss][$rate_type];
			//--
			if( isset($current_rates[ $getDailyRate[$ss]['periods_id'] ]) ){ // check if it has an old rate
				$sql = "SELECT `bookingsource_id` FROM `reservation_conn` WHERE `reservation_conn_id` = '$reservation_conn_id'";
				$get_res_conn_data = $this->executeQuery($sql);
				if($get_res_conn_data[0]['bookingsource_id'] != 5 && $get_res_conn_data[0]['bookingsource_id'] != 11){
					$room_amt = $current_rates[$getDailyRate[$ss]['periods_id']];
				}
			}
			//$room_amt = $current_rates['964'];
			//--
			if($ss == $cntDailyRate - 1){
				$comma = "";
			}
			$combineText .= $room_amt."".$comma;
			
			$total_amt = $total_amt + $room_amt;//$total_amt + $getDailyRate[$ss][$rate_type];
		}
		$rateName = $room_type_name."#@&".$total_amt;
				
		$room_rates_value[0]['combineText'] = $combineText;
		$room_rates_value[0]['total_amt'] = $total_amt;
		$room_rates_value[0]['rateName'] = $rateName;
		
		return $room_rates_value;
	}
	
	public function roomReservtionCost($res_id) {

		$getReservationCost = $this->executeQuery("SELECT * FROM reservation_costs WHERE `reservation_id` = '$res_id' ");
		$costCnt = count($getReservationCost);
		$totalCost = 0;
		for($ee = 0; $ee < $costCnt; $ee++){
			if($getReservationCost[$ee]['type'] == 'sg'){
				$totalCost = $totalCost + ($getReservationCost[$ee]['value'] * $getReservationCost[$ee]['quantity']);
			}
			if($getReservationCost[$ee]['type'] == 'dl'){
				$totalCost = $totalCost + (($getReservationCost[$ee]['value'] * $getReservationCost[$ee]['days']) * $getReservationCost[$ee]['quantity']);
			}
		}

		return $totalCost;
	}
	
	public function update_room_split($res_id, $user_id, $action) {
		$sql = "UPDATE `room_split`
				SET `isSettled` = :isSettled, `settled_by` = :settled_by, `settled_date` = NOW() + INTERVAL :diff_time HOUR, `action` = :action
				WHERE `reservation_id` = :res_id";
		$sql_data = array(
				':isSettled' => "Yes",
				':settled_by' => $user_id,
				':diff_time' => C_DIFF_ORE,
				':action' => $action,
				':res_id' => $res_id
			);
		$this->execute_insert($sql, $sql_data);
	}
	
	public function transfer_booking($booking, $id_property){
		$output = 'not exist blocking';
		$hotel_id = $id_property;
		$reservation = $booking;
		
		$bb_id = $reservation[0]['bb_id'];
		$roomColumnNew = $reservation[0]['current_octo_room_type_id'];
		
		$clientFname = $reservation[0]['name'];
		$clientLname = $reservation[0]['surname'];
		$clientTele = $reservation[0]['telepone'];
		$clientNation = $reservation[0]['nation'];
		$checkin_final = $reservation[0]['checkin_date'];
		$checkout_final = $reservation[0]['checkout_date'];
		$roomColumnNew = $reservation[0]['current_octo_room_type_id'];
		$roomColumnOld = $reservation[0]['previous_octo_room_type_id'];
		$rate_total = $reservation[0]['rate_total'];
		$pax = $reservation[0]['pax'];
		$room_id = $reservation[0]['apartment_id'];
		$new_room_id = $reservation[0]['new_apartment_id'];
		
		/* check blocking for new rooom */
		$date_start_id = $this->getPeriodID($checkin_final, 'start');
		$date_end_id = $this->getPeriodID($checkout_final, 'end');
		$blocked_period = $this->get_blocked_period($new_room_id, $date_start_id, $date_end_id);
		for($x=0; $x<count($blocked_period); $x++){
			# execute : incrementAvailability($relate_id,$property_id,$checkinDecr,$checkoutDecr)
			$getOctoRoom = $this->executeQuery("SELECT `octorate_roomtype`.`idroomtype_octo` As octo_id FROM `apartments` LEFT JOIN `octorate_roomtype` on `octorate_roomtype`.`roomtype_id` = `apartments`.`roomtype_id` WHERE `apartments`.`apartment_id` = '".$new_room_id."'");
			$room_id_octo = $getOctoRoom[0]["octo_id"];
			$date_start = $this->get_period_date($blocked_period[$x]['date_start_id'], 'start_date');
			$date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date');
			$this->incrementAvailability($room_id_octo, $hotel_id, $date_start, $date_end);
		}
		/* end check blocking for new rooom */
		
		/* check blocking */
		$date_start_id = $this->getPeriodID($checkin_final, 'start');
		$date_end_id = $this->getPeriodID($checkout_final, 'end');
		$sql = "SELECT a.* FROM blocking a WHERE a.status = 'active' AND a.appartment_id = '$room_id' AND
				(((a.blocking_start_date_id <= '$date_start_id' AND a.blocking_end_date_id >= '$date_start_id') OR 
				(a.blocking_start_date_id <= '$date_end_id' AND a.blocking_end_date_id >= '$date_end_id')) OR 
				((a.blocking_start_date_id >= '$date_start_id' AND a.blocking_start_date_id <= '$date_end_id') OR 
				(a.blocking_end_date_id >= '$date_start_id' AND a.blocking_end_date_id <= '$date_end_id')))";
		$existing_blocking = $this->executeQuery($sql);
		if(count($existing_blocking) > 0) {
			$output = 'exist blocking';
		}
		/* check blocking */
		
		if($reservation[0]['previous_room_type_id'] != $reservation[0]['current_room_type_id']){
			if($reservation[0]['transferStatus'] == 'not_checkin'){
				$url = "https://www.octorate.com/api/live/callApi.php?method=assignreservationroom";
				$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
								  '<UpdateResvRoomRequest>'.
								  '<Auth>'.
										'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
										'<PropertyId>'.$hotel_id.'</PropertyId>'.
								  '</Auth>'.
									  '<UpdateResaRoom>'.
											 '<ResvId>'.$bb_id.'</ResvId>'.
											 '<RoomId>'.$roomColumnNew.'</RoomId>'.
									  '</UpdateResaRoom>'.
								 '</UpdateResvRoomRequest>';

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
				$data = curl_exec($ch);
				curl_close($ch);
				
				$new_date_checkout = date('Y-m-d', strtotime($checkout_final .' -1 day'));
				if($output == 'exist blocking') {
					$dates = $this->get_dates_from_include_blocking($existing_blocking, $date_start_id, $date_end_id, $roomColumnOld);
					$xml = $this->incrementAvail_from_blocking($roomColumnOld, $dates, $hotel_id);
				}
				if($output == 'not exist blocking') {
					$this->incrementAvail($roomColumnOld,$checkin_final,$new_date_checkout,$hotel_id); /* Issue HTL-352 fix */
				}
				$this->decrementAvail($roomColumnNew,$checkin_final,$new_date_checkout,$hotel_id);
			}else if($reservation[0]['transferStatus'] == 'checkin'){
				$url = "https://www.octorate.com/api/live/callApi.php?method=assignreservationroom";
				$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
								  '<UpdateResvRoomRequest>'.
								  '<Auth>'.
										'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
										'<PropertyId>'.$hotel_id.'</PropertyId>'.
								  '</Auth>'.
									  '<UpdateResaRoom>'.
											 '<ResvId>'.$bb_id.'</ResvId>'.
											 '<RoomId>'.$roomColumnNew.'</RoomId>'.
									  '</UpdateResaRoom>'.
								 '</UpdateResvRoomRequest>';

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
				$data = curl_exec($ch);
				curl_close($ch);
				
				$new_date_checkout = date('Y-m-d', strtotime($checkout_final .' -1 day'));
				
				//$this->add_booking_extend($clientFname,$clientLname,$clientTele,$clientNation,$rate_total,$pax,$checkin_final,$checkout_final,$roomColumnNew,$hotel_id);
				/* if($output == 'not exist blocking') {
					$this->decrementAvail($roomColumnOld,$checkin_final,$new_date_checkout,$hotel_id);
				} */
				
				$this->decrementAvail($roomColumnNew,$checkin_final,$new_date_checkout,$hotel_id);
				$this->incrementAvail($roomColumnOld,$checkin_final,$new_date_checkout,$hotel_id);
				
				//$this->add_booking_extend($clientFname,$clientLname,$clientTele,$clientNation,$rate_total,$pax,$checkin_final,$checkout_final,$roomColumnNew,$hotel_id);
				//$this->incrementAvail($roomColumnOld,$checkin_final,$new_date_checkout,$hotel_id);
			}
			return $xml;
		}
	}
	
	public function editperiod_splitrooms($reservation_data, $selected_start_id, $selected_end_id){
		$res_id = $reservation_data['reservation_id'];
		$sql = "SELECT MIN(`date_start_id`) As date_start_id, MAX(`date_end_id`) As date_end_id FROM `reservation` WHERE (`reservation_id` = '$res_id' OR `split_from` = '$res_id') AND `status` = 'active'";
		$get_res_room = $this->executeQuery($sql);
		$current_start_id = $get_res_room[0]['date_start_id'];
		$current_end_id = $get_res_room[0]['date_end_id'];

		if($current_end_id <= $selected_end_id){  // Extended // old cond: $current_end_id <= $selected_end_id && $current_start_id >= $selected_start_id
			$sql = "SELECT `reservation_id`, `reservation_conn_id`, `date_start_id`, `date_end_id`, `pax`, `appartments_id`
					FROM `reservation`
					WHERE `split_from` = '$res_id' AND `status` = 'active' ORDER BY `date_end_id` DESC LIMIT 1"; //`appartments_id`, COALESCE(`discount`, 0) As discount, COALESCE(`deposit`, 0) As deposit, COALESCE(`commissions`, 0) As commissions, COALESCE(`paid`, 0) As paid, 
			$get_res = $this->executeQuery($sql);
			$room_res_id = $get_res[0]["reservation_id"];
			$room_res_conn_id = $get_res[0]["reservation_conn_id"];
			$room_id = $get_res[0]["appartments_id"];
			$room_date_start_id = $get_res[0]["date_start_id"];
			$room_date_end_id = $get_res[0]["date_end_id"];
			$room_pax = $get_res[0]["pax"];
			
			$getRoomDetails = $this->executeQuery("SELECT `apartments`.`apartment_id`, `apartments`.`apartment_name`, `apartments`.`roomtype_id`, `room_types`.`name`, `room_types`.`associated_column` FROM `apartments` LEFT JOIN `room_types` ON `apartments`.roomtype_id = `room_types`.room_type_id WHERE `apartments`.`apartment_id` = '$room_id' ");
			$rate_type = $getRoomDetails[0]['associated_column'];
			$current_room_type_name = $getRoomDetails[0]['name'];
								
			$rate_pax = $rate_type;
			$column_rate = $rate_type."_pax_".$room_pax;
			$column_is_exist = $this->executeQuery("SHOW COLUMNS FROM `periods` LIKE '$column_rate'");
			if(count($column_is_exist) == 0){
				$rate_pax = $rate_type;
			}else{
				$rate_pax = $column_rate;
			}

			$room_rates = $this->roomRates($room_res_id, $rate_pax, $room_date_start_id, $selected_end_id, $current_room_type_name);
			$room_total_cost = $this->roomReservtionCost($room_res_id);
			$rate_total = $room_rates[0]['total_amt'] + $room_total_cost;
			
			$sqlReservPeriod = "UPDATE `reservation`
								SET `date_start_id`=:date_start_id,
									`date_end_id`=:date_end_id,
									`rate`=:rate,
									`weekly_rates`=:weekly_rates,
									`rate_total`=:rate_total
								WHERE `reservation_conn_id`=:reservation_conn_id AND
									  `reservation_id`=:reservation_id";
			$reservPeriodUpdate = array(
									':date_start_id' => $room_date_start_id,
									':date_end_id' => $selected_end_id,
									':rate' => $room_rates[0]['rateName'],
									':weekly_rates' => $room_rates[0]['combineText'],
									':rate_total' => $rate_total,
									':reservation_conn_id' => $room_res_conn_id,
									':reservation_id' => $room_res_id
								);
			$this->execute_insert($sqlReservPeriod,$reservPeriodUpdate);
		}
		else{ // Shortened
			$this->shortenperiod_splitrooms($reservation_data, $selected_start_id, $selected_end_id);
		}

	}
	//orig space
	private function shortenperiod_splitrooms($reservation_data, $selected_start_id, $selected_end_id){
		$weekly_rates_sql = "SELECT weekly_rates FROM reservation WHERE reservation_id = :reservation_id";
		$weekly_rates_stmt = $this->db->prepare($weekly_rates_sql);		
		
		$update_date_end_sql = "UPDATE reservation SET rate = REPLACE(rate, :old_rate, :new_rate), weekly_rates = :weekly_rates, rate_total = :rate_total, date_end_id = :date_end_id WHERE reservation_id = :reservation_id";
		$update_date_end_stmt = $this->db->prepare($update_date_end_sql);

		$inactiveResSQL = "UPDATE reservation SET `status` = :status WHERE reservation_id = :reservation_id";
		$inactiveResStmt = $this->db->prepare($inactiveResSQL);

		$list_rooms = $reservation_data['list_rooms'];
		array_push($list_rooms, $reservation_data['reservation_id']);
		$list_rooms_cnt = count($list_rooms);

		$deactivated = array();

		$end_date_replaced = false;

		for($x=0; $x<$list_rooms_cnt; $x++){

			$split_res = $this->rooms_details($list_rooms[$x]);

			/*
			echo $split_res['date_start_id'] . " >= " . $selected_start_id 
				. " && " . $split_res['date_end_id'] . " > " . $selected_end_id
				. " && " . $selected_end_id . " >= " . $split_res['date_start_id'];
			*/

			if( $split_res['date_start_id'] >= $selected_start_id 
				&& $split_res['date_end_id'] > $selected_end_id
				&& $selected_end_id >= $split_res['date_start_id'] ) { // replace with new end date

				$end_date_replaced = true;

				$removed_days = $split_res['date_end_id'] - $selected_end_id;

				$weekly_rates_stmt->execute( array( ':reservation_id' => $split_res['reservation_id'] ) );
				$ret = $weekly_rates_stmt->fetchAll( PDO::FETCH_ASSOC );

				$weekly_rates_arr = explode( ",", $ret[0]['weekly_rates'] );
				$old_weekly_rates = 0;
				foreach($weekly_rates_arr as $weekly_rate){
					$old_weekly_rates += 1*$weekly_rate;
				}

				array_splice($weekly_rates_arr, -$removed_days);
				$new_weekly_rates = 0;
				foreach($weekly_rates_arr as $weekly_rate){
					$new_weekly_rates += 1*$weekly_rate;
				}

				$room_total_cost = $this->roomReservtionCost($split_res['reservation_id']);

				$update_date_end_stmt->execute( 
					array(
						':old_rate' => $old_weekly_rates,
						':rate_total' => $new_weekly_rates + $room_total_cost,
						':new_rate' => $new_weekly_rates,
						':weekly_rates' => implode(",", $weekly_rates_arr),
						':date_end_id' => $selected_end_id,
						':reservation_id' =>  $split_res['reservation_id']
					)
				);
			} else if( $selected_end_id < $split_res['date_start_id']  ) { // set status to inactive
				array_push($deactivated, $split_res['reservation_id']);
				
				$inactiveResStmt->execute( // change reservation status to inactive
					array(
						':reservation_id' => $split_res['reservation_id'],
						':status' => 'inactive'
					)
				); 
			} 
		}

		// recalculate cost commission, deposit, discount if a split is deactivated or end date's replaced
		if( count($deactivated) > 0 || $end_date_replaced == true ){ 
			$this->recalculateSplitCost($reservation_data['list_rooms'], $reservation_data['reservation_id'], $deactivated);
		}
	}

	private function recalculateSplitCost($splitted, $reservation_id, $deactivated){
		$activeSplitRes = array_diff($splitted,$deactivated);
		$activeSplitRes_cnt = count($activeSplitRes);

		$getCostSql = "SELECT SUM(discount) tot_discount, SUM(deposit) tot_deposit, SUM(commissions) tot_commissions, SUM(paid) tot_paid 
				FROM reservation WHERE reservation_id = " . $reservation_id;
		$splitted_cnt = count($splitted);
		for($x=0; $x<$splitted_cnt; $x++){
			$getCostSql .= " OR reservation_id = " . $splitted[$x];
		}

		$getCost = $this->executeQuery($getCostSql);

		$updateSplitCostsql = "UPDATE reservation
			SET 
				discount = :discount,
				deposit = :deposit,
				commissions = :commissions,
				paid = :payment_value					
			WHERE 
				reservation_id = :reservation_id";

		$updateSplitCost = $this->db->prepare($updateSplitCostsql);
		
		$payment_value = $getCost[0]['tot_paid'];
		$discount = $getCost[0]['tot_discount'] / ( $activeSplitRes_cnt + 1 );
		$deposit = $getCost[0]['tot_deposit'] / ( $activeSplitRes_cnt + 1 );
		$commissions =  $getCost[0]['tot_commissions'] / ( $activeSplitRes_cnt + 1 );

		for($x=$activeSplitRes_cnt - 1; $x>=0; $x--){ // split payment to rooms
		
			$splitted_room = $this->rooms_details($activeSplitRes[$x]);
			$room_total_cost = $this->roomReservtionCost($activeSplitRes[$x]);

			$max_payment_for_split = $splitted_room["rate_total"] + $room_total_cost - $discount;

			if( $payment_value > $max_payment_for_split ) {
				$split_payment_value = $max_payment_for_split;
				$payment_value = $payment_value - $max_payment_for_split;
			} else {
				$split_payment_value = $payment_value;
				$payment_value = 0;
			}
			
			$updateSplitCost->execute(
				array( 
					':reservation_id' =>  $splitted_room['reservation_id'],
					':discount' => $discount,
					':deposit' => $deposit,
					':commissions' => $commissions,
					':payment_value' => $split_payment_value
				)
			);
		}

		$updateSplitCost->execute(
			array( 
				':reservation_id' => $reservation_id,
				':discount' => $discount,
				':deposit' => $deposit,
				':commissions' => $commissions,
				':payment_value' => $payment_value
			)
		);
		
	}

	public function editPeriod2($booking,$id_property){
		$hotel_id = $id_property;
		$reservation = $booking;
		$octo_room_id = $reservation['octo_room_id'];
		$octo_room_id_related = $reservation['octo_room_id_related'];
		$old_checkin = $reservation['old_checkin'];
		$new_checkin = $reservation['new_checkin'];
		$old_checkout = $reservation['old_checkout'];
		$new_checkout = $reservation['new_checkout'];
		$room_id = $reservation['apartment_id'];
		
		$name = $reservation['name'];
		$surname = $reservation['surname'];
		
		$clientTele = $reservation['phone'];
		$clientNation = $reservation['nation'];
		$rate_total = $reservation['rate_total'];
		$pax = $reservation['pax'];
		
		$bb_id = $reservation['chnnl_manager_id_res'];
		$reservation_id = $reservation['reservation_id'];
		$edit_type = $reservation['edit_type'];
		
		$dateToday = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
		/* check booking source */
		$r_1_decode = $reservation['reservation_id'];
		$req = $this->executeQuery("SELECT b.bookingsource_id, a.reservation_id, a.reservation_conn_id FROM reservation a 
				LEFT JOIN reservation_conn b ON b.reservation_conn_id = a.reservation_conn_id
				WHERE a.reservation_id = '$r_1_decode'");
		$booking_source_id = $req[0]['bookingsource_id'];
		if($booking_source_id == 5 || $booking_source_id == 9 || $booking_source_id == 11) {
			if($dateToday > $new_checkin && $dateToday <= $new_checkout) {
				if($old_checkin < $new_checkin){
					$new_date_checkin = date('Y-m-d', strtotime($new_checkin .' -1 day'));
					$this->incrementAvail($octo_room_id,$old_checkin,$new_date_checkin,$hotel_id);
					
				}else if($old_checkin > $new_checkin){
					$new_old_date_checkout = date('Y-m-d', strtotime($old_checkin .' -1 day'));
					$this->decrementAvail($octo_room_id,$new_checkin,$new_old_date_checkout,$hotel_id);
				}
				
				if($old_checkout < $new_checkout){
					$this->add_booking_extend($name,$surname,$clientTele,$clientNation,$rate_total,$pax,$old_checkout,$new_checkout,$octo_room_id,$hotel_id);
					
					//HTL-773 Fix
					$start_id = $this->getPeriodID($old_checkout, 'start');
					$end_id = $this->getPeriodID($new_checkout, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->incrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}else if($old_checkout > $new_checkout){
					$old_date_checkout = date('Y-m-d', strtotime($old_checkout .' -1 day'));
					$this->incrementAvail($octo_room_id,$new_checkout,$old_date_checkout,$hotel_id);
					
					//HTL-771 Fix
					$start_id = $this->getPeriodID($new_checkout, 'start');
					$end_id = $this->getPeriodID($old_checkout, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->decrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}
			} else {
				//Walk In
				$old_bookings_cancelled = array('chnnl_manager_id_res' => $bb_id, 'date_start_id' => $old_checkin, 'date_end_id' => $old_checkout);
				$this->octorate_cancelled($old_bookings_cancelled, $id_property);
				
				$new_bookings_added = array('reservation_id' => $reservation_id, 'surname' => $surname, 'name' => $name, 'telepone' => $clientTele, 'nationality' => $clientNation, 'date_start_id' => $new_checkin, 'date_end_id' => $new_checkout, 'idroomtype_octo' => $octo_room_id, 'pax' => $pax, 'rate_total' => $rate_total);
				$this->add_octorate_booking_2($new_bookings_added, $id_property);
				
				if($old_checkin < $new_checkin){
					$start_id = $this->getPeriodID($old_checkin, 'start');
					$end_id = $this->getPeriodID($new_checkin, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->decrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}else if($old_checkin > $new_checkin){
					$start_id = $this->getPeriodID($new_checkin, 'start');
					$end_id = $this->getPeriodID($old_checkin, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->incrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}
				
				if($old_checkout < $new_checkout){
					//HTL-773 Fix
					$start_id = $this->getPeriodID($old_checkout, 'start');
					$end_id = $this->getPeriodID($new_checkout, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->incrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}else if($old_checkout > $new_checkout){
					$old_date_checkout = date('Y-m-d', strtotime($old_checkout .' -1 day'));
					//HTL-771 Fix
					$start_id = $this->getPeriodID($new_checkout, 'start');
					$end_id = $this->getPeriodID($old_checkout, 'end');
					$block_periods = $this->get_blocked_period($room_id, $start_id, $end_id);
					for($x=0; $x<count($block_periods); $x++){
						$date_start = $this->get_period_date($block_periods[$x]['date_start_id'], 'start_date');
						$date_end = $this->get_period_date($block_periods[$x]['date_end_id'], 'start_date');
						$this->decrementAvailability($octo_room_id, $hotel_id, $date_start, $date_end);
					}
				}
			}
		} else {
			//OTA
			if($old_checkin < $new_checkin){
				$new_date_checkin = date('Y-m-d', strtotime($new_checkin .' -1 day'));
				$this->incrementAvail($octo_room_id,$old_checkin,$new_date_checkin,$hotel_id);
				
			}else if($old_checkin > $new_checkin){
				$new_old_date_checkout = date('Y-m-d', strtotime($old_checkin .' -1 day'));
				$this->decrementAvail($octo_room_id,$new_checkin,$new_old_date_checkout,$hotel_id);
			}
			
			if($old_checkout < $new_checkout){
				$this->add_booking_extend($name,$surname,$clientTele,$clientNation,$rate_total,$pax,$old_checkout,$new_checkout,$octo_room_id,$hotel_id);
			}else if($old_checkout > $new_checkout){
				$old_date_checkout = date('Y-m-d', strtotime($old_checkout .' -1 day'));
				$this->incrementAvail($octo_room_id,$new_checkout,$old_date_checkout,$hotel_id);
			}
		}
		/* end of check booking source */
		
		return $existing_blocking;
	}
	
	public function add_octorate_booking_2($booking, $id_property){
		$reservation = $booking;
		
		$resv_id = $reservation['reservation_id'];
		$lname = $reservation['surname'];
		$fname = $reservation['name'];
		$telepone = $reservation['phone'];
		$nation = $reservation['nationality'];
		$check_in = $reservation['date_start_id'];
		$check_out = $reservation['date_end_id'];
		$room_id = $reservation['idroomtype_octo'];
		$pax = $reservation['pax'];
		$total = $reservation['rate_total'];
	
		$fullName = $fname." ".$lname;
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
						'<BookReservationRequest>'.
						  '<Auth>'.
							  '<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
							  '<PropertyId>'.$id_property.'</PropertyId>'.
						  '</Auth>'.
						  '<Reservations>'.
						  '<Reservation>'.
							  '<From>'.$check_in.'</From>'.
							  '<To>'.$check_out.'</To>'.
							  '<Rooms>'.
								  '<Room>'.
									 '<Roomid>'.$room_id.'</Roomid>'.
									 '<Pax>'.$pax.'</Pax>'.
									 '<Total>'.$total.'</Total>'.
									 '<Guestname>'.$fullName.'</Guestname>'.
									 '<Telephone>'.$telepone.'</Telephone>'.
									 '<Provenienza>'.$nation.'</Provenienza>'.
									 '<Status>Confirmed</Status>'.
								  '</Room>'.
							  '</Rooms>'.
							  '</Reservation>'.
							  '</Reservations>'.
						 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
		
		$array_data = json_decode(json_encode(simplexml_load_string($data)), true);
		
		$bb_id = $array_data['RoomUpdateMessage']['Bbliverateresvid'];
		
		$sqlBbUpdate = "UPDATE bookings SET chnnl_manager_id_res = '$bb_id' WHERE reservation_id = '$resv_id'";
		$this->execute_update($sqlBbUpdate);
		# ---- HTL-706 ------------------------
		if($bb_id != "" && $bb_id != NULL){
			#----- check room blocking ----
			/* $start_date_id = $this->get_date_id($reservation['date_start_id'], "start");
			$end_date_id = $this->get_date_id($reservation['date_end_id'], "end");
			$blocked_period = $this->get_blocked_period($reservation['appartments_id'], $start_date_id, $end_date_id);
			for($x=0; $x<count($blocked_period); $x++){
				$getOctoRoom = $this->executeQuery("SELECT `octorate_roomtype`.`idroomtype_octo` As octo_id FROM `apartments` LEFT JOIN `octorate_roomtype` on `octorate_roomtype`.`roomtype_id` = `apartments`.`roomtype_id` WHERE `apartments`.`apartment_id` = '".$reservation['appartments_id']."'");
				$room_id_octo = $getOctoRoom[0]["octo_id"];
				$date_start = $this->get_period_date($blocked_period[$x]['date_start_id'], 'start_date');
				$date_end = $this->get_period_date($blocked_period[$x]['date_end_id'], 'start_date');
				$this->incrementAvailability($room_id_octo,$id_property,$date_start,$date_end);
			} Jean Maot Nawng */
			$getOctoRoom = $this->executeQuery("SELECT `octorate_roomtype`.`idroomtype_octo` AS octo_id, apartments.roomtype_id FROM `apartments` LEFT JOIN `octorate_roomtype` ON `octorate_roomtype`.`roomtype_id` = `apartments`.`roomtype_id` WHERE `apartments`.`apartment_id` = '" . $reservation['appartments_id'] . "'");
			$roomtypeId = $getOctoRoom[0]["roomtype_id"];
			$room_id_octo = $getOctoRoom[0]['octo_id'];
			
			$max_room_request = $this->executeQuery("SELECT a.roomtype_id, COUNT(*) AS max_rooms, b.name FROM apartments a
								LEFT JOIN room_types b ON b.room_type_id = a.roomtype_id
								GROUP BY a.roomtype_id");
			foreach($max_room_request as $max_room) {
				if($max_room['roomtype_id'] == $getOctoRoom[0]["roomtype_id"]) {
					$max_rooms = $max_room['max_rooms'];
				}
			}					
			
			$start_date_id = $this->get_date_id($reservation['date_start_id'], "start");
			$end_date_id = $this->get_date_id($reservation['date_end_id'], "end");
			for($y=$start_date_id; $y<=$end_date_id; $y++) {
				$count_room_occupied = $this->executeQuery("SELECT * FROM reservation WHERE STATUS = 'active' AND appartments_id IN (SELECT apartment_id FROM apartments WHERE roomtype_id = '$roomtypeId') AND 
														(((date_start_id <= '$y' AND date_end_id >= '$y') OR 
														(date_start_id <= '$y' AND date_end_id >= '$y')) OR 
														((date_start_id >= '$y' AND date_start_id <= '$y') OR 
														(date_end_id >= '$y' AND date_end_id <= '$y')))");
				$count = $this->count_block_and_reservation($count_room_occupied, $y, $roomtypeId);
				$date = $this->get_period_date($y, 'start_date');
				$this->setAvailability($room_id_octo, $id_property, $date, $date, ($max_rooms-$count));
			}
		}
		# ---- HTL-706 ------------------------
	}
	/* ---- Room Splitting : END ------- */
	
	public function add_booking_extend($clientFname,$clientLname,$clientTele,$clientNation,$rate_total,$pax,$checkin,$checkout,$roomid,$id_property){
		$hotel_id = $id_property;
		$lname = $clientLname;
		$fname = $clientFname;
		$telepone = $clientTele;
		$nation = $clientNation;
		$check_in = $checkin;
		$check_out = $checkout;
		
		$room_id = $roomid;
		$pax = $pax;
		$total = $rate_total;

		$fullName = $fname." ".$lname;
		
		$url = "https://www.octorate.com/api/live/callApi.php?method=bookreservation";
		$xml_string = '<?xml version="1.0" encoding="UTF-8"?>'.
							'<BookReservationRequest>'.
							  '<Auth>'.
									'<ApiKey>294e1dc2b907ed0496e51572c3ef081e</ApiKey>'.
									'<PropertyId>'.$hotel_id.'</PropertyId>'.
							  '</Auth>'.
							  '<Reservations>'.
							  '<Reservation>'.
								  '<From>'.$check_in.'</From>'.
								  '<To>'.$check_out.'</To>'.
								  '<Rooms>'.
									  '<Room>'.
										 '<Roomid>'.$room_id.'</Roomid>'.
										 '<Pax>'.$pax.'</Pax>'.
										 '<Total>'.$total.'</Total>'.
										 '<Guestname>'.$fullName.'</Guestname>'.
										 '<Telephone>'.$telepone.'</Telephone>'.
										 '<Provenienza>'.$nation.'</Provenienza>'.
										 '<Status>Confirmed</Status>'.
									  '</Room>'.
								  '</Rooms>'.
								  '</Reservation>'.
								  '</Reservations>'.
							 '</BookReservationRequest>';

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "xml=" . $xml_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		$data = curl_exec($ch);
		curl_close($ch);
	}// orig space before OTA booking modification fix
	public function check_room_allocation_3($room_id, $checkin_id, $checkout_id, $idprenota){
		/*$room_is_available = true;
		
		$is_available_2 = $this->executeQuery("SELECT * FROM reservation WHERE reservation_id != '$idprenota' and status = 'active' and appartments_id = '$room_id' and (((date_start_id <= '$checkin_id' and date_end_id >= '$checkin_id') or (date_start_id <= '$checkout_id' and date_end_id >= '$checkout_id')) or ((date_start_id >= '$checkin_id' and date_start_id <= '$checkout_id') or (date_end_id >= '$checkin_id' and date_end_id <= '$checkout_id')))");
		if(count($is_available_2) > 0){
			$room_is_available = false;
		}else{
			$room_is_available = true;
		}
		
		return $room_is_available;*/
		$room_is_available = true;
		$sql = "SELECT MIN(`date_start_id`) As start_date_id, MAX(`date_end_id`) As end_date_id
				FROM `reservation`
				WHERE (`reservation_id` = '".$idprenota."' OR `split_from` = '".$idprenota."') AND `status` = 'active'";
		$res_reservation = $this->executeQuery($sql);
		if(count($res_reservation) > 0){ # panigutado lang na naa sulod para dili mo.error
			if($res_reservation[0]['end_date_id'] >= $checkout_id){ # if current date checkout is less than or equal to new checkout
				//$room_is_available = true;
			}
			else{ # if current date checkout is greater than the new checkout
				$checkin_id1 = $res_reservation[0]['end_date_id'] + 1;
				$checkout_id1 = $checkout_id;
				$sql = "SELECT * 
						FROM `reservation` 
						WHERE `status` = 'active' and 
							  `appartments_id` = '$room_id' and 
							  (((`date_start_id` <= '$checkin_id1' and `date_end_id` >= '$checkin_id1') or (`date_start_id` <= '$checkout_id1' and `date_end_id` >= '$checkout_id1')) or ((`date_start_id` >= '$checkin_id1' and `date_start_id` <= '$checkout_id1') or (`date_end_id` >= '$checkin_id1' and `date_end_id` <= '$checkout_id1')))";
				$is_available_2 = $this->executeQuery($sql);
				if(count($is_available_2) > 0){
					$room_is_available = false;
				}
			}
			if($res_reservation[0]['start_date_id'] <= $checkin_id){ # if current date checkin is greater than or equal to new checkin
				//$room_is_available = true;
			}
			else{ # if current date checkin is greater than the new checkin
				$checkin_id1 = $checkin_id;
				$checkout_id1 = $res_reservation[0]['start_date_id'] - 1;
				$sql = "SELECT * 
						FROM `reservation` 
						WHERE `status` = 'active' and 
							  `appartments_id` = '$room_id' and 
							  (((`date_start_id` <= '$checkin_id1' and `date_end_id` >= '$checkin_id1') or (`date_start_id` <= '$checkout_id1' and `date_end_id` >= '$checkout_id1')) or ((`date_start_id` >= '$checkin_id1' and `date_start_id` <= '$checkout_id1') or (`date_end_id` >= '$checkin_id1' and `date_end_id` <= '$checkout_id1')))";
				$is_available_2 = $this->executeQuery($sql);
				if(count($is_available_2) > 0){
					$room_is_available = false;
				}
			}
		}
		return $room_is_available;
	}
	public function room_split_trans_room($room_id, $reservation_id){
		$action = "";
		if($room_id != -1){ // HTL-636 fix
			$sql = "SELECT `apartment_name`
					FROM `apartments`
					WHERE `apartment_id` = '".$room_id."'";
			$get_room_info = $this->executeQuery($sql);
			$room_name = "";
			if(count($get_room_info) > 0){
				$room_name = $get_room_info[0]['apartment_name'];
			}
			$action = "Room has been moved to Room: ".$room_name;
		}
		else{
			$action = "Cancelled"; // HTL-636 fix
		}
		
		$sql = "UPDATE `room_split`
				SET `action` = '".$action."', `settled_by` = 1, `status` = 'inactive', `settled_date` = NOW() + INTERVAL ".C_DIFF_ORE." HOUR
				WHERE `reservation_id` = '".$reservation_id."' AND `status` = 'active'";
		$this->execute_update($sql);

	}
	
	public function insertResponsetoTable($reservation_id, $xml_response, $status) {
		$myDatabase = $this->db;
		$sql = "INSERT INTO push_notification_response (id, reservation_id, xml_response, status, inserted_date) 
				VALUES(:id, :reservation_id, :xml_response, :status, NOW() + INTERVAL :inserted_date HOUR)";
		$stmt = $myDatabase->prepare($sql);
		try {
			$stmt->execute(
				array(
					':id' => NULL,
					':reservation_id' => $reservation_id,
					':xml_response' => $xml_response,
					':status' => $status,
					':inserted_date' => C_DIFF_ORE
				)
			);
		} catch (PDOException $e) {
			/* $resp = array( "status"=> "error", "message" => $e->getMessage() );
			return $response->withJson( $resp )->withStatus(500); */
		}
	}
	public function get_string_between($string, $start, $end){
	    $string = ' ' . $string;
	    $ini = strpos($string, $start);
	    if ($ini == 0) return '';
	    $ini += strlen($start);
	    $len = strpos($string, $end, $ini) - $ini;
	    return substr($string, $ini, $len);
	}
	// ---- HTL-706 ------------------------
	public function get_blocked_period($room_id, $start_date_id, $end_date_id){

		$result = array();

	    $sql = "SELECT * 
	    		FROM `blocking` 
	    		WHERE `status` = 'active' AND `appartment_id` = '".$room_id."' AND 
					  (((`blocking_start_date_id` <= '".$start_date_id."' AND `blocking_end_date_id` >= '".$start_date_id."') OR 
						(`blocking_start_date_id` <= '".$end_date_id."' AND `blocking_end_date_id` >= '".$end_date_id."')) OR 
					   ((`blocking_start_date_id` >= '".$start_date_id."' AND `blocking_start_date_id` <= '".$end_date_id."') OR 
						(`blocking_end_date_id` >= '".$start_date_id."' AND `blocking_end_date_id` <= '".$end_date_id."')))"; 
		$room_blocked = $this->executeQuery($sql); # get blocked room
		if(count($room_blocked) > 0){ # check if the room($room_id) was blocked
			# blocked
			$res_ind = 0;

			$cnt = ($end_date_id - $start_date_id) + 1;
			$temp_date_id = $start_date_id;
			for($x=0; $x<$cnt; $x++){
				$is_blocked = 0;
				for($y=0; $y<count($room_blocked); $y++){
					if($temp_date_id >= $room_blocked[$y]["blocking_start_date_id"] && $temp_date_id <= $room_blocked[$y]["blocking_end_date_id"]){
						if(isset($result[$res_ind]["date_start_id"])){
							if($result[$res_ind]["date_start_id"] != "" && $result[$res_ind]["date_start_id"] != null){
								$result[$res_ind]["date_end_id"] = $temp_date_id;
							}
						}
						else{
							$result[$res_ind]["date_start_id"] = $temp_date_id;
							$result[$res_ind]["date_end_id"] = $temp_date_id;
							/*if($x==$cnt-1){
								$result[$res_ind]["date_end_id"] = $temp_date_id;
							}*/
						}
						$is_blocked++;
						break;
					}
				}
				$cnt1 = count($result);
				if($is_blocked == 0 && isset($result[$cnt1-1]["date_end_id"])){
					if($result[$cnt1-1]["date_end_id"] == ($temp_date_id-1)){
						$res_ind++;
					}
				}
				$temp_date_id++;
			}
		}
		else{
			# available
		}
		return $result;

	}
  public function get_reservation_period($room_id, $start_date_id, $end_date_id){

		$result = array();

	    $sql = "SELECT * 
	    		FROM `reservation` 
	    		WHERE `status` = 'active' AND `appartments_id` = '".$room_id."' AND 
					  (((`date_start_id` <= '".$start_date_id."' AND `date_end_id` >= '".$start_date_id."') OR 
						(`date_start_id` <= '".$end_date_id."' AND `date_end_id` >= '".$end_date_id."')) OR 
					   ((`date_start_id` >= '".$start_date_id."' AND `date_start_id` <= '".$end_date_id."') OR 
						(`date_end_id` >= '".$start_date_id."' AND `date_end_id` <= '".$end_date_id."')))"; 
		$room_blocked = $this->executeQuery($sql); # get blocked room
		if(count($room_blocked) > 0){ # check if the room($room_id) was blocked
			# blocked
			$res_ind = 0;

			$cnt = ($end_date_id - $start_date_id) + 1;
			$temp_date_id = $start_date_id;
			for($x=0; $x<$cnt; $x++){
				$is_blocked = 0;
				for($y=0; $y<count($room_blocked); $y++){
					if($temp_date_id >= $room_blocked[$y]["date_start_id"] && $temp_date_id <= $room_blocked[$y]["date_end_id"]){
						if(isset($result[$res_ind]["date_start_id"])){
							if($result[$res_ind]["date_start_id"] != "" && $result[$res_ind]["date_start_id"] != null){
								$result[$res_ind]["date_end_id"] = $temp_date_id;
							}
						}
						else{
							$result[$res_ind]["date_start_id"] = $temp_date_id;
							$result[$res_ind]["date_end_id"] = $temp_date_id;
							/*if($x==$cnt-1){
								$result[$res_ind]["date_end_id"] = $temp_date_id;
							}*/
						}
						$is_blocked++;
						break;
					}
				}
				$cnt1 = count($result);
				if($is_blocked == 0 && isset($result[$cnt1-1]["date_end_id"])){
					if($result[$cnt1-1]["date_end_id"] == ($temp_date_id-1)){
						$res_ind++;
					}
				}
				$temp_date_id++;
			}
		}
		else{
			# available
		}
		return $result;

	}
	public function set_minimum_stay($checkin_date, $checkout_date) {
		$startDate = date_format($checkin_date,"Y-m-d");
		$endDate = date_format($checkout_date,"Y-m-d");
		$startDate_id = $this->getPeriodID($startDate, 'start');
		$endDate_id = $this->getPeriodID($endDate, 'start');
		$periods = array(
			array(
				"start" => $this->getPeriodID('2018-12-20', 'start'),
				"end" => $this->getPeriodID('2019-01-05', 'end'),
				"min" => 7
			) 
		);
		$naa = false;
		$accept = false;
		$message = '';
		foreach($periods as $period) {
			if(($period["start"] <= $startDate_id && $period["end"] >= $startDate_id) || ($period["start"] <= $endDate_id && $period["end"] >= $endDate_id) || ($period["start"] >= $startDate_id && $period["start"] <= $endDate_id) || ($period["end"] >= $startDate_id && $period["end"] <= $endDate_id)) {
				$naa = true;
				break;
			}
		}
		
		if($naa == true) {
			foreach($periods as $period) {
				if(($period["start"] <= $startDate_id && $period["end"] >= $startDate_id) || ($period["start"] <= $endDate_id && $period["end"] >= $endDate_id) || ($period["start"] >= $startDate_id && $period["start"] <= $endDate_id) || ($period["end"] >= $startDate_id && $period["end"] <= $endDate_id)) {
					$count = -1;
					for($x=$startDate_id; $x<=$endDate_id; $x++) {
						/* if($x >= $period["start"] && $x <= $period["end"]) { */
							$count++;
							/* echo $count; */
						/* } */
					}
					if($count >= $period["min"]) {
						$accept = true;
						break;
					} else {
						$accept = false;
						$message = "We are sorry, we have a minimum stay requirement over these dates of " . $period["min"] . " nights. Please select a range of at least " . $period["min"] . " nights.";
					}
				}
			}
		} else {
			$accept = true;
		}
		return array(
			'success' => $accept,
			'message' => $message
		);
	}
	public function BBID_exist($reservation_data) {
		$rooms = $reservation_data['Bookings']['Booking']['Rooms']['Room'];
		$exist = false;
		
		if(isset($rooms[0])) {
			foreach($rooms as $room) {
				$bb_id = $room['BbliverateNumberId'];
				$sql = "SELECT * FROM `bookings` WHERE chnnl_manager_id_res = '$bb_id'";
				$bb_id_request = $this->executeQuery($sql);
				
				if(count($bb_id_request) > 0) {
					$exist = true;
				} else {
					$exist = false;
				}
			}
		} else {
			$bb_id = $rooms['BbliverateNumberId'];
			$sql = "SELECT * FROM `bookings` WHERE chnnl_manager_id_res = '$bb_id'";
			$bb_id_request = $this->executeQuery($sql);
			
			if(count($bb_id_request) > 0) {
				$exist = true;
			} else {
				$exist = false;
			}
		}
		return $exist;
	}
	// ---- HTL-706 ------------------------

	# AWEN INTEGRATION : Start ------------------------------------------------------------------------------------------------------------------------

	public function create_invoice_awen($res_id, $cashbox_id) {
		$date_today = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));

		try{
			$result;
			# awen connection --------------------
			$sql = "SELECT *
					FROM `global_variables`
					WHERE `key` = 'awen_api_auth_uname' OR `key` = 'awen_api_auth_pword' OR `key` = 'awen_account_id_htl_cashbox' OR `key` = 'awen_url' OR `key` = 'currency' OR `key` = 'awen_category_id_htl_price_section' OR `key` = 'awen_category_id_htl_room_price' OR `key` = 'awen_account_id_htl_bkng_src' OR `key` = 'awen_company_id'";
			$awen_conn = $this->executeQuery($sql);
			$awen_company_id = 0;
			$username = '';
			$password = '';
			$account_id = 1;
			$url = '';
			$currency = '';
			$awen_category_id_htl_price_section = array();
			$awen_category_id_htl_room_price = 0;
			$bkng_src_ids;
			for($x=0; $x<count($awen_conn); $x++){
				if($awen_conn[$x]['key'] == 'awen_api_auth_uname') $username = $awen_conn[$x]['value'];
				if($awen_conn[$x]['key'] == 'awen_api_auth_pword') $password = $awen_conn[$x]['value'];
				if($awen_conn[$x]['key'] == 'awen_account_id_htl_cashbox') {
					$findme = "(".$cashbox_id.":";
					$cashbox_ids = explode(",",$awen_conn[$x]['value']);
					for($y=0; $y<count($cashbox_ids); $y++){
						$pos = strpos($cashbox_ids[$y], $findme);
						if ($pos === true) {
							$cashb = $cashbox_ids[$y];
						   	$cashb = str_replace("(","",$cashb);
						   	$cashb = str_replace(")","",$cashb);
						   	$cashb_id = explode(":",$awen_conn[$x]['value']);
						   	$account_id = $cashb_id[1];
						   	break;
						}
					}
				}
				if($awen_conn[$x]['key'] == 'awen_url') $url = $awen_conn[$x]['value'];
				if($awen_conn[$x]['key'] == 'currency') {
					$curr = explode(",",$awen_conn[$x]['value']);
					$currency = $curr[1];
				} 
				if($awen_conn[$x]['key'] == 'awen_category_id_htl_price_section') {
					$temp = explode(",",$awen_conn[$x]['value']);
					foreach ($temp as $value) {
						$temp1 = explode(":",$value);
					    $awen_category_id_htl_price_section[$temp1[0]] = $temp1[1];
					}
				} 
				if($awen_conn[$x]['key'] == 'awen_category_id_htl_room_price') $awen_category_id_htl_room_price = $awen_conn[$x]['value'];
				if($awen_conn[$x]['key'] == 'awen_account_id_htl_bkng_src') $bkng_src_ids = explode(",",$awen_conn[$x]['value']);
				if($awen_conn[$x]['key'] == 'awen_company_id') $awen_company_id = $awen_conn[$x]['value'];
			}
			# awen connection --------------------

			$sql = "SELECT res.*, (SELECT `start_date` FROM `periods` WHERE `periods_id` = res.`date_start_id`) As checkin_date, (SELECT `end_date` FROM `periods` WHERE `periods_id` = res.`date_end_id`) As checkout_date, rooms.`apartment_name` As room_name, room_types.`name` As room_type, clients.`name` As c_name, clients.`surname` As c_surname, clients.`email` As c_email, res_conn.`reference_num` As ref_num, rooms.`roomtype_id` As room_type_id, bkng_src.`cost_comm`, bkng_src.`comm_type`, bkng_src.`deposit_status`, bkng_src.`commission_paid`, bkng_src.`booking_source_id`
					FROM `reservation` As res 
					LEFT JOIN `apartments` As rooms ON res.`appartments_id` = rooms.`apartment_id` 
					LEFT JOIN `room_types` As room_types ON rooms.`roomtype_id` = room_types.`room_type_id`
					LEFT JOIN `clients` As clients ON res.`clients_id` = clients.`clients_id`
					LEFT JOIN `reservation_conn` As res_conn ON res.`reservation_conn_id` = res_conn.`reservation_conn_id`
					LEFT JOIN `booking_source` As bkng_src ON res_conn.`bookingsource_id` = bkng_src.`booking_source_id`
					WHERE (res.`reservation_id` = '".$res_id."' OR res.`split_from` = '".$res_id."') AND (res.`status` != 'inactive' AND res.`status` != '')";
			$res_data = $this->executeQuery($sql);
			$cnt_res_data = count($res_data);

			$total_paid = 0;

			for($x=0; $x<$cnt_res_data; $x++){
				$reservation_id = $res_data[$x]["reservation_id"];
				if($res_data[$x]["paid"] != null || $res_data[$x]["paid"] != ''){
					$total_paid += $res_data[$x]["paid"];
				}
				# client
				$client_fname = $res_data[$x]["c_name"];
				$client_lname = $res_data[$x]["c_surname"];
				$client_email = $res_data[$x]["c_email"];
				if($client_email == NULL || $client_email == ''){
					$client_email = "";
				}
				# check client existence
				// $request_url = $url."/api/customers/".$client_fname."___".$client_lname;
				$request_url = $url."/api/customers/".base64_encode($client_fname."___".$client_lname.'&#'.$client_email);
				$client_match = $this->execute_curl_wth_bscAuth("GET", $username, $password, $request_url, '');

				if(!isset($client_match['data']['id'])){
					if($client_email == NULL || $client_email == ''){
						$client_email = "none@email.none";
					}
					$post_fields = array(
								'company_id' 	=> $awen_company_id,
								'user_id'  	 	=> '',
								'name'       	=> $client_fname." ".$client_lname,
								'email'			=> $client_email,
								'tax_number'    => NULL,
								'phone'			=> NULL,
								'address'		=> NULL,
								'website'		=> NULL,
								'currency_code'	=> $currency,
								'enabled'		=> 1
							   );
					$request_url = $url."/api/customers/import";
					$added_client = $this->execute_curl_wth_bscAuth("POST", $username, $password, $request_url, $post_fields);

					if($client_email == NULL || $client_email == 'none@email.none'){
						$client_email = "";
					}
					// $request_url = $url."/api/customers/".$client_fname."___".$client_lname;
					$request_url = $url."/api/customers/".base64_encode($client_fname."___".$client_lname.'&#'.$client_email);
					$client_match = $this->execute_curl_wth_bscAuth("GET", $username, $password, $request_url, '');
				}
				
				$customer_id 			= $client_match['data']['id'];
				$customer_name 			= $client_match['data']['name'];
				$customer_email 		= $client_match['data']['email'];
				$customer_tax_number 	= $client_match['data']['tax_number'];
				$customer_phone 		= $client_match['data']['phone'];
				$customer_address 		= $client_match['data']['address'];

				# room
				$room_type_name 		= $res_data[$x]["room_type"];
				$room_cost 				= 0;
				$reference_num 			= $res_data[$x]["ref_num"];
				$checkin_date 			= $res_data[$x]["checkin_date"];
				$checkout_date 			= $res_data[$x]["checkout_date"];
				$weekly_rates 			= $res_data[$x]["weekly_rates"];
				$rates_Array 			= explode(',', $weekly_rates);
				$cnt_rates 				= count($rates_Array);
				for($y=0; $y<$cnt_rates; $y++){
					$post_fields[""] = $room_cost += $rates_Array[$y];
				}

				$invoice_item = array();
				$invoice_item[] = array('company_id' 		=> $awen_company_id, 
										'invoice_id' 		=> '', 
										'item_id' 			=> '', 
										'name' 				=> $room_type_name, 
										'sku' 				=> '', 
										'quantity' 			=> 1, 
										'price' 			=> $room_cost, 
										'total' 			=> $room_cost, 
										'tax' 				=> 0, 
										'tax_id' 			=> 0, 
										'category_id' 		=> $awen_category_id_htl_room_price,
										'api_handler' 		=> 'HTL',
										'api_rel_ID' 		=> 'ROOM:'.$reservation_id);

				$sql = "SELECT res_costs.*, rates.`price_section_id`
						FROM `reservation_costs` res_costs LEFT JOIN `rates` rates ON res_costs.`rates_id` = rates.`rates_id`
						WHERE res_costs.`reservation_id` = '$reservation_id'";
				$getReservationCost = $this->executeQuery($sql);
				$costCnt = count($getReservationCost);
				$totalCost = $room_cost;
				for($ee = 0; $ee < $costCnt; $ee++){
					$cat_id = $awen_category_id_htl_room_price;
					if(isset($awen_category_id_htl_price_section[$getReservationCost[$ee]['price_section_id']])){
						$cat_id = $awen_category_id_htl_price_section[$getReservationCost[$ee]['price_section_id']];
					}
					if($getReservationCost[$ee]['type'] == 'sg'){
						$cost = ($getReservationCost[$ee]['value'] * $getReservationCost[$ee]['quantity']);
						$totalCost += $cost;
						$invoice_item[] = array('company_id' 	=> $awen_company_id, 
										'invoice_id' 			=> '', 
										'item_id' 				=> '', 
										'name' 					=> $getReservationCost[$ee]['name'], 
										'sku' 					=> '', 
										'quantity' 				=> $getReservationCost[$ee]['quantity'], 
										'price' 				=> $getReservationCost[$ee]['value'], 
										'total' 				=> $cost, 
										'tax' 					=> 0, 
										'tax_id' 				=> 0, 
										'category_id' 			=> $cat_id,
										'api_handler' 			=> 'HTL',
										'api_rel_ID' 			=> 'EXTRAS:'.$getReservationCost[$ee]['reservation_cost_id']);
					}
					if($getReservationCost[$ee]['type'] == 'dl'){
						$cost = (($getReservationCost[$ee]['value'] * $getReservationCost[$ee]['days']) * $getReservationCost[$ee]['quantity']);
						$totalCost += $cost;
						$qty = ($getReservationCost[$ee]['quantity']*$getReservationCost[$ee]['days']);
						$invoice_item[] = array('company_id' 	=> $awen_company_id, 
										'invoice_id' 			=> '', 
										'item_id' 				=> '', 
										'name' 					=> $getReservationCost[$ee]['name'], 
										'sku' 					=> '', 
										'quantity' 				=> $qty, 
										'price' 				=> $getReservationCost[$ee]['value'], 
										'total' 				=> $cost, 
										'tax' 					=> 0, 
										'tax_id' 				=> 0, 
										'category_id' 			=> $cat_id,
										'api_handler' 			=> 'HTL',
										'api_rel_ID' 			=> 'EXTRAS:'.$getReservationCost[$ee]['reservation_cost_id']);
					}
				}


				if($customer_email == NULL || $customer_email == '' || $customer_email == 'null' || $customer_email == 'NULL'){
					$customer_email = "none@email.none";
				}
	
				$invoice_status_code = 'draft';
				if(isset($res_data[0]['cost_comm']) && ($res_data[0]['deposit_status'] == "paid" || $res_data[0]['commission_paid'] == "yes") ){
					$invoice_status_code = 'partial';
				}

				$post_fields = array('company_id' 			=> $awen_company_id, 
									 'invoice_number' 		=> 'INV'.$res_id, 
									 'order_number' 		=> $reference_num.':'.$res_id, 
									 'invoice_status_code' 	=> $invoice_status_code, 
									 'invoiced_at' 			=> $date_today, 
									 'due_at' 				=> $checkout_date, 
									 'amount' 				=> $totalCost, 
									 'currency_code' 		=> $currency, 
									 'currency_rate' 		=> 1, 
									 'customer_id' 			=> $customer_id, 
									 'customer_name' 		=> $customer_name,
									 'customer_email' 		=> $customer_email, 
									 'customer_tax_number' 	=> $customer_tax_number, 
									 'customer_phone' 		=> $customer_phone, 
									 'customer_address' 	=> $customer_address, 
									 'notes' 				=> ['origin' => 'HTL', 'action' => 'CHECKIN', 'note' => ''], 
									 'category_id' 			=> 3, 
									 'parent_id' 			=> 0, 
									 'account_id' 			=> $account_id,
									 'item' 				=> $invoice_item);

				# Send data -----------
				$request_url = $url."/api/invoices/import";
				$result = $this->execute_curl_wth_bscAuth("POST", $username, $password, $request_url, $post_fields);
				# Send data -----------
			}

			# partial Payment
			if(isset($res_data[0]['cost_comm']) && ($res_data[0]['deposit_status'] == "paid" || $res_data[0]['commission_paid'] == "yes") ){
				for($y=0; $y<count($bkng_src_ids); $y++){
					$ids = explode(":",$bkng_src_ids[$y]);
					if($ids[0] == $res_data[0]['booking_source_id']){
						$account_id = $ids[1];
						break;
					}
				}
				$order_num = $res_data[0]["ref_num"].':'.$res_id;
				$datetime = date("Y-m-d H:i:s",(time() + (C_DIFF_ORE * 3600)));
				$post_fields = [
					'company_id'		=> $awen_company_id,
					'invoice_id'		=> $order_num,
					'account_id'		=> $account_id,
					'paid_at' 			=> $datetime,
					'amount' 			=> $total_paid,
					'currency_code'  	=> $currency,
					'currency_rate'		=> 1,
					'description' 		=> ['payment_action' => 'deposit_paid'],
					'payment_method'  	=> 'HTLpayment.credit'.'.'.$account_id,
					'reference' 		=> 'HTLPY345S'
				];			
				$request_url = $url . "/api/invoices.payments/import/".$order_num;
				$payment = $this->execute_curl_wth_bscAuth("POST", $username, $password, $request_url, $post_fields);
			}
			# partial Payment

			$resp = array( "status"=> "success", "message" => $result );
			// $resp = array( "status"=> "success", "message" => $client_match );
			return $resp;
		}catch(PDOException $e){
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			return $resp;
		}
	}

	public function execute_curl_wth_bscAuth($request_type, $uname, $pword, $url, $request_fields) {
		$result;
		if($request_type == "GET"){
			$ch = curl_init( $url );
			$options = array(
						        CURLOPT_SSL_VERIFYPEER => false,
						        CURLOPT_RETURNTRANSFER => true,
						        CURLOPT_USERPWD        => "{$uname}:{$pword}",
						        CURLOPT_HTTPHEADER     => array( "Accept: application/json" ),
						    );
			curl_setopt_array( $ch, $options );
			$resp = curl_exec( $ch );
			curl_close( $ch );
			$result = json_decode($resp, true);
		}
		else if($request_type == "POST"){
			$ch      = curl_init( $url );
			$options = array(
							CURLOPT_POST           => true,
					        CURLOPT_SSL_VERIFYPEER => false,
					        CURLOPT_RETURNTRANSFER => true,
					        CURLOPT_USERPWD        => "{$uname}:{$pword}",
					        CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ), //CURLOPT_HTTPHEADER     => array( "Content-type: application/json" ), //
					        CURLOPT_POSTFIELDS     => http_build_query( $request_fields )
					    );
			curl_setopt_array( $ch, $options );
			$resp = curl_exec( $ch );
			curl_close( $ch );
			$result = json_decode($resp, true);
		}
		return $result;
	}

	public function is_awen_integrated(){
		/* create invoice to awen */
		$sql = "SELECT `value`
				FROM `global_variables`
				WHERE `key` = 'awen_integration'";
		$awen_conn = $this->executeQuery($sql);
		if(count($awen_conn) > 0 && $awen_conn[0]["value"] == 1){
			return true;
		}
		else{
			return false;
		}
		/* create invoice to awen */
	}

	public function awen_auth_creds(){
		# awen connection --------------------
		$result = array();
		$sql = "SELECT *
				FROM `global_variables`
				WHERE `key` = 'awen_api_auth_uname' OR `key` = 'awen_api_auth_pword' OR `key` = 'awen_url'";
		$awen_conn = $this->executeQuery($sql);
		$username = '';
		$password = '';
		for($x=0; $x<count($awen_conn); $x++){
			if($awen_conn[$x]['key'] == 'awen_api_auth_uname') $result['username'] = $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_api_auth_pword') $result['password'] = $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_url') $result['url'] = $awen_conn[$x]['value'];
		}
		return $result;
		# awen connection --------------------
	}

	public function awen_invoice_item($res_id, $data, $action){
		$creds = $this->awen_auth_creds();

		$sql = "SELECT `invoice_number` FROM `invoice` WHERE `reservation_id` = '$res_id' and `status` != 'inactive' LIMIT 1";
		$res_sql = $this->executeQuery($sql);
		$inv_num = $res_sql[0]["invoice_number"];

		if($action == "add"){
			$request_url = $creds['url']."/api/invoices/".$inv_num;
			$invoice_match = $this->execute_curl_wth_bscAuth("GET", $creds['username'], $creds['password'], $request_url, '');
			$data['company_id'] = $invoice_match['data']['company_id'];
			$data['invoice_id'] = $invoice_match['data']['id'];

			$sql = "SELECT * FROM `global_variables` WHERE `key` = 'awen_category_id_htl_room_price'";
			$awen_cat = $this->executeQuery($sql);
			for($x=0; $x<count($awen_cat); $x++){
				if($awen_cat[$x]['key'] == 'awen_category_id_htl_room_price') $data['category_id'] = $awen_cat[$x]['value'];
			}
			$request_url = $creds['url'] . "/api/invoiceitem/import";
			$result = $this->execute_curl_wth_bscAuth("POST", $creds['username'], $creds['password'], $request_url, $data);
			return $result;
		}
		else if($action == "modify"){
			$request_url = $creds['url']."/api/invoices/".$inv_num;
			$invoice_match = $this->execute_curl_wth_bscAuth("GET", $creds['username'], $creds['password'], $request_url, '');
			$data['company_id'] = $invoice_match['data']['company_id'];
			$data['invoice_id'] = $invoice_match['data']['id'];
			$data['category_id'] = $invoice_match['data']['category_id'];
			$request_url = $creds['url'] . "/api/invoiceitem/modify";
			$result = $this->execute_curl_wth_bscAuth("POST", $creds['username'], $creds['password'], $request_url, $data);
			return $result;
		}
		else if($action == "delete"){

		}
		else{
			return false;
		}
	}

	# AWEN INTEGRATION : End ------------------------------------------------------------------------------------------------------------------------




	/* GET PAYMENT METHOD DETAILS =========================== */
	public function methodPAY_($method_ID){
		$mthD_query = "SELECT `payment_method_id`,
		`payment_name`,
		`payment_code`,
		`status`
		FROM `payment_method`
		WHERE `payment_method_id` = $method_ID
		LIMIT 0,1";
		$result_ 	= $this->executeQuery($mthD_query);
		$method_PY 	= [];
		foreach ($result_ as $INkey => $value) {
			$method_PY[] = $value;
		}
		return $method_PY;
	}
	/* ====================================================== */

	public function tempSTRORAGE_($id_res, $res_cashbox_id, $payment_value, $py_mthd_id, $py_mthd_code, $payment_action){

		$myDatabase = $this->db;

		$sql = "INSERT INTO awehtl_cashinvoice (res_id,

				cashbox_id,
				payment_value,
				py_mthd_id,
				py_mthd_code,
				payment_action) 
				VALUES(:res_id,
				:cashbox_id,
				:payment_value,
				:py_mthd_id,
				:py_mthd_code,
				:payment_action)";
		$stmt = $myDatabase->prepare($sql);
		try {

			$dbh = $this->db;
			$sth = $dbh->prepare("SELECT `rate_total`,
					`paid`
					FROM `reservation`
					WHERE `reservation_id` = $id_res");
			$sth->execute();
			$res_FN = $sth->fetch();
			if($payment_action == 'all_paid'):
				$payment_value = floatval($res_FN['rate_total']) - floatval($res_FN['paid']);
			endif;

			$stmt->execute(
				array(
					':res_id'    		=> $id_res,
					':cashbox_id'		=> $res_cashbox_id,
					':payment_value'	=> $payment_value,
					':py_mthd_id'		=> $py_mthd_id,
					':py_mthd_code'		=> $py_mthd_code,
					':payment_action'	=> $payment_action
				)
			);
		} catch (PDOException $e) {
			
		}
	}
	/* ====================================================== */
	public function GEThotel_cashinvoice($cashbox_id){
		$sMD_query = "SELECT `res_id`,
			`cashbox_id`,
			`payment_value`,
			`py_mthd_id`,
			`py_mthd_code`,
			`payment_action`
		FROM `awehtl_cashinvoice`
		WHERE `cashbox_id` = $cashbox_id";
		$result_ 	= $this->executeQuery($sMD_query);
		$sf 		= [];
		foreach ($result_ as $INkey => $value) {
			$id_res 				= $value['res_id'];
			$payment_value 			= $value['payment_value'];
			$py_mthd_id 			= $value['py_mthd_id'];
			$py_mthd_code 			= $value['py_mthd_code'];
			$payment_action 		= $value['payment_action'];	

		    $integTR 			= $this->AWE_invoice_payment($id_res, $payment_value, $py_mthd_id, $py_mthd_code, $payment_action);
			$sf[] = [
				'id_res'		=> $id_res,
				'payment_value' => $payment_value,
				'py_mthd_id'	=> $py_mthd_id,
				'py_mthd_code'	=> $py_mthd_code,
				'payment_action'=> $payment_action,
				'action_'		=> $payment_action
			];
		}

		if(count($result_) > 0):
			$db_= $this->db;
			$sMD_delete = "DELETE FROM `awehtl_cashinvoice`
				WHERE `cashbox_id` = $cashbox_id ";
			$smdDEL = $db_->prepare($sMD_delete);
			$smdDEL->execute();
		endif;

		return $sf;
	}

	/* ====================================================== */

	/* ------------------------------------------------------------------------------------- */
	/* AWEN ADD INVOICE PAYMENT ------------------------------------------------------------ */
	public function AWE_invoice_payment($res_id, $amount_, $payment_id, $payment_code, $paymentACT){		
		if($this->awen_implem() == '1'):
		$date_today 		= date("Y-m-d H:i:s",(time() + (C_DIFF_ORE * 3600)));
		$AWE_CRED 			= $this->AWE_CRED();
		$currency_code 		= $AWE_CRED['currency_code'];
		$username 			= $AWE_CRED['username'];
		$password 			= $AWE_CRED['password'];
		$account_id_CSH		= $AWE_CRED['account_id_CSH'];
		$account_id_CRD		= $AWE_CRED['account_id_CRD'];
		$url 				= $AWE_CRED['url'];
		$awen_company_id 	= $AWE_CRED['awen_company_id'];
		$account_id 		= null;

		if($payment_code == 'cash'):
			$htl_accIDs = explode(",", $account_id_CSH);
			foreach($htl_accIDs as $cshID):
				$HTL_acc = explode(':', str_replace(')', '', str_replace('(', '', $cshID)));
				if($HTL_acc[0] == $payment_id ):
					$account_id = $HTL_acc[1];
				endif;
			endforeach;			
		elseif($payment_code == 'credit'):
			$htl_accIDs = explode(",", $account_id_CRD);
			foreach($htl_accIDs as $cshID):
				$HTL_acc = explode(':', str_replace(')', '', str_replace('(', '', $cshID)));
				if($HTL_acc[0] == $payment_id ):
					$account_id = $HTL_acc[1];
				endif;
			endforeach;			
		endif;

		/* QUERY INVOICE DETAILS ----------------------------------------------------------- */
		$query_ORDERNO = "SELECT CONCAT(r_1.`reference_num`, ':', r_2.`reservation_id`) as order_no,
						r_2.`deposit`,
						r_2.`rate_total`,
						r_2.`paid`
						FROM `reservation_conn` as r_1
						INNER JOIN `reservation` as r_2
						ON r_1.`reservation_conn_id` = r_2.`reservation_conn_id`
						WHERE r_2.`reservation_id` = $res_id";
		$result_ORDERNO = $this->executeQuery($query_ORDERNO);
		$order_no_AWE 	= null;
		$deposit 		= 0; // Backup Datas
		$paid 			= 0; // Backup Datas
		$total_rate 	= 0;
		
		foreach($result_ORDERNO as $ORD_key => $ORDvalue):
			$order_no_AWE = $ORDvalue['order_no']; 
			$deposit      = $ORDvalue['deposit'];
			$paid 		  = $ORDvalue['paid'];
			$total_rate   = $ORDvalue['rate_total'];
			break;
		endforeach;

		if($paymentACT == 'deposit_paid'):
			$amount_ = $deposit;
		elseif($paymentACT == 'all_paid' && $payment_code != 'cash'):
			$amount_ = floatval($total_rate) - floatval($paid);
		endif;

		$post_fields = [
				'company_id'		=> $awen_company_id,
				'invoice_id'		=> $order_no_AWE,
				'account_id'		=> $account_id,
				'paid_at' 			=> $date_today,
				'amount' 			=> $amount_,
				'currency_code'  	=> $currency_code,
				'currency_rate'		=> null,
				'description' 		=> ['payment_action' => $paymentACT],
				'payment_method'  	=> 'HTLpayment.'.$payment_code.'.'.$account_id,
				'reference' 		=> 'HTLPY345S'
			];			

			$url    			= $url . "/api/invoices.payments/import/". $order_no_AWE;
			$ch      = curl_init( $url );
			$options = array(
							CURLOPT_POST           => true,
					        CURLOPT_SSL_VERIFYPEER => false,
					        CURLOPT_RETURNTRANSFER => true,
					        CURLOPT_USERPWD        => "{$username}:{$password}",
					        CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
					        CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
					    );
			curl_setopt_array( $ch, $options );
			$result = curl_exec( $ch );
			curl_close( $ch );
		return [$order_no_AWE => $result];
		endif;
		return false;
	}





	public function remove_HTLAWE_inv($res_id){
		if($this->awen_implem() == '1'):
		# awen connection --------------------
		$sql = "SELECT `id`, `key`, `value`
				FROM `global_variables`
				WHERE `key` IN ('awen_api_auth_uname', 'awen_api_auth_pword', 'awen_url') ";
		$awen_conn = $this->executeQuery($sql);
		$username 		= null;
		$password 		= null;
		$url_ 			= null;
		for($x=0; $x<count($awen_conn); $x++){
			if($awen_conn[$x]['key'] == 'awen_api_auth_uname') $username = $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_api_auth_pword') $password = $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_url') $url_ = $awen_conn[$x]['value'];
		}
		# awen connection --------------------
		# get order number -------------------
		$query_ORDERNO = "SELECT CONCAT(r_1.`reference_num`, ':', r_2.`reservation_id`) as order_no
						FROM `reservation_conn` as r_1
						INNER JOIN `reservation` as r_2
						ON r_1.`reservation_conn_id` = r_2.`reservation_conn_id`
						WHERE r_2.`reservation_id` = $res_id";
		$result_ORDERNO = $this->executeQuery($query_ORDERNO);
		$order_no_AWE 	= null;		
		foreach($result_ORDERNO as $ORD_key => $ORDvalue):
			$order_no_AWE = $ORDvalue['order_no']; 
			break;
		endforeach;
		# get order number -------------------
		$url    			= $url_ . "/api/invoices/vHTL_destroy/". $order_no_AWE;
		$ch      = curl_init( $url );
		$options = array(
						CURLOPT_POST           => true,
				        CURLOPT_SSL_VERIFYPEER => false,
				        CURLOPT_RETURNTRANSFER => true,
				        CURLOPT_USERPWD        => "{$username}:{$password}",
				        CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}




	/* METHOD GET AWEN CREDENTIALS ---------------------- */
	public function AWE_CRED(){
		# awen connection --------------------
		$sql = "SELECT `id`, `key`, `value`
				FROM `global_variables`
				WHERE `key`
				IN ('currency',
				'awen_api_auth_uname',
				'awen_api_auth_pword',
				'awen_account_id_htl_cashbox',
				'awen_url',
				'awen_account_id_credicard',
				'awen_category_id_htl_price_section',
				'awen_category_id_htl_room_price',
				'awen_company_id',
				'awen_account_id_htl_bkng_src',
				'res_cashbox_id') ";
		$awen_conn = $this->executeQuery($sql);
		#--------------------------------------
		$currency_code 		= null;
		$username 			= null;
		$password 			= null;
		$account_id 		= null;
		$account_id_CSH 	= null;
		$account_id_CRD 	= null;
		$awen_cat_id    	= null;
		$room_price_id		= null;
		$awen_company_id 	= null;
		$xt_account_id		= null;
		$url 				= null;
		$cashbox_id			= null;
		#------------------------------------
		for($x=0; $x<count($awen_conn); $x++){
			if($awen_conn[$x]['key'] == 'currency') 							$currency_code = explode(',', $awen_conn[$x]['value'])[1];
			if($awen_conn[$x]['key'] == 'awen_api_auth_uname') 					$username 			= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_api_auth_pword') 					$password 			= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_account_id_htl_cashbox') 			$account_id_CSH 	= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_account_id_credicard') 			$account_id_CRD 	= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_category_id_htl_price_section') 	$awen_cat_id 		= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_category_id_htl_room_price') 		$room_price_id		= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_company_id') 						$awen_company_id 	= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_account_id_htl_bkng_src') 		$xt_account_id 		= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'awen_url') 							$url 				= $awen_conn[$x]['value'];
			if($awen_conn[$x]['key'] == 'res_cashbox_id') 						$cashbox_id 		= $awen_conn[$x]['value'];
		}
		# awen connection --------------------

		return [
			'currency_code' 	=> $currency_code,
			'username' 			=> $username,
			'password' 			=> $password,
			'account_id' 		=> $account_id,
			'awen_cat_id' 		=> $awen_cat_id,
			'account_id_CSH'	=> $account_id_CSH, 
			'account_id_CRD'	=> $account_id_CRD,
			'room_price_id' 	=> $room_price_id,
			'awen_company_id' 	=> $awen_company_id,
			'booking_account'	=> $xt_account_id,
			'url' 				=> $url,
			'cashbox_id' 		=> $cashbox_id	
		];
	}
	/* -------------------------------------------------- */

	/* GET SECTION ID ----------------------------------- */
	public function item_sectionID($rates_ID){
		$dbh = $this->db;
		$sth = $dbh->prepare("SELECT `price_section_id`,
				`type_ca`
				FROM `rates`
				WHERE `rates_id` = $rates_ID");
		$sth->execute();
		return $sth->fetch();
	}
	/* -------------------------------------------------- */

	/* GET RESERVATION DURATION ------------------------- */
	public function res_DAY($res_ID){
		$dbh = $this->db;
		$sth = $dbh->prepare("SELECT `date_start_id`,
				`date_end_id`
				FROM `reservation`
				WHERE `reservation_id` = $res_ID");
		$sth->execute();
		$res_ = $sth->fetch();
		return (floatval($res_['date_end_id']) - floatval($res_['date_start_id'])) + 1;	
	}
	/* -------------------------------------------------- */

	/* GET RESERVATION COST ID -------------------------- */
	public function resCOSTID($rates_id, $res_ID){
		$dbh = $this->db;
		$sth = $dbh->prepare("SELECT `reservation_cost_id`
				FROM `reservation_costs`
				WHERE `rates_id` = $rates_id AND `reservation_id` = '$res_ID' ORDER BY`reservation_cost_id` DESC LIMIT 0, 1");
		$sth->execute();
		return $sth->fetch();
	}
	/* -------------------------------------------------- */


	/* METHOD ADD ITEM ---------------------------------- */
	public function add_AWE_item($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 	= $this->AWE_CRED();
		$duration_ 	= $this->res_DAY($data_['reservation_id']);

		$MPP_CTID 		= [];
		$invoice_item 	= [];
		if($AWE_CRED['awen_cat_id'] != null):
			$mp_CATID = explode(',', $AWE_CRED['awen_cat_id']);
			
			foreach($mp_CATID as $mpCTID => $CTID_val):
				$Xpl = explode(':', $CTID_val);
				$MPP_CTID[$Xpl[0]] = $Xpl[1];				
			endforeach;

			foreach ($data_['reservation_extras'] as $CTkey => $CTvalue):
				$rates_ 		= $this->item_sectionID($CTvalue['id_rates']);
				$AWE_category 	= $MPP_CTID[$rates_['price_section_id']];

				$resCOSTID 		= $this->resCOSTID($CTvalue['id_rates'], $data_['reservation_id']);


				#-----------------
				if($CTvalue['type']== 'dl')://if($AWE_category == 'dl'):Added for HTL-1016
					$CTvalue['qty']			= $CTvalue['qty'] * $duration_;
					$CTvalue['price']		= $CTvalue['price'] * $duration_;
					$CTvalue['price_total']	= $CTvalue['price_total'] * $duration_;					
				endif;
				#-----------------

				$invoice_item[] = array('company_id' 	=> $AWE_CRED['awen_company_id'], 
										'invoice_id' 	=> '', 
										'item_id' 		=> $resCOSTID['reservation_cost_id'], 
										'name' 			=> $CTvalue['rates_name'], 
										'sku' 			=> '', 
										'quantity' 		=> $CTvalue['qty'], 
										'price' 		=> $CTvalue['price'], 
										'total' 		=> $CTvalue['price_total'], 
										'tax' 			=> 0, 
										'tax_id' 		=> 0, 
										'category_id' 	=> $AWE_category);
			endforeach;
		endif;

		$post_fields = array('company_id' 				=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLADDITEM001', 
								'order_number' 			=> $data_['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> 0, 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $invoice_item);

		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_additem';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}
	/* METHOD ADD ITEM ---------------------------------- */

	/* METHOD GET EDIT ITEM ----------------------------- */
	public function AWE_edit_item($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 	= $this->AWE_CRED();
		$invoice_item = [
						'item_id' 		=> $data_['awen_ITMID'],
						'item_value' 	=> $data_['awen_val'],
						'item_day' 		=> $data_['awen_days'],
						'item_quantity' => $data_['awen_qty']
						];

		$post_fields = array('company_id' 				=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLADDITEM002', 
								'order_number' 			=> $data_['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> 0, 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $invoice_item);

		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_updateitem';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}
	/* METHOD GET EDIT ITEM ----------------------------- */

	/* METHOD REMOVE ITEM ------------------------------- */
	public function AWE_delete_item($reservation_id, $itmidcost){
		if($this->awen_implem() == '1'):
		$AWE_CRED 	= $this->AWE_CRED();
		$invoice_item = array('item_id' => $itmidcost);
		$post_fields = array('company_id' 				=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLDELETETEM003', 
								'order_number' 			=> $reservation_id, 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> 0, 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $invoice_item);

		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_deleteitem';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}
	/* METHOD REMOVE ITEM ------------------------------- */


	/* AWEN-PUROSES GET ADDED ADDONS DETAILS ------------ */
	public function AWE_htladdedaddon($resid_, $addonid_, $scene_){
		$whereClause = "`reservation_id` = '$resid_' AND `var_addon_id` = '$addonid_' ";
		if($scene_ == 'UPDATED'):
			$whereClause = "`id` = '$addonid_' ";
		endif;

		$dbh = $this->db;
		$sth = $dbh->prepare("SELECT `id`,
			`reservation_id`,
			`name`,
			`addon_type`,
			`cost`,
			`price_type`,
			`price`,
			`quantity`,
			`multiply_by`,
			`days`,
			`var_addon_id`,
			`inserted_date`,
			`status`
			FROM `reservation_costs2` 
			WHERE ". $whereClause."
			AND `status` = 'active'
			ORDER BY `id` DESC LIMIT 0, 1");
		$sth->execute();
		return $sth->fetch();
	}
	/* AWEN-PUROSES GET ADDED ADDONS DETAILS ------------ */

	/* METHOD ADD INVOICE ADDON ------------------------- */
	public function AWE_add_addon($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 		= $this->AWE_CRED();
		$inc_addon 	= [];
		foreach($data_['selected_price'] as $kyaddon => $addon_):
			$inc_addon[] = $this->AWE_htladdedaddon($data_['res_id'], $addon_['data']['var_addon_id'], 'ADDED');
		endforeach;

		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLADDADDONM003', 
								'order_number' 			=> $data_['res_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $inc_addon);

		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_addADDON';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}
	/* METHOD ADD INVOICE ADDON ------------------------- */

	/* METHOD EDIT ADDON -------------------------------- */
	public function AWE_updateaddon($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 		= $this->AWE_CRED();
		$inc_addon 		= $this->AWE_htladdedaddon($data_['var_addon']['reservation_id'], $data_['var_addon']['id'], 'UPDATED');
		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLUPDATEM004', 
								'order_number' 			=> $data_['var_addon']['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $inc_addon);

		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_updAteADDON';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}
	/* METHOD EDIT ADDON -------------------------------- */


	/* METHOD DELETE ADDON ------------------------------ */
	public function AWE_deleteaddon($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 		= $this->AWE_CRED();
		$inc_addon 		= [
						'item_id' 		 => $data_['addon_data']['id'],
						'reservation_id' => $data_['addon_data']['reservation_id']
						];

		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLDELETEM005', 
								'order_number' 			=> $data_['addon_data']['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> $inc_addon);
		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/inv_deleteADDON';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;

	}
	/* METHOD DELETE ADDON ----------------------------- */



	/* METHOD EDIT DATE PERIOD ------------------------- */
	public function awe_editperiod($data_){
		if($this->awen_implem() == '1'):
		$AWE_CRED = $this->AWE_CRED();
		$dbh 	= $this->db;
		$resr 	= $dbh->prepare("SELECT `reservation_conn_id`, 
				`date_start_id`,
				`date_end_id`,
				`weekly_rates`
				FROM `reservation`
				WHERE `reservation_id` = '$data_[reservation_id]' ORDER BY `reservation_id` DESC LIMIT 0, 1");
		$resr->execute();
		$resrvTBL = $resr->fetch();
		$current_amt 	= 0;
		$xpl_prices 	= explode(',', $resrvTBL['weekly_rates']);
		foreach($xpl_prices  as $prsK => $prsV): $current_amt += floatval($prsV); endforeach;

		$trnsser = $dbh->prepare("SELECT `reservation_conn_id`, 
				`date_start_id`,
				`date_end_id`,
				`weekly_rates`
				FROM `transfer_room_history`
				WHERE `reservation_id` = '$data_[reservation_id]' ORDER BY `reservation_id` DESC LIMIT 0, 1");
		$trnsser->execute();
		$trnsresrvTBL = $trnsser->fetchAll( PDO::FETCH_ASSOC );
		$history_amt 	= 0;
		if(count($trnsresrvTBL) > 0):
			foreach($trnsresrvTBL as $hisK => $hisV):
				$hsxpl_prices 	= explode(',', $hisV['weekly_rates']);
				foreach($hsxpl_prices  as $hsprsK => $hsprsV): $history_amt += floatval($hsprsV); endforeach;
			endforeach;
		endif;

		$TTL_amount_new = ($current_amt + $history_amt);

		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> 'HTLEXTENDM006', 
								'order_number' 			=> $data_['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> $TTL_amount_new, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> '');
		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/awen_extendperiod';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;

	}
	/* METHOD EDIT DATE PERIOD ------------------------- */



	public function awen_implem(){
		$dbh 	= $this->db;
		$aweN 	= $dbh->prepare("SELECT `value`
					FROM `global_variables`
					WHERE `key` = 'awen_integration'");
		$aweN->execute();
		$aweNvTBL = $aweN->fetch();
		return $aweNvTBL['value'];
	}



	public function check_invoiceEXIST($orderNUm, $invoiceNUm){
		if($this->awen_implem() == '1'):
		$AWE_CRED 		= $this->AWE_CRED();
		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> '$invoiceNUm', 
								'order_number' 			=> $orderNUm, 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> '');
		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/EXPORTcheckIvoice';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}	




	/* EXPORT INVOICE(RESERVATON) DATA ------------------------ */
	public function EXPORT_reservation_inf($inf_){

		if($this->awen_implem() == '1'):
			$dbh 				= $this->db;
			$AWE_CRED 			= $this->AWE_CRED();
			$awen_company_id 	= $AWE_CRED['awen_company_id'];
			$username			= $AWE_CRED['username'];
			$password			= $AWE_CRED['password'];
			$currency 			= $AWE_CRED['currency_code'];
			$payment_date 		= $inf_['checkin_date'];
			$inv_status 		= 'draft';
			$MPP_CTID 			= [];
			$invoice_item 		= array();
			$booking_account  	= array();
			$cName 				= $inf_['c_name'];
			$CLName 			= $inf_['c_surname'];
			$client_email 		= $inf_['c_email'];

			$mp_CATID = explode(',', $AWE_CRED['awen_cat_id']);			
			foreach($mp_CATID as $mpCTID => $CTID_val):
				$Xpl 				= explode(':', $CTID_val);
				$MPP_CTID[$Xpl[0]] 	= $Xpl[1];				
			endforeach;

			$bk_acct = explode(',', $AWE_CRED['booking_account']);			
			foreach($bk_acct as $mpbkACT => $bkACT_val):
				$bkaccTS_ 						= explode(':', $bkACT_val);
				$booking_account[$bkaccTS_[0]] 	= $bkaccTS_[1];				
			endforeach;


			# CUSTOMER DETAILS -------------------------------------
			if($inf_['c_email'] == null || $client_email == '') 		$client_email 	= "none_".$inf_['reservation_id']."@email.none";
			$cst_url 		= $AWE_CRED['url'].'/api/customers/'.base64_encode($cName."___".$CLName.'&#'.$client_email);
			$client_match 	= $this->execute_curl_wth_bscAuth("GET", $username, $password, $cst_url, '');
			if(!isset($client_match['data']['id'])){
				# IF CUSTOMER DOESN'T EXIST ------------------------			
				if($inf_['c_name'] == null || $inf_['c_name'] == '') 		$cName 			= 'none_'.$inf_['reservation_id'];	
				if($inf_['c_surname'] == null || $inf_['c_surname'] == '' ) $CLName 		= 'none_'.$inf_['reservation_id'];		


				$clpost_fields = array(
							'company_id' 	=> $awen_company_id,
							'user_id'  	 	=> '',
							'name'       	=> $cName.' '.$CLName,
							'email'			=> $client_email,
							'tax_number'    => NULL,
							'phone'			=> NULL,
							'address'		=> NULL,
							'website'		=> NULL,
							'currency_code'	=> $currency,
							'enabled'		=> 1
						   );
				$cst_url 		= $AWE_CRED['url'] . '/api/customers/import';
				$added_client 	= $this->execute_curl_wth_bscAuth("POST", $username, $password, $cst_url, $clpost_fields);
				# IF CUSTOMER DOESN'T EXIST ------------------------
				$cst_url 		= $AWE_CRED['url'].'/api/customers/'.base64_encode($cName."___".$CLName.'&#'.$client_email);
				$client_match 	= $this->execute_curl_wth_bscAuth("GET", $username, $password, $cst_url, '');
			}
			# CUSTOMER DETAILS -------------------------------------

			# INVOICE ITEM -----------------------------------------
				#  >> ITEM ROOM ------------------------------------
					$room_cost 		= 0;
					$rates_Array 	= explode(',', $inf_['weekly_rates']);
					foreach($rates_Array as $rTSK => $rTSV):
						$room_cost += floatval($rTSV);
					endforeach;
					$invoice_item[] = array('company_id' 	=> $awen_company_id, 
										'invoice_id' 		=> '', 
										'item_id' 			=> '', 
										'name' 				=> $inf_['room_type'], 
										'sku' 				=> '', 
										'quantity' 			=> 1, 
										'price' 			=> $room_cost, 
										'total' 			=> $room_cost, 
										'tax' 				=> 0, 
										'tax_id' 			=> 0, 
										'category_id' 		=> $AWE_CRED['room_price_id'],
										'api_handler' 		=> 'HTL',
										'api_rel_ID' 		=> 'ROOM:'.$inf_['reservation_id']);
				#  >> ITEM ROOM ------------------------------------
				#  >> LIST INV EXTRAS ------------------------------
				$item_ 	= $dbh->prepare("SELECT rCST.*,
							rates.`price_section_id`
							FROM `reservation_costs` rCST 
							LEFT JOIN `rates` rates ON rCST.`rates_id` = rates.`rates_id`
							WHERE rCST.`reservation_id` = '".$inf_['reservation_id']."' ");
				$item_->execute();			
				foreach($item_->fetchAll(PDO::FETCH_ASSOC) as $itmK => $itmV):
					if($itmV['type'] == 'dl'){
						$itm_qty  = ($itmV['quantity'] * $itmV['days']);
						$itm_cost = (($itmV['value'] * $itmV['days']) * $itmV['quantity']);
					}else{
						$itm_qty  = $itmV['quantity'];
						$itm_cost = ($itmV['value'] * $itmV['quantity']);
					}

					if(isset($MPP_CTID[$itmV['price_section_id']])):
						$invoice_item[] = array('company_id' 	=> $awen_company_id, 
									'invoice_id' 		=> '', 
									'item_id' 			=> '', 
									'name' 				=> $itmV['name'], 
									'sku' 				=> '', 
									'quantity' 			=> $itm_qty, 
									'price' 			=> $itmV['value'], 
									'total' 			=> $itm_cost, 
									'tax' 				=> 0, 
									'tax_id' 			=> 0, 
									'category_id' 		=> $MPP_CTID[$itmV['price_section_id']],
									'api_handler' 		=> 'HTL',
									'api_rel_ID' 		=> 'EXTRAS:' . $itmV['reservation_cost_id']);
						endif;
				endforeach;
				# >> LIST INV EXTRAS -------------------------------
				# >> LIST INV ADD ON -------------------------------
				$addon_ 	= $dbh->prepare("SELECT rCST2.*
							FROM `reservation_costs2` rCST2 
							WHERE rCST2.`reservation_id` = '".$inf_['reservation_id']."' ");
				$addon_->execute();
				foreach($addon_->fetchAll(PDO::FETCH_ASSOC) as $addonK => $addonV):
					$addon_TTL_price = 0;
					$exp_price      = explode(',', $addonV['price']);
	                for($ddn = 0; $ddn < count($exp_price); $ddn++):
	                    $addon_TTL_price += floatval($exp_price[$ddn]);
	                endfor;
	                $TTL_addNprice  = ($addon_TTL_price * intval($addonV['quantity']));

					$invoice_item[] = array('company_id' 	=> $awen_company_id, 
								'invoice_id' 		=> '', 
								'item_id' 			=> '', 
								'name' 				=> $addonV['name'], 
								'sku' 				=> '', 
								'quantity' 			=> $addonV['quantity'], 
								'price' 			=> $addon_TTL_price, 
								'total' 			=> $TTL_addNprice, 
								'tax' 				=> 0, 
								'tax_id' 			=> 0, 
								'category_id' 		=> $AWE_CRED['room_price_id'],
								'api_handler' 		=> 'HTL',
								'api_rel_ID' 		=> 'ADDON:' . $addonV['id']);
				endforeach;		
				# >> LIST INV ADD ON -------------------------------

			# INVOICE ITEM -----------------------------------------
			# EXECUTE EXPORTATION ----------------------------------

				if($inf_['c_email'] == NULL || $client_email == '') $client_email = "none_".$inf_['reservation_id']."@email.none";	
				
				$inv_status 		= 'draft';
				if($inf_['checkout'] != null || $inf_['checkout'] != ''):
					$inv_status 	= 'paid';
					$payment_date 	= $inf_['checkout_date'];
				endif;

				if($inf_['rate_total'] > ($inf_['discount'] + $inf_['paid'])):
					$inv_status = 'partial';
				endif;


				$htl_accIDs = explode(",", $AWE_CRED['account_id_CSH']);
				$account_id = 0;
				foreach($htl_accIDs as $cshID):
					$HTL_acc = explode(':', str_replace(')', '', str_replace('(', '', $cshID)));
					if($HTL_acc[0] == 1):
						$account_id = $HTL_acc[1];
					endif;
				endforeach;	

				$invpost_fields = array('company_id' 		=> $awen_company_id, 
									 'invoice_number' 		=> $inf_['invoice_number'], 
									 'order_number' 		=> $inf_['reference_num'].':'.$inf_['reservation_id'], 
									 'invoice_status_code' 	=> $inv_status, 
									 'invoiced_at' 			=> $inf_['checkin_date'], 
									 'due_at' 				=> $inf_['checkout_date'], 
									 'amount' 				=> $inf_['rate_total'], 
									 'currency_code' 		=> $currency, 
									 'currency_rate' 		=> 1, 
									 'customer_id' 			=> $client_match['data']['id'], 
									 'customer_name' 		=> $client_match['data']['name'],
									 'customer_email' 		=> $client_email, 
									 'customer_tax_number' 	=> $client_match['data']['tax_number'], 
									 'customer_phone' 		=> $client_match['data']['phone'],  
									 'customer_address' 	=> $client_match['data']['address'], 
									 'notes' 				=> ['origin' => 'HTL', 'action' => 'EXPORT', 'note' => ''], 
									 'category_id' 			=> 1, 
									 'parent_id' 			=> 0, 
									 'account_id' 			=> $account_id,
									 'item' 				=> $invoice_item);

				# >> Send data -------------------------------------
					$request_url = $AWE_CRED['url']."/api/invoices/import";
					$invoice = $this->execute_curl_wth_bscAuth("POST", $username, $password, $request_url, $invpost_fields);
				# >> Send data -------------------------------------
				# >> Send INV payment ------------------------------
					$py_accountID 	= $booking_account[$inf_['booking_source_id']];
					$order_num 		= $inf_['reference_num'].':'.$inf_['reservation_id'];
					$pypost_fields = [
					'company_id'		=> $awen_company_id,
					'invoice_id'		=> $order_num,
					'account_id'		=> $py_accountID,
					'paid_at' 			=> $payment_date,
					'amount' 			=> ($inf_['discount'] + $inf_['paid']),
					'currency_code'  	=> $currency,
					'currency_rate'		=> 1,
					'description' 		=> ['payment_action' => 'exported payment'],
					'payment_method'  	=> 'HTLpayment.export'.'.'.$inf_['reservation_id'],
					'reference' 		=> 'HTLPY345S'
					];			
					$py_url 	= $AWE_CRED['url'] . "/api/invoices.payments/import/".$order_num;
					$payment 	= $this->execute_curl_wth_bscAuth("POST", $username, $password, $py_url, $pypost_fields);
				# >> Send INV payment ------------------------------
			# EXECUTE EXPORTATION ----------------------------------
			return $invoice;
			# ------------------------------------------------------
		endif;	

	}
	/* EXPORT INVOICE(RESERVATON) DATA ------------------------ */


	/* ALIGN AWEN INVOICE NUMBER ------------------------------ */
	public function ALIGN_invnumber($inf_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 		= $this->AWE_CRED();
		$post_fields 	= array('company_id' 			=> $AWE_CRED['awen_company_id'], 
								'invoice_number' 		=> $inf_['invoice_number'], 
								'order_number' 			=> $inf_['reservation_id'], 
								'invoice_status_code' 	=> null, 
								'invoiced_at' 			=> date('Y-m-d H:i:s'), 
								'due_at' 				=> date('Y-m-d H:i:s'), 
								'amount' 				=> 1000, 
								'currency_code' 		=> $AWE_CRED['currency_code'], 
								'currency_rate' 		=> 0, 
								'customer_id' 			=> 0, 
								'customer_name' 		=> null,
								'customer_email' 		=> null, 
								'customer_tax_number' 	=> null, 
								'customer_phone' 		=> null, 
								'customer_address' 		=> null, 
								'notes' 				=> null, 
								'category_id' 			=> $AWE_CRED['room_price_id'], 
								'parent_id' 			=> 0, 
								'account_id' 			=> 0,
								'item' 					=> '');
		$username	= $AWE_CRED['username'];
		$password	= $AWE_CRED['password'];
		$url     	= $AWE_CRED['url'] . '/api/invoices/ALIGNinvNUMcheckIvoice';
		$ch      	= curl_init( $url );
		$options 	= array(
						CURLOPT_POST           => true,
						CURLOPT_SSL_VERIFYPEER => false,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_USERPWD        => "{$username}:{$password}",
						CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
						CURLOPT_POSTFIELDS     => http_build_query( $post_fields )
				    );
		curl_setopt_array( $ch, $options );
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
		endif;
		return false;
	}	
	/* ALIGN AWEN INVOICE NUMBER ------------------------------ */




	/* UPDATE PAYMENT (AWE-INVOICE) --------------------------- */
	public function adj_updateinvpayment($inf_){
		if($this->awen_implem() == '1'):
		$AWE_CRED 			= $this->AWE_CRED();
		$awen_company_id 	= $AWE_CRED['awen_company_id'];
		$username			= $AWE_CRED['username'];
		$password			= $AWE_CRED['password'];
		$currency 			= $AWE_CRED['currency_code'];
		$payment_date 		= $inf_['checkin_date'];
		$inv_status 		= 'draft';
		$booking_account  	= array();
		

		$bk_acct = explode(',', $AWE_CRED['booking_account']);			
		foreach($bk_acct as $mpbkACT => $bkACT_val):
			$bkaccTS_ 						= explode(':', $bkACT_val);
			$booking_account[$bkaccTS_[0]] 	= $bkaccTS_[1];				
		endforeach;

		if($inf_['checkout'] != null || $inf_['checkout'] != ''):
			$payment_date 	= $inf_['checkout_date'];
		endif;

		# >> Send INV payment ------------------------------
			$py_accountID 	= $booking_account[$inf_['booking_source_id']];
			$order_num 		= $inf_['reference_num'].':'.$inf_['reservation_id'];
			$pypost_fields 	= [
			'company_id'		=> $awen_company_id,
			'invoice_id'		=> $order_num,
			'account_id'		=> $py_accountID,
			'paid_at' 			=> $payment_date,
			'amount' 			=> ($inf_['discount'] + $inf_['paid']),
			'currency_code'  	=> $currency,
			'currency_rate'		=> 1,
			'description' 		=> ['payment_action' => 'change_to'],
			'payment_method'  	=> 'HTLpayment.update'.'.'.$inf_['reservation_id'],
			'reference' 		=> 'HTLPY345S'
			];			
			$py_url 	= $AWE_CRED['url'] . "/api/invoices.payments/import/".$order_num;
			$payment 	= $this->execute_curl_wth_bscAuth("POST", $username, $password, $py_url, $pypost_fields);
		# >> Send INV payment ------------------------------

		// return $pypost_fields;
		return $payment;
		endif;
		return false;
	}	
	/* UPDATE PAYMENT (AWE-INVOICE) --------------------------- */








	/* API VERIFY ITEM FROM MATERLIST ------------------------- */
	public function masterlist_check($field, $value_){
		$_clause = "$field = '$value_'";
		if($field == 'id') $_clause = $field.' = '.$value_;
		$dbh 		= $this->db;
		$msfl_itm 	= $dbh->prepare("SELECT count(items_id) as len_, items_id,
					item_name,
					item_code,
					inserted_host,
					inserted_by,
					items_inventory_category_id
					FROM items_inventory
					WHERE $_clause ORDER BY items_id ASC LIMIT 1");
		$msfl_itm->execute();
		$result_ = $msfl_itm->fetch();
		return ['exist' => $result_['len_'], 'data' => $result_];
	}
	/* -------------------------------------------------------- */

	/* API ADD TO ITEM INVETORY ------------------------------- */
	public function awe_appendMasterlist($item_){
		$dbh 		= $this->db;
		$item_invt 	= $dbh->prepare("INSERT INTO items_inventory(
			item_name,
			inserted_date,
			inserted_host,
			inserted_by,
			items_inventory_category_id) 
			VALUES('".$item_['name']."', '".date('Y-m-d H:i:s')."', '". $_SERVER['SERVER_NAME'] ."', '1', '1')");
		$item_invt->execute();
		return ['msfl_id' =>$dbh->lastInsertId()];
	}
	/* -------------------------------------------------------- */


	/* API ADD TO REL INVENTORY(STOCKROOM COUNT) -------------- */
	public function awe_appendinvtcount($item_, $msflID){
		$dbh 		= $this->db;
		$itemInvt 	= $dbh->prepare("INSERT INTO relinventory(good_inventory_id,
				stockroom_id,
				quantity,
				quantity_min,
				checkin_required,
				inserted_date,
				inserted_host,
				inserted_by) 
			VALUES('".$msflID."', 4, '".$item_['quantity']."', '1', 'n', '".date('Y-m-d H:i:s')."', 'ssv.hmgr.io', '1')");
		$itemInvt->execute();
		return true;
	}
	/* -------------------------------------------------------- */

	/* API UPDATE REL INVENTORY(STOCKROOM COUNT) -------------- */
	public function awe_updateinvtcount($item_, $msflID, $operation_){
		$dbh 		= $this->db;
		$itemInvt 	= $dbh->prepare("UPDATE relinventory SET $operation_
					WHERE good_inventory_id = $msflID AND stockroom_id = 4 ");
		$itemInvt->execute();
		return true;
	}
	/* -------------------------------------------------------- */

	
	/* API REQUEST LATESNT INVENTORY COUNT -------------------- */
	public function awe_inventorycount($stockroom, $api_id){
		$dbh 		= $this->db;
		$itemInvt 	= $dbh->prepare("SELECT allc.inventory_status, 
					sum(rel.quantity) quantity
					FROM items_allocation allc
					INNER JOIN relinventory rel ON allc.msfl_id = rel.good_inventory_id
					WHERE allc.id = $api_id
					AND rel.stockroom_id = $stockroom 
					AND allc.inventory_status != 'REMOVE' ");
		$itemInvt->execute();
		return $restF = $itemInvt->fetch();
	}
	/* -------------------------------------------------------- */


	/* API MANAGE AWEN RELATED BILL ITEMS --------------------- */
	public function awe_updatebillitem($stockroom, $api_id){
		$dbh 		= $this->db;
		$date_now 	= Date('Y-m-d H:i:s');
		$itemInvt 	= $dbh->prepare("UPDATE items_allocation allc 
					INNER JOIN relinventory rel ON allc.msfl_id = rel.good_inventory_id
					SET allc.inventory_status = 'REMOVE',
					allc.deleted_at = '".$date_now."',
					rel.quantity =  rel.quantity - allc.item_qty
					WHERE allc.id = $api_id
					AND rel.stockroom_id = $stockroom
					AND allc.inventory_status != 'REMOVE'");
		return $itemInvt->execute();
	}
	/* -------------------------------------------------------- */
	



	/* API EXECUTE ITEM REMOVAL(update) ----------------------- */
	public function awe_updatedREMOVEitem($stockroom, $existing_input){
		foreach ($existing_input as $exskey => $exsval) {
			if($exsval['api_rel_ID'] != 0):
				$this->awe_updatebillitem($stockroom, $exsval['api_rel_ID']);
			else:
				continue;
			endif;
		}
		return true;
	}
	/* -------------------------------------------------------- */
// orig space before HTL-938
	public function apply_res_discount($res_id, $booking_source_id, $date_start_id, $date_end_id, $room_type_id){

		try {
			$sql = "SELECT `inserted_date`, `pax`
					FROM `reservation`
					WHERE `reservation_id` = '".$res_id."'";
			$res_created_date = $this->executeQuery($sql); # get promo discount.
			$date_created = $res_created_date[0]["inserted_date"];
			// ----- HTL-985 START
			$pax = $res_created_date[0]["pax"];
			// ----- HTL-985 END

	        $sql = "SELECT a.`name` as name, a.`type` as type, a.`amount` as amount, a.`date_start_id` as promo_date_start_id, a.`date_end_id` as promo_date_end_id, b.`isRound`
					FROM `res_discounts` a LEFT JOIN `res_discounts_bking_src` b ON b.`res_discount_id` = a.`id`
					WHERE CAST(a.`created_date` AS date) <= CAST('".$date_created."' AS date) AND
						  (
							  (a.`date_start_id` <= '".$date_start_id."' AND a.`date_end_id` >= '".$date_start_id."') OR
							  (a.`date_start_id` <= '".$date_end_id."' AND a.`date_end_id` >= '".$date_end_id."') OR
							  (a.`date_start_id` >= '".$date_start_id."' AND a.`date_start_id` <= '".$date_end_id."') OR
							  (a.`date_end_id` >= '".$date_start_id."' AND a.`date_end_id` <= '".$date_end_id."')
						  ) AND
						  b.`booking_source_id` = '".$booking_source_id."' AND
						  a.`status` = 'active' AND
						  b.`status` = 'active'";
			$result_res_disc = $this->executeQuery($sql); # get promo discount.
			if(count($result_res_disc) > 0){ #
				$sql = "SELECT `associated_column`
						FROM `room_types`
						WHERE `room_type_id` = '".$room_type_id."' AND `status` = 'active'";
				$result_room_types= $this->executeQuery($sql);
				$associated_column = $result_room_types[0]["associated_column"];
				// ----- HTL-985 START
				$tmp_associated_column = $associated_column."_pax_".$pax;
				$sql = "SHOW COLUMNS FROM `periods` WHERE Field = '$tmp_associated_column'";
				$periods_column = $this->executeQuery($sql);
				if( count($periods_column) > 0 ){ $associated_column = $tmp_associated_column; }
				// ------HTL-985 END
				$additional_discount = 0; # initial value for the additional discount
				if($result_res_disc[0]["type"] == 'fixed'){ # fixed amount
					for($x=$date_start_id; $x<=$date_end_id; $x++){
						$temp_period_id = $x; # focused date
						if($result_res_disc[0]["promo_date_start_id"] <= $temp_period_id && $result_res_disc[0]["promo_date_end_id"] >= $temp_period_id){
							$additional_discount += $result_res_disc[0]["amount"]; # total the additional discount
						}
					}
				}
				else{ # percentage amount based on daily room rate
					$price = 0; # initial room price
					for($x=$date_start_id; $x<=$date_end_id; $x++){
						$temp_period_id = $x; # focused date
						if($result_res_disc[0]["promo_date_start_id"] <= $temp_period_id && $result_res_disc[0]["promo_date_end_id"] >= $temp_period_id){
							$sql = "SELECT *
									FROM `periods`
									WHERE `periods_id` = '".$temp_period_id."'";
							$result_period = $this->executeQuery($sql);
							$price += $result_period[0][$associated_column];
						}
					}
					$additional_discount = $price*($result_res_disc[0]["amount"]/100); # total the additional discount

					if($result_res_disc[0]['isRound'] == "yes"){
						$rounded_price = round( ($price * (1-($result_res_disc[0]["amount"]/100)) ) /10) * 10;
						$additional_discount = $price - $rounded_price;
					}
				}
				$sql = "UPDATE `reservation` SET `discount` = (IFNULL(`discount`, 0)+".$additional_discount.") WHERE `reservation_id` = '".$res_id."'";
				$this->execute_update($sql); # Add the discount
			}

			return "success";
	    } catch(PDOException $e) {
	        return "failed";
	    }
	}
	public function get_res_special_discount($booking_source_id, $date_start_id, $date_end_id, $room_type_id, $pax){

		try {
			$date_created = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));

	        $sql = "SELECT a.`name` as name, a.`type` as type, a.`amount` as amount, a.`date_start_id` as promo_date_start_id, a.`date_end_id` as promo_date_end_id, b.`isRound`
					FROM `res_discounts` a LEFT JOIN `res_discounts_bking_src` b ON b.`res_discount_id` = a.`id`
					WHERE CAST(a.`created_date` AS date) <= CAST('".$date_created."' AS date) AND
						  (
							  (a.`date_start_id` <= '".$date_start_id."' AND a.`date_end_id` >= '".$date_start_id."') OR
							  (a.`date_start_id` <= '".$date_end_id."' AND a.`date_end_id` >= '".$date_end_id."') OR
							  (a.`date_start_id` >= '".$date_start_id."' AND a.`date_start_id` <= '".$date_end_id."') OR
							  (a.`date_end_id` >= '".$date_start_id."' AND a.`date_end_id` <= '".$date_end_id."')
						  ) AND
						  b.`booking_source_id` = '".$booking_source_id."' AND
						  a.`status` = 'active' AND
						  b.`status` = 'active'";
			$result_res_disc = $this->executeQuery($sql); # get promo discount.
			$additional_discount = 0; # initial value for the additional discount
			if(count($result_res_disc) > 0){ #
				$sql = "SELECT `associated_column`
						FROM `room_types`
						WHERE `room_type_id` = '".$room_type_id."' AND `status` = 'active'";
				$result_room_types= $this->executeQuery($sql);
				$associated_column = $result_room_types[0]["associated_column"];
				// ----- HTL-985 START
				$tmp_associated_column = $associated_column."_pax_".$pax;
				$sql = "SHOW COLUMNS FROM `periods` WHERE Field = '$tmp_associated_column'";
				$periods_column = $this->executeQuery($sql);
				if( count($periods_column) > 0 ){ $associated_column = $tmp_associated_column; }
				// ------HTL-985 END : we've added 'pax' parameter in this function
				if($result_res_disc[0]["type"] == 'fixed'){ # fixed amount
					for($x=$date_start_id; $x<=$date_end_id; $x++){
						$temp_period_id = $x; # focused date
						if($result_res_disc[0]["promo_date_start_id"] <= $temp_period_id && $result_res_disc[0]["promo_date_end_id"] >= $temp_period_id){
							$additional_discount += $result_res_disc[0]["amount"]; # total the additional discount
						}
					}
				}
				else{ # percentage amount based on daily room rate
					$price = 0; # initial room price
					for($x=$date_start_id; $x<=$date_end_id; $x++){
						$temp_period_id = $x; # focused date
						if($result_res_disc[0]["promo_date_start_id"] <= $temp_period_id && $result_res_disc[0]["promo_date_end_id"] >= $temp_period_id){
							$sql = "SELECT *
									FROM `periods`
									WHERE `periods_id` = '".$temp_period_id."'";
							$result_period = $this->executeQuery($sql);
							$price += $result_period[0][$associated_column];
						}
					}
					$additional_discount = $price*($result_res_disc[0]["amount"]/100); # total the additional discount

					if($result_res_disc[0]['isRound'] == "yes"){
						$rounded_price = round( ($price * (1-($result_res_disc[0]["amount"]/100)) ) /10) * 10;
						$additional_discount = $price - $rounded_price;
					}
				}
			}

			return $additional_discount;
	    } catch(PDOException $e) {
	        return 10;
	    }
	}


	/* REVISED REPORT FUNCTIONS 
	===================================================== */
	public function rep_reservation_list ($date_start, $date_end){

		$date_start_id 	= $this->getIdperiod($date_start, "start_date");
		$date_end_id 	= $this->getIdperiod($date_end, "start_date");
		$sql 			= "SELECT resv.reservation_conn_id,
						resv.reservation_id,
						CONCAT(COALESCE(client.surname, ''), ', ', COALESCE(client.name,'')) client_name,
						rescon.bookingsource_id, bksource.booking_source_name, bksource.icon_src, bksource.commission_paid,
						(SELECT periods.start_date FROM periods WHERE periods.periods_id = resv.date_start_id) checkin,
						(SELECT periods.start_date FROM periods WHERE periods.periods_id = resv.date_end_id) checkout,
						resv.appartments_id room_id,
						(SELECT room.apartment_name FROM apartments room WHERE room.apartment_id = resv.appartments_id) room_name,
						resv.appartments_id room_num,
						resv.rate_total booking_price,
						resv.deposit,		
						resv.discount,	
						resv.commissions,
						resv.tax_perc,			
						COALESCE(SUM(payment.paid), 0) recieved,
						((resv.date_end_id - resv.date_start_id) + 1) num_nights,
						resv.checkin date_checkin,
						resv.checkout date_checkout,
						resv.status entry_status,
						GROUP_CONCAT(
							CONCAT_WS(
								',',
								extra_itm.type,
								extra_itm.value,
								extra_itm.days,
								extra_itm.quantity,
								extra_itm.discount
								)
						SEPARATOR '|') extras_item
						FROM  reservation resv 
						INNER JOIN reservation_conn rescon ON resv.reservation_conn_id = rescon.reservation_conn_id
						LEFT JOIN clients client ON rescon.client_id = client.clients_id
						LEFT JOIN booking_source bksource ON rescon.bookingsource_id = bksource.booking_source_id
						LEFT JOIN reservation_costs extra_itm ON resv.reservation_id = extra_itm.reservation_id
						LEFT JOIN res_payment_history payment ON resv.reservation_id = payment.reservation_id
						WHERE resv.status = 'active'
						AND (((resv.date_start_id <= '$date_start_id' AND resv.date_end_id >= '$date_start_id') OR (resv.date_start_id <= '$date_end_id' AND resv.date_end_id >= '$date_end_id'))
							OR ((resv.date_start_id >= '$date_start_id' AND resv.date_start_id <= '$date_end_id') OR (resv.date_end_id >= '$date_start_id' AND resv.date_end_id <= '$date_end_id')))
						GROUP BY resv.reservation_id";
		$result = $this->executeQuery($sql);
		foreach($result as $listkey => $r_list):
			if($r_list['commission_paid'] == 'yes'):
				$result[$listkey]['booking_price'] 	-= $r_list['commissions'];
				$result[$listkey]['recieved'] 		-= $r_list['commissions'];
			endif;

			if($r_list['date_checkin'] != null 		&& $r_list['date_checkout'] == null):
				$btn_skin 		= 'c-skin-5db85b';
				$booking_status = 'Checked In';
			elseif($r_list['date_checkin'] != null 	&& $r_list['date_checkout'] != null):
				$btn_skin 		= 'c-skin-94989b';
				$booking_status = 'Checked Out';
			elseif($r_list['date_checkin'] == null 	&& $r_list['date_checkout'] == null):
				$btn_skin 		= 'c-skin-fdaf17';
				$booking_status = 'Pending';
			endif;
			
			$extras_amount 		= 0;
			$discount_amount 	= 0;
			if($r_list['extras_item'] != null):
				$extras_item = explode('|', $r_list['extras_item']);
				foreach($extras_item as $itmkey => $itm_data):
					$itm_data_split = explode(',', $itm_data);
					if($itm_data_split[0] == 'sg'):
						$extras_amount 		+= (($itm_data_split[1] - $itm_data_split[4]) * $itm_data_split[3]);
						$discount_amount	+= $itm_data_split[4];
					else:
						$extras_amount 		+= (($itm_data_split[1] - $itm_data_split[4]) * ($itm_data_split[2] * $itm_data_split[3]));
						$discount_amount	+= $itm_data_split[4];
					endif;					
				endforeach;
			endif;
			$result[$listkey]['client_name'] 	= ($r_list['client_name'] != '') ? $r_list['client_name'] : ' -:- ';
			$result[$listkey]['recieved'] 		= ($r_list['recieved'] != null) ? $r_list['recieved'] : 0;
			$result[$listkey]['btn_skin'] 		= $btn_skin;
			$result[$listkey]['booking_status'] = $booking_status;
			$result[$listkey]['extra_charges'] 	= $extras_amount;
			$result[$listkey]['extra_discount']	= $discount_amount;
			// $result[$listkey]['total_cost'] 	= ($result[$listkey]['booking_price'] + $result[$listkey]['extra_charges']);
			$result[$listkey]['total_cost'] 	= $result[$listkey]['booking_price'];

		endforeach;		
		return $result;
	}


	public function rep_occupancy_rate ($date_start, $date_end){
		$date_start_id 		= $this->getIdperiod($date_start, "start_date");
		$date_end_id 		= $this->getIdperiod($date_end, "start_date");

		$occupancy_count 	= "SELECT date_start_id, date_end_id FROM reservation 
								WHERE status = 'active'
								AND (((date_start_id <= '$date_start_id' AND date_end_id >= '$date_start_id') OR (date_start_id <= '$date_end_id' AND date_end_id >= '$date_end_id'))
								OR ((date_start_id >= '$date_start_id' AND date_start_id <= '$date_end_id') OR (date_end_id >= '$date_start_id' AND date_end_id <= '$date_end_id')))";
		$occupancy_count	= $this->executeQuery($occupancy_count);
		$occupied_sum   	= 0;
		foreach($occupancy_count as $ocpkey => $ocpdata):
			for($i_n = $ocpdata['date_start_id']; $i_n <= $ocpdata['date_end_id']; $i_n++){				
				if(($i_n >= $date_start_id) && ($i_n <= $date_end_id)) $occupied_sum++;
			}
		endforeach;


		$transfer_count 	= "SELECT date_start_id, date_end_id FROM transfer_room_history 
								WHERE status = 'active'
								AND `transfer_status` = 'checkin' AND `rate_total` != 0
								AND (((date_start_id <= '$date_start_id' AND date_end_id >= '$date_start_id') OR (date_start_id <= '$date_end_id' AND date_end_id >= '$date_end_id'))
								OR ((date_start_id >= '$date_start_id' AND date_start_id <= '$date_end_id') OR (date_end_id >= '$date_start_id' AND date_end_id <= '$date_end_id')))";
		$transfer_count	= $this->executeQuery($transfer_count);
		foreach($transfer_count as $trfkey => $trfdata):
			for($i_n = $trfdata['date_start_id']; $i_n <= $trfdata['date_end_id']; $i_n++){				
				if(($i_n >= $date_start_id) && ($i_n <= $date_end_id)) $occupied_sum++;
			}
		endforeach;


		/* INCOME REPORT ------ */
		$blocked_count 	= "SELECT blocking_start_date_id, blocking_end_date_id FROM blocking 
								WHERE status = 'active'
								AND (((blocking_start_date_id <= '$date_start_id' AND blocking_end_date_id >= '$date_start_id') OR (blocking_start_date_id <= '$date_end_id' AND blocking_end_date_id >= '$date_end_id'))
								OR ((blocking_start_date_id >= '$date_start_id' AND blocking_start_date_id <= '$date_end_id') OR (blocking_end_date_id >= '$date_start_id' AND blocking_end_date_id <= '$date_end_id')))";
		$blocked_count	= $this->executeQuery($blocked_count);
		$blocked_sum   	= 0;
		foreach($blocked_count as $blckey => $blcdata):
			for($b = $blcdata['blocking_start_date_id']; $b <= $blcdata['blocking_end_date_id']; $b++){				
				if(($b >= $date_start_id) && ($b <= $date_end_id)) $blocked_sum++;
			}
		endforeach;
		/* INCOME REPORT ------ */

		
		$count_rooms 		= "SELECT COALESCE(COUNT(`apartment_id`), 0) as totRoom FROM `apartments`";
		$count_rooms 		= $this->db->prepare($count_rooms); $count_rooms->execute();
		$count_rooms 		= $count_rooms->fetch( PDO::FETCH_ASSOC );
		$occupancy_rate 	= ($occupied_sum / ((abs($date_end_id - $date_start_id) + 1) * $count_rooms['totRoom'])) * 100;
		$available_room   	= $count_rooms['totRoom'] - ($occupied_sum + $blocked_sum);
		return array(
			'percentage' => round($occupancy_rate),
			'count_room' => $count_rooms['totRoom'],
			'occupied' 	 => $occupied_sum,
			'blocked' 	 => $blocked_sum,
			'days' 	 	 => (abs($date_end_id - $date_start_id) + 1),
			'date_day' 	 => $date_end. '/'. $date_end_id . ' - '. $date_start .'/'. $date_start_id,
			'date_id'    => $date_start_id .' - '. $date_end_id,
			'available_room' => ($available_room >= 0) ? $available_room : 0, 
		);
	}


	public function rep_reservation_summary ($date_start, $date_end) {
		$date_start_id 		= $this->getIdperiod($date_start, "start_date");
		$date_end_id 		= $this->getIdperiod($date_end, "start_date");
		$res_summary 		= "SELECT r.reservation_id, r.checkin, r.checkout, r.status,
								(SELECT CONCAT_WS(' ', c.surname, c.name) FROM clients c WHERE c.clients_id = r.clients_id LIMIT 0, 1) clientname,
								r.rate_total, rc.bookingsource_id, rc.reference_num,
								(SELECT CONCAT_WS('|', b.booking_source_name, b.icon_src)
									FROM booking_source b WHERE b.booking_source_id = rc.bookingsource_id LIMIT 0, 1) booking_source_detail,
								(SELECT ps.start_date FROM periods ps WHERE ps.periods_id = r.date_start_id LIMIT 0, 1) date_start,
								(SELECT pe.start_date FROM periods pe WHERE pe.periods_id = r.date_end_id LIMIT 0, 1) date_end,
								ap.apartment_name,
								(SELECT rtp.name FROM room_types rtp WHERE rtp.room_type_id = ap.roomtype_id LIMIT 0, 1) room_type
								FROM reservation r
								LEFT JOIN reservation_conn rc ON r.reservation_conn_id = rc.reservation_conn_id
								LEFT JOIN apartments ap ON r.appartments_id = ap.apartment_id
								WHERE r.status IN ('no_show', 'cancelled', 'active')
								AND (((r.date_start_id <= '$date_start_id' AND r.date_end_id >= '$date_start_id') OR (r.date_start_id <= '$date_end_id' AND r.date_end_id >= '$date_end_id'))
								OR ((r.date_start_id >= '$date_start_id' AND r.date_start_id <= '$date_end_id') OR (r.date_end_id >= '$date_start_id' AND r.date_end_id <= '$date_end_id')))
								";
		$res_summary		= $this->executeQuery($res_summary);
		$checked_in 			= 0;
		$checked_out			= 0;
		$cancel_reservation 	= 0;
		$noshow_reservation 	= 0;
		$pending_reservation 	= 0;
		$checked_in_list 			= [];
		$checked_out_list 			= [];
		$cancel_reservation_list 	= [];
		$noshow_reservation_list 	= [];
		$pending_reservation_list 	= [];

		foreach($res_summary as $smmrykey => $smmrydata):
			$booking_source_detail  = explode('|', $smmrydata['booking_source_detail']);
			$smmrydata['booking_source_name'] 	= $booking_source_detail[0];
			$smmrydata['icon_src'] 				= $booking_source_detail[1];
			unset($smmrydata['booking_source_detail']);
			if($smmrydata['status'] == 'cancelled'){
				$cancel_reservation_list[] 		= $smmrydata; // ----
				$cancel_reservation++;
			}
			if($smmrydata['status'] == 'no_show'){
				$noshow_reservation_list[] 		= $smmrydata; // ----
				$noshow_reservation++;
			}
			if($smmrydata['status'] == 'active'){
				if($smmrydata['checkin'] != null):
					$checked_in_list[] 			= $smmrydata; // ----
					$checked_in++;
				endif;
				if($smmrydata['checkout'] != null):
					$checked_out_list[] 		= $smmrydata; // ----
					$checked_out++;
				endif;
				if($smmrydata['checkin'] == null && $smmrydata['checkout'] == null):
					$pending_reservation_list[] = $smmrydata; // ----
					$pending_reservation++;
				endif;
			}
		endforeach;
		return [
			'res_summary'	=> $res_summary,
			'res_count'		=> count($res_summary),
			'checked_in' 	=> $checked_in,
			'checked_out' 	=> $checked_out,
			'no_show' 		=> $noshow_reservation,
			'cancelled' 	=> $cancel_reservation, 
			'pending'		=> $pending_reservation,
			'checked_in_list' 	=> $checked_in_list,
			'checked_out_list'	=> $checked_out_list,
			'no_show_list'		=> $noshow_reservation_list,
			'cancelled_list'	=> $cancel_reservation_list,	
			'pending_list' 		=> $pending_reservation_list,
		];

	}


	public function rep_bookingsource_percentage ($reservation_list) {
		$booking_source = "SELECT booking_source_id, booking_source_name FROM booking_source WHERE status = 'active' ORDER BY booking_source_id ASC";
		$booking_source	= $this->executeQuery($booking_source);
		$booking_source_ii = $booking_source_percentage = []; 
		foreach($booking_source as $rowkey => $sourcedata):
			$booking_source_ii[$sourcedata['booking_source_id']] = array(
				'name' 		=> $sourcedata['booking_source_name'],
				'count' 	=> 0,
				'amount' 	=> 0,
			);
		endforeach;

		foreach($reservation_list as $keyreservation => $datareservation):
			if(isset($booking_source_ii[$datareservation['bookingsource_id']])):
				$booking_source_ii[$datareservation['bookingsource_id']]['count'] += 1;
				$booking_source_ii[$datareservation['bookingsource_id']]['amount'] += (float) $datareservation['total_cost'];
			endif;
		endforeach;

		foreach($booking_source_ii as $srcii_key => $srcii_data):
			$percentage_ = ($srcii_data['count'] > 0) ? ($srcii_data['count'] / count($reservation_list)) * 100 : 0;
			$booking_source_percentage[] = 	array(
												'id_src' 	=> $srcii_key,	
												'name' 		=> $srcii_data['name'],
												'percent' 	=> round($percentage_),
												'amount'  	=> $srcii_data['amount'],
											);
		endforeach;		

		return $booking_source_percentage;
	}


	public function getcurrent_YearASIGN ($active_years, $year = null) {
		$year_ = ($year != null) ? $year : $this->getCurrent_Year();
		$current_year 	= array('active_year_index' => 0, 'current_year' => $year_);
		foreach ($active_years as $actkey => $activeyear):
			if($activeyear['years_id'] == $current_year['current_year']):
				$current_year['active_year_index'] = $actkey;
				break;
			endif;
		endforeach;
		return $current_year;
	}



	public function inventory_ii_items ($stckroom_id = 'all', $category_id = 'all', $stock_status = 'all'){		
		$this->db->query("SET group_concat_max_len = 20000000");
		$stockroom_wh 	= ($stckroom_id == 'all') ? "rel.stockroom_id != ''" : "rel.stockroom_id = ". $stckroom_id;
		$category_wh 	= ($category_id == 'all') ? "invt.items_inventory_category_id != ''" : "invt.items_inventory_category_id = ". $category_id;

		if($stock_status == 'stocklow'):
			$stock_statusST = "(rel.quantity < rel.quantity_min AND rel.quantity != 0)"; 	
		elseif($stock_status == 'stockout'):
			$stock_statusST = "rel.quantity <= 0 ";
		elseif($stock_status == 'stockright'):
			$stock_statusST = "rel.quantity >= rel.quantity_min";
		else:
			$stock_statusST = "rel.quantity >= 0";
		endif;

		$sql = "SELECT invt.*, 
				ctg.category_name,
				rel.quantity quantity_in ,
				rel.quantity_min,
				rel.stockroom_id,
				(SELECT GROUP_CONCAT(
							CONCAT_WS(
								',',
								alci.item_price,
								alci.currency_rate,
								alci.item_qty
								)
						SEPARATOR '|')  FROM items_allocation alci 
					WHERE alci.msfl_id = invt.items_id AND alci.item_allocation = 'IN') allocation_data,
				(SELECT SUM(alcii.item_qty) FROM items_allocation alcii 
					WHERE alcii.msfl_id = invt.items_id AND alcii.item_allocation = 'OUT' AND alcii.stockroom_from = rel.stockroom_id) quantity_out,
				(SELECT stc.stockroom_name FROM stockrooms stc
				WHERE stc.stockrooms_id = rel.stockroom_id) stockroom_name				
				FROM items_inventory invt 
				LEFT JOIN items_inventory_category ctg ON invt.items_inventory_category_id = ctg.category_id
				LEFT JOIN relinventory rel ON invt.items_id = rel.good_inventory_id
				WHERE ". $stockroom_wh ."
				AND ". $category_wh ."
				AND ". $stock_statusST;
		$result	= $this->executeQuery($sql);

		foreach($result	as $row => $qdata){
			$item_cost 							= 0;
			$result[$row]['item_cost_reg'] 		= 0;
			foreach(explode('|', $qdata['allocation_data']) as $i_row => $i_qdata):
				$i_qdata 						= explode(',', $i_qdata);
				$item_cost 						= (((float)  $i_qdata[0] * (int) $i_qdata[1]) * (int) $i_qdata[2]);
				$result[$row]['item_cost_reg']  =  $i_qdata[0];
			endforeach;
			$item_status_color 					= '#5eb759';
			$item_status 						= 'Sufficient';
			if($qdata['quantity_in'] <= 0):
				$item_status_color 				= '#ba968f';
				$item_status 					= 'Out of Stock';
			elseif($qdata['quantity_in'] < $qdata['quantity_min']):
				$item_status_color 				= '#cfc597';
				$item_status 					= 'Low Stock';
			endif;
			$result[$row]['item_type_reg'] 		= $qdata['item_type'];
			$result[$row]['item_type'] 			= ucfirst($qdata['item_type']);
			$result[$row]['item_cost'] 			= number_format($item_cost, 2, '.', '');
			$result[$row]['item_status'] 		= $item_status;
			$result[$row]['item_status_color'] 	= $item_status_color;
			$result[$row]['quantity_out'] 		= (int) $qdata['quantity_out'];
			$result[$row]['description_skip'] 	= strlen($qdata['item_description']) > 25 ? substr($qdata['item_description'], 0, 25) . '...' :  ($qdata['item_description']);
			unset($result[$row]['allocation_data']);
		}

		return $result;

	}


	public function inventory_ii_items_setitemize ($item_id, $stckroom_id = 4){		
		$this->db->query("SET group_concat_max_len = 20000000");
		$stockroom_wh 	= ($stckroom_id == 'all') ? "rel.stockroom_id != ''" : "rel.stockroom_id = ". $stckroom_id;
		$sql = "SELECT invt.*, 
				ctg.category_name,
				rel.quantity quantity_in ,
				rel.quantity_min,
				rel.stockroom_id,
				(SELECT GROUP_CONCAT(
							CONCAT_WS(
								',',
								alci.item_price,
								alci.currency_rate,
								alci.item_qty
								)
						SEPARATOR '|')  FROM items_allocation alci 
					WHERE alci.msfl_id = invt.items_id AND alci.item_allocation = 'IN') allocation_data,

				(SELECT SUM(alcii.item_qty) FROM items_allocation alcii 
					WHERE alcii.msfl_id = invt.items_id AND alcii.item_allocation = 'OUT' AND alcii.stockroom_from = rel.stockroom_id) quantity_out,
				(SELECT stc.stockroom_name FROM stockrooms stc
				WHERE stc.stockrooms_id = rel.stockroom_id) stockroom_name				
				FROM items_inventory invt 
				LEFT JOIN items_inventory_category ctg ON invt.items_inventory_category_id = ctg.category_id
				LEFT JOIN relinventory rel ON invt.items_id = rel.good_inventory_id
				WHERE " . $stockroom_wh ."
				AND invt.items_id = $item_id";
		$result	= $this->executeQuery($sql);

		foreach($result	as $row => $qdata){
			$item_cost 							= 0;
			$result[$row]['item_cost_reg'] 		= 0;
			foreach(explode('|', $qdata['allocation_data']) as $i_row => $i_qdata):
				$i_qdata 						= explode(',', $i_qdata);
				$item_cost 						= (((float)  $i_qdata[0] * (int) $i_qdata[1]) * (int) $i_qdata[2]);
				$result[$row]['item_cost_reg']  =  $i_qdata[0];
			endforeach;
			$item_status_color 					= 'green';
			$item_status 						= 'Sufficient';
			if($qdata['qauntity_in'] <= 0):
				$item_status_color 				= 'red';
				$item_status 					= 'Out of Stock';
			elseif($qdata['qauntity_in'] < $qdata['quantity_min']):
				$item_status_color 				= 'orange';
				$item_status 					= 'Low Stock';
			endif;
			$result[$row]['item_type_reg'] 		= $qdata['item_type'];
			$result[$row]['item_type'] 			= ucfirst($qdata['item_type']);
			$result[$row]['item_cost'] 			= number_format($item_cost, 2, '.', '');
			$result[$row]['item_status'] 		= $item_status;
			$result[$row]['item_status_color'] 	= $item_status_color;
			$result[$row]['quantity_out'] 		= (int) $qdata['quantity_out'];
			$result[$row]['description_skip'] 	= strlen($qdata['item_description']) > 25 ? substr($qdata['item_description'], 0, 25) . '...' :  ($qdata['item_description']);
			unset($result[$row]['allocation_data']);
		}

		return (!empty($result)) ? $result[0] : $result;

	}



	public function inventory_ii_additems ($item_rows){
		$dbh 		= $this->db;
		$item_invt 	= $dbh->prepare("INSERT INTO items_inventory(`item_name`,
						`item_code`,
						`item_description`,
						`item_type`,
						`inserted_date`,
						`inserted_host`,
						`inserted_by`,
						`items_inventory_category_id`) 
					VALUES(:itemname,
						:itemcode,
						:itemdescription,
						:itemtype,
						:inserteddate,
						:insertedhost,
						:insertedby,
						:itemsinventory_category_id)");

		foreach($item_rows['data'] as $itemkey => $item) { 
			$item_invt->execute(array(
						':itemname'						=> $item['name'],
						':itemcode'						=> $item['code'],
						':itemdescription'				=> $item['description'],
						':itemtype'						=> $item['inventorytype'],
						':inserteddate'					=> date('Y-m-d H:i:s'),
						':insertedhost'					=> 'ssv.hmgr.io',
						':insertedby'					=> $item_rows['id'],
						':itemsinventory_category_id' 	=> (int) $item['category'] 
					));
			$item_rows['data'][$itemkey]['msfl_id'] 	= $dbh->lastInsertId();
		};

		$allocation 	= $this->inventory_ii_additemallocation($item_rows);
		$relinventory 	= $this->inventory_ii_additemrelinventory($item_rows);
		return $allocation;

	}





	public function inventory_ii_additemallocation ($items_alc){
		$dbh 			= $this->db;
		$itemallocation = $dbh->prepare("INSERT INTO items_allocation(`stockroom_to`,
					`msfl_id`,
					`item_name`,
					`item_qty`,
					`item_price`,
					`currency_rate`,
					`item_allocation`,
					`trans_date`,
					`status`,
					`inventory_status`,
					`inserted_host`,
					`inserted_by`) 
					VALUES(:stockroom_to,
					:msfl_id,
					:item_name,
					:item_qty,
					:item_price,
					:currency_rate,
					:item_allocation,
					:trans_date,
					:status,
					:inventory_status,
					:inserted_host,
					:inserted_by)"); 
		foreach($items_alc['data'] as $itemkey => $item) { 
			$itemallocation->execute(array(
						':stockroom_to'		=> 4,
						':msfl_id'			=> $item['msfl_id'],
						':item_name' 		=> $item['name'],
						':item_qty' 		=> $item['quantity'],
						':item_price' 		=> $item['cost'],
						':currency_rate' 	=> 1.0,
						':item_allocation' 	=> 'IN',
						':trans_date' 		=> date('Y-m-d H:i:s'),
						':status' 			=> 'active',
						':inventory_status' => 'AVAILABLE',
						':inserted_host' 	=> 'ssv.hmgr.io',
						':inserted_by' 		=> $items_alc['id']
					));
		};
		return true;
	}


	public function inventory_ii_additemrelinventory ($items_alc){
		$dbh 			= $this->db;
		$itemallocation = $dbh->prepare("INSERT INTO relinventory(`good_inventory_id`,
					`stockroom_id`,
					`quantity`,
					`quantity_min`,
					`checkin_required`,
					`inserted_date`,
					`inserted_host`,
					`inserted_by`) 
					VALUES(:good_inventory_id,
					:stockroom_id,
					:quantity,
					:quantity_min,
					:checkin_required,
					:inserted_date,
					:inserted_host,
					:inserted_by)");

		foreach($items_alc['data'] as $itemkey => $item) { 
			$itemallocation->execute(array(
					':good_inventory_id' 	=> $item['msfl_id'],
					':stockroom_id' 		=> 4,
					':quantity' 			=> $item['quantity'],
					':quantity_min' 		=> 1,
					':checkin_required' 	=> 'n',
					':inserted_date' 		=> date('Y-m-d H:i:s'),
					':inserted_host'		=> 'ssv.hmgr.io',
					':inserted_by'			=> $items_alc['id']
					));
		}

		return true;
	}


	public function inventory_ii_items_reverse ($stckroom_id){	
		$sql = "SELECT invt.*,
				(SELECT SUM(rels.quantity) FROM relinventory rels WHERE rels.stockroom_id = 4 AND rels.good_inventory_id = invt.items_id) quantity_in
				FROM items_inventory invt 
				WHERE invt.items_id NOT IN (SELECT  rel.good_inventory_id FROM relinventory rel WHERE rel.stockroom_id = '". $stckroom_id ."') 
				GROUP BY invt.items_id";
		$result	= $this->executeQuery($sql);
		return $result;

	}


	public function detail_itemallocation ($msfl_id, $stockroom){
		$dbh  = $this->db;
		$sql_ = $dbh->prepare("SELECT alc.id,
				alc.msfl_id,
				alc.item_name,
				alc.item_qty,
				alc.item_price,
				alc.currency_rate,
				alc.item_allocation,
				alc.trans_date,
				alc.status,
				alc.deleted_at,
				alc.inventory_status,
				alc.inserted_host,
				alc.inserted_by,
				(COALESCE(SUM(alc.item_qty)) - (SELECT COALESCE(sum(alc_ii.item_qty),0) FROM items_allocation alc_ii
					WHERE alc_ii.msfl_id = $msfl_id  AND alc_ii.item_allocation = 'OUT' 
					AND alc_ii.stockroom_from = $stockroom AND alc_ii.deleted_at IS NULL)) qty_onhand, 
				(SELECT alc_i.item_price FROM items_allocation alc_i
					WHERE alc_i.msfl_id = $msfl_id AND alc_i.item_allocation = 'IN'
					AND alc_i.stockroom_to = $stockroom AND alc_i.deleted_at IS NULL
					ORDER BY alc_i.id DESC LIMIT 0, 1) currentcost
				FROM items_allocation alc
				WHERE alc.msfl_id = $msfl_id AND alc.item_allocation = 'IN'
				AND alc.deleted_at IS NULL
				AND alc.stockroom_to = $stockroom
				GROUP BY alc.msfl_id");	
		$sql_->execute();
		$result = $sql_->fetch();
		return $result;
	}


	public function item_direct_quantity ($item_id, $stockroom_id) {
		$dbh  = $this->db;
		$sql_ = $dbh->prepare("SELECT COUNT(stockroom_id) count, COALESCE(SUM(quantity)) quantity
			FROM relinventory WHERE good_inventory_id = $item_id
			AND stockroom_id = $stockroom_id
			AND appartment_id IS NULL");
		$sql_->execute();
		$result = $sql_->fetch();
		return array('count' => (int) $result['count'], 'quantity' => (int) $result['quantity']);
	}


	public function inventory_ii_additemallocation_random ($data, $items_alc, $stockroom_origin = 4){
		$dbh 			= $this->db;
		$itemallocation = $dbh->prepare("INSERT INTO items_allocation(`stockroom_to`,
					`stockroom_from`,
					`msfl_id`,
					`item_name`,
					`item_qty`,
					`item_price`,
					`currency_rate`,
					`item_allocation`,
					`trans_date`,
					`status`,
					`inventory_status`,
					`inserted_host`,
					`inserted_by`) 
					VALUES(:stockroom_to,
					:stockroom_from,
					:msfl_id,
					:item_name,
					:item_qty,
					:item_price,
					:currency_rate,
					:item_allocation,
					:trans_date,
					:status,
					:inventory_status,
					:inserted_host,
					:inserted_by)"); 

		$relinventory = '';

		foreach($data['data'] as $itemkey => $item) { 
			$alc_indec 		= $items_alc[(int)$item['name']];
			if($alc_indec != false):
				$qty_in 	= $item['quantity'];//($item['quantity'] <= $alc_indec['qty_onhand']) ? $item['quantity'] : $alc_indec['qty_onhand'];
				$qty_onhand = $alc_indec['qty_onhand'] - $qty_in;

				$itemallocation->execute(array(
						':stockroom_to' 	=> $data['stockroom_id'],
						':stockroom_from' 	=> $stockroom_origin,
						':msfl_id'			=> $alc_indec['msfl_id'],
						':item_name' 		=> $alc_indec['item_name'],
						':item_qty' 		=> $qty_in,
						':item_price' 		=> $alc_indec['currentcost'],
						':currency_rate' 	=> $alc_indec['currency_rate'],
						':item_allocation' 	=> 'OUT',
						':trans_date' 		=> date('Y-m-d H:i:s'),
						':status' 			=> 'active',
						':inventory_status' => 'TRANSFER',
						':inserted_host' 	=> 'ssv.hmgr.io',
						':inserted_by' 		=> $data['id']
					));

				$itemallocation->execute(array(
						':stockroom_to' 	=> $data['stockroom_id'],
						':stockroom_from' 	=> $stockroom_origin,
						':msfl_id'			=> $alc_indec['msfl_id'],
						':item_name' 		=> $alc_indec['item_name'],
						':item_qty' 		=> $qty_in,
						':item_price' 		=> $alc_indec['currentcost'],
						':currency_rate' 	=> $alc_indec['currency_rate'],
						':item_allocation' 	=> 'IN',
						':trans_date' 		=> date('Y-m-d H:i:s'),
						':status' 			=> 'active',
						':inventory_status' => 'TRANSFER',
						':inserted_host' 	=> 'ssv.hmgr.io',
						':inserted_by' 		=> $data['id']
					));


				$rel_i = $dbh->prepare("UPDATE relinventory SET quantity = (quantity - ". $qty_in ." ) 
							WHERE good_inventory_id = ". $alc_indec['msfl_id'] ." AND stockroom_id = ". $stockroom_origin);
				$rel_i->execute();


				if($this->check_existreliventory($alc_indec['msfl_id'], $data['stockroom_id']) > 0):

					$rel_ii = $dbh->prepare("UPDATE relinventory SET quantity = (quantity + ". $qty_in ." ) 
						WHERE good_inventory_id = ". $alc_indec['msfl_id'] ." AND stockroom_id = " . $data['stockroom_id']);
					$rel_ii->execute();

				else:
						
					$relinventory = "INSERT INTO relinventory(`good_inventory_id`, `stockroom_id`, `quantity`, `quantity_min`,
							`checkin_required`, `inserted_date`, `inserted_host`, `inserted_by`) 
						VALUES('". $alc_indec['msfl_id'] ."', '".$data['stockroom_id']."', '".$qty_in."', '1', 'n', '".date('Y-m-d')."', 'ssv.hmgr.io', '".$data['id']."')";
					$relinventory = $dbh->prepare($relinventory);		
					$relinventory->execute();				

				endif;

			endif;
		}
		return $relinventory;
	}



	public function check_existreliventory ($msfl_id, $stockroom_id){
		$dbh 			= $this->db;
		$sql_ 			= $dbh->prepare("SELECT COUNT(good_inventory_id) count_s FROM relinventory WHERE good_inventory_id = $msfl_id AND stockroom_id = $stockroom_id ");
		$sql_->execute();
		$result = $sql_->fetch();
		return $result['count_s'];
	}



	public function update_itemdetails_ii ($item_request){
		$dbh 				= $this->db;
		$item_update  		= $item_request['item_update'];
		$item_current  		= $item_request['item_current'];

		$items_inventory 	= $dbh->prepare("UPDATE items_inventory SET item_name = '".$item_update['name']."',
							item_code 					= '".$item_update['code']."',
							item_description			= '".$item_update['item_description']."',
							item_type 					= '".$item_update['inventorytype']."',
							inserted_by 				= '".$item_request['id']."',
							items_inventory_category_id = '".$item_update['category']."'
							WHERE items_id  			= ". $item_current['items_id']);

		$items_allocation 	= $dbh->prepare("UPDATE items_allocation SET item_name = '".$item_update['name']."',
							item_price 					= '".$item_update['item_cost']."'
							WHERE msfl_id  				= ". $item_current['items_id'] ."
							AND stockroom_to 			= ". $item_current['stockroom_id'] ."");

		$relinventory 		= $dbh->prepare("UPDATE relinventory SET stockroom_id = '".$item_update['stockroom']."',
							quantity_min 				= '".$item_update['quantity_min']."'
							WHERE good_inventory_id		= ". $item_current['items_id'] ."
							AND stockroom_id 			= ". $item_current['stockroom_id']);

		$items_inventory 	= $items_inventory->execute();
		$items_allocation 	= $items_allocation->execute();
		$relinventory 		= $relinventory->execute();

		return true;
	}


	public function delete_item_relinventory($item_request){
		$dbh 				= $this->db;
		$relinventory 		= $dbh->prepare("DELETE FROM relinventory 
							WHERE good_inventory_id		= ". $item_request['item_id'] ."
							AND stockroom_id 			= ". $item_request['stockroom_id']);
		$relinventory 		= $relinventory->execute();
		return $relinventory;
	}


	public function update_masterfilequantity($item_request){
		$item_update = $item_request['item'];
		$item_origin = $item_request['item_origin'];

		$dbh 				= $this->db;
		$relinventory 		= $dbh->prepare("UPDATE relinventory SET quantity = (quantity + ".$item_update['newquantity']."),
							quantity_min = '". $item_update['minimum'] ."' WHERE good_inventory_id = ". $item_origin['items_id'] ."
							AND stockroom_id =". $item_origin['stockroom_id'] ."");
		$itemallocation = $dbh->prepare("INSERT INTO items_allocation(`stockroom_to`,
					`stockroom_from`,
					`msfl_id`,
					`item_name`,
					`item_qty`,
					`item_price`,
					`currency_rate`,
					`item_allocation`,
					`trans_date`,
					`status`,
					`inventory_status`,
					`inserted_host`,
					`inserted_by`) 
					VALUES('". $item_origin['stockroom_id'] ."',
					'0',
					'". $item_origin['items_id'] ."',
					'". $item_origin['item_name'] ."',
					'". $item_update['newquantity'] ."',
					'1',
					'IN',
					'". $item_origin['item_cost_reg'] ."',
					'". date('Y-m-d H:i:s') ."',
					'active',
					'AVAILABLE',
					'ssv.hmgr.io',
					'". $item_request['id'] ."')"); 

		$relinventory 		= $relinventory->execute();
		$itemallocation 	= $itemallocation->execute();		
		$update_bug 		= $dbh->prepare("UPDATE items_allocation SET currency_rate = '1', item_allocation = 'IN'
								WHERE id = ". $dbh->lastInsertId()); 
		$update_bug->execute();	

		return true;
	}




	public function stockroom_list_ii (){
		$this->db->query("SET group_concat_max_len = 20000000");
		$sql = "SELECT a.stockrooms_id,
			a.stockroom_name, 
			a.code_stockroom, 
			a.description_stockroom, 
			a.floor_number, 
			a.house_number, 
			a.inserted_date, 
			a.inserted_host, 
			a.inserted_by, 
			b.username, 
			COUNT(c.stockroom_id) AS count_of_inventory, 
			GROUP_CONCAT(CONCAT_WS(
						',',
						c.good_inventory_id,
						c.quantity,
						c.quantity_min,
						(SELECT itm.item_name FROM items_inventory itm WHERE itm.items_id = c.good_inventory_id LIMIT 0, 1),
						(SELECT ctg.category_name FROM items_inventory itmi 
								LEFT JOIN items_inventory_category ctg ON itmi.items_inventory_category_id =  ctg.category_id
								WHERE itmi.items_id = c.good_inventory_id LIMIT 0, 1)
						) SEPARATOR '|') itembrkd
			FROM stockrooms a 
			LEFT JOIN users b ON b.users_id = a.inserted_by 
			LEFT JOIN relinventory c ON c.stockroom_id = a.stockrooms_id 
			GROUP BY a.stockrooms_id ORDER BY a.stockrooms_id ASC";
			$result	= $this->executeQuery($sql);
			foreach($result as $stcrow => $stcdata):
				$result[$stcrow]['stock_zero'] 			= 0; 
				$result[$stcrow]['lowstock'] 			= 0; 
				$result[$stcrow]['outofstock'] 			= 0; 
				$result[$stcrow]['reimburse_stock'] 	= [];
				$split_stcdata = explode('|', $stcdata['itembrkd']);
				foreach($split_stcdata as $splrow => $spldata):
					$split_spldata = explode(',', $spldata);
					if(((int) $split_spldata[1] < (int) $split_spldata[2]) && $split_spldata[0] > 0){
						$result[$stcrow]['stock_zero']++;
						$result[$stcrow]['reimburse_stock'][] = $split_spldata[0];

						if($split_spldata[1] == 0):
							$result[$stcrow]['outofstock']++;
						else:
							$result[$stcrow]['lowstock']++;
						endif;
					}
				endforeach;
			endforeach;
			return $result;

	}


	public function item_transaction_logs ($stockroom_id, $item_id, $strt_date, $end_date){
		$sql_ = "SELECT alc.*, 
		(SELECT scfrm.stockroom_name FROM stockrooms scfrm WHERE scfrm.stockrooms_id = alc.stockroom_from) stckfrom_name,
		(SELECT scto.stockroom_name FROM stockrooms scto WHERE scto.stockrooms_id = alc.stockroom_to) stckto_name
		FROM items_allocation alc
		WHERE alc.msfl_id = $item_id
		AND (alc.stockroom_from = $stockroom_id OR alc.msfl_id = $item_id)
		AND (alc.trans_date BETWEEN '$strt_date' AND '$end_date')
		ORDER BY alc.id ASC";
		$result	= $this->executeQuery($sql_);
		return $result;
	}


	public function roomitem_transaction_logs ($apartment_id, $item_id, $strt_date, $end_date){
		$sql_ = "SELECT alc.*, 
			(SELECT scfrm.apartment_name FROM apartments scfrm WHERE scfrm.apartment_id = alc.apartment_from) apartment_fromname,
			(SELECT scto.apartment_name FROM apartments scto WHERE scto.apartment_id = alc.apartment_to) apartment_toname
			FROM roomitems_allocation alc
			WHERE alc.msfl_id = $item_id
			AND alc.apartment_from = $apartment_id
			AND (alc.trans_date BETWEEN '$strt_date' AND '$end_date')
			ORDER BY alc.id ASC";
		$result	= $this->executeQuery($sql_);
		return $result;
	}


	public function roomitem_stockroom_transaction_logs ($item_id, $strt_date, $end_date){
		$sql_ = "SELECT alc.*, 
			(SELECT scfrm.apartment_name FROM apartments scfrm WHERE scfrm.apartment_id = alc.apartment_from) apartment_fromname,
			(SELECT scto.apartment_name FROM apartments scto WHERE scto.apartment_id = alc.apartment_to) apartment_toname
			FROM roomitems_allocation alc
			WHERE alc.msfl_id = $item_id
			AND (alc.trans_date BETWEEN '$strt_date' AND '$end_date')
			ORDER BY alc.id ASC";
		$result	= $this->executeQuery($sql_);
		return $result;
	}


	public function list_apartment (){
		$this->db->query("SET group_concat_max_len = 20000000");
		$sql_ = "SELECT apr.*,
				typ.name type_name,
				GROUP_CONCAT(CONCAT_WS('<?>',
					rel.good_inventory_id,
					(SELECT itm.item_name FROM items_inventory itm WHERE itm.items_id = rel.good_inventory_id LIMIT 0, 1),
					rel.quantity,
					rel.quantity_min,

					(SELECT COALESCE(SUM(rmalc.item_qty),0) FROM roomitems_allocation rmalc
							WHERE rmalc.msfl_id 		= rel.good_inventory_id
							AND rmalc.apartment_to 		= apr.apartment_id
							AND rmalc.item_allocation 	= 'IN'
							AND rmalc.deleted_at IS NULL),

					(SELECT COALESCE(SUM(rmalc_ii.item_qty),0) FROM roomitems_allocation rmalc_ii
							WHERE rmalc_ii.msfl_id 			= rel.good_inventory_id
							AND rmalc_ii.apartment_to 		= apr.apartment_id
							AND rmalc_ii.item_allocation 	= 'OUT'
							AND rmalc_ii.deleted_at IS NULL),

					(SELECT ctg.category_name FROM items_inventory_category ctg 
							LEFT JOIN items_inventory itm_ctg ON ctg.category_id = itm_ctg.items_inventory_category_id
							WHERE itm_ctg.items_id = rel.good_inventory_id LIMIT 0, 1),			

					(SELECT GROUP_CONCAT(CONCAT_WS('<:>', clogs.id,
							clogs.apartment_from,
							clogs.apartment_to,
							clogs.msfl_id,
							clogs.item_name,
							clogs.item_qty,
							clogs.item_price,
							clogs.currency_rate,
							clogs.item_allocation,
							clogs.trans_date,
							clogs.status,
							clogs.deleted_at,
							clogs.inventory_status,
							clogs.inserted_host,
							clogs.inserted_by) SEPARATOR '<;>')
							FROM roomitems_allocation clogs WHERE clogs.msfl_id = rel.good_inventory_id
							AND (clogs.apartment_to = apr.apartment_id OR clogs.apartment_from = apr.apartment_id )
							AND clogs.deleted_at IS NULL
							ORDER BY clogs.id DESC),

					(SELECT COALESCE(t.item_type, '') FROM items_inventory t WHERE t.items_id = rel.good_inventory_id LIMIT 0, 1),
					(SELECT COALESCE(v.item_code, '') FROM items_inventory v WHERE v.items_id = rel.good_inventory_id LIMIT 0, 1),
					(SELECT COALESCE(u.item_description, '') FROM items_inventory u WHERE u.items_id = rel.good_inventory_id LIMIT 0, 1)

					) SEPARATOR '<|>') items
				FROM apartments apr
				LEFT JOIN relinventory rel ON rel.appartment_id = apr.apartment_id
				LEFT JOIN room_types typ ON typ.room_type_id = apr.roomtype_id
				WHERE rel.stockroom_id IS NULL
				GROUP BY apr.apartment_id
				ORDER BY apr.apartment_name";
		$result	= $this->executeQuery($sql_);
		foreach($result as $keyroom => $roomitems):
			$items_ 			= explode('<|>', $roomitems['items']);
			$items_data 		= array();
			$item_countissue 	= 0;
			$item_reimburse		= array();
			$category_list 		= array();

			foreach($items_ as $keyitems => $itemsdetais){
				$itemdetails_ii 	= explode('<?>', $itemsdetais);
				$logs_exp			= explode('<;>', $itemdetails_ii[6]);
				$logs_list 			= array();
				if(isset($itemdetails_ii[7])){
					foreach($logs_exp as $kylog => $logs_data){
						$logs_data_exp 	= explode('<:>', $logs_data);
						$logs_list[] 	= array(
							'id' 				=> $logs_data_exp[0],
							'apartment_from'	=> $logs_data_exp[1],
							'apartment_to'		=> $logs_data_exp[2],
							'msfl_id'			=> $logs_data_exp[3],
							'item_name' 		=> $logs_data_exp[4],
							'item_qty' 			=> $logs_data_exp[5],
							'item_price' 		=> $logs_data_exp[6],
							'currency_rate' 	=> $logs_data_exp[7],
							'item_allocation' 	=> $logs_data_exp[8],
							'trans_date' 		=> $logs_data_exp[9],
							'status' 			=> $logs_data_exp[10],
							'deleted_at' 		=> $logs_data_exp[11],
							'inventory_status' 	=> $logs_data_exp[12],
							'inserted_host' 	=> $logs_data_exp[13],
							'inserted_by'		=> $logs_data_exp[14],
						);
					}
				}	

				$status_item = 'Sufficient';
				if((int) $itemdetails_ii[2] < (int) $itemdetails_ii[3]):
					$status_item = 'Low Stock';
				elseif((int) $itemdetails_ii[2] === 0):
					$status_item = 'Out of Stock';
				endif;

				$itemdetails_ii[6] = (strlen($itemdetails_ii[6]) <= 40 && $itemdetails_ii[6] != null) ? $itemdetails_ii[6] : 'Unallocated';
				if(!in_array($itemdetails_ii[6], $category_list)):
					$category_list[] = $itemdetails_ii[6];
				endif;

				$item_setdata 		= array(					
					'apartment_id' 	=> $roomitems['apartment_id'],
					'apartment_name'=> $roomitems['apartment_name'],
					'items_id' 		=> $itemdetails_ii[0],
					'item_name' 	=> $itemdetails_ii[1],
					'quantity'		=> $itemdetails_ii[2],
					'quantity_min' 	=> $itemdetails_ii[3],
					'quantity_in' 	=> $itemdetails_ii[4],
					'quantity_out' 	=> $itemdetails_ii[5],
					'item_logs' 	=> $logs_list,
					'item_category' => $itemdetails_ii[6],
					'item_type' 	=> ucfirst($itemdetails_ii[8]),
					'item_description' 	=> $itemdetails_ii[9],
					'item_code' 	=> $itemdetails_ii[10],
					'status'		=> $status_item,
				);
				$items_data[] 		= $item_setdata;
				$item_countissue  	= ((int) $itemdetails_ii[2] < (int) $itemdetails_ii[3]) ? $item_countissue + 1: $item_countissue;
				if((int) $itemdetails_ii[2] < (int) $itemdetails_ii[3]) $item_reimburse[] = $item_setdata;
			}
			$result[$keyroom]['items_data'] 		= $items_data;
			$result[$keyroom]['item_countissue'] 	= $item_countissue;
			$result[$keyroom]['item_reimburse'] 	= $item_reimburse;
			$result[$keyroom]['item_listcategory'] 	= $category_list;
			unset($result[$keyroom]['items']);
		endforeach;
		return $result;
	}


	public function room_inventory_nonexistingitem($apartment_id){
		$sql_ = "SELECT itm.items_id, itm.item_name, rel.quantity,  rel.quantity_min
		FROM items_inventory itm INNER JOIN relinventory rel ON itm.items_id = rel.good_inventory_id
		WHERE rel.stockroom_id = (SELECT g.value FROM global_variables g WHERE g.key = 'ivt_roomitem_stockroom')
		AND rel.good_inventory_id NOT IN (SELECT reli.good_inventory_id FROM relinventory reli WHERE reli.appartment_id = $apartment_id)
		AND rel.appartment_id IS NULL";
		$result	= $this->executeQuery($sql_);
		return $result;
	}


	/* APARTMENT ADD ITEM FUNCTION */
	public function apartment_additem ($_request){
		$dbh 			= $this->db;
		$relinventory 	= $dbh->prepare("INSERT INTO relinventory(`good_inventory_id`,
					`appartment_id`,
					`quantity`,
					`quantity_min`,
					`checkin_required`,
					`inserted_date`,
					`inserted_host`,
					`inserted_by`) 
					VALUES(:good_inventory_id,
					:appartment_id,
					:quantity,
					:quantity_min,
					:checkin_required,
					:inserted_date,
					:inserted_host,
					:inserted_by)");

		$roomallocation	= $dbh->prepare("INSERT INTO roomitems_allocation(`apartment_from`,
					`apartment_to`,
					`msfl_id`,
					`item_name`,
					`item_qty`,
					`item_price`,
					`currency_rate`,
					`item_allocation`,
					`trans_date`,
					`status`,
					`inventory_status`,
					`inserted_host`,
					`inserted_by`) 
					VALUES(:apartment_from,
					:apartment_to,
					:msfl_id,
					:item_name,
					:item_qty,
					:item_price,
					:currency_rate,
					:item_allocation,
					:trans_date,
					:status,
					:inventory_status,
					:inserted_host,
					:inserted_by)");

		$Stckrm_inventory 	= $dbh->prepare("UPDATE relinventory SET quantity = quantity - :item_transfer
								WHERE stockroom_id = (SELECT g.value FROM global_variables g WHERE g.key = 'ivt_roomitem_stockroom')
								AND good_inventory_id = :good_inventory_id2 ");

		foreach($_request['data']['form_data'] as $kydata => $item):

			$relinventory->execute(array(
				':good_inventory_id' 	=> $item['item_id']['items_id'],
				':appartment_id' 		=> $_request['data']['apartment_id'],
				':quantity' 			=> $item['stock'],
				':quantity_min' 		=> $item['item_id']['quantity_min'],
				':checkin_required' 	=> 'n',
				':inserted_date' 		=> date('Y-m-d H:i:s'),
				':inserted_host' 		=> 'ssv.hmgr.io',
				':inserted_by'  		=> $_request['id']
			));

			$roomallocation->execute(array(
				':apartment_from' 		=> $_request['data']['apartment_id'],
				':apartment_to' 		=> $_request['data']['apartment_id'],
				':msfl_id' 				=> $item['item_id']['items_id'],
				':item_name' 			=> $item['item_id']['item_name'],
				':item_qty' 			=> $item['stock'],
				':item_price' 			=> 1,
				':currency_rate' 		=> 1,
				':item_allocation' 		=> 'IN',
				':trans_date' 			=> date('Y-m-d H:i:s'),
				':status' 				=> 'active',
				':inventory_status' 	=> 'AVAILABLE',
				':inserted_host' 		=> 'ssv.hmgr.io',
				':inserted_by' 			=> $_request['id']
			));

			$Stckrm_inventory->execute(array(
				':item_transfer' 		=> $item['stock'],
				':good_inventory_id2' 	=> $item['item_id']['items_id']
			));
		endforeach;

		return true;
	}

	/* Get room item stockroom inventory */
	public function roomitem_stockroom_inventory($item_id){
		$dbh 	= $this->db;
		$item 	= $dbh->prepare("SELECT r.*,
					s.stockroom_name
					FROM relinventory r
					INNER JOIN stockrooms s ON r.stockroom_id = s.stockrooms_id
					WHERE r.good_inventory_id = '$item_id'
					AND r.stockroom_id = (SELECT g.value FROM global_variables g WHERE g.key = 'ivt_roomitem_stockroom')
					GROUP BY s.stockrooms_id LIMIT 0, 1");
		$item->execute();
		$item 	= $item->fetch();
		return $item;
	}


	/* Refill room item stock */
	public function refill_roomitem ($data){
		$item_transfer 	= (int) $data['refile_qty'];
		$item_details 	= $data['item_reference']['itemdetails'];
		$stockroom 		= $data['item_reference']['stockroom'];	
		$user_id 		= $data['id'];

		$dbh 			= $this->db;

		$Stckrm_inventory 	= $dbh->prepare("UPDATE relinventory SET quantity = quantity - ".  $item_transfer ."
								WHERE stockroom_id = '". $stockroom['stockroom_id'] ."' 
								AND good_inventory_id = '".  $stockroom['good_inventory_id'] ."' ");

		$Aprtmt_inventory 	= $dbh->prepare("UPDATE relinventory SET quantity = quantity + ".  $item_transfer ."
								WHERE appartment_id = '". $item_details['apartment_id'] ."' 
								AND good_inventory_id = '".  $item_details['items_id'] ."' ");

		$roomallocation	= $dbh->prepare("INSERT INTO roomitems_allocation(`apartment_from`,
				`apartment_to`,
				`msfl_id`,
				`item_name`,
				`item_qty`,
				`item_price`,
				`currency_rate`,
				`item_allocation`,
				`trans_date`,
				`status`,
				`inventory_status`,
				`inserted_host`,
				`inserted_by`) 
				VALUES('". $item_details['apartment_id'] ."',
				'". $item_details['apartment_id'] ."',
				'". $item_details['items_id'] ."',
				'". $item_details['item_name'] ."',
				'". $item_transfer ."',
				'1',
				'1',
				'IN',
				'". date('Y-m-d H:i:s') ."',
				'active',
				'AVAILABLE',
				'". $_SERVER['SERVER_NAME'] ."',
				'". $user_id ."')");

		$Stckrm_inventory->execute();
		$Aprtmt_inventory->execute();
		$roomallocation->execute();
		return true;
		
	}

	// ------------------------------------------------------------------------------------
	// -- New Integration Method ----------------------------------------------------------
	// ------------------------------------------------------------------------------------
	// ------------------------------------------------------------------------------------
	// ------------------------------------------------------------------------------------

	public function accounting_integration($integration_key = 'awen_integration'){
		$dbh 	= $this->db;
		$awen 	= $dbh->prepare("SELECT `value` FROM `global_variables` WHERE `key` = '". $integration_key ."'");
		$awen->execute();
		$awen 			= $awen->fetch();
		$integration 	= 0;
		if($awen['value']):
			$decode 		= json_decode($awen['value']);
			$integration 	= $decode->integration;
		endif;
		return $integration;
	}


	public function access_awen_cred($integration_key = 'awen_integration'){
		$dbh 	= $this->db;
		$awen 	= $dbh->prepare("SELECT `value` FROM `global_variables` WHERE `key` = '". $integration_key ."'");
		$awen->execute();
		$awen 	= $awen->fetch();
		$integration = 0;
		if($awen['value']):
			$integration 	= json_decode($awen['value']);
		endif;
		return $integration;
	}


	private function push_API_POST ($request_, $url_route){
		$cred  	 = $this->access_awen_cred('ssv_awen_integration'); 
		$uname 	 = $cred->auth->uname; 		
		$pword 	 = $cred->auth->pword; 		
		$ch      = curl_init( $cred->url . $url_route );
		$options = array(
						CURLOPT_POST           => true,
				        CURLOPT_SSL_VERIFYPEER => false,
				        CURLOPT_RETURNTRANSFER => true,
				        CURLOPT_USERPWD        => "{$uname}:{$pword}",
				        CURLOPT_HTTPHEADER     => array( "Content-type: application/x-www-form-urlencoded" ),
				        CURLOPT_POSTFIELDS     => http_build_query( $request_ )
				    );
		curl_setopt_array( $ch, $options );
		$resp 	= curl_exec( $ch );
		curl_close( $ch );
		$result = json_decode($resp, true);
		return $result;
	}

	public function awen_ssv_invoice_payment_cash($cashbox_id){
		$this->db->query("SET group_concat_max_len = 20000000");
		$dbh 			= $this->db;
		$cred  			= $this->access_awen_cred('ssv_awen_integration'); 
		if($cashbox_id != $cred->cashbox->hotel->cashbox_id):
			$cashbox 	= $cred->cashbox->restaurant;
			$trans_sec 	= 'restaurant';
			if($cashbox_id == $cred->cashbox->minimart->cashbox_id):
				$cashbox 	= $cred->cashbox->minimart;	
				$trans_sec 	= 'minimart';			
			endif;

			$csh_duration = $dbh->prepare("SELECT date_opened, date_closed FROM cashbox_log lg_i
				WHERE lg_i.cashbox_id = '". $cashbox_id ."' ORDER BY lg_i.cashbox_log_id DESC LIMIT 0, 1");
			$csh_duration->execute();
			$csh_duration = $csh_duration->fetch();

			$transaction  = "SELECT c.*,
							GROUP_CONCAT(
								CONCAT_WS(',',
							  	i.item_id,
							  	i.item_qty,
							  	i.discount_id,
							  	i.item_price,
							  	(SELECT r.cost_name FROM rates r WHERE r.rates_id = i.item_id LIMIT 0, 1),
/*<<<<<<< HEAD CONFLICT 2019-05-07*/
							 	COALESCE(i.cost_item_id, 0)
							  	) SEPARATOR '|') cost_item
/*=======
							  	COALESCE(i.cost_item_id, 0) icost_item_id
							  	) SEPARATOR '|') cost_item
/*>>>>>>> version-2.3*/
							FROM costs c
							INNER JOIN cost_item i ON c.cost_id = i.cost_id
							WHERE c.cashbox_id = '". $cashbox_id ."'
							AND (c.inserted_date BETWEEN '".$csh_duration['date_opened']."' AND '".$csh_duration['date_closed']."')
							AND c.cost_type = 'e'
							AND c.cost_name != 'Opening Float'
							GROUP BY c.cost_id";
			$transaction  = $this->executeQuery($transaction);	
			$invoice 	= [];
			foreach($transaction as $key_trns => $data_trns):
				$cost_item 		= explode('|', $data_trns['cost_item']);
				$trans_item 	= [];
				foreach($cost_item as $itemkey => $itemdata){
					if($itemdata != null):
						$cost_itemize = explode(',', $itemdata);
						$trans_item[] = array(
							'company_id' 			=> $cred->auth->company_id, 
							'invoice_id' 			=> '', 
							'item_id' 				=> '', 
							'name' 					=> $cost_itemize[4], 
							'sku' 					=> '', 
							'quantity' 				=> (int) $cost_itemize[1], 
							'price' 				=> (float) $cost_itemize[3] / (float)$cost_itemize[1], 
							'total' 				=> (float) $cost_itemize[3], 
							'tax' 					=> 0, 
							'tax_id' 				=> 0, 
							'category_id' 			=> $cashbox->coa,
							'api_handler' 			=> $cred->handler,
							'api_rel_ID' 			=> 'ITEM:' . $data_trns['cost_id'],
							'data_integration' 		=> ['handler' => $cred->handler, 'id' => 'D:' . $cost_itemize[5] .':' . (int) $cost_itemize[0]],
						);
					endif;
				}

				$post_fields = array(
					'company_id' 			=> $cred->auth->company_id, 
					'invoice_number' 		=> strtoupper(substr($trans_sec, 0, 3)).'-'. $data_trns['cost_id'], 
					'order_number' 			=> $cred->handler .':' . $cashbox->cashbox_id . ':' . $data_trns['cost_id'], 
					'invoice_status_code' 	=> 'partial', 
					'invoiced_at' 			=> $data_trns['inserted_date'], 
					'due_at' 				=> $data_trns['inserted_date'], 
					'amount' 				=> $data_trns['val_cost'], 
					'currency_code' 		=> $cred->currency->php, 
					'currency_rate' 		=> 1, 
					'customer_id' 			=> $cashbox->customer_id, 
					'customer_name' 		=> ($trans_sec === 'minimart') ? 'Minimart Transaction': 'Restaurant Transaction',
					'customer_email' 		=> 'none@email.none', 
					'customer_tax_number' 	=> null, 
					'customer_phone' 		=> '', 
					'customer_address' 		=> '', 
					'notes' 				=> '', 
					'category_id' 			=> $cashbox->coa, 
					'parent_id' 			=> 0, 
					'account_id' 			=> $cred->banking->cash,
					'item' 					=> $trans_item,
					'data_integration' 		=> ['handler' => $cred->handler, 'id' => $data_trns['cost_id']],
				);
				$api_route 		= '/api/invoices/create-invoices';
				$invoices 	= $this->push_API_POST($post_fields, $api_route);
				if($invoices['data']['id']):
					$payment_fields = [
							'company_id'		=> $cred->auth->company_id,
							'invoice_id'		=> $cred->handler .':' . $cashbox->cashbox_id . ':' . $data_trns['cost_id'],
							'account_id'		=> $cred->banking->cash,
							'paid_at' 			=> $data_trns['inserted_date'],
							'amount' 			=> $data_trns['val_cost'],
							'currency_code'  	=> $cred->currency->php,
							'currency_rate'		=> 1,
							'description' 		=> ['payment_action' => 'add'],
							'payment_method'  	=> 'HTLpayment.transfer.' . $cred->banking->cash,
							'reference' 		=> 'HTLPY345S',
						];
					$api_route_p 	= '/api/invoices.payments/import/' . $invoices['data']['id'];
					$payment 		= $this->push_API_POST($payment_fields, $api_route_p);	
					$invoices 		= $payment;
				endif;
				$invoice[] 	= $post_fields;
			endforeach;
			return $invoice;
		else:
			return true;
		endif;
	}

	/* -- TVT -AWEN Integration -- */



	/* --- METHODS FOR INCOME REPORTS --- */
	public function figure_container (){
		$dbh = $this->db;
		$sql_ = $dbh->prepare("SELECT COALESCE(k.value, '') value FROM global_variables k WHERE k.key = 'cashbox_container' LIMIT 0, 1");
		$sql_->execute();
		$sql_ = $sql_->fetch();
		$figure_container = ($sql_['value'] != '') ? (array) json_decode($sql_['value']) : [];
		return $figure_container;
	}


	public function booking_source_color_scheme (){
		$clr = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
		$dbh = $this->db; 
		$color_array 				= [];
		$booking_source_color_array = [];
		$colorscheme = $dbh->prepare("SELECT COALESCE(k.value, '') value FROM global_variables k WHERE k.key = 'booking_source_color_scheme' LIMIT 0, 1");
		$colorscheme->execute();
		$colorscheme = $colorscheme->fetch();
		/* ----------------------------------------- */
		$bookingsoure = $dbh->prepare("SELECT booking_source_id, icon_src FROM booking_source WHERE status = 'active' ORDER BY booking_source_id ASC");
		$bookingsoure->execute();
		$bookingsoure = $bookingsoure->fetchAll( PDO::FETCH_ASSOC );
		/* ----------------------------------------- */
		$bkng_src_icon = [];
		foreach ($bookingsoure as $bkngkey => $bkngvalue) {
			$bkng_src_icon[] = $bkngvalue['icon_src'];
		}

		if($colorscheme){
			$colorscheme = get_object_vars( json_decode($colorscheme['value']) );	
			if(count($bookingsoure) > count($colorscheme)){
				foreach($bookingsoure as $bkey => $bkdata):
					if(!in_array($colorscheme[$bkdata['booking_source_id']], $colorscheme)):
						$color 	= '#'.$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)];
						$colorscheme[$bkdata['booking_source_id']] = $color;						
					endif;					
				endforeach;
				$colors3scheme = $dbh->prepare("UPDATE global_variables SET value = '". json_encode($colorscheme) ."'
							WHERE key = 'booking_source_color_scheme'");
				$colors3scheme->execute();
				$colorscheme 	= $this->booking_source_color_scheme();
			}
		}else{			
			foreach($bookingsoure as $bkey => $bkdata):
				$color 	= '#'.$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)];
				if(in_array($color, $color_array)){
					$color 	= '#'.$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)].$clr[rand(0,15)];
				}
				$color_array[] = $color;
				$booking_source_color_array[$bkdata['booking_source_id']] = $color;
			endforeach;
			$colors2scheme = $dbh->prepare("INSERT INTO global_variables(`key`,`value`)
							VALUES('booking_source_color_scheme', '". json_encode($booking_source_color_array) ."')");
			$colors2scheme->execute();
			$colorscheme 	= $this->booking_source_color_scheme();
		}
		return  array('tag_colorscheme' => $colorscheme, 'ind_colorscheme' => array_values($colorscheme), 'bkng_src_icon' => $bkng_src_icon);
	}




	public function income_apartments ($date_str, $date_end){	
		$dbh = $this->db;
		$date_start_id 	= $this->getIdperiod($date_str, "start_date");
		$date_end_id 	= $this->getIdperiod($date_end, "start_date");		
		$dbh = $this->db;
		$apartments = $dbh->prepare("SELECT ap.apartment_id,
				ap.apartment_name,
				ap.roomtype_id,
				ap.floor_number,
				ap.max_occupants,
				ap.house_number,
				rtp.name roomtype_name,
				(
					SELECT CONCAT_WS('|', COALESCE(COUNT(r.reservation_id),0),
						COALESCE(rc.bookingsource_id, ''),
						COALESCE(bs.booking_source_name, ''),
						COALESCE(r.reservation_id, ''),
						CONCAT_WS(' ', COALESCE(cl.name, ''), COALESCE(cl.surname, '')),
						COALESCE(rc.reference_num, ''),
						COALESCE(bs.icon_src, '')
						)
					FROM reservation r INNER JOIN reservation_conn rc ON r.reservation_conn_id = rc.reservation_conn_id					
					LEFT JOIN booking_source bs ON rc.bookingsource_id = bs.booking_source_id
					LEFT JOIN clients cl ON r.clients_id = cl.clients_id
					WHERE r.appartments_id = ap.apartment_id 
					AND (((r.date_start_id <= '$date_start_id' AND r.date_end_id >= '$date_start_id') OR (r.date_start_id <= '$date_end_id' AND r.date_end_id >= '$date_end_id'))
								OR ((r.date_start_id >= '$date_start_id' AND r.date_start_id <= '$date_end_id') OR (r.date_end_id >= '$date_start_id' AND r.date_end_id <= '$date_end_id')))
					AND r.status = 'active'
					AND  bs.status = 'active' LIMIT 0, 1
				) res_count,
				(
					SELECT CONCAT_WS('|', COALESCE(COUNT(bl.blocking_id),0), bl.blocking_reason) FROM blocking bl 			
					WHERE bl.appartment_id = ap.apartment_id AND bl.blocking_start_date_id = '$date_end_id' AND bl.status = 'active'
				) blc_count
				FROM apartments ap
				LEFT JOIN room_types rtp ON ap.roomtype_id = rtp.room_type_id");
		$apartments->execute();
		$apartments = $apartments->fetchAll( PDO::FETCH_ASSOC );
		foreach($apartments as $apkey => $apartmendata):
			$apartments[$apkey]['availability'] 		= 'vacant'; 
			$apartments[$apkey]['booking_source_id'] 	= null;
			$apartments[$apkey]['booking_source_name'] 	= null;
			$apartments[$apkey]['btn_class'] 			= 'btn-default';
			if($apartmendata['blc_count'] != 0):
				$apartments[$apkey]['availability'] 		= 'blocked';
				$apartments[$apkey]['btn_class'] 			= 'btn-defaultN';
			elseif($apartmendata['res_count'] != 0):
				$setitem 			= explode('|', $apartmendata['res_count']);
				$apartments[$apkey]['availability'] 		= 'occupied';
				$apartments[$apkey]['booking_source_id'] 	= $setitem[1];
				$apartments[$apkey]['booking_source_name'] 	= $setitem[2];
				$apartments[$apkey]['booking_source_icon'] 	= $setitem[6];
				$apartments[$apkey]['booking_source_icon_ii'] = str_replace('bkng_src', 'bkng_src_2', $setitem[6]);	
				$apartments[$apkey]['reservation_id'] 		= $setitem[3];
				$apartments[$apkey]['client_name'] 			= $setitem[4];
				$apartments[$apkey]['reference_num'] 		= $setitem[5];
				$apartments[$apkey]['btn_class'] 			= 'btn-success';
			endif;
			unset($apartments[$apkey]['blc_count']);
			unset($apartments[$apkey]['res_count']);
		endforeach;
		return $apartments;
		
	}


	public function rep_cashbox_stat($overall_figures){
		$cashbox_donut_values = [];
		foreach($overall_figures['overall_figures_list'] as $fkey => $Ofigures):
			$percentage_ = ($Ofigures['figr_income'] > 0) ? ($Ofigures['figr_income'] / $overall_figures['overall_figures']['figr_income']) * 100 : 0;
			$cashbox_donut_values[] = array(
						'id_src' 	=> $Ofigures['figr_id'],
						'name' 		=> ucfirst($Ofigures['figr_name']),
						'percent' 	=> round($percentage_, 0),
						'amount' 	=> $Ofigures['figr_income'],
					);
		endforeach;
		return $cashbox_donut_values;
	}


	public function room_figures ($room_reservation){
		$dbh 			= $this->db;
		$date_start_id 	= $this->getIdperiod($date_str, "start_date");
		$date_end_id 	= $this->getIdperiod($date_end, "start_date");
		$figure_Total 	= array(
							'amt_income' 		=> 0,
							'amt_net' 			=> 0,
							'amt_expense' 		=> 0,
							'amt_outstanding' 	=> 0,
							'amt_paid' 			=> 0,
							'amt_discount' 		=> 0,
 						);
		foreach($room_reservation as $rs_key => $roomreservation):
			$figure_Total['amt_income'] 		+= $roomreservation['total_cost'];
			$figure_Total['amt_net'] 			+= ($roomreservation['total_cost'] - $roomreservation['discount']);
			// $figure_Total['amt_expense'] 		+= $roomreservation['discount'];
			$figure_Total['amt_expense'] 		+= 0;
			$figure_Total['amt_outstanding'] 	+= (($roomreservation['total_cost'] - $roomreservation['discount'])  - $roomreservation['recieved']);
			$figure_Total['amt_paid'] 			+= $roomreservation['recieved'];
			$figure_Total['amt_discount'] 		+= $roomreservation['discount'] + $roomreservation['extra_discount'];
		endforeach;	
		return $figure_Total;
	}


	public function cashbox_figures ($cashbox_id, $date_str, $date_end){
		$dbh = $this->db;	
		$figure_Total 	= array(
							'amt_income' 		=> 0,
							'amt_net' 			=> 0,
							'amt_expense' 		=> 0,
							'amt_outstanding' 	=> 0,
							'amt_paid' 			=> 0,
							'amt_discount' 		=> 0,
 						);	

		$cashbox_figures = $dbh->prepare("SELECT COALESCE(SUM(c.val_cost),0) cost,
			COALESCE(c.cost_type, '') cost_type,
			COALESCE((SELECT SUM(ct.item_price - ct.item_orig_price) FROM cost_item ct WHERE ct.cost_id = c.cost_id), 0) ttl_discount
			FROM costs c
			WHERE c.cashbox_id = $cashbox_id AND c.cost_name != 'Opening Float'
			AND (c.inserted_date BETWEEN '$date_str' AND '$date_end' + INTERVAL 1 DAY - INTERVAL 1 SECOND)
			ORDER BY c.cost_type");
		$cashbox_figures->execute();
		$cashbox_figures = $cashbox_figures->fetchAll( PDO::FETCH_ASSOC );
		foreach($cashbox_figures as $chkey => $figures):
			$income_i 	= 0;
			$expense_i 	= 0;
			if($figures['cost_type'] === 's'){
				$expense_i 	= $figures['cost'];
			}else{
				$income_i 	= $figures['cost'];
			}
			$figure_Total['amt_income']   		+= $income_i;
			$figure_Total['amt_net'] 			+= ($income_i - $expense_i);
			$figure_Total['amt_expense']  		+= $expense_i;
			$figure_Total['amt_outstanding'] 	+= 0;
			$figure_Total['amt_paid'] 			+= ($income_i - $expense_i);
			$figure_Total['amt_discount'] 		+= $ttl_discount;
		endforeach;
		return $figure_Total;
	}

	public function overall_figures ($date_str, $date_end, $roomreservation){
		$dbh = $this->db;
		$figure_container 		= $this->figure_container();
		$overall_figures 		= array(
				'figr_income' 	=> 0,
				'figr_expense' 	=> 0,
				'figr_net'	 	=> 0,
				'figr_balance' 	=> 0,
				'figr_paid' 	=> 0,
				'figr_discount'	=> 0,
		);
		$overall_figures_list 	= array();
		foreach($figure_container as $key => $container):
			$i_figr_income 		= 0;
			$i_figr_expense 	= 0;
			$i_figr_net 		= 0;
			$i_figr_balance 	= 0;
			$i_figr_paid 		= 0;
			$i_figr_discount	= 0;
			if($key === 'hotel'){
				$room_figures 		= $this->room_figures($roomreservation);
				$cashbox_figures_H 	= $this->cashbox_figures($container, $date_str, $date_end);
				$i_figr_income 		= (float) $room_figures['amt_income'];
				// $i_figr_expense 	= (float) $room_figures['amt_expense'];
				$i_figr_expense 	= (float) $cashbox_figures_H['amt_expense'];
				$i_figr_net 		= (float) $room_figures['amt_net'];
				$i_figr_balance 	= (float) $room_figures['amt_outstanding'];
				$i_figr_paid 		= (float) $room_figures['amt_paid'];
				$i_figr_discount 	= (float) $room_figures['amt_discount'];					
			}else{ 
				$cashbox_figures 	= $this->cashbox_figures($container, $date_str, $date_end);
				$i_figr_name 		= $key;
				$i_figr_income 		= (float) $cashbox_figures['amt_income'];
				$i_figr_expense 	= (float) $cashbox_figures['amt_expense'];
				$i_figr_net 		= (float) $cashbox_figures['amt_net'];
				$i_figr_balance 	= (float) $cashbox_figures['amt_outstanding'];
				$i_figr_paid 		= (float) $cashbox_figures['amt_paid'];
				$i_figr_discount 	= (float) $cashbox_figures['amt_discount'];
			}

			$overall_figures_list[] 	= array(
				'figr_id' 		=> $container, 			
				'figr_name' 	=> $key,
				'figr_income' 	=> $i_figr_income,
				'figr_expense' 	=> $i_figr_expense,
				'figr_net' 		=> $i_figr_net,
				'figr_balance'	=> $i_figr_balance,
				'figr_paid'		=> $i_figr_paid,
				'figr_discount'	=> $i_figr_discount,
			);
			$overall_figures['figr_income'] 	+= $i_figr_income;
			$overall_figures['figr_expense'] 	+= $i_figr_expense;
			$overall_figures['figr_net'] 		+= $i_figr_net;
			$overall_figures['figr_balance'] 	+= $i_figr_balance;
			$overall_figures['figr_paid'] 		+= $i_figr_paid;
			$overall_figures['figr_discount'] 	+= $i_figr_discount;

		endforeach;
		return array('overall_figures' => $overall_figures, 'overall_figures_list' => $overall_figures_list);
	}	


	public function income_stock_items ($status = 'lowstock'){
		$whereClause = ($status == 'lowstock') ? '(rel.quantity < rel.quantity_min AND rel.quantity > 0)' :  'rel.quantity = 0';
		$dbh = $this->db;
		$itemlowstock = $dbh->prepare("SELECT rel.good_inventory_id,
			rel.appartment_id,
			rel.stockroom_id, 
			rel.quantity,
			rel.quantity_min,
			(rel.quantity_min - rel.quantity) difference,
			it.item_name,
			it.item_description,
			it.items_inventory_category_id,
			st.stockroom_name,
			ap.apartment_name
			FROM relinventory rel
			LEFT JOIN items_inventory it ON rel.good_inventory_id = it.items_id
			LEFT JOIN stockrooms st ON rel.stockroom_id = st.stockrooms_id
			LEFT JOIN apartments ap ON rel.appartment_id = ap.apartment_id
			WHERE $whereClause
			AND rel.good_inventory_id != 0
			ORDER BY rel.quantity DESC");
		$itemlowstock->execute();
		$itemlowstock = $itemlowstock->fetchAll( PDO::FETCH_ASSOC );
		return array('count' => count($itemlowstock), 'list' => $itemlowstock);
	}

	public function income_pricelist_item ($datestart, $dateend){
		$this->db->query("SET group_concat_max_len = 20000000");
		$dbh = $this->db;
		$pricelist_item = $dbh->prepare("SELECT r.rates_id, r.price_section_id,
					r.price_category_id, r.cost_name, r.type_ca,
					(SELECT
						GROUP_CONCAT(
						CONCAT_WS(
							'|',
							ci.item_qty,
							ci.item_price,
							(ci.item_qty * ci.item_price),
							ct.cash_register_name,
							ct.cost_id,
							ct.cashbox_id
						) 
						SEPARATOR '<:>')  FROM cost_item ci INNER JOIN costs ct ON ci.cost_id = ct.cost_id
						WHERE ci.item_id = r.rates_id AND (ct.inserted_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND)
					) cost_detail					
					FROM rates r INNER JOIN price_goods_inventory p	ON r.rates_id = p.rates_id		
					GROUP BY r.rates_id");
		$pricelist_item->execute();
		$pricelist_item = $pricelist_item->fetchAll( PDO::FETCH_ASSOC );
		foreach($pricelist_item as $lkey => $rates):
			$pricelist_item[$lkey]['amt_sales'] 	= 0;
			$pricelist_item[$lkey]['total_qty'] 	= 0;
			$pricelist_item[$lkey]['list_sales'] 	= [];
			if($rates['cost_detail'] != null):
				$cost_detail = explode('<:>', $rates['cost_detail']);
				foreach($cost_detail as $cstkey => $cstdata){
					$i_cstdata = explode('|', $cstdata);
					$pricelist_item[$lkey]['amt_sales'] += (float) $i_cstdata[2];
					$pricelist_item[$lkey]['total_qty'] += (int) $i_cstdata[0];
					$pricelist_item[$lkey]['list_sales'][] = array(
						'item_qty' 				=> $i_cstdata[0],
						'item_price' 			=> $i_cstdata[1],
						'total_cost' 			=> $i_cstdata[2],
						'cash_register_name' 	=> $i_cstdata[3],
						'cost_id' 				=> $i_cstdata[4],
						'cashbox_id' 			=> $i_cstdata[5],
					);
				}
			endif;			
			unset($pricelist_item[$lkey]['cost_detail']);
		endforeach;

		return $pricelist_item;
	}



	/* --- METHODS FOR INCOME REPORTS --- */


	/* INVENTORY ACTIVITY LOGS FUNCTIONALITY */
	public function addactivity_itemsallocation ($data = array()){
		$dbh = $this->db;
		$details_initem_STK = "SELECT sitv.item_name, sitv.item_code,
								COALESCE((SELECT salc.item_price FROM items_allocation salc
								WHERE salc.msfl_id = sitv.items_id
								AND salc.item_allocation = 'IN' AND salc.deleted_at IS NULL ORDER BY salc.id LIMIT 1), 0) item_price
								FROM items_inventory sitv WHERE sitv.items_id = :item_id";
		$details_initem_STK 	= $dbh->prepare($details_initem_STK);		
		$details_initem_STK->execute(array(':item_id' => $data['item_id']));
		$details_initem_STK 	= $details_initem_STK->fetch();

		$item_allc_STK 		= "INSERT INTO `items_allocation`(`stockroom_from`,
								`stockroom_to`, `msfl_id`, `item_name`,
								`item_qty`, `item_price`, `currency_rate`,
								`item_allocation`, `trans_date`, `status`,
								`inventory_status`, `alc_reference`, `inserted_host`, `inserted_by`)values(:stockroom_from,
								:stockroom_to, :msfl_id, :item_name,
								:item_qty, :item_price, :currency_rate,
								:item_allocation, :trans_date, :status,
								:inventory_status, :alc_reference, :inserted_host, :inserted_by)";
		$item_allc_STK 			= $dbh->prepare($item_allc_STK);

		$item_allc_STK->execute(array(
			':stockroom_from' 	=> $data['storage_id'],
			':stockroom_to' 	=> 0,
			':msfl_id' 			=> $data['item_id'],
			':item_name' 		=> $details_initem_STK['item_name'],
			':item_qty' 		=> $data['quantity'],
			':item_price' 		=> $details_initem_STK['item_price'],
			':currency_rate' 	=> 1,
			':item_allocation' 	=> 'OUT',
			':trans_date' 		=> date('Y-m-d H:i:s'),
			':status' 			=> 'active',
			':inventory_status' => 'SOLD',
			':alc_reference' 	=> 'RS:' . $data['cost_id'],
			':inserted_host' 	=> $_SERVER['SERVER_NAME'],
			':inserted_by' 		=> $data['inserted_by'],
		));
	}

	public function addactivity_roomitemsallocation ($data = array()){
		$dbh = $this->db;
		$details_initem_APR = "SELECT aitv.item_name, aitv.item_code,
								COALESCE((SELECT ralc.item_price FROM roomitems_allocation ralc
								WHERE ralc.msfl_id = aitv.items_id
								AND ralc.item_allocation = 'IN' AND ralc.deleted_at IS NULL), 0) item_price
								FROM items_inventory aitv WHERE aitv.items_id = :item_id";
		$details_initem_APR 	= $dbh->prepare($details_initem_APR);
		$details_initem_APR->execute(array(':item_id' => $items_id));
		$details_initem_APR 	= $details_initem_APR->fetch();

		$item_allc_APR 		= "INSERT INTO `roomitems_allocation`(`apartment_from`,
								`apartment_to`, `msfl_id`, `item_name`,
								`item_qty`, `item_price`, `currency_rate`,
								`item_allocation`, `trans_date`, `status`,
								`inventory_status`, `alc_reference`, `inserted_host`, `inserted_by`)values(:apartment_from,
								:apartment_to, :msfl_id, :item_name,
								:item_qty, :item_price, :currency_rate,
								:item_allocation, :trans_date, :status,
								:inventory_status, :alc_reference, :inserted_host, :inserted_by)";
		$item_allc_APR 			= $dbh->prepare($item_allc_APR);
		
		$item_allc_STK->execute(array(
			':apartment_from' 	=> $data['storage_id'],
			':apartment_to' 	=> 0,
			':msfl_id' 			=> $data['item_id'],
			':item_name' 		=> $details_initem_STK['item_name'],
			':item_qty' 		=> $data['quantity'],
			':item_price' 		=> $details_initem_STK['item_price'],
			':currency_rate' 	=> 1,
			':item_allocation' 	=> 'OUT',
			':trans_date' 		=> date('Y-m-d H:i:s'),
			':status' 			=> 'active',
			':inventory_status' => 'SOLD',
			':alc_reference' 	=> 'RS:' . $data['cost_id'],
			':inserted_host' 	=> $_SERVER['SERVER_NAME'],
			':inserted_by' 		=> $data['inserted_by'],
		));
	}

	/* ===================================== */




	public function rootitem_activity_log($datestart, $dateend){
		$dbh 			= $this->db;
		$sql_roomitem 	= $dbh->prepare("SELECT rt.*,
						COALESCE(itm.item_name, '-:-') orig_item_name,
						COALESCE(itm.item_name, '-:-') item_name,
						COALESCE(str_i.apartment_name, '-:-') fr_container_name,
						COALESCE(str_ii.apartment_name, '-:-') to_container_name,
						COALESCE(ctg.category_name, '-:-') category_name,
						COALESCE(usr.username, '-:-') username
						FROM roomitems_allocation rt
						INNER JOIN items_inventory itm ON rt.msfl_id = itm.items_id
						LEFT JOIN apartments str_i ON rt.apartment_from = str_i.apartment_id
						LEFT JOIN apartments str_ii ON rt.apartment_to = str_ii.apartment_id
						LEFT JOIN items_inventory_category ctg ON itm.items_inventory_category_id = ctg.category_id
						LEFT JOIN users usr ON rt.inserted_by = usr.users_id
						WHERE rt.deleted_at IS NULL
						AND (CAST(rt.trans_date as DATE) BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND)");
		$sql_roomitem->execute();
		$sql_roomitem 	= $sql_roomitem->fetchAll( PDO::FETCH_ASSOC );
		$itm_ttl=$itm_count_sold=$itm_count_trsf=$itm_count_avail=$itm_count_in=$itm_count_out=array('list'=>[], 'count'=>0, 'quantity'=>0, 'cost'=>0);
		foreach($sql_roomitem as $key => $allc):
			$sql_roomitem[$key]['container'] 		= 'roomitem';
			$sql_roomitem[$key]['container_from'] 	= $allc['apartment_from']; 	
			$sql_roomitem[$key]['container_to'] 	= $allc['apartment_to']; 
			unset($sql_roomitem[$key]['apartment_from']);
			unset($sql_roomitem[$key]['apartment_to']);
			$allc_item_row 							= $sql_roomitem[$key];
			/* ===================================== */
			/* ===================================== */
			$item_cost_row							= ($allc['item_price'] > 0) 
													? ((int) $allc['item_qty'] * $allc['item_price'] * $allc['currency_rate'])
													:  $allc['item_qty'];
			$itm_ttl['count']						+= 1;
			$itm_ttl['quantity']					+= $allc['item_qty'];
			$itm_ttl['cost']						+= $item_cost_row;
			$itm_ttl['list'][]						=  $allc_item_row;

			if($allc['inventory_status'] 	== 'SOLD'){
				$itm_count_sold['count'] 		+= 1;
				$itm_count_sold['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_sold['cost'] 		+= $item_cost_row;
				$itm_count_sold['list'][]		=  $allc_item_row;
			}
			if($allc['inventory_status'] 	== 'TRANSFER'){
				$itm_count_trsf['count'] 		+= 1;
				$itm_count_trsf['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_trsf['cost'] 		+= $item_cost_row;
				$itm_count_trsf['list'][]		=  $allc_item_row;
			}
			if($allc['inventory_status'] 	== 'AVAILABLE'){
				$itm_count_avail['count'] 		+= 1;
				$itm_count_avail['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_avail['cost'] 		+= $item_cost_row;
				$itm_count_avail['list'][]		=  $allc_item_row;
			}
			if($allc['item_allocation'] 	== 'IN'){
				$itm_count_in['count'] 		+= 1;
				$itm_count_in['quantity'] 		+= (int) $allc['item_qty'];
				$itm_count_in['cost'] 			+= $item_cost_row;
				$itm_count_in['list'][]			=  $allc_item_row;
			}
			if($allc['item_allocation'] 	== 'OUT'){
				$itm_count_out['count'] 		+= 1;
				$itm_count_out['quantity'] 		+= (int) $allc['item_qty'];
				$itm_count_out['cost'] 			+= $item_cost_row;
				$itm_count_out['list'][]		=  $allc_item_row;
			}
		endforeach;
		return [
			'itm_count_sold'	=> $itm_count_sold,
			'itm_count_trsf'	=> $itm_count_trsf,
			'itm_count_avail'	=> $itm_count_avail,
			'itm_count_in' 		=> $itm_count_in,
			'itm_count_out' 	=> $itm_count_out,
			'itm_totals' 		=> $itm_ttl,
		];
	}


	public function item_activity_log ($datestart, $dateend){
		$dbh 			= $this->db;
		$sql_stockroom 	= $dbh->prepare("SELECT st.*,
						COALESCE(itm.item_name, '-:-') orig_item_name,
						COALESCE(itm.item_name, '-:-') item_name,
						COALESCE(str_i.stockroom_name, '-:-') fr_container_name,
						COALESCE(str_ii.stockroom_name, '-:-') to_container_name,
						COALESCE(ctg.category_name, '-:-') category_name,
						COALESCE(usr.username, '-:-') username
						FROM items_allocation st
						INNER JOIN items_inventory itm ON st.msfl_id = itm.items_id
						LEFT JOIN stockrooms str_i ON st.stockroom_from = str_i.stockrooms_id
						LEFT JOIN stockrooms str_ii ON st.stockroom_to = str_ii.stockrooms_id
						LEFT JOIN items_inventory_category ctg ON itm.items_inventory_category_id = ctg.category_id
						LEFT JOIN users usr ON st.inserted_by = usr.users_id
						WHERE st.deleted_at IS NULL
						AND (CAST(st.trans_date as DATE) BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND)");
		$sql_stockroom->execute();
		$sql_stockroom 	= $sql_stockroom->fetchAll( PDO::FETCH_ASSOC );
		$itm_ttl=$itm_count_sold=$itm_count_trsf=$itm_count_avail=$itm_count_in=$itm_count_out=array('list'=>[], 'count'=>0, 'quantity'=>0, 'cost'=>0);
		foreach($sql_stockroom as $key => $allc):
			$sql_stockroom[$key]['container'] 		= 'stockroom';
			$sql_stockroom[$key]['container_from'] 	= $allc['stockroom_from']; 	
			$sql_stockroom[$key]['container_to'] 	= $allc['stockroom_to']; 	
			$sql_stockroom[$key]['date_formatted'] 	= date('d/m/y', strtotime($allc['trans_date'])); 		
			unset($sql_stockroom[$key][$stockroom_from]);
			unset($sql_stockroom[$key][$stockroom_to]);
			$allc_item_row 							= $sql_stockroom[$key];
			/* ===================================== */
			/* ===================================== */
			$item_cost_row							= ($allc['item_price'] > 0) 
													? ((int) $allc['item_qty'] * $allc['item_price'] * $allc['currency_rate'])
													:  $allc['item_qty'];
			$itm_ttl['count']						+= 1;
			$itm_ttl['quantity']					+= $allc['item_qty'];
			$itm_ttl['cost']						+= $item_cost_row;
			$itm_ttl['list'][]						=  $allc_item_row;

			if($allc['inventory_status'] 	== 'SOLD'){
				$itm_count_sold['count'] 		+= 1;
				$itm_count_sold['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_sold['cost'] 		+= $item_cost_row;
				$itm_count_sold['list'][]		=  $allc_item_row;
			}
			if($allc['inventory_status'] 	== 'TRANSFER'){
				$itm_count_trsf['count'] 		+= 1;
				$itm_count_trsf['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_trsf['cost'] 		+= $item_cost_row;
				$itm_count_trsf['list'][]		=  $allc_item_row;
			}
			if($allc['inventory_status'] 	== 'AVAILABLE'){
				$itm_count_avail['count'] 		+= 1;
				$itm_count_avail['quantity'] 	+= (int) $allc['item_qty'];
				$itm_count_avail['cost'] 		+= $item_cost_row;
				$itm_count_avail['list'][]		=  $allc_item_row;
			}
			if($allc['item_allocation'] 	== 'IN'){
				$itm_count_in['count'] 		+= 1;
				$itm_count_in['quantity'] 		+= (int) $allc['item_qty'];
				$itm_count_in['cost'] 			+= $item_cost_row;
				$itm_count_in['list'][]			=  $allc_item_row;
			}
			if($allc['item_allocation'] 	== 'OUT'){
				$itm_count_out['count'] 		+= 1;
				$itm_count_out['quantity'] 		+= (int) $allc['item_qty'];
				$itm_count_out['cost'] 			+= $item_cost_row;
				$itm_count_out['list'][]		=  $allc_item_row;
			}
		endforeach;
		return [
			'itm_count_sold'	=> $itm_count_sold,
			'itm_count_trsf'	=> $itm_count_trsf,
			'itm_count_avail'	=> $itm_count_avail,
			'itm_count_in' 		=> $itm_count_in,
			'itm_count_out' 	=> $itm_count_out,
			'itm_totals' 		=> $itm_ttl,
		];		
	}



	public function item_transaction_log($datestart, $dateend){

		$stockroom_log 	= $this->item_activity_log($datestart, $dateend);
		$roomitem_log 	= $this->rootitem_activity_log($datestart, $dateend);
		$item_list      = array_merge_recursive($stockroom_log, $roomitem_log);
		foreach($item_list as $key => $item_data):
			$item_list[ $key]['count'] 		= array_sum($item_list[ $key]['count']);
			$item_list[ $key]['quantity'] 	= array_sum($item_list[ $key]['quantity']);
			$item_list[ $key]['cost'] 		= array_sum($item_list[ $key]['cost']);
		endforeach;
    	// $itm_count_sold = array_column($item_list['itm_count_sold']['list'], 'item_name');
    	// array_multisort($itm_count_sold, SORT_ASC, $item_list['itm_count_sold']['list']);
		return $item_list;	
	}


	public function stock_itemonstock($datestart, $dateend, $type = 'out'){
		$andwhere 	= ($type == 'out') ? 'rel.quantity = 0' : 'rel.quantity > 0'; 
		$dbh 		= $this->db;
		$sql 		= "SELECT rel.*,
					COALESCE(itm.item_name, '') item_name,
					COALESCE(stc.stockroom_name, '') stockroom_name,
					COALESCE(apt.apartment_name, '') apartment_name,
					COALESCE(ctg.category_name, 'Unallocated') category_name
					FROM relinventory rel
					INNER JOIN items_inventory itm ON rel.good_inventory_id = itm.items_id
					LEFT JOIN stockrooms stc ON rel.stockroom_id = stc.stockrooms_id
					LEFT JOIN apartments apt ON rel.appartment_id = apt.apartment_id
					LEFT JOIN items_inventory_category ctg ON itm.items_inventory_category_id = ctg.category_id
					WHERE rel.quantity < rel.quantity_min
					AND $andwhere";
		$stock_itemotonstck = $dbh->prepare($sql);
		$stock_itemotonstck->execute();
		$stock_itemotonstck = $stock_itemotonstck->fetchAll( PDO::FETCH_ASSOC );
		$stock_countqty   	= 0;		
		foreach($stock_itemotonstck as $tkey => $item):
			$stock_suggest_refill = $item['quantity_min'] - $item['quantity'];
			$stock_itemotonstck[$tkey]['stock_suggest_refill'] = ($stock_suggest_refill > 0) ? $stock_suggest_refill : 0 ;
			$stock_countqty += ($stock_suggest_refill > 0) ? $stock_suggest_refill : 0 ;
			$stock_itemotonstck[$tkey]['storage'] 	= ($item['stockroom_name'] != '') ? $item['stockroom_name'] : $item['apartment_name'];
		endforeach;
		return ['item_list' => $stock_itemotonstck, 'refill_quantity' => $stock_countqty, 'item_count' => count($stock_itemotonstck)];
	}


	public function nonhotel_cashbox ($datestart, $dateend, $cashbox_id = 4){
		$dbh = $this->db;
		$sql = "SELECT  SUM((	
				SELECT 
					COALESCE(SUM(CASE 
				        	when st.item_price = 0 then 1
				        	when st.item_price IS NULL then 1
				        	ELSE st.item_price
				    	END * st.item_qty * st.currency_rate), 0) item_cost
					FROM items_allocation st 
					LEFT JOIN costs ct ON REGEXP_REPLACE(st.alc_reference, '[EP:]', '') = ct.cost_id
					WHERE st.inventory_status = 'SOLD' 
					AND st.msfl_id IS NOT NULL
					AND (st.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
					AND st.alc_reference LIKE 'EP:%'
					AND ct.cashbox_id = $cashbox_id
					AND ct.cost_type = 'e'
				) + (
				SELECT 
					COALESCE(SUM(CASE 
			        	when rt.item_price = 0 then 1
			        	when rt.item_price IS NULL then 1
			        	ELSE rt.item_price
			    	END * rt.item_qty * rt.currency_rate), 0) item_cost
				FROM roomitems_allocation rt 
				LEFT JOIN costs ct ON REGEXP_REPLACE(rt.alc_reference, '[EP:]', '') = ct.cost_id
				WHERE rt.inventory_status = 'AVAILABLE' 
				AND rt.msfl_id IS NOT NULL
				AND (rt.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
				AND rt.alc_reference LIKE 'EP:%'
				AND ct.cashbox_id = $cashbox_id
				AND ct.cost_type = 'e'
				)) inventory_Cost
				";
		$items_allocation = $dbh->prepare($sql);
		$items_allocation->execute();
		$items_allocation = $items_allocation->fetch();
		return $items_allocation['inventory_Cost'];	
	}


	public function hotel_cashboxcost($datestart, $dateend, $cashbox_id = 4){
		$dbh = $this->db;
		$sql = "SELECT  SUM((	
				SELECT 
					COALESCE(SUM(CASE 
				        	when st.item_price = 0 then 1
				        	when st.item_price IS NULL then 1
				        	ELSE st.item_price
				    	END * st.item_qty * st.currency_rate), 0) item_cost
					FROM items_allocation st 
					WHERE st.inventory_status = 'SOLD' 
					AND st.msfl_id IS NOT NULL
					AND (st.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
					AND st.alc_reference LIKE 'RS:%'
				) + (
				SELECT 
					COALESCE(SUM(CASE 
			        	when rt.item_price = 0 then 1
			        	when rt.item_price IS NULL then 1
			        	ELSE rt.item_price
			    	END * rt.item_qty * rt.currency_rate), 0) item_cost
				FROM roomitems_allocation rt 
				WHERE rt.inventory_status = 'AVAILABLE' 
				AND rt.msfl_id IS NOT NULL
				AND (rt.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
				AND rt.alc_reference LIKE 'RS:%'
				)) inventory_Cost";
		$items_allocation = $dbh->prepare($sql);
		$items_allocation->execute();
		$items_allocation = $items_allocation->fetch();
		return $items_allocation['inventory_Cost'];
	} 

	public function inventory_cashbox_figure($datestart, $dateend){
		$cashbox_ = [];
		$ttl_cost = 0;
		foreach($this->figure_container() as $key => $cashbox):
			if($key == 'hotel'):
				$cost = $this->hotel_cashboxcost($datestart, $dateend, $cashbox);
			else:
				$cost = $this->nonhotel_cashbox($datestart, $dateend, $cashbox);
			endif;
			$cashbox_[] = array('name' => $key, 'id_src' => $cashbox, 'amount' => $cost);
			$ttl_cost 	+= $cost;
		endforeach;
		foreach($cashbox_ as $cskey => $csdata):
			$cashbox_[$cskey]['percent'] = ($csdata['amount'] / $ttl_cost) * 100;
		endforeach;
		return $cashbox_;
	}


	public function inventory_stockroomchart($datestart, $dateend){
		$dbh = $this->db;
		$sql = "SELECT cont.stockrooms_id id_src, cont.stockroom_name name,
				COALESCE(CASE WHEN EXISTS(SELECT gl.value FROM global_variables gl WHERE gl.key = 'ivt_roomitem_stockroom' AND gl.value = cont.stockrooms_id) 
					THEN COALESCE(SUM((	
						SELECT COALESCE(SUM(st.item_qty), 0) FROM items_allocation st 
							WHERE st.inventory_status = 'SOLD' AND st.msfl_id IS NOT NULL
							AND (st.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
							AND st.stockroom_from = cont.stockrooms_id AND st.deleted_at IS NULL
						) + (
						SELECT COALESCE(SUM(rt.item_qty), 0) FROM roomitems_allocation rt 
						WHERE rt.inventory_status = 'AVAILABLE' AND rt.msfl_id IS NOT NULL
						AND (rt.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
						AND rt.deleted_at IS NULL
						)), 0)					
					ELSE COALESCE(SUM((
						SELECT COALESCE(SUM(st.item_qty), 0) FROM items_allocation st 
						WHERE st.inventory_status = 'SOLD' AND st.msfl_id IS NOT NULL
						AND (st.trans_date BETWEEN '$datestart' AND '$dateend' + INTERVAL 1 DAY - INTERVAL 1 SECOND) 
						AND st.stockroom_from = cont.stockrooms_id AND st.deleted_at IS NULL
						)), 0)
					END, 0) amount
				FROM stockrooms cont
				GROUP BY cont.stockrooms_id";
		$items_allocation = $dbh->prepare($sql);
		$items_allocation->execute();
		$items_allocation 	= $items_allocation->fetchAll( PDO::FETCH_ASSOC );
		$ttl_amount  		= array_sum(array_column($items_allocation, 'amount'));
		foreach($items_allocation as $cskey => $csdata):
			$items_allocation[$cskey]['percent'] = ($csdata['amount'] <= 0) ? 0 :round((($csdata['amount'] / $ttl_amount) * 100), 2);
		endforeach;
		return $items_allocation;
	}
// <<<<<<< HEAD 
	/*
								+------------------------------------------------------------------------+
								|                                                                        |
								|                            JIVOCHAT WEBHOOKS                           |
								|                                                                        |
								+------------------------------------------------------------------------+  
    */
	public function save_jivochat_agents($agentsObj, $event_name){
		if($event_name != 'offline_message'){
			// select agent if exist
			$select_agent = "SELECT agent_id FROM jivo_agents WHERE agent_id = :agent_id";
			$state = $this->db->prepare($select_agent);

			$insertQuery = "INSERT INTO `jivo_agents` ( id, agent_id, agent_email, agent_name, phone ) VALUES ( :id, :agent_id, :agent_email, :agent_name, :phone )";
				$stmts = $this->db->prepare($insertQuery); 
			// if($agentsObj != null){
			if($agentsObj['id'] != null){
				try{
					$state->execute(array(
						':agent_id' =>  $agentsObj['id']
					));
					$agent_id = $state->fetchAll( PDO::FETCH_ASSOC );
					if($agent_id != $agentsObj['id']){
						if($event_name == 'call_event' || $event_name == 'chat_accepted' || $event_name == 'chat_assigned' || $event_name == 'chat_updated'){
							$stmts->execute(array(
								':id' 			=> null,
								':agent_id' 	=> $agentsObj['id'],
								':agent_email' 	=> $agentsObj['email'],
								':agent_name' 	=> $agentsObj['name'],
								':phone'		=> $agentsObj['phone']
							));
							$resp = array( "status"=> "success", "message" => "Agent Inserted" );
						}
					}else{
						$resp = array( "status"=> "error1", "message" => "Agent id: ".$agentsObj['id']." already saved" );
					}
				} catch(PDOException $e) {
					$resp = array( "status"=> "Database Error1", "message" => $e->getMessage() );
				}
			}else{	
				for($x=0; $x<count($agentsObj); $x++){
					try{	
						$state->execute(array(
							':agent_id' =>  $agentsObj[$x][	'id']
						));
						$agent_id = $state->fetchAll( PDO::FETCH_ASSOC );
						if($agent_id != $agentsObj[$x][	'id']){
							if($event_name == 'chat_finished'){
								$stmts->execute(array(
									':id' 			=> null,
									':agent_id' 	=> $agentsObj[$x]['id'],
									':agent_email' 	=> $agentsObj[$x]['email'],
									':agent_name' 	=> $agentsObj[$x]['name'],
									':phone'		=> $agentsObj[$x]['phone']
								));
								$resp = array( "status"=> "success", "message" => "Agents Inserted" );
							}
						}else{
							$resp = array( "status"=> "error2", "message" => "Agents id already saved" );
						}
					} catch(PDOException $e) {
						$resp = array( "status"=> "Agent already saved", "message" => $e->getMessage() );
					}
				}
			}
		}else{
			$resp = array( "status"=> "error3", "message" => "Offline message does not have agent" );
		}
		var_dump($resp);
	}

	public function save_jivochat_client_details($chat_id, $clientObj, $event_name){
		$name 			= $clientObj['name'];
		$email 			= $clientObj['email'];
		$phone 			= $clientObj['phone'];
		$number 		= $clientObj['number'];
		$description 	= $clientObj['description'];
		$chat_count 	= $clientObj['chats_count'];
		$social_type 	= $clientObj['social']['photos']['0']['typeName'];
		$social_url 	= $clientObj['social']['photos']['0']['url'];
		// ---------- Select client number if exist
		$sql_select = "SELECT `client_number` FROM jivo_client_details WHERE `client_number` = '".$clientObj['number']."'  ";
		$statement = $this->db->prepare($sql_select);
		if($statement->execute()){
			$results = $statement->fetchAll( PDO::FETCH_ASSOC );
		}
		if($results[0]['client_number'] != $clientObj['number'] || $results[0]['client_number'] == null){
			$insertQuery = "INSERT INTO jivo_client_details
				( id, chat_id, name, email, phone, client_number, description, chat_count, social_type, social_url ) 
				VALUES
				( :id, :chat_id, :name, :email, :phone, :client_number, :description, :chat_count, :social_type, :social_url )
			";
			$stmts = $this->db->prepare($insertQuery);
			try {
				$stmts->execute(array(
					':id' 				=> NULL,
					':chat_id' 			=> $chat_id,
					':name' 			=> $name,
					':email' 			=> $email,
					':phone' 			=> $phone,
					':client_number'	=> $number,
					':description' 		=> $description,
					':chat_count' 		=> $chat_count,
					':social_type'		=> $social_type,
					':social_url' 		=> $social_url
				));
				$resp = array( "status"=> "success", "message" => "Client Details Inserted" );
			} catch(PDOException $e) {
				$resp = array( "status"=> "error1", "message" => $e->getMessage() );				
			}
		}else{
			if($event_name == 'chat_updated' || $results[0]['client_number'] == $clientObj['number'] ){
				$update_client = "UPDATE
					jivo_client_details SET
					`chat_id`		=:newchatid,
					`name`			=:newname,
					`email`			=:newemail,
					`phone`			=:newphone,
					`description`	=:newdescription,
					`chat_count`	=:newchat_count,
					`social_type`	=:newsocial_type,	
					`social_url`	=:newsocial_url 
					WHERE client_number	= '".$clientObj['number']."'
				";
				$stmt   = $this->db->prepare($update_client);
				try{ 
					$result = $stmt->execute(array(
						':newchatid' 		=> $chat_id,
						':newname' 			=> $name,
						':newemail' 		=> $email,
						':newphone' 		=> $phone,
						':newdescription' 	=> $description,
						':newchat_count' 	=> $chat_count,
						':newsocial_type'	=> $social_type,
						':newsocial_url' 	=> $social_url
					));
					$resp = array( "status"=> "success", "message" => "Client Details Updated" );
					//return $response->withJson( $resp );
				} catch(PDOException $e) {	
					$resp = array( "status"=> "error2", "message" => $e->getMessage() );
					//return $response->withJson( $resp );
				}
			}
		}
		var_dump($resp);
	}

	public function save_jivochat_session_history($event_name, $sessionObj, $widget_id, $clientNumber, $chatObj, $assign_to, $plain_messages, $html_messages, $page, $agentsObj){
		$geoip 	 		 = $sessionObj['geoip'];
		$utm 	 		 = $sessionObj['utm'];
		$utm_json 		 = $sessionObj['utm_json'];
		$ip_addr 		 = $sessionObj['ip_addr'];
		$user_agent 	 = $sessionObj['user-agent'];
		$agent_chat_id   = $chatObj['messages'];
		//SELECT id based on client number
		$select_id = "SELECT id FROM jivo_client_details WHERE client_number = :client_number ";
		$state = $this->db->prepare($select_id);
		$state->execute(
			array(
				':client_number' => $clientNumber
			)
		);
		$client_details_id = $state->fetchAll( PDO::FETCH_ASSOC );

		$insertQuery = "INSERT INTO 
			jivo_session_details
			( id, agents_id, client_details_id, widget_id, ses_region_code, ses_country, ses_country_code, ses_region, ses_city, ses_isp, ses_latitude, ses_longitude, ses_organization, ses_utm, ses_utm_source, ses_utm_campaign, ses_utm_content, ses_utm_medium, ses_utm_term, ip_addr, user_agent, url, title, assign_to, event_name, plain_messages, html_messages )
			VALUES
			( :id, :agents_id, :client_details_id, :widget_id, :ses_region_code, :ses_country, :ses_country_code, :ses_region, :ses_city, :ses_isp, :ses_latitude, :ses_longitude, :ses_organization, :ses_utm, :ses_utm_source, :ses_utm_campaign, :ses_utm_content, :ses_utm_medium, :ses_utm_term, :ip_addr, :user_agent, :url, :title, :assign_to, :event_name, :plain_messages, :html_messages )
		";
		$stmt = $this->db->prepare($insertQuery);
		
		if($event_name == 'chat_finished'){
			for($x=0; $x<count($agent_chat_id); $x++){
				if($agent_chat_id[$x]['agent_id'] != null){
					$agent_id = $agent_chat_id[$x]['agent_id']; //$agent_id = array($x => $agent_chat_id[$x]['agent_id']);
				}
			}
			try{
				$stmt->execute(array(
					':id'					=> null,
					':agents_id'			=> $agent_id,
					':client_details_id'	=> $client_details_id[0]['id'],
					':widget_id'			=> $widget_id,
					':ses_region_code'		=> $geoip['region_code'],
					':ses_country'			=> $geoip['country'],
					':ses_country_code'		=> $geoip['country_code'],
					':ses_region'			=> $geoip['region'],
					':ses_city'				=> $geoip['city'],
					':ses_isp'				=> $geoip['isp'],
					':ses_latitude'			=> $geoip['latitude'],
					':ses_longitude'		=> $geoip['longitude'],
					':ses_organization'		=> $geoip['organization'],
					':ses_utm'				=> $utm,
					':ses_utm_source'		=> $utm_json['source'],
					':ses_utm_campaign'		=> $utm_json['campaign'],
					':ses_utm_content'		=> $utm_json['content'],
					':ses_utm_medium'		=> $utm_json['medium'],
					':ses_utm_term'			=> $utm_json['term'],
					':ip_addr'				=> $ip_addr,
					':user_agent'			=> $user_agent,
					':url'					=> $page['url'],
					':title'				=> $page['title'],
					':assign_to'			=> $assign_to,
					':event_name'			=> $event_name,
					':plain_messages'		=> $plain_messages,
					':html_messages'		=> $html_messages
				));
				$resp = array( "status"=> "success1", "message" => "New Session History Inserted" );
			}catch(PDOException $e){
				$resp = array( "status"=> "error1", "message" => $e->getMessage() );
			}
		}else{
			$a = "(SELECT agent_id FROM jivo_agents WHERE agent_id='".$agentsObj['id']."')";
			if($event_name == 'chat_accepted'){
				try{
					$sessionUpdate = "UPDATE jivo_session_details SET agents_id='".$agentsObj['id']."' WHERE client_details_id=(SELECT id FROM jivo_client_details WHERE client_number='".$clientNumber."')";
					$status =  $this->db->prepare($sessionUpdate);
					$status->execute();

					$stmt->execute(array(
						':id'					=> null,
						':agents_id'			=> $agentsObj['id'],
						':client_details_id'	=> $client_details_id[0]['id'],
						':widget_id'			=> $widget_id,
						':ses_region_code'		=> $geoip['region_code'],
						':ses_country'			=> $geoip['country'],
						':ses_country_code'		=> $geoip['country_code'],
						':ses_region'			=> $geoip['region'],
						':ses_city'				=> $geoip['city'],
						':ses_isp'				=> $geoip['isp'],
						':ses_latitude'			=> $geoip['latitude'],
						':ses_longitude'		=> $geoip['longitude'],
						':ses_organization'		=> $geoip['organization'],
						':ses_utm'				=> $utm,
						':ses_utm_source'		=> $utm_json['source'],
						':ses_utm_campaign'		=> $utm_json['campaign'],
						':ses_utm_content'		=> $utm_json['content'],
						':ses_utm_medium'		=> $utm_json['medium'],
						':ses_utm_term'			=> $utm_json['term'],
						':ip_addr'				=> $ip_addr,
						':user_agent'			=> $user_agent,
						':url'					=> $page['url'],
						':title'				=> $page['title'],
						':assign_to'			=> $assign_to,
						':event_name'			=> $event_name,
						':plain_messages'		=> $plain_messages,
						':html_messages'		=> $html_messages
					));	
					$resp = array( "status"=> "success2", "message" => "New Session History Inserted" );
				}catch(PDOException $e){
					$resp = array( "status"=> "error2", "message" => $e->getMessage() );
				}
			}else{
				try{
					$stmt->execute(array(
						':id'					=> null,
						':agents_id'			=> $agentsObj['id'],
						':client_details_id'	=> $client_details_id[0]['id'],
						':widget_id'			=> $widget_id,
						':ses_region_code'		=> $geoip['region_code'],
						':ses_country'			=> $geoip['country'],
						':ses_country_code'		=> $geoip['country_code'],
						':ses_region'			=> $geoip['region'],
						':ses_city'				=> $geoip['city'],
						':ses_isp'				=> $geoip['isp'],
						':ses_latitude'			=> $geoip['latitude'],
						':ses_longitude'		=> $geoip['longitude'],
						':ses_organization'		=> $geoip['organization'],
						':ses_utm'				=> $utm,
						':ses_utm_source'		=> $utm_json['source'],
						':ses_utm_campaign'		=> $utm_json['campaign'],
						':ses_utm_content'		=> $utm_json['content'],
						':ses_utm_medium'		=> $utm_json['medium'],
						':ses_utm_term'			=> $utm_json['term'],
						':ip_addr'				=> $ip_addr,
						':user_agent'			=> $user_agent,
						':url'					=> $page['url'],
						':title'				=> $page['title'],
						':assign_to'			=> $assign_to,
						':event_name'			=> $event_name,
						':plain_messages'		=> $plain_messages,
						':html_messages'		=> $html_messages
					));	
				$resp = array( "status"=> "success3", "message" => "New Session History Inserted" );
				}catch(PDOException $e){
					$resp = array( "status"=> "error3", "message" => $e->getMessage() );
				}
			}
		}
		var_dump($resp);
	}
// =======
// orig space before res_discount codes HTL-985
	public function res_discounts($booking_src_id){
		try {
			$date_today = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
			$date_today_id = $this->getPeriodID($date_today, 'start');

			$sql = "SELECT a.`name` as name, a.`type` as type, a.`amount` as amount, a.`date_start_id` as promo_date_start_id, a.`date_end_id` as promo_date_end_id, b.`isRound`, (SELECT `start_date` FROM `periods` WHERE `periods_id` = a.`date_start_id`) As start_date, (SELECT `start_date` FROM `periods` WHERE `periods_id` = a.`date_end_id`) As end_date
					FROM `res_discounts` a LEFT JOIN `res_discounts_bking_src` b ON b.`res_discount_id` = a.`id`
					WHERE CAST(a.`created_date` AS date) <= CAST('".$date_today."' AS date) AND
						  a.`date_end_id` >= '".$date_today_id."' AND
						  b.`booking_source_id` = '".$booking_src_id."' AND
						  a.`status` = 'active' AND
						  b.`status` = 'active'";
			$result_res_disc = $this->executeQuery($sql); # get promo discount.

			$isavailable = 0;
			if(count($result_res_disc) > 0){
				$isavailable = 1;
			}
			
			$resp = array( "status" => "success", "isavailable" => $isavailable, "data" => $result_res_disc );
			return $resp;
		} catch (PDOException $e) {
			$resp = array( "status" => "success", "isavailable" => 0, "data" => array() );
			return $resp;
		}
	}
// ---------
// >>>>>>> bc55895d4cf0c65a66844a3b37772f0c79a00c2d

	public function save_jivochat_message_history($clientNumber, $offline_message_id, $message, $departmentObj, $chatObj, $callObj, $event_name){
		$select_session_id_query = "SELECT  max(jivo_ses.id) as id
			FROM  jivo_session_details jivo_ses  
			JOIN jivo_client_details jivo_cli  
			WHERE   jivo_ses.event_name='".$event_name."' AND  jivo_cli.client_number = '".$clientNumber."' AND jivo_ses.client_details_id = jivo_cli.id
		";
		$selection = $this->db->prepare($select_session_id_query);
		$selection->execute();
		$session = $selection->fetchAll( PDO::FETCH_ASSOC );

		$insertQuery = "INSERT INTO
			jivo_message_call_history
			( id, session_id, offline_message_id, dept_id, dept_name, message_timestamp, message_type, message, rate, invitation, blacklisted, call_type, phone, status,  reason, record_url )
			VALUES
			( :id, :session_id, :offline_message_id, :dept_id, :dept_name, :message_timestamp, :message_type, :message, :rate, :invitation, :blacklisted, :call_type, :phone, :status, :reason, :record_url )
		";

		$resp = array( "status"=> "success", "message" =>  "Message History 1 Inserted" );
		$stmts = $this->db->prepare($insertQuery);
		if($event_name == 'chat_finished'){
			for($count=0; $count<count($chatObj['messages']); $count++){
				try{
					$stmts->execute(array(
						':id'					=> null,
						':session_id'			=> $session[0]['id'],
						':offline_message_id' 	=> $offline_message_id,
						':dept_id'				=> $departmentObj['id'],
						':dept_name'			=> $departmentObj['name'],
						':message_timestamp'	=> date('Y-m-d H:i:s', $chatObj['messages'][$count]['timestamp']),
						':message_type'			=> $chatObj['messages'][$count]['type'],
						':message'				=> $chatObj['messages'][$count]['message'],
						':rate'					=> $chatObj['rate'],
						':invitation' 			=> $chatObj['invitation'],
						':blacklisted'			=> $chatObj['blacklisted'],
						':call_type'			=> $callObj['type'],
						':phone'				=> $callObj['phone'],
						':status'				=> $callObj['status'],
						':reason'				=> $callObj['reason'],
						':record_url'			=> $callObj['record_url']
					));
					$resp = array( "status"=> "success", "message" =>  "Message History 1 Inserted" );
				}catch(PDOException $e){
					$resp = array( "status" => "Database Error1", "message" => $e->getMessage() );
				}
			}
		}else{
			try{
				$dateTime = new DateTime('now',  new \DateTimeZone( 'Asia/Manila' ));
				$dateTime->setTimestamp($dateTime->getTimestamp());
				$stmts->execute(array(
					':id'					=> null,
					':session_id'			=> $session[0]['id'],
					':offline_message_id' 	=> $offline_message_id,
					':dept_id'				=> $departmentObj['id'],
					':dept_name'			=> $departmentObj['name'],
					':message_timestamp'	=> $dateTime->format('Y-m-d H:i:s'),
					':message_type'			=> $chatObj['messages'][$count]['type'],
					':message'				=> $message,    //this is for offline message
					':rate'					=> $chatObj['rate'],
					':invitation' 			=> $chatObj['invitation'],
					':blacklisted'			=> $chatObj['blacklisted'],
					':call_type'			=> $callObj['type'],
					':phone'				=> $callObj['phone'],
					':status'				=> $callObj['status'],
					':reason'				=> $callObj['reason'],
					':record_url'			=> $callObj['record_url']
				));
				$resp = array( "status"=> "success", "message" =>  "Message History 2 Inserted" );
			}catch(PDOException $e){
				$resp = array( "status" => "Database Error2", "message" => $e->getMessage() );
			}
		}
		var_dump($resp);
	}

    public function jivochat_function($agentsObj, $event_name, $chat_id, $widget_id, $clientObj, $departmentObj, $sessionObj, $page, $callObj, $assign_to, $chatObj, $offline_message_id, $message, $plain_messages, $html_messages){
    	$this->save_jivochat_agents($agentsObj, $event_name);
    	$this->save_jivochat_client_details($chat_id, $clientObj, $event_name);
    	$this->save_jivochat_session_history($event_name, $sessionObj, $widget_id, $clientObj['number'], $chatObj, $assign_to, $plain_messages, $html_messages, $page, $agentsObj);
    	if($event_name != 'chat_accepted'){
    		$this->save_jivochat_message_history($clientObj['number'], $offline_message_id, $message, $departmentObj, $chatObj, $callObj, $event_name);
    	}
	}
	/*EMAIL NOTIFICATION START HERE*/
	public function emailConfig(){
		$sql 		= "SELECT * FROM email_config WHERE status='active' ";
		$email_auth = $this->executeQuery($sql); 
		if(count($email_auth) > 0 ){
			return $email_auth[0];
		}else{
			return 0;
		}
	}

	public function setup_config(){
		$emails_switch = $this->get_global_variables('emails_switch');
		if($emails_switch == '1'){
			try{
				$mail 			= new PHPMailer(true);
				$config 		= $this->emailConfig();         							 // params { email_config_id }
				$host 			= $config['host'];           							 // 'smtp.dynu.com'	
				$smtp_auth 		= $config['SMTPauth'];  					 
				$SMTPusername 	= $config['email'];      							 //'angular@thehotelpms.com';
				$SMTPpassword 	= $config['password'];   							 //'L3tme1n!';
				$smtp_secure 	= $config['SMTPsec']; 							 // 'tls';
				$port 			= $config['port'];           							 // 587;
				$host_mail 		= $config['email'];        
				$setFrom 		= $config['setFrom'];

				/*MAILER CONFIGURATION*/
				$mail->CharSet 		= 'UTF-8';
				$mail->isSMTP();
				$mail->SMTPDebug 	= 0;  // 0 for production, 1 for client, 2 for client-server
				$mail->Debugoutput 	= 'html';
				$mail->Host 		= $host;
				$mail->Port 		= $port;
				$mail->SMTPSecure 	= $smtp_secure;
				$mail->SMTPAuth 	= $smtp_auth;
				$mail->Username 	= $SMTPusername;
				$mail->Password 	= $SMTPpassword;
				$mail->setFrom($SMTPusername, $setFrom);
				$mail->addReplyTo($SMTPusername);
				$mail->IsHTML(true);
				return $mail;
			}catch(MailerException $e){
				$resp = array( "status" => "Internal Error", "message" => $e->errorMessage(), "Other_reason" => "Message could not be sent. Mailer Error:".$this->mail->ErrorInfo);
			}
		}else{
			return 0;
		}
	}

	public function get_customer_information_with_reservation($res_con_id, $limit, $status){
		$select = " SELECT 
						res_con.client_id,
						res_con.reference_num,
						res.rate_total,
						res.commissions,
						res.discount,
						(CASE 
							WHEN cli.name IS NULL THEN cli.surname
							WHEN cli.surname IS NULL THEN cli.name
							ELSE
								CONCAT(cli.name, CONCAT(' ',cli.surname))
						END) as name,
						cli.email,
						apt.apartment_name,
						bs.booking_source_name,
						bs.commission_paid,
						rt.name as room,
						st.start_date,
						en.end_date,
						prev_start.start_date as prev_start_date,
						prev_end.end_date as prev_end_date,
						trh.rate_total as prev_rate_total,
						trh.discount as prev_discount,
						res.inserted_date,
						cli.reference_email,
						cli.phone,
						resn.note as reservation_notes,
						agnt.agent_name
					FROM reservation_conn res_con
					LEFT JOIN reservation res ON res.reservation_conn_id = res_con.reservation_conn_id
					LEFT JOIN clients cli ON res.clients_id = cli.clients_id
					LEFT JOIN apartments apt ON apt.apartment_id = res.appartments_id
					LEFT JOIN booking_source bs ON res_con.bookingsource_id = bs.booking_source_id
					LEFT JOIN room_types rt	ON apt.roomtype_id = rt.room_type_id
					LEFT JOIN periods st ON res.date_start_id = st.periods_id
					LEFT JOIN periods en ON res.date_end_id = en.periods_id
					LEFT JOIN transfer_room_history trh ON res_con.reservation_conn_id = trh.reservation_conn_id
					LEFT JOIN periods prev_start ON trh.date_start_id = prev_start.periods_id
					LEFT JOIN periods prev_end ON trh.date_end_id = prev_end.periods_id
					LEFT JOIN reservation_notes resn ON resn.reservation_id = res.reservation_id AND resn.note_type_id = '1' 
					LEFT JOIN booking_agents bkga ON bkga.reservation_id = res.reservation_id
					LEFT JOIN agents agnt ON agnt.id = bkga.agent_id
					WHERE res_con.reservation_conn_id = '".$res_con_id."'
					AND res.status = '".$status."'	
					ORDER BY trh.transfer_room_id DESC LIMIT ".$limit."
				";
		$result = $this->executeQuery($select);
		if(count($result) > 0 && $limit == 1 || $limit <=1){
			return $result[0];
		}else if($limit >= 20){
			return $result;
		}else{
			return 0;
		}
	}

	public function get_customer_information_with_reservation_OTA($res_con_id, $limit, $status){
		$select = " SELECT 
						res_con.client_id,
						res_con.reference_num,
						res.rate_total,
						res.commissions,
						res.discount,
						(CASE 
							WHEN cli.name IS NULL THEN cli.surname
							WHEN cli.surname IS NULL THEN cli.name
							ELSE
								CONCAT(cli.name, CONCAT(' ',cli.surname))
						END) as name,
						cli.email,
						apt.apartment_name,
						bs.booking_source_name,
						bs.commission_paid,
						rt.name as room,
						st.start_date,
						en.end_date,
						prev_start.start_date as prev_start_date,
						prev_end.end_date as prev_end_date,
						res.inserted_date,
						cli.reference_email,
						cli.phone,
						resn.note as reservation_notes
					FROM reservation_conn res_con
					LEFT JOIN reservation res ON res.reservation_conn_id = res_con.reservation_conn_id
					LEFT JOIN clients cli ON res.clients_id = cli.clients_id
					LEFT JOIN apartments apt ON apt.apartment_id = res.appartments_id
					LEFT JOIN booking_source bs ON res_con.bookingsource_id = bs.booking_source_id
					LEFT JOIN room_types rt	ON apt.roomtype_id = rt.room_type_id
					LEFT JOIN periods st ON res.date_start_id = st.periods_id
					LEFT JOIN periods en ON res.date_end_id = en.periods_id
					LEFT JOIN periods prev_start ON res_con.date_start_id = prev_start.periods_id
					LEFT JOIN periods prev_end ON res_con.date_end_id = prev_end.periods_id
					LEFT JOIN reservation_notes resn ON resn.reservation_id = res.reservation_id AND resn.note_type_id = '1' 
					WHERE res_con.reservation_conn_id = '".$res_con_id."'
					AND res.status = '".$status."'	
					LIMIT ".$limit."	
				";
		$result = $this->executeQuery($select);
		if(count($result) > 0 && $limit == 1 || $limit <=1){
			return $result[0];
		}else if($limit >= 20){
			return $result;
		}else{
			return 0;
		}
	}

	public function get_customer_informations($res_con_id, $status, $list_id = null){
		if($list_id != null){
			$select = "SELECT  res_con.client_id, res_con.reference_num, res.rate_total, res.commissions, res.discount, (CASE WHEN cli.name IS NULL THEN cli.surname 	WHEN cli.surname IS NULL THEN cli.name 	ELSE CONCAT(cli.name, CONCAT(' ',cli.surname)) END) as name, cli.email, apt.apartment_name, bs.booking_source_name, bs.commission_paid, rt.name as room, st.start_date, en.end_date, res.inserted_date, res.reservation_id, res.reservation_conn_id, cli.reference_email, cli.phone, resn.note as reservation_notes, agnt.agent_name
					FROM reservation_conn res_con
					LEFT JOIN reservation res ON res.reservation_conn_id = res_con.reservation_conn_id
					LEFT JOIN clients cli ON res.clients_id = cli.clients_id
					LEFT JOIN apartments apt ON apt.apartment_id = res.appartments_id
					LEFT JOIN booking_source bs ON res_con.bookingsource_id = bs.booking_source_id
					LEFT JOIN room_types rt	ON apt.roomtype_id = rt.room_type_id
					LEFT JOIN periods st ON res.date_start_id = st.periods_id
					LEFT JOIN periods en ON res.date_end_id = en.periods_id
					LEFT JOIN booking_agents bkga ON bkga.reservation_id = res.reservation_id
					LEFT JOIN agents agnt ON agnt.id = bkga.agent_id
					LEFT JOIN reservation_notes resn ON resn.reservation_id = res.reservation_id AND resn.note_type_id = '1' 
					WHERE res_con.reservation_conn_id = '".$res_con_id."'
					AND res.reservation_id IN (".$list_id.")
					AND res.status = '".$status."'	
					ORDER BY res.reservation_id DESC
			";
		}else{
			$select = "SELECT  res_con.client_id, res_con.reference_num, res.rate_total, res.commissions, res.discount, (CASE  WHEN cli.name IS NULL THEN cli.surname WHEN cli.surname IS NULL THEN cli.name ELSE CONCAT(cli.name, CONCAT(' ',cli.surname)) END) as name, cli.email, apt.apartment_name, bs.booking_source_name, bs.commission_paid, rt.name as room, st.start_date, en.end_date, res.inserted_date, res.reservation_id, res.reservation_conn_id, cli.reference_email, cli.phone, resn.note as reservation_notes, agnt.agent_name
					FROM reservation_conn res_con
					LEFT JOIN reservation res ON res.reservation_conn_id = res_con.reservation_conn_id
					LEFT JOIN clients cli ON res.clients_id = cli.clients_id
					LEFT JOIN apartments apt ON apt.apartment_id = res.appartments_id
					LEFT JOIN booking_source bs ON res_con.bookingsource_id = bs.booking_source_id
					LEFT JOIN room_types rt	ON apt.roomtype_id = rt.room_type_id
					LEFT JOIN periods st ON res.date_start_id = st.periods_id
					LEFT JOIN periods en ON res.date_end_id = en.periods_id
					LEFT JOIN booking_agents bkga ON bkga.reservation_id = res.reservation_id
					LEFT JOIN agents agnt ON agnt.id = bkga.agent_id
					LEFT JOIN reservation_notes resn ON resn.reservation_id = res.reservation_id AND resn.note_type_id = '1' 
					WHERE res_con.reservation_conn_id = '".$res_con_id."'
					AND res.status = '".$status."'	
					ORDER BY res.reservation_id DESC
				";
		}
		$result = $this->executeQuery($select);
		return $result;
	}

	public function select_template($action){
		$templateSql 	= "SELECT * FROM email_template WHERE action='".$action."' ";
		$template 		= $this->executeQuery($templateSql);
		return $template[0]['value'];
	}

	public function get_email_template($lang){
		$templateSql 	= "SELECT * FROM email_template WHERE language='".$lang."' ";
		$template 		= $this->executeQuery($templateSql);
		return $template[0];
	}

	public function prepare_cancel_noshow_email($res_type, $res_con_id, $ids=null, $cancelled_ = ""){
		$mail = $this->setup_config();
		// email switch check
		if($mail == 0):
			$resp = array('status' => "error", "message" => "Email Switch is OFF");
		else:
			$status = $res_type == "cancel" ? "cancelled" : "no_show";
			$id_list = implode(',', $ids);
			$customer = $this->get_customer_informations($res_con_id, $status, $id_list);
			// select template language
			$language   = (array) json_decode($this->get_global_variables('email_template_language'));
			$lang 		= $language['language'] == null ? "en" : $language['language'];
			$locale     = $language['locale'] == null ? "en_US" : $language['locale'];
			setlocale(LC_ALL, $locale);
			$selected_template = $this->get_email_template($lang);
			$other_texts 	= $selected_template['other_text'];
			$text 			= (array) json_decode($other_texts);	

			$rsrvtn_cnt = $this->executeQuery("SELECT count(reservation_id) as res_count FROM reservation WHERE reservation_conn_id = '$res_con_id' AND status = 'active' ");
			$text_partial = $text['partial'] == null ? "PARTIAL" : strtoupper($text['partial']);
			$res_count = $rsrvtn_cnt[0]['res_count'] > 1 ? " - ".$text_partial : "";

			/*customer condition*/
			if($customer != null){
				$datemodi       = new DateTime('now');
				$dateCnclNshw 	= ucfirst(strftime('%h %e, %Y', strtotime($datemodi->format('M d, Y') )) );
				$referenceNum 	= $customer[0]['reference_num'];
				$guestName 		= $customer[0]['name'];
				$date_inserted 	= new DateTime($customer[0]['inserted_date']);
				$dateCreated 	= ucfirst(strftime('%h %e, %Y', strtotime($date_inserted->format('M d, Y') )) )." ".$text['to']." ".$date_inserted->format('h:i A');
				$bookingSource 	= $customer[0]['agent_name'] == null ? $customer[0]['booking_source_name'] : "Agent: ".$customer[0]['agent_name'];
				// $bookingSource 	= $customer[0]['booking_source_name'];
				$cancelled_from = $cancelled_ == "OTA" ? " ".$text['from']." ".$bookingSource : "";
				$siteTitle 		= $this->get_global_variables('email_company_name');
				$site_title 	= $siteTitle == null ? "the HotelPMS" : $siteTitle;
				$sign 			= $this->get_global_variables('currency_symbol');
				$decimal_sep    = $this->get_global_variables('decimal_sep');
				$thousand_sep 	= $this->get_global_variables('thousand_sep');
				// set address
				if($customer[0]['email'] == null || $customer[0]['email'] == ""){ $address = $customer[0]['reference_email']; }
				else if($customer[0]['reference_email'] == null || $customer[0]['reference_email'] == ""){ $address = $customer[0]['email']; }
				else{ $address 	= ""; }
				$telephone      = $customer[0]['phone'] == null || $customer[0]['phone'] == "" ? "-" : $customer[0]['phone'];
				$res_notes 		= $customer[0]['reservation_notes'] == null || $customer[0]['reservation_notes'] == "" ? "-" : $customer[0]['reservation_notes'];
				$body 			= "";
				$count 			= count($customer);
				$commissions_paid = $this->booking_source_commision_paid($bookingSource);
				for($x=0; $x<$count; $x++ ){
					$dateStart 	= new DateTime($customer[$x]['start_date']);
					$dateEnd 	= new DateTime($customer[$x]['end_date']);
					$dateFrom 	= ucfirst(strftime('%h %e, %Y', strtotime($dateStart->format('M d, Y') )) );
					$dateTo 	= ucfirst(strftime('%h %e, %Y', strtotime($dateEnd->format('M d, Y') )) );
					// $discount   = $customer[$x]['discount'];
					// $rate  		= $customer[$x]['rate_total'] - $discount;
					// $roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					if($customer[$x]['commission_paid'] == "yes"){
						$commission = $customer[$x]['commissions'];
						$rate_total = $customer[$x]['rate_total'];
						$discounted = $rate_total - $customer[$x]['discount'];  
						$dif 		= $discounted - $commission;
						$roomRate 	= number_format((float)$dif, 2, $decimal_sep, $thousand_sep);
					}else{
						$discount   = $customer[$x]['discount'];
						$rate  		= $customer[$x]['rate_total'] - $discount;
						$roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					}
					$roomName 	= $customer[$x]['room'];
					$roomNo 	= $customer[$x]['apartment_name'];
					$body_temp  = $selected_template['sub_body'];
					// $body_temp 	= "
					// 	<tr>
                        //     <td colspan='2'>
                        //         <div style='color: #636363; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 150%; text-align: left;'>
                        //             <table id='room' border='0' cellpadding='15' cellspacing='0' width='100%' style='color: #636363; background-color: #d9edf7; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 14px; line-height: 0%; text-align: left; box-shadow: 0 1px 4px rgba(0,0,0,0.2) !important;'>
                        //                 <tr style='color: #fff; background-color: #303d50;'>
                        //                     <td colspan='2' style='text-align: center; font-weight: bold; border: 2px solid #d9edf7;'>[roomName]</td>
                        //                 </tr>
                        //                 <tr>
                        //                     <td>Period</td>
                        //                     <td>:&nbsp;<b>[dateFrom] - [dateTo]</b></td>
                        //                 </tr>
                        //                 <tr>
                        //                     <td>Room No</td>
                        //                     <td>:&nbsp;<b>[roomNo]</b></td>
                        //                 </tr>
                        //                 <tr>
                        //                     <td>Room Rate</td>
                        //                     <td>:&nbsp;[sign]<b>[roomRate]/night</b></td>
                        //                 </tr>
                        //             </table>
                        //         </div>
                        //     </td>
                        // </tr>
					// ";
					$search_body 	= array("[roomName]","[dateFrom]","[dateTo]","[sign]","[roomRate]","[roomNo]","[trDate]");
					$trDate 	 = "";
					$last   	 = $count - 1;
					if($x == $last){
						$initialBooking = $selected_template['initial_booking'];
						$cncl_nshw 		= $res_type == "cancel" ? $selected_template['date_cancelled'] : $selected_template['date_noshow'];
						$trDate 		= "<tr style='background-color: #fff;'><td>".$initialBooking."</td><td>:&nbsp;<b>".$dateCreated."</b></td></tr>";
						$trDate 	   .= "<tr style='background-color: #fff;'><td>".$cncl_nshw."</td><td>:&nbsp;<b>".$dateCnclNshw.$cancelled_from."</b></td></tr>";
					}
					$replace_body 	= array($roomName, $dateFrom, $dateTo, $sign, $roomRate, $roomNo, $trDate);
					$body 		   .= str_replace($search_body, $replace_body, $body_temp);
				}
				$searchVal 		= array("[referenceNum]","[guestName]","[dateCreated]","[bookingSource]","[note]","[siteTitle]","[body]","[email]","[telephone]","[reservationNotes]");
				$emListSql 		= "SELECT cancel,no_show,email FROM email_list WHERE status='active'";
				$emailList 		= $this->executeQuery($emListSql);	
				$email_count	= count($emailList);

				if($res_type == "cancel"):
					$redCircle = '❌'; 		/*https://emojipedia.org/cross-mark/*/
					$cancel_template = $selected_template['cancel'];	
					if($address != null || $address != ""){
						$note 	 = "";
						//send email for customer
						// $subject 	  	= $redCircle." | ".$selected_template['cancel_subject']." | ".$bookingSource." | ".$site_title." | ".$dateFrom." - ".$dateTo;
						// $replaceVal   	= array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $body, $address, $telephone, $res_notes);
						// $email_body 	= str_replace($searchVal, $replaceVal, $cancel_template);
						// $cus_send_resp 	= $this->send_email_notification($mail, $address, $subject, $email_body);
					}else{
						$note = $selected_template['note'];
						$cus_send_resp = $note;
						$address = "-";
					}
					if( $email_count > 0 ){
						$msgSubject 	= $redCircle." | ".$selected_template['cancel_subject'].$res_count." | ".$bookingSource." | ".$site_title." | ".$dateFrom." - ".$dateTo." | ".$guestName;
						$replaceVal    	= array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $address, $telephone, $res_notes);
						// send mail for client
						for( $y = 0; $y < count ( $emailList ); $y++ ){
							if($emailList[$y]['cancel'] == '1'){
								$address 		 = $emailList[$y]['email'];
								$email_body 	 = str_replace($searchVal, $replaceVal, $cancel_template);
								$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $email_body);
							}
						}
					}
					$resp = array( "status" => "success", 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp, 'pakapin' => $customer, 'ids' => $id_list );
				elseif($res_type == "noshow"):
					$warningEmoji = "⭕";	/*https://emojipedia.org/heavy-large-circle/*/
					$noshow_template = $selected_template['no_show'];
					if($address != null || $address != ""){
						$note 	 = "";
						//send email for customer
						// $subject 	  	= $warningEmoji." | ".$selected_template['noshow_subject']." | ".$bookingSource." | ".$site_title." | ".$dateFrom." - ".$dateTo;
						// $replaceVal    	= array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $address, $telephone, $res_notes);
						// $email_body 	= str_replace($searchVal, $replaceVal, $noshow_template);
						// $cus_send_resp 	= $this->send_email_notification($mail, $address, $subject, $email_body);
					}else{
						$note = $selected_template['note'];
						$cus_send_resp = $note;
						$address = "-";
					}
					if( $email_count > 0 ){
						$msgSubject 	= $warningEmoji." | ".$selected_template['noshow_subject'].$res_count." | ".$bookingSource." | ".$site_title." | ".$dateFrom." - ".$dateTo." | ".$guestName;
						$replaceVal    	= array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $address, $telephone, $res_notes);
						// send mail for client
						for( $y = 0; $y < count ( $emailList ); $y++ ){
							if($emailList[$y]['no_show'] == '1'){
								$address 		 = $emailList[$y]['email'];
								$email_body 	 = str_replace($searchVal, $replaceVal, $noshow_template);
								$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $email_body);
							}
						}
					}
					$resp = array( "status" => "success", 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp , 'pakapin' => $customer, 'ids' => $id_list );
				else:
					$resp = array( "status" => "error", "message" => "Reservation Type =".$res_type);
				endif;
			}else{
				$resp = array( "status" => "error", "message" => "No data", 'pakapin' => $customer, 'ids' => $id_list );
			}
			/*end customer condition*/
		endif;
		// end email switch check
		return $resp;
	}

	public function prepare_transfer_email($transdata, $res_con_id){
		$mail = $this->setup_config();
		if($mail == 0):
			$resp = array('status' => "error", "message" => "Email Switch is OFF");
		else:
			$customer = $this->get_customer_information_with_reservation($res_con_id, 1, 'active');
			# select template language
			$language   = (array) json_decode($this->get_global_variables('email_template_language'));
			$lang 		= $language['language'] == null ? "en" : $language['language'];
			$locale     = $language['locale'] == null ? "en_US" : $language['locale'];
			setlocale(LC_ALL, $locale);
			$selected_template = $this->get_email_template($lang);
			$other_texts 	= $selected_template['other_text'];
			$text 			= (array) json_decode($other_texts);	
			if($customer != 0){
				$selectRoomTypeSql = "SELECT rt.name as current, rid.name as previous 
										FROM room_types rt
									LEFT JOIN room_types rid ON rid.room_type_id ='".$transdata[0]['previous_room_type_id']."'
									WHERE rt.room_type_id='".$transdata[0]['current_room_type_id']."'  ";
				$room_type 		= $this->executeQuery($selectRoomTypeSql);
				$apartamento	= "SELECT apartment_name FROM apartments WHERE apartment_id ='".$transdata[0]['apartment_id']."' ";
				$prev_room_no 	= $this->executeQuery($apartamento);
				$dateStart 		= new DateTime($transdata[0]['checkin_date']);
				$dateEnd 		= new DateTime($transdata[0]['checkout_date']);
				$datePrevStart  = new DateTime($customer['prev_start_date']);
				$datePrevEnd    = new DateTime($customer['prev_end_date']);
				$dateinsert 	= new DateTime($customer['inserted_date']);
				/*START OF BODY*/
				$siteLogo     	= $this->get_global_variables('email_logo');
				$referenceNum 	= $customer['reference_num'];
				//current room
				$roomName 		= $room_type[0]['current'];
				$roomNo 		= $customer['apartment_name'];
				$sign 			= $this->get_global_variables('currency_symbol');
				$decimal_sep    = $this->get_global_variables('decimal_sep');
				$thousand_sep 	= $this->get_global_variables('thousand_sep');
				$bookingSource 	= $customer['agent_name'] == null ? $customer['booking_source_name'] : "Agent: ".$customer['agent_name'];
				// $bookingSource 	= $customer['booking_source_name'];
				if($customer['commission_paid'] == "yes"){
					$commission = $customer['commissions'];
					$rate_total = $customer['rate_total'];
					$discounted = $rate_total - $customer['discount'];  
					$dif 		= $discounted - $commission;
					$roomRate 	= number_format((float)$dif, 2, $decimal_sep, $thousand_sep);
				}else{
					$discount   = $customer['discount'];
					$rate  		= $customer['rate_total'] - $discount;
					$roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
				}
				// $discount   = $customer['discount'];
				// $rate  		= $customer['rate_total'] - $discount;
				// $roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
				// $roomRate 		= number_format((float)$customer['rate_total'], 2, $decimal_sep, $thousand_sep);
				$dateFrom 		= ucfirst(strftime('%h %e, %Y', strtotime($dateStart->format('M d, Y') )) );
				$dateTo 		= ucfirst(strftime('%h %e, %Y', strtotime($dateEnd->format('M d, Y') )) );
				//previous room
				$pRoomName 		= $room_type[0]['previous'];
				$pRoomNo 		= $prev_room_no[0]['apartment_name'];
				// if($customer['commission_paid'] == "yes"){
				// 	$pcommission = $customer['commissions'];
				// 	$prate_total = $customer['prev_rate_total'];
				// 	$pdiscounted = $prate_total - $customer['prev_discount'];  
				// 	$pdif 		 = $pdiscounted - $pcommission;
				// 	$proomRate 	 = number_format((float)$pdif, 2, $decimal_sep, $thousand_sep);
				// }else{
				// 	$pdiscount   	= $customer['prev_discount'];
				// 	$prate  		= $customer['prev_rate_total'] - $pdiscount;
				// 	$proomRate 		= number_format((float)$prate, 2, $decimal_sep, $thousand_sep);
				// }
				$pdiscount   	= $customer['prev_discount'];
				$prate  		= $customer['prev_rate_total'] - $pdiscount;
				$proomRate 		= number_format((float)$prate, 2, $decimal_sep, $thousand_sep);
				// $proomRate 		= number_format((float)$customer['prev_rate_total'], 2, $decimal_sep, $thousand_sep);
				$datemodi       = new DateTime('now');
				$dateModified 	= ucfirst(strftime('%h %e, %Y', strtotime($datemodi->format('M d, Y') )) );
				if($customer['prev_start_date'] != null || $customer['prev_start_date'] != '' && $customer['prev_end_date'] != null || $customer['prev_start_date'] != ''){
					$pDateFrom	= ucfirst(strftime('%h %e, %Y', strtotime($datePrevStart->format('M d, Y') )) );
					$pDateTo	= ucfirst(strftime('%h %e, %Y', strtotime($datePrevEnd->format('M d, Y') )) );
				}else{
					$pDateFrom	= $dateFrom;
					$pDateTo	= $dateTo;
				}
				$guestName 		= $customer['name'];
				$dateCreated 	= ucfirst(strftime('%h %e, %Y', strtotime($dateinsert->format('M d, Y') )) )." ".$text['at']." ".$dateinsert->format('h:i A');
				$siteTitle 		= $this->get_global_variables('email_company_name');
				$site_title 	= $siteTitle == null ? "the HotelPMS" : $siteTitle;
				// set address
				if($customer['email'] == null || $customer['email'] == ""){ $address = $customer['reference_email']; }
				else if($customer['reference_email'] == null || $customer['reference_email'] == ""){ $address = $customer['email']; }
				else{ $address 	= ""; }
				$telephone      = $customer['phone'] == null || $customer['phone'] == "" ? "-" : $customer['phone'];
				$res_notes 		= $customer['reservation_notes'] == null || $customer['reservation_notes'] == "" ? "-" : $customer['reservation_notes'];
				/*END OF BODY*/
				$searchVal = array("[referenceNum]" , "[roomName]", "[roomNo]", "[sign]", "[roomRate]", "[pRoomName]", "[pRoomNo]", "[proomRate]", "[dateFrom]", "[dateTo]", "[guestName]", "[bookingSource]", "[note]", "[siteTitle]", "[pdateFrom]","[pdateTo]","[email]","[telephone]","[reservationNotes]", "[trDate]");
				//get all the receiving company emails
				$emListSql 		   = "SELECT transfer,email FROM email_list WHERE status='active'";
				$emailList 		   = $this->executeQuery($emListSql);	
				$email_count 	   = count($emailList);
				// $orangeDiamond 	   = '🔄'; // do not modify (https://emojipedia.org/anticlockwise-downwards-and-upwards-open-circle-arrows/)
				$orangeDiamond 	   = '➰';   /*https://emojipedia.org/curly-loop/*/
				$transfer_template = $selected_template['transfer'];

				if($customer['email'] != null || $customer['email'] != ""){
					$address = $customer['email'];        // setFrom
					$note 	 = "";
					//send email for customer
					// $subject 	   = $orangeDiamond." | ".$selected_template['transfer_subject']." | ".$bookingSource." | ".$site_title." | ".$roomName." - ".$roomNo." to ".$pRoomName." - ".$pRoomNo;
					// $replaceVal    = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $pRoomName, $pRoomNo, $proomRate, $dateFrom, $dateTo, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo, $dateModified, $address, $telephone, $res_notes);
					// $body 		   = str_replace($searchVal, $replaceVal, $transfer_template);
					// $cus_send_resp = $this->send_email_notification($mail, $address, $subject, $body);
				}else{
					$note = $selected_template['note'];
					$cus_send_resp = $note;
					$address = "-";
				}
				if( $email_count > 0 ){
					$trDate 	 = "";
					$initialBooking = $selected_template['initial_booking'];
					$modified_lbl 	= $selected_template['date_modified'];
					$trDate 		= "<tr style='background-color: #fff;'><td>".$initialBooking."</td><td>:&nbsp;<b>".$dateCreated."</b></td></tr>";
					$trDate 	   .= "<tr style='background-color: #fff;'><td>".$modified_lbl."</td><td>:&nbsp;<b>".$dateModified."</b></td></tr>";

					$msgSubject   = $orangeDiamond." | ".$selected_template['transfer_subject']." | ".$bookingSource." | ".$site_title." | ".$pRoomName." - ".$pRoomNo." to ".$roomName." - ".$roomNo." | ".$guestName;
					$replaceVal   = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $pRoomName, $pRoomNo, $proomRate, $dateFrom, $dateTo, $guestName, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo, $address, $telephone, $res_notes, $trDate);
					// send mail for client
					for( $y = 0; $y < count ( $emailList ); $y++ ){
						if($emailList[$y]['transfer'] == '1'){
							$address 		 = $emailList[$y]['email'];
							$body    		 = str_replace($searchVal, $replaceVal, $transfer_template);
							$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $body);
						}
					}
				}
				$resp = array( "status" => "success", 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp , "email_data" => $customer, "commissions" => $commissions_paid);
			}else{
				$resp = array( "status" => "error", "message" => "No data");
			}
		endif;
		return $resp;
	}	

	// public function prepare_extend_modify_email($housekeeping_data, $period_type){
	// 	$mail = $this->setup_config();
	// 	if($mail == 0):
	// 		$resp = array('status' => "error", "message" => "Email Switch is OFF");
	// 	else:
	// 		if($housekeeping_data[0]['res_con_id'] != null || $housekeeping_data[0]['res_con_id'] != ''){
	// 			/*Exucutes on modified reservation from OTA*/
	// 			$res_con_id = $housekeeping_data[0]['res_con_id'];
	// 			$customer = $this->get_customer_information_with_reservation_OTA($res_con_id, 1, 'active'); 
	// 		}else{
	// 			$res_con_id = $housekeeping_data['reservation_conn_id'];
	// 			$customer = $this->get_customer_information_with_reservation($res_con_id, 1, 'active'); 
	// 		}
	// 		if($customer != 0){
	// 			$lang = "en";
	// 			$selected_template = $this->get_email_template($lang);
	// 			$decimal_sep    = $this->get_global_variables('decimal_sep');
	// 			$thousand_sep 	= $this->get_global_variables('thousand_sep');
	// 			if($housekeeping_data[0]['res_con_id'] == null){
	// 				$dateStart 		= new DateTime($housekeeping_data['new_checkin']);
	// 				$dateEnd 		= new DateTime($housekeeping_data['new_checkout']);
	// 				$datePrevStart  = new DateTime($housekeeping_data['old_check_in_date']);
	// 				$datePrevEnd    = new DateTime($housekeeping_data['old_check_out_date']);
	// 			}else{
	// 				$dateStart 		= new DateTime($customer['start_date']);
	// 				$dateEnd 		= new DateTime($customer['end_date']);
	// 				$datePrevStart  = new DateTime($customer['prev_start_date']);
	// 				$datePrevEnd    = new DateTime($customer['prev_end_date']);
	// 			}
	// 			$dateinsert 	= new DateTime($customer['inserted_date']);
	// 			// EMAIL BODY
	// 			$referenceNum 	= $customer['reference_num'];
	// 			$roomName 		= $customer['room'];
	// 			$roomNo 		= $customer['apartment_name'];
	// 			$sign 			= $this->get_global_variables('currency_symbol');
	// 			$roomRate 		= number_format((float)$customer['rate_total'], 2, $decimal_sep, $thousand_sep);
	// 			$dateFrom 		= $dateStart->format('M d, Y');
	// 			$dateTo 		= $dateEnd->format('M d, Y');
	// 			$guestName 		= $customer['name'];
	// 			$dateCreated 	= $dateinsert->format('M d, Y');
	// 			$bookingSource 	= $customer['booking_source_name'];
	// 			$siteTitle 		= $this->get_global_variables('site_title');
	// 			$pDateFrom 		= $datePrevStart->format('M d, Y');
	// 			$pDateTo 		= $datePrevEnd->format('M d, Y');
	// 			// END OF EMAIL BODY
	// 			// $extendedArrow 	= "⏩"; // do not modify (https://emojipedia.org/black-right-pointing-double-triangle/)
	// 			$extendedArrow = '➖';   /*https://emojipedia.org/heavy-minus-sign/*/
	// 			$searchVal 		= array("[referenceNum]" , "[roomName]", "[roomNo]", "[sign]", "[roomRate]", "[dateFrom]", "[dateTo]", "[guestName]", "[dateCreated]", "[bookingSource]", "[note]", "[siteTitle]", "[pdateFrom]","[pdateTo]");
	// 			if($period_type == "extend_period"){
	// 				/*EMAIL NOTIFICATION FOR EXTENDED RESERVATION*/
	// 				$template 		= $selected_template['extend'];
	// 				$emListSql	 	= "SELECT extended_book,email FROM email_list WHERE status='active'";
	// 				$emailList 		= $this->executeQuery($emListSql);	
	// 				$email_count 	= count($emailList);

	// 				if($customer['email'] != null || $customer['email'] != ""){
	// 					$address = $customer['email'];        // setFrom
	// 					$note 	 = "";
	// 					//send email for customer
	// 					$subject 	   = $extendedArrow." | ".$selected_template['extend_subject']." | ".$bookingSource." | ".$siteTitle." | ".$dateFrom." - ".$dateTo." to ".$pDateFrom." - ".$pDateTo;
	// 					$replaceVal    = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $dateFrom, $dateTo, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo);
	// 					$body 		   = str_replace($searchVal, $replaceVal, $template);
	// 					$cus_send_resp = $this->send_email_notification($mail, $address, $subject, $body);
	// 				}else{
	// 					$note = $selected_template['note'];
	// 					$cus_send_resp = $note;
	// 				}
	// 				if( $email_count > 0 ){
	// 					$msgSubject   = $extendedArrow." | ".$selected_template['extend_subject']." | ".$bookingSource." | ".$siteTitle." | ".$dateFrom." - ".$dateTo." to ".$pDateFrom." - ".$pDateTo." | ".$guestName;
	// 					$replaceVal   = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $dateFrom, $dateTo, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo);
	// 					// send mail for client
	// 					for( $y = 0; $y < count ( $emailList ); $y++ ){
	// 						if($emailList[$y]['extended_book'] == '1'){
	// 							$address = $emailList[$y]['email'];
	// 							$body    = str_replace($searchVal, $replaceVal, $template);
	// 							$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $body);
	// 						}
	// 					}
	// 				}
	// 			}else{
	// 				/*EMAIL NOTIFICATION FOR MODIFIED RESERVATION*/
	// 				// $modifyEmoji = "📝";   // do not modify (https://emojipedia.org/memo/)
	// 				$modifyEmoji 	= '➕';  /*https://emojipedia.org/heavy-plus-sign/*/
	// 				$template 		= $selected_template['modification'];
	// 				$emListSql 		= "SELECT modification,email FROM email_list WHERE status='active'";
	// 				$emailList 		= $this->executeQuery($emListSql);	
	// 				$email_count 	= count($emailList);

	// 				if($customer['email'] != null || $customer['email'] != ""){
	// 					$address = $customer['email'];        // setFrom
	// 					$note 	 = "";
	// 					//send email for customer
	// 					$subject 	   = $modifyEmoji." | ".$selected_template['modify_subject']." | ".$bookingSource." | ".$siteTitle." | ".$dateFrom." - ".$dateTo." to ".$pDateFrom." - ".$pDateTo;
	// 					$replaceVal    = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $dateFrom, $dateTo, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo);
	// 					$body 		   = str_replace($searchVal, $replaceVal, $template);
	// 					$cus_send_resp = $this->send_email_notification($mail, $address, $subject, $body);
	// 				}else{
	// 					$note = $selected_template['note'];
	// 					$cus_send_resp = $note;
	// 				}
	// 				if( $email_count > 0 ){
	// 					$msgSubject   = $modifyEmoji." | ".$selected_template['modify_subject']." | ".$bookingSource." | ".$siteTitle." | ".$dateFrom." - ".$dateTo." to ".$pDateFrom." - ".$pDateTo." | ".$guestName;
	// 					$replaceVal   = array($referenceNum, $roomName, $roomNo, $sign, $roomRate, $dateFrom, $dateTo, $guestName, $dateCreated, $bookingSource, $note, $siteTitle, $pDateFrom, $pDateTo);
	// 					// send mail for client
	// 					for( $y = 0; $y < count ( $emailList ); $y++ ){
	// 						if($emailList[$y]['modification'] == '1'){
	// 							$address = $emailList[$y]['email'];
	// 							$body    = str_replace($searchVal, $replaceVal, $template);
	// 							$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $body);
	// 						}
	// 					}
	// 				}
	// 			}
	// 			$resp = array( "status" => "success",'period_type'=> $period_type, 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp );
	// 		}else{
	// 			$resp = array( "status" => "error", "message" => "No data", 'id' => $res_con_id);
	// 		}
	// 	endif;
	// 	return $resp;
	// }

	public function prepare_full_extend_period_email($list_id, $res_data, $modified_ = ""){
		$mail = $this->setup_config();
		if($mail == 0):
			$resp = array('status' => "error", "message" => "Email Switch is OFF");
		else:
			$res_con_id   	= $res_data['reservation_conn_id'];
			$res_type 	  	= $res_data['reservation_period_type'];
			$ids 		  	= implode(',', $list_id);
			$old_check_in 	= new DateTime($res_data['socket_data']['old_check_in_date']);
			$old_check_out 	= new DateTime($res_data['socket_data']['old_check_out_date']);
			$data 		  	= $this->get_customer_informations($res_con_id,'active',$ids);
			if(isset($data)){
				# select template language
				$language   = (array) json_decode($this->get_global_variables('email_template_language'));
				$lang 		= $language['language'] == null ? "en" : $language['language'];
				$locale     = $language['locale'] == null ? "en_US" : $language['locale'];
				setlocale(LC_ALL, $locale);
				$selected_template = $this->get_email_template($lang);
				$other_texts 	= $selected_template['other_text'];
				$text 			= (array) json_decode($other_texts);	
				$date_inserted 	= new DateTime($data[0]['inserted_date']);
				$dateStart 		= new DateTime($data[0]['start_date']);
				$dateEnd 		= new DateTime($data[0]['end_date']);
				$datemodi       = new DateTime('now');
				$referenceNum 	= $data[0]['reference_num'];
				$guestName 	  	= $data[0]['name'];
				$bookingSource 	= $data[0]['agent_name'] == null ? $data[0]['booking_source_name'] : "Agent: ".$data[0]['agent_name'];
				// $bookingSource  = $data[0]['booking_source_name'];
				$modified_from  = $modified_ == "OTA" ? " ".$text['from']." ".$bookingSource : "";
				// set address
				if($data[0]['email'] == null || $data[0]['email'] == ""){ $address = $data[0]['reference_email']; }
				else if($data[0]['reference_email'] == null || $data[0]['reference_email'] == ""){ $address = $data[0]['email']; }
				else{ $address 	= ""; }
				$telephone      = $data[0]['phone'] == null || $data[0]['phone'] == "" ? "-" : $data[0]['phone'];
				$res_notes 		= $data[0]['reservation_notes'] == null || $data[0]['reservation_notes'] == "" ? "-" : $data[0]['reservation_notes'];
				$dateCreated 	= ucfirst(strftime('%h %e, %Y', strtotime($date_inserted->format('M d, Y') )) )." ".$text['at']." ".$date_inserted->format('h:i A');
				$dateFrom 		= ucfirst(strftime('%h %e, %Y', strtotime($dateStart->format('M d, Y') )) );
				$dateTo 		= ucfirst(strftime('%h %e, %Y', strtotime($dateEnd->format('M d, Y') )) );
				$pdateFrom 		= ucfirst(strftime('%h %e, %Y', strtotime($old_check_in->format('M d, Y') )) );
				$pdateTo 		= ucfirst(strftime('%h %e, %Y', strtotime($old_check_out->format('M d, Y') )) );
				$dateModified 	= ucfirst(strftime('%h %e, %Y', strtotime($datemodi->format('M d, Y') )) ).$modified_from;
				$sign 			= $this->get_global_variables('currency_symbol');
				$decimal_sep    = $this->get_global_variables('decimal_sep');
				$thousand_sep 	= $this->get_global_variables('thousand_sep');
				$count 			= count($data);
				$siteTitle 		= $this->get_global_variables('email_company_name');
				$site_title 	= $siteTitle == null ? "the HotelPMS" : $siteTitle;
				// $commissions_paid = $this->booking_source_commision_paid($bookingSource);
				for($x=0; $x<$count; $x++){	
					$roomName = $data[$x]['room'];
					$roomNo = $data[$x]['apartment_name'];
					if($data[$x]['commission_paid'] == "yes"){
						$commission = $data[$x]['commissions'];
						$rate_total = $data[$x]['rate_total'];
						$discounted = $rate_total - $data[$x]['discount'];  
						$dif 		= $discounted - $commission;
						$roomRate 	= number_format((float)$dif, 2, $decimal_sep, $thousand_sep);
					}else{
						$discount   = $data[$x]['discount'];
						$rate  		= $data[$x]['rate_total'] - $discount;
						$roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					}
					// $discount   = $customer[$x]['discount'];
					// $rate  		= $customer[$x]['rate_total'] - $discount;
					// $roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					// $roomRate = number_format((float)$data[$x]['rate_total'], 2, $decimal_sep, $thousand_sep);
					$body_temp  = "";
					if($x > 0){
						$body_temp .= "<tr><td style='padding: 0px 15px !important;' colspan='2'><hr></td></tr>"; //separator
					}
					$body_temp     .= $selected_template['sub_body_extended'];
					$last   	   = $count - 1;
					if($x == $last){
						$initialBooking = $selected_template['initial_booking'];
						$modified_lbl 	= $selected_template['date_modified'];
						$body_temp 	   .= "<tr style='background-color: #fff;'><td>".$initialBooking."</td><td>:&nbsp;<b>".$dateCreated."</b></td></tr>";
						$body_temp	   .= "<tr style='background-color: #fff;'><td>".$modified_lbl."</td><td>:&nbsp;<b>".$dateModified."</b></td></tr>";
					}
					$search_body 	= array("[roomName]","[sign]","[roomRate]","[roomNo]");
					$replace_body 	= array($roomName, $sign, $roomRate, $roomNo );
					$body 		   .= str_replace($search_body, $replace_body, $body_temp);
				}
				$searchVal 			= array("[referenceNum]","[guestName]","[bookingSource]","[note]","[siteTitle]","[body]", "[dateFrom]", "[dateTo]", "[pdateFrom]", "[pdateTo]","[email]","[telephone]","[reservationNotes]");
				$emListSql 		 	= "SELECT extended_book,modification,email FROM email_list WHERE status='active'";
				$emailList 		  	= $this->executeQuery($emListSql);	
				$email_count 	  	= count($emailList);
				if($res_type == "extend_period"){
					$template = $selected_template['extend'];
					$extendedArrow = '➖'; 
					if($address != null || $address != ""){
						$note 	 = "";
						//send email for customer
						// $subject 	   = $extendedArrow." | ".$selected_template['extend_subject']." | ".$bookingSource." | ".$site_title." | ".$pdateFrom." - ".$pdateTo." to ".$dateFrom." - ".$dateTo;
						// $replaceVal    = array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $dateModified, $dateFrom, $dateTo, $pdateFrom, $pdateTo, $address, $telephone, $res_notes);
						// $email_body    = str_replace($searchVal, $replaceVal, $template);
						// $cus_send_resp = $this->send_email_notification($mail, $address, $subject, $email_body);
					}else{
						$note = $selected_template['note'];
						$cus_send_resp = $note;
						$address = "-";
					}
					if( $email_count > 0 ){
						$msgSubject   = $extendedArrow." | ".$selected_template['extend_subject']." | ".$bookingSource." | ".$site_title." | ".$pdateFrom." - ".$pdateTo." to ".$dateFrom." - ".$dateTo." | ".$guestName;
						$replaceVal    = array($referenceNum, $guestName, $bookingSource, $note, $site_title, $body, $dateFrom, $dateTo, $pdateFrom, $pdateTo, $address, $telephone, $res_notes);
						// send mail for client
						for( $y = 0; $y < count ( $emailList ); $y++ ){
							if($emailList[$y]['extended_book'] == '1'){
								$address 		 = $emailList[$y]['email'];
								$email_body 	 = str_replace($searchVal, $replaceVal, $template);
								$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $email_body);
							}
						}
					}
					$resp = array( "status" => "success", 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp );
				}else if($res_type == "full_period"){
					$template = $selected_template['modification'];
					$modifyEmoji 	= '➕';  /*https://emojipedia.org/heavy-plus-sign/*/
					if($address != null || $address != ""){
						$note 	 = "";
						//send email for customer
						// $subject 	   = $modifyEmoji." | ".$selected_template['modify_subject']." | ".$bookingSource." | ".$site_title." | ".$pdateFrom." - ".$pdateTo." to ".$dateFrom." - ".$dateTo;
						// $replaceVal    = array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $dateModified, $dateFrom, $dateTo, $pdateFrom, $pdateTo, $address, $telephone, $res_notes);
						// $email_body    = str_replace($searchVal, $replaceVal, $template);
						// $cus_send_resp = $this->send_email_notification($mail, $address, $subject, $email_body);
					}else{
						$note = $selected_template['note'];
						$cus_send_resp = $note;
					}
					if( $email_count > 0 ){
						$msgSubject   = $modifyEmoji." | ".$selected_template['modify_subject']." | ".$bookingSource." | ".$site_title." | ".$pdateFrom." - ".$pdateTo." to ".$dateFrom." - ".$dateTo." | ".$guestName;
						$replaceVal    = array($referenceNum, $guestName, $bookingSource, $note, $site_title, $body, $dateFrom, $dateTo, $pdateFrom, $pdateTo, $address, $telephone, $res_notes);
						// send mail for client
						for( $y = 0; $y < count ( $emailList ); $y++ ){
							if($emailList[$y]['modification'] == '1'){
								$address 		 = $emailList[$y]['email'];
								$email_body 	 = str_replace($searchVal, $replaceVal, $template);
								$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $email_body);
							}
						}
					}
					$resp = array( "status" => "success", 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp );
				}
			}else{
				$resp = array( "status" => "error", "message" => "No data", 'id' => $res_con_id);
			}
		endif;
		return $resp;
	}

	public function prepare_new_reservation_email($res_con_id){
		$mail = $this->setup_config();
		if($mail == 0):
			$resp = array('status' => "error", "message" => "Email Switch is OFF");
		else:
			$customer 	= $this->get_customer_information_with_reservation($res_con_id, 20, 'active');
			//select email template language
			$language   = (array) json_decode($this->get_global_variables('email_template_language'));
			$lang 		= $language == null ? "en" : $language['language'];
			$locale     = $language == null ? "en_US" : $language['locale'];
			setlocale(LC_ALL, $locale);
			$selected_template = $this->get_email_template($lang);
			$other_texts 	= $selected_template['other_text'];
			$text 			= (array) json_decode($other_texts);	
			if($customer != 0){
				$referenceNum 	= $customer[0]['reference_num'];
				$guestName 		= $customer[0]['name'];
				$date_inserted 	= new DateTime($customer[0]['inserted_date']);
				$dateCreated 	= ucfirst(strftime('%h %e, %Y', strtotime($date_inserted->format('M d, Y') )) )." ".$text['at']." ".$date_inserted->format('h:i A');
				$bookingSource 	= $customer[0]['agent_name'] == null ? $customer[0]['booking_source_name'] : "Agent: ".$customer[0]['agent_name'];
				$siteTitle 		= $this->get_global_variables('email_company_name');
				$site_title 	= $siteTitle == null ? "the HotelPMS" : $siteTitle;
				$sign 			= $this->get_global_variables('currency_symbol');
				$decimal_sep    = $this->get_global_variables('decimal_sep');
				$thousand_sep 	= $this->get_global_variables('thousand_sep');
				// set address
				if($customer[0]['email'] == null || $customer[0]['email'] == ""){ $address = $customer[0]['reference_email']; }
				else if($customer[0]['reference_email'] == null || $customer[0]['reference_email'] == ""){ $address = $customer[0]['email']; }
				else{ $address 	= ""; }
				$telephone      = $customer[0]['phone'] == null || $customer[0]['phone'] == "" ? "-" : $customer[0]['phone'];
				$res_notes 		= $customer[0]['reservation_notes'] == null || $customer[0]['reservation_notes'] == "" ? "-" : $customer[0]['reservation_notes'];
				$body 			= "";
				$count 			= count($customer);
				for($x=0; $x<$count; $x++ ){
					$dateStart 	= new DateTime($customer[$x]['start_date']);
					$dateEnd 	= new DateTime($customer[$x]['end_date']);
					$dateFrom 	= ucfirst(strftime('%h %e, %Y', strtotime($dateStart->format('M d, Y') )) );
					$dateTo 	= ucfirst(strftime('%h %e, %Y', strtotime($dateEnd->format('M d, Y') )) );
					if($customer[$x]['commission_paid'] == "yes"){
						$commission = $customer[$x]['commissions'];
						$rate_total = $customer[$x]['rate_total'];
						$discounted = $rate_total - $customer[$x]['discount'];  
						$dif 		= $discounted - $commission;
						$roomRate 	= number_format((float)$dif, 2, $decimal_sep, $thousand_sep);
					}else{
						$discount   = $customer[$x]['discount'];
						$rate  		= $customer[$x]['rate_total'] - $discount;
						$roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					}
					// $discount   = $customer[$x]['discount'];
					// $rate  		= $customer[$x]['rate_total'] - $discount;
					// $roomRate 	= number_format((float)$rate, 2, $decimal_sep, $thousand_sep);
					$roomName 	 = $customer[$x]['room'];
					$roomNo 	 = $customer[$x]['apartment_name'];
					$body_temp   = $selected_template['sub_body'];
					$search_body = array("[roomName]","[dateFrom]","[dateTo]","[sign]","[roomRate]","[roomNo]","[trDate]");
					$trDate 	 = "";
					$last   	 = $count - 1;
					if($x == $last){
						$initialBooking = $selected_template['initial_booking'];
						$trDate 		= "<tr style='background-color: #fff;'><td>".$initialBooking."</td><td>:&nbsp;<b>".$dateCreated."</b></td></tr>";
					}
					$replace_body 	= array($roomName, $dateFrom, $dateTo, $sign, $roomRate, $roomNo, $trDate);
					$body 		   .= str_replace($search_body, $replace_body, $body_temp);
				}
				$searchVal 		  = array("[referenceNum]","[guestName]","[bookingSource]","[note]","[siteTitle]","[body]","[email]","[telephone]","[reservationNotes]");
				// $reservationEmoji = "🛏️"; //do not modify (https://emojipedia.org/bed/)
				$reservationEmoji = '✔️';  /*https://emojipedia.org/heavy-check-mark/*/
				$template 		  = $selected_template['new_reservation'];
				$emListSql 		  = "SELECT new_reservation,email FROM email_list WHERE status='active'";
				$emailList 		  = $this->executeQuery($emListSql);	
				$email_count 	  = count($emailList);
				if($address != null || $address != ""){
					$note 	 = "";
					$emailStyle = "";
					//send email for customer
					// $subject 	   = $reservationEmoji." | ".$selected_template['new_subject']." | ".$bookingSource." | ".$site_title." | ".$dateFrom." to ".$dateTo;
					// $replaceVal    = array($referenceNum, $guestName, $dateCreated, $bookingSource, $note, $site_title, $body, $address, $telephone, $res_notes);
					// $email_body    = str_replace($searchVal, $replaceVal, $template);
					// $cus_send_resp = $this->send_email_notification($mail, $address, $subject, $email_body);
				}else{
					$note = $selected_template['note'];
					$cus_send_resp = $note;
					$emailStyle = "display:none;";
					$address = "-";
				}
				if( $email_count > 0 ){
					$msgSubject   = $reservationEmoji." | ".$selected_template['new_subject']." | ".$bookingSource." | ".$site_title." | ".$dateFrom." to ".$dateTo." | ".$guestName;
					$replaceVal   = array($referenceNum, $guestName, $bookingSource, $note, $site_title, $body, $address, $telephone, $res_notes);
					// send mail for client
					for( $y = 0; $y < count ( $emailList ); $y++ ){
						if($emailList[$y]['new_reservation'] == '1'){
							$address 		 = $emailList[$y]['email'];
							$email_body 	 = str_replace($searchVal, $replaceVal, $template);
							$elist_send_resp = $this->send_email_notification($mail, $address, $msgSubject, $email_body);
						}
					}
				}
				$resp = array( "status" => "success",'id'=> $res_con_id, 'Customer Send Status' => $cus_send_resp, 'Emails Send Status' => $elist_send_resp, 'email_body' => $email_body );
			}else{
				$resp = array( "status" => "error", "message" => "No data", 'id' => $res_con_id);
			}
		endif;
		return $resp;	
	}

	public function booking_source_commision_paid($booking_source_name){
		$sql = "SELECT * FROM booking_source WHERE booking_source_name = '".$booking_source_name."' AND commission_paid = 'yes' AND status = 'active' ";
		$commission_paid = $this->executeQuery($sql);
		if($commissions_paid != null){
			return "yes";
		}else{
			return "no";
		}
	}

	public function send_email_notification($mail, $address, $subject, $body){
		$config = $this->emailConfig();  
		$mail->ClearAddresses();  //clear queue of each AddAddress add to list
		$mail->ClearCCs();
		$mail->addAddress($address);
		$mail->Subject = $subject;
		$mail->msgHTML($body);
		$mail->AltBody = $body;
		$result = $mail->Send();
		if ($result) {
			$resps = array( "status" => "success", "message" => "Email Sent" );
		}else{
			$resps = array( "status" => "error", "message" => "Message could not be sent. Mailer Error:".$this->mail->ErrorInfo);
		}
		if($config['imap_status'] == true || $config['imap_status'] == '1'){
			$stream      = imap_open("{".$config['imap_host'].":".$config['imap_port'].$config['imap_flags']."}", $config['email'] , $config['password']);
		    $mail_string = $mail->getSentMIMEMessage();
		    $imap_result = imap_append($stream, "{".$config['imap_host'].":".$config['imap_port'].$config['imap_flags']."}"."Sent", $mail_string, "\\Seen");				
			if($imap_result):
				imap_close($stream);
				$im = array( "status" => "success", "message" => "IMAP SAVED" );
			else:
				$im = array( "status" => "error", "message" => "couldn't connect to IMAP" );
			endif;
		}else{
			$im = array( "status" => "error", "message" => "IMAP is either error or closed" );
		}
		$resp = array( "status" => "success", "message" => $resps, 'imap' => $im );
		return $resp;
	}
	/*EMAIL NOTIFICATION END HERE*/
	public function get_all_email_config($status){
		if($status == 1 || $status == 0){
			if($status == 1){
				$stat = 'active';
			}else{
				$stat = 'inactive';
			}
			$sql = "SELECT * FROM email_config WHERE deleted_at IS NULL AND status = '".$stat."'";
		}else{
			$sql = "SELECT * FROM email_config WHERE deleted_at IS NULL ORDER BY status";
		}
		$email_auth = $this->executeQuery($sql); 
		if(count($email_auth) > 0 ){
			return $email_auth;
		}else{
			return 0;
		}
	}

	public function get_email_lists($status){
		if($status == 1 || $status == 0){
			if($status == 1){
				$stat = 'active';
			}else{
				$stat = 'inactive';
			}
			$sql = "SELECT * FROM email_list WHERE deleted_at IS NULL AND status = '".$stat."'  ";
		}else{
			$sql = "SELECT * FROM email_list WHERE deleted_at IS NULL ORDER BY status ";
		}
		$email_list = $this->executeQuery($sql); 
		if(count($email_list) > 0 ){
			return $email_list;
		}else{
			return 0;
		}
	}

	public function add_outgoing_email($data){
		$email 		= $data['email'];
		$status 	= $data['status'];
		$new_res 	= $data['new_res'];
		$no_show 	= $data['no_show'];
		$cancel 	= $data['cancel'];
		$modify 	= $data['modify'];
		$extended 	= $data['extended'];
		$transfer 	= $data['transfer'];
		$name 		= $data['name'];
		if($email != null || $email != null || $email != ''){
			$sqlInsert 	= "INSERT INTO 
				email_list 
				(
					email_list_id, 
					email, 
					status, 
					new_reservation, 
					no_show, 
					cancel, 
					modification, 
					extended_book, 
					transfer,
					name
				) 
				VALUES 
				(
					:id, 
					:email, 
					:status, 
					:new_res, 
					:no_show, 
					:cancel, 
					:modify, 
					:extended, 
					:transfer, 
					:name
				) ";
			$stmt = $this->db->prepare($sqlInsert);
			try {
				$stmt->execute(array(
					':id' 		=> null,
					':email' 	=> $email,
					':status' 	=> $status,
					':new_res' 	=> $new_res,
					':no_show' 	=> $no_show,
					':cancel' 	=> $cancel,
					':modify' 	=> $modify,
					':extended' => $extended,
					':transfer' => $transfer,
					':name'		=> $name
				));
				$resp = array( "status" => "Success", "message" => "Successfully Saved" );
				/* echo json_ecode($resp); */
			} catch(PDOException $e) {
				$resp = array( "status"=> "Error", "message" => "Duplicate email, email already saved!" );
			}
		}else{	
			$resp = array( "status" => "Error", "message" => "Data or email is null" );
		}
		return $resp;
	}

	// this function is used on notify cancel, and notify modification from OTA
	public function select_con_id($reference, $status= 'cancelled'){
		$sql = "SELECT rc.reservation_conn_id as res_con_id FROM reservation_conn rc
				LEFT JOIN reservation res ON res.reservation_conn_id = rc.reservation_conn_id 
				WHERE rc.reference_num = '".$reference."'
				AND res.status = '".$status."'  ";
		$selected = $this->executeQuery($sql);
		return $selected;
	}
	/*-------------------------------------------------------------------------*/

	public function update_outgoing_email($data){
		if($data != null || $data['email'] != null || $data['email'] != ''){
			$id 		= $data['id'];
			$email 		= $data['email'];
			$status 	= $data['status'];
			$new_res 	= $data['new_res'];
			$no_show 	= $data['no_show'];
			$cancel 	= $data['cancel'];
			$modify 	= $data['modify'];
			$extended 	= $data['extended'];
			$transfer 	= $data['transfer'];
			$name 		= $data['name'];
			$type 		= $data['type'];
			if($type == 'edit'){
				$select_email_sql = "SELECT * FROM email_list WHERE email LIKE '".$email."' ";
				$selected = $this->executeQuery($select_email_sql);
				$sel_email = $selected[0]['email'];
				if($sel_email != null){
					$sql = "UPDATE email_list SET name='".$name."', status='".$status."', new_reservation='".$new_res."', no_show='".$no_show."', cancel='".$cancel."', modification='".$modify."', extended_book='".$extended."', transfer='".$transfer."' WHERE email='".$email."' ";
					$statement = $this->db->prepare($sql);
					try{
						$stat = $statement->execute();
						if(isset($stat)){
							$resp = array( "status" => "Success", "message" => "Edit Successful" , "update_data" => $data);
						}else{
							$resp = array( "status" => "Error", "message" => "Edit Unsuccessful" , "update_data" => $data);
						}
					}catch(PDOException $e) {
						$resp = array( "status"=> "error", "message" => $e->getMessage(), "data" => $data);
					}
				}else{
					$sql = "UPDATE email_list SET name='".$name."', email='".$email."', status='".$status."', new_reservation='".$new_res."', no_show='".$no_show."', cancel='".$cancel."', modification='".$modify."', extended_book='".$extended."', transfer='".$transfer."' WHERE email_list_id='".$id."' ";
					$statement = $this->db->prepare($sql);
					try{
						$stat = $statement->execute();
						if(isset($stat)){
							$resp = array( "status" => "Success", "message" => "Edit Successful" , "update_data" => $data);
						}else{
							$resp = array( "status" => "Error", "message" => "Edit Unsuccessful" , "update_data" => $data);
						}
					}catch(PDOException $e) {
						$resp = array( "status"=> "Error", "message" => $e->getMessage(), "update_data" => $data);
					}
				}				
			}else if($type == 'delete'){
				$date  = new DateTime('now');
				$ndate = $date->format('Y-m-d h:m:s');
				$sql   = "UPDATE email_list SET deleted_at='".$ndate."' WHERE email_list_id='".$id."' ";
				$statement = $this->db->prepare($sql);
				try{
					$stat = $statement->execute();
					if(isset($stat)){
						$resp = array( "status" => "Success", "message" => "Deleted Successfully" , "update_data" => $data);
					}else{
						$resp = array( "status" => "Error", "message" => "Delete Unsuccessful" , "update_data" => $data);
					}
				}catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage(), "data" => $data);
				}
			}
		}else{	
			$resp = array( "status" => "Error", "message" => "Data is null" );
		}
		return $resp;
	}

	public function boolean_convert($data){
		if($data != true || $data != 'true'){
			return 0;
		}else{
			return 1;
		}
	}

	public function get_users($user_id){
		$sql = "SELECT username, title FROM users WHERE users_id = ".$user_id." AND active='1' ";
		$results = $this->executeQuery($sql);
		try{
			if(isset($results)){
				$resp = array( "status" => "Success", "user_data" => $results[0]);
			}else{
				$resp = array( "status" => "Error", "user_data" => $results);
			}
		}catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage(), "user_data" => $results);
		}
		return $resp;
	}

	public function add_system_email($data){
		$email 		= $data['email'];
		$flags 		= $data['flags'];
		$host 		= $data['host'];
		$imap_host 	= $data['imap_host'];
		$imap_port 	= $data['imap_port'];
		$name 		= $data['name'];
		$port 		= $data['port'];
		$type 		= $data['type'];
		$name 		= $data['name'];
		$status 	= $data['status'];
		$password  	= $data['password'];
		$sqlInsert 	= "INSERT INTO 
				email_config 
				(
					email_config_id, 
					email, 
					password, 
					SMTPauth, 
					SMTPsec, 
					host, 
					port, 
					setFrom, 
					status,
					imap_status,
					imap_host,
					imap_port,
					imap_flags,
					deleted_at
				) 
				VALUES 
				(
					:email_config_id, 
					:email, 
					:password, 
					:SMTPauth, 
					:SMTPsec, 
					:host, 
					:port, 
					:setFrom, 
					:status, 
					:imap_status,
					:imap_host,
					:imap_port,
					:imap_flags,
					:deleted_at
				) ";
			$stmt = $this->db->prepare($sqlInsert);
			try {
				$stmt->execute(array(
					':email_config_id' => null, 
					':email' => $email, 
					':password' => $password, 
					':SMTPauth' => 1, 
					':SMTPsec' => $type, 
					':host' => $host, 
					':port' => $port, 
					':setFrom' => $name, 
					':status' => $status, 
					':imap_status' => 0,
					':imap_host' => $imap_host,
					':imap_port' => $imap_port,
					':imap_flags' => $flags,
					':deleted_at' => null
				));
				$resp = array( "status" => "Success", "message" => "Successfully Saved" );
				/* echo json_ecode($resp); */
			} catch(PDOException $e) {
				$resp = array( "status"=> "Error", "message" => "Duplicate email, email already saved!" );
			}
		return $resp;
	}

	public function update_system_email($data){
		$id 		= $data['id'];
		$email		= $data['email'];
		$flags		= $data['flags'];
		$host		= $data['host'];
		$imap_host	= $data['imap_host'];
		$imap_port	= $data['imap_port'];
		$name		= $data['name'];
		$password	= $data['password'];
		$port		= $data['port'];
		$status		= ($data['status'] != 'true' || $data['status'] != true ? 'inactive' : 'active');
		$type		= $data['type'];
		$editdelete	= $data['editdelete'];
		if($editdelete == 'edit'){
			/*check email if already in the list*/
			$select_email_sql = "SELECT * FROM email_config WHERE email LIKE '".$email."' AND deleted_at IS NULL ";
			$selected 		  = $this->executeQuery($select_email_sql);
			$sel_email        = $selected[0]['email'];
			$sel_email_stat   = $selected[0]['status'];
			// executes if the user set the status of the inactive email to active
			
			/*select all inactive*/
			$select_email_sql = "SELECT * FROM email_config WHERE status = 'inactive' AND deleted_at IS NULL ";
			$selected 		  = $this->executeQuery($select_email_sql);
			$sel_email        = $selected[0]['email'];
			
			if($status == 'inactive'){
				if($sel_email_stat == "active"){
					//set all email to inactive
					$sql   = "UPDATE email_config SET status = 'inactive' WHERE status = 'active' ";
					$stments = $this->db->prepare($sql);
					$stments->execute();
					//set another email to active
					$sql   = "UPDATE email_config SET status = 'active' WHERE email = '".$sel_email."' AND deleted_at IS NULL ";
					$stmts = $this->db->prepare($sql);
					$stmts->execute();
				}
			}else{
				//set all email to inactive
				$sql   = "UPDATE email_config SET status = 'inactive' WHERE status = 'active' ";
				$stmts = $this->db->prepare($sql);
				$stmts->execute();	
			}
			if($sel_email != null){
				// update only the other data except the email
				$sql = "UPDATE email_config SET  password  = '".$password."', SMTPsec  = '".$type."', host  = '".$host."', port  = '".$port."', setFrom  = '".$name."', status  = '".$status."', imap_host  = '".$imap_host."', imap_port  = '".$imap_port."', imap_flags = '".$flags."' 
				WHERE email = '".$email."' ";
				$statement = $this->db->prepare($sql);
				try{
					$stat = $statement->execute();
					if(isset($stat)){
						$resp = array( "status" => "Success", "message" => "Edit Successful" , "update_data" => $data);
					}else{
						$resp = array( "status" => "Error", "message" => "Edit Unsuccessful" , "update_data" => $data);
					}
				}catch(PDOException $e) {
					$resp = array( "status"=> "error", "message" => $e->getMessage(), "data" => $data);
				}
			}else{
				// update all the column
				$sql = "UPDATE email_config SET password  = '".$password."', SMTPsec  = '".$type."', host  = '".$host."', port  = '".$port."', setFrom  = '".$name."', status  = '".$status."', imap_host  = '".$imap_host."', imap_port  = '".$imap_port."', imap_flags = '".$imap_flags."', email = '".$email."' 
					WHERE email_config_id = '".$id."' ";
				$statement = $this->db->prepare($sql);
				try{
					$stat = $statement->execute();
					if(isset($stat)){
						$resp = array( "status" => "Success", "message" => "Edit Successful1" , "update_data" => $data);
					}else{
						$resp = array( "status" => "Error", "message" => "Edit Unsuccessful" , "update_data" => $data);
					}
				}catch(PDOException $e) {
					$resp = array( "status"=> "Error", "message" => $e->getMessage(), "update_data" => $data);
				}
			}				
		}else{
			$date  = new DateTime('now');
			$ndate = $date->format('Y-m-d h:m:s');
			$sql   = "UPDATE email_config SET deleted_at='".$ndate."' WHERE email_config_id='".$id."' ";
			$statement = $this->db->prepare($sql);
			try{
				$stat = $statement->execute();
				if(isset($stat)){
					$resp = array( "status" => "Success", "message" => "Deleted Successfully" , "update_data" => $data);
				}else{
					$resp = array( "status" => "Error", "message" => "Delete Unsuccessful" , "update_data" => $data);
				}
			}catch(PDOException $e) {
				$resp = array( "status"=> "error", "message" => $e->getMessage(), "data" => $data);
			}
		}
		return $resp;
	}

	/*BREAKFAST REPORT*/
	public function get_breakfast_reports(){
		try{
			$current_date   = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
			$tom_date  = new DateTime('tomorrow');
			$tomorrow_date = $tom_date->format('Y-m-d');
			// $dateNow = "2019-07-18";
			$sql = "
				SELECT
					res.reservation_id,
	                res.reservation_conn_id,
	                res.clients_id,
					res.pax,
					res.checkin, 
					res.checkout,
					apt.apartment_name,
					rt.name as room,
					(CASE 
						WHEN cli.name IS NULL THEN cli.surname
						WHEN cli.surname IS NULL THEN cli.name
						ELSE
							CONCAT(cli.name, CONCAT(' ',cli.surname))
					END) as name
				FROM reservation res
				LEFT JOIN clients cli ON cli.clients_id = res.clients_id
				LEFT JOIN periods ds ON res.date_start_id = ds.periods_id
				LEFT JOIN periods de ON res.date_end_id = de.periods_id
				LEFT JOIN apartments apt ON apt.apartment_id = res.appartments_id	
				LEFT JOIN room_types rt	ON apt.roomtype_id = rt.room_type_id
				WHERE res.status = 'active'
	            AND :dateNow BETWEEN ds.start_date AND de.end_date
	            AND res.checkin IS NOT NULL
	            AND res.checkout IS NULL
	            AND rt.name IS NOT NULL
	            ORDER BY apt.apartment_name ASC
			";
			$statement = $this->db->prepare($sql);
			$statement->execute(array(
				':dateNow' => $current_date
			));
			$statement->execute();
			$results = $statement->fetchAll( PDO::FETCH_ASSOC );
			// breakfast tomorrow
			$statements = $this->db->prepare($sql);
			$statements->execute(array(
				':dateNow' => $tomorrow_date 
			));
			$statements->execute();
			$results_tom = $statements->fetchAll( PDO::FETCH_ASSOC );

			$resp = array( 'bf_today' => $results, 'bf_tom' => $results_tom, "datenow" =>  $current_date, "datetom" =>  $tomorrow_date );
		}catch(PDOException $e){
			$resp = array( "status"=> "error", "message" => $e->getMessage());
		}
		return $resp;
	}
	/*END OF BREAKFAST REPORT*/

	public function calendar_last_date(){	
		$sql = "SELECT `start_date`
				FROM `periods`
				ORDER BY `periods_id` DESC LIMIT 1";
		$result = $this->executeQuery($sql);
		$last_date = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
		if(count($result) > 0){
			$last_date = $result[0]["start_date"];
		}
		return $last_date;
	}
	
	public function get_orders($table_id = 0){
		try{
			$sql_date = $this->executeQuery("SELECT CAST(NOW() + INTERVAL ". C_DIFF_ORE ." HOUR AS date) as date");
			$date = $sql_date[0]['date'];
			if($table_id == 0){
				$order_conn_sql = "SELECT oc.reference_num, 
									  (SELECT item_name FROM items WHERE item_id = ord.item_id) as item_name,
									  ord.*,
									  (SELECT lastname FROM users WHERE users_id = ord.table_id) as table_name
							FROM orders_conn oc 
							LEFT JOIN orders ord ON ord.order_conn_id = oc.order_conn_id
							WHERE oc.status='active' AND ord.status='active'
							AND ord.inserted_date = '$date'
							AND ord.served_in IS NULL
	                        ORDER BY ord.table_id ASC ";
			}else{
				$order_conn_sql = "SELECT oc.reference_num, 
									  (SELECT item_name FROM items WHERE item_id = ord.item_id) as item_name,
									  ord.*,
									  (SELECT lastname FROM users WHERE users_id = ord.table_id) as table_name
							FROM orders_conn oc 
							LEFT JOIN orders ord ON ord.order_conn_id = oc.order_conn_id AND ord.table_id = '$table_id'
							WHERE oc.status='active' AND ord.status='active'
							AND ord.inserted_date = '$date'
	                        ORDER BY ord.table_id ASC ";
			}
			
	        $result = $this->executeQuery($order_conn_sql);
	        $lasttableid = "";
	        $lastconnid = "";
	        $lastkey = "";
	        $categorized_order = [];
	        $total = 0;
	        foreach ($result as $key => $order) {
	        	$table_name = $order['table_name'];
	        	$reference_num = $order['reference_num'];
	        	if($order['table_id'] != $lasttableid){
	        		$lasttableid = $order['table_id'];
	        		$lastconnid = $order['order_conn_id'];
	        		// $total = 0;
	        		$order_by_conn = $order;
	        		$categorization[$lasttableid]['table_name'] = $table_name;
        			$categorization[$lasttableid][$lastconnid]['reference_num'] = $reference_num;
	        		$categorization[$lasttableid][$lastconnid][] = $order_by_conn;
	        		$total = $order['total_price'];
	        	}else{
        			$order_by_conn = $order;
	        		if($order['order_conn_id'] == $lastconnid){
	        			$categorization[$lasttableid]['table_name'] = $table_name;
	        			$categorization[$lasttableid][$lastconnid]['reference_num'] = $reference_num;
	        			$categorization[$lasttableid][$lastconnid][] = $order_by_conn;
        				$total += $order['total_price'];
	        		}else if($lastconnid != $order['order_conn_id']){
	        			$total = 0;
	        			$total = $order['total_price'];
	        			$lastconnid = $order['order_conn_id'];
	        			$categorization[$lasttableid]['table_name'] = $table_name;
	        			$categorization[$lasttableid][$lastconnid]['reference_num'] = $reference_num;
	        			$categorization[$lasttableid][$lastconnid][] = $order_by_conn;
	        		}
	        	}
				$categorization[$lasttableid][$lastconnid]['total_price'] = $total;
	        }
	        $resp = array( "categorization" => $categorization, "result" => $result, 'date' => $sql_date);
		}catch(PDOException $e){
			$resp = array( "status"=> "error", "message" => $e->getMessage());
		}
		return $resp;
	}

} /* end of class */