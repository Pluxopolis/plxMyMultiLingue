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
		$this->addHook('plxMotorConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxMotorPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('plxFeedConstructLoadPlugins', 'ConstructLoadPlugins');
		$this->addHook('plxFeedPreChauffageBegin', 'PreChauffageBegin');
		$this->addHook('IndexEnd', 'IndexEnd');
		$this->addHook('FeedEnd', 'FeedEnd');
		$this->addHook('plxShowStaticListEnd', 'plxShowStaticListEnd');
		$this->addHook('SitemapBegin', 'SitemapBegin');		
		
		# déclaration des hooks partie administration
		$this->addHook('AdminTopBottom', 'AdminTopBottom');
		$this->addHook('plxAdminEditConfiguration', 'plxAdminEditConfiguration');
		$this->addHook('AdminSettingsAdvancedTop', 'AdminSettingsAdvancedTop');
		$this->addHook('AdminArticleTop', 'AdminArticleTop');
		$this->addHook('AdminArticleContent', 'AdminArticleContent');
	
		# déclaration hook utilisateur à mettre dans le thème
		$this->addHook('MyMultiLingue', 'MyMultiLingue');

		# récupération des langues enregsitrées dans le fichier de configuration du plugin
		if($this->getParam('flags')!='')
			$this->aLangs = explode(',', $this->getParam('flags'));

		$this->lang = $this->validLang($this->lang);
		
		define('PLX_MYMULTILINGUE', $this->getParam('flags'));
		
	}
	
	public function onDeactivate() {
		unset($_SESSION['lang']);
		unset($_SESSION['medias']);
		unset($_SESSION['folder']);
		unset($_SESSION['currentfolder']);	
		unset($_COOKIE['plxMyMultiLingue']);
		setcookie('plxMyMultiLingue', '', time() - 3600); 		
	}

	# Méthode qui créée les répertoires des langues (écran de config du plugin)
	public function mkDirs() {

		$plxAdmin = plxAdmin::getInstance();

		# on nettoie les chemins
		$racine_articles = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_articles']);
		$racine_statiques = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_statiques']);
		$racine_commentaires =  str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['racine_commentaires']);
		$racine_images = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['images']);
		$racine_documents = str_replace('/'.$this->lang.'/', '/', $plxAdmin->aConf['documents']);

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
				if(!is_dir(PLX_ROOT.$racine_documents.$lang))
					mkdir(PLX_ROOT.$racine_documents.$lang, 0755, true);
				if(!is_dir(PLX_ROOT.PLX_CONFIG_PATH.$lang))
					mkdir(PLX_ROOT.PLX_CONFIG_PATH.$lang, 0755, true);
				plxUtils::write('',PLX_ROOT.PLX_CONFIG_PATH.$lang.'/index.html');
				plxUtils::write("<Files *>\n\tOrder allow,deny\n\tDeny from all\n</Files>",PLX_ROOT.PLX_CONFIG_PATH.$lang.'/.htaccess');
			}
		}

	}

	public function validLang($lang) {
		return (in_array($lang, $this->aLangs) ? $lang : $this->default_lang);
	}

	public function getCurrentLang() {
	
		# sélection de la langue à partir d'un drapeau
		if(isset($_GET["lang"]) AND !empty($_GET["lang"])) {
		
			$this->lang = $this->validLang(plxUtils::getValue($_GET["lang"])); 

			if(defined('PLX_ADMIN')) {
				unset($_SESSION['medias']);
				unset($_SESSION['folder']);
				unset($_SESSION['currentfolder']);
			}
			setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours
			$_SESSION['lang'] = $this->lang;
			
			# redirection avec un minimum de sécurité sur l'url
			if(defined('PLX_ADMIN')) {
				if(preg_match('@^'.plxUtils::getRacine().'(.*)@', $_SERVER['HTTP_REFERER']))
					header('Location: '.plxUtils::strCheck($_SERVER['HTTP_REFERER']));
				else
					header('Location: '.plxUtils::getRacine());
				exit;
			} else {
				# on redirige pour nettoyer l'url
				header('Location: '.plxUtils::strCheck($_SERVER['PHP_SELF']));
				exit;
			}			
		}
		
		# récupération de la langue si on accède au site à partir du sitemap
		if(preg_match('/sitemap\.php\??([a-zA-Z]+)?/', $_SERVER['REQUEST_URI'], $capture)) {
			$this->lang = $this->validLang(plxUtils::getValue($capture[1]));	
			return;
		}
		
		setcookie("plxMyMultiLingue", $this->lang, time()+3600*24*30);  // expire dans 30 jours
		
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
		
		# s'il faut un dossier images et documents différents pour chaque langue
		if($this->getParam('lang_images_folder')) {
			echo '<?php $this->aConf["images"] = $this->aConf["images"]."'.$this->lang.'/"; ?>';
		}
		if($this->getParam('lang_documents_folder')) {
			echo '<?php $this->aConf["documents"] = $this->aConf["documents"]."'.$this->lang.'/"; ?>';
		}

	}

	public function plxAdminEditConfiguration() {

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
		if($this->getParam('lang_documents_folder')) {
			echo '<?php $global["documents"] = str_replace("/'.$this->lang.'/", "/", $global["documents"]); ?>';
		}		
	}

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
		if($this->getParam('lang_documents_folder')) {
			echo '<?php $plxAdmin->aConf["documents"] =  str_replace("/'.$this->lang.'/", "/", $plxAdmin->aConf["documents"]); ?>';
		}
		
	}

	public function AdminTopBottom() {
		
		# affichage des drapeaux
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

	public function PreChauffageBegin() {

		echo '<?php
			# utilisation de preg_replace pour etre sur que la chaine commence bien par la langue
			$this->get = preg_replace("/^'.$this->lang.'\/(.*)/", "$1", $this->get);
		?>';
	}

	public function plxShowStaticListEnd() {
		echo '<?php
		foreach($menus as $idx => $menu) {
			if(strpos($menu[0], "static-home")===false) {
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