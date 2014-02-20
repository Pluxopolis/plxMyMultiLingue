<?php
/**
 * Plugin plxMyMultiLingue
 *
 * @author	Stephane F
 *
 **/

class plxMyMultiLingue extends plxPlugin {

	public $aLangs = array(); # tableau des langues

	public $lang = ''; # la langue courante

	/**
	 * Constructeur de la classe
	 *
	 * @param	default_lang	langue par défaut
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

		# appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($default_lang);

		# droits pour accéder à la page config.php du plugin
		$this->setConfigProfil(PROFIL_ADMIN);

		# déclaration des hooks partie administration
		$this->addHook('plxMotorConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxFeedConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('AdminTopBottom', 'AdminTopBottom');
		$this->addHook('AdminTopEndHead', 'AdminTopEndHead');
		$this->addHook('plxAdminEditConfiguration', 'plxAdminEditConfiguration');
		$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
		$this->addHook('AdminArticleTop', 'AdminArticleTop');
		$this->addHook('AdminArticleContent', 'AdminArticleContent');

		# déclaration des hooks partie publique
		$this->addHook('plxMotorPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('plxFeedPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('IndexEnd', 'IndexEnd');
		$this->addHook('ThemeEndHead', 'ThemeEndHead');
		$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');
		$this->addHook('SitemapBegin', 'SitemapBegin');
		$this->addHook('SitemapEnd', 'SitemapEnd');

		# déclaration hook utilisateur à mettre dans le thème
		$this->addHook('MyMultiLingue', 'MyMultiLingue');

		# récupération des langues cochées dans la configuration
		if($this->getParam('flags')!='')
			$this->aLangs = explode(',', $this->getParam('flags'));

	}

	# Méthode qui créée les répertoires des langues (écran de config du plugin)
	public function mkDirs() {

		$plxAdmin = plxAdmin::getInstance();

		# on nettoie les chemins
		$racine_articles = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_articles']);
		$racine_statiques = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_statiques']);
		$racine_commentaires =  str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_commentaires']);

		# récupération des langues cochées dans la configuration
		$aLangs = array();

		if($this->getParam('flags')!='')
			$aLangs = explode(',', $this->getParam('flags'));

		foreach($aLangs as $lang) {
			if(!is_dir(PLX_ROOT.$racine_articles.$lang))
				mkdir(PLX_ROOT.$racine_articles.$lang, 0755, true);
			if(!is_dir(PLX_ROOT.$racine_statiques.$lang))
				mkdir(PLX_ROOT.$racine_statiques.$lang, 0755, true);
			if(!is_dir(PLX_ROOT.$racine_commentaires.$lang))
				mkdir(PLX_ROOT.$racine_commentaires.$lang, 0755, true);
			if(!is_dir(PLX_ROOT.PLX_CONFIG_PATH.$lang))
				mkdir(PLX_ROOT.PLX_CONFIG_PATH.$lang, 0755, true);
			plxUtils::write('',PLX_ROOT.PLX_CONFIG_PATH.$lang.'/index.html');
			plxUtils::write("<Files *>\n\tOrder allow,deny\n\tDeny from all\n</Files>",PLX_ROOT.PLX_CONFIG_PATH.$lang.'/.htaccess');
		}

	}

	public function validLang($lang) {
		return (in_array($lang, $this->aLangs) ? $lang : "");
	}

	public function getCurrentLang() {

		# traitment de la langue à utiliser si un drapeau est clické
		if($this->lang = $this->validLang(plxUtils::getValue($_GET["lang"]))) {
			if(defined('PLX_ADMIN'))
				$_SESSION["plxMyMultiLingue"] = $this->lang;
			else
				setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours

			# on redirige pour nettoyer l'url
			header('Location: '.plxUtils::strCheck($_SERVER['PHP_SELF']));
			exit;
		}

		# partie administration
		if(defined('PLX_ADMIN')) {
			if(!$this->lang = $this->validLang(plxUtils::getValue($_SESSION['plxMyMultiLingue'])))
				$this->lang = $this->default_lang;
		} else {
			if(preg_match('/sitemap\.php\??([a-zA-Z]+)?/', $_SERVER['REQUEST_URI'], $capture)) {
				$this->lang = $this->validLang(plxUtils::getValue($capture[1]));
			} else {
				if(!$this->lang = $this->validLang(plxUtils::getValue($_COOKIE["plxMyMultiLingue"]))) {
					$this->lang = $this->default_lang;
					setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours
				}
				echo '<?php $this->aConf["default_lang"]="'.$this->lang.'"; ?>';
			}
		}

	}

	public function ConstructLoadPlugins() {

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

	}

	public function plxAdminEditConfiguration() {

		# pour ne pas écraser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$global["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $global["racine_articles"]);
			$global["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $global["racine_statiques"]);
			$global["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $global["racine_commentaires"]);
		?>';
	}

	public function AdminSettingsAdvancedTop() {

		# pour ne pas écraser les chemins racine_articles, racine_statiques et racine_commentaires
		echo '<?php
			$plxAdmin->aConf["racine_articles"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_articles"]);
			$plxAdmin->aConf["racine_statiques"] = str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_statiques"]);
			$plxAdmin->aConf["racine_commentaires"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["racine_commentaires"]);
		?>';
	}


	public function AdminTopEndHead() {

		echo '
		<style type="text/css">
		#langs { width:100%; float:right; top:0; text-align:right; }
		#langs a { margin: 0 10px 0 0 }
		#langs a.lang img	{ padding: 2px 2px 2px 2px; border: 1px solid #cecece; }
		#langs a.active img	{ padding: 2px 2px 2px 2px; border: 1px solid red; }
		</style>
		';
	}

	public function ThemeEndHead() {

		echo '
		<style type="text/css">
		#langs ul li { display:inline; list-style-type: none;}
		#langs a { margin: 0 10px 0 0; }
		#langs a.lang img	{ padding: 2px 2px 2px 2px; border: 1px solid #cecece; }
		#langs a.active img	{ padding: 2px 2px 2px 2px; border: 1px solid red; }
		</style>
		';
	}

	public function AdminTopBottom() {

		if($this->aLangs) {
			echo '<div id="langs">';
			foreach($this->aLangs as $lang) {
				$sel = $this->lang==$lang ? " active" : "";
				echo '<a class="lang'.$sel.'" href="?lang='.$lang.'"><img class="lang'.$sel.'" src="'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.jpg" alt="'.$lang.'" style="width:25px" /></a>';
			}
			echo '</div>';
		}

	}

	public function AdminArticleTop() {

		echo '<?php ob_start(); ?>';
	}

	public function AdminArticleContent() {

		echo '<?php echo preg_replace("/(article[a-z0-9-]+\/)/", "'.$this->lang.'/$1", ob_get_clean()); ?>';

	}

	public function MyMultiLingue() {

		if($this->aLangs) {
			echo '<div id="langs">';
			echo '<ul>';
			foreach($this->aLangs as $idx=>$lang) {
				$sel = $this->lang==$lang ? ' active':'';
				echo '<li><?php echo "<a class=\"lang'.$sel.'\" href=\"".$plxShow->plxMotor->urlRewrite("?lang='.$lang.'")."\"><img class=\"lang'.$sel.'\" src=\"'.PLX_PLUGINS.'plxMyMultiLingue/img/'.$lang.'.jpg\" alt=\"'.$lang.'\" style=\"width:25px\" /></a></li>"; ?>';
			}
			echo '</ul>';
			echo '</div>';
		}
	}

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

	public function PreChauffageBegin() {

		echo '<?php
			# utilisation de preg_replace pour etre sur que la chaine commence bien par la langue
			$this->get = preg_replace("/^'.$this->lang.'\/(.*)/", "$1", $this->get);
		?>';
	}

	public function plxShowStaticListEnd() {
		echo '<?php
		foreach($menus as $idx => $menu) {
			if(strpos($menu[0], "static-home")!==false) {
				if($this->plxMotor->aConf["urlrewriting"])
					$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine."'.$this->lang.'/", $menu);
				else
					$menus[$idx] = str_replace($this->plxMotor->racine, $this->plxMotor->racine."index.php?'.$this->lang.'/", $menu);
			}
		}
		?>';
	}

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

}
?>