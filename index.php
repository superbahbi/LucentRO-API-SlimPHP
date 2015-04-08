<?php
require 'vendor/autoload.php';
require 'db.php';
require 'function.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');

$app = new \Slim\Slim();
$app->response->headers['Content-Type'] = 'application/json';
$app->get('/','main');
$app->post('/login/', 'postLogin');
$app->post('/register/', 'postRegister');
$app->delete('/session/', 'deleteSession');
$app->get('/:module/', 'getData');
$app->get('/:module/:query', 'getDataSearch');

$app->run();
function main() {
    echo '{"success":{"message" : "Lucent Data Api!!"}}';
}

function postLogin() {
    global $app;
    $key = new KeyGenerator();
    $post = json_decode($app->request->getBody());
    $encryted_pass = md5($post->user_pass);
    $sql = "SELECT * from ragnarok.login WHERE userid='{$post->userid}' AND user_pass='{$encryted_pass}'";
    try {
        $db = getDB();
        $stmt = $db->query($sql);
        $login = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        if($login){
            $sql = "SELECT * from ragnarok.app_access_token WHERE account_id='{$login[0]->account_id}'";
            $db = getDB();
            $stmt = $db->query($sql);
            $access = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;
            if(!$access){
                $token = $key->generate_password(35);
                $sql = "INSERT INTO ragnarok.app_access_token (`account_id`,`access_token`) VALUES ('{$login[0]->account_id}', '{$token}')";
                $db = getDB();
                $stmt = $db->query($sql);
                $db = null;
            } else {
                $token = $access[0]->access_token;
            }

            echo '{"success":{"message" : "Login data found!","access_token":"'.$token.'"}}';
        }else{
            echo '{"error":{"message" : "Login data not found!"}}';
        }
    } catch(PDOException $e) {
        echo json_encode($e->getMessage());
    }
}
function postRegister() {
    global $app;
    $post = json_decode($app->request->getBody());
    $username = $post->userid;
    $password = $post->user_pass;
    $email = $post->email;
    $gender = $post->sex;
    try {
        $sql = "SELECT userid FROM ragnarok.login WHERE userid='{$username}' LIMIT 1";
        $db = getDB();
        $stmt = $db->query($sql);
        $username2 = $stmt->fetchAll(PDO::FETCH_OBJ);

        if (preg_match('/[^a-z_\-0-9]/i', $username)) {
            throw new Exception('Invalid character(s) used in username');
        } else if($post->user_pass != $post->user_pass2){
            throw new Exception("Password doesn't match!");
        } else if(strlen($username) < 4){
            throw new Exception("Username is too short!");
        } else if(strlen($username) > 23){
            throw new Exception("Username is too long!");
        } elseif (stripos($password, $username) !== false) {
            throw new Exception("Password contains username");
        } elseif (!ctype_graph($password)) {
            throw new Exception("Invalid character(s) used in password");
        } elseif (strlen($password) < 8) {
            throw new Exception('Password is too short');
        } elseif (strlen($password) > 31) {
            throw new Exception('Password is too long');
        } elseif (!preg_match('/^(.+?)@(.+?)$/', $email)) {
            throw new Exception("Invalid e-mail address");
        } elseif (!in_array(strtoupper($gender), array('M', 'F'))) {
            throw new Exception("Invalid gender");
        } elseif (!$post->birthdate) {
            throw new Exception("Invalid birthdate");
        }
                // Check user if exist
        $sql = "SELECT userid FROM ragnarok.login WHERE userid='{$username}' LIMIT 1";
        $db = getDB();
        $stmt = $db->query($sql);
        $res = $stmt->fetchAll(PDO::FETCH_OBJ);
        if( $res ){
            throw new Exception("Username is already taken!");
        }
        // Check email if exist
        $sql = "SELECT email FROM ragnarok.login WHERE email = '{$email}' LIMIT 1";
        $db = getDB();
        $stmt = $db->query($sql);
        $res = $stmt->fetchAll(PDO::FETCH_OBJ);
        if ($res) {
            throw new Exception("E-mail address is already in use");
        }
        $encryted_pass = md5($post->user_pass);
        $sql = "INSERT INTO ragnarok.login (`userid`,`user_pass`,`sex`,`email`,`birthdate`) VALUES ('{$post->userid}', '{$encryted_pass}', '{$post->sex}', '{$post->email}', '{$post->birthdate}')";
        $db = getDB();
        $stmt = $db->query($sql);
        $db = null;
        echo '{"success":{"message" : "Account created!"}}';
    } catch(PDOException $e) {
        echo '{"error":{"message" : "Can not create account! :("}}';
    } catch (Exception $e) {
        echo '{"error":{"message" : "'.$e->getMessage().'"}}';
    }
}
// GET http://api.lucentro.com/:module
function getData($module) {
  global $app;
  $access_token = $app->request()->get("access_token");
  $check = new Validate();
  $check->validateAccessToken($app, $access_token);
  if($module == "char"){
      $data = $check->getData("SELECT * FROM ragnarok.char", "All character");
  } else if($module == "account"){
      $data = $check->getData("SELECT account_id, userid, sex, email, group_id, state, unban_time, expiration_time, logincount, lastlogin, last_ip, birthdate FROM ragnarok.login", "All account");
  }
  echo '{"result": ' . json_encode($data) . '}';
}
// GET http://api.lucentro.com/:module/:id/:mode mode 1 = char mode 2 = name
function getDataSearch($module, $query) {
    global $app;
    $access_token = $app->request()->get("access_token");
    $check = new Validate();
    $check->validateAccessToken($app, $access_token);

    $req = $app->request();
    $mode = $req->get('mode');
    if($mode == "account_id"){
        $sql  = "SELECT * FROM ragnarok.char ";
        $sql .= "WHERE account_id=".$query;
        $data = $check->getData($sql, "Character on account id");
    } else if($module == "char"){
        $sql  = "SELECT * FROM ragnarok.char ";
        $sql .= "WHERE char_id=".$query." LIMIT 1";
        $data = $check->getData($sql, "Character by character ID");
    } else if($module == "account"){
        $sql = "SELECT account_id, userid, sex, email, group_id, state, unban_time, expiration_time, logincount, lastlogin, last_ip, birthdate FROM ragnarok.login ";
        $sql .= "WHERE account_id=".$query;
        $data = $check->getData($sql, "Account by account ID");
    } else if($module == "session"){
        $sql = "SELECT account_id, userid, group_id, state, unban_time, last_ip, email, birthdate FROM ragnarok.login ";
        $sql .= "WHERE userid='".$query."'";
        $data = $check->getData($sql, "Account session");
    }
    echo '{"result": ' . json_encode($data) . '}';
}
function deleteSession() {
    global $app;
    $post = json_decode($app->request->getBody());
    $id = $post->account_id;
    $check = new Validate();

    $res = $check->getData("SELECT * FROM ragnarok.app_access_token WHERE account_id='{$id}' LIMIT 1");
    if (!$res) {
      throw new Exception("Invalid account ID");
    }

	try {
		$sql = "DELETE FROM ragnarok.app_access_token WHERE account_id='{$id}'";
		$db = getDB();
		$stmt = $db->query($sql);
		$db = null;
		echo '{"success":{"message" : "Access token delete!"}}';
	} catch(PDOException $e) {
		echo '{"error":{"message" : "Cannot delete access token!"}}';
	}

}

?>
