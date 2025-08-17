<?php
require_once __DIR__ . '/../database/conexaoBd.php';

class SessaoDAO
{
    private $tabela = 'sessao';
    private $conn;

    public function __construct()
    {
        $database = new ConexaoBd();
        $this->conn = $database->getConnection();
    }

    public function criarSessao($codigoUsuario, $tipoUsuario)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));
        
        $sql = "INSERT INTO {$this->tabela} 
                (codigo_usuario, tipo_usuario, acao, token, expires_at) 
                VALUES (:codigoUsuario, :tipoUsuario, 'Login', :token, :expiresAt)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigoUsuario', $codigoUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':tipoUsuario', $tipoUsuario);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expiresAt', $expiresAt);

        return $stmt->execute() ? $token : false;
    }

    public function validarToken($token)
    {
        $sql = "SELECT codigo_usuario, tipo_usuario 
                FROM {$this->tabela} 
                WHERE token = :token 
                AND is_active = TRUE 
                AND expires_at > NOW() 
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function encerrarSessao($token)
    {
        $sql = "UPDATE {$this->tabela} 
                SET is_active = FALSE, acao = 'Logout', expires_at = NOW()
                WHERE token = :token";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        
        return $stmt->execute();
    }
}