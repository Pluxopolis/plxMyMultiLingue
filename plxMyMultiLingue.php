<?php if (!defined('PLX_ROOT')) exit;
/**
 * Plugin plxMyMultiLingue 0.9.0
 * @author	Stephane F, Thomas Ingles
 **/

class plxMyMultiLingue extends plxPlugin {
	# tableau contenant la liste des langues gérées
	public $aLangs = array();
	# langue courante pour savoir dans quel dossier aller chercher les données dans le dossier data
	public $lang = '';

	/**
	 * Constructeur de la classe
	 * @param	default_lang	langue par défaut
	 * @return	stdio
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function __construct($default_lang) {
		$this->lang = $default_lang;
		# recherche de la langue par défaut de PluXml
		if(!isset($_SESSION['default_lang'])) {
			$file = file_get_contents(path('XMLFILE_PARAMETERS'));
			preg_match('~name="default_lang"><!\[CDATA\[([^\]]+)~',$file,$lang);
			if(empty($lang))#Fix next gen
				preg_match('~name="default_lang">([^<]+)~',$file,$lang);#fix config base of pluxml : on save param default lang change to present lang
			$_SESSION['default_lang'] = empty($lang[1]) ? $default_lang : $lang[1];
			$_SESSION['data_lang'] = $_SESSION['default_lang'];
			//~ var_dump('Location: ' . $_SERVER['REQUEST_URI']);#Fix? Next gen Logon : langues des plugins = default_lang
			//~ exit;
		}

		#===============================
		# traitement coté administration
		#===============================
		if(defined('PLX_ADMIN')) {
			if(isset($_GET['lang']) AND !empty($_GET['lang'])) {# Changer de langue
				# Fix next gen, if have unloaded params a this time '' === $this->getParam('user_lang')???
				if(empty($this->plug)) {
					$this->plug = array(
						'dir' 			=> PLX_PLUGINS,
						'name' 			=> __CLASS__,
						'filename'		=> PLX_PLUGINS.__CLASS__.'/'.__CLASS__.'.php',
						'parameters.xml'=> PLX_ROOT.PLX_CONFIG_PATH.'plugins/'.__CLASS__.'.xml',
						'infos.xml'		=> PLX_PLUGINS.__CLASS__.'/infos.xml'
					);
					$this->loadParams();
				}

				$this->lang = $_GET['lang'];
				$_SESSION['data_lang'] = $this->lang;
				if(!$this->getParam('user_lang')) {#fix change realy (plugin & more)
					$_SESSION['admin_lang'] = $this->lang;
				}
				# Remove lang=fr[&] before redirect
				$redirect = preg_replace('~lang='.$this->lang.'&?~', '', $_SERVER['REQUEST_URI']);
				# Réinitialisation des dossiers pour le gestionnaire de médias
				unset($_SESSION['medias']);
				unset($_SESSION['folder']);
				unset($_SESSION['currentfolder']);

				header('Location: '.rtrim($redirect, '?'));#Fix Remove "?" at end if alone
				exit;
			}
			elseif(isset($_SESSION['data_lang']))
				$this->lang = $_SESSION['data_lang'];
			else
				$this->lang = $_SESSION['default_lang'];
		}
		#=========================
		# traitement coté visiteur
		#=========================
		else {
			# sitemap
			if(strpos($_SERVER['REQUEST_URI'], 'sitemap.php') !== false)
				$get = basename($_SERVER['REQUEST_URI']);#sitemap.php ou (ln)?
			else# index & feed
				$get = plxUtils::getGets();

			if(strlen($get) == 2 and !is_numeric($get))#2 letter
				$this->lang = strtolower($get);
			elseif(isset($get[2]) and $get[2] ==='/' and !is_numeric($get[0].$get[1]))
				$this->lang = strtolower($get[0].$get[1]);
			else
				$this->lang = $_SESSION['default_lang'];
		}
		# appel du constructeur de la classe plxPlugin (obligatoire)
		//~ parent::__construct($this->lang);
		parent::__construct($default_lang);
		# validation de la langue courante
		$this->validateLang();
		# droits pour accéder à la page config.php du plugin
		$this->setConfigProfil(PROFIL_ADMIN);
		# PLX_MYMULTILINGUE contient la liste des langues et la langue courante - pour être utilisé par d'autres plugins
		if(!defined('PLX_MYMULTILINGUE'))
			define('PLX_MYMULTILINGUE', serialize(array('langs' => $this->getParam('flags'), 'lang' => $this->lang)));
		if(!defined('PLX_ADMIN'))# AND !$this->getParam('user_lang')
			$_SESSION['lang'] = $this->lang;
		#====================================================
		# déclaration des hooks communs frontend et backend
		#====================================================
		# core/lib/class.plx.motor.php
		$this->addHook('plxMotorConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxMotorPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('plxMotorDemarrageEnd', 'plxMotorDemarrageEnd');
		$this->addHook('plxMotorDemarrageNewCommentaire', 'plxMotorDemarrageNewCommentaire');
		$this->addHook('plxMotorGetStatiques', 'plxMotorGetStatiques');
		$this->addHook('plxMotorParseArticle', 'plxMotorParseArticle');
		$this->addHook('plxMotorRedir301', 'plxMotorRedir301');
		#=====================================================
		# Déclaration des hooks pour la zone d'administration
		#=====================================================
		if(defined('PLX_ADMIN')) {
			# core/lib/class.plx.admin.php
			$this->addHook('plxAdminEditConfiguration', 'plxAdminEditConfiguration');
			$this->addHook('plxAdminEditStatiquesUpdate', 'plxAdminEditStatiquesUpdate');
			$this->addHook('plxAdminEditStatiquesXml', 'plxAdminEditStatiquesXml');
			$this->addHook('plxAdminEditArticleXml', 'plxAdminEditArticleXml');
			$this->addHook('plxAdminEditStatique', 'plxAdminEditStatique');

			# core/admin/top.php
			$this->addHook('AdminTopEndHead', 'AdminTopEndHead');
			$this->addHook('AdminTopBottom', 'AdminTopBottom');
			# core/admin/foot.php
			$this->addHook('AdminFootEndBody', 'AdminFootEndBody');
			# core/admin/prepend.php
			$this->addHook('AdminPrepend', 'AdminPrepend');
			# core/admin/article.php
			$this->addHook('AdminArticlePostData', 'AdminArticlePostData');
			$this->addHook('AdminArticlePreview', 'AdminArticlePreview');
			$this->addHook('AdminArticleParseData', 'AdminArticleParseData');
			$this->addHook('AdminArticleInitData', 'AdminArticleInitData');
			$this->addHook('AdminArticleContent', 'AdminArticleContent');
			if(version_compare(PLX_VERSION, '5.9.0', '<')) {# Since 0.9.0
				# core/admin/statiques.php
				$this->addHook('AdminStaticsPrepend', 'AdminStaticsPrepend');# Next gen ready (maybe)
			}
			# core/admin/statique.php
			$this->addHook('AdminStatic', 'AdminStatic');
			# core/admin/parametres_avances.php
			$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
			# core/admin/parametres_base.php
			$this->addHook('AdminSettingsBaseTop', 'AdminSettingsBaseTop');
		}
		#======================================================
		# Déclaration des hooks pour la partie visiteur
		#======================================================
		else {
			# core/lib/class.plx.show.php
			$this->addHook('plxShowConstruct', 'plxShowConstruct');
			$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');

			# core/lib/class.plx.feed.php
			$this->addHook('plxFeedConstructLoadPlugins', 'ConstructLoadPlugins');
			$this->addHook('plxFeedPreChauffageBegin', 'PreChauffageBegin');

			# index.php
			$this->addHook('ThemeEndHead', 'ThemeEndHead');
			$this->addHook('IndexEnd', 'IndexEnd');
			# feed.php
			$this->addHook('FeedEnd', 'FeedEnd');
			# sitemap.php
			$this->addHook('SitemapBegin', 'SitemapBegin');
			$this->addHook('SitemapEnd', 'SitemapEnd');

			# hook utilisateur à mettre dans le thème
			$this->addHook('MyMultiLingue', 'MyMultiLingue');

		}
	}
	# Donne de la langue en cours
	public static function _Lang() {
		$def = unserialize(PLX_MYMULTILINGUE);
		if(isset($def['lang'])) {
			return $def['lang'];
		}
	}
	# Donnes les langues actives. Un explode(',', $langs) est une idée ;)
	public static function _Langs() {
		$def = unserialize(PLX_MYMULTILINGUE);
		if(isset($def['langs'])) {
			return $def['langs'];
		}
	}

	/**********************************/
	/* gestion active/deactive/update */
	/**********************************/

