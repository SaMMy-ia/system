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

    // Registrar sessão
    public function registarSessao($codigoUsuario, $tipoUsuario, $acao, $detalhes = null)
    {
        $sql = "INSERT INTO {$this->tabela} 
                (codigo_usuario, tipo_usuario, acao, detalhes) 
                VALUES (:codigoUsuario, :tipoUsuario, :acao, :detalhes)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigoUsuario', $codigoUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':tipoUsuario', $tipoUsuario);
        $stmt->bindParam(':acao', $acao);
        $stmt->bindParam(':detalhes', $detalhes);

        return $stmt->execute();
    }

    // Selecionar sessões por usuário
    public function selecionarSessoesPorUsuario($codigoUsuario, $tipoUsuario)
    {
        $sql = "SELECT * FROM {$this->tabela} 
                WHERE codigo_usuario = :codigoUsuario 
                  AND tipo_usuario = :tipoUsuario
                ORDER BY data_acao DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':codigoUsuario', $codigoUsuario, PDO::PARAM_INT);
        $stmt->bindParam(':tipoUsuario', $tipoUsuario);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Invalidar sessão (registrar logout)
    public function invalidarSessao($codigoUsuario, $tipoUsuario, $detalhes = null)
    {
        return $this->registarSessao($codigoUsuario, $tipoUsuario, "Logout", $detalhes);
    }
}
