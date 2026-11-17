<?php require 'config.php';
$err = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $u = trim($_POST['usuario']); $k = $_POST['clave'];
  if (strlen($u) < 3 || strlen($k) < 4) $err = 'El usuario necesita mínimo 3 caracteres y la contraseña mínimo 4.';
  elseif ($k != $_POST['clave2']) $err = 'Las contraseñas no coinciden.';
  else try { $db->prepare("INSERT INTO usuarios(usuario,clave,nombre) VALUES(?,?,?)")->execute([$u, password_hash($k, PASSWORD_DEFAULT), trim($_POST['nombre'])]); $ok = true; }
  catch (Exception $x) { $err = 'Ese nombre de usuario ya existe.'; }
}
top('Registro'); ?>
<div class="box narrow"><h2>Crear cuenta</h2>
<?php if ($ok): ?><div class="msg">Cuenta creada correctamente. <a href="login.php">Iniciar sesión</a></div>
<?php else: if ($err) echo '<div class="msg">'.e($err).'</div>'; ?>
<form method="post"><input name="nombre" placeholder="Nombre completo" required><input name="usuario" placeholder="Nombre de usuario" required>
<input name="clave" type="password" placeholder="Contraseña" required><input name="clave2" type="password" placeholder="Repetir contraseña" required><button>Registrarme</button></form>
<p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p><?php endif; ?></div>
<?php bottom();