	/**
	 * Méthode exécutée à l'activation du plugin
	 * @author	Stephane F
	 **/
	public function onActivate() {
		if(file_exists(PLX_PLUGINS.__CLASS__.'/update')) @chmod(PLX_PLUGINS.__CLASS__.'/update',0644); # en attendant la modif en natif dans class.plx.plugins.php
		# Mise en cache du css partie administration
		$src_cssfile = PLX_PLUGINS.__CLASS__.'/css/admin.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/'.__CLASS__.'.admin.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# Mise en cache du ccs partie visiteurs
		$src_cssfile = PLX_PLUGINS.__CLASS__.'/css/site.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/'.__CLASS__.'.site.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# Régénération des caches css
		$plxAdmin = plxAdmin::getInstance();
		$plxAdmin->plxPlugins->cssCache('admin');
		$plxAdmin->plxPlugins->cssCache('site');
	}

	/**
	 * Méthode exécutée à la désactivation du plugin
	 * @author	Stephane F
	 **/
	public function onDeactivate() {
		unset($_SESSION['lang']);
		unset($_SESSION['admin_lang']);
		unset($_SESSION['default_lang']);
		unset($_SESSION['data_lang']);
		unset($_SESSION['medias']);
		unset($_SESSION['folder']);
		unset($_SESSION['currentfolder']);
	}

	/**
	 * Méthode appelée par la classe plxPlugins et executée si un fichier "upadate" est présent dans le dossier du plugin
	 * On demande une mise à jour du cache css
	 * Nouvelles règles css pour le plugin avec PluXml 5.6 et PluCSS 1.2 pour afficher les drapeaux dans l'action bar
	 * @author	Stephane F
	 **/
	public function onUpdate() {
		# demande de mise à jour du cache css
		return array('cssCache' => true);
	}

	/**
	 * Méthode qui créer les répertoires des langues (écran de config du plugin)
	 * @author	Stephane F
	 **/
	public function mkDirs() {

		$plxAdmin = plxAdmin::getInstance();

		# on nettoie les chemins
		$racine_articles = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_articles']);
		$racine_statiques = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_statiques']);
		$racine_commentaires =  str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_commentaires']);
		$racine_medias = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['medias']);

