<?php
/**
 * Plugin plxMyMultiLingue
 *
 * @author	Stephane F
 *
 **/

class plxMyMultiLingue extends plxPlugin {

	public $aLangs = array(); # tableau des langues
	public $lang = ''; # langue courante
	public $plxMotorConstruct = false;

	/**
	 * Constructeur de la classe
	 *
	 * @param	default_lang	langue par défaut
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

		# récupération de la langue si présente dans l'url
		$get = plxUtils::getGets();
		if(preg_match('/^([a-zA-Z]{2})\/(.*)/', $get, $capture))
			$this->lang = $capture[1];
		elseif(isset($_SESSION['lang']))
			$this->lang = $_SESSION['lang'];
		elseif(isset($_COOKIE["plxMyMultiLingue"]))
			$this->lang = $_COOKIE["plxMyMultiLingue"];
		else
			$this->lang = $default_lang;

		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($this->lang);

		# droits pour accéder à la page config.php du plugin
		$this->setConfigProfil(PROFIL_ADMIN);

		# déclaration des hooks partie publique
		$this->addHook('IndexEnd', 'IndexEnd');
		$this->addHook('FeedEnd', 'FeedEnd');
		$this->addHook('SitemapBegin', 'SitemapBegin');

		# déclaration des hooks plxMotor
		$this->addHook('plxMotorConstruct', 'plxMotorConstruct');
		$this->addHook('plxMotorPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('plxMotorConstructLoadPlugins', 'ConstructLoadPlugins');

		# déclaration des hooks plxAdmin
		$this->addHook('plxAdminEditConfiguration', 'plxAdminEditConfiguration');

		# déclaration des hooks plxShow
		$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');

		# déclaration des hooks plxFeed
		$this->addHook('plxFeedConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxFeedPreChauffageBegin', 'PreChauffageBegin');

		# déclaration des hooks partie administration
		$this->addHook('AdminTopEndHead', 'AdminTopEndHead');
		$this->addHook('AdminTopBottom', 'AdminTopBottom');
		$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
		$this->addHook('AdminSettingsBaseTop', 'AdminSettingsBaseTop');
		$this->addHook('AdminArticleTop', 'AdminArticleTop');
		$this->addHook('AdminArticleContent', 'AdminArticleContent');

		# déclaration hook utilisateur à mettre dans le thème
		$this->addHook('MyMultiLingue', 'MyMultiLingue');

		# récupération des langues enregistrées dans le fichier de configuration du plugin
		if($this->getParam('flags')!='')
			$this->aLangs = explode(',', $this->getParam('flags'));

		$this->lang = $this->validLang($this->lang);

		# PLX_MYMULTILINGUE contient la liste des langues - pour être utilisé par d'autres plugins
		define('PLX_MYMULTILINGUE', $this->getParam('flags'));

	}

	/**
	 * Méthode executée à la désactivation du plugin
	 *
	 * @author	Stephane F
	 **/
	public function onDeactivate() {
		unset($_SESSION['lang']);
		unset($_SESSION['medias']);
		unset($_SESSION['folder']);
		unset($_SESSION['currentfolder']);
		unset($_COOKIE['plxMyMultiLingue']);
		setcookie('plxMyMultiLingue', '', time() - 3600);
	}

	/**
	 * Méthode qui créée les répertoires des langues (écran de config du plugin)
	 *
	 * @author	Stephane F
	 **/
	public function mkDirs() {

		$plxAdmin = plxAdmin::getInstance();

		# on nettoie les chemins
		$racine_articles = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_articles']);
		$racine_statiques = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_statiques']);
		$racine_commentaires =  str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_commentaires']);
		$racine_images = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['images']);

