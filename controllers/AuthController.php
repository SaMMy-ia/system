<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Remova em produção
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../database/conexaoBd.php';
    require_once __DIR__ . '/../models/SessaoDAO.php';

    class AuthController
    {
        private $conn;
        private $sessaoDAO;

        public function __construct()
        {
            $database = new ConexaoBd();
            $this->conn = $database->getConnection();
            $this->sessaoDAO = new SessaoDAO();
        }

        public function login()
        {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido', 405);
            }

            $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                throw new Exception('Email e senha são obrigatórios', 400);
            }

            // Tabelas que podem fazer login
            $tables = [
                ['table' => 'medicos', 'type' => 'medico', 'id_field' => 'codigo'],
                ['table' => 'pacientes', 'type' => 'paciente', 'id_field' => 'codigo'],
                ['table' => 'secretarios', 'type' => 'secretario', 'id_field' => 'codigo']
            ];

            $user = null;
            $user_type = null;

            foreach ($tables as $tableInfo) {
                $query = "SELECT {$tableInfo['id_field']} AS id, email, senha AS password, '{$tableInfo['type']}' AS tipo 
                          FROM {$tableInfo['table']} WHERE email = ? LIMIT 1";
                $stmt = $this->conn->prepare($query);

                if ($stmt->execute([$email])) {
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $user = $result;
                        $user_type = $tableInfo['type'];
                        break;
                    }
                }
            }

            // Se não achou o usuário
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                return;
            }

            // Verificação dupla: texto puro OU hash bcrypt
            if ($password !== $user['password'] && !password_verify($password, $user['password'])) {
                echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
                return;
            }

            // Inicia a sessão PHP
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user_type;

            // Registra na tabela de sessões
            $this->sessaoDAO->registarSessao(
                $user['id'],        // codigo_usuario
                $user_type,         // tipo_usuario
                'Login',            // ação
                'Usuário logou no sistema' // detalhes
            );

            echo json_encode([
                'success' => true,
                'user_type' => $user_type
            ]);
        }
    }

    if (isset($_GET['action'])) {
        $authController = new AuthController();

        switch ($_GET['action']) {
            case 'login':
                $authController->login();
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Parâmetro action não especificado']);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
