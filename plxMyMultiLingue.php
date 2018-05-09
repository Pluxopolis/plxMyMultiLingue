<?php
/**
 * Plugin plxMyMultiLingue
 *
 * @author	Stephane F
 *
 **/

class plxMyMultiLingue extends plxPlugin {

	# tableau contenant la liste des langues g�r�es
	public $aLangs = array();
	# langue courante pour savoir dans quel dossier aller chercher les donn�es dans le dossier data
	public $lang = '';

	/**
	 * Constructeur de la classe
	 *
	 * @param	default_lang	langue par d�faut
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

		$this->lang = "";

		# recherche de la langue par d�faut de PluXml
		if(!isset($_SESSION['default_lang'])) {
			$file = file_get_contents(path('XMLFILE_PARAMETERS'));
			preg_match('#name="default_lang"><!\[CDATA\[([^\]]+)#',$file,$lang);
			$_SESSION['default_lang'] = empty($lang[1]) ? $default_lang : $lang[1];
			$_SESSION['data_lang'] = $_SESSION['default_lang'];
		}

		#===============================
		# traitement cot� administration
		#===============================
		if(defined('PLX_ADMIN')) {
			if(isset($_GET["lang"]) AND !empty($_GET["lang"])) {
				$this->lang = $_GET["lang"];
				$_SESSION['data_lang'] = $this->lang;

				if(preg_match('/\?lang='.$this->lang.'$/', $_SERVER['REQUEST_URI']))
					$redirect = 'index.php';
				else
					$redirect = preg_replace('/lang='.$this->lang.'&?/', '', $_SERVER['REQUEST_URI']);

				# r�initialisation des dossiers pour le gestionnaire de m�dias
				unset($_SESSION["medias"]);
				unset($_SESSION["folder"]);
				unset($_SESSION['currentfolder']);

				header("Location: ".$redirect);
				exit;
			}
			elseif(isset($_SESSION['data_lang']))
				$this->lang = $_SESSION['data_lang'];
			else
				$this->lang = $_SESSION['default_lang'];
		}
		#===============================
		# traitement cot� visiteur
		#===============================
		else {
			# recherche de la langue dans l'url si acc�s � partir du sitemap
			if(preg_match("/sitemap\.php\/?([a-zA-Z]{2})?/", $_SERVER["REQUEST_URI"], $capture)) {
				if(isset($capture[1]))
					$this->lang = $capture[1];
				else
					$this->lang = $_SESSION['default_lang'];
			} else {
				$get = plxUtils::getGets();
				if(preg_match('/^([a-zA-Z]{2})\/(.*)/', $get, $capture))
					$this->lang = $capture[1];
				else
					$this->lang = $_SESSION['default_lang'];
			}
		}

		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($this->lang);

		# validation de la langue courante
		$this->validateLang();

		# droits pour acc�der � la page config.php du plugin
		$this->setConfigProfil(PROFIL_ADMIN);

		# PLX_MYMULTILINGUE contient la liste des langues et la langue courante - pour �tre utilis� par d'autres plugins
		define('PLX_MYMULTILINGUE', serialize(array('langs' => $this->getParam('flags'), 'lang' => $this->lang)));
		
		if(!defined('PLX_ADMIN')) $_SESSION['lang'] = $this->lang;

		#====================================================
		# d�claration des hooks communs frontend et backend
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
		# D�claration des hooks pour la zone d'administration
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
			# core/admin/statique.php
			$this->addHook('AdminStatic', 'AdminStatic');
			# core/admin/parametres_avances.php
			$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
			# core/admin/parametres_base.php
			$this->addHook('AdminSettingsBaseTop', 'AdminSettingsBaseTop');
		}

		#======================================================
		# D�claration des hooks pour la partie visiteur
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

			# hook utilisateur � mettre dans le th�me
			$this->addHook('MyMultiLingue', 'MyMultiLingue');

		}
	}
	
	public static function _Lang() {
		$def = unserialize(PLX_MYMULTILINGUE);
		if(isset($def['lang'])) {
			return $def['lang'];
		}
	}
	
	public static function _Langs() {
		$def = unserialize(PLX_MYMULTILINGUE);
		if(isset($def['langs'])) {
			return $def['langs'];
		}
	}	

	/*************************************/
	/* gestion active/deactive/update    */
	/*************************************/

	/**
	 * M�thode ex�cut�e � l'activation du plugin
	 *
	 * @author	Stephane F
	 **/
	public function onActivate() {
		if(file_exists(PLX_PLUGINS.'plxMyMultiLingue/update')) chmod(PLX_PLUGINS.'plxMyMultiLingue/update',0644); # en attendant la modif en natif dans class.plx.plugins.php
		# Mise en cache du css partie administration
		$src_cssfile = PLX_PLUGINS.'plxMyMultiLingue/css/admin.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/plxMyMultiLingue.admin.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# Mise en cache du ccs partie visiteurs
		$src_cssfile = PLX_PLUGINS.'plxMyMultiLingue/css/site.css';
		$dst_cssfile = PLX_ROOT.PLX_CONFIG_PATH.'plugins/plxMyMultiLingue.site.css';
		plxUtils::write(file_get_contents($src_cssfile), $dst_cssfile);
		# R�g�n�ration des caches css
		$plxAdmin = plxAdmin::getInstance();
		$plxAdmin->plxPlugins->cssCache('admin');
		$plxAdmin->plxPlugins->cssCache('site');
	}

