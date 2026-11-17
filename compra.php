<?php require 'config.php';
if (($_SESSION['rol'] ?? '') != 'admin') { header('Location: login.php'); exit; }
$provs = $db->query("SELECT * FROM proveedores ORDER BY nombre")->fetchAll();
$C = $_SESSION['compra'] ?? ['prov' => 0, 'l' => []]; $err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') { $a = $_POST['a'] ?? '';
  if ($a == 'prov') { $np = (int)$_POST['prov']; if ($np != $C['prov']) $C = ['prov' => $np, 'l' => []]; }
  if ($a == 'add' && $C['prov']) {
    $pid = (int)$_POST['id']; $q = (int)$_POST['cantidad']; $c = (float)$_POST['costo'];
    $ok = $db->prepare("SELECT COUNT(*) FROM productos WHERE id=? AND proveedor_id=?"); $ok->execute([$pid, $C['prov']]);
    if (!$ok->fetchColumn()) $err = 'Ese teléfono no pertenece al proveedor elegido.';
    elseif ($q < 1 || $c <= 0) $err = 'Escribe una cantidad y un costo válidos.';
    else $C['l'][$pid] = ['q' => ($C['l'][$pid]['q'] ?? 0) + $q, 'c' => $c];
  }
  if ($a == 'del') unset($C['l'][(int)$_POST['id']]);
  $_SESSION['compra'] = $C;
  if ($a == 'cancelar') { unset($_SESSION['compra']); header('Location: admin.php?t=compras'); exit; }
  if ($a == 'cotizar') { if ($C['prov'] && $C['l']) { header('Location: compra.php?paso=cotizacion'); exit; } $err = 'Elige un proveedor y agrega al menos un teléfono.'; }
}
$L = []; $sub = 0;
foreach ($C['l'] as $pid => $l) { $s = $db->prepare("SELECT * FROM productos WHERE id=?"); $s->execute([$pid]); $p = $s->fetch(); if ($p) { $L[] = [$p, $l['q'], $l['c']]; $sub += $l['q'] * $l['c']; } }
$prov = null; foreach ($provs as $x) if ($x['id'] == $C['prov']) $prov = $x;
if (($_POST['a'] ?? '') == 'confirmar' && $prov && $L) {
  try {
    $db->beginTransaction();
    $db->prepare("INSERT INTO compras(proveedor_id,subtotal,itbis,total) VALUES(?,?,?,?)")->execute([$prov['id'], $sub, $sub * ITBIS, $sub * (1 + ITBIS)]);
    $cid = $db->lastInsertId();
    foreach ($L as [$p, $q, $c]) {
      $db->prepare("INSERT INTO facturas_compra(producto_id,proveedor_id,cantidad,precio_unitario,total,compra_id) VALUES(?,?,?,?,?,?)")->execute([$p['id'], $prov['id'], $q, $c, $q * $c, $cid]);
      $db->prepare("UPDATE productos SET stock=stock+?, precio_compra=? WHERE id=?")->execute([$q, $c, $p['id']]);
      $db->prepare("INSERT INTO movimientos(producto_id,cantidad,tipo) VALUES(?,?,'Entrada')")->execute([$p['id'], $q]);
    }
    $db->commit(); unset($_SESSION['compra']); header("Location: factura_compra.php?id=$cid"); exit;
  } catch (Exception $x) { if ($db->inTransaction()) $db->rollBack(); $err = $x->getMessage(); }
}
$paso = $_GET['paso'] ?? 'editar';
if ($paso == 'cotizacion' && (!$prov || !$L)) { header('Location: compra.php'); exit; }
top('Nueva compra'); ?>
<p><a href="admin.php?t=compras">← Volver al panel</a></p>
<h2><?= $paso == 'cotizacion' ? 'Cotización de compra' : 'Nueva compra a proveedor' ?></h2>
<?php if ($err) echo '<div class="msg">'.e($err).'</div>';
if (!$provs): ?><div class="msg">Antes de comprar debes <a href="admin.php?t=proveedores">registrar un proveedor</a>.</div>
<?php elseif ($paso == 'cotizacion'): ?>
<div class="box"><p>Esto todavía no es una compra. Revisa y confirma para que la mercancía entre al inventario.</p>
<p><b>Proveedor:</b> <?= e($prov['nombre']) ?> · Tel: <?= e($prov['telefono']) ?> · <?= e($prov['email']) ?></p>
<div class="scroll"><table><tr><th>Teléfono</th><th>Cant.</th><th>Costo unit.</th><th>Total</th></tr>
<?php foreach ($L as [$p, $q, $c]) echo '<tr><td>'.e($p['marca'].' '.$p['nombre']).'</td><td>'.$q.'</td><td>'.m($c).'</td><td>'.m($q * $c).'</td></tr>'; ?></table></div>
<p class="tot">Subtotal: <?= m($sub) ?><br>ITBIS (18%): <?= m($sub * ITBIS) ?><br><b>Total: <?= m($sub * (1 + ITBIS)) ?></b></p>
<form method="post" class="tot"><a href="compra.php">← Editar</a> &nbsp; <button class="rojo" name="a" value="cancelar">Cancelar</button> <button style="background:#16a34a" name="a" value="confirmar">Confirmar compra y generar factura</button></form></div>
<?php else: ?>
<div class="box"><h3>1. Proveedor</h3><form method="post" class="f"><input type="hidden" name="a" value="prov">
<select name="prov" onchange="this.form.submit()" required><option value="">Elige un proveedor...</option><?php foreach ($provs as $x) echo '<option value="'.$x['id'].'" '.($x['id'] == $C['prov'] ? 'selected' : '').'>'.e($x['nombre']).'</option>'; ?></select></form>
<?php if ($prov) echo '<p>Tel: '.e($prov['telefono']).' · Correo: '.e($prov['email']).'</p>'; ?></div>
<?php if ($prov): $pp = $db->prepare("SELECT * FROM productos WHERE proveedor_id=? ORDER BY marca,nombre"); $pp->execute([$prov['id']]); $pp = $pp->fetchAll(); ?>
<div class="box"><h3>2. Teléfonos a comprar</h3>
<?php if (!$pp): ?><p>Este proveedor aún no tiene teléfonos. Agrégalos en <a href="admin.php?t=productos">Productos</a>.</p><?php else: ?>
<form method="post" class="f"><input type="hidden" name="a" value="add">
<select name="id" onchange="this.form.costo.value=this.selectedOptions[0].dataset.c"><?php foreach ($pp as $p) echo '<option value="'.$p['id'].'" data-c="'.e($p['precio_compra']).'">'.e($p['marca'].' '.$p['nombre']).' (stock '.$p['stock'].')</option>'; ?></select>
<input name="cantidad" type="number" min="1" placeholder="Cantidad" required><input name="costo" type="number" step="0.01" value="<?= e($pp[0]['precio_compra']) ?>" placeholder="Costo unitario" required><button>Agregar a la compra</button></form><?php endif; ?>
<?php if ($L): ?><div class="scroll"><table><tr><th>Teléfono</th><th>Cant.</th><th>Costo unit.</th><th>Total</th><th></th></tr>
<?php foreach ($L as [$p, $q, $c]) echo '<tr><td>'.e($p['marca'].' '.$p['nombre']).'</td><td>'.$q.'</td><td>'.m($c).'</td><td>'.m($q * $c).'</td><td><form method="post"><input type="hidden" name="id" value="'.$p['id'].'"><button class="rojo" name="a" value="del">Quitar</button></form></td></tr>'; ?></table></div>
<p class="tot">Subtotal: <?= m($sub) ?><br>ITBIS (18%): <?= m($sub * ITBIS) ?><br><b>Total: <?= m($sub * (1 + ITBIS)) ?></b></p>
<form method="post" class="tot"><button class="rojo" name="a" value="cancelar">Cancelar</button> <button name="a" value="cotizar">Generar cotización →</button></form><?php endif; ?></div>
<?php endif; endif; bottom();
