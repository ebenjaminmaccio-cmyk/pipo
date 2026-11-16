# Pipo — Asistente personal con IA

Web app de asistente personal hecha en **PHP 8.2+ (estricto, moderno)**, **MySQL/MariaDB** y **HTML/CSS/JS** sin frameworks.
Pensada para alojarse en hosting compartido como **Hostinger** o **DonWeb**.

Inspirada en la arquitectura de [OpenJarvis](https://github.com/open-jarvis/OpenJarvis) (conectores, *morning digest*,
memoria, aprobación antes de actuar), pero reimplementada en PHP + SQL para que corra en cualquier hosting.

## Qué hace

| Módulo | Funciones |
|---|---|
| **Asistente (chat)** | Le pedís cosas en lenguaje natural: "agendame con Juan el jueves a las 10", "recordame mañana llamar al contador", "resumime los mails sin leer", "¿qué me escribieron por WhatsApp?". Usa **Claude** (Anthropic) con herramientas. |
| **Agenda** | Reuniones en la base local y sincronizadas con **Google Calendar** (invita a los asistentes). Aviso por mail antes de cada reunión. |
| **Tareas y recordatorios** | Prioridad, vencimiento y aviso por mail a la hora indicada. |
| **Mail** | **Gmail** (API oficial, OAuth) y cualquier casilla **IMAP/SMTP** (Hostinger, Outlook, etc.). Resumen con IA y prioridad de cada mail. El asistente redacta respuestas como **borradores que vos aprobás**: nunca envía nada solo. |
| **WhatsApp** | Recibe mensajes desde **tu CRM por webhook** (o la API oficial de WhatsApp Cloud) y permite **importar chats exportados** (.txt). Resúmenes por chat o generales. |
| **Resumen diario** | Cada mañana, a la hora que elijas, te llega por mail un briefing con agenda, prioridades, mails y WhatsApp. |
| **Memoria** | Pipo recuerda datos tuyos ("mi socio es Martín", "no agendar antes de las 9"). |

## Seguridad

- PHP con `declare(strict_types=1)` en todos los archivos y PDO con **consultas preparadas**, para prevenir inyección SQL.
- Las contraseñas se guardan con **Argon2id**. El login tiene **límite de intentos** (5 cada 15 min por IP/email) y **2FA (TOTP)** opcional, compatible con Google Authenticator y Authy.
- Los **tokens de Google y las contraseñas IMAP se guardan cifrados** con libsodium (XSalsa20-Poly1305).
- La protección **CSRF** cubre todos los formularios y las llamadas AJAX. Toda salida HTML se escapa contra XSS.
- Cabeceras de seguridad: una **Content Security Policy** estricta (sin scripts inline), HSTS, `X-Frame-Options` y `nosniff`.
- Cookies de sesión `HttpOnly`, `Secure`, `SameSite` y con prefijo `__Host-`. La sesión expira tras 2 h de inactividad.
- Solo la carpeta `public/` es accesible desde la web. El código, la configuración y las dependencias quedan fuera de `public_html`.
- **Defensa contra *prompt injection*:** el contenido de mails y WhatsApp se marca como externo. El asistente no lo obedece como orden y **no puede enviar mails sin tu aprobación**.
- Todo es **multiusuario** desde el diseño: cada fila tiene `user_id`, así que se puede comercializar sin migrar datos.
- Hay una **auditoría** (`audit_log`) de logins, envíos y acciones de la IA, y un registro de **consumo de IA** por usuario (`ai_usage`) para controlar costos o facturar.

## Estructura

```
pipo/
├── public/            ← lo ÚNICO que va dentro de public_html
│   ├── index.php      (front controller + rutas)
│   ├── .htaccess
│   └── assets/        (app.css, app.js)
├── src/
│   ├── Core/          (Database, Auth, Session, Csrf, Crypto, Totp, View…)
│   ├── Services/      (Assistant, Claude, MailService, CalendarService, WhatsAppService, DigestService, CronService…)
│   └── Controllers/
├── views/             (plantillas HTML/PHP)
├── database/schema.sql
├── config/config.example.php
├── bin/               (install.php, cron.php, build-zip.sh)
└── storage/logs/
```

## Instalación en Hostinger (o DonWeb)

### 1. Base de datos
En hPanel → **Bases de datos MySQL**, creá una base y un usuario. Anotá el nombre, el usuario y la contraseña.

### 2. Subir los archivos
Estructura recomendada en el hosting:
```
/home/USUARIO/
├── pipo/                 ← todo el proyecto (src, views, vendor, config, bin…)
└── public_html/          ← el CONTENIDO de pipo/public/ (index.php, .htaccess, assets/)
```
`public/index.php` detecta solo si el proyecto está en `../` o en `../pipo/`.

