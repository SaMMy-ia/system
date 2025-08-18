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
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Visualizar Médico</h1>
        <h2><?= htmlspecialchars($medico['nome_completo']) ?></h2>
    </nav>
    
    <div class="sidebar">
        <a href="listarMedicos.php"><i class="fas fa-arrow-left"></i> Voltar</a>
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