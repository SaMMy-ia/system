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
                throw new Exception('Método não permitido', 405);
            }

            $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                throw new Exception('Email e senha são obrigatórios', 400);
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
                throw new Exception('Usuário não encontrado', 404);
            }

            // ✅ Verificação dupla
            $senhaDB = $user['password'];
            $senhaOk = false;

            // Se for hash (bcrypt começa com $2y$...)
            if (strlen($senhaDB) > 30 && password_verify($password, $senhaDB)) {
                $senhaOk = true;
            }
            // Se estiver em texto plano
            elseif ($password === $senhaDB) {
                $senhaOk = true;
            }

            if (!$senhaOk) {
                throw new Exception('Senha incorreta', 401);
            }

            // Criar sessão
            $token = $this->sessaoDAO->criarSessao($user['id'], $user_type);
            if (!$token) {
                throw new Exception('Falha ao criar sessão', 500);
            }

            // 🔹 secure => false em localhost
            $isLocal = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']);
            setcookie('authToken', $token, [
                'expires' => time() + 8 * 3600,
                'path' => '/',
                'secure' => !$isLocal, // HTTPS só fora do localhost
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
                throw new Exception('Token não fornecido', 401);
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
                throw new Exception('Falha ao encerrar sessão', 500);
            }
        }

        public function validateToken()
        {
            $token = $this->getBearerToken();
            if (!$token) {
                throw new Exception('Token não fornecido', 401);
            }

            $user = $this->sessaoDAO->validarToken($token);
            if (!$user) {
                throw new Exception('Token inválido ou expirado', 401);
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
            echo json_encode(['error' => 'Ação não encontrada']);
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
