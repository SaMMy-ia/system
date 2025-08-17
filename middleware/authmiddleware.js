/**
 * Middleware de autenticação - Verifica o token em páginas protegidas
 */

document.addEventListener('DOMContentLoaded', function() {
    // Verifica se a página requer autenticação
    if (document.body.classList.contains('auth-required')) {
        initAuthMiddleware();
    }
});

function initAuthMiddleware() {
    const token = getCookie('authToken');
    console.log('Token encontrado:', token); // Depuração
    const publicRoutes = ['/login.html', '/register.html'];
    const currentPath = window.location.pathname.split('/').pop();

    if (publicRoutes.includes(currentPath)) {
        return;
    }

    if (!token) {
        redirectToLogin();
        return;
    }

    verifyToken(token)
        .then(userData => {
            updateUIForUser(userData.user_type);
            checkPagePermissions(userData.user_type);
        })
        .catch(error => {
            console.error('Falha na autenticação:', error);
            redirectToLogin();
        });
}

async function verifyToken(token) {
    const response = await fetch("../controllers/AuthController.php?action=validate", {
        method: "GET",
        headers: {
            'Authorization': `Bearer ${token}`
        }
    });

    if (!response.ok) {
        throw new Error('Token inválido ou servidor indisponível');
    }

    const data = await response.json();
    if (!data.success) {
        throw new Error(data.error || 'Falha na autenticação');
    }

    return data;
}

function checkPagePermissions(userType) {
    const protectedRoutes = {
        'medico': ['dashboardMedico.php', 'consultas.html'],
        'secretario': ['dashboardSecretario.php', 'agendamentos.html'],
        'paciente': ['dashboardPaciente.php', 'minhas-consultas.html'],
        'admin': ['dashboardAdmin.php', 'gerenciamento.html']
    };

    const currentPage = window.location.pathname.split('/').pop();
    const allowedPages = protectedRoutes[userType] || [];

    if (!allowedPages.includes(currentPage)) {
        redirectToDashboard(userType);
    }
}

function redirectToDashboard(userType) {
    const dashboards = {
        'medico': 'Medico/dashboardMedico.php',
        'secretario': 'Secretario/dashboardSecretario.php',
        'paciente': 'Paciente/dashboardPaciente.php',
        'admin': 'admin/dashboard.php'
    };

    window.location.href = dashboards[userType] || '/';
}

function redirectToLogin() {
    sessionStorage.setItem('redirectAfterLogin', window.location.pathname);
    window.location.href = '/views/login.html';
}

function updateUIForUser(userType) {
    $(`[data-user-type]:not([data-user-type="${userType}"])`).hide();
    $(`[data-user-type="${userType}"]`).show();
    updateUserHeader(userType);
}

function updateUserHeader(userType) {
    $('#userTypeDisplay').text(userType.charAt(0).toUpperCase() + userType.slice(1));
    $('#logoutBtn').show().on('click', logout);
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function logout() {
    // Implementar chamada para logout no AuthController.php
    $.ajax({
        url: "../controllers/AuthController.php?action=logout",
        type: "POST",
        dataType: "json",
        success: function (response) {
            if (response.success) {
                window.location.href = '/views/login.html';
            }
        },
        error: function () {
            console.error('Erro ao fazer logout');
        }
    });
}