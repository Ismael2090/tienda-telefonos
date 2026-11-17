<?php require 'config.php';
$s = $db->prepare("SELECT f.*, u.nombre, u.usuario FROM facturas_venta f LEFT JOIN usuarios u ON u.id=f.usuario_id WHERE f.id=?"); $s->execute([(int)($_GET['id'] ?? 0)]); $f = $s->fetch();
if (!$f || (($_SESSION['rol'] ?? '') != 'admin' && ($_SESSION['uid'] ?? 0) != $f['usuario_id'])) { header('Location: index.php'); exit; }
$d = $db->prepare("SELECT d.*, p.marca, p.nombre FROM detalle_venta d LEFT JOIN productos p ON p.id=d.producto_id WHERE factura_id=?"); $d->execute([$f['id']]);
top('Factura'); ?>
<?php if (!empty($_SESSION['stock_msg'])) { echo '<div class="msg">✅ Compra realizada. Inventario actualizado: '; foreach ($_SESSION['stock_msg'] as $n => $r) echo e($n).' (quedan '.$r.') · '; echo '</div>'; unset($_SESSION['stock_msg']); } ?>
<div class="box"><h2>Factura #<?= $f['id'] ?></h2>
<p>Ismael Phones · Instagram: ismael20902 · WhatsApp: 849-378-2318<br>Fecha: <?= e(fecha_rd($f['fecha'])) ?> · Cliente: <?= e($f['nombre'] ?: $f['usuario']) ?> · Pago: <?= e($f['metodo_pago']) ?></p>
<table><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Total</th></tr>
<?php foreach ($d as $r): ?><tr><td><?= e($r['marca'].' '.$r['nombre']) ?></td><td><?= $r['cantidad'] ?></td><td><?= m($r['precio_unitario']) ?></td><td><?= m($r['precio_unitario']*$r['cantidad']) ?></td></tr><?php endforeach; ?></table>
<p class="tot">Subtotal: <?= m($f['subtotal']) ?><br>ITBIS (18%): <?= m($f['itbis']) ?><br><b>Total: <?= m($f['total']) ?></b></p>
<button class="noprint" onclick="print()">Imprimir</button></div>
<?php bottom();
