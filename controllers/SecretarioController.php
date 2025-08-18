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

    if (!$user || $user['tipo_usuario'] !== 'secretario') {
        throw new Exception('Acesso negado', 403);
    }

    $_SESSION['user_id'] = $user['codigo_usuario'];

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';
    $secretarioId = $_SESSION['user_id'];

    // Verificar CSRF para ações que modificam dados
    $modifyingActions = ['update_consulta', 'confirmar', 'cancelar', 'create_medico', 'create_paciente', 'create_consulta'];
    if (in_array($action, $modifyingActions)) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Token CSRF inválido', 403);
        }
    }

    switch ($action) {
        case 'read_consultas':
            $stmt = $conn->prepare("
                SELECT c.codigo, c.data_consulta, c.hora_consulta, c.tipo_consulta, c.estado, 
                       c.nome_paciente, c.nome_medico, c.codigo_paciente, c.codigo_medico
                FROM consultas c
                ORDER BY c.data_consulta DESC, c.hora_consulta DESC
                LIMIT 100
            ");
            $stmt->execute();
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'consultas' => $consultas]);
            break;

        case 'consultas_hoje':
            $stmt = $conn->prepare("
                SELECT c.codigo, c.data_consulta, c.hora_consulta, c.tipo_consulta, c.estado, 
                       c.nome_paciente, c.nome_medico
                FROM consultas c
                WHERE c.data_consulta = CURDATE()
                ORDER BY c.hora_consulta ASC
            ");
            $stmt->execute();
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'consultas' => $consultas]);
            break;

        case 'pendentes':
            $stmt = $conn->prepare("
                SELECT c.codigo, c.data_consulta, c.hora_consulta, c.tipo_consulta, c.estado, 
                       c.nome_paciente, c.nome_medico
                FROM consultas c
                WHERE c.estado = 'Agendada'
                ORDER BY c.data_consulta ASC, c.hora_consulta ASC
            ");
            $stmt->execute();
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'consultas' => $consultas]);
            break;

        case 'confirmadas':
            $stmt = $conn->prepare("
                SELECT c.codigo, c.data_consulta, c.hora_consulta, c.tipo_consulta, c.estado, 
                       c.nome_paciente, c.nome_medico
                FROM consultas c
                WHERE c.estado = 'Confirmada'
                ORDER BY c.data_consulta ASC, c.hora_consulta ASC
            ");
            $stmt->execute();
            $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'consultas' => $consultas]);
            break;

        case 'get_consulta':
            $consultaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('ID da consulta inválido', 400);
            }

            $stmt = $conn->prepare("SELECT * FROM consultas WHERE codigo = ?");
            $stmt->execute([$consultaId]);
            $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$consulta) {
                throw new Exception('Consulta não encontrada', 404);
            }

            echo json_encode(['success' => true, 'consulta' => $consulta]);
            break;

        case 'update_consulta':
            $consultaId = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('ID da consulta inválido', 400);
            }

            $requiredFields = [
                'paciente_id' => 'Paciente',
                'medico_id' => 'Médico',
                'data_consulta' => 'Data da consulta',
                'hora_consulta' => 'Hora da consulta',
                'tipo_consulta' => 'Tipo de consulta'
            ];
            $dados = [
                'paciente_id' => filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT),
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
                $dados['medico_id'],
                $dados['data_consulta'],
                $dados['hora_consulta'],
                $consultaId
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma consulta marcada para este médico no mesmo horário', 409);
            }

            // Buscar nome do paciente e médico
            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$dados['paciente_id']]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ?");
            $stmt->execute([$dados['medico_id']]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$medico) {
                throw new Exception('Médico não encontrado', 404);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("
                UPDATE consultas SET
                    codigo_paciente = ?,
                    codigo_medico = ?,
                    nome_paciente = ?,
                    nome_medico = ?,
                    data_consulta = ?,
                    hora_consulta = ?,
                    tipo_consulta = ?
                WHERE codigo = ?
            ");
            $stmt->execute([
                $dados['paciente_id'],
                $dados['medico_id'],
                $paciente['nome_completo'],
                $medico['nome_completo'],
                $dados['data_consulta'],
                $dados['hora_consulta'],
                $dados['tipo_consulta'],
                $consultaId
            ]);

            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Atualizar Consulta', "Consulta ID: $consultaId atualizada");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Consulta atualizada com sucesso']);
            break;

        case 'confirmar':
            $consultaId = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('ID da consulta inválido', 400);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE consultas SET estado = 'Confirmada' WHERE codigo = ?");
            $stmt->execute([$consultaId]);

            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Confirmar Consulta', "Consulta ID: $consultaId confirmada");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Consulta confirmada com sucesso']);
            break;

        case 'cancelar':
            $consultaId = filter_input(INPUT_POST, 'consulta_id', FILTER_VALIDATE_INT);
            if (!$consultaId) {
                throw new Exception('ID da consulta inválido', 400);
            }

            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE consultas SET estado = 'Cancelada' WHERE codigo = ?");
            $stmt->execute([$consultaId]);

            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Cancelar Consulta', "Consulta ID: $consultaId cancelada");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Consulta cancelada com sucesso']);
            break;

        case 'create_medico':
            $requiredFields = [
                'nome_completo' => 'Nome completo',
                'especialidade' => 'Especialidade',
                'numero_da_cedula_profissional' => 'Número da cédula profissional',
                'email' => 'Email',
                'senha' => 'Senha'
            ];
            $dados = [
                'nome_completo' => filter_input(INPUT_POST, 'nome_completo', FILTER_SANITIZE_STRING),
                'especialidade' => filter_input(INPUT_POST, 'especialidade', FILTER_SANITIZE_STRING),
                'numero_da_cedula_profissional' => filter_input(INPUT_POST, 'numero_da_cedula_profissional', FILTER_SANITIZE_STRING),
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'senha' => $_POST['senha'],
                'telefone' => filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING),
                'data_nascimento' => filter_input(INPUT_POST, 'data_nascimento', FILTER_SANITIZE_STRING),
                'sexo' => filter_input(INPUT_POST, 'sexo', FILTER_SANITIZE_STRING),
                'ano_de_conclusao' => filter_input(INPUT_POST, 'ano_de_conclusao', FILTER_VALIDATE_INT),
                'nivel_academico' => filter_input(INPUT_POST, 'nivel_academico', FILTER_SANITIZE_STRING)
            ];

            validarCamposObrigatorios($requiredFields, $dados);

            if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Formato de email inválido', 400);
            }

            if (
                strlen($dados['email']) > 100 || strlen($dados['nome_completo']) > 100 ||
                strlen($dados['especialidade']) > 50 || strlen($dados['numero_da_cedula_profissional']) > 30
            ) {
                throw new Exception('Tamanho máximo de campo excedido', 400);
            }

            if ($dados['data_nascimento'] && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dados['data_nascimento'])) {
                throw new Exception('Formato de data de nascimento inválido', 400);
            }

            if ($dados['ano_de_conclusao'] && !is_numeric($dados['ano_de_conclusao'])) {
                throw new Exception('Ano de conclusão deve ser numérico', 400);
            }

            $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE email = ?");
            $stmt->execute([$dados['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email já está em uso', 409);
            }

            $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE numero_da_cedula_profissional = ?");
            $stmt->execute([$dados['numero_da_cedula_profissional']]);
            if ($stmt->fetch()) {
                throw new Exception('Número de cédula profissional já cadastrado', 409);
            }

            $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            $conn->beginTransaction();
            $stmt = $conn->prepare("
                INSERT INTO medicos (
                    nome_completo, especialidade, numero_da_cedula_profissional, email, senha,
                    telefone, data_nascimento, sexo, ano_de_conclusao, nivel_academico, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            $stmt->execute([
                $dados['nome_completo'],
                $dados['especialidade'],
                $dados['numero_da_cedula_profissional'],
                $dados['email'],
                $senhaHash,
                $dados['telefone'],
                $dados['data_nascimento'],
                $dados['sexo'],
                $dados['ano_de_conclusao'],
                $dados['nivel_academico']
            ]);

            $medicoId = $conn->lastInsertId();
            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Cadastrar Médico', "Médico ID: $medicoId cadastrado");
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Médico cadastrado com sucesso',
                'medico_id' => $medicoId
            ]);
            break;

        case 'list_medicos':
            $stmt = $conn->prepare("
                SELECT codigo, nome_completo, especialidade, email, telefone, estado
                FROM medicos 
                ORDER BY nome_completo
            ");
            $stmt->execute();
            $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'medicos' => $medicos]);
            break;

        case 'get_medico':
            $medicoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$medicoId) {
                throw new Exception('ID do médico inválido', 400);
            }

            $stmt = $conn->prepare("SELECT * FROM medicos WHERE codigo = ?");
            $stmt->execute([$medicoId]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medico) {
                throw new Exception('Médico não encontrado', 404);
            }

            echo json_encode(['success' => true, 'medico' => $medico]);
            break;

        case 'create_paciente':
            $requiredFields = [
                'nome_completo' => 'Nome completo',
                'email' => 'Email',
                'senha' => 'Senha'
            ];
            $dados = [
                'nome_completo' => filter_input(INPUT_POST, 'nome_completo', FILTER_SANITIZE_STRING),
                'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
                'senha' => $_POST['senha'],
                'telefone' => filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING),
                'data_nascimento' => filter_input(INPUT_POST, 'data_nascimento', FILTER_SANITIZE_STRING),
                'sexo' => filter_input(INPUT_POST, 'sexo', FILTER_SANITIZE_STRING),
                'morada' => filter_input(INPUT_POST, 'morada', FILTER_SANITIZE_STRING),
                'grupo_sanguineo' => filter_input(INPUT_POST, 'grupo_sanguineo', FILTER_SANITIZE_STRING)
            ];

            validarCamposObrigatorios($requiredFields, $dados);

            if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Formato de email inválido', 400);
            }

            if (strlen($dados['email']) > 100 || strlen($dados['nome_completo']) > 100) {
                throw new Exception('Tamanho máximo de campo excedido', 400);
            }

            if ($dados['data_nascimento'] && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dados['data_nascimento'])) {
                throw new Exception('Formato de data de nascimento inválido', 400);
            }

            $stmt = $conn->prepare("SELECT codigo FROM pacientes WHERE email = ?");
            $stmt->execute([$dados['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email já está em uso', 409);
            }

            $senhaHash = password_hash($dados['senha'], PASSWORD_DEFAULT);
            $conn->beginTransaction();
            $stmt = $conn->prepare("
                INSERT INTO pacientes (
                    nome_completo, email, senha, telefone, data_nascimento, 
                    sexo, morada, grupo_sanguineo, estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            $stmt->execute([
                $dados['nome_completo'],
                $dados['email'],
                $senhaHash,
                $dados['telefone'],
                $dados['data_nascimento'],
                $dados['sexo'],
                $dados['morada'],
                $dados['grupo_sanguineo']
            ]);

            $pacienteId = $conn->lastInsertId();
            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Cadastrar Paciente', "Paciente ID: $pacienteId cadastrado");
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Paciente cadastrado com sucesso',
                'paciente_id' => $pacienteId
            ]);
            break;

        case 'list_pacientes':
            $stmt = $conn->prepare("
                SELECT codigo, nome_completo, email, telefone, data_nascimento, estado
                FROM pacientes 
                ORDER BY nome_completo
            ");
            $stmt->execute();
            $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'pacientes' => $pacientes]);
            break;

        case 'get_paciente':
            $pacienteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$pacienteId) {
                throw new Exception('ID do paciente inválido', 400);
            }

            $stmt = $conn->prepare("SELECT * FROM pacientes WHERE codigo = ?");
            $stmt->execute([$pacienteId]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            echo json_encode(['success' => true, 'paciente' => $paciente]);
            break;

        case 'create_consulta':
            $requiredFields = [
                'paciente_id' => 'Paciente',
                'medico_id' => 'Médico',
                'data_consulta' => 'Data da consulta',
                'hora_consulta' => 'Hora da consulta',
                'tipo_consulta' => 'Tipo de consulta'
            ];
            $dados = [
                'paciente_id' => filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT),
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

            // Verificar se paciente existe
            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$dados['paciente_id']]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            // Verificar se médico existe
            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ?");
            $stmt->execute([$dados['medico_id']]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$medico) {
                throw new Exception('Médico não encontrado', 404);
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
                $dados['paciente_id'],
                $dados['medico_id'],
                $paciente['nome_completo'],
                $medico['nome_completo'],
                $dados['data_consulta'],
                $dados['hora_consulta'],
                $dados['tipo_consulta']
            ]);

            $consultaId = $conn->lastInsertId();
            $sessaoDAO->registarSessao($secretarioId, 'secretario', 'Agendar Consulta', "Consulta ID: $consultaId agendada");
            $conn->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Consulta agendada com sucesso',
                'consulta_id' => $consultaId
            ]);
            break;

        default:
            throw new Exception('Ação inválida', 400);
    }
} catch (Exception $e) {
    error_log("Exception in SecretarioController: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}