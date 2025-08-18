<?php
session_start();
require_once __DIR__ . '/../../database/ConexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

$token = $_COOKIE['authToken'] ?? null;
$sessaoDAO = new SessaoDAO();
$user = $token ? $sessaoDAO->validarToken($token) : null;

if (!$user || $user['tipo_usuario'] !== 'medico') {
    header('Location: ../login.html?error=access_denied');
    exit;
}

$_SESSION['user_id'] = $user['codigo_usuario'];
$_SESSION['user_type'] = $user['tipo_usuario'];
$_SESSION['nome'] = $user['nome_completo'] ?? 'Médico';

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
        <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['nome']) ?>!</h2>
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
        document.addEventListener('DOMContentLoaded', () => {
            carregarConsultas();
            carregarResumo();
        });

        function carregarConsultas() {
            const loading = document.getElementById('loadingConsultas');
            const tbody = document.querySelector('#tabelaConsultas tbody');
            loading.style.display = 'block';
            tbody.innerHTML = '';

            fetch('../../controllers/MedicoController.php?action=listar_consultas', {
                headers: {
                    'Authorization': 'Bearer <?= htmlspecialchars($_SESSION['csrf_token']) ?>',
                    'X-CSRF-Token': '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Erro na rede');
                return res.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erro ao carregar consultas');
                }
                if (data.consultas.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Nenhuma consulta encontrada</td></tr>';
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
                alert('Erro ao carregar consultas: ' + err.message);
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">Erro ao carregar consultas</td></tr>';
            })
            .finally(() => {
                loading.style.display = 'none';
            });
        }

        function carregarResumo() {
            fetch('../../controllers/MedicoController.php?action=proxima_consulta', {
                headers: {
                    'Authorization': 'Bearer <?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                }
            })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    const proxima = document.getElementById('proximaConsulta');
                    if (data.success && data.consulta) {
                        proxima.innerHTML = `${formatarData(data.consulta.data_consulta)} às ${data.consulta.hora_consulta}<br><small>${data.consulta.nome_paciente} - ${data.consulta.tipo_consulta}</small>`;
                    } else {
                        proxima.textContent = 'Nenhuma consulta futura';
                    }
                })
                .catch(err => {
                    document.getElementById('proximaConsulta').textContent = 'Erro ao carregar';
                });

            fetch('../../controllers/MedicoController.php?action=consultas_pendentes', {
                headers: {
                    'Authorization': 'Bearer <?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                }
            })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('consultasPendentes').textContent = data.success ? data.total : '0';
                })
                .catch(err => {
                    document.getElementById('consultasPendentes').textContent = 'Erro';
                });

            fetch('../../controllers/MedicoController.php?action=consultas_hoje', {
                headers: {
                    'Authorization': 'Bearer <?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                }
            })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('consultasHoje').textContent = data.success ? data.total : '0';
                })
                .catch(err => {
                    document.getElementById('consultasHoje').textContent = 'Erro';
                });
        }

        function formatarData(dataString) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return new Date(dataString).toLocaleDateString('pt-BR', options);
        }

        function getStatusClass(estado) {
            switch (estado) {
                case 'Agendada': return 'badge-warning';
                case 'Confirmada': return 'badge-success';
                case 'Cancelada': return 'badge-danger';
                case 'Realizada': return 'badge-info';
                default: return '';
            }
        }

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

        span.onclick = closeBtn.onclick = () => {
            modal.style.display = 'none';
        };

        window.onclick = (event) => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };

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
                    'Authorization': 'Bearer <?= htmlspecialchars($_SESSION['csrf_token']) ?>',
                    'X-CSRF-Token': '<?= htmlspecialchars($_SESSION['csrf_token']) ?>'
                },
                body: JSON.stringify({ action: 'cancelar_consulta', consulta_id: id })
            })
            .then(res => {
                if (!res.ok) throw new Error('Erro na rede');
                return res.json();
            })
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Erro ao cancelar consulta');
                modal.style.display = 'none';
                alert('Consulta cancelada com sucesso!');
                carregarConsultas();
                carregarResumo();
            })
            .catch(err => {
                alert('Erro ao cancelar consulta: ' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        setInterval(() => {
            carregarConsultas();
            carregarResumo();
        }, 600000); // 10 minutos
    </script>
</body>
</html>