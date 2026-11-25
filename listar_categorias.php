<?php

require_once 'conexao.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $categorias = $pdo
        ->query('SELECT id_categoria, nome_categoria FROM categorias ORDER BY nome_categoria')
        ->fetchAll();

    echo json_encode($categorias, JSON_UNESCAPED_UNICODE);

} catch (PDOException $ex) {
    http_response_code(500);
    error_log($ex->getMessage());
    echo json_encode(['erro' => 'Não foi possível carregar as categorias.']);
}