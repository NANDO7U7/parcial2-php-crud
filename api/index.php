<?php
declare(strict_types=1);

/**
 * CRUD de productos - PHP nativo + PDO + MySQL.
 * Las credenciales NO están en el código: se leen de variables de entorno
 * (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD) definidas en el panel de la PaaS.
 */

function env(string $clave, ?string $defecto = null): ?string
{
    $valor = getenv($clave);
    return ($valor === false || $valor === '') ? $defecto : $valor;
}

function h(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function conectar(): PDO
{
    $host = env('DB_HOST');
    $puerto = env('DB_PORT', '3306');
    $base = env('DB_NAME');
    $usuario = env('DB_USER');
    $clave = env('DB_PASSWORD', '');

    if (!$host || !$base || !$usuario) {
        throw new RuntimeException('Faltan variables de entorno de la base de datos (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD).');
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4",
        $usuario,
        (string) $clave,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS productos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(120) NOT NULL,
            descripcion TEXT NULL,
            precio DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock INT UNSIGNED NOT NULL DEFAULT 0,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    return $pdo;
}

/** @return array{0: array<string,string>, 1: array<string,mixed>} errores y datos limpios */
function validar(array $entrada): array
{
    $errores = [];
    $nombre = trim((string) ($entrada['nombre'] ?? ''));
    $descripcion = trim((string) ($entrada['descripcion'] ?? ''));
    $precio = str_replace(',', '.', trim((string) ($entrada['precio'] ?? '')));
    $stock = trim((string) ($entrada['stock'] ?? ''));

    $largo = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);
    if ($nombre === '' || $largo > 120) {
        $errores['nombre'] = 'El nombre es obligatorio (máximo 120 caracteres).';
    }
    if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > 99999999.99) {
        $errores['precio'] = 'El precio debe ser un número mayor o igual a 0.';
    }
    if (!ctype_digit($stock) || (int) $stock > 1000000) {
        $errores['stock'] = 'El stock debe ser un entero mayor o igual a 0.';
    }

    return [$errores, [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'precio' => is_numeric($precio) ? round((float) $precio, 2) : 0,
        'stock' => ctype_digit($stock) ? (int) $stock : 0,
    ]];
}

