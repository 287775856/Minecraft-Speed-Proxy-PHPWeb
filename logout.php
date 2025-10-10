<?php
session_start();
require_once 'api-helper.php';

if (isLoggedIn()) {
    $token = getToken();
    makeApiRequest('logout', 'GET', null, $token);
}

session_destroy();
header('Location: login.php');
exit;
?>