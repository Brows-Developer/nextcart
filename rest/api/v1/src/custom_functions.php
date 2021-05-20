<?php 
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
	public function executeQuery($sql){ # Execute Mysql Query
		$myDatabase= $this->db;
	    $stmt = $myDatabase->prepare($sql);
	    $stmt->execute();
	    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		return $result;
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

	
	/* -- START : Activity Log -- */
	public function log_activity($log_data, $sendNotif){ # $sendNotif (bolean, true: notify | false: don't notify)
		$this->insertNotification($log_data);
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
		}
		$resp = array( "status" => "success", "message" => $resps, 'imap' => $im );
		return $resp;
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
} /* end of class */