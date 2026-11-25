<?php
session_start();
require_once 'conexao.php';

// Impede cache da página protegida (proteção contra botão Voltar após logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Protege a página
if (!isset($_SESSION['usuario_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erros' => ['Sessão expirada. Faça login novamente.']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: index.html');
    exit;
}

$idUsuario   = (int) $_SESSION['usuario_id'];
$nomeUsuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Processa o cadastro quando o formulário envia via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $nome          = trim($_POST['nome_produto'] ?? '');
    $ean           = trim($_POST['codigo_ean_produto'] ?? '');
    $sku           = trim($_POST['codigo_sku_produto'] ?? '');
    $sku           = mb_strtoupper(preg_replace('/^sku\s*:\s*/i', '', $sku));
    $categoria     = trim($_POST['categoria_produto'] ?? '');
    $custoTexto    = trim($_POST['preco_custo'] ?? '');
    $vendaTexto    = trim($_POST['preco_venda'] ?? '');
    $estoqueAtual  = $_POST['estoque_atual'] ?? '';
    $estoqueMinimo = $_POST['estoque_minimo'] ?? '5';
    $status        = strtoupper(trim($_POST['status_produto'] ?? 'ATIVO'));

    $erros = [];

    $converterDecimal = function ($valorTexto) {
        if ($valorTexto === '') return null;
        $limpo = str_replace(['R$', ' '], '', $valorTexto);
        if (strpos($limpo, ',') !== false) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        }
        if (!is_numeric($limpo) || $limpo < 0 || $limpo > 99999999.99) {
            return false;
        }
        return number_format((float) $limpo, 2, '.', '');
    };

    if ($nome === '' || mb_strlen($nome) > 100) {
        $erros[] = 'Informe o nome do produto (até 100 caracteres).';
    }

    $precoCusto = $converterDecimal($custoTexto);
    if ($precoCusto === false) {
        $erros[] = 'Preço de custo inválido.';
    }

    $precoVenda = $converterDecimal($vendaTexto);
    if ($precoVenda === false) {
        $erros[] = 'Preço de venda inválido.';
    }

    if ($estoqueAtual === '' || filter_var($estoqueAtual, FILTER_VALIDATE_INT) === false || (int) $estoqueAtual < 0) {
        $erros[] = 'Informe um estoque atual válido.';
    }

    if ($estoqueMinimo === '' || filter_var($estoqueMinimo, FILTER_VALIDATE_INT) === false || (int) $estoqueMinimo < 0) {
        $erros[] = 'Informe um estoque mínimo válido.';
    }

    if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
        $status = 'ATIVO';
    }

    // Verifica duplicidade de EAN e SKU antes de tentar inserir
    if (empty($erros)) {
        if ($ean !== '') {
            $stmtCheckEan = $pdo->prepare("SELECT nome_produto FROM tblprodutos WHERE codigo_ean_produto = :ean LIMIT 1");
            $stmtCheckEan->execute([':ean' => $ean]);
            if ($existenteEan = $stmtCheckEan->fetch(PDO::FETCH_ASSOC)) {
                $erros[] = "O código EAN {$ean} já está cadastrado no produto \"{$existenteEan['nome_produto']}\".";
            }
        }

        if ($sku !== '') {
            $stmtCheckSku = $pdo->prepare("SELECT nome_produto FROM tblprodutos WHERE codigo_sku_produto = :sku LIMIT 1");
            $stmtCheckSku->execute([':sku' => $sku]);
            if ($existenteSku = $stmtCheckSku->fetch(PDO::FETCH_ASSOC)) {
                $erros[] = "O código SKU {$sku} já está cadastrado no produto \"{$existenteSku['nome_produto']}\".";
            }
        }
    }

    if (!empty($erros)) {
        http_response_code(422);
        echo json_encode(['erros' => $erros], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $sql = "INSERT INTO tblprodutos (
                    id_usuario, codigo_ean_produto, codigo_sku_produto,
                    nome_produto, categoria_produto, preco_custo,
                    preco_venda, estoque_atual, estoque_minimo, status_produto
                ) VALUES (
                    :id_usuario, :ean, :sku,
                    :nome, :categoria, :preco_custo,
                    :preco_venda, :estoque_atual, :estoque_minimo, :status
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_usuario'     => $idUsuario,
            ':ean'            => $ean !== '' ? $ean : null,
            ':sku'            => $sku !== '' ? $sku : null,
            ':nome'           => $nome,
            ':categoria'      => $categoria !== '' ? $categoria : null,
            ':preco_custo'    => $precoCusto,
            ':preco_venda'    => $precoVenda,
            ':estoque_atual'  => (int) $estoqueAtual,
            ':estoque_minimo' => (int) $estoqueMinimo,
            ':status'         => $status,
        ]);

        http_response_code(201);
        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000' || (isset($ex->errorInfo[1]) && (int) $ex->errorInfo[1] === 1062)) {
            http_response_code(422);
            echo json_encode(['erros' => ['Já existe um produto cadastrado com este código EAN ou SKU.']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(500);
        echo json_encode(['erros' => ['Erro no banco: ' . $ex->getMessage()]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GerenWeb - Cadastrar Produtos</title>
    <link rel="stylesheet" href="produtos.css">
</head>
<body>

    <header class="topo">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span>Conectado como: <strong><?php echo htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8'); ?></strong></span>
            <a href="painel.php" style="color: #fff; text-decoration: underline;">← Voltar ao Painel</a>
        </div>
        <h1>Gestão de Produtos</h1>
        <p>Cadastro e controle de estoque</p>
    </header>

    <main class="conteudo">

        <section class="cadastro">
            <h2>Cadastrar produto</h2>

            <div id="alerta" class="alerta" hidden></div>

            <form id="formProduto" autocomplete="off">

                <div class="campo">
                    <label for="nome_produto">Nome do produto *</label>
                    <input type="text" id="nome_produto" name="nome_produto" maxlength="100" required>
                </div>

                <div class="linha">
                    <div class="campo">
                        <label for="codigo_ean_produto">Código EAN (Barras)</label>
                        <input type="text" id="codigo_ean_produto" name="codigo_ean_produto" maxlength="30">
                    </div>

                    <div class="campo">
                        <label for="codigo_sku_produto">Código SKU</label>
                        <input type="text" id="codigo_sku_produto" name="codigo_sku_produto" maxlength="15">
                    </div>
                </div>

                <div class="linha">
                    <div class="campo">
                        <label for="categoria_produto">Categoria</label>
                        <input
                            type="text"
                            id="categoria_produto"
                            name="categoria_produto"
                            list="lista_categorias"
                            maxlength="30"
                            placeholder="Selecione ou digite..."
                            autocomplete="off"
                        >
                        <datalist id="lista_categorias">
                            <option value="Alimentos">
                            <option value="Bebidas">
                            <option value="Eletrônicos">
                            <option value="Frios e Laticínios">
                            <option value="Higiene">
                            <option value="Hortifruti">
                            <option value="Limpeza">
                            <option value="Padaria">
                            <option value="Utilidades">
                        </datalist>
                    </div>

                    <div class="campo">
                        <label for="status_produto">Status</label>
                        <select id="status_produto" name="status_produto">
                            <option value="ATIVO" selected>ATIVO</option>
                            <option value="INATIVO">INATIVO</option>
                        </select>
                    </div>
                </div>

                <div class="linha">
                    <div class="campo">
                        <label for="preco_custo">Preço de Custo (R$)</label>
                        <input type="text" id="preco_custo" name="preco_custo" placeholder="0,00" inputmode="decimal">
                    </div>

                    <div class="campo">
                        <label for="preco_venda">Preço de Venda (R$)</label>
                        <input type="text" id="preco_venda" name="preco_venda" placeholder="0,00" inputmode="decimal">
                    </div>
                </div>

                <div class="linha">
                    <div class="campo">
                        <label for="estoque_atual">Estoque Atual *</label>
                        <input type="number" id="estoque_atual" name="estoque_atual" min="0" required>
                    </div>

                    <div class="campo">
                        <label for="estoque_minimo">Estoque Mínimo *</label>
                        <input type="number" id="estoque_minimo" name="estoque_minimo" min="0" value="5" required>
                    </div>
                </div>

                <button type="submit" id="btnSalvar">Cadastrar produto</button>
            </form>
        </section>

        <div class="filtros">
            <input type="search" id="busca" placeholder="Consultar produto por nome, EAN ou SKU...">
        </div>

        <p id="mensagem" class="mensagem">Digite o nome, EAN ou SKU acima para consultar produtos cadastrados.</p>

        <section id="lista" class="grade"></section>

    </main>

    <script>
    const lista              = document.getElementById('lista');
    const mensagem           = document.getElementById('mensagem');
    const campoBusca         = document.getElementById('busca');
    const datalistCategorias = document.getElementById('lista_categorias');

    let produtos = [];

    function formatarPreco(valor) {
        if (valor === null || valor === undefined || valor === '') return 'R$ 0,00';
        return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function criarCard(p) {
        const card = document.createElement('article');
        card.className = 'card';

        const corpo = document.createElement('div');
        corpo.className = 'card-corpo';

        const categoria = document.createElement('span');
        categoria.className = 'etiqueta';
        categoria.textContent = (p.categoria_produto || 'Sem categoria') + ' • ' + p.status_produto;

        const nome = document.createElement('h2');
        nome.textContent = p.nome_produto;

        const codigos = document.createElement('p');
        const ean = p.codigo_ean_produto ? `EAN: ${p.codigo_ean_produto}` : '';
        const sku = p.codigo_sku_produto ? `SKU: ${p.codigo_sku_produto}` : '';
        codigos.textContent = [ean, sku].filter(Boolean).join(' | ') || 'Sem código EAN/SKU';

        const estoque = document.createElement('p');
        const alertaEstoque = Number(p.estoque_atual) <= Number(p.estoque_minimo) ? ' (Estoque baixo!)' : '';
        estoque.textContent = `Estoque: ${p.estoque_atual} un. (Mín: ${p.estoque_minimo})${alertaEstoque}`;

        const preco = document.createElement('strong');
        preco.className = 'preco';
        preco.textContent = `Venda: ${formatarPreco(p.preco_venda)}`;

        corpo.append(categoria, nome, codigos, estoque, preco);
        card.append(corpo);
        return card;
    }

    function desenhar() {
        const termo = campoBusca.value.trim().toLowerCase();

        // Mantém a tela limpa se não houver termo digitado (evita rolagem longa com muitos produtos)
        if (termo === '') {
            lista.innerHTML = '';
            mensagem.textContent = 'Digite o nome, EAN ou SKU acima para consultar produtos cadastrados.';
            mensagem.style.display = 'block';
            return;
        }

        const filtrados = produtos.filter(p =>
            p.nome_produto.toLowerCase().includes(termo) ||
            (p.codigo_ean_produto && p.codigo_ean_produto.toLowerCase().includes(termo)) ||
            (p.codigo_sku_produto && p.codigo_sku_produto.toLowerCase().includes(termo))
        );

        lista.innerHTML = '';
        filtrados.slice(0, 20).forEach(p => lista.appendChild(criarCard(p)));

        if (filtrados.length === 0) {
            mensagem.textContent = 'Nenhum produto encontrado.';
            mensagem.style.display = 'block';
        } else if (filtrados.length > 20) {
            mensagem.textContent = `Mostrando 20 de ${filtrados.length} produtos encontrados. Refine a busca pelo EAN ou SKU.`;
            mensagem.style.display = 'block';
        } else {
            mensagem.textContent = '';
            mensagem.style.display = 'none';
        }
    }

    function atualizarSugestoesCategoria() {
        const padrao = [
            'Alimentos',
            'Bebidas',
            'Eletrônicos',
            'Frios e Laticínios',
            'Higiene',
            'Hortifruti',
            'Limpeza',
            'Padaria',
            'Utilidades'
        ];

        const doBanco = produtos.map(p => p.categoria_produto).filter(Boolean);
        const todas = [...new Set([...padrao, ...doBanco])].sort((a, b) => a.localeCompare(b));

        datalistCategorias.innerHTML = '';
        todas.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            datalistCategorias.appendChild(option);
        });
    }

    async function carregar() {
        try {
            const resp = await fetch('listar_produtos.php');
            const dados = await resp.json();

            if (!resp.ok || dados.erro) {
                throw new Error(dados.erro || 'Erro ao carregar.');
            }

            produtos = dados;
            atualizarSugestoesCategoria();
            desenhar();
        } catch (erro) {
            console.error(erro);
            mensagem.textContent = 'Não foi possível carregar os produtos.';
            mensagem.style.display = 'block';
        }
    }

    const form      = document.getElementById('formProduto');
    const alerta    = document.getElementById('alerta');
    const btnSalvar = document.getElementById('btnSalvar');

    function mostrarAlerta(textos, tipo) {
        alerta.hidden = false;
        alerta.className = 'alerta alerta-' + tipo;
        alerta.innerHTML = '';
        textos.forEach(t => {
            const p = document.createElement('p');
            p.textContent = t;
            alerta.appendChild(p);
        });
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            const resp  = await fetch('cadastrar_produtos.php', {
                method: 'POST',
                body: new FormData(form)
            });
            const dados = await resp.json();

            if (!resp.ok) {
                mostrarAlerta(dados.erros || ['Erro ao cadastrar o produto.'], 'erro');
                return;
            }

            mostrarAlerta(['Produto cadastrado com sucesso!'], 'sucesso');
            form.reset();
            await carregar();
        } catch (erro) {
            console.error(erro);
            mostrarAlerta(['Não foi possível enviar o formulário.'], 'erro');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Cadastrar produto';
        }
    });

    campoBusca.addEventListener('input', desenhar);
    atualizarSugestoesCategoria();
    carregar();

    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
    </script>

</body>
</html>