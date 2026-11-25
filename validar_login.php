<?php
session_start();

require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$emailDigitado = trim($_POST['email_usuario'] ?? '');
$senhaDigitada = trim($_POST['senha_usuario'] ?? '');

if (empty($emailDigitado) || empty($senhaDigitada)) {
    echo "<script>
            alert('Preencha todos os campos!');
            window.location.href = 'login.html';
          </script>";
    exit;
}

// Criptografa a senha digitada em MD5 para comparar com o banco
$senhaCriptografada = md5($senhaDigitada);

try {
    // Busca o usuário pelo e-mail e pela senha já criptografada
    $sql = "SELECT id, nome_completo, email
            FROM usuarios
            WHERE email = :email AND senha = :senha
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $emailDigitado,
        ':senha' => $senhaCriptografada
    ]);

    $usuario = $stmt->fetch();

    if ($usuario) {
        // Guarda os dados na sessão (mesmos nomes de chave do professor)
        $_SESSION['usuario_id']    = $usuario['id'];
        $_SESSION['usuario_nome']  = $usuario['nome_completo'];
        $_SESSION['usuario_email'] = $usuario['email'];

        header('Location: painel.php');
        exit;
    } else {
        echo "<script>
                alert('E-mail ou senha inválidos!');
                window.location.href = 'login.html';
              </script>";
        exit;
    }

} catch (PDOException $e) {
    echo "Erro ao validar login: " . $e->getMessage();
    exit;
}