	/**
	 * M�thode ex�cut�e � la d�sactivation du plugin
	 *
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
	 * M�thode appel�e par la classe plxPlugins et execut�e si un fichier "upadate" est pr�sent dans le dossier du plugin
	 * On demande une mise � jour du cache css
	 * Nouvelles r�gles css pour le plugin avec PluXml 5.6 et PluCSS 1.2 pour afficher les drapeaux dans l'action bar
	 *
	 * @author	Stephane F
	 **/
	public function onUpdate() {
		# demande de mise � jour du cache css
		return array('cssCache' => true);
	}

	/**
	 * M�thode qui cr�er les r�pertoires des langues (�cran de config du plugin)
	 *
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
	 * M�thode qui v�rifie que la langue courante du site est valide
	 *
	 * @author	Stephane F
	 **/
	public function validateLang() {

		# r�cup�ration des langues enregistr�es dans le fichier de configuration du plugin
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
	 * M�thode qui modifie les chemins de PluXml en tenant compte de la langue
	 *
	 * @author	Stephane F
	 **/
	public function ConstructLoadPlugins() {

		echo '<?php
			# initialisation n� page statique comme page d accueil (recup�r�e dans plxMotorGetStatiques)
			$this->aConf["homestatic"] = "";
		?>';

		# modification des chemins d'acc�s
		echo '<?php
			$this->aConf["default_lang"] ="'.$this->lang.'";
			$this->aConf["racine_articles"] = $this->aConf["racine_articles"]."'.$this->lang.'/";
			$this->aConf["racine_statiques"] = $this->aConf["racine_statiques"]."'.$this->lang.'/";
			$this->aConf["racine_commentaires"] = $this->aConf["racine_commentaires"]."'.$this->lang.'/";
			path("XMLFILE_CATEGORIES", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/categories.xml");
			path("XMLFILE_STATICS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/statiques.xml");
			path("XMLFILE_TAGS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/tags.xml");
		?>';

		# modification des infos du site en fonction de la langue
		if(file_exists(PLX_ROOT.PLX_CONFIG_PATH."plugins/plxMyMultiLingue.xml")) {
			echo '<?php
				$this->aConf["title"] = "'.$this->getParam("title_".$this->lang).'";
				$this->aConf["description"] = "'.$this->getParam("description_".$this->lang).'";
				$this->aConf["meta_description"] = "'.$this->getParam("meta_description_".$this->lang).'";
				$this->aConf["meta_keywords"] = "'.$this->getParam("meta_keywords_".$this->lang).'";
			?>';
			if($this->getParam("lang_style")) {
				echo '<?php
					$theme = "'.$this->getParam("style_".$this->lang).'";
					if($theme!="" AND is_dir(PLX_ROOT.$this->aConf["racine_themes"].$theme)) {
						$this->aConf["style"] = $theme;
						$this->style = $theme;
					}
				?>';
			}
		}

		# s'il faut un dossier medias diff�rent pour chaque langue
		if($this->getParam('lang_medias_folder')) {
			echo '<?php
				$this->aConf["medias"] = $this->aConf["medias"]."'.$this->lang.'/";
			?>';
		}
	}

	/**
	 * M�thode qui v�rifie que la langue est bien pr�sente dans l'url
	 *
	 * @author	Stephane F
	 **/
	public function PreChauffageBegin() {

		echo '<?php
			# utilisation de preg_replace pour �tre sur que la chaine commence bien par la langue
			$this->get = preg_replace("/^'.$this->lang.'\/(.*)/", "$1", $this->get);
		?>';

	}

	/**
	 * M�thode qui r�cup�re les d�pendances sur les articles et les pages statiques
	 *
	 * @author	Stephane F
	 **/

	public function plxMotorDemarrageEnd() {
		echo '<?php
		$this->infos_arts = null;
		$this->infos_statics = null;

		if($this->mode=="article") {
			if(isset($this->plxRecord_arts)) {
				if($deplng = $this->plxRecord_arts->f("deplng")) {
					foreach($deplng as $lang => $ident) {
						# r�cup�ration du titre de l article correspondant � la langue
						$root = PLX_ROOT.$this->aConf["racine_articles"];
						$root = str_replace("/'.$this->lang.'/", "/".$lang."/", $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match("/^".$ident."(.*).xml$/", $file)) {
								$uniqart = $this->parseArticle($root.$file);
								if($uniqart["date"] <= date("YmdHi")) {
									$url = "/article".intval($ident)."/".$uniqart["url"];
									if($lang!=$_SESSION["default_lang"]) $url = $lang.$url;
									$this->infos_arts[$lang]["img"] = "<img class=\"lang\" src=\"".$this->urlRewrite(PLX_PLUGINS."plxMyMultiLingue/img/".$lang.".png")."\" alt=\"".$lang."\" />";
									$this->infos_arts[$lang]["link"] = "<a href=\"".$url."\">".plxUtils::strCheck($uniqart["title"])."</a>";
									$this->infos_arts[$lang]["url"] = $url;
								}
								break;
							}
						}
						closedir($folder);
					}
				}
			}
		}
		elseif($this->mode=="static") {
			$deplng = null;
			if(isset($this->aStats[$this->cible]["deplng"]) AND !empty($this->aStats[$this->cible]["deplng"])) {
				$values = explode("|", $this->aStats[$this->cible]["deplng"]);
				foreach($values as $k => $v) {
					$tmp = explode(",", $v);
					$deplng[$tmp[0]] = $tmp[1];
				}
			}
			if($deplng) {
				foreach($deplng as $lang => $id) {
					# r�cup�ration du titre de la page statique correspondant � la langue
					$root = PLX_ROOT.PLX_CONFIG_PATH;
					$root = str_replace("/'.$this->lang.'/", $lang, $root);
					$filename=$root.$lang."/statiques.xml";
					if(is_file($filename)) {
						# Mise en place du parseur XML
						$data = implode("",file($filename));
						$parser = xml_parser_create(PLX_CHARSET);
						xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
						xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
						xml_parse_into_struct($parser,$data,$values,$iTags);
						xml_parser_free($parser);
						if(isset($iTags["statique"]) AND isset($iTags["name"])) {
							$nb = sizeof($iTags["name"]);
							$size=ceil(sizeof($iTags["statique"])/$nb);
							for($i=0;$i<$nb;$i++) {
								$attributes = $values[$iTags["statique"][$i*$size]]["attributes"];
								$number = $attributes["number"];
								if($number==$id) {
									$active = intval($attributes["active"]);
									if($active) {
										$homestatic = plxUtils::getValue($values[$iTags["homeStatic"][$i]]["value"]);
										if($homestatic)
											$url = $this->racine.$lang."/";
										else {
											$url = "/static".intval($id)."/".$attributes["url"];
											if($lang!=$_SESSION["default_lang"]) $url = $lang.$url;
										}
										$title = plxUtils::getValue($values[$iTags["name"][$i]]["value"]);
										$this->infos_statics[$lang]["img"] = "<img class=\"lang\" src=\"".$this->urlRewrite(PLX_PLUGINS."plxMyMultiLingue/img/".$lang.".png")."\" alt=\"".$lang."\" />";
										$this->infos_statics[$lang]["link"] = "<a href=\"".$url."\">".plxUtils::strCheck($title)."</a>";
										$this->infos_statics[$lang]["url"] = $url;
										$this->infos_statics[$lang]["homestatic"] = $homestatic;
									}
									break;
								}
							}
						}
					}
				}
			}
		}

		?>';
	}

	/**
	 * M�thode qui r�dirige vers la bonne url apr�s soumission d'un commentaire
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorDemarrageNewCommentaire() {

		if($_SESSION['default_lang']!==$this->lang) {
			echo '<?php
				$url = $this->urlRewrite("?'.$this->lang.'/article".intval($this->plxRecord_arts->f("numero"))."/".$this->plxRecord_arts->f("url"));
			?>';
		}
	}

	/**
	 * M�thode qui r�cup�re les d�pendances des pages statiques et la page statique comme page d'accueil
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorGetStatiques() {

		echo '<?php
			# Recuperation du num�ro la page statique d\'accueil
			if(isset($iTags["homeStatic"])) {
				$homeStatic = plxUtils::getValue($iTags["homeStatic"][$i]);
				$this->aStats[$number]["homeStatic"] = plxUtils::getValue($values[$homeStatic]["value"]);
				if($this->aStats[$number]["homeStatic"]) {
					# n� de la page statique comme page d accueil
					$this->aConf["homestatic"] = $number;
				}
			} else {
				$this->aStats[$number]["homeStatic"] = 0;
			}
			# Recuperation des d�pendances des pages statiques
			if(isset($iTags["deplng"])) {
				$deplng = plxUtils::getValue($iTags["deplng"][$i]);
				$this->aStats[$number]["deplng"] = plxUtils::getValue($values[$deplng]["value"]);
			} else {
				$this->aStats[$number]["deplng"] = array();
			}
		?>';
	}

	/**
	 * M�thode qui r�cup�re les d�pendances entre articles dans le fichier .xml
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorParseArticle() {
		echo '<?php
			if(isset($iTags["deplng"])) {
				foreach($iTags["deplng"] as $k => $v) {
					$key = $values[$v]["value"];
					$val = explode(",", $key);
					$art["deplng"][$val[0]] = $val[1];
				}
			} else {
				$art["deplng"] = null;
			}
			?>';
	}

	/**
	 * M�thode qui s'assure que la langue est pr�sente dans les liens de redirection de type 301
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorRedir301() {
		if($this->lang!=$_SESSION['default_lang']) {
			echo '<?php
				if(!preg_match("#".$this->racine."'.$this->lang.'/#", $url)) {
					$url = str_replace($this->racine, $this->racine."'.$this->lang.'/", $url);
				}
			?>';
		}
	}

	/********************************/
	/* core/lib/class.plx.admin.php	*/
	/********************************/

	/**
	 * M�thode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditConfiguration() {

		# sauvegarde des param�tres pris en compte en fonction de la langue
		echo '<?php
		if(preg_match("/parametres_base/",basename($_SERVER["SCRIPT_NAME"]))) {
			$_lang = $this->aConf["default_lang"];
			$plugin = $this->plxPlugins->aPlugins["plxMyMultiLingue"];
			$plugin->setParam("title_".$_lang, $_POST["title"], "cdata");
			$plugin->setParam("description_".$_lang, $_POST["description"], "cdata");
			$plugin->setParam("meta_description_".$_lang, $_POST["meta_description"], "cdata");
			$plugin->setParam("meta_keywords_".$_lang, $_POST["meta_keywords"], "cdata");
			$plugin->saveParams();
			# pour etre r�actualiser au chargement du plugin si on a change la langue par defaut du site
			unset($_SESSION["default_lang"]);
		}
		?>';

		# theme diff�rent pour chaque langue
		if($this->getParam("lang_style")) {
			echo '<?php
				if(preg_match("/parametres_themes/",basename($_SERVER["SCRIPT_NAME"]))) {
					$_lang = $this->aConf["default_lang"];
					$plugin = $this->plxPlugins->aPlugins["plxMyMultiLingue"];
					$plugin->setParam("style_".$_lang, $_POST["style"], "cdata");
					$plugin->saveParams();
					# pour ne pas �craser le style de l installation
					$_POST["style"] = $this->aConf["style"];
				}
			?>';
		}

		# pour ne pas �craser la langue par d�faut, les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$global["default_lang"] = $_SESSION["default_lang"];
			$global["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $global["racine_articles"]);
			$global["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $global["racine_statiques"]);
			$global["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $global["racine_commentaires"]);
		?>';

		# pour ne pas �craser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php $global["medias"] = str_replace("/'.$this->lang.'/", "/", $global["medias"]); ?>';
		}

	}

	/**
	 * M�thode qui ajoute une nouvelle cl� dans le fichier xml des pages statiques pour savoir
	 * si une page statique est configur�e comme page d'accueil (valeur boolean 0/1)
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatiquesUpdate() {
		echo '<?php
			if(!isset($content["homeStatic"]))
				$this->aStats[$static_id]["homeStatic"] = 0;
			else
				$this->aStats[$static_id]["homeStatic"] = $content["homeStatic"][0]==$static_id;
		?>';
	}

	/**
	 * M�thode qui enregistre une nouvelle cl� dans le fichier xml des pages statiques pour stocker
	 * le n� de la page statique d'accueil
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatiquesXml() {
		echo '<?php
			if(!isset($static["homeStatic"])) $static["homeStatic"] = 0;
			$xml .= "<homeStatic><![CDATA[".plxUtils::cdataCheck($static["homeStatic"])."]]></homeStatic>";
			# d�pendances des pages statiques
			if(!isset($static["deplng"])) $static["deplng"]="";
			$xml .= "<deplng><![CDATA[".plxUtils::cdataCheck($static["deplng"])."]]></deplng>";
		?>';
	}

	/**
	 * M�thode qui enregistre dans les articles les d�pendances (identifiants par langue)
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditArticleXml() {

		if(isset($_POST['deplng'])) {
			foreach($_POST['deplng'] as $lang => $ident) {
				$id = intval($ident);
				if($id>0) {
					echo '<?php
						$xml .= "\t<deplng><![CDATA['.$lang.",".str_pad($id,4,"0",STR_PAD_LEFT).']]></deplng>\n";
					?>';
				}

			}
		}
	}

	/**
	 * M�thode qui enregistre les d�pendances dans le fichier statiques.xml de la langue courante
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditStatique() {
		echo '<?php
			if(isset($content["deplng"])) {
				$values = array();
				foreach($content["deplng"] as $lang => $ident) {
					$id = intval($ident);
					if($id>0) {
						$values[] = $lang.",".str_pad($id,3,"0",STR_PAD_LEFT);
					}
				}
				$this->aStats[$content["id"]]["deplng"] = implode("|", $values);
			}
		?>';
	}

	/********************************/
	/* core/lib/class.plx.show.php 	*/
	/********************************/

	/**
	 * M�thode qui modifie l'url des pages statiques en rajoutant la langue courante dans le lien du menu de la page
	 *
	 * @author	Stephane F
	 **/
	public function plxShowStaticListEnd() {

		if($_SESSION['default_lang']==$this->lang) return;

		echo '<?php
		foreach($menus as $idx => $menu) {
			if($this->plxMotor->aConf["urlrewriting"]) {
				$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine."'.$this->lang.'/", $menu);
			}
		}
		?>';
	}

