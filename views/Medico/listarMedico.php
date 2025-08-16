<?php
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();

// Verificar se o usuário está logado e tem permissão (secretário ou admin)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'secretario' && $_SESSION['user_type'] !== 'medico')) {
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

// Buscar todos os médicos
$stmt = $conn->prepare("SELECT * FROM medicos ORDER BY nome_completo");
$stmt->execute();
$medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Registrar ação na sessão
$sessaoDAO = new SessaoDAO();
$sessaoDAO->registarSessao(
    $_SESSION['user_id'],
    $_SESSION['user_type'],
    'Visualização da lista de médicos'
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Médicos - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .especialidade-badge {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Lista de Médicos</h1>
        <h2>Total de médicos: <?= count($medicos) ?></h2>
    </nav>
    
    <div class="sidebar">
        <a href="../Secretario/dashboardSecretario.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    
    <div class="main">
        <div class="filtros">
            <input type="text" id="filtroNome" placeholder="Filtrar por nome..." onkeyup="filtrarTabela()">
            <select id="filtroEspecialidade" onchange="filtrarTabela()">
                <option value="">Todas especialidades</option>
                <?php
                $especialidades = array_unique(array_column($medicos, 'especialidade'));
                foreach ($especialidades as $especialidade):
                ?>
                <option value="<?= htmlspecialchars($especialidade) ?>"><?= htmlspecialchars($especialidade) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtroEstado" onchange="filtrarTabela()">
                <option value="">Todos os estados</option>
                <option value="activo">Ativo</option>
                <option value="inactivo">Inativo</option>
            </select>
        </div>
        
        <table id="tabelaMedicos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Completo</th>
                    <th>Especialidade</th>
                    <th>Cédula Prof.</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicos as $medico): ?>
                <tr>
                    <td><?= htmlspecialchars($medico['codigo']) ?></td>
                    <td>
                        <?= htmlspecialchars($medico['nome_completo']) ?>
                        <div class="especialidade-badge"><?= htmlspecialchars($medico['especialidade']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($medico['especialidade']) ?></td>
                    <td><?= htmlspecialchars($medico['numero_da_cedula_profissional']) ?></td>
                    <td><?= htmlspecialchars($medico['telefone']) ?></td>
                    <td><?= htmlspecialchars($medico['email']) ?></td>
                    <td>
                        <span class="badge <?= $medico['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($medico['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="visualizarMedico.php?id=<?= $medico['codigo'] ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                        <?php if ($_SESSION['user_type'] === 'secretario'): ?>
                        <a href="editarMedico.php?id=<?= $medico['codigo'] ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        //Pesquisar como funciona esse filtro
        function filtrarTabela() {
            const inputNome = document.getElementById('filtroNome').value.toLowerCase();
            const filtroEspecialidade = document.getElementById('filtroEspecialidade').value.toLowerCase();
            const filtroEstado = document.getElementById('filtroEstado').value;
            const linhas = document.querySelectorAll('#tabelaMedicos tbody tr');
            
            linhas.forEach(linha => {
                const nome = linha.cells[1].textContent.toLowerCase();
                const especialidade = linha.cells[2].textContent.toLowerCase();
                const estado = linha.cells[6].textContent.toLowerCase();
                
                const nomeMatch = nome.includes(inputNome);
                const especialidadeMatch = filtroEspecialidade === '' || especialidade.includes(filtroEspecialidade);
                const estadoMatch = filtroEstado === '' || estado.includes(filtroEstado);
                
                if (nomeMatch && especialidadeMatch && estadoMatch) {
                    linha.style.display = '';
                } else {
                    linha.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>