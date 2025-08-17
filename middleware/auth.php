<?php
require_once __DIR__ . '/../database/conexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return $_GET['token'] ?? $_POST['token'] ?? null;
}

function authenticate() {
    $token = getBearerToken();
    
    if (!$token) {
        http_response_code(401);
        exit(json_encode(['success' => false, 'error' => 'Token não fornecido']));
    }
    
    $sessaoDAO = new SessaoDAO();
    $user = $sessaoDAO->validarToken($token);
    
    if (!$user) {
        http_response_code(401);
        exit(json_encode(['success' => false, 'error' => 'Token inválido ou expirado']));
    }
    
    return $user;
}

function checkUserType($allowedTypes) {
    $auth = authenticate();
    
    if (!in_array($auth['tipo_usuario'], $allowedTypes)) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Acesso não autorizado']));
    }
    
    return $auth;
}