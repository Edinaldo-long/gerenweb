<?php
session_start();

// Só quem está logado pode concluir tarefas
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit;
}

// Só aceita envio pelo botão (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: painel.php');
    exit;
}

require_once 'conexao.php';

$idTarefa  = (int) ($_POST['id_tarefa'] ?? 0);
$idUsuario = $_SESSION['usuario_id'];

if ($idTarefa > 0) {
    try {
        // O AND id_usuario garante que ninguém conclua tarefa de outra pessoa
        $sql = "UPDATE tbltarefas
                SET status_tarefa = 'Concluída'
                WHERE id_tarefa = :id_tarefa AND id_usuario = :id_usuario";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_tarefa'  => $idTarefa,
            ':id_usuario' => $idUsuario
        ]);
    } catch (PDOException $e) {
        die("Erro ao concluir a tarefa: " . $e->getMessage());
    }
}

// Volta para a página de onde veio (só aceita páginas conhecidas)
$paginasPermitidas = ['painel.php', 'consultar_tarefas.php'];
$voltar = $_POST['voltar'] ?? 'painel.php';

if (!in_array($voltar, $paginasPermitidas, true)) {
    $voltar = 'painel.php';
}

header('Location: ' . $voltar);
exit;