<?php
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();
header('Content-Type: text/html; charset=utf-8');

// Verificar token
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['authToken'] ?? null;
if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7); // Remove "Bearer "
}

$sessaoDAO = new SessaoDAO();
$user = $token ? $sessaoDAO->validarToken($token) : null;

if (!$user || !in_array($user['tipo_usuario'], ['secretario', 'medico'])) {
    header('Location: ../login.html?error=access_denied');
    exit;
}

// Verificar ID do médico
$medicoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$medicoId) {
    header('Location: listarMedicos.php?error=invalid_id');
    exit;
}

// Gerar CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    $db = new ConexaoBd();
    $conn = $db->getConnection();

    // Buscar dados do médico
    $stmt = $conn->prepare("SELECT * FROM medicos WHERE codigo = ?");
    $stmt->execute([$medicoId]);
    $medico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$medico) {
        header('Location: listarMedicos.php?error=medico_not_found');
        exit;
    }

    // Registrar ação na sessão
    $sessaoDAO->registarSessao(
        $user['codigo_usuario'],
        $user['tipo_usuario'],
        'Visualização do médico',
        "Médico ID: $medicoId visualizado"
    );
} catch (PDOException $e) {
    error_log("Erro ao visualizar médico: " . $e->getMessage());
    header('Location: listarMedicos.php?error=database_error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Médico - SumNanz</title>
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

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 800px;
        }

        .card>div {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card>div:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
        }

        p {
            margin-bottom: 0.8rem;
            display: flex;
        }

        strong {
            min-width: 180px;
            color: var(--dark-text);
        }

        .badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-success {
            background-color: rgba(52, 168, 83, 0.15);
            color: var(--secondary-color);
        }

        .badge-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
        }

        .acoes {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-warning {
            background-color: rgba(255, 193, 7, 0.15);
            color: var(--warning-color);
        }

        .btn-warning:hover {
            background-color: rgba(255, 193, 7, 0.25);
        }

        .btn-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: rgba(220, 53, 69, 0.25);
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

            .card {
                padding: 1.5rem;
            }

            p {
                flex-direction: column;
            }

            strong {
                min-width: auto;
                margin-bottom: 0.3rem;
            }

            .acoes {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Visualizar Médico</h1>
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
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_GET['error'] === 'medico_has_appointments' ? 'Não é possível inativar: médico possui consultas agendadas!' : 'Erro ao processar a solicitação!' ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="dados-pessoais">
                <h3><i class="fas fa-id-card"></i> Dados Pessoais</h3>
                <p><strong>Nome Completo:</strong> <?= htmlspecialchars($medico['nome_completo']) ?></p>
                <p><strong>Data de Nascimento:</strong> <?= $medico['data_nascimento'] ? date('d/m/Y', strtotime($medico['data_nascimento'])) : 'Não informado' ?></p>
                <p><strong>Sexo:</strong> <?= htmlspecialchars($medico['sexo'] ?? 'Não informado') ?></p>
                <p><strong>Nível Acadêmico:</strong> <?= htmlspecialchars($medico['nivel_academico'] ?? 'Não informado') ?></p>
                <p><strong>Ano de Conclusão:</strong> <?= htmlspecialchars($medico['ano_de_conclusao'] ?? 'Não informado') ?></p>
            </div>

            <div class="dados-profissionais">
                <h3><i class="fas fa-briefcase"></i> Dados Profissionais</h3>
                <p><strong>Especialidade:</strong> <?= htmlspecialchars($medico['especialidade']) ?></p>
                <p><strong>Cédula Profissional:</strong> <?= htmlspecialchars($medico['numero_da_cedula_profissional']) ?></p>
                <p><strong>Estado:</strong>
                    <span class="badge <?= $medico['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                        <?= ucfirst($medico['estado']) ?>
                    </span>
                </p>
            </div>

            <div class="dados-contato">
                <h3><i class="fas fa-address-book"></i> Contato</h3>
                <p><strong>Email:</strong> <?= htmlspecialchars($medico['email']) ?></p>
                <p><strong>Telefone:</strong> <?= htmlspecialchars($medico['telefone'] ?? 'Não informado') ?></p>
            </div>

            <div class="acoes">
                <?php if ($user['tipo_usuario'] === 'secretario'): ?>
                    <a href="editarMedico.php?id=<?= $medico['codigo'] ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <button onclick="confirmarExclusao(<?= $medico['codigo'] ?>)" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Excluir
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function confirmarExclusao(id) {
            if (confirm('Tem certeza que deseja inativar este médico?\nEsta ação não pode ser desfeita!')) {
                fetch('apagarMedicos.php?id=' + id, {
                    headers: {
                        'X-CSRF-Token': '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                    }
                }).then(response => {
                    window.location.href = 'listarMedicos.php';
                });
            }
        }
    </script>
</body>

</html>