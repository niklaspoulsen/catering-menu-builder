<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = cmbwc_get_order_bon_data($order);

?>

<style>

body{
	font-family: Arial;
	font-size:12px;
}

.bon{
	width:300px;
}

.bon h1{
	font-size:20px;
	margin-bottom:10px;
}

.section{
	margin-top:15px;
}

ul{
	margin:4px 0 8px 16px;
}

hr{
	border:0;
	border-top:1px dashed #000;
	margin:12px 0;
}

</style>

<div class="bon">

<h1>CATERING BON</h1>

<strong>Ordre:</strong> #<?php echo $data['order_number']; ?><br>
<strong>Oprettet:</strong> <?php echo $data['created']; ?><br>

<br>

<strong>Leveringsdato:</strong> <?php echo $data['delivery_date']; ?><br>
<strong>Tid:</strong> <?php echo $data['delivery_time']; ?><br>

<br>

<strong>Kunde:</strong> <?php echo $data['customer']; ?><br>
<strong>Firma:</strong> <?php echo $data['company']; ?><br>
<strong>Tlf:</strong> <?php echo $data['phone']; ?><br>

<hr>

<div class="section">

<h3>Produktion</h3>

<?php foreach($data['items'] as $item): ?>

<strong><?php echo $item['name']; ?></strong><br>

<?php if($item['covers']): ?>

Kuverter: <?php echo $item['covers']; ?><br>

<?php endif; ?>

<?php if($item['included']): ?>

Indhold:
<ul>
<?php foreach($item['included'] as $line): ?>
<li><?php echo $line; ?></li>
<?php endforeach; ?>
</ul>

<?php endif; ?>

<?php if($item['addons']): ?>

Tilvalg:
<ul>
<?php foreach($item['addons'] as $line): ?>
<li><?php echo $line; ?></li>
<?php endforeach; ?>
</ul>

<?php endif; ?>

<?php if($item['service']): ?>

Service: <?php echo $item['service']; ?><br>

<?php endif; ?>

<hr>

<?php endforeach; ?>

</div>

<?php if($data['order_note']): ?>

<div class="section">

<h3>Kundebemærkning</h3>

<?php echo nl2br($data['order_note']); ?>

</div>

<?php endif; ?>

</div>
