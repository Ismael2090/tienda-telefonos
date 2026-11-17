<?php require 'config.php'; top('Inicio');
$q = trim($_GET['q'] ?? '');
$marca = $_GET['marca'] ?? ''; if (!in_array($marca, ['iPhone', 'Samsung'])) $marca = '';
$s = $db->prepare("SELECT * FROM productos WHERE (nombre LIKE ? OR marca LIKE ?) AND marca LIKE ? ORDER BY id DESC"); $s->execute(["%$q%", "%$q%", "%$marca%"]); ?>
<section class="hero"><h1>iPhone y Samsung al mejor precio</h1>
<?php foreach (['mp4','webm'] as $x) if (file_exists("media/promo.$x")) { echo '<video controls muted loop src="media/promo.'.$x.'?v='.filemtime("media/promo.$x").'"></video>'; break; } ?></section>
<div class="cats">
<?php foreach (['' => ['Todos', '🛒'], 'iPhone' => ['iPhone', '🍎'], 'Samsung' => ['Samsung', '📱']] as $k => [$n, $i]): $c = $db->prepare("SELECT COUNT(*) FROM productos WHERE marca LIKE ?"); $c->execute(["%$k%"]); ?>
<a class="cat <?= $marca == $k ? 'on' : '' ?>" href="?<?= $k ? 'marca='.$k : '' ?>#catalogo"><span><?= $i ?></span><b><?= $n ?></b><small><?= $c->fetchColumn() ?> modelos</small></a>
<?php endforeach; ?></div>
<h2 id="catalogo">Catálogo <?= e($marca) ?></h2>
<form class="buscar"><input type="hidden" name="marca" value="<?= e($marca) ?>"><input name="q" value="<?= e($q) ?>" placeholder="Buscar teléfono por nombre o marca..."><button>Buscar</button></form>
<div class="grid">
<?php foreach ($s as $p): ?>
<article class="card"><img src="<?= e($p['imagen'] ?: 'media/sin-imagen.png') ?>" alt="<?= e($p['nombre']) ?>">
<h3><?= e($p['marca'].' '.$p['nombre']) ?></h3>
<ul><li>RAM: <?= e($p['ram']) ?></li><li>Almacenamiento: <?= e($p['almacenamiento']) ?></li><li>Cámara: <?= e($p['camara']) ?></li><li>Carga: <?= e($p['carga']) ?></li></ul>
<p><?= e($p['descripcion']) ?></p><div class="precio"><?= m($p['precio_venta']) ?></div>
<?php if ($p['stock'] > 0): ?><small>Disponibles: <?= (int)$p['stock'] ?></small>
<form method="post" action="carrito.php"><input type="hidden" name="a" value="add"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button>Agregar al carrito</button></form>
<?php else: ?><b>Agotado</b><?php endif; ?></article>
<?php endforeach; ?></div>
<?php bottom();
