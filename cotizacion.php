<?php require 'config.php';
if (!isset($_SESSION['uid'])) { $_SESSION['volver'] = 'pago.php'; header('Location: login.php'); exit; }
$av = ajustar_carrito(); [$items, $sub] = carrito_items();
if (!$items) { header('Location: carrito.php'); exit; }
if (empty($_SESSION['pago'])) { header('Location: pago.php'); exit; }
$pg = $_SESSION['pago']; $err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (($_POST['a'] ?? '') == 'cancelar') { unset($_SESSION['pago']); header('Location: carrito.php'); exit; }
  if (($_POST['a'] ?? '') == 'confirmar') {
    try {
      $db->beginTransaction();
      $db->prepare("INSERT INTO facturas_venta(usuario_id,subtotal,itbis,total,metodo_pago) VALUES(?,?,?,?,?)")->execute([$_SESSION['uid'], $sub, $sub * ITBIS, $sub * (1 + ITBIS), $pg['desc']]);
      $fid = $db->lastInsertId(); $rest = [];
      foreach ($items as [$p, $q]) {
        $u = $db->prepare("UPDATE productos SET stock=stock-? WHERE id=? AND stock>=?"); $u->execute([$q, $p['id'], $q]); // nunca baja de 0
        if ($u->rowCount() < 1) throw new Exception('Inventario insuficiente: '.$p['marca'].' '.$p['nombre']);
        $db->prepare("INSERT INTO detalle_venta(factura_id,producto_id,cantidad,precio_unitario) VALUES(?,?,?,?)")->execute([$fid, $p['id'], $q, $p['precio_venta']]);
        $db->prepare("INSERT INTO movimientos(producto_id,cantidad,tipo) VALUES(?,?,'Salida')")->execute([$p['id'], $q]);
        $rest[$p['marca'].' '.$p['nombre']] = (int)$db->query("SELECT stock FROM productos WHERE id=".(int)$p['id'])->fetchColumn();
      }
      $db->commit(); $_SESSION['stock_msg'] = $rest; $_SESSION['carrito'] = []; unset($_SESSION['pago']); header("Location: factura.php?id=$fid"); exit;
    } catch (Exception $x) { if ($db->inTransaction()) $db->rollBack(); $err = $x->getMessage(); $av = ajustar_carrito(); [$items, $sub] = carrito_items(); }
  }
}
top('Cotización'); ?>
<h2>Cotización</h2><p>Esto todavía no es una compra. Revisa los datos y decide si quieres comprar.</p>
<?php foreach ($av as $x) echo '<div class="msg">'.e($x).'</div>'; if ($err) echo '<div class="msg">'.e($err).'</div>'; ?>
<?php if ($items): ?><div class="box"><div class="scroll"><table><tr><th>Teléfono</th><th>Cant.</th><th>Precio</th><th>Total</th></tr>
<?php foreach ($items as [$p, $q]): ?><tr><td><?= e($p['marca'].' '.$p['nombre']) ?></td><td><?= $q ?></td><td><?= m($p['precio_venta']) ?></td><td><?= m($p['precio_venta'] * $q) ?></td></tr><?php endforeach; ?></table></div>
<p class="tot">Subtotal: <?= m($sub) ?><br>ITBIS (18%): <?= m($sub * ITBIS) ?><br><b>Total: <?= m($sub * (1 + ITBIS)) ?></b></p>
<p><b>Método de pago:</b> <?= e($pg['desc']) ?><?= $pg['titular'] ? ' · Titular: '.e($pg['titular']) : '' ?> · <a href="pago.php">Cambiar</a></p>
<form method="post" class="tot"><button class="rojo" name="a" value="cancelar">Cancelar</button> <button style="background:#16a34a" name="a" value="confirmar">Confirmar compra y pagar</button></form></div>
<?php else: ?><p><a href="carrito.php">Volver al carrito</a></p><?php endif; bottom();
