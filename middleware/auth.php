<?php
require_once __DIR__ . '/../database/conexaoBd.php';

function authenticate() {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /views/login.html');
        exit();
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'user_type' => $_SESSION['user_type']
    ];
}

function checkUserType($allowedTypes) {
    $auth = authenticate();
    
    if (!in_array($auth['user_type'], $allowedTypes)) {
        header('HTTP/1.1 403 Forbidden');
        exit('Acesso não autorizado');
    }
    
    return $auth;
}
?>