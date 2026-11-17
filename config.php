<?php
session_start();
$db = new PDO('sqlite:' . __DIR__ . '/tienda.db'); // la base de datos es el archivo tienda.db
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec("
CREATE TABLE IF NOT EXISTS usuarios(id INTEGER PRIMARY KEY AUTOINCREMENT, usuario TEXT UNIQUE, clave TEXT, nombre TEXT, rol TEXT DEFAULT 'cliente', creado TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS proveedores(id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT, telefono TEXT, email TEXT);
CREATE TABLE IF NOT EXISTS productos(id INTEGER PRIMARY KEY AUTOINCREMENT, marca TEXT, nombre TEXT, descripcion TEXT, ram TEXT, almacenamiento TEXT, camara TEXT, carga TEXT, precio_compra REAL, precio_venta REAL, stock INTEGER DEFAULT 0, imagen TEXT, proveedor_id INTEGER);
CREATE TABLE IF NOT EXISTS facturas_compra(id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT DEFAULT CURRENT_TIMESTAMP, producto_id INTEGER, proveedor_id INTEGER, cantidad INTEGER, precio_unitario REAL, total REAL, tipo TEXT DEFAULT 'Entrada');
CREATE TABLE IF NOT EXISTS facturas_venta(id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT DEFAULT CURRENT_TIMESTAMP, usuario_id INTEGER, subtotal REAL, itbis REAL, total REAL, metodo_pago TEXT, tipo TEXT DEFAULT 'Salida');
CREATE TABLE IF NOT EXISTS detalle_venta(id INTEGER PRIMARY KEY AUTOINCREMENT, factura_id INTEGER, producto_id INTEGER, cantidad INTEGER, precio_unitario REAL);
CREATE TABLE IF NOT EXISTS movimientos(id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT DEFAULT CURRENT_TIMESTAMP, producto_id INTEGER, cantidad INTEGER, tipo TEXT);
");
if (!$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn()) {
  $db->prepare("INSERT INTO usuarios(usuario,clave,nombre,rol) VALUES('admin',?,'Administrador','admin')")->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
}
const ITBIS = 0.18;
function e($s){ return htmlspecialchars((string)$s); }
function m($n){ return 'RD$ ' . number_format((float)$n, 2); }
function compra($pid, $prov, $q, $costo){ global $db;
  $db->prepare("INSERT INTO facturas_compra(producto_id,proveedor_id,cantidad,precio_unitario,total) VALUES(?,?,?,?,?)")->execute([$pid,$prov,$q,$costo,$q*$costo]);
  $db->prepare("UPDATE productos SET stock=stock+?, precio_compra=? WHERE id=?")->execute([$q,$costo,$pid]);
  $db->prepare("INSERT INTO movimientos(producto_id,cantidad,tipo) VALUES(?,?,'Entrada')")->execute([$pid,$q]);
}
function tabla($rows){
  if (!$rows) return '<p>Sin datos.</p>';
  $h = '<div class="scroll"><table><tr>';
  foreach (array_keys($rows[0]) as $k) $h .= '<th>'.e($k).'</th>';
  $h .= '</tr>';
  foreach ($rows as $r) { $h .= '<tr>'; foreach ($r as $k=>$v) $h .= '<td>'.($k=='ver' ? $v : e($v)).'</td>'; $h .= '</tr>'; }
  return $h . '</table></div>';
}
function top($t){
  $u = $_SESSION['usuario'] ?? null; $n = array_sum($_SESSION['carrito'] ?? []);
  echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).' - Ismael Phones</title><link rel="stylesheet" href="style.css"></head><body><header><a class="logo" href="index.php"><img src="media/logo.png" alt="Ismael Phones" onerror="this.outerHTML=\'Ismael Phones\'"></a><nav><a href="index.php">Inicio</a><div class="dd"><a href="index.php#catalogo" tabindex="0">Teléfonos ▾</a><div class="dd-menu"><a href="index.php?marca=iPhone#catalogo">🍎 iPhone</a><a href="index.php?marca=Samsung#catalogo">📱 Samsung</a><a href="index.php#catalogo">🛒 Ver todos</a></div></div><a href="carrito.php">Carrito ('.$n.')</a>';
  if ($u) { if ($_SESSION['rol']=='admin') echo '<a href="admin.php">Administrador</a>'; echo '<a href="logout.php">Salir ('.e($u).')</a>'; }
  else echo '<a href="login.php">Entrar</a><a href="registro.php">Registrarme</a>';
  echo '</nav></header><main>';
}
function bottom(){ echo '</main><footer>Instagram: ismael20902 · WhatsApp: 849-378-2318</footer></body></html>'; }

function auth_top($t){
  echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($t).' - Ismael Phones</title><link rel="stylesheet" href="style.css"></head><body class="auth">';
}

// ---------- Carrito e inventario ----------
function ajustar_carrito(){ global $db; $av = [];
  foreach ($_SESSION['carrito'] ?? [] as $id => $q) {
    $s = $db->prepare("SELECT nombre,stock FROM productos WHERE id=?"); $s->execute([$id]); $p = $s->fetch();
    if (!$p || $p['stock'] < 1) { unset($_SESSION['carrito'][$id]); $av[] = ($p['nombre'] ?? 'Un producto').' ya no está disponible.'; }
    elseif ($q > $p['stock']) { $_SESSION['carrito'][$id] = $p['stock']; $av[] = 'Ajustamos '.$p['nombre'].' a '.$p['stock'].' unidad(es), que es lo que hay en inventario.'; }
  } return $av; }
function carrito_items(){ global $db; $items = []; $sub = 0;
  foreach ($_SESSION['carrito'] ?? [] as $id => $q) {
    $s = $db->prepare("SELECT * FROM productos WHERE id=?"); $s->execute([$id]); $p = $s->fetch();
    if ($p) { $items[] = [$p, $q]; $sub += $p['precio_venta'] * $q; } }
  return [$items, $sub]; }
function v($k){ return e($_POST[$k] ?? ''); }

// ---------- Compras a proveedores (cabecera) ----------
$db->exec("CREATE TABLE IF NOT EXISTS compras(id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT DEFAULT CURRENT_TIMESTAMP, proveedor_id INTEGER, subtotal REAL, itbis REAL, total REAL, tipo TEXT DEFAULT 'Entrada')");
if (!in_array('compra_id', array_column($db->query("PRAGMA table_info(facturas_compra)")->fetchAll(), 'name'))) $db->exec("ALTER TABLE facturas_compra ADD COLUMN compra_id INTEGER");

// Las fechas se guardan en UTC; aquí se muestran en hora de República Dominicana
function fecha_rd($s){ return (new DateTime($s, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Santo_Domingo'))->format('d/m/Y h:i A'); }
