<?php
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';
require_once __DIR__ . '/../../controllers/auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Verificar autenticação e tipo de usuário
    $user = checkUserType(['secretario']);

    // Iniciar sessão para CSRF
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();

    // Buscar todos os secretários
    $stmt = $conn->prepare("SELECT * FROM secretarios ORDER BY nome_completo");
    $stmt->execute();
    $secretarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registrar ação na sessão
    $sessaoDAO = new SessaoDAO();
    $sessaoDAO->registarSessao(
        $user['codigo_usuario'],
        'secretario',
        'Visualizar Lista de Secretários',
        'Lista de secretários acessada'
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
    <title>Lista de Secretários - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-secret"></i> SumNanz - Lista de Secretários</h1>
        <h2>Total de secretários: <?= count($secretarios) ?></h2>
    </nav>

    <div class="sidebar">
        <a href="../dashboardSecretario.php"><i class="fas fa-arrow-left"></i> Voltar</a>
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

        <table id="tabelaSecretarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Completo</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($secretarios as $secretario): ?>
                    <tr>
                        <td><?= htmlspecialchars($secretario['codigo']) ?></td>
                        <td><?= htmlspecialchars($secretario['nome_completo']) ?></td>
                        <td><?= htmlspecialchars($secretario['telefone'] ?? 'Não informado') ?></td>
                        <td><?= htmlspecialchars($secretario['email']) ?></td>
                        <td>
                            <span class="badge <?= $secretario['estado'] === 'activo' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($secretario['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="visualizarSecretario.php?id=<?= $secretario['codigo'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <a href="editarSecretario.php?id=<?= $secretario['codigo'] ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Editar
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
            const linhas = document.querySelectorAll('#tabelaSecretarios tbody tr');

            linhas.forEach(linha => {
                const nome = linha.cells[1].textContent.toLowerCase();
                const estado = linha.cells[4].textContent.toLowerCase();

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