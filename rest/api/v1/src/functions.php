<?php

use \Firebase\JWT\JWT;

function isAuthenticated($that, $request){

   $jwt = $request->getHeaders();

   $decoded = "";
    
    try {
        if( isset($jwt['HTTP_AUTHORIZATION'][0]) ){
            if( strpos($jwt['HTTP_AUTHORIZATION'][0], "Bearer ") === 0 ) {
                $token = str_replace("Bearer ","",$jwt['HTTP_AUTHORIZATION'][0]);
                $decoded = JWT::decode( $token , $that->get('settings')['key'], array('HS256'));
            }
            else {  throw new UnexpectedValueException("Unauthorized"); }
        }
        else {  throw new UnexpectedValueException("Unauthorized"); }
        
    } catch (UnexpectedValueException $e) {
        
        echo json_encode([ "status" => "fail", "message" => $e->getMessage() ]);
        return false;
    }

    if (isset($decoded)) {

        $sql = "SELECT * FROM tokens WHERE user_id = :user_id";
        try {
            $db = $that->db;
            $stmt = $db->prepare($sql);
            $stmt->bindParam("user_id", $decoded->context->user->user_id);
            $stmt->execute();
            $user_from_db = $stmt->fetchObject();
            $db = null;

            if (isset($user_from_db->user_id)) {
                return true;
            }
        } catch (PDOException $e) {
            echo json_encode([ "status" => "fail", "message" => $e->getMessage() ]); 
            return false;
        }
    }

}





