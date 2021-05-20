<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
//use \Firebase\JWT\JWT;
/* Login */
$app->post('/Login', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$myDatabase = $this->db;
	$sql = "SELECT * FROM users a WHERE username=:username AND PASSWORD = :password AND active = 1";
	$stmt = $myDatabase->prepare($sql);
	try {
		$stmt->execute(
			array(
				':username' => $data['username'],
				':password' => $data['password']
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		if(!empty($result)) {
			$valori = "abcdefghijkmnpqrstuvwxzABCDEFGHJKLMNPQRSTUVWXZ1234567890";
			$salt = substr($valori,rand(0,4),1);
			for ($num2 = 0 ; $num2 < 19 ; $num2++) $salt .= substr($valori,rand(0,60),1);

			$sql3 = "UPDATE users a SET a.session_key=:salt WHERE a.username = :username";
			$stmt3 = $myDatabase->prepare($sql3);
			$stmt3->execute(
				array(
					':username' => $data['username'],
					':salt' => $salt
				)
			);
			
			$sql1 = "SELECT c.role_id, c.role_name, c.start_url, c.allowed_url FROM users a 
					LEFT JOIN users_role b ON b.users_id = a.users_id
					LEFT JOIN roles c ON c.role_id = b.role_id
					WHERE a.username = :username";
			$stmt1 = $myDatabase->prepare($sql1);
			$stmt1->execute(
				array(
					':username' => $data['username']
				)
			);
			$result1 = $stmt1->fetchAll( PDO::FETCH_ASSOC );
			
			$sql = "SELECT a.module_id, b.modules_label, b.modules_url, b.has_children, b.icon FROM roles_modules_relationship a
					LEFT JOIN modules b ON a.module_id = b.modules_id
					WHERE a.role_id = :role_id";
			$stmt_4 = $myDatabase->prepare($sql);
			$stmt_4->execute(
				array(
					':role_id' => $result1[0]['role_id']
				)
			);
			$results = $stmt_4->fetchAll(PDO::FETCH_ASSOC);
			
			$sql = "SELECT sub_modules_label, sub_modules_url, modules_id FROM sub_modules";
			$stmt = $myDatabase->prepare($sql);
			$stmt->execute();
			$sub_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$websocket_url = $this->func->get_global_variables('websocket_url');

			// -- execute activity log HTL-646
			$activity_log_message = array(
				'action' => "notify",
				'message' => $result[0]['firstname']." ".$result[0]['lastname']." has logged in",
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => $result[0]['users_id'],
				'reservation_link' => '',
				'notification_type' => '9'
			);
			$this->func->log_activity($activity_log_message, true);
			// -- execute activity log
			$resp = array( "status" => "success", "data" => $result, "roles" => $result1, "session_key" => $salt, "modules" => $results, "sub_modules" => $sub_modules, "websocket_url" => $websocket_url);
		} else {
			$resp = array( "status" => "error", "message" => "Error Logging In" );
		}
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of Login */
$app->post('/notification', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	/* var_dump($data); */
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
			':action' => $data['data']['action'],
			':message' => $data['data']['message'],
			':host' => $data['data']['host'],
			':inserted_by' => $data['data']['inserted_by'],
			':reservation_link' => $data['data']['reservation_link'],
			':inserted_datetime' => C_DIFF_ORE,
			':notification_type' => $data['data']['notification_type']
		));
		$resp = array( "status"=> "success", "message" => "notification inserted" );
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of Notification */


/* Logout */
$app->post('/logout', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	try {
		// -- execute activity log HTL-646
		$activity_log_message = array(
				'action' => "notify",
				'message' => $data['fname']." ".$data['lname']." has logged out",
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' =>  $data['user_id'],
				'reservation_link' => '',
				'notification_type' => '9'
		);
		$this->func->log_activity($activity_log_message, true);
		// -- execute activity log
			$resp = array( "status" => "success");
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of Logout */

/* roles */
$app->post('/roles', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$sql_insert = "INSERT INTO roles (role_id, role_name, start_url, allowed_url, start_page_modules_id) VALUES (:role_id, :role_name, :start_url, :allowred_url, :start_page_modules_id)";
	$stmt = $this->db->prepare($sql_insert);
	try {
		$stmt->execute(
			array(
				':role_id' => NULL,
				':role_name' => $data['role']['role_name'],
				':start_url' => $data['start_url'],
				':allowred_url' => $data['allowed_url'],
				':start_page_modules_id' => $data['start_page']
			)
		);
		$id = $this->db->lastInsertId();
		$array_key = array_keys($data['role']['ids']);
		$sql = "INSERT INTO roles_modules_relationship(roles_modules_relationship_id, role_id, module_id) VALUES(:roles_modules_relationship_id, :role_id, :module_id)";
		$stmt = $this->db->prepare($sql);
		foreach($array_key as $key) {
			$stmt->execute(
				array(
					':roles_modules_relationship_id' => NULL,
					':role_id' => $id,
					':module_id' => $key
				)
			);
		}

		$socket_json_message = array(
			'action' => '',
			'message' => "New role has been created, " . $data['role']['role_name'] . ".",
			'host' => $_SERVER['SERVER_NAME'],
			'inserted_by' => $data['inserted_by'],
			'reservation_link' => '#/roles',
			'notification_type' => '9'
		);
		$this->func->log_activity($socket_json_message, false);

		$resp = array( "status"=> "success", "message" => "Success Insert" );
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of role */

/* users */
$app->post('/users', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	//var_dump($data);
	$sql_select_users = "SELECT username FROM users";
	$stmt = $this->db->prepare($sql_select_users);
	$stmt->execute();
	$users = $stmt->fetchAll( PDO::FETCH_ASSOC );
	$match = false;
	$host = $_SERVER['SERVER_NAME'];
	$valori = "abcdefghijkmnpqrstuvwxzABCDEFGHJKLMNPQRSTUVWXZ1234567890";
	$salt = substr($valori,rand(0,4),1);
	for ($num2 = 0 ; $num2 < 19 ; $num2++) $salt .= substr($valori,rand(0,60),1);
	foreach($users as $user) {
		if(strtolower($data['users']['username']) == strtolower($user['username'])) {
			$match = true;
		}
	}
	if($match) {
		$resp = array( "status"=> "success", "exist" => 1,"message" => "Username has been used try another name." );
		return $response->withJson( $resp );
	} else {
		$sql_insert_user = "INSERT INTO users (
								users_id, 
								username, 
								password, 
								salt, 
								inserted_date, 
								inserted_host, 
								firstname, 
								lastname, 
								title, 
								expiration, 
								prof_pic,
								active
							) 
							VALUES (
								:users_id,
								:username,
								MD5(:password),
								:salt,
								NOW() + INTERVAL :inserted_date HOUR,
								:inserted_host,
								:firstname,
								:lastname,
								:title,
								:expiration,
								:prof_pic,
								:active
							)";
		$stmt = $this->db->prepare($sql_insert_user);
		try {
			$stmt->execute(
				array(
					':users_id' => NULL,
					':username' => $data['users']['username'],
					':password' => $data['users']['password'] . $salt,
					':salt' => $salt,
					':inserted_date' => C_DIFF_ORE,
					':inserted_host' => $host,
					':firstname' => $data['users']['firstname'],
					':lastname' => $data['users']['lastname'],
					':title' => $data['users']['title'],
					':expiration' => 24,
					':prof_pic' => 'images/user/nextcart.png',
					':active' => 1
				)
			);
			$id = $this->db->lastInsertId();
			
			$sql_insert_user_role = "INSERT INTO users_role (users_role_id, users_id, role_id) VALUES (:users_role_id, :users_id, :role_id)";
			$stmt = $this->db->prepare($sql_insert_user_role);
			$stmt->execute(
				array(
					':users_role_id' => NULL,
					':users_id' => $id,
					':role_id' => $data['users']['role']
				)
			);

			$socket_json_message = array(
				'action' => 'notify',
				'message' => "New user has been created. " . $data['users']['firstname'] . " " . $data['users']['lastname'] . " - " . $data['users']['title'],
				'host' => $_SERVER['SERVER_NAME'],
				'inserted_by' => $data['inserted_by'],
				'reservation_link' => '#/users',
				'notification_type' => '9'
			);

			$this->func->log_activity($socket_json_message, true);
		} catch(PDOException $e) {
			$resp = array( "status"=> "error", "message" => $e->getMessage() );
			return $response->withJson( $resp )->withStatus(500);
		}
		$resp = array( "status"=> "success", "exist" => 0,"message" => "Username Success." );
		return $response->withJson( $resp );
	}
});
/* end of users */