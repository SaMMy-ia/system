<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // manter ON no dev
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
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
                return;
            }

            $tables = [
                ['table' => 'medicos', 'type' => 'medico', 'id_field' => 'codigo'],
                ['table' => 'pacientes', 'type' => 'paciente', 'id_field' => 'codigo'],
                ['table' => 'secretarios', 'type' => 'secretario', 'id_field' => 'codigo']
            ];

            $user = null;
            $user_type = null;

            foreach ($tables as $tableInfo) {
                $query = "SELECT {$tableInfo['id_field']} AS id,email,senha AS password,'{$tableInfo['type']}' AS tipo 
                          FROM {$tableInfo['table']} WHERE email=? LIMIT 1";
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

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado']);
                return;
            }

            // ✅ Verificação dupla (hash bcrypt ou texto plano)
            $senhaDB = $user['password'];
            $senhaOk = false;

            if (strlen($senhaDB) > 30 && password_verify($password, $senhaDB)) {
                $senhaOk = true;
            } elseif ($password === $senhaDB) {
                $senhaOk = true;
            }

            if (!$senhaOk) {
                echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
                return;
            }

            // Criar sessão
            $token = $this->sessaoDAO->criarSessao($user['id'], $user_type);
            if (!$token) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Falha ao criar sessão']);
                return;
            }

            // 🔹 secure => false em localhost
            $isLocal = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);
            setcookie('authToken', $token, [
                'expires' => time() + 8 * 3600,
                'path' => '/',
                'secure' => !$isLocal,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            echo json_encode([
                'success' => true,
                'user_type' => $user_type,
                'token' => $token
            ]);
        }

        public function logout()
        {
            $token = $this->getBearerToken();
            if (!$token) {
                echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
                return;
            }

            if ($this->sessaoDAO->encerrarSessao($token)) {
                setcookie('authToken', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Falha ao encerrar sessão']);
            }
        }

        public function validateToken()
        {
            $token = $this->getBearerToken();
            if (!$token) {
                echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
                return;
            }

            $user = $this->sessaoDAO->validarToken($token);
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado']);
                return;
            }

            echo json_encode([
                'success' => true,
                'user_type' => $user['tipo_usuario'],
                'user_id' => $user['codigo_usuario']
            ]);
        }

        private function getBearerToken()
        {
            $headers = getallheaders();
            if (isset($headers['Authorization']) && preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
            return $_REQUEST['token'] ?? null;
        }
    }

    $action = $_GET['action'] ?? '';
    $authController = new AuthController();

    switch ($action) {
        case 'login':
            $authController->login();
            break;
        case 'logout':
            $authController->logout();
            break;
        case 'validate':
            $authController->validateToken();
            break;
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Ação não encontrada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