- **Con SSH** (Hostinger Premium/Business), clonás el repo, entrás a la carpeta e instalás las dependencias:
  ```bash
  git clone https://github.com/ebenjaminmaccio-cmyk/pipo.git ~/pipo
  ```
  ```bash
  cd ~/pipo
  ```
  ```bash
  composer install --no-dev -o
  ```
  Después copiá el contenido de `~/pipo/public/` a `~/public_html/`.
- **Sin SSH** (por ejemplo, algunos planes de DonWeb): en tu compu corré `bin/build-zip.sh`, que genera `pipo-deploy.zip` con `vendor/` incluido. Subilo con el Administrador de archivos y descomprimilo.

> **Plan B:** si solo podés subir todo dentro de `public_html`, el `.htaccess` de la raíz redirige todo a `public/`. Así no queda expuesto el código ni la configuración.

### 3. Configuración
Copiá `config/config.example.php` como `config/config.php` y completá los datos:
- `app.url`: tu dominio con `https://`.
- `app.key`: generala con `php -r "echo base64_encode(random_bytes(32));"`. No la pierdas: si cambia, hay que reconectar las cuentas.
- `db.*`: los datos del paso 1.
- `anthropic.api_key`: sacala de https://console.anthropic.com.
- `smtp.*`: una casilla de tu dominio (en Hostinger: `smtp.hostinger.com`, puerto 465, ssl) para enviar recordatorios.
- `cron_token`: un texto aleatorio largo.

### 4. Crear las tablas
- Con SSH, corré:
  ```bash
  php ~/pipo/bin/install.php
  ```
- Sin SSH: en hPanel → **phpMyAdmin** → *Importar* → subí `database/schema.sql`.

Después entrá a `https://tudominio.com/instalar` y creá tu usuario. Esta pantalla se desactiva sola cuando ya existe uno.

### 5. Cron (recordatorios, sincronización de mail y resumen diario)
En hPanel → **Avanzado → Cron Jobs**, configuralo **cada 5 minutos**:
```bash
/usr/bin/php /home/USUARIO/pipo/bin/cron.php
```
Si tu plan solo permite cron por URL:
```bash
curl -s "https://tudominio.com/cron?token=TU_CRON_TOKEN"
```

### 6. Versión de PHP
Elegí **PHP 8.3** (o 8.2+) en hPanel. Tienen que estar activas las extensiones `pdo_mysql`, `sodium`, `curl`, `mbstring`, `openssl` e `iconv`, que vienen por defecto en Hostinger.

## Conectar Google (Gmail + Calendar)
1. Entrá a https://console.cloud.google.com y creá un proyecto.
2. En **APIs y servicios → Biblioteca**, habilitá **Gmail API** y **Google Calendar API**.
3. En la **pantalla de consentimiento de OAuth**, elegí tipo "Externo", agregá tu email como usuario de prueba y sumá estos scopes: `gmail.modify` y `calendar.events`.
4. En **Credenciales → Crear ID de cliente OAuth → Aplicación web**, cargá como URI de redirección `https://tudominio.com/google/callback`.
5. Copiá el *Client ID* y el *Secret* en `config.php`.
6. En Pipo, andá a **Configuración → Conectar Gmail y Google Calendar**.

> Para comercializarlo con muchos usuarios, Google exige **verificar la app**, porque `gmail.modify` es un scope restringido. Mientras la uses vos (y hasta 100 usuarios de prueba) no hace falta.

## WhatsApp desde Lime CRM
Pipo se conecta a **Lime CRM** (crmlime.com) con un token de solo lectura y trae cada 5 minutos los mensajes de WhatsApp, Instagram y Messenger de tu empresa. Las instrucciones y el código para agregar a Lime están en [`integraciones/lime-crm/`](integraciones/lime-crm/README.md). Después de agregarlo, conectalo en Pipo → **Configuración → Lime CRM**.

## WhatsApp desde otro CRM (webhook)
En **WhatsApp** dentro de Pipo vas a ver una **URL secreta de webhook**. Configurala en tu CRM para que envíe un `POST` con JSON por cada mensaje:

```json
{ "chat": "Juan Pérez", "sender": "Juan Pérez", "body": "Hola!", "direction": "in", "timestamp": 1731700000, "id": "abc123" }
```

También acepta una lista (`{"messages": [...]}`) y el formato nativo de **WhatsApp Cloud API** de Meta: usá la misma URL y, como *Verify token*, el valor de `token`. Si tu CRM usa otro formato, decime cuál es y lo adapto en `src/Services/WhatsAppService.php`.

## Desarrollo local
Cargá el esquema en la base local y levantá el servidor:
```bash
mysql -u root pipo < database/schema.sql
```
```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

## Modelo de IA
Usa `claude-opus-5-5` mediante el SDK oficial de Anthropic para PHP, con *fallback* automático del lado del servidor (si el modelo rechaza un pedido, la API reintenta con el modelo de respaldo). Podés cambiar el modelo y el nivel de esfuerzo (`effort`) en `config.php`. El consumo queda registrado en Configuración → Consumo de IA.
