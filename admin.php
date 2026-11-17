<?php require 'config.php';
if (($_SESSION['rol'] ?? '') != 'admin') { header('Location: login.php'); exit; }
$t = $_GET['t'] ?? ($db->query("SELECT COUNT(*) FROM proveedores")->fetchColumn() ? 'productos' : 'proveedores'); $msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') { $a = $_POST['a'] ?? ''; if (!$a) $msg = 'El archivo es demasiado grande para el servidor (sube upload_max_filesize y post_max_size en php.ini).';
  try {
    if ($a == 'nuevo') {
      $img = '';
      if (!empty($_FILES['imagen']['name'])) { $img = 'uploads/'.time().'_'.preg_replace('/[^\w.\-]/', '_', $_FILES['imagen']['name']); move_uploaded_file($_FILES['imagen']['tmp_name'], $img); }
      $pid = (int)($_POST['proveedor_id'] ?? 0); if (!$pid) throw new Exception('Primero registra un proveedor y elígelo.');
      $db->prepare("INSERT INTO productos(marca,nombre,descripcion,ram,almacenamiento,camara,carga,precio_compra,precio_venta,stock,imagen,proveedor_id) VALUES(?,?,?,?,?,?,?,?,?,0,?,?)")
         ->execute([$_POST['marca'], $_POST['nombre'], $_POST['descripcion'], $_POST['ram'], $_POST['almacenamiento'], $_POST['camara'], $_POST['carga'], $_POST['costo'], $_POST['precio'], $img, $pid]);
      $msg = 'Teléfono agregado al catálogo con stock 0. Regístrale una compra al proveedor para que tenga existencias.';
    }
    if ($a == 'editar') { $db->prepare("UPDATE productos SET precio_venta=?, descripcion=?, stock=? WHERE id=?")->execute([$_POST['precio'], $_POST['descripcion'], (int)$_POST['stock'], $_POST['id']]); $msg = 'Producto actualizado.'; }
    if ($a == 'borrar') { $db->prepare("DELETE FROM productos WHERE id=?")->execute([$_POST['id']]); $msg = 'Producto eliminado.'; }
    if ($a == 'prov_nuevo') { $db->prepare("INSERT INTO proveedores(nombre,telefono,email) VALUES(?,?,?)")->execute([trim($_POST['nombre']), trim($_POST['telefono']), trim($_POST['email'])]); $msg = 'Proveedor agregado. Ya puedes registrar teléfonos y compras con él.'; }
    if ($a == 'prov_editar') { $db->prepare("UPDATE proveedores SET nombre=?, telefono=?, email=? WHERE id=?")->execute([trim($_POST['nombre']), trim($_POST['telefono']), trim($_POST['email']), $_POST['id']]); $msg = 'Proveedor actualizado.'; }
    if ($a == 'prov_borrar') { $pid0 = (int)$_POST['id']; $n = (int)$db->query("SELECT (SELECT COUNT(*) FROM productos WHERE proveedor_id=$pid0) + (SELECT COUNT(*) FROM compras WHERE proveedor_id=$pid0)")->fetchColumn();
      if ($n) $msg = 'No se puede eliminar: el proveedor tiene teléfonos o compras registradas.'; else { $db->prepare("DELETE FROM proveedores WHERE id=?")->execute([$pid0]); $msg = 'Proveedor eliminado.'; } }
    if ($a == 'video' && !empty($_FILES['archivo']['name'])) {
      $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['mp4','webm'])) $msg = 'Solo se permiten videos MP4 o WebM.';
      elseif (move_uploaded_file($_FILES['archivo']['tmp_name'], __DIR__.'/media/promo.'.$ext)) { @unlink(__DIR__.'/media/promo.'.($ext == 'mp4' ? 'webm' : 'mp4')); $msg = 'Video de presentación actualizado.'; }
      else $msg = 'No se pudo subir el video.';
    }
    if ($a == 'logo' && !empty($_FILES['archivo']['name'])) { move_uploaded_file($_FILES['archivo']['tmp_name'], __DIR__.'/media/logo.png'); $msg = 'Logo actualizado.'; }
    if ($a == 'rol') { $db->prepare("UPDATE usuarios SET rol=? WHERE id=? AND id<>?")->execute([$_POST['rol'], $_POST['id'], $_SESSION['uid']]); }
    if ($a == 'borrar_usuario') { $db->prepare("DELETE FROM usuarios WHERE id=? AND id<>?")->execute([$_POST['id'], $_SESSION['uid']]); }
  } catch (Exception $x) { $msg = 'Error: '.$x->getMessage(); }
}
top('Administrador'); ?>
<h2>Panel del administrador</h2>
<div class="panel"><aside>
<?php $menu = ['Inventario'=>['proveedores'=>'1. Proveedores','productos'=>'2. Productos','compras'=>'3. Compras y facturas','movimientos'=>'Movimientos'],'Ventas'=>['ventas'=>'Facturas de venta'],'Usuarios'=>['usuarios'=>'Usuarios registrados'],'Multimedia'=>['video'=>'Video de presentación','logo'=>'Logo'],'Sistema'=>['bd'=>'Base de datos']];
foreach ($menu as $g => $its) { echo '<h4>'.$g.'</h4>'; foreach ($its as $k => $v) echo '<a class="'.($t == $k ? 'on' : '').'" href="?t='.$k.'">'.$v.'</a>'; } ?>
</aside><section>
<?php if ($msg) echo '<div class="msg">'.e($msg).'</div>';
$prods = $db->query("SELECT p.*, pr.nombre AS prov FROM productos p LEFT JOIN proveedores pr ON pr.id=p.proveedor_id ORDER BY p.id DESC")->fetchAll();
$provs = $db->query("SELECT * FROM proveedores ORDER BY nombre")->fetchAll();
$dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia'] ?? '') ? $_GET['dia'] : '';
$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : '';
function filtro($col, $dia, $mes){ // hora local RD = UTC-4
  if ($dia) return ["date($col,'-4 hours') = ?", [$dia]];
  if ($mes) return ["strftime('%Y-%m',$col,'-4 hours') = ?", [$mes]];
  return ['1=1', []]; }
