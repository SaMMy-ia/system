// JavaScript para login
$(document).ready(function () {
  $("#loginForm").submit(function (e) {
    e.preventDefault();

    const email = $("#email").val().trim();
    const password = $("#password").val().trim();

    if (!email || !password) {
      $("#message")
        .removeClass("success")
        .addClass("error")
        .text("Por favor, preencha todos os campos.");
      return;
    }

    $.ajax({
      url: "../controllers/AuthController.php?action=login", // caminho relativo
      type: "POST",
      dataType: "json",
      data: {
        email: email,
        password: password,
      },
      success: function (response) {
        if (response.success) {
          $("#message")
            .removeClass("error")
            .addClass("success")
            .text("Login bem-sucedido! Redirecionando...");

          // Redirecionar com base no tipo de usuário
          switch (response.user_type) {
            case "admin":
              window.location.href = "admin/dashboard.php";
              break;
            case "medico":
              window.location.href = "Medico/dashboardMedico.php";
              break;
            case "secretario":
              window.location.href = "Secretario/dashboardSecretario.php";
              break;
            case "paciente":
              window.location.href = "Paciente/dashboardPaciente.php";
              break;
            default:
              window.location.href = "../index.php"; // fallback
          }
        } else {
          $("#message")
            .removeClass("success")
            .addClass("error")
            .text(response.message || "Credenciais inválidas!");
        }
      },
      error: function (xhr) {
        $("#message")
          .removeClass("success")
          .addClass("error")
          .text("Erro ao processar login. Código: " + xhr.status);
      },
    });
  });
});
