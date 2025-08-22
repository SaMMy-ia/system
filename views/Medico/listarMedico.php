<?php
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: text/html; charset=utf-8');

try {
    // Verificar autenticação e tipo de usuário
    $user = checkUserType(['secretario', 'medico']);

    // Iniciar sessão para CSRF
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $db = new ConexaoBd();
    $conn = $db->getConnection();

    // Buscar todos os médicos
    $stmt = $conn->prepare("SELECT * FROM medicos ORDER BY nome_completo");
    $stmt->execute();
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registrar ação na sessão
    $sessaoDAO = new SessaoDAO();
    $sessaoDAO->registarSessao(
        $user['codigo_usuario'],
        $user['tipo_usuario'],
        'Visualizar Lista de Médicos',
        'Lista de médicos acessada'
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
    <title>Lista de Médicos - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
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

        .filtros {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filtros input,
        .filtros select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filtros input:focus,
        .filtros select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        tr:hover {
            background-color: rgba(26, 115, 232, 0.03);
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
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

        .especialidade-badge {
            background-color: rgba(26, 115, 232, 0.1);
            color: var(--primary-color);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            display: inline-block;
            margin-top: 0.3rem;
        }

        .btn {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-info {
            background-color: rgba(23, 162, 184, 0.15);
            color: var(--info-color);
        }

        .btn-info:hover {
            background-color: rgba(23, 162, 184, 0.25);
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
            }

            .filtros {
                flex-direction: column;
            }

            table {
                display: block;
                overflow-x: auto;
            }
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
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_GET['success'] === 'updated' ? 'Médico atualizado com sucesso!' : 'Operação realizada com sucesso!' ?>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_GET['error'] === 'medico_not_found' ? 'Médico não encontrado!' : ($_GET['error'] === 'medico_has_appointments' ? 'Não é possível inativar: médico possui consultas agendadas!' : ($_GET['error'] === 'invalid_id' ? 'ID inválido!' : 'Erro no banco de dados!')) ?>
            </div>
        <?php endif; ?>

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
                        <td><?= htmlspecialchars($medico['telefone'] ?? 'Não informado') ?></td>
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
                            <?php if ($user['tipo_usuario'] === 'secretario'): ?>
                                <a href="editarMedico.php?id=<?= $medico['codigo'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <a href="ApagarMedico.php?id=<?= $medico['codigo'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja inativar este médico?')">
                                    <i class="fas fa-trash"></i> Inativar
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
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