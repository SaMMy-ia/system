<?php
session_start();
require_once __DIR__ . '/../../database/conexaoBd.php';
require_once __DIR__ . '/../../models/SessaoDAO.php';

// Verificar token em vez de sessão
$token = $_COOKIE['authToken'] ?? null;
$sessaoDAO = new SessaoDAO();
$user = $token ? $sessaoDAO->validarToken($token) : null;

if (!$user || $user['tipo_usuario'] !== 'secretario') {
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
    <title>Dashboard Secretário - SumNanz</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="Styles.css">
</head>

<body>
    <nav>
        <h1><i class="fas fa-user-secret"></i> SumNanz - Painel do Secretário</h1>
        <h2>Bem-vindo, <?= htmlspecialchars($_SESSION['nome'] ?? 'Secretário') ?>!</h2>
    </nav>
    <div class="sidebar">
        <a href="#" onclick="carregarConsultas()"><i class="fas fa-calendar-alt"></i> Todas Consultas</a>
        <a href="#" onclick="document.getElementById('formAgendar').scrollIntoView({behavior: 'smooth'})"><i class="fas fa-plus-circle"></i> Novo Agendamento</a>
        <a href="#" onclick="document.getElementById('formPaciente').scrollIntoView({behavior: 'smooth'})"><i class="fas fa-user-plus"></i> Adicionar Paciente</a>
        <a href="#" onclick="document.getElementById('formMedico').scrollIntoView({behavior: 'smooth'})"><i class="fas fa-user-md"></i> Adicionar Médico</a>
        <a href="../Medico/listarMedico.php">‗Listar Medicos</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
    <div class="main">
        <h2><i class="fas fa-chart-line"></i> Resumo de Hoje</h2>
        <div class="cards">
            <div class="card">
                <h3>Consultas Hoje</h3>
                <p id="consultasHoje">
            </div>
            <div class="card">
                <h3>Pendentes</h3>
                <p id="consultasPendentes">
            </div>
            <div class="card">
                <h3>Confirmadas</h3>
                <p id="consultasConfirmadas">
            </div>
        </div>

        <h2><i class="fas fa-user-md"></i> Adicionar Médico</h2>
        <form id="formMedico">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label for="nome_medico"><i class="fas fa-user"></i> Nome Completo:</label>
                <input type="text" name="nome_completo" id="nome_medico" required>
            </div>
            <div class="form-group">
                <label for="especialidade"><i class="fas fa-stethoscope"></i> Especialidade:</label>
                <input type="text" name="especialidade" id="especialidade" required>
            </div>
            <div class="form-group">
                <label for="cedula"><i class="fas fa-id-card"></i> Cédula Profissional:</label>
                <input type="text" name="numero_da_cedula_profissional" id="cedula" required>
            </div>
            <div class="form-group">
                <label for="email_medico"><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" name="email" id="email_medico" required>
            </div>
            <div class="form-group">
                <label for="senha_medico"><i class="fas fa-lock"></i> Senha:</label>
                <input type="password" name="senha" id="senha_medico" required>
            </div>
            <div class="form-group">
                <label for="telefone_medico"><i class="fas fa-phone"></i> Telefone:</label>
                <input type="text" name="telefone" id="telefone_medico">
            </div>
            <div class="form-group">
                <label for="data_nascimento_medico"><i class="fas fa-calendar-day"></i> Data de Nascimento:</label>
                <input type="date" name="data_nascimento" id="data_nascimento_medico">
            </div>
            <div class="form-group">
                <label for="sexo_medico"><i class="fas fa-venus-mars"></i> Sexo:</label>
                <select name="sexo" id="sexo_medico">
                    <option value="">Selecione</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ano_conclusao"><i class="fas fa-graduation-cap"></i> Ano de Conclusão:</label>
                <input type="number" name="ano_de_conclusao" id="ano_conclusao">
            </div>
            <div class="form-group">
                <label for="nivel_academico"><i class="fas fa-university"></i> Nível Acadêmico:</label>
                <input type="text" name="nivel_academico" id="nivel_academico">
            </div>
            <button type="submit" class="btn btn-primary" id="btnCadastrarMedico">
                <i class="fas fa-user-plus"></i> Cadastrar Médico
            </button>
        </form>

        <h2><i class="fas fa-user-plus"></i> Adicionar Paciente</h2>
        <form id="formPaciente">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label for="nome_paciente"><i class="fas fa-user"></i> Nome Completo:</label>
                <input type="text" name="nome_completo" id="nome_paciente" required>
            </div>
            <div class="form-group">
                <label for="email_paciente"><i class="fas fa-envelope"></i> Email:</label>
                <input type="email" name="email" id="email_paciente" required>
            </div>
            <div class="form-group">
                <label for="senha_paciente"><i class="fas fa-lock"></i> Senha:</label>
                <input type="password" name="senha" id="senha_paciente" required>
            </div>
            <div class="form-group">
                <label for="telefone_paciente"><i class="fas fa-phone"></i> Telefone:</label>
                <input type="text" name="telefone" id="telefone_paciente">
            </div>
            <div class="form-group">
                <label for="data_nascimento_paciente"><i class="fas fa-calendar-day"></i> Data de Nascimento:</label>
                <input type="date" name="data_nascimento" id="data_nascimento_paciente">
            </div>
            <div class="form-group">
                <label for="sexo_paciente"><i class="fas fa-venus-mars"></i> Sexo:</label>
                <select name="sexo" id="sexo_paciente">
                    <option value="">Selecione</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                </select>
            </div>
            <div class="form-group">
                <label for="morada"><i class="fas fa-home"></i> Morada:</label>
                <input type="text" name="morada" id="morada">
            </div>
            <div class="form-group">
                <label for="grupo_sanguineo"><i class="fas fa-tint"></i> Grupo Sanguíneo:</label>
                <select name="grupo_sanguineo" id="grupo_sanguineo">
                    <option value="">Selecione</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" id="btnCadastrarPaciente">
                <i class="fas fa-user-plus"></i> Cadastrar Paciente
            </button>
        </form>

        <h2><i class="fas fa-plus-circle"></i> Novo Agendamento</h2>
        <form id="formAgendar">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label for="paciente_id"><i class="fas fa-user"></i> Paciente:</label>
                <select name="paciente_id" id="paciente_id" required>
                    <option value="">Selecione</option>
                </select>
            </div>
            <div class="form-group">
                <label for="medico_id"><i class="fas fa-user-md"></i> Médico:</label>
                <select name="medico_id" id="medico_id" required>
                    <option value="">Selecione</option>
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
                    <option value="">Selecione</option>
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

        <h2><i class="fas fa-calendar-alt"></i> Próximas Consultas</h2>
        <div id="loadingConsultas" style="display: none; padding: 20px; text-align: center;">
            <div class="loading"></div> Carregando consultas...
        </div>
        <table id="tabelaConsultas">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Data</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" style="text-align: center;">Nenhuma consulta encontrada</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Modal para Confirmar/Cancelar Consulta -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modalTitle"><i class="fas fa-exclamation-triangle"></i> Ação na Consulta</h3>
            <p id="modalText">Tem certeza que deseja realizar esta ação?</p>
            <input type="hidden" id="consultaId">
            <input type="hidden" id="actionType">
            <button id="confirmAction" class="btn btn-danger"><i class="fas fa-check"></i> Confirmar</button>
            <button id="closeModal" class="btn"><i class="fas fa-times"></i> Fechar</button>
        </div>
    </div>

    <script>
        // Carregar dados ao abrir a página
        document.addEventListener('DOMContentLoaded', () => {
            carregarConsultas();
            carregarResumo();
            carregarMedicos();
            carregarPacientes();
        });

        // Função para carregar médicos
        function carregarMedicos() {
            fetch('../../controllers/secretarioController.php?action=list_medicos', {
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
                    select.innerHTML = '<option value="">Selecione</option>';
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

        // Função para carregar pacientes
        function carregarPacientes() {
            fetch('../../controllers/secretarioController.php?action=list_pacientes', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar pacientes');
                    const select = document.getElementById('paciente_id');
                    select.innerHTML = '<option value="">Selecione</option>';
                    data.pacientes.forEach(paciente => {
                        const option = document.createElement('option');
                        option.value = paciente.codigo;
                        option.textContent = paciente.nome_completo;
                        select.appendChild(option);
                    });
                })
                .catch(err => {
                    console.error('Erro ao carregar pacientes:', err);
                    alert('Erro ao carregar pacientes. Por favor, recarregue a página.');
                });
        }

        // Função para carregar consultas
        function carregarConsultas() {
            const loading = document.getElementById('loadingConsultas');
            const tbody = document.querySelector('#tabelaConsultas tbody');
            loading.style.display = 'block';
            tbody.innerHTML = '';

            fetch('../../controllers/secretarioController.php?action=read_consultas', {
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
                            <td colspan="7" style="text-align: center;">Nenhuma consulta agendada</td>
                        </tr>
                    `;
                        return;
                    }
                    data.consultas.forEach(c => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                        <td>${c.nome_paciente}</td>
                        <td>${c.nome_medico}</td>
                        <td>${formatarData(c.data_consulta)}</td>
                        <td>${c.hora_consulta}</td>
                        <td>${c.tipo_consulta}</td>
                        <td><span class="badge ${getStatusClass(c.estado)}">${c.estado}</span></td>
                        <td>
                            ${c.estado === 'Agendada' ? `
                            <button class="btn btn-info" onclick="confirmarConsulta(${c.codigo}, '${c.nome_paciente}', '${c.nome_medico}', '${c.data_consulta}', '${c.hora_consulta}')">
                                <i class="fas fa-check"></i> Confirmar
                            </button>` : ''}
                            <button class="btn btn-danger" onclick="cancelarConsulta(${c.codigo}, '${c.nome_paciente}', '${c.nome_medico}', '${c.data_consulta}', '${c.hora_consulta}')">
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
                        <td colspan="7" style="text-align: center; color: var(--danger-color);">
                            Erro ao carregar consultas: ${err.message}
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
            // Consultas de hoje
            fetch('../../controllers/secretarioController.php?action=consultas_hoje', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('consultasHoje').textContent = data.success ? data.consultas.length : '0';
                })
                .catch(err => {
                    console.error('Erro ao carregar consultas de hoje:', err);
                    document.getElementById('consultasHoje').textContent = 'Erro';
                });

            // Consultas pendentes
            fetch('../../controllers/secretarioController.php?action=pendentes', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('consultasPendentes').textContent = data.success ? data.consultas.length : '0';
                })
                .catch(err => {
                    console.error('Erro ao carregar consultas pendentes:', err);
                    document.getElementById('consultasPendentes').textContent = 'Erro';
                });

            // Consultas confirmadas
            fetch('../../controllers/secretarioController.php?action=confirmadas', {
                    headers: {
                        'Authorization': 'Bearer <?= $_SESSION['csrf_token'] ?>'
                    }
                })
                .then(res => {
                    if (!res.ok) throw new Error('Erro na rede');
                    return res.json();
                })
                .then(data => {
                    document.getElementById('consultasConfirmadas').textContent = data.success ? data.consultas.length : '0';
                })
                .catch(err => {
                    console.error('Erro ao carregar consultas confirmadas:', err);
                    document.getElementById('consultasConfirmadas').textContent = 'Erro';
                });
        }

        // Função para cadastrar médico
        document.getElementById('formMedico').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnCadastrarMedico');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Cadastrando...';

            const formData = new FormData(this);
            fetch('../../controllers/secretarioController.php?action=create_medico', {
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
                    alert(data.message || data.error || 'Médico cadastrado com sucesso!');
                    if (data.success) {
                        this.reset();
                        carregarMedicos();
                    }
                })
                .catch(err => {
                    console.error('Erro ao cadastrar médico:', err);
                    alert('Erro ao cadastrar médico. Tente novamente.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Função para cadastrar paciente
        document.getElementById('formPaciente').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnCadastrarPaciente');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Cadastrando...';

            const formData = new FormData(this);
            fetch('../../controllers/secretarioController.php?action=create_paciente', {
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
                    alert(data.message || data.error || 'Paciente cadastrado com sucesso!');
                    if (data.success) {
                        this.reset();
                        carregarPacientes();
                    }
                })
                .catch(err => {
                    console.error('Erro ao cadastrar paciente:', err);
                    alert('Erro ao cadastrar paciente. Tente novamente.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Função para marcar consulta
        document.getElementById('formAgendar').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnAgendar');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Agendando...';

            const formData = new FormData(this);
            fetch('../../controllers/secretarioController.php?action=create_consulta', {
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
                    alert(data.message || data.error || 'Consulta marcada com sucesso!');
                    if (data.success) {
                        this.reset();
                        carregarConsultas();
                        carregarResumo();
                    }
                })
                .catch(err => {
                    console.error('Erro ao marcar consulta:', err);
                    alert('Erro ao marcar consulta. Tente novamente.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // Função para reagendar consulta
        function reagendarConsulta(consultaId) {
            fetch(`../../controllers/secretarioController.php?action=get_consulta&id=${consultaId}`, {
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
                    document.getElementById('paciente_id').value = data.consulta.paciente_id;
                    document.getElementById('medico_id').value = data.consulta.medico_id;
                    document.getElementById('data_consulta').value = data.consulta.data_consulta;
                    document.getElementById('hora_consulta').value = data.consulta.hora_consulta;
                    document.getElementById('tipo_consulta').value = data.consulta.tipo_consulta;
                    document.getElementById('formAgendar').scrollIntoView({
                        behavior: 'smooth'
                    });
                    const form = document.getElementById('formAgendar');
                    form.action = `../../controllers/secretarioController.php?action=update_consulta&id=${consultaId}`;
                })
                .catch(err => {
                    console.error('Erro ao carregar consulta para reagendamento:', err);
                    alert('Erro ao carregar consulta. Tente novamente.');
                });
        }

        // Modal para confirmar/cancelar
        const modal = document.getElementById('actionModal');
        const span = document.getElementsByClassName('close')[0];
        const closeBtn = document.getElementById('closeModal');

        function confirmarConsulta(id, paciente, medico, data, hora) {
            document.getElementById('consultaId').value = id;
            document.getElementById('actionType').value = 'confirmar';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle"></i> Confirmar Consulta';
            document.getElementById('modalText').innerHTML = `
                Tem certeza que deseja confirmar a consulta de <strong>${paciente}</strong> com <strong>${medico}</strong>
                marcada para <strong>${formatarData(data)} às ${hora}</strong>?
            `;
            document.getElementById('confirmAction').className = 'btn btn-info';
            document.getElementById('confirmAction').innerHTML = '<i class="fas fa-check"></i> Confirmar';
            modal.style.display = 'block';
        }

        function cancelarConsulta(id, paciente, medico, data, hora) {
            document.getElementById('consultaId').value = id;
            document.getElementById('actionType').value = 'cancelar';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Cancelar Consulta';
            document.getElementById('modalText').innerHTML = `
                Tem certeza que deseja cancelar a consulta de <strong>${paciente}</strong> com <strong>${medico}</strong>
                marcada para <strong>${formatarData(data)} às ${hora}</strong>?
                <br><br>
                <small>Cancelamentos podem notificar o paciente.</small>
            `;
            document.getElementById('confirmAction').className = 'btn btn-danger';
            document.getElementById('confirmAction').innerHTML = '<i class="fas fa-trash-alt"></i> Confirmar Cancelamento';
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

        // Confirmar ou cancelar ação
        document.getElementById('confirmAction').addEventListener('click', function() {
            const id = document.getElementById('consultaId').value;
            const action = document.getElementById('actionType').value;
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<div class="loading"></div> Processando...';

            const formData = new FormData();
            formData.append('consulta_id', id);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

            fetch(`../../controllers/secretarioController.php?action=${action}`, {
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
                    if (!data.success) throw new Error(data.error || `Erro ao ${action === 'confirmar' ? 'confirmar' : 'cancelar'} consulta`);
                    alert(data.message || `Consulta ${action === 'confirmar' ? 'confirmada' : 'cancelada'} com sucesso!`);
                    modal.style.display = 'none';
                    carregarConsultas();
                    carregarResumo();
                })
                .catch(err => {
                    console.error(`Erro ao ${action === 'confirmar' ? 'confirmar' : 'cancelar'} consulta:`, err);
                    alert(err.message || `Erro ao ${action === 'confirmar' ? 'confirmar' : 'cancelar'} consulta. Tente novamente.`);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

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
    </script>
</body>

</html>