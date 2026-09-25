<?php

require_once __DIR__ . '/config.php';

/**
 * Abre a conexão PDO e a reutiliza durante a requisição.
 *
 * Parâmetros: nenhum. Lê os dados de config.php.
 * @return PDO Conexão com o MySQL; encerra a página se não conseguir conectar.
 */
function conectarBanco()
{
    static $conexao = null;

    if ($conexao === null) {
        try {
            $endereco = 'mysql:host=' . DB_HOST;
            $endereco .= ';port=' . DB_PORT;
            $endereco .= ';dbname=' . DB_NAME;
            $endereco .= ';charset=utf8mb4';

            $conexao = new PDO($endereco, DB_USER, DB_PASS);
            $conexao->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conexao->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $conexao->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $erro) {
            error_log($erro->getMessage());
            http_response_code(503);
            exit('Não foi possível conectar. Confira config.php e inicie o MySQL no XAMPP.');
        }
    }

    return $conexao;
}
