<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'medico') {
        throw new Exception('Acesso negado', 403);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $sessaoDAO = new SessaoDAO();

    $medicoId = $_SESSION['user_id'];
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    // Verificar CSRF para ações que modificam dados
    if (in_array($action, ['cancelar_consulta'])) {
        $headers = getallheaders();
        $csrfToken = $headers['X-CSRF-Token'] ?? '';

        if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }

    switch ($action) {
        // Listar todas as consultas do médico
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

        // Próxima consulta do médico
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

            if ($consulta) {
                echo json_encode([
                    'success' => true,
                    'consulta' => $consulta,
                    'message' => 'Próxima consulta encontrada'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'consulta' => null,
                    'message' => 'Nenhuma consulta futura encontrada'
                ]);
            }
            break;

        // Consultas pendentes do médico
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

        // Consultas de hoje do médico
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

        // Cancelar consulta
        case 'cancelar_consulta':
            $consultaId = $_POST['consulta_id'] ?? null;
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            // Verificar se a consulta pertence ao médico
            $stmt = $conn->prepare("
                SELECT codigo FROM consultas 
                WHERE codigo = ? AND codigo_medico = ?
            ");
            $stmt->execute([$consultaId, $medicoId]);

            if (!$stmt->fetch()) {
                throw new Exception('Consulta não encontrada ou não pertence ao médico', 404);
            }

            // Atualizar estado da consulta
            $stmt = $conn->prepare("
                UPDATE consultas 
                SET estado = 'Cancelada' 
                WHERE codigo = ?
            ");
            $stmt->execute([$consultaId]);

            // Registrar na sessão
            $sessaoDAO->registarSessao(
                $medicoId,
                'medico',
                'Cancelar Consulta',
                "Consulta $consultaId cancelada pelo médico"
            );

            echo json_encode([
                'success' => true,
                'message' => 'Consulta cancelada com sucesso'
            ]);
            break;

        default:
            throw new Exception('Ação inválida', 400);
    }
} catch (PDOException $e) {
    error_log("PDOException in MedicoController: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados',
        'message' => $e->getMessage(),
        'code' => 500
    ]);
} catch (Exception $e) {
    error_log("Exception in MedicoController: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
