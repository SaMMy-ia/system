<?php
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

session_start();

// Verificar permissões (apenas secretário)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'secretario') {
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

// Processar formulário se for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF inválido');
    }

    // Coletar e validar dados
    $dados = [
        'nome_completo' => filter_input(INPUT_POST, 'nome_completo'),
        'especialidade' => filter_input(INPUT_POST, 'especialidade'),
        'numero_da_cedula_profissional' => filter_input(INPUT_POST, 'cedula'),
        'telefone' => filter_input(INPUT_POST, 'telefone'),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL),
        'data_nascimento' => filter_input(INPUT_POST, 'data_nascimento'),
        'sexo' => filter_input(INPUT_POST, 'sexo'),
        'ano_de_conclusao' => filter_input(INPUT_POST, 'ano_conclusao'),
        'nivel_academico' => filter_input(INPUT_POST, 'nivel_academico'),
        'estado' => filter_input(INPUT_POST, 'estado')
    ];

    // Atualizar no banco
    $stmt = $conn->prepare("UPDATE medicos SET 
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
        WHERE codigo = :codigo");

    $dados['codigo'] = $medicoId;
    $stmt->execute($dados);

    // Registrar ação
    $sessaoDAO = new SessaoDAO();
    $sessaoDAO->registarSessao(
        $_SESSION['user_id'],
        $_SESSION['user_type'],
        'Atualização do médico ID: ' . $medicoId
    );

    header('Location: visualizarMedico.php?id=' . $medicoId . '&sucesso=1');
    exit;
}

// Registrar ação de visualização
$sessaoDAO = new SessaoDAO();
$sessaoDAO->registarSessao(
    $_SESSION['user_id'],
    $_SESSION['user_type'],
    'Acesso ao formulário de edição do médico ID: ' . $medicoId
);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Médico - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Editar Médico</h1>
        <h2><?= htmlspecialchars($medico['nome_completo']) ?></h2>
    </nav>
    
    <div class="sidebar">
        <a href="listarMedicos.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    
    <div class="main">
        <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Médico atualizado com sucesso!
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
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
                    <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($medico['data_nascimento']) ?>">
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
                    <input type="text" id="nivel_academico" name="nivel_academico" value="<?= htmlspecialchars($medico['nivel_academico']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="ano_conclusao"><i class="fas fa-calendar-alt"></i> Ano de Conclusão</label>
                    <input type="text" id="ano_conclusao" name="ano_conclusao" value="<?= htmlspecialchars($medico['ano_de_conclusao']) ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="telefone"><i class="fas fa-phone"></i> Telefone</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= htmlspecialchars($medico['telefone']) ?>">
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