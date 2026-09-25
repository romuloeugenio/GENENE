-- Execute uma única vez no banco antigo. Faça backup antes da migração.
-- Mantém IDs, valores e vínculos existentes das despesas.
CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao_categoria VARCHAR(120) NOT NULL,
    Centro_de_custo INT NULL,
    UNIQUE KEY uq_categoria_descricao (descricao_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categoria (descricao_categoria)
SELECT DISTINCT CASE WHEN TRIM(categoria) = '' THEN 'Sem categoria' ELSE TRIM(categoria) END
FROM despesas_material;

RENAME TABLE despesas_material TO despesas;

ALTER TABLE despesas
    ADD COLUMN categoria_nova INT UNSIGNED NULL,
    ADD COLUMN tecnico INT UNSIGNED NULL;

UPDATE despesas d
INNER JOIN categoria c
    ON c.descricao_categoria = CASE WHEN TRIM(d.categoria) = '' THEN 'Sem categoria' ELSE TRIM(d.categoria) END
SET d.categoria_nova = c.id_categoria;

ALTER TABLE despesas
    DROP COLUMN categoria,
    CHANGE COLUMN categoria_nova categoria INT UNSIGNED NOT NULL,
    ADD KEY idx_despesas_categoria (categoria),
    ADD KEY idx_despesas_tecnico (tecnico),
    ADD CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria)
        REFERENCES categoria(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD CONSTRAINT fk_despesas_tecnico FOREIGN KEY (tecnico)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE;