		if(isset($_POST['flags'])) {
			foreach($_POST['flags'] as $lang) {
				if(!is_dir(PLX_ROOT.$racine_articles.$lang))
					mkdir(PLX_ROOT.$racine_articles.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_statiques.$lang))
					mkdir(PLX_ROOT.$racine_statiques.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_commentaires.$lang))
					mkdir(PLX_ROOT.$racine_commentaires.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_medias.$lang))
					mkdir(PLX_ROOT.$racine_medias.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.PLX_CONFIG_PATH.$lang))
					mkdir(PLX_ROOT.PLX_CONFIG_PATH.$lang, 0755, true);
				plxUtils::write('',PLX_ROOT.PLX_CONFIG_PATH.$lang.'/index.html');
				plxUtils::write("<Files *>\n\tOrder allow,deny\n\tDeny from all\n</Files>",PLX_ROOT.PLX_CONFIG_PATH.$lang.'/.htaccess');
			}
		}

	}
	/**
	* Pour garder la compatibilité ascendante et montante (Origin PluXml 5.7)
	* Méthode qui retourne une chaine de caractères nettoyée des cdata
	* Méthode qui controle une chaine de caractères pour un fichier .xml
	* Si la chaine est vide ou numérique : la chaine est retournée sans modification
	* Autrement, la chaine est encadrée automatiquement par "<![CDATA[ ... ]]>" si besoin.
	* Si "<![CDATA[" et "]]>" sont présents à l'intérieur de la chaine, alors conversion
	* en entités HTML.
	*
	* @param	str		chaine de caractères à nettoyer
	* @return	string	chaine de caractères nettoyée
	* @author	Stephane F,
	**/
	public static function cdataCheck($str) {
		$str = str_ireplace('!CDATA', '&#33;CDATA', $str);
		return str_replace(']]>', ']]&gt;', $str);
	}

	/**
	 * Méthode qui vérifie que la langue courante du site est valide
	 * @author	Stephane F
	 **/
	public function validateLang() {
		# récupération des langues enregistrées dans le fichier de configuration du plugin
		if($this->getParam('flags')!='')
			$this->aLangs = explode(',', $this->getParam('flags'));

		# validation de la langue coutante du site
		$this->lang = in_array($this->lang, $this->aLangs) ? $this->lang : $_SESSION['default_lang'];
	}

	/********************************/
	/* core/lib/class.plx.motor.php	*/
	/* core/lib/class.plx.feed.php	*/
	/********************************/

	/**
	 * Méthode qui modifie les chemins de PluXml en tenant compte de la langue
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function ConstructLoadPlugins() {
		echo '<?php ';?>
			# initialisation n° page statique comme page d accueil (recupérée dans plxMotorGetStatiques)
			$this->aConf['homestatic'] = '';
?><?php

		# modification des chemins d'accès
		echo '<?php $this_lang = "'.$this->lang.'";'; ?>
			$this->aConf['default_lang'] = $this_lang;
			$this->aConf['racine_articles'] = $this->aConf['racine_articles'].$this_lang.'/';
			$this->aConf['racine_statiques'] = $this->aConf['racine_statiques'].$this_lang.'/';
			$this->aConf['racine_commentaires'] = $this->aConf['racine_commentaires'].$this_lang.'/';
			path('XMLFILE_CATEGORIES', PLX_ROOT.PLX_CONFIG_PATH.$this_lang.'/categories.xml');
			path('XMLFILE_STATICS', PLX_ROOT.PLX_CONFIG_PATH.$this_lang.'/statiques.xml');
			path('XMLFILE_TAGS', PLX_ROOT.PLX_CONFIG_PATH.$this_lang.'/tags.xml');
?><?php

		# modification des infos du site en fonction de la langue
		if(file_exists(PLX_ROOT.PLX_CONFIG_PATH.'plugins/'.__CLASS__.'.xml')) {# Config exist
			echo '<?php '; ?>
				$this->aConf['title'] = '<?= $this->getParam('title_'.$this->lang) ?>';
				$this->aConf['description'] = '<?= $this->getParam('description_'.$this->lang) ?>';
				$this->aConf['meta_description'] = '<?= $this->getParam('meta_description_'.$this->lang) ?>';
				$this->aConf['meta_keywords'] = '<?= $this->getParam('meta_keywords_'.$this->lang) ?>';
?><?php
			if($this->getParam('lang_style')) {
				echo '<?php '; ?>
					$theme = '<?= $this->getParam('style_'.$this->lang) ?>';
					if($theme!='' AND is_dir(PLX_ROOT.$this->aConf['racine_themes'].$theme)) {
						$this->aConf['style'] = $theme;
						$this->style = $theme;
					}
?><?php
			}
		}#FI Config exist

		# s'il faut un dossier medias différent pour chaque langue
		if($this->getParam('lang_medias_folder')) {
			echo '<?php '; ?>
				$this->aConf['medias'] = $this->aConf['medias'].$this_lang.'/';
?><?php
		}
	}

	/**
	 * Méthode qui vérifie que la langue est bien présente dans l'url
	 * @author	Stephane F, Thomas I.
	 **/
	public function PreChauffageBegin() {
		# utilisation de preg_replace pour être sur que la chaine commence bien par une langue
		if($this->lang != $_SESSION['default_lang']){# No default lang
			echo '<?php ';?>$this->get = preg_replace('~^(<?=$this->lang?>)/?(.*)~', "$2", $this->get);?><?php
		}else{# No duplicate content 4 default lang : remove it if find & redirect
			echo '<?php ';?>
			$countr = 0;
			$this->get = preg_replace('~^(<?=$this->lang?>/?)~', '', $this->get, 1, $countr);
			if($countr){
				header('Location: ' . $this->racine . $this->get);
				exit;
			}
?><?php
		}
	}

	/**
	 * Méthode qui récupère les dépendances sur les articles et les pages statiques
	 * @author	Stephane F, Thomas Ingles
	 **/

	public function plxMotorDemarrageEnd() {
		echo '<?php $this_lang = "'.$this->lang.'"; $this_class = "' . __CLASS__ .'";'; ?>
		$this->infos_arts = null;
		$this->infos_statics = null;

		if($this->mode=='article') {
			if(isset($this->plxRecord_arts)) {
				if($deplng = $this->plxRecord_arts->f('deplng')) {
					foreach($deplng as $mml_lang => $ident) {
						# récupération du titre de l article correspondant à la langue
						$root = PLX_ROOT.$this->aConf['racine_articles'];
						$root = str_replace('/'.$this_lang.'/', '/'.$mml_lang.'/', $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match('/^'.$ident.'(.*).xml$/', $file)) {
								$uniqart = $this->parseArticle($root.$file);
								if($uniqart['date'] <= date('YmdHi')) {
									$url = '/article'.intval($ident).'/'.$uniqart['url'];
									#if($mml_lang!=$_SESSION['default_lang']) $url = $mml_lang.$url;#BUG with 1st default lang
									$url = $mml_lang.$url;#Fix hook4art
									$this->infos_arts[$mml_lang]['img'] = '<img class="lang" src="'.$this->urlRewrite(PLX_PLUGINS.$this_class.'/img/'.$mml_lang.'.png').'" alt="'.$mml_lang.'" />';
									$this->infos_arts[$mml_lang]['link'] = '<a href="'.$url.'">'.plxUtils::strCheck($uniqart['title']).'</a>';
									$this->infos_arts[$mml_lang]['url'] = $url;
								}
								break;
							}
						}
						closedir($folder);
					}
				}
			}
		}
		elseif($this->mode=='static') {
			$deplng = null;
			if(isset($this->aStats[$this->cible]['deplng']) AND strpos($this->aStats[$this->cible]['deplng'],',') !== FALSE) {
				$values = explode('|', $this->aStats[$this->cible]['deplng']);
				foreach($values as $k => $v) {
					$tmp = explode(',', $v);
					$deplng[$tmp[0]] = $tmp[1];
				}
			}
			if($deplng) {
				foreach($deplng as $mml_lang => $id) {
					# récupération du titre de la page statique correspondant à la langue
					$root = PLX_ROOT.PLX_CONFIG_PATH;
					$root = str_replace('/'.$this_lang.'/', $mml_lang, $root);
					$filename=$root.$mml_lang.'/statiques.xml';
					if(is_file($filename)) {
						# Mise en place du parseur XML
						$data = implode('',file($filename));
						$parser = xml_parser_create(PLX_CHARSET);
						xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
						xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
						xml_parse_into_struct($parser,$data,$values,$iTags);
						xml_parser_free($parser);
						if(isset($iTags['statique']) AND isset($iTags['name'])) {
							$nb = sizeof($iTags['name']);
							$size=ceil(sizeof($iTags['statique'])/$nb);
							for($i=0;$i<$nb;$i++) {
								$attributes = $values[$iTags['statique'][$i*$size]]['attributes'];
								$number = $attributes['number'];
								if($number==$id) {
									$active = intval($attributes['active']);
									if($active) {
										$homestatic = plxUtils::getValue($values[$iTags['homeStatic'][$i]]['value']);
										if($homestatic)
											$url = $this->racine.$mml_lang.'/';
										else {
											$url = $mml_lang.'/static'.intval($id).'/'.$attributes['url'];
											//if($mml_lang!=$_SESSION['default_lang']) $url = $mml_lang.$url;
										}
										$title = plxUtils::getValue($values[$iTags['name'][$i]]['value']);
										$this->infos_statics[$mml_lang]['img'] = '<img class="lang" src="'.$this->urlRewrite(PLX_PLUGINS.$this_class.'/img/'.$mml_lang.'.png').'" alt="'.$mml_lang.'" />';
										$this->infos_statics[$mml_lang]['link'] = '<a href="'.$url.'">'.plxUtils::strCheck($title).'</a>';
										$this->infos_statics[$mml_lang]['url'] = $url;
										$this->infos_statics[$mml_lang]['homestatic'] = $homestatic;
									}
									break;
								}
							}
						}
					}
				}
			}
		}