	/********************************/
	/* core/admin/top.php 			*/
	/********************************/

	/**
	 * M�thode qui affiche les langues sous forme de drapeaux, nom ou liste d�roulante
	 *
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function AdminTopBottom() {

		$aLabels = unserialize($this->getParam('labels'));

		if($this->aLangs) {
			echo '<div id="langs">';
			# affichage sous forme de liste d�roulante
			if($this->getParam('display')=='listbox') {
				echo "<select onchange=\"self.location='?lang='+this.options[this.selectedIndex].value\">";
				foreach($this->aLangs as $idx=>$lang) {
					$sel = $this->lang==$lang ? ' selected="selected"':'';
					echo '<option value="'.$lang.'"'.$sel.'>'. $aLabels[$lang].'</option>';
				}
				echo '</select>';
			# affichage sous forme de drapeaux ou de texte
			} else {
				foreach($this->aLangs as $lang) {
					$sel = $this->lang==$lang ? " active" : "";
					if($this->getParam('display')=='flag') {
						$img = '<img class="lang'.$sel.'" src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png" alt="'.$lang.'" />';
						echo '<a href="?lang='.$lang.'">'.$img.'</a>';
					} else {
						echo '<a class="lang'.$sel.'" href="?lang='.$lang.'">'.$aLabels[$lang].'</a>';
					}
				}
			}
			echo '</div>';
		}

		# message d'information utilisateur si la r��criture d'url n'est pas activ�e
		$string = '
		if($plxAdmin->aConf["urlrewriting"]!="1") {
			echo "<p class=\"warning\">Plugin MyMultiLingue<br />'.$this->getLang("L_ERR_URL_REWRITING").'</p>";
			plxMsg::Display();
		}';
		echo '<?php '.$string.' ?>';

	}

	/**
	 * M�thode qui d�marre la buff�risation de sortie
	 *
	 * @author	Stephane F
	 **/
	public function AdminTopEndHead() {
		echo '<?php ob_start(); ?>';
	}

