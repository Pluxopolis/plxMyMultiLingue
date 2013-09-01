<?php if(!defined('PLX_ROOT')) exit; ?>

<?php
# Control du token du formulaire
plxToken::validateFormToken($_POST);
?>

<?php

if(!empty($_POST)) {
	$array=array();
	if(isset($_POST['flags']) AND sizeof($_POST['flags'])>0) {
		foreach($_POST['flags'] as $flag) {
			$array[$flag] = $_POST['order'][$flag];
		}
		uasort($array, create_function('$a, $b', 'return $a>$b;'));
	}
	$plxPlugin->setParam('flags', implode(",",array_keys($array)), 'string');
	$plxPlugin->saveParams();
	$plxPlugin->mkDirs();
	header('Location: parametres_plugin.php?p=plxMyMultiLingue');
	exit;
}

# Récupération des langues sélectionnées
$flags = $plxPlugin->getParam('flags');
$aFlags = $flags=='' ? array() :  explode(',', $flags);

# Récupération et tri des langues en fonction des préférences de l'utilisateur
$aLangs = array_merge($aFlags, array_diff(plxUtils::getLangs(), $aFlags));

?>
<h2><?php echo $plxPlugin->getInfo('title') ?></h2>
<p><?php $plxPlugin->lang('L_FLAGS') ?></p>
<form action="parametres_plugin.php?p=plxMyMultiLingue" method="post" id="form_langs">
	<table class="table" style="width:150px">
	<thead>
		<tr>
			<th style="width:10px">&nbsp;</th>
			<th style="width:20px"></th>
			<th style="width:60px"></th>
			<th style="width:60px"><?php $plxPlugin->lang('L_ORDER') ?></th>
		</tr>
	</thead>
	<tbody>
	<?php
		# Initialisation de l'ordre
		$num = 0;
		foreach($aLangs as $flag) {
			$order = ++$num;
			echo '<tr class="line-'.($num%2).'">';
			$selected = in_array($flag,$aFlags)?'checked="checked "':'';
			echo '<td><input type="checkbox" '.$selected.'id="flag_'.$flag.'" name="flags['.$flag.']" value="'.$flag.'" /></td>';
			echo '<td>'.$flag.'</td>';
			echo '<td><img src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$flag.'.jpg" alt="'.$flag.'" style="width:25px" /></td>';
			echo '<td><input size="2" maxlength="2" type="input" id="order_'.$flag.'" name="order['.$flag.']" value="'.$order.'" /></td>';
			echo '</tr>';
		}
	?>
	</tbody>
	</table>
	<p>
		<?php echo plxToken::getTokenPostMethod() ?>
		<input type="submit" name="submit" value="<?php $plxPlugin->lang('L_SAVE') ?>" />
	</p>
</form>