# Integración Lime CRM → Pipo

Pipo lee (solo lectura) los mensajes de **WhatsApp, Instagram y Messenger** de una empresa de Lime CRM y los resume.
Funciona así: Lime expone un endpoint protegido con un token personal, y Pipo le pide los mensajes nuevos cada 5 minutos
(y también cuando le preguntás algo al asistente o tocás "Traer mensajes").

```
Pipo (cron cada 5 min) ──GET api.php?action=pipo_mensajes&desde_id=N──▶ Lime CRM
                       ◀── mensajes nuevos + siguiente_desde_id ────────
```

- **Solo lectura:** el token no permite enviar mensajes ni modificar nada en Lime.
- **Por empresa:** el token queda atado a una empresa (por ejemplo, la futura empresa "Benja") y a un usuario.
  - Alcance `empresa`: todos los chats de esa empresa.
  - Alcance `mis_chats`: solo los chats asignados a ese usuario.
- **No se exponen:** notas internas, charlas internas de Slack, mensajes eliminados ni datos de otras empresas.
- En la base de Lime se guarda solo el **hash SHA-256** del token.
- Más adelante, el MCP de Lime puede reutilizar la misma tabla `api_tokens` y la función `pipoAutenticar()`.

## Archivos para agregar a Lime

| Archivo de esta carpeta | Dónde va en `lime.crm` |
|---|---|
| `migrate_api_tokens.sql` | raíz (correrla una vez en la base, como el resto de las `migrate_*.sql`) |
| `lib_api_pipo.php` | raíz |
| `crear_token_pipo.php` | `scripts/crear_token_pipo.php` |

## Cambios en `api.php` (3 líneas)

1. Junto a los otros `require_once` de librerías (cerca de `require_once __DIR__ . '/lib_cobros.php';`):

```php
require_once __DIR__ . '/lib_api_pipo.php';
```

2. En `const PUBLIC_ACTIONS = [ ... ]` agregá la acción. Es pública porque no usa sesión: se valida con el token Bearer dentro de la función, igual que los webhooks se validan por firma.

```php
    // Pipo (asistente personal): API de solo lectura, se valida con el token Bearer adentro (lib_api_pipo.php).
    'pipo_mensajes',
```

3. En el `match ($action)` del router, junto a `'wa_webhook' => action_wa_webhook(),`:

```php
    'pipo_mensajes'   => action_pipo_mensajes(),
```

> El encabezado `Authorization` a veces no le llega a PHP en Apache/LiteSpeed. `lib_api_pipo.php` ya lo busca también en `REDIRECT_HTTP_AUTHORIZATION` y `getallheaders()`. Si igual responde "Falta el token", agregá esto al `.htaccess` de Lime:
> ```apache
> RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
> ```

## Crear el token

Cuando exista la empresa "Benja" en Lime, por SSH en la carpeta de Lime:

```bash
php scripts/crear_token_pipo.php <empresa_id> tu-email@dominio.com empresa
```

Muestra el token **una sola vez**. Copialo en Pipo → Configuración → **Lime CRM** junto con la dirección `https://crmlime.com`.

Para revocar un token:

```sql
UPDATE api_tokens SET revocado_en = NOW() WHERE id = <id>;
```

## Respuesta del endpoint

`GET https://crmlime.com/api.php?action=pipo_mensajes&desde_id=0&limite=200`, con el encabezado `Authorization: Bearer <token>`:

```json
{
  "ok": true,
  "data": {
    "mensajes": [
      { "id": 1, "chat_id": 1, "canal": "whatsapp", "contacto": "Juan Pérez", "telefono": "5493510000001",
        "direccion": "in", "autor": "usuario", "tipo": "text", "texto": "Hola, ¿tenés lugar?", "timestamp": 1794823200 }
    ],
    "siguiente_desde_id": 1,
    "hay_mas": false
  }
}
```

- `direccion`: `in` si escribió el cliente (`rol = usuario`), `out` si respondió la empresa (agente, bot o la app del celular).
- `timestamp`: Unix (UTC), así no hay dudas con la zona horaria.
- Límite: 500 mensajes por pedido. Pipo pagina con `siguiente_desde_id` mientras `hay_mas` sea `true`.

## Probado

Probé el endpoint contra el `schema.sql` real de Lime, cargado en MariaDB, con dos empresas, chats de WhatsApp e Instagram, una nota interna, una charla de Slack y un chat de otra empresa:
- Devuelve solo lo que corresponde.
- Pagina bien y respeta el alcance `mis_chats`.
- Rechaza sin token, con token inválido y por POST.

Después conecté Pipo a esa API de prueba de punta a punta: se conecta, importa, no duplica al sincronizar de nuevo y el cron funciona.
