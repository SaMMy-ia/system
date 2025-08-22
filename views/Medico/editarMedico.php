<?php
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Verificar autenticação e tipo de usuário
    $user = checkUserType(['secretario']);

    // Iniciar sessão para CSRF
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Validar ID do médico
    $medicoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$medicoId) {
        throw new Exception('ID do médico inválido', 400);
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();

    // Buscar dados do médico
    $stmt = $conn->prepare("SELECT * FROM medicos WHERE codigo = ?");
    $stmt->execute([$medicoId]);
    $medico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        throw new Exception('Médico não encontrado', 404);
    }

    // Processar formulário se for POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token']) {
            throw new Exception('Token CSRF inválido', 403);
        }

        // Coletar e validar dados
        $requiredFields = [
            'nome_completo' => 'Nome completo',
            'especialidade' => 'Especialidade',
            'numero_da_cedula_profissional' => 'Número da cédula profissional',
            'email' => 'Email',
            'estado' => 'Estado'
        ];
        $dados = [
            'nome_completo' => filter_input(INPUT_POST, 'nome_completo', FILTER_SANITIZE_STRING),
            'especialidade' => filter_input(INPUT_POST, 'especialidade', FILTER_SANITIZE_STRING),
            'numero_da_cedula_profissional' => filter_input(INPUT_POST, 'cedula', FILTER_SANITIZE_STRING),
            'telefone' => filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING) ?: null,
            'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
            'data_nascimento' => filter_input(INPUT_POST, 'data_nascimento', FILTER_SANITIZE_STRING) ?: null,
            'sexo' => filter_input(INPUT_POST, 'sexo', FILTER_SANITIZE_STRING) ?: null,
            'ano_de_conclusao' => filter_input(INPUT_POST, 'ano_conclusao', FILTER_VALIDATE_INT) ?: null,
            'nivel_academico' => filter_input(INPUT_POST, 'nivel_academico', FILTER_SANITIZE_STRING) ?: null,
            'estado' => filter_input(INPUT_POST, 'estado', FILTER_SANITIZE_STRING)
        ];

        // Validar campos obrigatórios
        $missingFields = [];
        foreach ($requiredFields as $field => $name) {
            if (empty($dados[$field])) {
                $missingFields[] = $name;
            }
        }
        if (!empty($missingFields)) {
            throw new Exception('Campos obrigatórios não preenchidos: ' . implode(', ', $missingFields), 400);
        }

        // Validar email
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Formato de email inválido', 400);
        }

        // Validar data de nascimento
        if ($dados['data_nascimento'] && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dados['data_nascimento'])) {
            throw new Exception('Formato de data de nascimento inválido', 400);
        }

        // Validar ano de conclusão
        if ($dados['ano_de_conclusao'] && !is_numeric($dados['ano_de_conclusao'])) {
            throw new Exception('Ano de conclusão deve ser numérico', 400);
        }

        // Validar comprimento dos campos
        if (
            strlen($dados['nome_completo']) > 100 ||
            strlen($dados['especialidade']) > 50 ||
            strlen($dados['numero_da_cedula_profissional']) > 30 ||
            strlen($dados['email']) > 100
        ) {
            throw new Exception('Tamanho máximo de campo excedido', 400);
        }

        // Verificar duplicatas
        $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE email = ? AND codigo != ?");
        $stmt->execute([$dados['email'], $medicoId]);
        if ($stmt->fetch()) {
            throw new Exception('Email já está em uso por outro médico', 409);
        }

        $stmt = $conn->prepare("SELECT codigo FROM medicos WHERE numero_da_cedula_profissional = ? AND codigo != ?");
        $stmt->execute([$dados['numero_da_cedula_profissional'], $medicoId]);
        if ($stmt->fetch()) {
            throw new Exception('Número de cédula profissional já cadastrado', 409);
        }

        // Atualizar no banco
        $stmt = $conn->prepare("
            UPDATE medicos SET 
                nome_completo = :nome_completo,
                especialidade = :especialidade,
                numero_da_cedula_profissional = :numero_da_cedula_profissional,
                telefone = :telefone,
                email = :email,
                data_nascimento = :data_nascimento,
                sexo = :sexo,
                ano_de_conclusao = :ano_de_conclusao,
                nivel_academico = :nivel_academico,
                estado = :estado
            WHERE codigo = :codigo
        ");

        $dados['codigo'] = $medicoId;
        $stmt->execute($dados);

        // Registrar ação
        $sessaoDAO = new SessaoDAO();
        $sessaoDAO->registarSessao(
            $user['codigo_usuario'],
            'secretario',
            'Atualizar Médico',
            "Médico ID: $medicoId atualizado"
        );

        header('Location: visualizarMedico.php?id=' . $medicoId . '&success=updated');
        exit;
    }

    // Registrar ação de visualização
    $sessaoDAO = new SessaoDAO();
    $sessaoDAO->registarSessao(
        $user['codigo_usuario'],
        'secretario',
        'Visualizar Formulário de Edição de Médico',
        "Médico ID: $medicoId"
    );
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Médico - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Paleta de cores principal: Azul profissional e verde médico */
        :root {
            --primary-color: #1a73e8;
            /* Azul profissional */
            --secondary-color: #34a853;
            /* Verde médico */
            --light-bg: #f8f9fa;
            /* Fundo claro */
            --dark-text: #202124;
            /* Texto escuro */
            --light-text: #f8f9fa;
            /* Texto claro */
            --border-color: #dadce0;
            /* Cor da borda */
            --success-color: #28a745;
            /* Verde sucesso */
            --danger-color: #dc3545;
            /* Vermelho perigo */
            --warning-color: #ffc107;
            /* Amarelo aviso */
            --info-color: #17a2b8;
            /* Azul informação */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--dark-text);
            line-height: 1.6;
        }

        nav {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        nav h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        nav h2 {
            font-size: 1.1rem;
            font-weight: 500;
        }

        .sidebar {
            background-color: white;
            width: 250px;
            height: calc(100vh - 70px);
            position: fixed;
            padding: 1.5rem 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar a {
            display: block;
            padding: 0.8rem 1.5rem;
            color: var(--dark-text);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar a:hover {
            background-color: rgba(26, 115, 232, 0.1);
            color: var(--primary-color);
        }

        .main {
            margin-left: 250px;
            padding: 2rem;
        }

        .alert {
            padding: 0.8rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        form {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        input,
        select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }

        input:invalid,
        select:invalid {
            border-color: var(--danger-color);
        }

        input:valid,
        select:valid {
            border-color: var(--success-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #0d62d0;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
            }
        }

        @media (max-width: 768px) {
            nav {
                flex-direction: column;
                gap: 0.5rem;
                padding: 1rem;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
                gap: 0.5rem;
                padding: 1rem;
            }

            .main {
                margin-left: 0;
                padding: 1rem;
            }

            .form-row {
                flex-direction: column;
            }

            .form-group {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Editar Médico</h1>
        <h2><?= htmlspecialchars($medico['nome_completo']) ?></h2>
    </nav>

    <div class="sidebar">
        <a href="listarMedico.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>

    <div class="main">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Médico atualizado com sucesso!
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="nome_completo"><i class="fas fa-signature"></i> Nome Completo</label>
                <input type="text" id="nome_completo" name="nome_completo" value="<?= htmlspecialchars($medico['nome_completo']) ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="especialidade"><i class="fas fa-stethoscope"></i> Especialidade</label>
                    <input type="text" id="especialidade" name="especialidade" value="<?= htmlspecialchars($medico['especialidade']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="cedula"><i class="fas fa-id-card"></i> Cédula Profissional</label>
                    <input type="text" id="cedula" name="cedula" value="<?= htmlspecialchars($medico['numero_da_cedula_profissional']) ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="data_nascimento"><i class="fas fa-birthday-cake"></i> Data de Nascimento</label>
                    <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($medico['data_nascimento'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="sexo"><i class="fas fa-venus-mars"></i> Sexo</label>
                    <select id="sexo" name="sexo">
                        <option value="Masculino" <?= $medico['sexo'] === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                        <option value="Feminino" <?= $medico['sexo'] === 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                        <option value="Outro" <?= $medico['sexo'] === 'Outro' ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nivel_academico"><i class="fas fa-graduation-cap"></i> Nível Acadêmico</label>
                    <input type="text" id="nivel_academico" name="nivel_academico" value="<?= htmlspecialchars($medico['nivel_academico'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="ano_conclusao"><i class="fas fa-calendar-alt"></i> Ano de Conclusão</label>
                    <input type="number" id="ano_conclusao" name="ano_conclusao" value="<?= htmlspecialchars($medico['ano_de_conclusao'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="telefone"><i class="fas fa-phone"></i> Telefone</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= htmlspecialchars($medico['telefone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($medico['email']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="estado"><i class="fas fa-check-circle"></i> Estado</label>
                <select id="estado" name="estado" required>
                    <option value="activo" <?= $medico['estado'] === 'activo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactivo" <?= $medico['estado'] === 'inactivo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar Alterações
            </button>
        </form>
    </div>
</body>

</html>