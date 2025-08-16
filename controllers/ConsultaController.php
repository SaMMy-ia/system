<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'paciente') {
    throw new Exception('Acesso negado', 403);
}


try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'paciente') {
        throw new Exception('Acesso negado', 403);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $sessaoDAO = new SessaoDAO();

    $action = $_GET['action'] ?? '';
    $pacienteId = $_SESSION['user_id'];

    switch ($action) {
        // Listar médicos disponíveis
        case 'list_medicos':
            $stmt = $conn->prepare("
                SELECT codigo, nome_completo, especialidade 
                FROM medicos 
                WHERE estado = 'activo'
                ORDER BY nome_completo
            ");
            $stmt->execute();
            $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'medicos' => $medicos]);
            break;

        // Criar uma nova consulta
        case 'create':
            $medicoId = $_POST['medico_id'] ?? null;
            $dataConsulta = $_POST['data_consulta'] ?? null;
            $horaConsulta = $_POST['hora_consulta'] ?? null;
            $tipoConsulta = $_POST['tipo_consulta'] ?? null;

            if (!$medicoId || !$dataConsulta || !$horaConsulta || !$tipoConsulta) {
                throw new Exception('Todos os campos são obrigatórios', 400);
            }

            // Buscar nome do médico e paciente
            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ? AND estado = 'activo'");
            $stmt->execute([$medicoId]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$medico) {
                throw new Exception('Médico não encontrado ou inativo', 404);
            }

            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$pacienteId]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            // Inserir consulta
            $stmt = $conn->prepare("
                INSERT INTO consultas (
                    codigo_paciente, codigo_medico, nome_paciente, nome_medico, 
                    data_consulta, hora_consulta, tipo_consulta, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Agendada')
            ");
            $stmt->execute([
                $pacienteId,
                $medicoId,
                $paciente['nome_completo'],
                $medico['nome_completo'],
                $dataConsulta,
                $horaConsulta,
                $tipoConsulta
            ]);

            $sessaoDAO->registarSessao(
                $pacienteId,
                'paciente',
                'Marcar Consulta',
                "Consulta marcada com médico {$medico['nome_completo']} em $dataConsulta $horaConsulta"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta marcada com sucesso']);
            break;

        // Listar consultas do paciente
        case 'read':
            $stmt = $conn->prepare("
                SELECT c.codigo, c.data_consulta, c.hora_consulta, c.tipo_consulta, c.estado, c.nome_medico
                FROM consultas c
                WHERE c.codigo_paciente = ?
                ORDER BY c.data_consulta DESC
            ");
            $stmt->execute([$pacienteId]);
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'consultas' => $consultas]);
            break;

        // Atualizar data/hora da consulta
        case 'update':
            $consultaId = $_POST['consulta_id'] ?? null;
            $novaData = $_POST['data_consulta'] ?? null;
            $novaHora = $_POST['hora_consulta'] ?? null;

            if (!$consultaId || !$novaData || !$novaHora) {
                throw new Exception('Todos os campos são obrigatórios', 400);
            }

            $stmt = $conn->prepare("
                UPDATE consultas 
                SET data_consulta = ?, hora_consulta = ? 
                WHERE codigo = ? AND codigo_paciente = ?
            ");
            $stmt->execute([$novaData, $novaHora, $consultaId, $pacienteId]);

            $sessaoDAO->registarSessao(
                $pacienteId,
                'paciente',
                'Alterar Consulta',
                "Consulta $consultaId alterada para $novaData $novaHora"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta atualizada com sucesso']);
            break;

        // Cancelar consulta
        case 'delete':
            $consultaId = $_POST['consulta_id'] ?? null;
            if (!$consultaId) {
                throw new Exception('Consulta não informada', 400);
            }

            $stmt = $conn->prepare("
                DELETE FROM consultas 
                WHERE codigo = ? AND codigo_paciente = ?
            ");
            $stmt->execute([$consultaId, $pacienteId]);

            $sessaoDAO->registarSessao(
                $pacienteId,
                'paciente',
                'Cancelar Consulta',
                "Consulta $consultaId cancelada"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta cancelada com sucesso']);
            break;

        default:
            throw new Exception('Ação inválida', 400);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
