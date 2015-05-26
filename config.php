<?php if(!defined('PLX_ROOT')) exit; ?>
<?php

# Control du token du formulaire
plxToken::validateFormToken($_POST);

$Labels = array(
	'fr'	=> 'Français',
	'en'	=> 'English',
	'de'	=> 'Deutsch',
	'es'	=> 'Español',
	'it'	=> 'Italiano',
	'nl'	=> 'Nederlands',
	'oc'	=> 'Occitan',
	'pl'	=> 'Polski',
	'pt'	=> 'Português',
	'ro'	=> 'Român',
	'ru'	=> 'Pусский',
);

if(!empty($_POST)) {
	$array1=array();
	$array2=array();
	if(isset($_POST['flags']) AND sizeof($_POST['flags'])>0) {
		foreach($_POST['flags'] as $flag) {
			$array1[$flag] = $_POST['order'][$flag];
			$array2[$flag] = $_POST['label'][$flag];
		}
		uasort($array1, create_function('$a, $b', 'return $a>$b;'));
	}
	$plxPlugin->setParam('flags', implode(",",array_keys($array1)), 'string');
	$plxPlugin->setParam('labels', serialize($array2), 'cdata');
	$plxPlugin->setParam('lang_medias_folder', $_POST['lang_medias_folder'], 'numeric');
	$plxPlugin->setParam('display', $_POST['display'], 'string');
	$plxPlugin->setParam('redirect_ident', $_POST['redirect_ident'], 'numeric');

	$plxPlugin->mkDirs();
	$plxPlugin->saveParams();
	# réinitialisation des variables de sessions dépendantes de la langues
	unset($_SESSION['lang']);
	unset($_SESSION['medias']);
	unset($_SESSION['folder']);
	header('Location: parametres_plugin.php?p=plxMyMultiLingue');
	exit;
}

# Récupération des langues sélectionnées
$flags = $plxPlugin->getParam('flags');
$aFlags = $flags!='' ? explode(',', $flags) : array() ;
$labels = $plxPlugin->getParam('labels');
$aLabels = $labels!='' ? unserialize($labels) : $Labels;
$display = $plxPlugin->getParam('display')!='' ? $plxPlugin->getParam('display') : 'flag';
$redirect_ident = $plxPlugin->getParam('redirect_ident') == '' ? 0 : $plxPlugin->getParam('redirect_ident');

# Récupération et tri des langues en fonction des préférences de l'utilisateur
$aLangs = array_merge($aFlags, array_diff(plxUtils::getLangs(), $aFlags));

$lang_medias_folder = $plxPlugin->getParam('lang_medias_folder')=='' ? 0 : $plxPlugin->getParam('lang_medias_folder');
?>
<p><?php $plxPlugin->lang('L_FLAGS') ?></p>
<form action="parametres_plugin.php?p=plxMyMultiLingue" method="post" id="form_langs">
	<table class="table" style="width:150px">
		<thead>
			<tr>
				<th style="width:10px">&nbsp;</th>
				<th style="width:20px"></th>
				<th style="width:20px"><?php $plxPlugin->lang('L_LABEL') ?></th>
				<th style="width:40px"></th>
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
				$selected = in_array($flag,$aFlags)?'checked="checked" ':'';
				echo '<td><input type="checkbox" '.$selected.'id="flag_'.$flag.'" name="flags['.$flag.']" value="'.$flag.'" /></td>';
				echo '<td>'.$flag.'</td>';
				if(isset($aLabels[$flag]))
					$label = $aLabels[$flag]=='' ? $Labels[$flag] : $aLabels[$flag];
				else
					$label = $Labels[$flag];
				echo '<td><input size="10" maxlength="30" type="input" id="label_'.$flag.'" name="label['.$flag.']" value="'.plxUtils::strCheck($label).'" /></td>';
				echo '<td><img src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$flag.'.png" alt="'.$flag.'" style="width:25px" /></td>';
				echo '<td><input size="2" maxlength="2" type="input" id="order_'.$flag.'" name="order['.$flag.']" value="'.$order.'" /></td>';
				echo '</tr>';
			}
			?>
		</tbody>
	</table>
	<fieldset>
		<p class="field"><label for="id_lang_medias_folder"><?php echo $plxPlugin->lang('L_LANG_MEDIAS_FOLDER') ?>&nbsp;:</label></p>
		<?php plxUtils::printSelect('lang_medias_folder',array('1'=>L_YES,'0'=>L_NO),$lang_medias_folder) ?>
		<p class="field"><label for="id_display"><?php echo $plxPlugin->lang('L_DISPLAY') ?>&nbsp;:</label></p>
		<?php plxUtils::printSelect('display',array('flag'=>$plxPlugin->getLang('L_FLAG'),'label'=>$plxPlugin->getLang('L_LABEL')),$display) ?>
		<p class="field"><label for="id_redirect_ident"><?php echo $plxPlugin->lang('L_REDIRECT_IDENT') ?>&nbsp;:</label></p>
		<?php plxUtils::printSelect('redirect_ident',array('1'=>L_YES,'0'=>L_NO),$redirect_ident) ?>
	</fieldset>
	<fieldset>
		<p class="in-action-bar">
			<?php echo plxToken::getTokenPostMethod() ?>
			<input type="submit" name="submit" value="<?php $plxPlugin->lang('L_SAVE') ?>" />
		</p>
	</fieldset>
</form>
