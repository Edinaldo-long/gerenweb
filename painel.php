<?php
// Todo o PHP fica ANTES do HTML, para o header() funcionar
session_start();

// Se não houver sessão ativa, volta para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.html');
    exit;
}

require_once 'conexao.php';

$idUsuario   = $_SESSION['usuario_id'];
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

date_default_timezone_set('America/Sao_Paulo');
$dataHoje = date('Y-m-d');

try {
    // Total de tarefas do usuário
    $sqlTotal = "SELECT COUNT(*) AS total FROM tbltarefas
                 WHERE id_usuario = :id_usuario";

    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->bindValue(':id_usuario', $idUsuario);
    $stmtTotal->execute();

    $qtdeTarefas = (int) $stmtTotal->fetch()['total'];

    // Tarefas agendadas para hoje
    $sqlHoje = "SELECT id_tarefa, titulo_tarefa, horario_tarefa, status_tarefa
                FROM tbltarefas
                WHERE id_usuario = :id_usuario AND data_tarefa = :data_hoje
                ORDER BY horario_tarefa ASC";

    $stmtHoje = $pdo->prepare($sqlHoje);
    $stmtHoje->bindValue(':id_usuario', $idUsuario);
    $stmtHoje->bindValue(':data_hoje', $dataHoje);
    $stmtHoje->execute();

    $resultadoTarefas = $stmtHoje->fetchAll();

} catch (PDOException $e) {
    die("Erro ao carregar o painel: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GerenWeb - Painel</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

    <!-- Barra com informações do usuário e botão Sair -->
    <div class="barra-topo-usuario">
        <span>Conectado como: <strong><?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        
    </div>

    <nav class="menu">
        <a href="painel.php" class="ativo">Painel</a>
        <a href="nova_tarefa.php">Nova Tarefa</a>
        <a href="consultar_tarefas.php">Consultar Tarefas</a>
        <a href="cadastrar_produtos.php">Cadastrar Produtos</a>
        <a href="logout.php">Sair</a>   
    </nav>

    <main class="conteudo">

        <section class="cartao">
            <h2>Resumo da Conta</h2>
            <p class="resumo">
                Você possui um total de
                <strong class="numero"><?php echo $qtdeTarefas; ?></strong>
                <?php echo $qtdeTarefas === 1 ? 'tarefa' : 'tarefas'; ?>
            </p>
        </section>

        <section class="cartao">
            <h2>Compromissos de Hoje <span class="data-hoje"><?php echo date('d/m/Y'); ?></span></h2>

            <?php if (count($resultadoTarefas) > 0): ?>
                <ul class="lista-tarefas">
                    <?php foreach ($resultadoTarefas as $tarefa): ?>
                        <?php $concluida = ($tarefa['status_tarefa'] === 'Concluída'); ?>
                        <li>
                            <span class="horario"><?php echo htmlspecialchars($tarefa['horario_tarefa'] ?? '--:--', ENT_QUOTES, 'UTF-8'); ?></span>

                            <span class="titulo-tarefa"><?php echo htmlspecialchars($tarefa['titulo_tarefa'], ENT_QUOTES, 'UTF-8'); ?></span>

                            <span class="status <?php echo $concluida ? 'status-concluida' : 'status-pendente'; ?>">
                                <?php echo htmlspecialchars($tarefa['status_tarefa'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                            <?php if (!$concluida): ?>
                                <form action="concluir_tarefa.php" method="POST" class="form-inline">
                                    <input type="hidden" name="id_tarefa" value="<?php echo (int) $tarefa['id_tarefa']; ?>">
                                    <button type="submit" class="btn-concluir">Concluir</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="vazio">Sem compromissos para o dia de hoje...</p>
            <?php endif; ?>
        </section>

    </main>

</body>
</html>