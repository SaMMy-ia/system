<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database/ConexaoBd.php';
require_once __DIR__ . '/../models/SessaoDAO.php';

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'secretario') {
        throw new Exception('Acesso negado: usuário não autenticado ou não é secretário', 403);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();
    $sessaoDAO = new SessaoDAO();

    $action = $_GET['action'] ?? '';
    $secretarioId = $_SESSION['user_id'];

    error_log("SecretarioController - Ação solicitada: $action - Dados recebidos: " . json_encode($_POST));

    switch ($action) {
        // ==============================================
        // CONSULTAS
        // ==============================================
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
            $consultaId = $_GET['id'] ?? null;
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            $stmt = $conn->prepare("
                SELECT * FROM consultas WHERE codigo = ?
            ");
            $stmt->execute([$consultaId]);
            $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$consulta) {
                throw new Exception('Consulta não encontrada', 404);
            }

            echo json_encode(['success' => true, 'consulta' => $consulta]);
            break;

        case 'update_consulta':
            $consultaId = $_POST['consulta_id'] ?? null;
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            $requiredFields = ['paciente_id', 'medico_id', 'data_consulta', 'hora_consulta', 'tipo_consulta'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obrigatório '$field' não preenchido", 400);
                }
            }

            // Buscar nome do paciente e médico
            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$_POST['paciente_id']]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ?");
            $stmt->execute([$_POST['medico_id']]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$medico) {
                throw new Exception('Médico não encontrado', 404);
            }

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
                $_POST['paciente_id'],
                $_POST['medico_id'],
                $paciente['nome_completo'],
                $medico['nome_completo'],
                $_POST['data_consulta'],
                $_POST['hora_consulta'],
                $_POST['tipo_consulta'],
                $consultaId
            ]);

            $sessaoDAO->registarSessao(
                $secretarioId,
                'secretario',
                'Atualizar Consulta',
                "Consulta $consultaId atualizada"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta atualizada com sucesso']);
            break;

        case 'confirmar':
            $consultaId = $_POST['consulta_id'] ?? null;
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            $stmt = $conn->prepare("
                UPDATE consultas 
                SET estado = 'Confirmada' 
                WHERE codigo = ?
            ");
            $stmt->execute([$consultaId]);

            $sessaoDAO->registarSessao(
                $secretarioId,
                'secretario',
                'Confirmar Consulta',
                "Consulta $consultaId confirmada"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta confirmada com sucesso']);
            break;

        case 'cancelar':
            $consultaId = $_POST['consulta_id'] ?? null;
            if (!$consultaId) {
                throw new Exception('ID da consulta não informado', 400);
            }

            $stmt = $conn->prepare("
                UPDATE consultas 
                SET estado = 'Cancelada' 
                WHERE codigo = ?
            ");
            $stmt->execute([$consultaId]);

            $sessaoDAO->registarSessao(
                $secretarioId,
                'secretario',
                'Cancelar Consulta',
                "Consulta $consultaId cancelada"
            );

            echo json_encode(['success' => true, 'message' => 'Consulta cancelada com sucesso']);
            break;

        // ==============================================
        // MÉDICOS
        // ==============================================
        case 'create_medico':
            $requiredFields = [
                'nome_completo' => 'Nome completo',
                'especialidade' => 'Especialidade',
                'numero_da_cedula_profissional' => 'Número da cédula profissional',
                'email' => 'Email',
                'senha' => 'Senha'
            ];

            $missingFields = [];
            foreach ($requiredFields as $field => $name) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $name;
                }
            }

            if (!empty($missingFields)) {
                throw new Exception('Campos obrigatórios não preenchidos: ' . implode(', ', $missingFields), 400);
            }

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Formato de email inválido', 400);
            }

            $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email já está em uso por outro médico', 409);
            }

            $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE numero_da_cedula_profissional = ?");
            $stmt->execute([$_POST['numero_da_cedula_profissional']]);
            if ($stmt->fetch()) {
                throw new Exception('Número de cédula profissional já cadastrado', 409);
            }

            $telefone = !empty($_POST['telefone']) ? $_POST['telefone'] : null;
            $dataNascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
            $sexo = !empty($_POST['sexo']) ? $_POST['sexo'] : null;
            $anoConclusao = !empty($_POST['ano_de_conclusao']) ? $_POST['ano_de_conclusao'] : null;
            $nivelAcademico = !empty($_POST['nivel_academico']) ? $_POST['nivel_academico'] : null;

            $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);

            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    INSERT INTO medicos (
                        nome_completo, especialidade, numero_da_cedula_profissional, email, senha,
                        telefone, data_nascimento, sexo, ano_de_conclusao, nivel_academico, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')
                ");

                $stmt->execute([
                    $_POST['nome_completo'],
                    $_POST['especialidade'],
                    $_POST['numero_da_cedula_profissional'],
                    $_POST['email'],
                    $senhaHash,
                    $telefone,
                    $dataNascimento,
                    $sexo,
                    $anoConclusao,
                    $nivelAcademico
                ]);

                $medicoId = $conn->lastInsertId();

                $sessaoDAO->registarSessao(
                    $secretarioId,
                    'secretario',
                    'Cadastrar Médico',
                    "Médico {$_POST['nome_completo']} (ID: $medicoId) cadastrado"
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Médico cadastrado com sucesso',
                    'medico_id' => $medicoId
                ]);
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Erro ao cadastrar médico: " . $e->getMessage());
                throw new Exception('Erro no banco de dados ao cadastrar médico: ' . $e->getMessage(), 500);
            }
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
            $medicoId = $_GET['id'] ?? null;
            if (!$medicoId) {
                throw new Exception('ID do médico não informado', 400);
            }

            $stmt = $conn->prepare("
                SELECT * FROM medicos WHERE codigo = ?
            ");
            $stmt->execute([$medicoId]);
            $medico = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medico) {
                throw new Exception('Médico não encontrado', 404);
            }

            echo json_encode(['success' => true, 'medico' => $medico]);
            break;

        // ==============================================
        // PACIENTES
        // ==============================================
        case 'create_paciente':
            $requiredFields = [
                'nome_completo' => 'Nome completo',
                'email' => 'Email',
                'senha' => 'Senha'
            ];

            $missingFields = [];
            foreach ($requiredFields as $field => $name) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $name;
                }
            }

            if (!empty($missingFields)) {
                throw new Exception('Campos obrigatórios não preenchidos: ' . implode(', ', $missingFields), 400);
            }

            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Formato de email inválido', 400);
            }

            $stmt = $conn->prepare("SELECT codigo FROM pacientes WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception('Email já está em uso por outro paciente', 409);
            }

            $telefone = !empty($_POST['telefone']) ? $_POST['telefone'] : null;
            $dataNascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
            $sexo = !empty($_POST['sexo']) ? $_POST['sexo'] : null;
            $morada = !empty($_POST['morada']) ? $_POST['morada'] : null;
            $grupoSanguineo = !empty($_POST['grupo_sanguineo']) ? $_POST['grupo_sanguineo'] : null;

            $senhaHash = password_hash($_POST['senha'], PASSWORD_DEFAULT);

            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    INSERT INTO pacientes (
                        nome_completo, email, senha, telefone, data_nascimento, 
                        sexo, morada, grupo_sanguineo, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')
                ");

                $stmt->execute([
                    $_POST['nome_completo'],
                    $_POST['email'],
                    $senhaHash,
                    $telefone,
                    $dataNascimento,
                    $sexo,
                    $morada,
                    $grupoSanguineo
                ]);

                $pacienteId = $conn->lastInsertId();

                $sessaoDAO->registarSessao(
                    $secretarioId,
                    'secretario',
                    'Cadastrar Paciente',
                    "Paciente {$_POST['nome_completo']} (ID: $pacienteId) cadastrado"
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Paciente cadastrado com sucesso',
                    'paciente_id' => $pacienteId
                ]);
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Erro ao cadastrar paciente: " . $e->getMessage());
                throw new Exception('Erro no banco de dados ao cadastrar paciente: ' . $e->getMessage(), 500);
            }
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
            $pacienteId = $_GET['id'] ?? null;
            if (!$pacienteId) {
                throw new Exception('ID do paciente não informado', 400);
            }

            $stmt = $conn->prepare("
                SELECT * FROM pacientes WHERE codigo = ?
            ");
            $stmt->execute([$pacienteId]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            echo json_encode(['success' => true, 'paciente' => $paciente]);
            break;

        // ==============================================
        // AGENDAMENTO
        // ==============================================
        case 'create_consulta':
            $requiredFields = [
                'paciente_id' => 'Paciente',
                'medico_id' => 'Médico',
                'data_consulta' => 'Data da consulta',
                'hora_consulta' => 'Hora da consulta',
                'tipo_consulta' => 'Tipo de consulta'
            ];

            $missingFields = [];
            foreach ($requiredFields as $field => $name) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $name;
                }
            }

            if (!empty($missingFields)) {
                throw new Exception('Campos obrigatórios não preenchidos: ' . implode(', ', $missingFields), 400);
            }

            // Verificar se paciente existe
            $stmt = $conn->prepare("SELECT nome_completo FROM pacientes WHERE codigo = ?");
            $stmt->execute([$_POST['paciente_id']]);
            $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$paciente) {
                throw new Exception('Paciente não encontrado', 404);
            }

            // Verificar se médico existe
            $stmt = $conn->prepare("SELECT nome_completo FROM medicos WHERE codigo = ?");
            $stmt->execute([$_POST['medico_id']]);
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
                $_POST['medico_id'],
                $_POST['data_consulta'],
                $_POST['hora_consulta']
            ]);
            if ($stmt->fetch()) {
                throw new Exception('Já existe uma consulta marcada para este médico no mesmo horário', 409);
            }

            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    INSERT INTO consultas (
                        codigo_paciente, codigo_medico, nome_paciente, nome_medico,
                        data_consulta, hora_consulta, tipo_consulta, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Agendada')
                ");

                $stmt->execute([
                    $_POST['paciente_id'],
                    $_POST['medico_id'],
                    $paciente['nome_completo'],
                    $medico['nome_completo'],
                    $_POST['data_consulta'],
                    $_POST['hora_consulta'],
                    $_POST['tipo_consulta']
                ]);

                $consultaId = $conn->lastInsertId();

                $sessaoDAO->registarSessao(
                    $secretarioId,
                    'secretario',
                    'Agendar Consulta',
                    "Consulta $consultaId agendada para paciente {$_POST['paciente_id']} com médico {$_POST['medico_id']}"
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Consulta agendada com sucesso',
                    'consulta_id' => $consultaId
                ]);
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Erro ao agendar consulta: " . $e->getMessage());
                throw new Exception('Erro no banco de dados ao agendar consulta: ' . $e->getMessage(), 500);
            }
            break;

        default:
            throw new Exception('Ação inválida', 400);
    }
} catch (PDOException $e) {
    error_log("PDOException in SecretarioController: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro no banco de dados: ' . $e->getMessage(),
        'code' => 500,
        'details' => $e->errorInfo ?? null
    ]);
} catch (Exception $e) {
    error_log("Exception in SecretarioController: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
