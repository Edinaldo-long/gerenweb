<?php
session_start();

// Só quem está logado pode cadastrar tarefas
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit;
}

require_once 'conexao.php';

date_default_timezone_set('America/Sao_Paulo');

$idUsuario   = $_SESSION['usuario_id'];
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

$erros     = [];
$titulo    = '';
$descricao = '';
$data      = date('Y-m-d');
$horario   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo    = trim($_POST['titulo_tarefa'] ?? '');
    $descricao = trim($_POST['descricao_tarefa'] ?? '');
    $data      = trim($_POST['data_tarefa'] ?? '');
    $horario   = trim($_POST['horario_tarefa'] ?? '');

    // Validações
    if ($titulo === '') {
        $erros[] = 'Informe o título da tarefa.';
    } elseif (mb_strlen($titulo) > 50) {
        $erros[] = 'O título pode ter no máximo 50 caracteres.';
    }

    if (mb_strlen($descricao) > 200) {
        $erros[] = 'A descrição pode ter no máximo 200 caracteres.';
    }

    $dataValida = DateTime::createFromFormat('Y-m-d', $data);
    if (!$dataValida || $dataValida->format('Y-m-d') !== $data) {
        $erros[] = 'Informe uma data válida.';
    } elseif ($data < date('Y-m-d')) {
        $erros[] = 'Não é permitido agendar tarefa para uma data que já passou.';
    }

    if ($horario !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horario)) {
        $erros[] = 'Informe um horário válido (HH:MM).';
    }

    // Se está tudo certo, grava no banco
    if (empty($erros)) {
        try {
            $sql = "INSERT INTO tbltarefas
                        (id_usuario, titulo_tarefa, descricao_tarefa, data_tarefa, horario_tarefa, status_tarefa)
                    VALUES
                        (:id_usuario, :titulo, :descricao, :data, :horario, 'Pendente')";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_usuario' => $idUsuario,
                ':titulo'     => $titulo,
                ':descricao'  => ($descricao === '' ? null : $descricao),
                ':data'       => $data,
                ':horario'    => ($horario === '' ? null : $horario),
            ]);

            header('Location: painel.php');
            exit;

        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar a tarefa: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GerenWeb - Nova Tarefa</title>
    <link rel="stylesheet" href="estilo.css">
</head>
<body>

    <div class="barra-topo-usuario">
        <span>Conectado como: <strong><?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <a href="logout.php" class="btn-logout">Sair</a>
    </div>

    <nav class="menu">
        <a href="painel.php">Painel</a>
        <a href="nova_tarefa.php" class="ativo">Nova Tarefa</a>
        <a href="consultar_tarefas.php">Consultar Tarefas</a>
    </nav>

    <main class="conteudo">

        <section class="cartao">
            <h2>Nova Tarefa</h2>

            <?php if (!empty($erros)): ?>
                <div class="alerta-erro">
                    <?php foreach ($erros as $erro): ?>
                        <p><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="formulario" action="nova_tarefa.php" method="POST" autocomplete="off">

                <div class="campo">
                    <label for="titulo_tarefa">Título</label>
                    <input type="text" id="titulo_tarefa" name="titulo_tarefa" maxlength="50" required
                           value="<?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="campo">
                    <label for="descricao_tarefa">Descrição (opcional)</label>
                    <textarea id="descricao_tarefa" name="descricao_tarefa" rows="3" maxlength="200"><?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="linha">
                    <div class="campo">
                        <label for="data_tarefa">Data</label>
                        <input type="date" id="data_tarefa" name="data_tarefa"
                               min="<?php echo date('Y-m-d'); ?>" required
                               value="<?php echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="campo">
                        <label for="horario_tarefa">Horário (opcional)</label>
                        <input type="time" id="horario_tarefa" name="horario_tarefa"
                               value="<?php echo htmlspecialchars($horario, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="acoes">
                    <button type="submit" class="btn btn-primario">Salvar tarefa</button>
                    <a href="painel.php" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </section>

    </main>

</body>
</html>