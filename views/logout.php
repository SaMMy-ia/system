<?php
// logout.php
require_once __DIR__ . '/../database/conexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

header('Content-Type: application/json');

try {
    // Verifica se é uma requisição AJAX/API ou navegador
    $isApiRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    $sessaoDAO = new SessaoDAO();

    // Para requisições de navegador (com token no localStorage)
    if (!$isApiRequest && !empty($_COOKIE['authToken'])) {
        $token = $_COOKIE['authToken'];
        $sessaoDAO->encerrarSessao($token);
        setcookie('authToken', '', time() - 3600, '/');
    }
    
    // Para requisições com header Authorization
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
            $sessaoDAO->encerrarSessao($token);
        }
    }

    // Limpar qualquer sessão PHP residual (se ainda estiver em uso)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    if ($isApiRequest) {
        echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
    } else {
        header('Location: login.html');
    }
    exit;

} catch (Exception $e) {
    if ($isApiRequest) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        // Redireciona para login com mensagem de erro na URL
        header('Location: login.html?error=' . urlencode($e->getMessage()));
    }
    exit;
}