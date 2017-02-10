<p style="margin-top:20px">
Per afichar las bandièras per seleccionar una lenga, modificatz lo fichièr sidebar.php de vòstre tèma e ajustatz la linha seguenta :
<br /><br />
<pre style="font-size:1.1em;margin-left:20px">
&lt;?php eval($plxShow->callHook('MyMultiLingue')) ?>
</pre>
</p>

<p style="margin-top:20px">
Per afichar los articles dependants redigits dins una autra lenga, ajustatz dins lo fichièr article.php de vòstre tèma la linha seguenta :
<br /><br />
<pre style="font-size:1.1em;margin-left:20px">
&lt;?php eval($plxShow->callHook('MyMultiLingue', 'artlinks')) ?>
</pre>
</p>

<p style="margin-top:20px">
Per afichar las paginas estaticas dependants redigits dins una autra lenga, ajustatz dins lo fichièr static.php de vòstre tèma la linha seguenta :
<br /><br />
<pre style="font-size:1.1em;margin-left:20px">
&lt;?php eval($plxShow->callHook('MyMultiLingue', staticlinks')) ?>
</pre>
</p>

<p style="margin-top:20px">
<strong>Important :<br />
<ul>
<li>
	plxMyMultiLingue deu èsser la primièra extension de la lista de las extensions per assegurar lo bon foncionament<br />
	Notamment avec le plugin plxMyBetterUrl.
</li>
<li>
	plxMyMultiLingue necessita l'activacion de la reescritura de las urls dins PluXml<br />
	Paramètres > Configuracion avançada > Activar la reescritura de las urls : Òc
</li>
</ul>

</strong>
</p>
