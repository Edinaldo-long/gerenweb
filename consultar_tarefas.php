<?php
session_start();

// Só quem está logado pode consultar tarefas
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit;
}

require_once 'conexao.php';

date_default_timezone_set('America/Sao_Paulo');

$idUsuario   = $_SESSION['usuario_id'];
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Filtros (opcionais), enviados pela URL
$filtroData   = trim($_GET['data'] ?? '');
$filtroStatus = trim($_GET['status'] ?? '');

$statusPermitidos = ['Pendente', 'Concluída'];

$sql = "SELECT id_tarefa, titulo_tarefa, descricao_tarefa, data_tarefa, horario_tarefa, status_tarefa
        FROM tbltarefas
        WHERE id_usuario = :id_usuario";

$params = [':id_usuario' => $idUsuario];

if ($filtroData !== '') {
    $dataValida = DateTime::createFromFormat('Y-m-d', $filtroData);
    if ($dataValida && $dataValida->format('Y-m-d') === $filtroData) {
        $sql .= " AND data_tarefa = :data";
        $params[':data'] = $filtroData;
    } else {
        $filtroData = '';
    }
}

if (in_array($filtroStatus, $statusPermitidos, true)) {
    $sql .= " AND status_tarefa = :status";
    $params[':status'] = $filtroStatus;
} else {
    $filtroStatus = '';
}

$sql .= " ORDER BY data_tarefa ASC, horario_tarefa ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tarefas = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro ao consultar as tarefas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GerenWeb - Consultar Tarefas</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

    <div class="barra-topo-usuario">
        <span>Conectado como: <strong><?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a href="logout.php" class="btn-logout">Sair</a>
    </div>

    <nav class="menu">
        <a href="painel.php">Painel</a>
        <a href="nova_tarefa.php">Nova Tarefa</a>
        <a href="consultar_tarefas.php" class="ativo">Consultar Tarefas</a>
    </nav>

    <main class="conteudo">

        <section class="cartao">
            <h2>Filtrar Tarefas</h2>

            <form class="filtros" action="consultar_tarefas.php" method="GET">
                <div class="grupo">
                    <label for="data">Data</label>
                    <input type="date" id="data" name="data" value="<?php echo htmlspecialchars($filtroData, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="grupo">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Todos</option>
                        <option value="Pendente" <?php echo $filtroStatus === 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="Concluída" <?php echo $filtroStatus === 'Concluída' ? 'selected' : ''; ?>>Concluída</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primario">Filtrar</button>
                <a href="consultar_tarefas.php" class="btn btn-secundario">Limpar</a>
            </form>
        </section>

        <section class="cartao">
            <h2>Tarefas</h2>

            <?php if (count($tarefas) > 0): ?>

                <div class="tabela-rolagem">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Horário</th>
                                <th>Título</th>
                                <th>Descrição</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tarefas as $tarefa): ?>
                                <?php $concluida = ($tarefa['status_tarefa'] === 'Concluída'); ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($tarefa['data_tarefa'])); ?></td>
                                    <td><?php echo htmlspecialchars($tarefa['horario_tarefa'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($tarefa['titulo_tarefa'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tarefa['descricao_tarefa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="status <?php echo $concluida ? 'status-concluida' : 'status-pendente'; ?>">
                                            <?php echo htmlspecialchars($tarefa['status_tarefa'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!$concluida): ?>
                                            <form action="concluir_tarefa.php" method="POST" class="form-inline">
                                                <input type="hidden" name="id_tarefa" value="<?php echo (int) $tarefa['id_tarefa']; ?>">
                                                <input type="hidden" name="voltar" value="consultar_tarefas.php">
                                                <button type="submit" class="btn-concluir">Concluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <p class="vazio">Nenhuma tarefa encontrada.</p>

            <?php endif; ?>
        </section>

    </main>

</body>
</html>