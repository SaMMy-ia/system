<?php
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();

// Verificar se o usuário está logado e tem permissão (médico ou admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'medico' && $_SESSION['user_type'] !== 'secretario')) {
    header('Location: ../login.html');
    exit;
}

// Gerar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Conexão com o banco
$db = new ConexaoBD();
$conn = $db->getConnection();

// Buscar todos os pacientes
$stmt = $conn->prepare("SELECT * FROM pacientes ORDER BY nome_completo");
$stmt->execute();
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Registrar ação na sessão
$sessaoDAO = new SessaoDAO();
$sessaoDAO->registarSessao(
    $_SESSION['user_id'],
    $_SESSION['user_type'],
    'Visualização da lista de pacientes'
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Pacientes - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <nav>
        <h1><i class="fas fa-users"></i> SumNanz - Lista de Pacientes</h1>
        <h2>Total de pacientes: <?= count($pacientes) ?></h2>
    </nav>
    
    <div class="sidebar">
        <a href="../dashboardMedico.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    
    <div class="main">
        <div class="filtros">
            <input type="text" id="filtroNome" placeholder="Filtrar por nome..." onkeyup="filtrarTabela()">
            <select id="filtroEstado" onchange="filtrarTabela()">
                <option value="">Todos os estados</option>
                <option value="activo">Ativo</option>
                <option value="inactivo">Inativo</option>
            </select>
        </div>
        
        <table id="tabelaPacientes">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Completo</th>
                    <th>Data Nasc.</th>
                    <th>Sexo</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $paciente): ?>
                <tr>
                    <td><?= htmlspecialchars($paciente['codigo']) ?></td>
                    <td><?= htmlspecialchars($paciente['nome_completo']) ?></td>
                    <td><?= date('d/m/Y', strtotime($paciente['data_nascimento'])) ?></td>
                    <td><?= htmlspecialchars($paciente['sexo']) ?></td>
                    <td><?= htmlspecialchars($paciente['telefone']) ?></td>
                    <td><?= htmlspecialchars($paciente['email']) ?></td>
                    <td>
                        <span class="badge <?= $paciente['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($paciente['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="visualizarPaciente.php?id=<?= $paciente['codigo'] ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function filtrarTabela() {
            const inputNome = document.getElementById('filtroNome').value.toLowerCase();
            const filtroEstado = document.getElementById('filtroEstado').value;
            const linhas = document.querySelectorAll('#tabelaPacientes tbody tr');
            
            linhas.forEach(linha => {
                const nome = linha.cells[1].textContent.toLowerCase();
                const estado = linha.cells[6].textContent.toLowerCase();
                
                const nomeMatch = nome.includes(inputNome);
                const estadoMatch = filtroEstado === '' || estado.includes(filtroEstado);
                
                if (nomeMatch && estadoMatch) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>