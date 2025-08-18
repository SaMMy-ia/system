<?php
require_once __DIR__ . '/../database/ConexaoBd.php';

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
        try {
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
        } catch (Exception $e) {
            error_log("Erro ao criar sessão: " . $e->getMessage());
            return false;
        }
    }

    public function validarToken($token)
    {
        try {
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
        } catch (Exception $e) {
            error_log("Erro ao validar token: " . $e->getMessage());
            return false;
        }
    }

    public function encerrarSessao($token)
    {
        try {
            $sql = "UPDATE {$this->tabela} 
                    SET is_active = FALSE, acao = 'Logout', expires_at = NOW()
                    WHERE token = :token";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':token', $token);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao encerrar sessão: " . $e->getMessage());
            return false;
        }
    }

    public function registarSessao($codigoUsuario, $tipoUsuario, $acao, $detalhes)
    {
        try {
            $sql = "INSERT INTO {$this->tabela} 
                    (codigo_usuario, tipo_usuario, acao, detalhes, token, expires_at, is_active) 
                    VALUES (:codigoUsuario, :tipoUsuario, :acao, :detalhes, :token, :expiresAt, TRUE)";

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+8 hours'));

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':codigoUsuario', $codigoUsuario, PDO::PARAM_INT);
            $stmt->bindParam(':tipoUsuario', $tipoUsuario);
            $stmt->bindParam(':acao', $acao);
            $stmt->bindParam(':detalhes', $detalhes);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expiresAt', $expiresAt);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao registrar sessão: " . $e->getMessage());
            return false;
        }
    }
}