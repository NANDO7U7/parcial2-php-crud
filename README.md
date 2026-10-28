# App 1 — CRUD de productos (PHP nativo + PDO + MySQL)

Parcial 2 · Informática en la Nube. Desplegada en **Railway** con base de datos **MySQL administrada**.

## Variables de entorno (se configuran en el panel de Railway, nunca en el código)

| Variable | Descripción |
|---|---|
| `DB_HOST` | Host de MySQL |
| `DB_PORT` | Puerto (3306) |
| `DB_NAME` | Nombre de la base de datos |
| `DB_USER` | Usuario |
| `DB_PASSWORD` | Contraseña |
| `RAILPACK_PHP_ROOT_DIR` | `/app/public` (carpeta pública servida por la PaaS) |

La tabla `productos` se crea automáticamente en el primer arranque.

## Rutas
- `/` — listado, alta, edición y baja de productos
- `/?accion=health` — estado de la aplicación y de la conexión a la BD (JSON)

Actualizacion automatica Git-Ops
