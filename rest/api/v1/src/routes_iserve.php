<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->post('/add_category', function(Request $request, Response $response){
	$data = $request->getParsedBody();

	$name = $data['name'];
	$status = $data['status'] == "true" || $data['status'] == true ? 'active' : 'inactive';
 	try{
		$sql = "INSERT INTO item_category (item_category_id, name, status) VALUES (null, '".$name."', '".$status."')";
		$st = $this->db->prepare($sql);
		$st->execute();
		$resp = array( "status" => "success", "response" => "Category added successfully!");
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/get_category', function(Request $request, Response $response){
	try{
		$sql = "SELECT * FROM item_category WHERE status = 'active' ";
		$result = $this->func->executeQuery($sql);
		$resp = array( "status" => "success", "category" => $result);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->post('/add_item/{user_id}', function(Request $request, Response $response, $params){
	$user_id = $params['user_id'];
	$data = $request->getParsedBody();
	$name = $data['name'];
	$category = $data['category'];
	$quantity = $data['quantity'];
	$price = $data['price'];
 	$description = $data['description'];
	// $photo = base64_encode(file_get_contents($data['image']));
	$photo = $data['image'];
	$this->func->setMsg("item");

 	try{
 		$sql = "INSERT INTO items (item_id, item_category_id, item_name, quantity, item_out, price, description, image_src)
 				VALUES (null, '$category' , '$name' , '$quantity' , 0 , '$price' , '$description' , '$photo' )";
 		$st = $this->db->prepare($sql);
		$st->execute();
		$resp = array( "status" => "success", "response" => "Item added successfully!");
		/* intended for notification */
		$socket_json_message = array(
			'action' => 'item',
			'message' => $name." has been added to the inventory",
			'host' => $_SERVER['SERVER_NAME'],
			'inserted_by' => $user_id,
			'reservation_link' => '',
			'notification_type' => '1'
		);
		$this->func->log_activity($socket_json_message, true);

		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/get_item', function(Request $request, Response $response, $params){
	try{
		$sql = "SELECT ic.name as category_name, it.* FROM item_category ic LEFT JOIN items it ON it.item_category_id = ic.item_category_id WHERE ic.status='active' AND it.item_name IS NOT NULL GROUP BY it.item_id";
		$result = $this->func->executeQuery($sql);
		$resp = array( "status" => "success", "items" => $result, 'sql' => $sql, "data" => $data);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/generate_server', function(Request $request, Response $response){
	try{
		$server = $_SERVER['REMOTE_HOST'];
		$resp = array( "status" => "success", "server" => $server);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->post('/saveOrderFunction', function(Request $request, Response $response){
	try{
		$data = $request->getParsedBody();
		$inserted_by = $data['table_id'];
		$orders = $data['placed_orders'];
		$table_id = $inserted_by; 
		$order_id_arr = array();
		
		// foreach ($orders as $order) {
		// 	$list_arr[] = $order['item']['item_id'];
		// }

		$order_conn_sql = "INSERT INTO orders_conn(table_id, status, date_inserted, inserted_by) VALUES (:table_id, :status, NOW() + INTERVAL :date_inserted HOUR, :inserted_by)";

		$order_sql = "INSERT INTO orders(order_conn_id, table_id, item_id, quantity, price, total_price, status, inserted_date, inserted_by) 
					VALUES (:order_conn_id, :table_id, :item_id, :quantity, :price, :total_price, :status, NOW() + INTERVAL :inserted_date HOUR, :inserted_by)";
		$order_conn_update_sql = "UPDATE orders_conn SET order_ids=:order_ids, reference_num=:reference_num WHERE order_conn_id=:order_conn_id";
		$order_conn_insert = array(
					':table_id' => $table_id,
					':status' => 'active',
					':date_inserted' => C_DIFF_ORE,
					':inserted_by' => $inserted_by
				);
		

		$last_order_conn_id = $this->func->execute_insert_getId($order_conn_sql,$order_conn_insert);

		foreach ($orders as $key => $order) {
			$order_insert = array(
				':order_conn_id' => $last_order_conn_id,
				':table_id' => $table_id,
				':item_id' => $order['item']['item_id'],
				':quantity' => $order['quantity'],
				':price' => $order['item']['price'],
				':total_price' => $order['quantity'] * $order['item']['price'],
				':status' => 'active',
				':inserted_date' => C_DIFF_ORE,
				':inserted_by' => $table_id
			);
			$last_order_id = $this->func->execute_insert_getId($order_sql, $order_insert);
			$order_id_arr[] = $last_order_id;
		}

		$list_id = implode(',', $order_id_arr);

		$order_conn_update = array(
			':order_ids' => $list_id,
			':reference_num' => "O-".$last_order_conn_id,
			':order_conn_id' => $last_order_conn_id
		); #----- Reservation Connection
			
		$this->func->execute_insert_getId($order_conn_update_sql,$order_conn_update);

		$resp = array( "status" => "success", "last_conn_id" => "O-".$last_order_conn_id);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/get_orders', function(Request $request, Response $response){
	try{
		$data = $this->func->get_orders();
		$datetoday = new DateTime('now');
		// $date = date("Y-m-d",(time() + (C_DIFF_ORE * 3600)));
		$date = $datetoday->format('Y-m-d');
		$resp = array( "status" => "success", "data" => $data, "datetoday" => $date);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/get_orders/{table_id}', function(Request $request, Response $response, $params){
	try{
		$table_id = $params['table_id'];
		$data = $this->func->get_orders($table_id);
		$resp = array( "status" => "success", "data" => $data['categorization'],"result" => $data['result'], "params" => $params['table_id'], "date" => $data['date']);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->put('/update_order_prepared', function(Request $request, Response $response){
	try{
		$data = $request->getParsedBody();
		$prepared = $data['prepared'];
		$id = $data['order_id'];
		$sql = "UPDATE orders SET prepared = '$prepared' WHERE order_id = '$id' ";
		$this->func->execute_update($sql);
		$resp = array( "status" => "success", "message" => "Updated Successfully", 'data' => $data);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->put('/update_order_served_in', function(Request $request, Response $response, $params){
	$data = $request->getParsedBody();
	$reference_num = $data['reference_num'];
	$sql = "SELECT order_conn_id FROM orders_conn WHERE reference_num = '$reference_num' ";
	$result = $this->func->executeQuery($sql);
	$conn_id = $result[0]['order_conn_id'];

	$sql = "SELECT COUNT(prepared) as count FROM orders WHERE prepared = 'no' AND order_conn_id = '$conn_id' ";
	$res = $this->func->executeQuery($sql);
	$result = $res[0]['count'];

	if($result == null || $result == 0){
		$sql = "UPDATE orders SET served_in = NOW() + INTERVAL ".C_DIFF_ORE." HOUR WHERE order_conn_id = '$conn_id' ";
		$this->func->execute_update($sql);
		$resp = array( "status" => "success", "message" => "Served In Successfully", "conn_id" => $conn_id);
	}else{
		$resp = array( "status" => "error", "message" => "Served In Unsuccessful other order are not prepared already", "res" => $result);
	}	
	return $response->withJson( $resp );
});

$app->get('/get_order_served_in', function(Request $request, Response $response, $params){
	try{
		$reference_num = $data['reference_num'];
		$sql = "SELECT order_conn_id FROM orders_conn WHERE reference_num = '$reference_num' ";
		$result = $this->func->executeQuery($sql);
		$conn_id = $result[0]['order_conn_id'];

		$sql = "SELECT COUNT(served_in) as count FROM orders WHERE served_in IS NOT NULL AND order_conn_id = '$conn_id' ";
		$res = $this->func->executeQuery($sql);
		$result = $res[0]['count'];

		if($result == null || $result == 0){
			$resp = array( "status" => "success", "served" => "no");
		}else{
			$resp = array( "status" => "success", "served" => "yes");
		}
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});

$app->get('/dashboard_content', function(Request $request, Response $response, $params){
	try{
		$sql = "SELECT * FROM orders WHERE inserted_date = CAST(NOW() + INTERVAL ".C_DIFF_ORE." HOUR as DATE) AND status = 'active' AND served_in IS NOT NULL AND served_out IS NULL GROUP BY order_conn_id";
		$served = $this->func->executeQuery($sql);

		$sql = "SELECT * FROM orders_conn WHERE CAST(date_inserted as DATE) = CAST(NOW() + INTERVAL ".C_DIFF_ORE." HOUR as DATE) AND status = 'active'";
		$orders_today = $this->func->executeQuery($sql);

		$sql = "SELECT
					(SELECT GROUP_CONCAT( order_id SEPARATOR ',') FROM orders WHERE order_conn_id = oc.order_conn_id AND served_in IS NOT NULL ) as ords,
					(SELECT SUM((price*quantity)) FROM orders WHERE order_conn_id = oc.order_conn_id AND served_in IS NOT NULL ) as total_cost, oc.* 
				FROM orders_conn oc
				WHERE CAST(oc.date_inserted as DATE) = CAST(NOW() + INTERVAL ".C_DIFF_ORE." HOUR as DATE) AND oc.status = 'active' ORDER BY oc.date_inserted DESC
		";
		$orde = $this->func->executeQuery($sql);
		$resp = array( "status" => "success", "served" => $served, "orders_today" => $orders_today, "orde" => $orde);
		return $response->withJson( $resp );
	}catch(PDOException $e){
		$resp = array( "status"=> "error", "message" => $e->getMessage() );
		return $response->withJson( $resp )->withStatus(500);
	}
});