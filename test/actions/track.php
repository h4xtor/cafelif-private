<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';header('Content-Type: application/json');$type=(string)($_POST['type']??'');$allowed=['menu_click','add_to_cart','phone_click','email_click','facebook_click'];if(!in_array($type,$allowed,true)){http_response_code(400);echo '{"ok":false}';exit;}log_event($type,isset($_POST['item_id'])?(int)$_POST['item_id']:null);echo '{"ok":true}';
