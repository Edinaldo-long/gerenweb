<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT
                id_produto,
                codigo_ean_produto,
                codigo_sku_produto,
                nome_produto,
                categoria_produto,
                preco_custo,
                preco_venda,
                estoque_atual,
                estoque_minimo,
                status_produto,
                data_cadastro
            FROM tblprodutos
            ORDER BY id_produto DESC";

    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);

} catch (PDOException $ex) {
    http_response_code(500);
    error_log($ex->getMessage());
    echo json_encode(['erro' => 'Não foi possível carregar os produtos.'], JSON_UNESCAPED_UNICODE);
}