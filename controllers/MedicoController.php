<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

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

    if (!$user || $user['tipo_usuario'] !== 'medico') {
        throw new Exception('Acesso negado: usuário não autenticado ou não é médico', 403);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $medicoId = $user['codigo_usuario'];
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING) ?? '';

    // Verificar CSRF para ações que modificam dados
    if ($action === 'cancelar_consulta') {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }

    switch ($action) {
        case 'listar_consultas':
            $stmt = $conn->prepare("
                SELECT 
                    c.codigo, 
                    DATE_FORMAT(c.data_consulta, '%Y-%m-%d') as data_consulta, 
                    c.hora_consulta, 
                    c.tipo_consulta, 
                    c.estado, 
                    c.nome_paciente
                FROM consultas c
                WHERE c.codigo_medico = ?
                ORDER BY c.data_consulta ASC, c.hora_consulta ASC
            ");
            $stmt->execute([$medicoId]);
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'consultas' => $consultas,
                'message' => 'Consultas carregadas com sucesso'
            ]);
            break;

        case 'proxima_consulta':
            $stmt = $conn->prepare("
                SELECT 
                    c.codigo, 
                    DATE_FORMAT(c.data_consulta, '%Y-%m-%d') as data_consulta, 
                    c.hora_consulta, 
                    c.tipo_consulta, 
                    c.estado, 
                    c.nome_paciente
                FROM consultas c
                WHERE c.codigo_medico = ? 
                AND c.data_consulta >= CURDATE()
                AND c.estado IN ('Agendada', 'Confirmada')
                ORDER BY c.data_consulta ASC, c.hora_consulta ASC
                LIMIT 1
            ");
            $stmt->execute([$medicoId]);
            $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'consulta' => $consulta ?: null,
                'message' => $consulta ? 'Próxima consulta encontrada' : 'Nenhuma consulta futura encontrada'
            ]);
            break;

        case 'consultas_pendentes':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM consultas
                WHERE codigo_medico = ? 
                AND estado = 'Agendada'
                AND data_consulta >= CURDATE()
            ");
            $stmt->execute([$medicoId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'total' => (int)$result['total'],
                'message' => 'Total de consultas pendentes'
            ]);
            break;

        case 'consultas_hoje':
            $stmt = $conn->prepare("
                SELECT COUNT(*) as total
                FROM consultas
                WHERE codigo_medico = ? 
                AND data_consulta = CURDATE()
                AND estado IN ('Agendada', 'Confirmada')
            ");
            $stmt->execute([$medicoId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'total' => (int)$result['total'],
                'message' => 'Total de consultas para hoje'
            ]);
            break;

        case 'cancelar_consulta':
            $consultaId = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            // Verificar se a consulta pertence ao médico e está em estado válido
            $stmt = $conn->prepare("
                SELECT codigo FROM consultas 
                WHERE codigo = ? 
                AND codigo_medico = ?
                AND estado IN ('Agendada', 'Confirmada')
            ");
            $stmt->execute([$consultaId, $medicoId]);
            if (!$stmt->fetch()) {
                throw new Exception('Consulta não encontrada, não pertence ao médico ou já foi cancelada/realizada', 404);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE consultas SET estado = 'Cancelada' WHERE codigo = ?");
            $stmt->execute([$consultaId]);

            $sessaoDAO->registarSessao($medicoId, 'medico', 'Cancelar Consulta', "Consulta ID: $consultaId cancelada");
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Consulta cancelada com sucesso'
            ]);
            break;

        default:
            throw new Exception('Ação inválida', 400);
    }
} catch (Exception $e) {
    error_log("Exception in MedicoController: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}