	/********************************/
	/* core/admin/admin.php 		*/
	/********************************/

	/* m�thodes qui g�rent les d�pendances entre articles - E/S fichiers .xml */

	public function AdminArticlePostData() {
		echo '<?php $art["deplng"] = $_POST["deplng"]; ?>';
	}

	public function AdminArticlePreview() {
		echo '<?php $art["deplng"] = $_POST["deplng"]; ?>';
	}

	public function AdminArticleParseData() {
		echo '<?php $art["deplng"] = $result["deplng"]; ?>';
	}

	public function AdminArticleInitData() {
		echo '<?php $art["deplng"] = null; ?>';
	}

	/**
	 * M�thode qui affiche les d�pendances d'articles entre les langues
	 *
	 * @author	Stephane F
	 **/
	public function AdminArticleContent() {

		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_ARTICLE').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $lang) {
				if($this->lang!=$lang) {
					echo '<?php
					$img = "<img src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png\" alt=\"'.$lang.'\" />";
					$id = $titre = "";
					if(isset($art["deplng"]["'.$lang.'"])) {
						$id = $art["deplng"]["'.$lang.'"];
						$id = intval($id)>0 ? str_pad($id,4,"0",STR_PAD_LEFT) : "";
						# r�cup�ration du titre de l article correspondant � la langue
						$root = PLX_ROOT.$plxAdmin->aConf["racine_articles"];
						$root = str_replace("/'.$this->lang.'/", "/'.$lang.'/", $root);
						$folder = opendir($root);
						while($file = readdir($folder)) {
							if(preg_match("/^".$id."(.*).xml$/", $file)) {
								$uniqart = $plxAdmin->parseArticle($root.$file);
								$titre = $uniqart["title"];
								$titre = "<a href=\"?lang='.$lang.'&amp;a=".$id."\">".plxUtils::strCheck($titre)."</a>";
								break;
							}
						}
						closedir($folder);
					}
					# affichage
					$fld = "<input value=\"".$id."\" type=\"text\" name=\"deplng['.$lang.']\" maxlength=\"4\" size=\"2\" />";
					echo "<li>".$img." ".$fld." ".$titre."</li>";
					?>';
				}
			}
			echo '</ul>';
		}
	}


	/********************************/
	/* core/admin/statique.php 		*/
	/********************************/

	/**
	 * M�thode qui affiche les d�pendances des pages statiques entre les langues
	 *
	 * @author	Stephane F
	 **/
	public function AdminStatic() {

		echo '<?php
		# r�cup�ration des d�pendances des pages et stockage dans un tableau pour manipulation + facile
		$deplng = array();

		if(isset($plxAdmin->aStats[$id]["deplng"]) AND !empty($plxAdmin->aStats[$id]["deplng"])) {
			$values = explode("|", $plxAdmin->aStats[$id]["deplng"]);
			foreach($values as $k => $v) {
				$tmp = explode(",", $v);
				$deplng[$tmp[0]] = $tmp[1];
			}
		}
		?>';

		# affichage des drapeaux
		if($this->aLangs) {
			echo '<p>'.$this->getLang('L_IDENT_STATIC').'</p>';
			echo '<ul class="unstyled-list">';
			foreach($this->aLangs as $lang) {
				if($this->lang!=$lang) {
					echo '<?php
					# recherche du titre de la page statique
					$img = "<img src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png\" alt=\"'.$lang.'\" />";
					$id = $titre = "";
					if(isset($deplng["'.$lang.'"])) {
						$id = $deplng["'.$lang.'"];
						$id = intval($id)>0 ? str_pad($id,3,"0",STR_PAD_LEFT) : "";
						# r�cup�ration du titre de la page statique correspondant � la langue
						$root = PLX_ROOT.PLX_CONFIG_PATH;
						$root = str_replace("/'.$this->lang.'/", "/'.$lang.'/", $root);
						$filename=$root."'.$lang.'/statiques.xml";
						if(is_file($filename)) {
							# Mise en place du parseur XML
							$data = implode("",file($filename));
							$parser = xml_parser_create(PLX_CHARSET);
							xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
							xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
							xml_parse_into_struct($parser,$data,$values,$iTags);
							xml_parser_free($parser);
							if(isset($iTags["statique"]) AND isset($iTags["name"])) {
								$nb = sizeof($iTags["name"]);
								$size=ceil(sizeof($iTags["statique"])/$nb);
								for($i=0;$i<$nb;$i++) {
									$attributes = $values[$iTags["statique"][$i*$size]]["attributes"];
									$number = $attributes["number"];
									if($number==$id) {
										# R�cup�ration du nom de la page statique
										$titre = plxUtils::getValue($values[$iTags["name"][$i]]["value"]);
										$titre = "<a href=\"?lang='.$lang.'&amp;p=".$id."\">".plxUtils::strCheck($titre)."</a>";
										break;
									}
								}
							}
						}
					}
					# affichage
					$fld = "<input value=\"".$id."\" type=\"text\" name=\"deplng['.$lang.']\" maxlength=\"3\" size=\"2\" />";
					echo "<li>".$img." ".$fld." ".$titre."</li>";
					?>';
				}
			}
			echo '</ul>';
		}

	}

	/********************************/
	/* core/admin/foot.php 			*/
	/********************************/

	/**
	 * M�thode qui rajoute la langue courante dans les liens des articles et des pages statiques permettant
	 * de les visualiser cot� visiteurs (liens "Voir", "Visualiser la page statique sur le site", etc...)
	 *
	 * @author	Stephane F
	 **/
	public function AdminFootEndBody() {

		echo '<?php
			$output = ob_get_clean();
			if (!preg_match("/parametres/",basename($_SERVER["SCRIPT_NAME"]))) {
				$output = preg_replace("#(".$plxAdmin->racine.")(article[a-z0-9-]+\/)#", "$1'.$this->lang.'/$2", $output);
				$output = preg_replace("#(".$plxAdmin->racine.")(static[a-z0-9-]+\/)#",  "$1'.$this->lang.'/$2", $output);
			}
			echo $output;
		?>';

	}

	/*************************************/
	/* core/admin/prepend.php            */
	/*************************************/

	/**
	 * M�thode pour d�finir la langue � utiliser dans l'administration en fonction du profil utilisateur
	 *
	 * @author	Stephane F
	 **/
	public function AdminPrepend() {
		# on change la langue de l'administration en fonction des drapeaux si parametre user_lang = 0
		if(!$this->getParam("user_lang") AND isset($_SESSION['data_lang'])) {
			echo '<?php	$lang = "'.$_SESSION['data_lang'].'"; ?>';
		}
	}

	/*************************************/
	/* core/admin/parametres_avances.php */
	/*************************************/

	/**
	 * M�thode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsAdvancedTop() {

		# pour ne pas �craser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$plxAdmin->aConf["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_articles"]);
			$plxAdmin->aConf["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_statiques"]);
			$plxAdmin->aConf["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_commentaires"]);
		?>';

		# pour ne pas �craser le chemin du dossier des medias
		if($this->getParam('lang_medias_folder')) {
			echo '<?php $plxAdmin->aConf["medias"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["medias"]); ?>';
		}

	}

	/************************************/
	/* core/admin/parametres_base.php 	*/
	/************************************/

	/**
	 * M�thode qui remet la vraie langue par d�faut de PluXml du fichier parametres.xml, sans tenir compte du multilangue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsBaseTop() {

		echo '<?php
			$plxAdmin->aConf["default_lang"] = $_SESSION["default_lang"];
		?>';

	}

	/********************************/
	/* /index.php 					*/
	/********************************/

	/**
	 * M�thode qui modifie les liens en tenant compte de la langue courante et de la r��criture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function IndexEnd() {

		$lang = $_SESSION['default_lang']==$this->lang ? "" : $this->lang."/";

		echo '<?php
			$output = str_replace("href=\"".$plxMotor->racine."\"", "href=\"".$plxMotor->racine."'.$lang.'\"", $output);
			$output = str_replace($plxMotor->racine."article", $plxMotor->racine."'.$lang.'article", $output);
			$output = str_replace($plxMotor->racine."static", $plxMotor->racine."'.$lang.'static", $output);
			$output = str_replace($plxMotor->racine."categorie", $plxMotor->racine."'.$lang.'categorie", $output);
			$output = str_replace($plxMotor->racine."tag", $plxMotor->racine."'.$lang.'tag", $output);
			$output = str_replace($plxMotor->racine."archives", $plxMotor->racine."'.$lang.'archives", $output);
			$output = str_replace($plxMotor->racine."feed/", $plxMotor->racine."feed/'.$lang.'", $output);
			$output = str_replace($plxMotor->racine."page", $plxMotor->racine."'.$lang.'page", $output);
			$output = str_replace($plxMotor->racine."blog", $plxMotor->racine."'.$lang.'blog", $output);
			$output = str_replace(PLX_PLUGINS, $plxMotor->aConf["racine_plugins"], $output);
			$output = str_replace("href=\"".$plxMotor->racine.$_SESSION["default_lang"]."/", "href=\"".$plxMotor->racine, $output);
		?>';
	}

	/**
	 * M�thode qui affiche les balises <link rel="alternate"> de tous les articles d�pendants par langue
	 *
	 * @author	Stephane F
	 **/
	public function ThemeEndHead() {
		echo '<?php
		if($plxMotor->mode=="article") {
			# affichage du hreflang pour la langue courante
			$url = "/article".intval($plxMotor->cible)."/".$plxMotor->plxRecord_arts->f("url");
			if("'.$this->lang.'"!=$_SESSION["default_lang"]) $url = "'.$this->lang.'".$url;
			echo "\t<link rel=\"alternate\" hreflang=\"'.$this->lang.'\" href=\"".$url."\" />\n";
			if($plxMotor->infos_arts) {
				foreach($plxMotor->infos_arts as $lang => $data) {
					echo "\t<link rel=\"alternate\" hreflang=\"".$lang."\" href=\"".$data["url"]."\" />\n";
				}
			}
		}
		if($plxMotor->mode=="static") {
			# affichage du hreflang pour la langue courante
			$url = "/static".intval($plxMotor->cible)."/".$plxMotor->aStats[$plxMotor->cible]["url"];
			if("'.$this->lang.'"!=$_SESSION["default_lang"]) $url = "'.$this->lang.'".$url;
			if($plxMotor->aConf["homestatic"] == $plxMotor->cible)
				echo "\t<link rel=\"alternate\" hreflang=\"'.$this->lang.'\" href=\"".$plxMotor->racine."\" />\n";
			else
				echo "\t<link rel=\"alternate\" hreflang=\"'.$this->lang.'\" href=\"".$url."\" />\n";
			if($plxMotor->infos_statics) {
				foreach($plxMotor->infos_statics as $lang => $data) {
					if($data["homestatic"])
						echo "\t<link rel=\"alternate\" hreflang=\"".$lang."\" href=\"".$plxMotor->racine.$lang."/\" />\n";
					else
						echo "\t<link rel=\"alternate\" hreflang=\"".$lang."\" href=\"".$data["url"]."\" />\n";
				}
			}
		}
		?>';
	}


	/********************************/
	/* feed.php 					*/
	/********************************/

	/**
	 * M�thode qui modifie les liens en tenant compte de la langue courante et de la r��criture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function FeedEnd() {

		$lang = $_SESSION['default_lang']==$this->lang ? "" : $this->lang."/";

		echo '<?php
			$output = str_replace($plxFeed->racine."article", $plxFeed->racine."'.$lang.'article", $output);
			$output = str_replace($plxFeed->racine."static", $plxFeed->racine."'.$lang.'static", $output);
			$output = str_replace($plxFeed->racine."categorie", $plxFeed->racine."'.$lang.'categorie", $output);
			$output = str_replace($plxFeed->racine."tag", $plxFeed->racine."'.$lang.'tag", $output);
			$output = str_replace($plxFeed->racine."archives", $plxFeed->racine."'.$lang.'archives", $output);
			$output = str_replace($plxFeed->racine."feed/", $plxFeed->racine."feed/'.$lang.'", $output);
			$output = str_replace($plxFeed->racine."page", $plxFeed->racine."'.$lang.'page", $output);
			$output = str_replace($plxFeed->racine."blog", $plxFeed->racine."'.$lang.'blog", $output);
			$output = str_replace("<link>".$plxFeed->racine."</link>", "<link>".$plxFeed->racine."'.$lang.'</link>", $output);
		?>';

	}

	/********************************/
	/* sitemap.php 					*/
	/********************************/

	/**
	 * M�thode qui g�n�re un sitemap en fonction d'une langue
	 *
	 * @author	Stephane F
	 **/
	public function SitemapBegin() {

		# affichage du sitemapindex ou du sitemap de la langue
		if(!preg_match("/sitemap.php\/([a-zA-Z]{2})$/", $_SERVER["REQUEST_URI"], $capture)) {
			# cr�ation d'un sitemapindex
			echo '<?php echo "<?xml version=\"1.0\" encoding=\"".strtolower(PLX_CHARSET)."\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">" ?>';
			foreach($this->aLangs as $lang) {
				echo '<?php echo "\n\t<sitemap>"; ?>';
				echo '<?php echo "\n\t\t<loc>".$plxMotor->racine."sitemap.php/'.$lang.'</loc>"; ?>';
				echo '<?php echo "\n\t</sitemap>"; ?>';
			}
			echo '<?php echo "\n</sitemapindex>"; ?>';
			echo '<?php return true; ?>';
		}
	}

	public function SitemapEnd() {

		$this->IndexEnd();

		$lang = $_SESSION['default_lang']==$this->lang ? "" : $this->lang."/";

		echo '<?php
			$output = str_replace("<loc>".$plxMotor->racine."</loc>", "<loc>".$plxMotor->racine."'.$lang.'</loc>", $output);
		?>';

	}

	/*********************************/
	/* Hooks � mettre dans le th�me  */
	/*********************************/

	/**
	 * M�thode qui affiche les drapeaux, le nom des langues ou une liste d�roulante pour la partie visiteur du site
	 * ou les liens d�pendants de l'article r�dig� dans d'autres langues
	 *
	 * param	param	si valeur = 'artlinks' on affiche les liens d�pendants de l'article
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function MyMultiLingue($param) {

		# Affichage des drapeaux
		if($param=="") {
			$aLabels = unserialize($this->getParam('labels'));
			if($this->aLangs) {
				echo '<div id="langs">';
				if($this->getParam('display')=='listbox') {
					$plxMotor =  plxMotor::getInstance();
					if(isset($plxMotor->infos_arts)) {
						$allPgLang = $plxMotor->infos_arts;
					} else {
						$allPgLang = $plxMotor->infos_statics;
					}
					if (isset($allPgLang)) { /* redirection sur la page concern�e */
						echo '<select onchange="self.location=this.options[this.selectedIndex].value">';
						foreach($this->aLangs as $idx=>$lang) {
							$sel = $this->lang==$lang ? ' selected="selected"':'';
							if (isset($allPgLang[$lang])) {
								$val_lang = (($_SESSION['default_lang']==$lang) ? '' : '/') . $allPgLang[$lang]["url"];
							} else { /* Par d�faut redirection sur home */
								if ($this->getParam('modif_url')) {
									$sURIend = ($_SESSION['default_lang']==$this->lang) ? $_SERVER[REQUEST_URI] : substr($_SERVER[REQUEST_URI], 3);
									$val_lang = ($_SESSION['default_lang']==$lang ? '' : '/' .$lang) . $sURIend;
								} else {
									$val_lang = '/'. $_SESSION['default_lang']==$lang ? "" : $lang.'/';
								}
							}
							echo '<option value="'.$val_lang.'"'.$sel.'>'. $aLabels[$lang].'</option>';
						}
					} else /* Par d�faut redirection sur home */ {
						echo '<select onchange="self.location=this.options[this.selectedIndex].value">';
						foreach($this->aLangs as $idx=>$lang) {
							$sel = $this->lang==$lang ? ' selected="selected"':'';
							if ($this->getParam('modif_url')) {
								$sURIend = ($_SESSION['default_lang']==$this->lang) ? $_SERVER[REQUEST_URI] : substr($_SERVER[REQUEST_URI], 3);
								$val_lang = ($_SESSION['default_lang']==$lang ? '' : '/' .$lang) . $sURIend;
							} else {
								$val_lang = '/'. $_SESSION['default_lang']==$lang ? "" : $lang.'/';
							}
							echo '<option value="'.$val_lang.'"'.$sel.'>'. $aLabels[$lang].'</option>';
						}
					}

					echo '</select>';
				} else {
					echo '<ul>';
					foreach($this->aLangs as $idx=>$lang) {
						$url_lang = $lang.'/';
						if($_SESSION['default_lang']==$lang) $url_lang="";
						$sel = $this->lang==$lang ? ' active':'';
						if($this->getParam('display')=='flag') {
							echo '<?php
								$img = "<img class=\"lang'.$sel.'\" src=\"".PLX_PLUGINS."plxMyMultiLingue/img/'.$lang.'.png"."\" alt=\"'.$lang.'\" />";
								echo "<li><a href=\"'.$url_lang.'\">".$img."</a></li>";
							?>';
						} else {
							echo '<li><?php echo "<a class=\"lang'.$sel.'\" href=\"'.$url_lang.'\">'. $aLabels[$lang].'</a></li>"; ?>';
						}
					}
					echo '</ul>';
				}
				echo '</div>';
			}
		}
		# Affichage des d�pendances entre articles
		elseif($param=="artlinks") {
			echo '<?php
				if(isset($plxMotor->infos_arts)) {
					$output = "";
					foreach($plxMotor->infos_arts as $lang => $data) {
						$output .= "<li>".$data["img"]." ".$data["link"]."</li>";
					}
					if($output!="") {
						echo "<ul class=\"unstyled-list\">".$output."</ul>";
					}
				}
			?>';
		}
		# Affichage des d�pendances entre articles
		elseif($param=="staticlinks") {
			echo '<?php
				if(isset($plxMotor->infos_statics)) {
					$output = "";
					foreach($plxMotor->infos_statics as $lang => $data) {
						$output .= "<li>".$data["img"]." ".$data["link"]."</li>";
					}
					if($output!="") {
						echo "<ul class=\"unstyled-list\">".$output."</ul>";
					}
				}
			?>';
		}
	}

}
?>