?><?php
	}

	/**
	 * Méthode qui rédirige vers la bonne url après soumission d'un commentaire
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function plxMotorDemarrageNewCommentaire() {
		if($_SESSION['default_lang']!==$this->lang) {
			echo '<?php	'; ?>
			$url = $this->urlRewrite('?<?= $this->lang?>/article'.intval($this->plxRecord_arts->f('numero')).'/'.$this->plxRecord_arts->f('url'));
?><?php
		}
	}

	/**
	 * Méthode qui récupère les dépendances des pages statiques et la page statique comme page d'accueil
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function plxMotorGetStatiques() {
		echo '<?php '; ?>
			# Recuperation du numéro la page statique d\'accueil
			if(isset($iTags['homeStatic'])) {
				$homeStatic = plxUtils::getValue($iTags['homeStatic'][$i]);
				$this->aStats[$number]['homeStatic'] = plxUtils::getValue($values[$homeStatic]['value']);
				if($this->aStats[$number]['homeStatic']) {
					# n° de la page statique comme page d accueil
					$this->aConf['homestatic'] = $number;
				}
			} else {
				$this->aStats[$number]['homeStatic'] = 0;
			}
			# Recuperation des dépendances des pages statiques
			if(isset($iTags['deplng'])) {
				$deplng = plxUtils::getValue($iTags['deplng'][$i]);
				$this->aStats[$number]['deplng'] = plxUtils::getValue($values[$deplng]['value']);
			} else {
				$this->aStats[$number]['deplng'] = array();
			}
?><?php
	}

	/**
	 * Méthode qui récupère les dépendances entre articles dans le fichier .xml
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function plxMotorParseArticle() {
		echo '<?php '; ?>
			if(isset($iTags['deplng'])) {
				foreach($iTags['deplng'] as $k => $v) {
					$key = $values[$v]['value'];
					$val = explode(',', $key);
					$art['deplng'][$val[0]] = $val[1];
				}
			} else {
				$art["deplng"] = null;
			}
?><?php
	}

	/**
	 * Méthode qui s'assure que la langue est présente dans les liens de redirection de type 301
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function plxMotorRedir301() {
		if($this->lang!=$_SESSION['default_lang']) {
			echo '<?php $this_lang = "'.$this->lang.'";'; ?>
				if(!preg_match('#'.$this->racine.$this_lang.'/#', $url)) {
					$url = str_replace($this->racine, $this->racine.$this_lang.'/', $url);
				}
?><?php
		}
	}

	/********************************/
	/* core/lib/class.plx.admin.php	*/
	/********************************/

	/**
	 * Méthode qui modifie les chemins de PluXml en supprimant la langue
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function plxAdminEditConfiguration() {
		# sauvegarde des paramètres pris en compte en fonction de la langue
		echo '<?php '; ?>
		$_lang = $this->aConf['default_lang'];
		if(preg_match('/parametres_base/',basename($_SERVER['SCRIPT_NAME']))) {
			$plugin = $this->plxPlugins->aPlugins['<?= __CLASS__ ?>'];
			$plugin->setParam('title_'.$_lang, $_POST['title'], 'cdata');
			$plugin->setParam('description_'.$_lang, $_POST['description'], 'cdata');
			$plugin->setParam('meta_description_'.$_lang, $_POST['meta_description'], 'cdata');
			$plugin->setParam('meta_keywords_'.$_lang, $_POST['meta_keywords'], 'cdata');
			$plugin->saveParams();
			# pour etre réactualiser au chargement du plugin si on a change la langue par defaut du site
			unset($_SESSION['default_lang']);
		}
?><?php
		# theme différent pour chaque langue
		if($this->getParam('lang_style')) {
			echo '<?php '; ?>
				if(preg_match('/parametres_themes/',basename($_SERVER['SCRIPT_NAME']))) {
					$plugin = $this->plxPlugins->aPlugins['<?= __CLASS__ ?>'];
					$plugin->setParam('style_'.$_lang, $_POST['style'], 'cdata');
					$plugin->saveParams();
					# pour ne pas écraser le style de l installation
					$_POST['style'] = $this->aConf['style'];
				}
?><?php
		}
		# pour ne pas écraser la langue par défaut, les chemins racine_articles, racine_statiques et racine_commentaires
		# Fix add /fr/fr/en/de/ when save statics on 5.8.3 pluxml release (maybe before) : $global is for <= 5.7 AND $content is for >= 5.8
		echo '<?php $this_lang = "'.$this->lang.'";'; ?>
		if(!isset($content['racine_articles'])){
			#$content['default_lang'] = $global['default_lang'] = isset($_SESSION['default_lang'])? $_SESSION['default_lang']:$this->aConf['default_lang'];#;
			$content['racine_articles'] = $global['racine_articles'] = str_replace('/'.$this_lang.'/', '/', $this->aConf['racine_articles']);
			$content['racine_statiques'] = $global['racine_statiques'] = str_replace('/'.$this_lang.'/', '/', $this->aConf['racine_statiques']);
			$content['racine_commentaires'] = $global['racine_commentaires'] = str_replace('/'.$this_lang.'/', '/', $this->aConf['racine_commentaires']);
		}
?><?php
		# pour ne pas écraser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php '?>
				$content['medias'] = $global['medias'] = str_replace('/'.$this_lang.'/', '/', $this->aConf['medias']);
?><?php
		}
	}

	/**
	 * Méthode qui ajoute une nouvelle clé dans le fichier xml des pages statiques pour savoir
	 * si une page statique est configurée comme page d'accueil (valeur boolean 0/1)
	 * @author	Stephane F, Thomas I.
	 **/
	public function plxAdminEditStatiquesUpdate() {
		echo '<?php '; ?>
			if(!isset($content['homeStatic']))
				$this->aStats[$static_id]['homeStatic'] = 0;
			else
				$this->aStats[$static_id]['homeStatic'] = $content['homeStatic'][0]==$static_id;
?><?php
	}

	/**
	 * Méthode qui enregistre une nouvelle clé dans le fichier xml des pages statiques pour stocker
	 * le n° de la page statique d'accueil et les id des pages pour les langues liées
	 * @author	Stephane F, Thomas I.
	 **/
	public function plxAdminEditStatiquesXml() {
		echo '<?php '; ?>
			if(!isset($static['homeStatic'])) $static['homeStatic'] = 0;
#			$xml .= '<homeStatic><![CDATA['.plxUtils::cdataCheck($static['homeStatic']).']]></homeStatic>';#5.7
			$xml .= '<homeStatic><![CDATA['.<?=__CLASS__?>::cdataCheck($static['homeStatic']).']]></homeStatic>';#ALLBySelf
			# dépendances des pages statiques
			if(!isset($static['deplng'])) $static['deplng']='';
#			$xml .= '<deplng><![CDATA['.plxUtils::cdataCheck($static['deplng']).']]></deplng>';#5.7
			$xml .= '<deplng><![CDATA['.<?=__CLASS__?>::cdataCheck($static['deplng']).']]></deplng>';#ALLBySelf
?><?php
	}

	/**
	 * Méthode qui enregistre dans les articles les dépendances (identifiants par langue)
	 * @author	Stephane F, Thomas I.
	 **/
	public function plxAdminEditArticleXml() {
		if(isset($_POST['deplng'])) {
			foreach($_POST['deplng'] as $lang => $ident) {
				$id = intval($ident);
				if($id>0) {
					echo '<?php ';?>
						$xml .= '	<deplng><![CDATA[<?=self::cdataCheck($lang.','.str_pad($id,4,'0',STR_PAD_LEFT))?>]]></deplng>' . PHP_EOL;
					?><?php
				}

			}
		}
	}

	/**
	 * Méthode qui enregistre les dépendances dans le fichier statiques.xml de la langue courante
	 * @author	Stephane F, Thomas I.
	 **/
	public function plxAdminEditStatique() {
		echo '<?php '; ?>
			if(isset($content['deplng'])) {
				$values = array();
				foreach($content['deplng'] as $mml_lang => $ident) {
					$id = intval($ident);
					if($id>0) {
						$values[] = $mml_lang.','.str_pad($id,3,'0',STR_PAD_LEFT);
					}
				}
				$this->aStats[$content['id']]['deplng'] = implode('|', $values);
			}
?><?php
	}

	/*******************************/
	/* core/lib/class.plx.show.php */
	/*******************************/

	/**
	 * Méthode qui modifie l'url des pages statiques en rajoutant la langue courante dans le lien du menu de la page
	 * @author	Stephane F, Thomas I.
	 **/
	public function plxShowStaticListEnd() {
		if($_SESSION['default_lang']==$this->lang) return;
		echo '<?php '; ?>
		foreach($menus as $idx => $menu) {
			if($this->plxMotor->aConf['urlrewriting']) {
				$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine.'<?= $this->lang ?>/', $menu);
			}
		}
