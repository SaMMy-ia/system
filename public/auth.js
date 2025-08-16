// JavaScript para login
$(document).ready(function() {
    $('#loginForm').submit(function(e) {
        e.preventDefault();
        
        const email = $('#email').val();
        const password = $('#password').val();
        
        $.ajax({
            url: '/controllers/AuthController.php?action=login',
            type: 'POST',
            dataType: 'json',
            data: {
                email: email,
                password: password
            },
            success: function(response) {
                if(response.success) {
                    $('#message').removeClass('error').addClass('success').text('Login bem-sucedido!');
                    
                    // Redirecionar com base no tipo de usuário
                    switch(response.user_type) {
                        case 'admin':
                            window.location.href = '/admin/dashboard.php';
                            break;
                        case 'medico':
                            window.location.href = '/Medico/dashboardMedico.html';
                            break;
                        case 'secretario':
                            window.location.href = '/Secretario/dashboardSecretario.html';
                            break;
                        case 'paciente':
                            window.location.href = '/Paciente/dashboardPaciente.html';
                            break;

                    }
                } else {
                    $('#message').removeClass('success').addClass('error').text('Credenciais inválidas!');
                }
            },
            error: function() {
                $('#message').removeClass('success').addClass('error').text('Erro ao processar login.');
            }
        });
    });
});