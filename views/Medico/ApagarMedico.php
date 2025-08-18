<?php
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['authToken'] ?? null;
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }

    if (!$token) {
        throw new Exception('Token não fornecido', 401);
    }

    $sessaoDAO = new SessaoDAO();
    $user = $sessaoDAO->validarToken($token);

    if (!$user || $user['tipo_usuario'] !== 'secretario') {
        throw new Exception('Acesso negado', 403);
    }

    // Verificar CSRF
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        throw new Exception('Token CSRF inválido', 403);
    }

    // Verificar ID do médico
    $medicoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$medicoId) {
        throw new Exception('ID do médico inválido', 400);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();

    // Verificar se o médico existe
    $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ?");
    $stmt->execute([$medicoId]);
    $medico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        throw new Exception('Médico não encontrado', 404);
    }

    // Verificar consultas agendadas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM consultas WHERE codigo_medico = ? AND estado = 'Agendada'");
    $stmt->execute([$medicoId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['total'] > 0) {
        throw new Exception('Não é possível inativar: médico possui consultas agendadas', 400);
    }

    $conn->beginTransaction();
    $stmt = $conn->prepare("UPDATE medicos SET estado = 'inactivo' WHERE codigo = ?");
    $stmt->execute([$medicoId]);

    $sessaoDAO->registarSessao($user['codigo_usuario'], 'secretario', 'Inativação de Médico', "Médico ID: $medicoId inativado");
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Médico inativado com sucesso']);
} catch (Exception $e) {
    error_log("Exception in apagarMedico: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}