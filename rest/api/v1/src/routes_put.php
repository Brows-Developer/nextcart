<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

//use \Firebase\JWT\JWT;

$app->put('/users', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$sql_update = "UPDATE
					users
					SET
						firstname = :firstname,
						lastname = :lastname,
						title = :title,
						username = :username
					WHERE users_id = :users_id";
	$stmt = $this->db->prepare($sql_update);
	
	$sql_select_user_role = "SELECT * FROM users_role WHERE users_id = :users_id";
	$stmt_select_user_role = $this->db->prepare($sql_select_user_role);
	
	$sql_update_user_role = "UPDATE users_role
							SET
								role_id = :role_id
							WHERE users_id = :users_id";
	$stmt_update_user_role = $this->db->prepare($sql_update_user_role);
	
	try {
		$stmt->execute(
			array(
				':firstname' => $data['users']['firstname'],
				':lastname' => $data['users']['lastname'],
				':title' => $data['users']['title'],
				':username' =>  $data['users']['username'],
				':users_id' => $data['users']['users_id']
			)
		);
		$stmt_select_user_role->execute(
			array(
				':users_id' => $data['users']['users_id']
			)
		);
		$exist = $stmt_select_user_role->fetchAll( PDO::FETCH_ASSOC );
		if(count($exist) > 0) {
			$stmt_update_user_role->execute(
				array(
					':role_id' => $data['users']['role'],
					':users_id' => $data['users']['users_id']
				)
			);
		} else {
			$sql = "INSERT INTO users_role(users_role_id, users_id, role_id) VALUES(:users_role_id, :users_id, :role_id)";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(
				array(
					':users_role_id' => NULL,
					':users_id' => $data['users']['users_id'],
					':role_id' => $data['users']['role']
				)
			);
		}

		$concat_changes = "";
		foreach ( $data['users'] as $key => $value ) {
			if($data['old_users'][$key] != $value ) {
				$concat_changes .= $key . ' "' . $data['old_users'][$key] . '" to "' . $value . '"; ' ;
			}
		}

		$socket_json_message = array(
			'action' => '',
			'message' => "User detail has been modified: " . $concat_changes,
			'host' => $_SERVER['SERVER_NAME'],
			'inserted_by' => $data['inserted_by'],
			'reservation_link' => '#/users',
			'notification_type' => '9'
		);

		$this->func->log_activity($socket_json_message, false);

		$resp = array( "status"=> "success", "message" => "Success Update", "exist" => $exist);
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of users */
/* role */
$app->put('/roles', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$sql_update = "UPDATE
					roles 
					SET
						role_name = :role_name,
						start_url = :start_url,
						allowed_url = :allowred_url,
						start_page_modules_id = :start_page_modules_id
					WHERE 
						role_id = :role_id";
	$stmt = $this->db->prepare($sql_update);
	try {
		$stmt->execute(
			array(
				':role_id' => $data['role']['role_id'],
				':role_name' => $data['role']['role_name'],
				':start_url' => $data['start_url'],
				':allowred_url' => $data['allowed_url'],
				':start_page_modules_id' => $data['start_page']
			)
		);
		$id = $data['role']['role_id'];
		$sql = "DELETE FROM roles_modules_relationship WHERE role_id = :role_id";
		$stmt = $this->db->prepare($sql);
		$stmt->execute(
			array(
				':role_id' => $id
			)
		);
		/* echo $data['role']['ids'][5]; */
		$array_key = array_keys($data['role']['ids']);
		$sql = "INSERT INTO roles_modules_relationship(roles_modules_relationship_id, role_id, module_id) VALUES(:roles_modules_relationship_id, :role_id, :module_id)";
		$stmt = $this->db->prepare($sql);
		foreach($array_key as $key) {
			if($data['role']['ids'][$key] == 'true') {
				$stmt->execute(
					array(
						':roles_modules_relationship_id' => NULL,
						':role_id' => $id,
						':module_id' => $key
					)
				);
			}
		}

		/* for log */
		$concat_changes = "";
		if($data['role']['role_name'] != $data['old_role']['role_name']){
			$concat_changes .= " Role Name: \"" . $data['old_role']['role_name'] . "\" to \"" .  $data['role']['role_name']  ."\";"; 
		}
		if($data['start_url'] != $data['old_start_url']){
			$concat_changes .= 'Start url: "' .  $data['old_start_url'] . '" to "' . $data['start_url'] . '";';
		} 
		$merged_array_key = array_unique( array_merge( $array_key, array_keys ($data['old_role']['ids'] ) ) );

		$sql_module = "SELECT modules_label FROM `modules` WHERE modules_id = :module_id";
		$stmt_module = $this->db->prepare($sql_module);
		foreach($merged_array_key as $key) {
			$data['role']['ids'][$key] = isset($data['role']['ids'][$key]) ? $data['role']['ids'][$key] : false;
			$data['old_role']['ids'][$key] = isset($data['old_role']['ids'][$key]) ? $data['old_role']['ids'][$key] : false;
			
			if( $data['role']['ids'][$key] != $data['old_role']['ids'][$key] ) {
				$stmt_module->execute(
					array( ':module_id' => $key  )
				);
				$module_label = $stmt_module->fetchAll( PDO::FETCH_ASSOC );
				$concat_changes .= $module_label[0]['modules_label'] . ' has been ' . ($data['role']['ids'][$key] == 'true' ? 'enabled; ' : 'disabled; ');
			}
		}

		$socket_json_message = array(
			'action' => '',
			'message' => $data['old_role']['role_name'] . " Role has been modified. " . $concat_changes,
			'host' => $_SERVER['SERVER_NAME'],
			'inserted_by' => $data['inserted_by'],
			'reservation_link' => '#/roles',
			'notification_type' => '9'
		);
		$this->func->log_activity($socket_json_message, false);
		/* end for log */

		$resp = array( "status"=> "success", "message" => "Success Update" );
		return $response->withJson( $resp );
	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});
/* end of role */

$app->put('/settings', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$sql_update = " UPDATE `global_variables` 
					SET `value` = :value
					WHERE `global_variables`.`key` = :key AND `global_variables`.`id` = :id";
	$stmt = $this->db->prepare($sql_update);
	try {
		$stmt->execute(
			array(
				':key' => $data['setting']['key'],
				':value' => $data['setting']['value'],
				':id' => $data['setting']['id']
			)
		);
		$resp = array( "status"=> "success", "message" => "Success Update" );
		return $response->withJson( $resp );

	} catch(PDOException $e) {
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});