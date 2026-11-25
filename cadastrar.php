<?php

require_once 'conexao.php';

function e($valor)
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

// Busca de reserva no servidor (cURL; se não existir, tenta file_get_contents)
function buscarCep($cepLimpo)
{
    $url = "https://viacep.com.br/ws/{$cepLimpo}/json/";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $resposta = curl_exec($ch);
        curl_close($ch);
    } else {
        $resposta = @file_get_contents($url);
    }

    if ($resposta === false || $resposta === null) {
        return null;
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados) || isset($dados['erro'])) {
        return null;
    }
    return $dados;
}

$nomeCompleto       = trim($_POST['nome_usuario'] ?? '');
$nomeSocial         = trim($_POST['nome_social'] ?? '');
$cpfUsuario         = trim($_POST['cpf_usuario'] ?? '');
$emailUsuario       = trim($_POST['email_usuario'] ?? '');
$senhaUsuario       = trim($_POST['senha_usuario'] ?? '');
$cepUsuario         = trim($_POST['cep_usuario'] ?? '');
$enderecoUsuario    = trim($_POST['endereco_usuario'] ?? '');
$numeroEndereco     = trim($_POST['numero_endereco'] ?? '');
$bairroUsuario      = trim($_POST['bairro_usuario'] ?? '');
$cidadeUsuario      = trim($_POST['cidade_usuario'] ?? '');
$estadoUsuario      = trim($_POST['estado_usuario'] ?? '');
$complementoUsuario = trim($_POST['complemento_usuario'] ?? '');

