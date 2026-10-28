-- Execute uma única vez em bancos existentes. Usuários atuais recebem 'N'.
ALTER TABLE usuarios
    ADD COLUMN administrador VARCHAR(1) NOT NULL DEFAULT 'N';