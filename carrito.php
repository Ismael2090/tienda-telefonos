<?php require 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $c = $_SESSION['carrito'] ?? []; $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0); $err = '';
  $s = $db->prepare("SELECT nombre,stock FROM productos WHERE id=?"); $s->execute([$id]); $p = $s->fetch(); $q = $c[$id] ?? 0;
  if ($a == 'menos') { if ($q <= 1) unset($c[$id]); else $c[$id] = $q - 1; }
  elseif ($a == 'del') unset($c[$id]);
  elseif (in_array($a, ['add', 'mas'])) {
    if (!$p || $p['stock'] < 1) $err = 'Producto agotado.';
    elseif ($q + 1 > $p['stock']) $err = 'Solo hay '.$p['stock'].' unidad(es) disponibles de '.$p['nombre'].'.';
    else $c[$id] = $q + 1;
  }
  $_SESSION['carrito'] = $c; unset($_SESSION['pago']); $_SESSION['flash'] = $err;
  header('Location: carrito.php'); exit;
}
$av = ajustar_carrito(); if (!empty($_SESSION['flash'])) $av[] = $_SESSION['flash']; unset($_SESSION['flash']);
[$items, $sub] = carrito_items();
top('Carrito'); ?>
<h2>Tu carrito</h2><?php foreach ($av as $m) echo '<div class="msg">'.e($m).'</div>'; ?>
<?php if (!$items): ?><p>Tu carrito está vacío. <a href="index.php">Ver teléfonos</a></p><?php else: ?>
<div class="box"><div class="scroll"><table><tr><th>Teléfono</th><th>Cantidad</th><th>Precio</th><th>Total</th><th></th></tr>
<?php foreach ($items as [$p, $q]): ?>
<tr><td><?= e($p['marca'].' '.$p['nombre']) ?><br><small>Disponibles: <?= (int)$p['stock'] ?></small></td>
<td><form method="post" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="a" value="menos">−</button><b><?= $q ?></b><button name="a" value="mas" <?= $q >= $p['stock'] ? 'disabled' : '' ?>>+</button></form></td>
<td><?= m($p['precio_venta']) ?></td><td><?= m($p['precio_venta'] * $q) ?></td>
<td><form method="post"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="rojo" name="a" value="del">Quitar</button></form></td></tr>
<?php endforeach; ?></table></div>
<p class="tot">Subtotal: <?= m($sub) ?><br>ITBIS (18%): <?= m($sub * ITBIS) ?><br><b>Total: <?= m($sub * (1 + ITBIS)) ?></b></p>
<p class="tot"><a class="btn" href="pago.php">Continuar al método de pago →</a></p></div>
<?php endif; bottom();
