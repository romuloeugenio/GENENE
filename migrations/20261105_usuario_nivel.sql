-- Usuários existentes recebem o nível 1; novos usuários também usam esse padrão.
ALTER TABLE usuarios
    ADD COLUMN Nivel_Usuario VARCHAR(1) NOT NULL DEFAULT '1';
