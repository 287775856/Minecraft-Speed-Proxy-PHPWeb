<?php
require_once 'config.php';

function makeApiRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = API_BASE_URL . $endpoint;
    $headers = [
        'Content-Type: application/json',
    ];
    
    if ($token) {
        $headers[] = 'Authorize: ' . $token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'data' => json_decode($response, true)
    ];
}

function login($password) {
    $response = makeApiRequest('login', 'POST', ['password' => $password]);
    
    if ($response['code'] == 200 && $response['data']['status'] == 200) {
        $_SESSION['token'] = $response['data']['token'];
        $_SESSION['token_expiry'] = time() + TOKEN_EXPIRY;
        return true;
    }
    
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['token']) && $_SESSION['token_expiry'] > time();
}

function getToken() {
    return $_SESSION['token'] ?? null;
}
?>
