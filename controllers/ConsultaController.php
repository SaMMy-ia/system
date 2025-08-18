<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

try {
    // Funções auxiliares
    function validarCamposObrigatorios($campos, $dados)
    {
        $missing = [];
        foreach ($campos as $campo => $nome) {
            if (empty($dados[$campo])) {
                $missing[] = $nome;
            }
        }
        if (!empty($missing)) {
            throw new Exception('Campos obrigatórios não preenchidos: ' . implode(', ', $missing), 400);
        }
    }

    function validarData($data)
    {
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $data)) {
            throw new Exception('Formato de data inválido', 400);
        }
        $date = DateTime::createFromFormat('Y-m-d', $data);
        if (!$date || $date->format('Y-m-d') !== $data || $date < new DateTime('today')) {
            throw new Exception('Data inválida ou no passado', 400);
        }
    }

    function validarHora($hora)
    {
        if (!preg_match("/^\d{2}:\d{2}(:\d{2})?$/", $hora)) {
            throw new Exception('Formato de hora inválido', 400);
        }
        $time = DateTime::createFromFormat('H:i', $hora);
        if (!$time || $time->format('H:i') !== $hora) {
            throw new Exception('Hora inválida', 400);
        }
    }

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

    if (!$user || $user['tipo_usuario'] !== 'paciente') {
        throw new Exception('Acesso negado: usuário não autenticado ou não é paciente', 403);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';
    $pacienteId = $user['codigo_usuario'];

    // Verificar CSRF para ações que modificam dados
    $modifyingActions = ['create', 'update', 'delete'];
    if (in_array($action, $modifyingActions)) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }

    switch ($action) {
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

        case 'create':
            $requiredFields = [
                'medico_id' => 'Médico',
                'data_consulta' => 'Data da consulta',
                'hora_consulta' => 'Hora da consulta',
                'tipo_consulta' => 'Tipo de consulta'
            ];
            $dados = [
                'medico_id' => filter_input(INPUT_POST, 'medico_id', FILTER_VALIDATE_INT),
                'data_consulta' => filter_input(INPUT_POST, 'data_consulta', FILTER_SANITIZE_STRING),
                'hora_consulta' => filter_input(INPUT_POST, 'hora_consulta', FILTER_SANITIZE_STRING),
                'tipo_consulta' => filter_input(INPUT_POST, 'tipo_consulta', FILTER_SANITIZE_STRING)
            ];

            validarCamposObrigatorios($requiredFields, $dados);
            validarData($dados['data_consulta']);
            validarHora($dados['hora_consulta']);

            if (strlen($dados['tipo_consulta']) > 50) {
                throw new Exception('Tipo de consulta excede o tamanho máximo permitido', 400);
            }

            // Verificar médico
            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ? AND estado = 'activo'");
            $stmt->execute([$dados['medico_id']]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$medico) {
                throw new Exception('Médico não encontrado ou inativo', 404);
            }

            // Verificar paciente
            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$pacienteId]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            // Verificar conflito de horário
            $stmt = $conn->prepare("
                SELECT codigo FROM consultas 
                WHERE codigo_medico = ? 
                AND data_consulta = ? 
                AND hora_consulta = ?
                AND estado NOT IN ('Cancelada', 'Realizada')
            ");
            $stmt->execute([
                $dados['medico_id'],
                $dados['data_consulta'],
                $dados['hora_consulta']
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma consulta marcada para este médico no mesmo horário', 409);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("
                INSERT INTO consultas (
                    codigo_paciente, codigo_medico, nome_paciente, nome_medico, 
                    data_consulta, hora_consulta, tipo_consulta, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Agendada')
            ");
            $stmt->execute([
                $pacienteId,
                $dados['medico_id'],
                $paciente['nome_completo'],
                $medico['nome_completo'],
                $dados['data_consulta'],
                $dados['hora_consulta'],
                $dados['tipo_consulta']
            ]);

            $consultaId = $conn->lastInsertId();
            $sessaoDAO->registarSessao($pacienteId, 'paciente', 'Marcar Consulta', "Consulta ID: $consultaId marcada");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Consulta marcada com sucesso', 'consulta_id' => $consultaId]);
            break;

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

        case 'update':
            $requiredFields = [
                'consulta_id' => 'Consulta',
                'data_consulta' => 'Data da consulta',
                'hora_consulta' => 'Hora da consulta'
            ];
            $dados = [
                'consulta_id' => filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT),
                'data_consulta' => filter_input(INPUT_POST, 'data_consulta', FILTER_SANITIZE_STRING),
                'hora_consulta' => filter_input(INPUT_POST, 'hora_consulta', FILTER_SANITIZE_STRING)
            ];

            validarCamposObrigatorios($requiredFields, $dados);
            validarData($dados['data_consulta']);
            validarHora($dados['hora_consulta']);

            // Verificar se a consulta pertence ao paciente
            $stmt = $conn->prepare("SELECT codigo_medico FROM consultas WHERE codigo = ? AND codigo_paciente = ?");
            $stmt->execute([$dados['consulta_id'], $pacienteId]);
            $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$consulta) {
                throw new Exception('Consulta não encontrada ou não pertence ao paciente', 404);
            }

            // Verificar conflito de horário
            $stmt = $conn->prepare("
                SELECT codigo FROM consultas 
                WHERE codigo_medico = ? 
                AND data_consulta = ? 
                AND hora_consulta = ?
                AND estado NOT IN ('Cancelada', 'Realizada')
                AND codigo != ?
            ");
            $stmt->execute([
                $consulta['codigo_medico'],
                $dados['data_consulta'],
                $dados['hora_consulta'],
                $dados['consulta_id']
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma consulta marcada para este médico no mesmo horário', 409);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("
                UPDATE consultas 
                SET data_consulta = ?, hora_consulta = ? 
                WHERE codigo = ? AND codigo_paciente = ?
            ");
            $stmt->execute([$dados['data_consulta'], $dados['hora_consulta'], $dados['consulta_id'], $pacienteId]);

            $sessaoDAO->registarSessao($pacienteId, 'paciente', 'Alterar Consulta', "Consulta ID: {$dados['consulta_id']} alterada");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Consulta atualizada com sucesso']);
            break;

        case 'delete':
            $consultaId = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('Consulta não informada', 400);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("DELETE FROM consultas WHERE codigo = ? AND codigo_paciente = ?");
            $stmt->execute([$consultaId, $pacienteId]);

            $sessaoDAO->registarSessao($pacienteId, 'paciente', 'Cancelar Consulta', "Consulta ID: $consultaId cancelada");
            $conn->commit();
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