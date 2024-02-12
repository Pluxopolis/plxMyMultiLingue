Le principe de ce plugin est d'avoir coté administration la possibilité de rédiger des articles en plusieurs langues et de pouvoir coté visiteur les lire en fonction de la langue de son choix.


Le choix des langues à gérer se fait à partir de l'écran de configuration du plugin et d'une liste à cocher.
Pour chaque langue sélectionnée un drapeau est affiché en haut à droite de l'administration.
En cliquant sur un drapeau on bascule d'une langue à une autre permettant ainsi d'avoir une gestion complètement séparée :
- des articles et leurs tags ainsi que leurs commentaires
- des catégories
- des pages statiques


Coté visiteur, éditez par exemple le fichier sidebar.php de votre thème et ajoutez la ligne suivante:

<code>&lt;?php eval($plxShow->callHook('MyMultiLingue')) ?></code>


Les drapeaux de langues gérées seront affichés. En cliquant sur un drapeau le thème basculera vers la langue choisie et les articles de cette langue seront affichés.

Sont gérés également par langue:
- les urls
- les flux rss
- un sitemap par langue
 
<b>Le contenu du site rédigé avant l'activation du plugin n'est pas pris en compte.</b>