		if(isset($_POST['flags'])) {
			foreach($_POST['flags'] as $lang) {
				if(!is_dir(PLX_ROOT.$racine_articles.$lang))
					mkdir(PLX_ROOT.$racine_articles.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_statiques.$lang))
					mkdir(PLX_ROOT.$racine_statiques.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_commentaires.$lang))
					mkdir(PLX_ROOT.$racine_commentaires.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.$racine_images.$lang))
					mkdir(PLX_ROOT.$racine_images.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.PLX_CONFIG_PATH.$lang))
					mkdir(PLX_ROOT.PLX_CONFIG_PATH.$lang, 0755, true);
				plxUtils::write('',PLX_ROOT.PLX_CONFIG_PATH.$lang.'/index.html');
				plxUtils::write("<Files *>\n\tOrder allow,deny\n\tDeny from all\n</Files>",PLX_ROOT.PLX_CONFIG_PATH.$lang.'/.htaccess');
			}
		}

	}

	/**
	 * Méthode qui vérifie qu'une langue est bien gérer par le plugin
	 *
	 * param	lang		langue à tester
	 * return	string		langue passée au paramètre si elle est gérée sinon la langue par défaut de PluXml
	 * @author	Stephane F
	 **/
	public function validLang($lang) {
		return (in_array($lang, $this->aLangs) ? $lang : $this->default_lang);
	}

	/**
	 * Méthode qui renseigne la variable $this->lang avec la langue courante à utiliser lors de l'accès
	 * au site ou dans l'administration (cf COOKIE/SESSION)
	 *
	 * @author	Stephane F
	 **/
	public function getCurrentLang() {

		# sélection de la langue à partir dun drapeau
		if(isset($_GET["lang"]) AND !empty($_GET["lang"])) {

			$this->lang = $this->validLang(plxUtils::getValue($_GET["lang"]));

			if(defined("PLX_ADMIN")) {
				unset($_SESSION["medias"]);
				unset($_SESSION["folder"]);
				unset($_SESSION["currentfolder"]);
			}

			setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours
			$_SESSION["lang"] = $this->lang;

			# redirection avec un minimum de sécurité sur lurl
			if(defined("PLX_ADMIN")) {
				if(preg_match("@^".plxUtils::getRacine()."(.*)@", $_SERVER["HTTP_REFERER"]))
					header("Location: ".plxUtils::strCheck($_SERVER["HTTP_REFERER"]));
				else
					header("Location: ".plxUtils::getRacine());
				exit;
			} else {
				$this->plxMotorConstruct = true;
			}

		}

		# récupération de la langue si on accède au site à partir du sitemap
		if(preg_match("/sitemap\.php\??([a-zA-Z]+)?/", $_SERVER["REQUEST_URI"], $capture)) {
			$this->lang = $this->validLang(plxUtils::getValue($capture[1]));
			return;
		}

		setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours

	}

	/********************************/
	/* core/lib/class.plx.motor.php	*/
	/********************************/

	/**
	 * Méthode qui fat la redirection lors du changement de langue coté visiteur
	 *
	 * @author	Stephane F
	 **/
	public function plxMotorConstruct() {

		if($this->plxMotorConstruct) {

			if($this->getParam('redirect_ident')) {

				echo '<?php

				$url = $_SERVER["PHP_SELF"];
				if(preg_match("@^(".plxUtils::getRacine()."(index.php\?)?)([a-z]{2})\/(.*)@", $_SERVER["HTTP_REFERER"], $uri)) {

					if(preg_match("/^(article([0-9]+))\/(.*)/", $uri[4], $m)) {
						$file = $this->plxGlob_arts->query("/".str_pad($m[2],4,"0",STR_PAD_LEFT)."\.(.*)\.xml$/");
						$match = preg_match("/(.*)\.([a-z0-9-]+)\.xml$/", $file[0], $f);
						if($file AND $match) {
							$url = $uri[1]."'.$this->lang.'/".$m[1]."/".$f[2];
						} else {
							$url = $uri[1]."'.$this->lang.'/404";
						}
					}
					elseif(preg_match("/^(static([0-9]+))\/(.*)/", $uri[4], $m)) {
						if($sUrl = plxUtils::getValue($this->aStats[str_pad($m[2],3,"0",STR_PAD_LEFT)]["url"])) {
							$url =  $uri[1]."'.$this->lang.'/".$m[1]."/".$sUrl;
						} else {
							$url = $uri[1]."'.$this->lang.'/404";
						}
					}
					elseif(preg_match("/^(categorie([0-9]+))\/(.*)/", $uri[4], $m)) {
						if($sUrl = plxUtils::getValue($this->aCats[str_pad($m[2],3,"0",STR_PAD_LEFT)]["url"])) {
							$url =  $uri[1]."'.$this->lang.'/".$m[1]."/".$sUrl;
						} else {
							$url = $uri[1]."'.$this->lang.'/404";
						}
					} else {
							$url = $uri[1]."'.$this->lang.'/".$uri[4];
					}
				} else {
					$url = $_SERVER["HTTP_REFERER"];
				}

				header("Location: ".plxUtils::strCheck($url));
				exit;

				?>';
			} else {
				header('Location: '.plxUtils::strCheck($_SERVER['PHP_SELF']));
				exit;
			}
		}
	}

	/**
	 * Méthode qui vérifie que la langue est bien présente dans l'url
	 *
	 * @author	Stephane F
	 **/
	public function PreChauffageBegin() {

		echo '<?php
			# utilisation de preg_replace pour être sur que la chaine commence bien par la langue
			$this->get = preg_replace("/^'.$this->lang.'\/(.*)/", "$1", $this->get);
		?>';

	}

	/**
	 * Méthode qui modifie les chemins de PluXml en tenant compte de la langue
	 *
	 * @author	Stephane F
	 **/
	public function ConstructLoadPlugins() {

		# sauvegarde de la langue stockée dans le fichier parametres.xml dans uen variable de session
		echo '<?php
			if(!isset($_SESSION["plxMyMultiLingue"]["default_lang"])) {
				$_SESSION["plxMyMultiLingue"]["default_lang"] = $this->aConf["default_lang"];
			}
		?>';

		# récupération de la langue à utiliser
		$this->getCurrentLang();

		# modification des chemins d'accès
		echo '<?php
			$this->aConf["racine_articles"] = $this->aConf["racine_articles"]."'.$this->lang.'/";
			$this->aConf["racine_statiques"] = $this->aConf["racine_statiques"]."'.$this->lang.'/";
			$this->aConf["racine_commentaires"] = $this->aConf["racine_commentaires"]."'.$this->lang.'/";
			path("XMLFILE_CATEGORIES", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/categories.xml");
			path("XMLFILE_STATICS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/statiques.xml");
			path("XMLFILE_TAGS", PLX_ROOT.PLX_CONFIG_PATH."'.$this->lang.'/tags.xml");
		?>';

		# modification des infos du site en fonction de la langue
		echo '<?php
			if(file_exists(PLX_ROOT.PLX_CONFIG_PATH."plugins/plxMyMultiLingue.xml")) {
				$this->aConf["title"] = "'.$this->getParam("title_".$this->lang).'";
				$this->aConf["description"] = "'.$this->getParam("description_".$this->lang).'";
				$this->aConf["meta_description"] = "'.$this->getParam("meta_description_".$this->lang).'";
				$this->aConf["meta_keywords"] = "'.$this->getParam("meta_keywords_".$this->lang).'";
			}
		?>';

		# s'il faut un dossier images et documents différents pour chaque langue
		if($this->getParam('lang_images_folder')) {
			echo '<?php $this->aConf["images"] = $this->aConf["images"]."'.$this->lang.'/"; ?>';
		}
	}

	/********************************/
	/* core/lib/class.plx.show.php 	*/
	/********************************/

	/**
	 * Méthode qui modifie l'url des pages statiques en rajoutant la langue courante dans le lien du menu de la page
	 *
	 * @author	Stephane F
	 **/
	public function plxShowStaticListEnd() {

		echo '<?php
		foreach($menus as $idx => $menu) {
			if(strpos($menu[0], "static-home")===false) {
				if($this->plxMotor->aConf["urlrewriting"]) {
					$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine."'.$this->lang.'/", $menu);
				}
			}
		}
		?>';
	}

	/********************************/
	/* core/lib/class.plx.admin.php	*/
	/********************************/

	/**
	 * Méthode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function plxAdminEditConfiguration() {

		# sauvegarde des parametres pris en compte en fonction de la langue
		echo '<?php
		if (preg_match("/parametres_base/",basename($_SERVER["SCRIPT_NAME"]))) {
			$lang = $this->aConf["default_lang"];
			$plugin = $this->plxPlugins->aPlugins["plxMyMultiLingue"];
			$plugin->setParam("title_".$lang, $_POST["title"], "cdata");
			$plugin->setParam("description_".$lang, $_POST["description"], "cdata");
			$plugin->setParam("meta_description_".$lang, $_POST["meta_description"], "cdata");
			$plugin->setParam("meta_keywords_".$lang, $_POST["meta_keywords"], "cdata");
			$plugin->saveParams();
		}
		?>';

		# pour ne pas écraser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$global["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $global["racine_articles"]);
			$global["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $global["racine_statiques"]);
			$global["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $global["racine_commentaires"]);
		?>';

		# pour ne pas écraser le chemin du dossier des images et des documents
		if($this->getParam('lang_images_folder')) {
			echo '<?php $global["images"] = str_replace("/'.$this->lang.'/", "/", $global["images"]); ?>';
		}

		# pour tenir compte des changements de paramètrage de la langue par défaut du site
		echo '<?php
			$_SESSION["plxMyMultiLingue"]["default_lang"] = $_POST["default_lang"];
		?>';

	}

	/*************************************/
	/* core/admin/parametres_avances.php */
	/*************************************/

	/**
	 * Méthode qui modifie les chemins de PluXml en supprimant la langue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsAdvancedTop() {

		# pour ne pas écraser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$plxAdmin->aConf["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_articles"]);
			$plxAdmin->aConf["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_statiques"]);
			$plxAdmin->aConf["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_commentaires"]);
		?>';

		# pour ne pas écraser le chemin du dossier des images et des documents
		if($this->getParam('lang_images_folder')) {
			echo '<?php $plxAdmin->aConf["images"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["images"]); ?>';
		}

	}

	/************************************/
	/* core/admin/parametres_base.php 	*/
	/************************************/

	/**
	 * Méthode qui remet la vraie langue par défaut de PluXml du fichier parametres.xml, sans tenir compte du multilangue
	 *
	 * @author	Stephane F
	 **/
	public function AdminSettingsBaseTop() {

		echo '<?php
			$plxAdmin->aConf["default_lang"] = $_SESSION["plxMyMultiLingue"]["default_lang"];
		?>';

	}

	/********************************/
	/* core/admin/top.php 			*/
	/********************************/

	/**
	 * Méthode qui affiche les drapeaux ou le nom des langues dans l'administration
	 *
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function AdminTopBottom() {

		$aLabels = unserialize($this->getParam('labels'));

		# affichage des drapeaux
		if($this->aLangs) {
			echo '<div id="langs">';
			foreach($this->aLangs as $lang) {
				$sel = $this->lang==$lang ? " active" : "";
				if($this->getParam('display')=='flag') {
					$img = '<img class="lang'.$sel.'" src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png" alt="'.$lang.'" />';
					echo '<a href="?lang='.$lang.'">'.$img.'</a>';
				} else {
					echo '<a class="lang'.$sel.'" href="?lang='.$lang.'">'.$aLabels[$lang].'</a>';
				}

			}
			echo '</div>';
		}

	}

	/********************************/
	/* core/admin/article.php		*/
	/********************************/

	/**
	 * Méthode qui démarre la bufférisation de sortie
	 *
	 * @author	Stephane F
	 **/
	public function AdminArticleTop() {

		echo '<?php ob_start(); ?>';
	}

	/**
	 * Méthode qui rajoute la langue courante dans les liens des articles
	 *
	 * @author	Stephane F
	 **/
	public function AdminArticleContent() {

		echo '<?php echo preg_replace("/(article[a-z0-9-]+\/)/", "'.$this->lang.'/$1", ob_get_clean()); ?>';

	}

	/********************************/
	/* index.php 					*/
	/********************************/

	/**
	 * Méthode qui modifie les liens en tenant compte de la langue courante et de la réécriture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function IndexEnd() {

		echo '<?php
		if($plxMotor->aConf["urlrewriting"]) {
			$output = str_replace($plxMotor->racine."article", $plxMotor->racine."'.$this->lang.'/article", $output);
			$output = str_replace($plxMotor->racine."static", $plxMotor->racine."'.$this->lang.'/static", $output);
			$output = str_replace($plxMotor->racine."categorie", $plxMotor->racine."'.$this->lang.'/categorie", $output);
			$output = str_replace($plxMotor->racine."tag", $plxMotor->racine."'.$this->lang.'/tag", $output);
			$output = str_replace($plxMotor->racine."archives", $plxMotor->racine."'.$this->lang.'/archives", $output);
			$output = str_replace($plxMotor->racine."feed", $plxMotor->racine."feed/'.$this->lang.'", $output);
			$output = str_replace($plxMotor->racine."page", $plxMotor->racine."'.$this->lang.'/page", $output);
			$output = str_replace($plxMotor->racine."blog", $plxMotor->racine."'.$this->lang.'/blog", $output);
		} else {
			$output = str_replace("?article", "?'.$this->lang.'/article", $output);
			$output = str_replace("?static", "?'.$this->lang.'/static", $output);
			$output = str_replace("?categorie", "?'.$this->lang.'/categorie", $output);
			$output = str_replace("?tag", "?'.$this->lang.'/tag", $output);
			$output = str_replace("?archives", "?'.$this->lang.'/archives", $output);
			$output = str_replace("?rss", "?'.$this->lang.'/rss", $output);
			$output = str_replace("?page", "?'.$this->lang.'/page", $output);
			$output = str_replace("?blog", "?'.$this->lang.'/blog", $output);
		}
		?>';

	}

	/********************************/
	/* feed.php 					*/
	/********************************/

	/**
	 * Méthode qui modifie les liens en tenant compte de la langue courante et de la réécriture d'urls
	 *
	 * @author	Stephane F
	 **/
	public function FeedEnd() {

		echo '<?php
		if($plxFeed->aConf["urlrewriting"]) {
			$output = str_replace($plxFeed->racine."article", $plxFeed->racine."'.$this->lang.'/article", $output);
			$output = str_replace($plxFeed->racine."static", $plxFeed->racine."'.$this->lang.'/static", $output);
			$output = str_replace($plxFeed->racine."categorie", $plxFeed->racine."'.$this->lang.'/categorie", $output);
			$output = str_replace($plxFeed->racine."tag", $plxFeed->racine."'.$this->lang.'/tag", $output);
			$output = str_replace($plxFeed->racine."archives", $plxFeed->racine."'.$this->lang.'/archives", $output);
			$output = str_replace($plxFeed->racine."feed", $plxFeed->racine."feed/'.$this->lang.'", $output);
			$output = str_replace($plxFeed->racine."page", $plxFeed->racine."'.$this->lang.'/page", $output);
			$output = str_replace($plxFeed->racine."blog", $plxFeed->racine."'.$this->lang.'/blog", $output);
		} else {
			$output = str_replace("?article", "?'.$this->lang.'/article", $output);
			$output = str_replace("?static", "?'.$this->lang.'/static", $output);
			$output = str_replace("?categorie", "?'.$this->lang.'/categorie", $output);
			$output = str_replace("?tag", "?'.$this->lang.'/tag", $output);
			$output = str_replace("?archives", "?'.$this->lang.'/archives", $output);
			$output = str_replace("?rss", "?'.$this->lang.'/rss", $output);
			$output = str_replace("?page", "?'.$this->lang.'/page", $output);
			$output = str_replace("?blog", "?'.$this->lang.'/blog", $output);
		}
		?>';

	}

	/********************************/
	/* sitemap.php 					*/
	/********************************/

	/**
	 * Méthode qui génère un sitemap en fonction d'une langue
	 *
	 * @author	Stephane F
	 **/
	public function SitemapBegin() {

		# affichage du sitemapindex ou du sitemap de la langue
		if(empty($_SERVER['QUERY_STRING'])) {
			# création d'un sitemapindex
			echo '<?php echo "<?xml version=\"1.0\" encoding=\"".strtolower(PLX_CHARSET)."\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">" ?>';
			foreach($this->aLangs as $lang) {
				echo '<?php echo "\n\t<sitemap>"; ?>';
				echo '<?php echo "\n\t\t<loc>".$plxMotor->racine."sitemap.php?'.$lang.'</loc>"; ?>';
				echo '<?php echo "\n\t</sitemap>"; ?>';
			}
			echo '<?php echo "\n</sitemapindex>"; ?>';
			echo '<?php return true; ?>';
		}
	}

	public function SitemapEnd() {

		$this->IndexEnd();

	}

	/********************************/
	/* theme: affichage du drapeaux */
	/********************************/

	/**
	 * Méthode qui affiche les drapeaux ou le nom des langues pour la partie visiteur du site
	 *
	 * return	stdio
	 * @author	Stephane F
	 **/
	public function MyMultiLingue() {

		$aLabels = unserialize($this->getParam('labels'));

		if($this->aLangs) {
			echo '<div id="langs">';
			echo '<ul>';
			foreach($this->aLangs as $idx=>$lang) {
				$sel = $this->lang==$lang ? ' active':'';
				if($this->getParam('display')=='flag') {
					$img = '<img class=\"lang'.$sel.'\" src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.png\" alt=\"'.$lang.'\" />';
					echo '<li><?php echo "<a href=\"".$plxShow->plxMotor->urlRewrite("?lang='.$lang.'")."\">'.$img.'</a></li>"; ?>';
				} else {
					echo '<li><?php echo "<a class=\"lang'.$sel.'\" href=\"".$plxShow->plxMotor->urlRewrite("?lang='.$lang.'")."\">'. $aLabels[$lang].'</a></li>"; ?>';
				}

			}
			echo '</ul>';
			echo '</div>';
		}
	}

}
?>