<?php require 'config.php';
if (($_SESSION['rol'] ?? '') != 'admin') { header('Location: login.php'); exit; }
$s = $db->prepare("SELECT c.*, p.nombre AS prov, p.telefono, p.email FROM compras c LEFT JOIN proveedores p ON p.id=c.proveedor_id WHERE c.id=?"); $s->execute([(int)($_GET['id'] ?? 0)]); $f = $s->fetch();
if (!$f) { header('Location: admin.php?t=compras'); exit; }
$d = $db->prepare("SELECT x.*, pr.marca, pr.nombre FROM facturas_compra x LEFT JOIN productos pr ON pr.id=x.producto_id WHERE x.compra_id=?"); $d->execute([$f['id']]);
top('Factura de compra'); ?>
<div class="box"><h2>Factura de compra #<?= $f['id'] ?></h2>
<p>Ismael Phones · Compra a proveedor · Tipo de movimiento: <b><?= e($f['tipo']) ?></b><br>Fecha: <?= e(fecha_rd($f['fecha'])) ?><br>
Proveedor: <b><?= e($f['prov']) ?></b> · Tel: <?= e($f['telefono']) ?> · <?= e($f['email']) ?></p>
<table><tr><th>Teléfono</th><th>Cant.</th><th>Costo unit.</th><th>Total</th></tr>
<?php foreach ($d as $r): ?><tr><td><?= e($r['marca'].' '.$r['nombre']) ?></td><td><?= $r['cantidad'] ?></td><td><?= m($r['precio_unitario']) ?></td><td><?= m($r['total']) ?></td></tr><?php endforeach; ?></table>
<p class="tot">Subtotal: <?= m($f['subtotal']) ?><br>ITBIS (18%): <?= m($f['itbis']) ?><br><b>Total: <?= m($f['total']) ?></b></p>
<p class="noprint"><button onclick="print()">Imprimir</button> <a href="admin.php?t=compras">← Volver a compras</a></p></div>
<?php bottom();
