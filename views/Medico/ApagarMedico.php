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

// Verificar se o médico existe
$db = new ConexaoBD();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM medicos WHERE codigo = ?");
$stmt->execute([$medicoId]);
$medico = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medico) {
    header('Location: listarMedicos.php?erro=medico_nao_encontrado');
    exit;
}

// Verificar se o médico tem consultas agendadas
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM consultas WHERE codigo_medico = ? AND estado = 'Agendada'");
$stmt->execute([$medicoId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['total'] > 0) {
    header('Location: visualizarMedico.php?id=' . $medicoId . '&erro=medico_com_consultas');
    exit;
}

// Realizar a exclusão (mudar status para inativo)
$stmt = $conn->prepare("UPDATE medicos SET estado = 'inactivo' WHERE codigo = ?");
$stmt->execute([$medicoId]);

// Registrar ação
$sessaoDAO = new SessaoDAO();
$sessaoDAO->criarSessao(
    $_SESSION['user_id'],
    $_SESSION['user_type'],
    'Exclusão (inativação) do médico ID: ' . $medicoId
);

header('Location: listarMedicos.php?sucesso=medico_excluido');
exit;
?>