function vista(string $titulo, string $contenido, string $pie = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?></title>
<style>
  :root { --azul:#0369a1; --oscuro:#0f172a; --gris:#f1f5f9; --rojo:#b91c1c; --verde:#15803d; }
  * { box-sizing: border-box; }
  body { font-family: system-ui, Segoe UI, Arial, sans-serif; margin:0; background:var(--gris); color:var(--oscuro); }
  header { background:var(--oscuro); color:#fff; padding:18px 24px; }
  header h1 { margin:0; font-size:1.3rem; }
  header small { color:#94a3b8; }
  main { max-width:900px; margin:24px auto; padding:0 16px; }
  .tarjeta { background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.1); margin-bottom:20px; }
  table { width:100%; border-collapse:collapse; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #e2e8f0; font-size:.95rem; }
  th { background:var(--gris); }
  label { display:block; margin-top:12px; font-weight:600; font-size:.9rem; }
  input, textarea { width:100%; padding:9px; border:1px solid #cbd5e1; border-radius:6px; font:inherit; }
  .fila { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  .btn { display:inline-block; background:var(--azul); color:#fff; border:0; padding:9px 16px; border-radius:6px; cursor:pointer; text-decoration:none; font:inherit; font-size:.9rem; }
  .btn.sec { background:#64748b; }
  .btn.peligro { background:var(--rojo); }
  .acciones { display:flex; gap:6px; }
  .acciones form { margin:0; }
  .aviso { padding:10px 14px; border-radius:6px; margin-bottom:16px; background:#dcfce7; color:var(--verde); }
  .error { color:var(--rojo); font-size:.85rem; margin:4px 0 0; }
  footer { text-align:center; color:#64748b; font-size:.8rem; padding:12px 0 28px; }
</style>
</head>
<body>
<header>
  <h1>Inventario de productos</h1>
  <small>App 1 · PHP nativo + PDO + MySQL administrado · Desplegada en Railway</small>
</header>
<main>
<?= $contenido ?>
</main>
<footer><?= $pie ?></footer>
</body>
</html>
    <?php
}

$accion = (string) ($_POST['accion'] ?? $_GET['accion'] ?? 'listar');

try {
    $pdo = conectar();
} catch (Throwable $e) {
    error_log('Error de conexión a la BD: ' . $e->getMessage());
    http_response_code(500);
    if ($accion === 'health') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'db' => 'sin conexión']);
        exit;
    }
    $detalle = $e instanceof RuntimeException ? h($e->getMessage()) : 'No se pudo conectar a la base de datos.';
    vista('Error', '<div class="tarjeta"><h2>Servicio no disponible</h2><p>' . $detalle . '</p></div>');
    exit;
}

if ($accion === 'health') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'db' => 'conectada', 'mysql' => $pdo->query('SELECT VERSION()')->fetchColumn()]);
    exit;
}

$errores = [];
$datos = ['nombre' => '', 'descripcion' => '', 'precio' => '', 'stock' => ''];
$editandoId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($accion === 'crear') {
        [$errores, $limpios] = validar($_POST);
        if (!$errores) {
            $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, stock) VALUES (?, ?, ?, ?)')
                ->execute([$limpios['nombre'], $limpios['descripcion'] ?: null, $limpios['precio'], $limpios['stock']]);
            header('Location: /?msg=creado');
            exit;
        }
        $datos = array_merge($datos, $_POST);
    } elseif ($accion === 'actualizar') {
        $editandoId = (int) ($_POST['id'] ?? 0);
        [$errores, $limpios] = validar($_POST);
        if (!$errores) {
            $pdo->prepare('UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, stock = ? WHERE id = ?')
                ->execute([$limpios['nombre'], $limpios['descripcion'] ?: null, $limpios['precio'], $limpios['stock'], $editandoId]);
            header('Location: /?msg=actualizado');
            exit;
        }
        $datos = array_merge($datos, $_POST);
    } elseif ($accion === 'eliminar') {
        $pdo->prepare('DELETE FROM productos WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        header('Location: /?msg=eliminado');
        exit;
    }
} elseif ($accion === 'editar') {
    $editandoId = (int) ($_GET['id'] ?? 0);
    $fila = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $fila->execute([$editandoId]);
    $encontrado = $fila->fetch();
    if (!$encontrado) {
        header('Location: /');
        exit;
    }
    $datos = $encontrado;
}

$mensajes = ['creado' => 'Producto creado correctamente.', 'actualizado' => 'Producto actualizado.', 'eliminado' => 'Producto eliminado.'];
$aviso = isset($_GET['msg'], $mensajes[$_GET['msg']]) ? '<div class="aviso">' . h($mensajes[$_GET['msg']]) . '</div>' : '';

$productos = $pdo->query('SELECT * FROM productos ORDER BY id DESC')->fetchAll();
$version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

ob_start();
?>
<?= $aviso ?>
<div class="tarjeta">
  <h2><?= $editandoId ? 'Editar producto #' . (int) $editandoId : 'Nuevo producto' ?></h2>
  <form method="post" action="/">
    <input type="hidden" name="accion" value="<?= $editandoId ? 'actualizar' : 'crear' ?>">
    <?php if ($editandoId): ?><input type="hidden" name="id" value="<?= (int) $editandoId ?>"><?php endif; ?>
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="120" value="<?= h((string) $datos['nombre']) ?>" required>
    <?php if (isset($errores['nombre'])): ?><p class="error"><?= h($errores['nombre']) ?></p><?php endif; ?>
    <label for="descripcion">Descripción</label>
    <textarea id="descripcion" name="descripcion" rows="2"><?= h((string) $datos['descripcion']) ?></textarea>
    <div class="fila">
      <div>
        <label for="precio">Precio (USD)</label>
        <input id="precio" name="precio" inputmode="decimal" value="<?= h((string) $datos['precio']) ?>" required>
        <?php if (isset($errores['precio'])): ?><p class="error"><?= h($errores['precio']) ?></p><?php endif; ?>
      </div>
      <div>
        <label for="stock">Stock</label>
        <input id="stock" name="stock" inputmode="numeric" value="<?= h((string) $datos['stock']) ?>" required>
        <?php if (isset($errores['stock'])): ?><p class="error"><?= h($errores['stock']) ?></p><?php endif; ?>
      </div>
    </div>
    <p>
      <button class="btn" type="submit"><?= $editandoId ? 'Guardar cambios' : 'Crear producto' ?></button>
      <?php if ($editandoId): ?><a class="btn sec" href="/">Cancelar</a><?php endif; ?>
    </p>
  </form>
</div>

<div class="tarjeta">
  <h2>Productos registrados (<?= count($productos) ?>)</h2>
  <?php if (!$productos): ?>
    <p>Aún no hay productos. Crea el primero con el formulario.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($productos as $p): ?>
      <tr>
        <td><?= (int) $p['id'] ?></td>
        <td><strong><?= h($p['nombre']) ?></strong><br><small><?= h($p['descripcion']) ?></small></td>
        <td>$<?= number_format((float) $p['precio'], 2) ?></td>
        <td><?= (int) $p['stock'] ?></td>
        <td class="acciones">
          <a class="btn sec" href="/?accion=editar&id=<?= (int) $p['id'] ?>">Editar</a>
          <form method="post" action="/" onsubmit="return confirm('¿Eliminar este producto?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn peligro" type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php
$contenido = (string) ob_get_clean();
vista('Inventario de productos', $contenido, 'PHP ' . h(PHP_VERSION) . ' · MySQL ' . h($version) . ' · <a href="/?accion=health">estado</a>');
