<?php require 'config.php';
if (!isset($_SESSION['uid'])) { $_SESSION['volver'] = 'pago.php'; header('Location: login.php'); exit; }
$av = ajustar_carrito(); [$items, $sub] = carrito_items();
if (!$items) { header('Location: carrito.php'); exit; }
$bancos = ['Banreservas', 'Banco Popular', 'BHD', 'Scotiabank', 'Banco Santa Cruz', 'Promerica', 'Banco Caribe', 'Banco Vimenca', 'APAP', 'Otro banco'];
$err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $m = $_POST['metodo'] ?? ''; $b = $_POST['banco'] ?? ''; $t = trim($_POST['titular'] ?? '');
  $n = preg_replace('/\D/', '', $_POST['numero'] ?? ''); $vc = trim($_POST['vence'] ?? ''); $cv = $_POST['cvv'] ?? '';
  $tarj = in_array($m, ['credito', 'debito']);
  if (!in_array($m, ['credito', 'debito', 'transferencia', 'efectivo'])) $err = 'Elige un método de pago.';
  elseif ($m != 'efectivo' && !in_array($b, $bancos)) $err = 'Selecciona el banco.';
  elseif ($m != 'efectivo' && strlen($t) < 3) $err = 'Escribe el nombre del titular.';
  elseif ($tarj && (strlen($n) < 13 || strlen($n) > 19)) $err = 'El número de tarjeta debe tener entre 13 y 19 dígitos.';
  elseif ($tarj && !preg_match('/^(0[1-9]|1[0-2])\/(\d\d)$/', $vc, $mm)) $err = 'La fecha de vencimiento debe tener el formato MM/AA.';
  elseif ($tarj && ((2000 + (int)$mm[2]) * 100 + (int)$mm[1]) < (int)date('Ym')) $err = 'La tarjeta está vencida.';
  elseif ($tarj && !preg_match('/^\d{3,4}$/', $cv)) $err = 'El CVV debe tener 3 o 4 dígitos.';
  elseif ($m == 'credito' && (!in_array($_POST['red'] ?? '', ['Visa', 'Mastercard', 'American Express']) || !in_array((int)($_POST['cuotas'] ?? 0), [1, 3, 6, 12]))) $err = 'Elige la red de la tarjeta y la cantidad de cuotas.';
  elseif ($m == 'debito' && !in_array($_POST['cuenta'] ?? '', ['Ahorros', 'Corriente'])) $err = 'Elige el tipo de cuenta.';
  elseif ($m == 'transferencia' && strlen(trim($_POST['referencia'] ?? '')) < 4) $err = 'Escribe el número de referencia de la transferencia.';
  else {
    $u4 = substr($n, -4); // solo se conservan los últimos 4 dígitos; el número completo y el CVV NO se guardan
    $desc = ['credito' => 'Tarjeta de crédito '.($_POST['red'] ?? '').' · '.$b.' · **** '.$u4.' · '.(int)($_POST['cuotas'] ?? 1).' cuota(s)',
             'debito' => 'Tarjeta de débito · '.$b.' · cuenta '.strtolower($_POST['cuenta'] ?? '').' · **** '.$u4,
             'transferencia' => 'Transferencia · '.$b.' · ref. '.trim($_POST['referencia'] ?? ''),
             'efectivo' => 'Efectivo (pago al recibir)'][$m];
    $_SESSION['pago'] = ['desc' => $desc, 'metodo' => $m, 'titular' => $t];
    header('Location: cotizacion.php'); exit;
  }
}
top('Método de pago'); ?>
<h2>Método de pago</h2>
<?php foreach ($av as $x) echo '<div class="msg">'.e($x).'</div>'; if ($err) echo '<div class="msg">'.e($err).'</div>'; ?>
<div class="msg">Proyecto educativo (simulación): no escribas datos de tarjetas reales. El sistema solo guarda los últimos 4 dígitos; el número completo y el CVV no se almacenan.</div>
<form method="post" class="box">
<h3>1. Elige cómo vas a pagar</h3>
<div class="metodos">
<?php foreach (['credito' => ['💳', 'Tarjeta de crédito'], 'debito' => ['🏧', 'Tarjeta de débito'], 'transferencia' => ['🏦', 'Transferencia bancaria'], 'efectivo' => ['💵', 'Efectivo al recibir']] as $k => [$ic, $nm]): ?>
<label><input type="radio" name="metodo" value="<?= $k ?>" required <?= ($_POST['metodo'] ?? '') == $k ? 'checked' : '' ?>><span><?= $ic ?><b><?= $nm ?></b></span></label>
<?php endforeach; ?></div>
<h3>2. Datos del pago</h3>
<div class="sec f" data-m="credito debito transferencia"><select name="banco" required><option value="">Banco...</option><?php foreach ($bancos as $bk) echo '<option '.(($_POST['banco'] ?? '') == $bk ? 'selected' : '').'>'.e($bk).'</option>'; ?></select>
<input name="titular" placeholder="Nombre del titular" value="<?= v('titular') ?>" required></div>
<div class="sec f" data-m="credito"><select name="red" required><option value="">Red de la tarjeta...</option><option>Visa</option><option>Mastercard</option><option>American Express</option></select>
<select name="cuotas" required><option value="1">Pago en 1 cuota</option><option value="3">3 cuotas</option><option value="6">6 cuotas</option><option value="12">12 cuotas</option></select></div>
<div class="sec f" data-m="debito"><select name="cuenta" required><option value="">Tipo de cuenta...</option><option>Ahorros</option><option>Corriente</option></select></div>
<div class="sec f" data-m="credito debito"><input name="numero" inputmode="numeric" maxlength="23" placeholder="Número de tarjeta" autocomplete="off" required>
<input name="vence" maxlength="5" placeholder="Vence (MM/AA)" value="<?= v('vence') ?>" required><input name="cvv" type="password" inputmode="numeric" maxlength="4" placeholder="CVV" autocomplete="off" required></div>
<div class="sec f" data-m="transferencia"><input name="referencia" placeholder="Número de referencia / confirmación" value="<?= v('referencia') ?>" required><small>Haz la transferencia a la cuenta de la tienda (te la enviamos por WhatsApp 849-378-2318) y escribe aquí el número de confirmación.</small></div>
<div class="sec" data-m="efectivo"><p>Pagas en efectivo cuando recibas tu teléfono. No necesitas llenar nada más.</p></div>
<p class="tot">Total a pagar: <b><?= m($sub * (1 + ITBIS)) ?></b> (ITBIS incluido)</p>
<p class="tot"><a href="carrito.php">← Volver al carrito</a> &nbsp; <button>Ver cotización →</button></p></form>
<script>
function act(){const m=(document.querySelector('input[name=metodo]:checked')||{}).value;
document.querySelectorAll('.sec').forEach(s=>{const on=s.dataset.m.split(' ').includes(m);s.style.display=on?'':'none';s.querySelectorAll('input,select').forEach(i=>i.disabled=!on)})}
document.querySelectorAll('input[name=metodo]').forEach(r=>r.onchange=act);act();
document.querySelector('[name=numero]').oninput=function(){this.value=this.value.replace(/\D/g,'').replace(/(.{4})/g,'$1 ').trim()};
document.querySelector('[name=vence]').oninput=function(){let d=this.value.replace(/\D/g,'').slice(0,4);this.value=d.length>2?d.slice(0,2)+'/'+d.slice(2):d};
</script>
<?php bottom();