function buscador($t, $dia, $mes){ ?>
<div class="box"><form class="f" method="get"><input type="hidden" name="t" value="<?= e($t) ?>">
<label>Día<br><input type="date" name="dia" value="<?= e($dia) ?>"></label>
<label>Mes<br><input type="month" name="mes" value="<?= e($mes) ?>" placeholder="AAAA-MM"></label>
<div style="align-self:end"><button>🔍 Buscar</button> <a href="?t=<?= e($t) ?>">Limpiar</a></div></form>
<small>Puedes buscar por día, por mes o ver todas. Si eliges las dos cosas, se usa el día.</small></div>
<?php }
if ($t == 'productos'): ?>
<?php if (!$provs) echo '<div class="msg">Antes de agregar teléfonos debes <a href="?t=proveedores">registrar un proveedor</a>.</div>'; ?><div class="box"><h3>Agregar teléfono</h3><form class="f" method="post" enctype="multipart/form-data"><input type="hidden" name="a" value="nuevo">
<select name="marca" required><option>iPhone</option><option>Samsung</option></select><input name="nombre" placeholder="Modelo" required><input name="ram" placeholder="RAM (8 GB)"><input name="almacenamiento" placeholder="Almacenamiento (128 GB)">
<input name="camara" placeholder="Cámara (48 MP)"><input name="carga" placeholder="Carga (25W)"><input name="descripcion" placeholder="Descripción"><input type="file" name="imagen" accept="image/*">
<input name="costo" type="number" step="0.01" placeholder="Costo de compra (referencia)" required><input name="precio" type="number" step="0.01" placeholder="Precio de venta" required>
<select name="proveedor_id" required><option value="">Proveedor...</option><?php foreach ($provs as $pr) echo '<option value="'.$pr['id'].'">'.e($pr['nombre']).'</option>'; ?></select><button>Guardar</button></form></div>
<div class="scroll"><table><tr><th>Teléfono</th><th>Proveedor</th><th>Costo</th><th>Precio venta / Descripción / Stock</th><th></th></tr>
<?php foreach ($prods as $p): ?><tr><td><?= e($p['marca'].' '.$p['nombre']) ?></td><td><?= e($p['prov']) ?></td><td><?= m($p['precio_compra']) ?></td>
<td><form id="fp<?= $p['id'] ?>" method="post"><input type="hidden" name="id" value="<?= $p['id'] ?>"></form><input form="fp<?= $p['id'] ?>" name="precio" type="number" step="0.01" value="<?= e($p['precio_venta']) ?>" size="8"> <input form="fp<?= $p['id'] ?>" name="descripcion" value="<?= e($p['descripcion']) ?>"> <input form="fp<?= $p['id'] ?>" name="stock" type="number" value="<?= (int)$p['stock'] ?>" style="width:80px"></td>
<td><button form="fp<?= $p['id'] ?>" name="a" value="editar">Guardar</button> <button form="fp<?= $p['id'] ?>" class="rojo" name="a" value="borrar" onclick="return confirm('¿Eliminar?')">Eliminar</button></td></tr><?php endforeach; ?></table></div>
<?php elseif ($t == 'compras'): ?>
<div class="box"><h3>Compras a proveedores</h3>
<?php if ($provs): ?><a class="btn" href="compra.php">➕ Nueva compra (cotización y factura)</a>
<?php else: ?><p>Primero registra un proveedor en <a href="?t=proveedores">Proveedores</a>.</p><?php endif; ?></div>
<?php buscador($t, $dia, $mes); [$w, $v] = filtro('c.fecha', $dia, $mes);
$st = $db->prepare("SELECT c.id,datetime(c.fecha,'-4 hours') AS fecha,p.nombre AS proveedor,c.subtotal,c.itbis,c.total,c.tipo,'<a href=\"factura_compra.php?id='||c.id||'\">Ver factura</a>' AS ver FROM compras c LEFT JOIN proveedores p ON p.id=c.proveedor_id WHERE $w ORDER BY c.id DESC"); $st->execute($v); $rows = $st->fetchAll();
echo '<p><b>'.count($rows).'</b> factura(s) de compra · Total: <b>'.m(array_sum(array_column($rows, 'total'))).'</b></p>'.tabla($rows);
$st = $db->prepare("SELECT c.id,datetime(c.fecha,'-4 hours') AS fecha,p.nombre AS producto,c.cantidad,c.precio_unitario,c.total FROM facturas_compra c LEFT JOIN productos p ON p.id=c.producto_id WHERE c.compra_id IS NULL AND $w ORDER BY c.id DESC"); $st->execute($v); $old = $st->fetchAll();
if ($old) echo '<h3>Compras anteriores</h3>'.tabla($old); ?>
<?php elseif ($t == 'ventas'): buscador($t, $dia, $mes); [$w, $v] = filtro('f.fecha', $dia, $mes);
$st = $db->prepare("SELECT f.id,datetime(f.fecha,'-4 hours') AS fecha,u.usuario AS cliente,f.subtotal,f.itbis,f.total,f.metodo_pago,f.tipo,'<a href=\"factura.php?id='||f.id||'\">Ver factura</a>' AS ver FROM facturas_venta f LEFT JOIN usuarios u ON u.id=f.usuario_id WHERE $w ORDER BY f.id DESC"); $st->execute($v); $rows = $st->fetchAll();
echo '<p><b>'.count($rows).'</b> factura(s) · Total: <b>'.m(array_sum(array_column($rows, 'total'))).'</b></p>'.tabla($rows); ?>
<?php elseif ($t == 'movimientos'): ?>
<?= tabla($db->query("SELECT m.id,m.fecha,p.nombre AS producto,m.cantidad,m.tipo FROM movimientos m LEFT JOIN productos p ON p.id=m.producto_id ORDER BY m.id DESC")->fetchAll()) ?>
<?php elseif ($t == 'usuarios'): ?>
<div class="scroll"><table><tr><th>ID</th><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Registro</th><th></th></tr>
<?php foreach ($db->query("SELECT * FROM usuarios") as $u): ?><tr><td><?= $u['id'] ?></td><td><?= e($u['usuario']) ?></td><td><?= e($u['nombre']) ?></td>
<td><form id="fu<?= $u['id'] ?>" method="post"><input type="hidden" name="id" value="<?= $u['id'] ?>"></form><select form="fu<?= $u['id'] ?>" name="rol"><option <?= $u['rol']=='cliente'?'selected':'' ?>>cliente</option><option <?= $u['rol']=='admin'?'selected':'' ?>>admin</option></select></td><td><?= e($u['creado']) ?></td>
<td><button form="fu<?= $u['id'] ?>" name="a" value="rol">Cambiar rol</button> <button form="fu<?= $u['id'] ?>" class="rojo" name="a" value="borrar_usuario" onclick="return confirm('¿Eliminar usuario?')">Eliminar</button></td></tr><?php endforeach; ?></table></div>
<?php elseif ($t == 'video'): ?>
<div class="box"><h3>Video de presentación</h3>
<?php foreach (['mp4','webm'] as $x) if (file_exists("media/promo.$x")) echo '<video controls width="420" src="media/promo.'.$x.'?v='.filemtime("media/promo.$x").'"></video>'; ?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="a" value="video"><input type="file" name="archivo" accept="video/mp4,video/webm" required> <button>Subir video</button></form>
<small>MP4 o WebM, corto. Aparece en la página principal de la tienda.</small></div>
<?php elseif ($t == 'logo'): ?>
<div class="box"><h3>Logo de la tienda</h3><img src="media/logo.png" height="70" alt="" onerror="this.remove()">
<form method="post" enctype="multipart/form-data"><input type="hidden" name="a" value="logo"><input type="file" name="archivo" accept="image/*" required> <button>Subir logo</button></form></div>
<?php elseif ($t == 'proveedores'): ?>
<div class="box"><h3>Agregar proveedor</h3><form class="f" method="post"><input type="hidden" name="a" value="prov_nuevo">
<input name="nombre" placeholder="Nombre del proveedor" required><input name="telefono" placeholder="Teléfono" required><input name="email" type="email" placeholder="Correo electrónico" required><button>Guardar proveedor</button></form></div>
<div class="scroll"><table><tr><th>Proveedor</th><th>Teléfono</th><th>Correo</th><th></th></tr>
<?php foreach ($provs as $pr): $f = 'fv'.$pr['id']; ?><tr><td><form id="<?= $f ?>" method="post"><input type="hidden" name="id" value="<?= $pr['id'] ?>"></form><input form="<?= $f ?>" name="nombre" value="<?= e($pr['nombre']) ?>"></td>
<td><input form="<?= $f ?>" name="telefono" value="<?= e($pr['telefono']) ?>"></td><td><input form="<?= $f ?>" name="email" value="<?= e($pr['email']) ?>"></td>
<td><button form="<?= $f ?>" name="a" value="prov_editar">Guardar</button> <button form="<?= $f ?>" class="rojo" name="a" value="prov_borrar" onclick="return confirm('¿Eliminar proveedor?')">Eliminar</button></td></tr><?php endforeach; ?></table></div>
<?php else: foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll() as $tb) echo '<h3>'.e($tb['name']).'</h3>'.tabla($db->query('SELECT * FROM '.$tb['name'])->fetchAll()); endif; ?>
</section></div>
<?php bottom();
