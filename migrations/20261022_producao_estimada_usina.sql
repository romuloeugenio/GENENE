CREATE TABLE producao_estimada_usina (
    id_usina INT UNSIGNED NOT NULL,
    mes_estimado DATE NOT NULL,
    producao DECIMAL(12,2) UNSIGNED NOT NULL,
    PRIMARY KEY (id_usina, mes_estimado),
    KEY idx_producao_estimada_mes (mes_estimado),
    CONSTRAINT fk_producao_estimada_usina FOREIGN KEY (id_usina)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