$erros   = [];
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cepLimpo = preg_replace('/[^0-9]/', '', $cepUsuario);
    $cpfLimpo = preg_replace('/[^0-9]/', '', $cpfUsuario);

    // Reserva: se o JavaScript não preencheu o endereço, busca no servidor
    if ($enderecoUsuario === '' && strlen($cepLimpo) === 8) {
        $dados = buscarCep($cepLimpo);
        if ($dados) {
            $enderecoUsuario = $dados['logradouro'] ?? '';
            $bairroUsuario   = $dados['bairro'] ?? '';
            $cidadeUsuario   = $dados['localidade'] ?? '';
            $estadoUsuario   = $dados['uf'] ?? '';
        } else {
            $erros[] = 'CEP inválido ou não encontrado.';
        }
    }

    if ($nomeCompleto === '') {
        $erros[] = 'Informe o nome completo.';
    }
    if (strlen($cpfLimpo) !== 11) {
        $erros[] = 'O CPF deve ter 11 números.';
    }
    if (!filter_var($emailUsuario, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'E-mail inválido.';
    }
    if (empty($senhaUsuario)) {
        $erros[] = 'Informe a senha.';
    }
    if (strlen($cepLimpo) !== 8 && !in_array('CEP inválido ou não encontrado.', $erros)) {
        $erros[] = 'O CEP deve ter 8 números.';
    }

    // Grava no banco de dados com a senha criptografada em MD5
    if (empty($erros)) {
        try {
            $senhaMd5 = md5($senhaUsuario);

            $sql = "INSERT INTO usuarios
                        (nome_completo, nome_social, cpf, email, senha, cep, endereco,
                         numero, bairro, cidade, estado, complemento)
                    VALUES
                        (:nome, :social, :cpf, :email, :senha, :cep, :endereco,
                         :numero, :bairro, :cidade, :estado, :complemento)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome'        => $nomeCompleto,
                ':social'      => $nomeSocial !== '' ? $nomeSocial : null,
                ':cpf'         => $cpfLimpo,
                ':email'       => $emailUsuario,
                ':senha'       => $senhaMd5,
                ':cep'         => $cepLimpo,
                ':endereco'    => $enderecoUsuario,
                ':numero'      => $numeroEndereco,
                ':bairro'      => $bairroUsuario,
                ':cidade'      => $cidadeUsuario,
                ':estado'      => strtoupper($estadoUsuario),
                ':complemento' => $complementoUsuario,
            ]);

            $sucesso = true;

        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $erros[] = 'CPF ou e-mail já cadastrado.';
            } else {
                $erros[] = 'Erro ao salvar no banco de dados.';
                error_log($ex->getMessage());
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Usuário</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

<?php if ($sucesso): ?>

    <h1>Cadastro realizado</h1>

    <div class="alerta alerta-sucesso">Usuário cadastrado com sucesso!</div>

    <dl class="resumo">
        <div><dt>Nome completo</dt><dd><?php echo e($nomeCompleto); ?></dd></div>
        <div><dt>Nome social</dt><dd><?php echo e($nomeSocial ?: '—'); ?></dd></div>
        <div><dt>CPF</dt><dd><?php echo e($cpfUsuario); ?></dd></div>
        <div><dt>E-mail</dt><dd><?php echo e($emailUsuario); ?></dd></div>
        <div><dt>CEP</dt><dd><?php echo e($cepUsuario); ?></dd></div>
        <div><dt>Endereço</dt><dd><?php echo e($enderecoUsuario); ?>, <?php echo e($numeroEndereco ?: 's/n'); ?></dd></div>
        <div><dt>Bairro</dt><dd><?php echo e($bairroUsuario); ?></dd></div>
        <div><dt>Cidade / UF</dt><dd><?php echo e($cidadeUsuario); ?> / <?php echo e($estadoUsuario); ?></dd></div>
        <div><dt>Complemento</dt><dd><?php echo e($complementoUsuario ?: '—'); ?></dd></div>
    </dl>

    <a class="botao-link" href="index.html">Novo cadastro</a>

<?php else: ?>

    <h1>Cadastro de Usuário</h1>

    <?php if (!empty($erros)): ?>
        <div class="alerta alerta-erro">
            <ul>
                <?php foreach ($erros as $erro): ?>
                    <li><?php echo e($erro); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="cadastrar.php" method="POST" autocomplete="off">

        <div>
            <label for="nome_usuario">Nome Completo:</label>
            <input type="text" id="nome_usuario" name="nome_usuario" value="<?php echo e($nomeCompleto); ?>" required>
        </div>

        <div>
            <label for="nome_social">Nome Social:</label>
            <input type="text" id="nome_social" name="nome_social" value="<?php echo e($nomeSocial); ?>">
        </div>

        <div>
            <label for="cpf_usuario">CPF</label>
            <input type="text" id="cpf_usuario" name="cpf_usuario" maxlength="14" value="<?php echo e($cpfUsuario); ?>" required>
        </div>

        <div>
            <label for="email_usuario">E-mail:</label>
            <input type="email" id="email_usuario" name="email_usuario" value="<?php echo e($emailUsuario); ?>" required>
        </div>

        <div>
            <label for="senha_usuario">Senha:</label>
            <input type="password" id="senha_usuario" name="senha_usuario" required>
        </div>

        <div>
            <label for="cep_usuario">CEP:</label>
            <input type="text" id="cep_usuario" name="cep_usuario" maxlength="9" placeholder="14400-500" value="<?php echo e($cepUsuario); ?>" required>
        </div>

        <div>
            <label for="endereco_usuario">Endereço</label>
            <input type="text" id="endereco_usuario" name="endereco_usuario" value="<?php echo e($enderecoUsuario); ?>">
        </div>

        <div>
            <label for="numero_endereco">Nº</label>
            <input type="text" id="numero_endereco" name="numero_endereco" value="<?php echo e($numeroEndereco); ?>">
        </div>

        <div>
            <label for="bairro_usuario">Bairro</label>
            <input type="text" id="bairro_usuario" name="bairro_usuario" value="<?php echo e($bairroUsuario); ?>">
        </div>

        <div>
            <label for="cidade_usuario">Cidade</label>
            <input type="text" id="cidade_usuario" name="cidade_usuario" value="<?php echo e($cidadeUsuario); ?>">
        </div>

        <div>
            <label for="estado_usuario">Estado (UF)</label>
            <input type="text" id="estado_usuario" name="estado_usuario" maxlength="2" value="<?php echo e($estadoUsuario); ?>">
        </div>

        <div>
            <label for="complemento_usuario">Complemento</label>
            <input type="text" id="complemento_usuario" name="complemento_usuario" value="<?php echo e($complementoUsuario); ?>">
        </div>

        <div>
            <button type="submit">Cadastrar</button>
        </div>

    </form>

<?php endif; ?>

</div>

</body>
</html>