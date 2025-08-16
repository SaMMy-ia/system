<?php
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();

// Verificar permissões (médico ou secretário)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'medico' && $_SESSION['user_type'] !== 'secretario')) {
    header('Location: ../login.html');
    exit;
}

// Verificar ID do médico
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: listarMedicos.php');
    exit;
}

$medicoId = $_GET['id'];

// Buscar dados do médico
$db = new ConexaoBD();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM medicos WHERE codigo = ?");
$stmt->execute([$medicoId]);
$medico = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medico) {
    header('Location: listarMedicos.php?erro=medico_nao_encontrado');
    exit;
}

// Registrar ação na sessão
$sessaoDAO = new SessaoDAO();
$sessaoDAO->registarSessao(
    $_SESSION['user_id'],
    $_SESSION['user_type'],
    'Visualização do médico ID: ' . $medicoId
);
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
        <a href="listarMedico.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    
    <div class="main">
        <div class="card">
            <div class="dados-pessoais">
                <h3><i class="fas fa-id-card"></i> Dados Pessoais</h3>
                <p><strong>Nome Completo:</strong> <?= htmlspecialchars($medico['nome_completo']) ?></p>
                <p><strong>Data de Nascimento:</strong> <?= date('d/m/Y', strtotime($medico['data_nascimento'])) ?></p>
                <p><strong>Sexo:</strong> <?= htmlspecialchars($medico['sexo']) ?></p>
                <p><strong>Nível Acadêmico:</strong> <?= htmlspecialchars($medico['nivel_academico']) ?></p>
                <p><strong>Ano de Conclusão:</strong> <?= htmlspecialchars($medico['ano_de_conclusao']) ?></p>
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
                <p><strong>Telefone:</strong> <?= htmlspecialchars($medico['telefone']) ?></p>
            </div>
            
            <div class="acoes">
                <?php if ($_SESSION['user_type'] === 'secretario'): ?>
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
            if (confirm('Tem certeza que deseja excluir este médico?\nEsta ação não pode ser desfeita!')) {
                window.location.href = 'apagarMedico.php?id=' + id;
            }
        }
    </script>
</body>
</html>