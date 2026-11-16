-- Lime CRM: tokens de acceso de solo lectura para integraciones externas (Pipo y, a futuro, el MCP de Lime).
-- Se guarda solo el hash SHA-256 del token: si alguien lee la base, no obtiene tokens usables.
CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT          NOT NULL,
    usuario_id   INT          NOT NULL,  -- dueño del token: Pipo ve lo que este usuario ve
    nombre       VARCHAR(100) NOT NULL,  -- ej. "Pipo"
    token_hash   CHAR(64)     NOT NULL,
    alcance      ENUM('empresa','mis_chats') NOT NULL DEFAULT 'empresa',
    ultimo_uso   DATETIME     NULL,
    revocado_en  DATETIME     NULL,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token_hash (token_hash),
    INDEX idx_empresa (empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
