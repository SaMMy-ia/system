<?php
session_start();
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

// Verificar token em vez de sessão
$token = $_COOKIE['authToken'] ?? null;
$sessaoDAO = new SessaoDAO();
$user = $token ? $sessaoDAO->validarToken($token) : null;

if (!$user || $user['tipo_usuario'] !== 'paciente') {
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
    <title>Dashboard Paciente - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-injured"></i> SumNanz - Painel do Paciente</h1>
        <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? 'Paciente') ?>!</h2>
    </nav>
    <div class="sidebar">
        <a href="#" class="active"><i class="fas fa-home"></i> Minha Página</a>
        <a href="#" onclick="carregarConsultas()"><i class="fas fa-calendar-alt"></i> Minhas Consultas</a>
        <a href="#" onclick="document.getElementById('formAgendar').scrollIntoView({behavior: 'smooth'})"><i class="fas fa-plus-circle"></i> Agendar Consulta</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    <div class="main">
        <h2><i class="fas fa-plus-circle"></i> Agendar Nova Consulta</h2>
        <form id="formAgendar">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="medico_id"><i class="fas fa-user-md"></i> Médico:</label>
                <select name="medico_id" id="medico_id" required>
                    <option value="">Selecione um médico</option>
                </select>
            </div>

            <div class="form-group">
                <label for="data_consulta"><i class="fas fa-calendar-day"></i> Data:</label>
                <input type="date" name="data_consulta" id="data_consulta" required min="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-group">
                <label for="hora_consulta"><i class="fas fa-clock"></i> Hora:</label>
                <input type="time" name="hora_consulta" id="hora_consulta" required min="08:00" max="18:00">
            </div>

            <div class="form-group">
                <label for="tipo_consulta"><i class="fas fa-stethoscope"></i> Tipo de Consulta:</label>
                <select name="tipo_consulta" id="tipo_consulta" required>
                    <option value="">Selecione o tipo</option>
                    <option value="Consulta Geral">Consulta Geral</option>
                    <option value="Consulta Especializada">Consulta Especializada</option>
                    <option value="Retorno">Retorno</option>
                    <option value="Exame">Exame</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" id="btnAgendar">
                <i class="fas fa-calendar-plus"></i> Marcar Consulta
            </button>
        </form>

        <h2><i class="fas fa-calendar-alt"></i> Minhas Consultas</h2>
        <div id="loadingConsultas" style="display: none; padding: 20px; text-align: center;">
            <div class="loading"></div> Carregando consultas...
        </div>
        <table id="tabelaConsultas">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Médico</th>
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
            <h3><i class="fas fa-exclamation-triangle"></i> Cancelar Consulta</h3>
            <p id="modalText">Temទ11>Tem certeza que deseja cancelar esta consulta?</p>
            <input type="hidden" id="consultaId">
            <button id="confirmCancel" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Confirmar Cancelamento</button>
            <button id="closeModal" class="btn"><i class="fas fa-times"></i> Fechar</button>
        </div>
    </div>

    <script>
        // Carregar médicos e consultas ao abrir a página
        document.addEventListener('DOMContentLoaded', () => {
            carregarMedicos();
            carregarConsultas();
        });

        // Função para carregar médicos
        function carregarMedicos() {
            fetch('../../controllers/ConsultaController.php?action=list_medicos', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar médicos');

                    const select = document.getElementById('medico_id');
                    select.innerHTML = '<option value="">Selecione um médico</option>';

                    data.medicos.forEach(medico => {
                        const option = document.createElement('option');
                        option.value = medico.codigo;
                        option.textContent = `${medico.nome_completo} (${medico.especialidade})`;
                        select.appendChild(option);
                    });
                })
                .catch(err => {
                    console.error('Erro ao carregar médicos:', err);
                    alert('Erro ao carregar médicos. Por favor, recarregue a página.');
                });
        }

        // Função para carregar consultas
        function carregarConsultas() {
            const loading = document.getElementById('loadingConsultas');
            const tbody = document.querySelector('#tabelaConsultas tbody');

            loading.style.display = 'block';
            tbody.innerHTML = '';

            fetch('../../controllers/ConsultaController.php?action=read', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar consultas');

                    if (data.consultas.length === 0) {
                        tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhuma consulta agendada</td>
                        </tr>
                    `;
                        return;
                    }

                    data.consultas.forEach(c => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                        <td>${formatarData(c.data_consulta)}</td>
                        <td>${c.hora_consulta}</td>
                        <td>${c.nome_medico}</td>
                        <td>${c.tipo_consulta}</td>
                        <td><span class="badge ${getStatusClass(c.estado)}">${c.estado}</span></td>
                        <td>
                            <button class="btn btn-danger" onclick="abrirModalCancelamento(${c.codigo}, '${c.nome_medico}', '${c.data_consulta}', '${c.hora_consulta}')">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            ${c.estado === 'Agendada' ? `
                            <button class="btn btn-info" onclick="reagendarConsulta(${c.codigo})">
                                <i class="fas fa-calendar-alt"></i> Reagendar
                            </button>` : ''}
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
                            Erro ao carregar consultas: ${err.message}
                        </td>
                    </tr>
                `;
                })
                .finally(() => {
                    loading.style.display = 'none';
                });
        }

        // Função para marcar consulta
        document.getElementById('formAgendar').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const btn = document.getElementById('btnAgendar');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Agendando...';

            fetch('../../controllers/ConsultaController.php?action=create', {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao marcar consulta');

                    alert('Consulta marcada com sucesso!');
                    this.reset();
                    carregarConsultas();
                })
                .catch(err => {
                    console.error('Erro ao marcar consulta:', err);
                    alert(err.message || 'Erro ao marcar consulta. Tente novamente.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Função para reagendar consulta
        function reagendarConsulta(consultaId) {
            // Preenche o formulário com os dados da consulta para reagendamento
            fetch(`../../controllers/ConsultaController.php?action=get_consulta&id=${consultaId}`, {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar consulta');

                    document.getElementById('medico_id').value = data.consulta.medico_id;
                    document.getElementById('data_consulta').value = data.consulta.data_consulta;
                    document.getElementById('hora_consulta').value = data.consulta.hora_consulta;
                    document.getElementById('tipo_consulta').value = data.consulta.tipo_consulta;
                    document.getElementById('formAgendar').scrollIntoView({
                        behavior: 'smooth'
                    });

                    // Modifica o formulário para reagendamento
                    const form = document.getElementById('formAgendar');
                    form.action = `../../controllers/ConsultaController.php?action=update&id=${consultaId}`;
                })
                .catch(err => {
                    console.error('Erro ao carregar consulta para reagendamento:', err);
                    alert('Erro ao carregar consulta. Tente novamente.');
                });
        }

        // Funções auxiliares
        function formatarData(dataString) {
            const options = {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
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

        function abrirModalCancelamento(id, medico, data, hora) {
            document.getElementById('consultaId').value = id;
            document.getElementById('modalText').innerHTML = `
                Tem certeza que deseja cancelar sua consulta com <strong>${medico}</strong>
                marcada para <strong>${formatarData(data)} às ${hora}</strong>?
                <br><br>
                <small>Cancelamentos com menos de 24h de antecedência podem estar sujeitos a taxas.</small>
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

            const formData = new FormData();
            formData.append('consulta_id', id);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

            fetch('../../controllers/ConsultaController.php?action=cancel', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao cancelar consulta');

                    alert('Consulta cancelada com sucesso!');
                    modal.style.display = 'none';
                    carregarConsultas();
                })
                .catch(err => {
                    console.error('Erro ao cancelar consulta:', err);
                    alert(err.message || 'Erro ao cancelar consulta. Tente novamente.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });
    </script>
</body>

</html>