?><?php
	}

	/**********************/
	/* core/admin/top.php */
	/**********************/

	/**
	 * Méthode qui affiche les langues sous forme de drapeaux, nom ou liste déroulante
	 * return	stdio
	 * @author	Stephane F, Thomas I.
	 **/
	public function AdminTopBottom() {

		$aLabels = unserialize($this->getParam('labels'));

		if($this->aLangs) {
			$ruri = '';
			if(strstr($_SERVER['REQUEST_URI'],'?')){
				$ruri = htmlentities('&'.substr($_SERVER['REQUEST_URI'], (strpos($_SERVER['REQUEST_URI'], '?') + 1)));
			}
			echo '<div id="mmlangs">';
			# affichage sous forme de liste déroulante
			if($this->getParam('display')=='listbox') {
				echo '<select onchange="self.location=\'?lang=\'+this.options[this.selectedIndex].value">';
				foreach($this->aLangs as $idx=>$lang) {
					$sel = $this->lang==$lang ? ' selected="selected"':'';
					echo '<option value="'.$lang.$ruri.'"'.$sel.'>'. $aLabels[$lang].'</option>';
				}
				echo '</select>';
			# affichage sous forme de drapeaux ou de texte
			} else {
				foreach($this->aLangs as $lang) {
					$sel = $this->lang==$lang ? ' active' : '';
					if($this->getParam('display')=='flag') {
						$img = '<img class="lang'.$sel.'" src="'.PLX_PLUGINS.__CLASS__.'/img/'.$lang.'.png" alt="'.$lang.'" />';
						echo '<a href="?lang='.$lang.$ruri.'">'.$img.'</a>';
					} else {
						echo '<a class="lang'.$sel.'" href="?lang='.$lang.$ruri.'">'.$aLabels[$lang].'</a>';
					}
				}
			}
			echo '</div>';
		}

		# message d'information utilisateur si la réécriture d'url n'est pas activée Parse error: syntax error, unexpected 'url' (T_STRING), expecting ',' or ';' in core/admin/top.php(148) : eval()'d code on line 2 ::: addslashes
		echo '<?php '; ?>
		if($plxAdmin->aConf['urlrewriting']!='1') {
			echo '<p class="warning">Plugin <?=__CLASS__?><br /><?= plxUtils::strCheck($this->getLang('L_ERR_URL_REWRITING')) ?></p>'.PHP_EOL;
			plxMsg::Display();
		}
?><?php
	}

	/**
	 * Méthode qui démarre la bufférisation de sortie
	 * @author	Stephane F
	 **/
	public function AdminTopEndHead() {
		echo '<?php ';?>ob_start();?><?php
	}

	/************************/
	/* core/admin/admin.php */
	/************************/

	/* méthodes qui gèrent les dépendances entre articles - E/S fichiers .xml */
	public function AdminArticlePostData() {
		echo '<?php ';?>$art['deplng'] = $_POST['deplng'];?><?php
	}
	public function AdminArticlePreview() {
		echo '<?php ';?>$art['deplng'] = $_POST['deplng'];?><?php
	}
	public function AdminArticleParseData() {
		echo '<?php ';?>$art['deplng'] = $result['deplng'];?><?php
	}
	public function AdminArticleInitData() {
		echo '<?php ';?>$art['deplng'] = null;?><?php
	}

	/**
	 * Méthode qui affiche les dépendances d'articles entre les langues
	 * @author	Stephane F, Thomas I.
	 **/
	public function AdminArticleContent() {
		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_ARTICLE').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $mml_lang) {
				if($this->lang!=$mml_lang) {
					echo '<?php $mml_lang = "'.$mml_lang.'"; $this_lang = "'.$this->lang.'"; $this_class = "' . __CLASS__ .'";'; ?>
					$img = '<img src="'.PLX_PLUGINS.$this_class.'/img/'.$mml_lang.'.png" alt="'.$mml_lang.'" />';
					$id = $titre = '';
					if(isset($art['deplng'][$mml_lang])) {
						$id = $art['deplng'][$mml_lang];
						$id = intval($id)>0 ? str_pad($id,4,'0',STR_PAD_LEFT) : '';
						# récupération du titre de l article correspondant à la langue
						$root = PLX_ROOT.$plxAdmin->aConf['racine_articles'];
						$root = str_replace('/'.$this_lang.'/', '/'.$mml_lang.'/', $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match('/^'.$id.'(.*).xml$/', $file)) {
								$uniqart = $plxAdmin->parseArticle($root.$file);
								$titre = $uniqart['title'];
								$titre = '<a href="?lang='.$mml_lang.'&amp;a='.$id.'">'.plxUtils::strCheck($titre).'</a>';
								break;
							}
						}
						closedir($folder);
					}
					# affichage
					$fld = '<input value="'.$id.'" type="text" name="deplng['.$mml_lang.']" maxlength="4" size="2" />';
					echo '<li>'.$img.' '.$fld.' '.$titre.'</li>';
?><?PHP
				}
			}
			echo '</ul>';
		}
	}

	/****************************/
	/* core/admin/statiques.php */
	/****************************/

	/**
	 * Méthode qui modifie l'ordre des appels lors de la modif de la liste des pages statiques
	 * Le chemin de la langue des pages statiques peut-être perdu lors du renommage #next gen ready Fx
		 * @author Thomas Ingles
	 **/
	public function AdminStaticsPrepend() {
		echo '<?php '; ?>
		# On édite les pages statiques
		if(!empty($_POST)) {
			# Controle de l'accès à la page en fonction du profil de l'utilisateur connecté
			$plxAdmin->checkProfil(PROFIL_MANAGER);
			$plxAdmin->editStatiques($_POST);#Fix lost path lng on next gen (old maybe work) # Before editConf /!\
			$plxAdmin->editConfiguration(!empty($_POST['homeStatic']) ? array('homestatic'=>$_POST['homeStatic'][0]) : array('homestatic'=>''));
			header('Location: statiques.php');
			exit;
		}
?><?php
	}

	/***************************/
	/* core/admin/statique.php */
	/***************************/

	/**
	 * Méthode qui affiche les dépendances des pages statiques entre les langues
	 * @author	Stephane F, Thomas I.
	 **/
	public function AdminStatic() {
		echo '<?php $this_lang = "'.$this->lang.'"; $this_class = "' . __CLASS__ .'";'; ?>
		# récupération des dépendances des pages et stockage dans un tableau pour manipulation + facile
		$deplng = array();
		if(isset($plxAdmin->aStats[$id]['deplng']) AND !empty($plxAdmin->aStats[$id]['deplng']) AND strpos($plxAdmin->aStats[$id]['deplng'],',') !== FALSE) {
			$values = explode('|', $plxAdmin->aStats[$id]['deplng']);
			foreach($values as $k => $v) {
				$tmp = explode(',', $v);
				$deplng[$tmp[0]] = $tmp[1];
			}
		}
?><?php
		# affichage des drapeaux
		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_STATIC').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $mml_lang) {
				if($this->lang!=$mml_lang) {
					echo '<?php $mml_lang="'.$mml_lang.'";'; ?>
					# recherche du titre de la page statique
					$mmlImg = '<img src="'.PLX_PLUGINS.$this_class.'/img/'.$mml_lang.'.png" alt="'.$mml_lang.'" />';
					$mmlId = $mmlTitre = '';
					if(isset($deplng[$mml_lang])) {
						$mmlId = $deplng[$mml_lang];
						$mmlId = intval($mmlId)>0 ? str_pad($mmlId,3,'0',STR_PAD_LEFT) : '';
						# récupération du titre de la page statique correspondant à la langue
						$mmlRoot = PLX_ROOT.PLX_CONFIG_PATH;
						$mmlRoot = str_replace('/'.$this_lang.'/', '/'.$mml_lang.'/', $mmlRoot);
						$filename=$mmlRoot.$mml_lang.'/statiques.xml';
						if(is_file($filename)) {
							# Mise en place du parseur XML
							$data = implode('',file($filename));
							$parser = xml_parser_create(PLX_CHARSET);
							xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
							xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
							xml_parse_into_struct($parser,$data,$values,$iTags);
							xml_parser_free($parser);
							if(isset($iTags['statique']) AND isset($iTags['name'])) {
								$nb = sizeof($iTags['name']);
								$size=ceil(sizeof($iTags['statique'])/$nb);
								for($i=0;$i<$nb;$i++) {
									$attributes = $values[$iTags['statique'][$i*$size]]['attributes'];
									$number = $attributes['number'];
									if($number==$mmlId) {
										# Récupération du nom de la page statique
										$mmlTitre = plxUtils::getValue($values[$iTags['name'][$i]]['value']);
										$mmlTitre = '<a href="?lang='.$mml_lang.'&amp;p='.$mmlId.'">'.plxUtils::strCheck($mmlTitre).'</a>';
										break;
									}
								}
							}
						}
					}
					# affichage
					$mmlFld = '<input value="'.$mmlId.'" type="text" name="deplng['.$mml_lang.']" maxlength="3" size="2" />';
					echo '<li>'.$mmlImg.' '.$mmlFld.' '.$mmlTitre.'</li>';
?><?php
				}
			}
			echo '</ul>';
		}
	}

	/***********************/
	/* core/admin/foot.php */
	/***********************/

	/**
	 * Méthode qui rajoute la langue courante dans les liens des articles et des pages statiques permettant
	 * de les visualiser coté visiteurs (liens "Voir", "Visualiser la page statique sur le site", etc...)
	 * @author	Stephane F, Thomas I.
	 **/
	public function AdminFootEndBody() {
		echo '<?php '; ?>
			$output = ob_get_clean();
			if (!preg_match('/parametres/',basename($_SERVER['SCRIPT_NAME']))) {
				$output = preg_replace('~('.$plxAdmin->racine.')(article[\w\d-]+\/)~', '$1<?=$this->lang?>/$2', $output);
				$output = preg_replace('~('.$plxAdmin->racine.')(static[\w\d-]+\/)~', '$1<?=$this->lang?>/$2', $output);
			}
			echo $output;
		?><?php
	}

	/**************************/
	/* core/admin/prepend.php */
	/**************************/

	/**
	 * Méthode pour définir la langue à utiliser dans l'administration en fonction du profil utilisateur
	 * Fix $this->getParam('user_lang') is empty @ constructor + traitement coté administration (swd)
	 * @author	Stephane F
	 **/
	public function AdminPrepend() {
		# on change la langue de l'administration en fonction des drapeaux si parametre user_lang = 0
		if(!$this->getParam('user_lang') AND isset($_SESSION['data_lang'])) {
			echo '<?php ';?>
			$lang = $_SESSION['data_lang'];
			#Fix after logon (if redirected to plugin, or go in admin plugin is not in data lang in first time (2nd click o plug ok)
			if(isset($_SESSION['admin_lang']) AND $_SESSION['admin_lang'] != $lang){
				$_SESSION['admin_lang'] = $lang;
				header('Location: ' . $_SERVER['REQUEST_URI']);#Fix Next gen? Logon : langues des plugins = default_lang
				exit;
			}
?><?php
		}
	}

	/*************************************/
	/* core/admin/parametres_avances.php */
	/*************************************/

	/**
	 * Méthode qui modifie les chemins de PluXml en supprimant la langue
	 * @author	Stephane F, Thomas I
	 **/
	public function AdminSettingsAdvancedTop() {

		# pour ne pas écraser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php ';?>
			$plxAdmin->aConf['racine_articles'] = str_replace('/<?=$this->lang?>/', '/', $plxAdmin->aConf['racine_articles']);
			$plxAdmin->aConf['racine_statiques'] = str_replace('/<?=$this->lang?>/', '/', $plxAdmin->aConf['racine_statiques']);
			$plxAdmin->aConf['racine_commentaires'] =  str_replace('/<?=$this->lang?>/', '/', $plxAdmin->aConf['racine_commentaires']);
		?><?php

		# pour ne pas écraser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php ';?>$plxAdmin->aConf['medias'] =  str_replace('/<?=$this->lang?>/', '/', $plxAdmin->aConf['medias']); ?><?php
		}
	}

	/**********************************/
	/* core/admin/parametres_base.php */
	/**********************************/

	/**
	 * Méthode qui remet la vraie langue par défaut de PluXml du fichier parametres.xml, sans tenir compte du multilangue
	 * @author	Stephane F Thomas I
	 **/
	public function AdminSettingsBaseTop() {
		echo '<?php ';?>$plxAdmin->aConf['default_lang'] = $_SESSION['default_lang'];?><?php
	}

	/**************/
	/* /index.php */
	/**************/

	/**
	 * Méthode qui modifie les liens en tenant compte de la langue courante et de la réécriture d'urls
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function IndexEnd() {

		$lang = $_SESSION['default_lang']==$this->lang ? '' : $this->lang.'/';
echo '<?php $mml_lang="'.$lang.'";'; ?>
		$output = strtr($output, array(
			'href="'.$plxMotor->racine.'"' => 'href="'.$plxMotor->racine.$mml_lang.'"',
			$plxMotor->racine.'article' => $plxMotor->racine.$mml_lang.'article',
			$plxMotor->racine.'static' => $plxMotor->racine.$mml_lang.'static',
			$plxMotor->racine.'categorie' => $plxMotor->racine.$mml_lang.'categorie',
			$plxMotor->racine.'tag' => $plxMotor->racine.$mml_lang.'tag',
			$plxMotor->racine.'archives' => $plxMotor->racine.$mml_lang.'archives',
			$plxMotor->racine.'feed/' => $plxMotor->racine.'feed/'.$mml_lang,
			$plxMotor->racine.'page' => $plxMotor->racine.$mml_lang.'page',
			$plxMotor->racine.'blog' => $plxMotor->racine.$mml_lang.'blog',
			PLX_PLUGINS => $plxMotor->aConf['racine_plugins'],
			'href="'.$plxMotor->racine.$_SESSION['default_lang'].'/' => 'href="'.$plxMotor->racine
		));
?><?php
	}

	/**
	 * Méthode qui affiche les balises <link rel="alternate"> de tous les articles dépendants par langue
	 * Mofifiable par les hooks plxMyMultiLingueThemeEndHeadBegin plxMyMultiLingueThemeEndHead
	 * @author	Stephane F, Thomas I.
	 * Note : never use $output var here :/
	 **/
	public function ThemeEndHead() {
		echo '<?php $this_lang = "'.$this->lang.'";'; ?>

		$outLnk = '';

		#Hook Plugins plxMyMultiLingueThemeEndHeadBegin
		if(eval($plxMotor->plxPlugins->callHook('<?=__CLASS__.__FUNCTION__?>Begin'))) return;

		if($plxMotor->mode=='article') {
			# affichage du hreflang pour la langue courante
			$url = $plxMotor->urlRewrite('article'.intval($plxMotor->cible).'/'.$plxMotor->plxRecord_arts->f('url'));
			if($this_lang!=$_SESSION['default_lang']) $url = $this_lang.$url;
			$outLnk .= '	<link rel="alternate" hreflang="'.$this_lang.'" href="'.$url.'" />'.PHP_EOL;
			if($plxMotor->infos_arts) {
				foreach($plxMotor->infos_arts as $mml_lang => $data) {
					$outLnk .= '	<link rel="alternate" hreflang="'.$mml_lang.'" href="'.$data['url'].'" />'.PHP_EOL;
				}
			}
		}
		if($plxMotor->mode=='static') {
			# affichage du hreflang pour la langue courante
			$url = $plxMotor->urlRewrite('static'.intval($plxMotor->cible).'/'.$plxMotor->aStats[$plxMotor->cible]['url']);
			if($this_lang!=$_SESSION['default_lang']) $url = $this_lang.$url;
			if($plxMotor->aConf['homestatic'] == $plxMotor->cible)
				$outLnk .= '	<link rel="alternate" hreflang="'.$this_lang.'" href="'.$plxMotor->racine.'" />'.PHP_EOL;
			else
				$outLnk .= '	<link rel="alternate" hreflang="'.$this_lang.'" href="'.$url.'" />'.PHP_EOL;
			if($plxMotor->infos_statics) {
				foreach($plxMotor->infos_statics as $mml_lang => $data) {
					if($data['homestatic'])
						$outLnk .= '	<link rel="alternate" hreflang="'.$mml_lang.'" href="'.$plxMotor->racine.$mml_lang.'/" />'.PHP_EOL;
					else
						$outLnk .= '	<link rel="alternate" hreflang="'.$mml_lang.'" href="'.$plxMotor->racine.$data['url'].'" />'.PHP_EOL;
				}
			}
		}

		#Hook Plugins plxMyMultiLingueThemeEndHead
		eval($plxMotor->plxPlugins->callHook('<?=__CLASS__.__FUNCTION__?>'));

		echo $outLnk;

?><?php
	}

	/************/
	/* feed.php */
	/************/

	/**
	 * Méthode qui modifie les liens en tenant compte de la langue courante et de la réécriture d'urls
	 * @author	Stephane F, Thomas I.
	 **/
	public function FeedEnd() {
		$lang = $_SESSION['default_lang']!=$this->lang?$this->lang.'/':'';
		echo '<?php $mml_lang="'.$lang.'";'; ?>
		$output = strtr($output, array(
			$plxFeed->racine.'article' => $plxFeed->racine.$mml_lang.'article',
			$plxFeed->racine.'static' => $plxFeed->racine.$mml_lang.'static',
			$plxFeed->racine.'categorie' => $plxFeed->racine.$mml_lang.'categorie',
			$plxFeed->racine.'tag' => $plxFeed->racine.$mml_lang.'tag',
			$plxFeed->racine.'archives' => $plxFeed->racine.$mml_lang.'archives',
			$plxFeed->racine.'feed/' => $plxFeed->racine.'feed/'.$mml_lang,
			$plxFeed->racine.'page' => $plxFeed->racine.$mml_lang.'page',
			$plxFeed->racine.'blog' => $plxFeed->racine.$mml_lang.'blog',
			'<link>'.$plxFeed->racine.'</link>' => '<link>'.$plxFeed->racine.$mml_lang.'</link>'
		));
?><?php
	}

	/***************/
	/* sitemap.php */
	/***************/

	/**
	 * Méthode qui génère un sitemap en fonction d'une langue
		 * @author	Stephane F, Thomas I.
	 **/
	public function SitemapBegin() {
		# affichage du sitemapindex ou du sitemap de la langue
		if(!preg_match('~sitemap.php\/([a-zA-Z]{2})$~', $_SERVER['REQUEST_URI'], $capture)) {
			# création d'un sitemapindex
			header('Content-type: text/xml');
echo '<?xml version="1.0" encoding="' . strtolower(PLX_CHARSET) . '"?>' . PHP_EOL;
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php	foreach($this->aLangs as $lang) {?>
	<sitemap>
		<loc><?='<?=$plxMotor->racine?>sitemap.php/'.$lang?></loc>
	</sitemap>
<?php	}?>
</sitemapindex>
<?php
			echo '<?php return true; ?>';#stop here
		}
	}

	/**
	 * Méthode qui change la racine en fonction de la langue en cours
	 * Note : incertains de l'utilité de modifier la racine ici #Thomas
	 * puisque la langue par defaut n'a point de sur domaine
	 * Risque de dupliquer les contenus :/ Remplacement désactivé #plm Thomas. (Faire attention aux fin de PHP ? >)
	 * @author	Stephane F, Thomas I.
	 **/
	public function SitemapEnd() {

		$this->IndexEnd();
		#if($_SESSION['default_lang']==$this->lang) {
			#echo '<?php '; ? >
			#$output = strtr($output, array('<loc>'.$plxMotor->racine.'</loc>' => '<loc>'.$plxMotor->racine.'<?=$this->lang.'/'? ></loc>'));
			#? >< ?php
		#}
	}

	/*********************************/
	/* Hooks à mettre dans le thème  */
	/*********************************/

	/**
	 * Méthode qui affiche les drapeaux, le nom des langues ou une liste déroulante pour la partie visiteur du site
	 * ou les liens dépendants de l'article rédigé dans d'autres langues.
	 * Next step: Option List with good url (like flags)
	 * param	param	si valeur = 'artlinks' on affiche les liens dépendants de l'article
	 * return	stdio
	 * Possibility to modify with Hooks plxMyMultiLingueMyMultiLingue[flag]
	 * @author	Stephane F, Thomas Ingles
	 **/
	public function MyMultiLingue($param) {
		echo '<?php '; ?>
		$htmls = array('ul','li');#base elements
		$mmlCss = array('unstyled-list','');#class elements

		#Hook Plugins plxMyMultiLingueMyMultiLingue
		if(eval($plxMotor->plxPlugins->callHook('<?=__CLASS__.__FUNCTION__?>'))) return;

?><?php
		# Affichage de la liste des langue ou des drapeaux
		if($param=='') {
			$aLabels = unserialize($this->getParam('labels'));
			if($this->aLangs) {
				echo '<div id="mmlangs">';# Global
				if($this->getParam('display')=='listbox') {# Liste
					echo '<select onchange="self.location=\'<?= $plxShow->plxMotor->urlRewrite() ?>\'+this.options[this.selectedIndex].value">'.PHP_EOL;
					foreach($this->aLangs as $idx=>$lang) {
						$sel = $this->lang==$lang ? ' selected="selected"':'';
						$val_lang = $_SESSION['default_lang']==$lang ? "" : $lang.'/';
						echo '<option value="'.$val_lang.'"'.$sel.'>'. $aLabels[$lang].'</option>'.PHP_EOL;
					}
					echo '</select>'.PHP_EOL;
				} else {# Drapeaux
					echo '<?php #' . __CLASS__ . '->' . __FUNCTION__ . '()' . PHP_EOL; ?>

					$output = '';
					$display = '<?= $this->getParam('display') ?>';
					$this_lang = '<?= $this->lang ?>';
					$this_aLangs = <?= var_export($this->aLangs,!0); ?>;
					$aLabels = <?= var_export($aLabels,!0); ?>;

					foreach($this_aLangs as $idx => $mml_lang) {
						$mml_title = ($this_lang == $mml_lang? '⌂': '⏏') . ' ' . ucfirst($mml_lang);/* L_HOMEPAGE ⌂ &#x2302; ⏏	&#x23CF; */
						$sel = ($this_lang == $mml_lang)? ' active': '';
						$url_lang = ($this_lang == $mml_lang)? '#': $plxMotor->get;# Default Url
						$mml_deplng=FALSE;
						# article mode principal hook #sidebar
						if(strpos($plxMotor->mode, 'art') !== FALSE AND $plxMotor->plxRecord_arts) {
							if($this_lang == $mml_lang){#self
								$mml_title = plxUtils::strCheck($plxMotor->plxRecord_arts->f('title'));#Found lang title
								$url_lang = '#';#No reload
							}
							if(isset($plxMotor->infos_arts[$mml_lang])) {
								$mml_title = strip_tags($plxMotor->infos_arts[$mml_lang]['link']);#Found lang title
								$url_lang = $plxMotor->infos_arts[$mml_lang]['url'];#tep for lng url
							}
						}#fi art AND $plxMotor->plxRecord_arts

						# static mode principal hook #sidebar
						elseif($plxMotor->mode == 'static' AND strpos($plxMotor->aStats[$plxMotor->cible]['deplng'],',') !== FALSE) {
							$url_lang = $url_lang == $plxMotor->get? $mml_lang.'/': $url_lang;

#							$deplng = explode('|',$plxMotor->aStats[$plxMotor->cible]['deplng']);# str (empty) or like 'fr,002|en,001|es,001|ru,001'
							if(isset($plxMotor->infos_statics[$mml_lang])) {# have related page(s)
								$mml_title = strip_tags($plxMotor->infos_statics[$mml_lang]['link']);#Found lang title
								$url_lang = $plxMotor->infos_statics[$mml_lang]['url'];#tep for lng url
							}

							if($this_lang == $mml_lang){#self
								$mml_title = 	plxUtils::strCheck($plxMotor->aStats[ $plxMotor->cible ]['name']);#Found lang title
								$url_lang = '#';#No reload
							}
							elseif(isset($plxMotor->infos_statics[$mml_lang])){
								$mml_title = strip_tags($plxMotor->infos_statics[$mml_lang]['link']);#Found lang title
								$url_lang = str_replace($_SESSION['default_lang'].'/', '', $plxMotor->infos_statics[$mml_lang]['url']);
							}
						}#fi $plxMotor->aStats

						# add lng if same as begin AND not defaul lng #shift 2 lng HOME
						if($_SESSION['default_lang']!=$mml_lang ) {
							$url_lang = $url_lang == $plxMotor->get? $mml_lang.'/': $url_lang;
						}

						$cssImg = ($url_lang == $mml_lang . '/')? ' homepage': '';

						if($display=='flag') {
							$img = '<img class="lang'.$sel.$cssImg.'" src="<?= PLX_PLUGINS.__CLASS__?>/img/'.$mml_lang.'.png" alt="'.$mml_lang.'" />';
							$htm = '<'.$htmls[1].' class="'.$mmlCss[1].'"><a title="'.$mml_title.'" href="'.$url_lang.'">'.$img.'</a></'.$htmls[1].'>'.PHP_EOL;
						}else{
							$htm = '<'.$htmls[1].' class="'.$mmlCss[1].'"><a title="'.$mml_title.'" class="lang'.$sel.'" href="'.$url_lang.'">'. $aLabels[$mml_lang].'</a></'.$htmls[1].'>'.PHP_EOL;
						}

						#Hook Plugins plxMyMultiLingueMyMultiLingueFlag
						eval($plxMotor->plxPlugins->callHook('<?=__CLASS__.__FUNCTION__?>Flag'));

						# Possibility to modify $htm with Hook
						$output .= $htm;

					}

					echo '<'.$htmls[0].' class="'.$mmlCss[0].'">'.PHP_EOL.$output.'</'.$htmls[0].'>'.PHP_EOL;# ul
?><?php
				}
				echo '</div>';# Global END
			}
		}
		# Affichage des dépendances entre articles
		elseif($param=='artlinks') {
			echo '<?php '; ?>
				if(isset($plxMotor->infos_arts)) {
					$output = '';
					foreach($plxMotor->infos_arts as $mml_lang => $data) {
						$output .= '<'.$htmls[1].' class="'.$mmlCss[1].'">'.$data['img'].' '.$data['link'].'</'.$htmls[1].'>'.PHP_EOL;
					}
					if($output!='') {
						echo '<'.$htmls[0].' class="'.$mmlCss[0].'">'.PHP_EOL.$output.'</'.$htmls[0].'>'.PHP_EOL;
					}
				}
?><?php
		}
		# Affichage des dépendances entre statiques
		elseif($param=='staticlinks') {
			echo '<?php '; ?>
				if(isset($plxMotor->infos_statics)) {
					$output = '';
					foreach($plxMotor->infos_statics as $mml_lang => $data) {
						$output .= '<'.$htmls[1].' class="'.$mmlCss[1].'">'.$data['img'].' '.$data['link'].'</'.$htmls[1].'>'.PHP_EOL;
					}
					if($output!='') {
						echo '<'.$htmls[0].' class="'.$mmlCss[0].'">'.PHP_EOL.$output.'</'.$htmls[0].'>';
					}
				}
?><?php
		}
	}
}#class end