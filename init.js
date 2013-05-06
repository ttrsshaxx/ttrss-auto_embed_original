hotkey_actions['auto_embed_original'] = function() {
  if (getActiveArticleId()) {
    autoEmbedOriginalArticle(getActiveArticleId());
    return;
  }
};


PluginHost.register(PluginHost.HOOK_ARTICLE_EXPANDED,
	function (articleID) { 
		console.log('HOOK_ARTICLE_EXPANDED: ' + articleID); 
		var query = "op=pluginhandler&plugin=auto_embed_original&method=feedAutoloadContent&id=" + param_escape(getActiveArticleId());
		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				var ti = JSON.parse(transport.responseText);
				if (ti) {	
					autoEmbedOriginalArticle(articleID,true);
					console.log("feedAutoloadContent: embed finished");
					}
				}
			});
		});
	
PluginHost.register(PluginHost.HOOK_ARTICLE_COLLAPSED,
	function (articleID) { 
		console.log('HOOK_ARTICLE_COLLAPSED: ' + articleID); 
		return true; });


function configAutoEmbedOriginal(id) {
	try {
		if (!id) {
			var ids = getSelectedArticleIds2();

			if (ids.length == 0) {
				alert(__("No articles are selected."));
				return;
			}

			id = ids.toString();
		}

		if (dijit.byId("configAutoEmbedOriginalDlg"))
			dijit.byId("configAutoEmbedOriginalDlg").destroyRecursive();

		var query = "backend.php?op=pluginhandler&plugin=auto_embed_original&method=get_config&param=" + param_escape(id);

		dialog = new dijit.Dialog({
			id: "configAutoEmbedOriginalDlg",
			title: __("Edit Feed Article Display type"),
			style: "width: 600px",
			execute: function() {
				if (this.validate()) {
					notify_progress("Saving data...", true);

					new Ajax.Request("backend.php", {
						parameters: dojo.objectToQuery(dialog.attr('value')),
						onComplete: function(transport) {

							var reply = JSON.parse(transport.responseText);
							var error = reply['error'];

							if (error) {
								alert(__('Error saving settings:') + ' ' + error);
							} else {
							dialog.hide();
							notify('');
							}
					}});
				}
			},
			href: query});

		dialog.show();



	} catch (e) {
		exception_error("configAutoEmbedOriginal", e);
	}
}


function autoEmbedOriginalArticle(id,skipToggle) {
	try {
		var hasSandbox = "sandbox" in document.createElement("iframe");

		if (!hasSandbox) {
			alert(__("Sorry, your browser does not support sandboxed iframes."));
			return;
		}

		var query = "op=pluginhandler&plugin=auto_embed_original&method=getUrl&id=" +
			param_escape(id);

		var c = false;

		if (isCdmMode()) {
			c = $$("div#RROW-" + id + " div[class=cdmContentInner]")[0];
		} else if (id == getActiveArticleId()) {
			c = $$("div[class=postContent]")[0];
		}

		if (c) {
			var iframe = c.parentNode.getElementsByClassName("embeddedContent")[0];

			if (iframe) {
			        if (skipToggle) {
  				  Element.hide(c);
  				  c.parentNode.insertBefore(iframe,c);
  				  } else {
  				  Element.show(c);
  				  c.parentNode.removeChild(iframe);
  				  }

				if (isCdmMode()) {
					cdmScrollToArticleId(id, true);
				}

				return;
			}
		}

		new Ajax.Request("backend.php",	{
			parameters: query,
			onComplete: function(transport) {
				var ti = JSON.parse(transport.responseText);

				if (ti) {
					var iframe = new Element("iframe", {
						class: "embeddedContent",
						src: ti.url,
						width: (c.parentNode.offsetWidth-5)+'px',

//						height: (c.parentNode.parentNode.offsetHeight-c.parentNode.firstChild.offsetHeight-50)+'px',
//						height: (c.parentNode.parentNode.offsetHeight-c.parentNode.firstChild.offsetHeight-15)+'px',
//						style: "overflow: auto; border: none; min-height: "+(document.body.clientHeight/2)+"px;",
//						style: "overflow: scroll; border: none; min-height: "+(document.body.clientHeight-180)+"px; max-height: "+(document.body.clientHeight-80)+"px;",
						style: "overflow: scroll; border: none; min-height: "+(document.body.clientHeight-214)+"px; max-height: "+(document.body.clientHeight-85)+"px;",
						sandbox: 'allow-same-origin allow-scripts allow-popups allow-forms',
					});

					if (c) {
						Element.hide(c);
						c.parentNode.insertBefore(iframe,c);

						if (isCdmMode()) {
							cdmScrollToArticleId(id, true);
						}
					}
				}

			} });


	} catch (e) {
		exception_error("autoEmbedOriginalArticle", e);
	}
}

/*

#frame {  Example size! 
    height: 400px; original height 
    width: 100%;  original width 
}
#frame {
    height: 500px;  new height (400 * (1/0.8) )
    width: 125%;  new width (100 * (1/0.8) )

    transform: scale(0.8); 
    transform-origin: 0 0;
}

.frame
{
    width: 1280px;
    height: 786px;
    border: 0;
    -ms-zoom: 0.25;
    -transform: scale(0.25);
    -moz-transform: scale(0.25);
    -moz-transform-origin: 0 0;
    -o-transform: scale(0.25);
    -o-transform-origin: 0 0;
    -webkit-transform: scale(0.25);
    -webkit-transform-origin: 0 0;
}

http://stackoverflow.com/questions/166160/how-can-i-scale-the-content-of-an-iframe

http://futtta.be/squeezeFrame/

*/
