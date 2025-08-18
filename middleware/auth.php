<?php
require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
        return $matches[1];
    }
    return $_COOKIE['authToken'] ?? null;
}

function authenticate() {
    $token = getBearerToken();
    
    if (!$token) {
        throw new Exception('Token não fornecido', 401);
    }
    
    $sessaoDAO = new SessaoDAO();
    $user = $sessaoDAO->validarToken($token);
    
    if (!$user) {
        throw new Exception('Token inválido ou expirado', 401);
    }
    
    return $user;
}

function checkUserType($allowedTypes) {
    $user = authenticate();
    
    if (!in_array($user['tipo_usuario'], $allowedTypes)) {
        throw new Exception('Acesso não autorizado', 403);
    }
    
    return $user;
}