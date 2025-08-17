<?php
session_start();
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

// Verificar token em vez de sessão
$token = $_COOKIE['authToken'] ?? null;
$sessaoDAO = new SessaoDAO();
$user = $token ? $sessaoDAO->validarToken($token) : null;

if (!$user || $user['tipo_usuario'] !== 'medico') {
    header('Location: ../login.html');
    exit;
}

// Definir variáveis de sessão para uso posterior
$_SESSION['user_id'] = $user['codigo_usuario'];
$_SESSION['user_type'] = $user['tipo_usuario'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Médico - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-md"></i> SumNanz - Painel do Médico</h1>
        <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? 'Médico') ?>!</h2>
    </nav>
    <div class="sidebar">
        <a href="#" class="active"><i class="fas fa-home"></i> Home</a>
        <a href="#" onclick="carregarConsultas()"><i class="fas fa-calendar-alt"></i> Minhas Consultas</a>
        <a href="#"><i class="fas fa-cog"></i> Configurações</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    <div class="main">
        <h2><i class="fas fa-tachometer-alt"></i> Visão Geral</h2>
        <div class="cards">
            <div class="card">
                <h3><i class="fas fa-calendar-check"></i> Próxima Consulta</h3>
                <div class="value" id="proximaConsulta">Carregando...</div>
                <p>Próximo compromisso agendado</p>
            </div>
            <div class="card">
                <h3><i class="fas fa-clock"></i> Consultas Pendentes</h3>
                <div class="value" id="consultasPendentes">0</div>
                <p>Consultas aguardando confirmação</p>
            </div>
            <div class="card">
                <h3><i class="fas fa-calendar-day"></i> Consultas Hoje</h3>
                <div class="value" id="consultasHoje">0</div>
                <p>Consultas para hoje</p>
            </div>
        </div>

        <h2><i class="fas fa-calendar-alt"></i> Minhas Consultas</h2>
        <div id="loadingConsultas" style="display: none; padding: 20px; text-align: center;">
            <div class="loading"></div> Carregando consultas...
        </div>
        <table id="tabelaConsultas">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="6" style="text-align: center;">Nenhuma consulta encontrada</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal para Cancelar Consulta -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Cancelar Consulta</h3>
            <p id="modalText">Tem certeza que deseja cancelar esta consulta?</p>
            <input type="hidden" id="consultaId">
            <button id="confirmCancel" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Confirmar Cancelamento</button>
            <button id="closeModal" class="btn"><i class="fas fa-times"></i> Fechar</button>
        </div>
    </div>

    <script>
        // Carregar dados ao abrir a página
        document.addEventListener('DOMContentLoaded', () => {
            carregarConsultas();
            carregarResumo();
        });

        // Função para carregar consultas
        function carregarConsultas() {
            const loading = document.getElementById('loadingConsultas');
            const tbody = document.querySelector('#tabelaConsultas tbody');

            loading.style.display = 'block';
            tbody.innerHTML = '';

            fetch('../../controllers/MedicoController.php?action=listar_consultas', {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) {
                        console.error('Erro na resposta:', res.status, res.statusText);
                        return res.json().then(err => {
                            throw err;
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('Dados recebidos:', data);

                    if (!data.success) {
                        throw new Error(data.message || 'Erro ao carregar consultas');
                    }

                    if (data.consultas.length === 0) {
                        tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhuma consulta encontrada</td>
                        </tr>
                    `;
                        return;
                    }

                    data.consultas.forEach(c => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                        <td>${formatarData(c.data_consulta)}</td>
                        <td>${c.hora_consulta}</td>
                        <td>${c.nome_paciente}</td>
                        <td>${c.tipo_consulta}</td>
                        <td><span class="badge ${getStatusClass(c.estado)}">${c.estado}</span></td>
                        <td>
                            ${c.estado === 'Agendada' || c.estado === 'Confirmada' ? `
                            <button class="btn btn-danger" onclick="abrirModalCancelamento(${c.codigo}, '${c.nome_paciente}', '${c.data_consulta}', '${c.hora_consulta}')">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            ` : ''}
                        </td>
                    `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(err => {
                    console.error('Erro ao carregar consultas:', err);
                    tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--danger-color);">
                            Erro ao carregar consultas: ${err.message || 'Erro desconhecido'}
                        </td>
                    </tr>
                `;
                })
                .finally(() => {
                    loading.style.display = 'none';
                });
        }

        // Função para carregar resumo
        function carregarResumo() {
            // Próxima consulta
            fetch('../../controllers/MedicoController.php?action=proxima_consulta')
                .then(res => res.json())
                .then(data => {
                    const proxima = document.getElementById('proximaConsulta');
                    if (data.success && data.consulta) {
                        proxima.textContent = `${formatarData(data.consulta.data_consulta)} às ${data.consulta.hora_consulta}`;
                        proxima.innerHTML += `<br><small>${data.consulta.nome_paciente} - ${data.consulta.tipo_consulta}</small>`;
                    } else {
                        proxima.textContent = data.message || 'Nenhuma consulta futura';
                    }
                })
                .catch(err => {
                    console.error('Erro ao carregar próxima consulta:', err);
                    document.getElementById('proximaConsulta').textContent = 'Erro ao carregar';
                });

            // Consultas pendentes
            fetch('../../controllers/MedicoController.php?action=consultas_pendentes')
                .then(res => res.json())
                .then(data => {
                    const pendentes = document.getElementById('consultasPendentes');
                    pendentes.textContent = data.success ? data.total : '0';
                })
                .catch(err => {
                    console.error('Erro ao carregar pendentes:', err);
                    document.getElementById('consultasPendentes').textContent = '0';
                });

            // Consultas de hoje
            fetch('../../controllers/MedicoController.php?action=consultas_hoje')
                .then(res => res.json())
                .then(data => {
                    const hoje = document.getElementById('consultasHoje');
                    hoje.textContent = data.success ? data.total : '0';
                })
                .catch(err => {
                    console.error('Erro ao carregar hoje:', err);
                    document.getElementById('consultasHoje').textContent = '0';
                });
        }

        // Funções auxiliares
        function formatarData(dataString) {
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            return new Date(dataString).toLocaleDateString('pt-BR', options);
        }

        function getStatusClass(estado) {
            switch (estado) {
                case 'Agendada':
                    return 'badge-warning';
                case 'Confirmada':
                    return 'badge-success';
                case 'Cancelada':
                    return 'badge-danger';
                case 'Realizada':
                    return 'badge-info';
                default:
                    return '';
            }
        }

        // Modal de cancelamento
        const modal = document.getElementById('cancelModal');
        const span = document.getElementsByClassName('close')[0];
        const closeBtn = document.getElementById('closeModal');

        function abrirModalCancelamento(id, paciente, data, hora) {
            document.getElementById('consultaId').value = id;
            document.getElementById('modalText').innerHTML = `
                Tem certeza que deseja cancelar a consulta de <strong>${paciente}</strong>
                marcada para <strong>${formatarData(data)} às ${hora}</strong>?
            `;
            modal.style.display = 'block';
        }

        span.onclick = function() {
            modal.style.display = 'none';
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Confirmar cancelamento
        document.getElementById('confirmCancel').addEventListener('click', function() {
            const id = document.getElementById('consultaId').value;
            const btn = this;
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Cancelando...';

            fetch('../../controllers/MedicoController.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                    },
                    body: JSON.stringify({
                        action: 'cancelar_consulta',
                        consulta_id: id
                    })
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Erro ao cancelar consulta');

                    modal.style.display = 'none';
                    alert('Consulta cancelada com sucesso!');
                    carregarConsultas();
                    carregarResumo();
                })
                .catch(err => {
                    console.error('Erro ao cancelar consulta:', err);
                    alert('Erro ao cancelar consulta: ' + err.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Atualizar a cada 5 minutos
        setInterval(() => {
            carregarConsultas();
            carregarResumo();
        }, 300000);
    </script>
</body>

</html>