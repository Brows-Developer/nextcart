<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/GetSalt/{username}', function (Request $request, Response $response, $params) {
	$username = $params['username'];
	$myDatabase = $this->db;
	$sql = "SELECT salt FROM users WHERE username=:username AND active = 1";
	$stmt = $myDatabase->prepare($sql);
	try {
		$stmt->execute(
			array(
				':username' => $username
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "salt" => $result[0]['salt'], "message" => $result[0]['salt'] == null ? "Error Logging In!" : "" );
		return $response->withJson( $resp );
	}catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => "Error Logging In!" );
		return $response->withJson( $resp )->withStatus(500);
	}
});



$app->get('/get_websocket_url', function(Request $request, Response $response, $args) {
	$url = $this->func->get_global_variables('websocket_url');
	$resp = array( 'status' => 'success', 'data' => $url);
	return $response->withJson( $resp );
});

function retThis($a){
	return "return this string = " . $a ;
}
/* end test methods */


function get_global_variables($that, $key = 'time_difference') {
	$myDatabase = $that->db; //variable to access your database
	$sql = "SELECT a.value FROM global_variables a WHERE a.key=:key";
	$stmt = $myDatabase->prepare($sql);
	$stmt->execute(array(
		':key' => $key
	));
	$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
	$value = $result[0]['value'];
	return $value;
}

$app->get('/get_global_variables[/{key}]', function (Request $request, Response $response, $args) {

	$key = '';
	if(isset($args['key'])) {
		$key = $args['key'];
		return get_global_variables($this, $key);
	}
	else {
		return get_global_variables($this);
	}
	

});

$app->get('/usersfor/{role_id}', function (Request $request, Response $response, $args) {
	try {
		$sql = "SELECT a.users_id, a.username, c.role_name FROM `users` a
				LEFT JOIN `users_role` b ON a.users_id = b.users_id
				LEFT JOIN `roles` c ON c.role_id = b.role_id
				WHERE b.role_id = :role_id";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array( ":role_id" => $args['role_id'])
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );

	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

/* USERS */
$app->get('/users', function(Request $request, Response $response, $args) use ($app) {
	$sql = "SELECT a.users_id, a.username, a.firstname, a.lastname, b.role_id, c.role_name, a.title FROM users a
			LEFT JOIN users_role b ON b.users_id = a.users_id
			LEFT JOIN roles c ON c.role_id = b.role_id
			WHERE a.active = 1
			ORDER BY a.users_id ASC";
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute();
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
$app->get('/roles', function(Request $request, Response $response, $args) use ($app) {
	$sql = "SELECT * FROM roles a
			LEFT JOIN modules b ON a.start_page_modules_id = b.modules_id";
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute();
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
$app->get('/modules[/{type}]', function(Request $request, Response $response, $args) use ($app) {
	$where = '';
	if(isset($args['type'])) {
		$where = "WHERE modules_url != ''";
	}
	$sql = "SELECT * FROM modules " . $where;
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute();
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
$app->get('/role_modules_relationship/{role_id}', function(Request $request, Response $response, $args) use ($app) {
	$sql = "SELECT * FROM roles_modules_relationship WHERE role_id = :role_id";
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute(
			array(
				':role_id' => $args['role_id']
			)
		);
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* END OF USERS */

/* Notifications */
$app->get('/notification', function(Request $request, Response $response, $args) {
	$sql = "SELECT a.notification_id, a.message, a.reservation_link FROM notifications a
			WHERE 
				CAST(inserted_datetime AS DATE) = CAST(NOW() + INTERVAL :time_diff HOUR AS DATE) AND 
				( a.action = 'reservation' or a.action = 'notify' )
			ORDER BY a.inserted_datetime DESC";
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute(array(
			':time_diff' => C_DIFF_ORE
		));
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/notification_type', function(Request $request, Response $response, $args) {

	try {

		$sql = "SELECT * FROM `notification_types` ORDER BY notification_type_name";
		$stmt = $this->db->prepare($sql);
		$stmt->execute();			

		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/notification/{start_date}/{end_date}/{type}/{notif_or_log}', function(Request $request, Response $response, $args) {

	try {
		$sql_only_notification = "";
		if($args['notif_or_log'] == 'notification'){
			$sql_only_notification = "AND ( a.action = 'notify' OR a.action = 'reservation' )";
		}

		if($args['type'] == 'all') {
			$sql = "SELECT a.notification_id, a.message, a.reservation_link, a.inserted_datetime, b.notification_type_name,
					CONCAT( IFNULL(c.lastname, '') , ', '  , IFNULL(c.firstname, '')) as user_name
					FROM `notifications` a
					LEFT JOIN `notification_types` b ON a.notification_type = b.`notification_type_id`
					LEFT JOIN `users` c ON a.inserted_by = c.users_id
					WHERE CAST(a.inserted_datetime AS DATE) >= :start_date AND 
						CAST(a.inserted_datetime AS DATE) <= :end_date " . $sql_only_notification .
					" ORDER BY a.inserted_datetime DESC";

			$stmt = $this->db->prepare($sql);
			$stmt->execute(array(
				':start_date' => $args['start_date'],
				':end_date' => $args['end_date']
			));			
		} else {
			$sql = "SELECT a.notification_id, a.message, a.reservation_link, a.inserted_datetime, b.notification_type_name,
					CONCAT( IFNULL(c.lastname, '') , ', '  , IFNULL(c.firstname, '')) as user_name
					FROM `notifications` a
					LEFT JOIN `notification_types` b ON a.notification_type = b.`notification_type_id`
					LEFT JOIN `users` c ON a.inserted_by = c.users_id
					WHERE CAST(a.inserted_datetime AS DATE) >= :start_date AND 
					CAST(a.inserted_datetime AS DATE) <= :end_date
					AND notification_type_id = :type " . $sql_only_notification .
					" ORDER BY a.inserted_datetime DESC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(array(
				':start_date' => $args['start_date'],
				':end_date' => $args['end_date'],
				':type' => $args['type']
			));		
		}

		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() ,"type" => $args['type'] );
		return $response->withJson( $resp )->withStatus(500);
	}
});
$app->get('/global_variables/{key}', function(Request $request, Response $response, $args) {
	$key = $args['key'];
	$value = $this->func->get_global_variables($key);
	$resp = array( "status" => "success", "data" => $value);
	return $response->withJson( $resp );
});

$app->get('/get_multiple_global_variables', function(Request $request, Response $response, $args) {
	//$key = $args['key'];
	//$value = $this->func->get_global_variables($key);

	$data = $request->getQueryParams();
	$arr_data = array_map('trim', explode(",", $data["keys"]) );
	$arr = implode("','",$arr_data);

	$var_sql = "SELECT * FROM `global_variables` where `key` in ( '".$arr."' )" ;
	$var_stmt = $this->db->prepare($var_sql);
	$var_stmt->execute( );
	$result = $var_stmt->fetchAll( PDO::FETCH_ASSOC );

	$resp = array( "status" => "success", "data" => $result );
	return $response->withJson( $resp );
});

$app->get('/settings', function(Request $request, Response $response, $args) use ($app) {
	$sql = "SELECT * FROM global_variables";
	$stmt = $this->db->prepare($sql);
	try {
		$stmt->execute();
		$result = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$resp = array( "status" => "success", "data" => $result);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});