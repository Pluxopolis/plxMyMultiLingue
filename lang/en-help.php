<p style="margin-top:20px">
To display flags, edit sidebar.php in your theme folder and add this following line:
<br /><br />
<pre style="font-size:1.2em;margin-left:20px">
&lt;?php eval($plxShow->callHook('MyMultiLingue')) ?>
</pre>
</p>
<p style="margin-top:20px">
<strong>Caution :<br />
<ul>
<li>- Set plxMyMultiLingue in first position in the active plugins list.</li>
</li>- Activate url rewriting (Parameters > Advanced configuration, Enable url rewriting : Yes</li>
</ul>
</strong>
</p>