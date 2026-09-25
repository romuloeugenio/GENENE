-- GERENE: selecione o banco gerene_local antes de importar este arquivo.
-- O banco pode ser criado com: CREATE DATABASE gerene_local CHARACTER SET utf8mb4;
SET NAMES utf8mb4;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    pode_editar ENUM('S', 'N') NOT NULL DEFAULT 'N',
    pode_excluir ENUM('S', 'N') NOT NULL DEFAULT 'N',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_email (email),
    KEY idx_usuario_permissoes (pode_editar, pode_excluir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usinas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo ENUM('Fotovoltaica', 'Hidrelétrica') NOT NULL,
    capacidade_kw DECIMAL(12,2) UNSIGNED NOT NULL,
    localizacao VARCHAR(255) NOT NULL,
    status ENUM('Ativa', 'Inativa', 'Manutenção') NOT NULL DEFAULT 'Ativa',
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usina_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tecnicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    cpf CHAR(11) NOT NULL,
    especialidade VARCHAR(120) NOT NULL,
    telefone VARCHAR(25) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tecnico_cpf (cpf),
    KEY idx_tecnico_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE producao_energia (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    data_producao DATE NOT NULL,
    kwh_gerado DECIMAL(12,2) UNSIGNED NOT NULL,
    UNIQUE KEY uq_producao_usina_data (usina_id, data_producao),
    KEY idx_producao_data (data_producao),
    CONSTRAINT fk_producao_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horas_extras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    quantidade_horas DECIMAL(12,2) UNSIGNED NOT NULL,
    valor_total DECIMAL(12,2) UNSIGNED NOT NULL,
    KEY idx_horas_usina_data (usina_id, data),
    KEY idx_horas_tecnico (tecnico_id),
    CONSTRAINT fk_horas_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_horas_tecnico FOREIGN KEY (tecnico_id)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diarias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    valor_diaria DECIMAL(12,2) UNSIGNED NOT NULL,
    descricao TEXT NOT NULL,
    KEY idx_diarias_usina_data (usina_id, data),
    KEY idx_diarias_tecnico (tecnico_id),
    CONSTRAINT fk_diarias_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_diarias_tecnico FOREIGN KEY (tecnico_id)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categoria (
    id_categoria INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao_categoria VARCHAR(120) NOT NULL,
    Centro_de_custo INT NULL,
    UNIQUE KEY uq_categoria_descricao (descricao_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE despesas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usina_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    valor_despesa DECIMAL(12,2) UNSIGNED NOT NULL,
    descricao TEXT NOT NULL,
    categoria INT UNSIGNED NOT NULL,
    tecnico INT UNSIGNED NULL,
    KEY idx_despesas_categoria (categoria),
    KEY idx_despesas_tecnico (tecnico),
    KEY idx_material_usina_data (usina_id, data),
    CONSTRAINT fk_material_usina FOREIGN KEY (usina_id)
        REFERENCES usinas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_despesas_categoria FOREIGN KEY (categoria)
        REFERENCES categoria(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_despesas_tecnico FOREIGN KEY (tecnico)
        REFERENCES tecnicos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário inicial. Senha: admin.
INSERT INTO usuarios (nome, email, senha_hash, pode_editar, pode_excluir)
VALUES (
    'Administrador',
    'admin@usina.com',
    '$2y$12$Vvvjkm3RKtN15XhGN05rm.Dh1gWWCCGYNpSJclntuZykW.PJmERm6',
    'S',
    'S'
);
