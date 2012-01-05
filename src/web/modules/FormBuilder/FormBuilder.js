$(document).ready(function(){
    /**
     * Noms des classes css utilisées dans l'éditeur.
     * 
     * Un ou plusieurs nom de classe séparés par un espace.
     */
    var css = {
		selected	: 'ui-state-highlight', // Elément sélectionné
		//placeholder	: 'ui-state-active ui-formbuilder-placeholder' // Pour voir où on va dropper
		placeholder	: 'fb-placeholder' // Pour voir où on va dropper
    };

    /**
     * Liste des attributs qui sont protégés.
     * 
     * Ces attributs sont utilisés par l'éditeur et ne peuvent pas être directement utilisés
     * dans les propriétés. Ils sont stockés dans le tag avec un préfixe "_". 
     */
    var protectedAttributes = ['class', 'style'];
    		
    // Un item est sélectionné quand on clique dessus
    $('.fbitem').live('click', function(event) {
    	selectItem($(this)); 
    	return false;
    });
    $('.fbtext').live('click', function(event) {
    	//this.focus();
    	selectItem($(this));
    	$(this).trigger('blur').focus();
    	event.stopPropagation();
//    	return false;
    });
    
    $('.fbitem').live('mouseover', function(event) {
    	//$('.ui-state-active').removeClass('ui-state-active');
    	$(this).addClass('fb-hover');
    	return false;
    });
    
    $('.fbitem').live('mouseout', function(event) {
    	$(this).removeClass('fb-hover');
    	return false;
    });
    
    /**
     * Stocke l'item actuellement sélectionné
     */
    var currentItem = null;
    
    loadTools();

    /**
     * Charge les contrôles disponibles
     */
    function loadTools() {
        var temp = {};
        var first = true;
        for (var groupName in tools)
        {
        	var group = tools[groupName];
        	
    		// Entête du groupe
        	var header = $('<a class="tools-group-header" href="#">' + (group.label || groupName) + '</a>');
        	if (group.title) header.attr('title', group.title);
        	header.click(function(){
        		$(this).toggleClass('opened').next().slideToggle('fast');
        		return false;
        	});
        	header.appendTo('#fb-tools-list');

        	// Corps du groupe
        	var body = $('<div class="tools-group" />');
        	for (var toolName in group.tools)
        	{
        		var tool = group.tools[toolName];
            	var link = $('<a href="#" class="widget-' + toolName + '">' + (tool.label || toolName) + '</a>');
            	if (tool.title) link.attr('title', tool.title);
            	
            	link.data('tool', tool).click(function(event){
                	createItem($(this).data('tool'));
                });
            	
            	link.appendTo(body);
            	
            	temp[toolName] = tool;
        	}
        	body.appendTo('#fb-tools-list');
        	
        	if (first) 
        	{
        		header.addClass('opened');
        		body.show();
        		first=false;
        	}
        }
        tools = temp;
    }
    
    /**
     * Crée un nouvel item. 
     * 
     * @param object tool l'objet outil décrivant le type d'item à créer.
     */
    function createItem(tool) {
    	// Récupère la valeur par défaut de chacune des propriétés du contrôle
    	var data = {widget : tool.widget};
    	for (var groupName in tool.attributes) {
    		var group = tool.attributes[groupName];
    		for (var attrName in group) {
    			var attribute = group[attrName];
    			if (attribute && 'undefined' !== typeof attribute['default']) {
    				data[attrName] = attribute['default'];
    			}
    		}
    	}

    	// Crée le placeholder qui sera remplacé par le code retourné par la requête ajax
    	var placeholder = $('<span class="fbitem fbcontainer" fbtype="' + tool.widget + '"></span>');

    	// Lance la requête ajax
    	jQuery.ajax({
            type: 'GET',
            url: 'Render',
            data: data,
            success: function(item) {
    			item = $(item);
    			placeholder.replaceWith(item);	// Remplace le placeholder par le widget
    	    	selectItem(item); 				// Sélectionne l'item inséré
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                var msg = textStatus ? textStatus : errorThrown;
                placeholder.html('Une erreur est survenue lors de la création du contrôle : ' + msg);
            },
            dataType: 'html',
            timeout: 10 * 1000 // timeout des requêtes en millisecondes
        });
    	
    	// Détermine l'élément actuellement sélectionné dans l'éditeur
    	var selected = currentItem || $('#fb-editor');
    	
    	// Si c'est un containeur, on ajoute le nouvel item dans le dernier fils
    	// par exemple si on a <div fbtype="form"><form></form></div>, il faut ajouter l'item
    	// comme dernier fils du tag <form> et non pas dans la <div>
    	if (selected.is('.fbcontainer')) {
    		var last = $(':last-child', selected);
    		
    		var last = selected.children(':not(#deleteItem)').last();
    		if (last.length)
    			last.append(placeholder);
    		else
    			selected.prepend(placeholder);
    	
    	// Sinon, on ajoute le nouvel item après l'élément sélectionné
    	} else {
    		selected.after(placeholder);
    	}
    }
    
    
    /**
     * Sélectionne un item, le met en surbrillance, affiche ses propriétés et son path.
     */
    function selectItem(item) {
    	// Désélectionne l'élément actuel
    	if (currentItem)
    		currentItem.removeClass(css.selected);
    	
    	// Sélectionne l'élément
    	item.addClass(css.selected);
    	currentItem = item;
    	item.focus();
    	
    	// Insère le bouton "supprimer"
		if (item.is('.ui-first'))
			$('#deleteItem').hide();
		else
			item.append($('#deleteItem').show());
		
		// Affiche les propriétés de l'item
    	loadProperties();
    	
		// Affiche le path de l'item dans la ligne de statut
    	$('#fb-status').html(getPath(item));
    	
    	// Affiche le code de l'éditeur
    	var code = getCode(currentItem, true);
    	$('#fb-code').text(code); // todo: remove
    }

    
    /**
     * Supprime l'item actuellement sélectionné (et son contenu).
     */
    function deleteItem() {
		// Détermine quel sera l'élément sélectionné après la suppression 
		var next = currentItem.next();
		if (next.length===0) next = currentItem.prev();
		if (next.length===0) currentItem.parent();
		
		// Met la croix "supprimer" ailleurs
		$('#deleteItem').hide().appendTo('body');
		
		// Supprime l'élément
		currentItem.remove();
		
		// Sélectionne l'élément suivant
		selectItem(next);
    }
    
    
    /**
     * Charge les propriétés de l'élément actuellement sélectionné pour permettre 
     * à l'utilisateur de les modifier.
     */
    function loadProperties() {
		// Enlève le focus de la propriété en cours de modification pour qu'elle soit sauvegardée
    	$(':focus').trigger('blur');
    	
    	// Crée la liste des propriétés à afficher
    	var tool = tools[currentItem.attr('fbtype')];
    	if (tool === undefined)
    	{
    		//alert("Impossible de modifier cet item : l'outil '" + currentItem.attr('fbtype') + "' indiqué dans l'attribut 'fbtype' n'existe pas");
    		return;
    	}
    	
    	var h='';
    	for (var groupName in tool.attributes) {
    		var group = tool.attributes[groupName];
    		
    		// Entête du groupe
    		h += '<h3><a href="#">' + groupName + '</a></h3>';
    		
    		// Liste des propriétés de ce groupe
    		h += '<div>';
    		for (var attrName in group) {
    			var attribute = group[attrName];
    			
    			// Détermine le libellé et le type de la propriété
    			var attrLabel = attrName;
    			var attrType  = 'text';
    			if (attribute !== null) {
    				attrLabel =  attribute.label || attrLabel;
    				attrType  = attribute.type || attrType;
    			}

				// Gère les attributs protégés
    			for (var i in protectedAttributes) {
    				if (attrName === protectedAttributes[i]) {
    					attrName = '_' + protectedAttributes[i];
    					break;
    				}
    			}
    			
    			// Récupère la valeur actuelle de l'attribut
    			var value = currentItem.get(0).getAttribute(attrName) || '';
    			
				// Encode les guillemets
    			value = value.replace(/"/g, '&quot;');
    				
    			// Crée le contrôle pour cette propriété
				h += '<div class="holder textbox">';
    			h += '<label for="property-' + attrName + '">' + attrLabel + '</label>';
    			h += '<input type="text" name="' + attrName + '" value="' + value + '" id="property-' + attrName + '" class="fbproperty" />';
    			h += '</div>';
    		}
    		h += '</div>';
    	}
    	
    	// Affiche les propriétés
    	$('#fb-properties-list').html(h); //.accordion('destroy').accordion({icons: false, autoHeight: false});
    	
    	// Quand une propriété est modifiée, on met à jour les attributs du contrôle
    	$('.fbproperty', '#fb-properties-list').change(function() {
    		if (this.value === '')
    			currentItem.get(0).removeAttribute(this.name);
    		else
    			currentItem.get(0).setAttribute(this.name, this.value);
    		
        	var data = {
    			widget : tool.widget,
    			content : getCode(currentItem.children())
			};

        	var attributes = currentItem.get(0).attributes;
        	for(var i=0; i<attributes.length; i++) {
        		var attr=attributes[i];
        		var name=attr.name;

        		if (name.substr(0,2) === 'fb') continue;
    			
				// Gère les attributs protégés
    			var ignore = false;
    			for (var j in protectedAttributes) {
    				if (name === protectedAttributes[j]) {
    					ignore = true;
    					break;
    				}
    				if (name === '_' + protectedAttributes[j]) {
    					name = protectedAttributes[j];
    					break;
    				}
    			}
    			if (ignore) continue;
    			
        		data[name] = attr.value;
        	}

        	// Lance la requête ajax
        	jQuery.ajax({
                type: 'GET',
                url: 'Render',
                data: data,
                success: function(data){
        			data=$(data);
        			currentItem.replaceWith(data);
        			selectItem(data);
//        	    	$('#fb-code').text(getCode($('#fb-editor')));
//        			$('#fb-status').html(getPath(currentItem));
        		},
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    var msg = textStatus ? textStatus : errorThrown;
                    currentItem.html('Une erreur est survenue lors de la mise à jour du contrôle : ' + msg);
                },
                dataType: 'html',
                timeout: 10 * 1000 // timeout des requêtes en millisecondes
            });
    	});
    }
    

    /**
     * Retourne le dom-path de l'élément passé en paramètre tel qu'il est affiché 
     * dans la ligne de statut lorsqu'on sélectionne un item.
     * 
     * @param node e
     * @return string
     */
    function getPath(e) {
    	var path = '';

    	$(e).parents('.fbitem,.fbcontainer not(#fb-editor)').each(function(i, e){
    		path = getPathPart($(e)) + ' » ' + path;
    	});
		return path + getPathPart($(e));
    }
    
    
    /**
     * Fonction interne utilisée par {@link getPath()}.
     */
    function getPathPart(e) {
		var h = e.attr('name') || e.attr('label') || '';
		if (h) h=' "<i>' + h + '</i>"';
		return '<b>' + e.attr('fbtype') + '</b>' + h;
    }
    
    
    /**
     * Retourne le code html correspondant aux items actuellement présents dans le noeud passé
     * en paramètre.
     * 
     * Le code html est généré en créant tous les items dans une div temporaire puis en demandant
     * à la div temporaire de nous fournir son code html.
     * 
     * Le code obtenu est ensuite indenté pour améliorer sa lisibilité.
     */
    function getCode(e, prettify)
    {
    	// Crée une div temporaire
    	var result = $('<div />');
    	
    	// Recopie/crée tous les items dans la div temporaire
    	copyItems($(e), result);

    	// Récupère le code html obtenu
    	var code = result.html();
    	
    	// Supprime les attributs xmlns
    	code = code.replace(/ xmlns="http:\/\/www\.w3\.org\/1999\/xhtml"/g, '');

    	// Mise en forme et indentation du code
    	if (prettify) {
	    	code = style_html(code, {
		      'indent_size': 4,
		      'indent_char': ' ',
		      'max_char': 100,
		      'brace_style': 'expand'
		    });    	
    	}
    	
    	// Supprime la div temporaire
    	result.remove();
    	
    	// Retourne le résultat
    	return code;
    }

    
    /**
     * Méthode récursive utilisée par getCode().
     * 
     * Prend tous les éléments de type ".fbitem" présents dans from et crée des noeuds 
     * du type indiqué dans "fbtype" dans "to".
     * 
     * Par exemple, si on a <div class="fbitem" fbtype="textbox"></div> dans "from", on 
     * va créer un noeud <textbox /> dans to.
     * 
     * Le noeud est créé en reprenant tout les attributs qui figurent dans l'item d'origine.
     * 
     * Les classes css utilisées par l'éditeur sont supprimées avant la copie. 
     */
    function copyItems(from, to) {
    	var result = '';
    	
    	from.each(function(){
    		if ($(this).is('.fbtext')) {
				node = $(document.createTextNode($(this).text()));
				to.append(node);
    		}
    		else if ($(this).is('.fbitem,.fbtext')) {
    			
    			var type = $(this).attr('fbtype');
    			var node;
    			switch (type)
    			{
	    			case 'comment':
	    				node = $('<!--' + $(this).attr('content') +'-->');
	    				break;
	    				
	    			case 'text':
	    				node = $(document.createTextNode($(this).attr('content')));
	    				break;
	    				
    				default:
    		    		// Crée un noeud du type indiqué dans l'attribut fbitem
    		    		node = $('<' + type + ' />');
    					//node = $(document.createElement('aa'+type));
    					//node = $(document.createElementNS("http://www.w3.org/1999/xhtml","html:" + type));
    			}
	
	    		// Recopie tous les attributs
	        	var attributes = this.attributes;
	        	for(var i=0; i<attributes.length; i++) {
	        		var attr=attributes[i];
	        		var name=attr.name;

	        		if (name.substr(0,2) === 'fb') continue;
            		
    				// Gère les attributs protégés
        			var ignore = false;
        			for (var j in protectedAttributes) {
        				if (name === protectedAttributes[j]) {
        					ignore = true;
        					break;
        				}
        				if (name === '_' + protectedAttributes[j]) {
        					name = protectedAttributes[j];
        					break;
        				}
        			}
        			if (ignore) continue;

	        		node.attr(name, attr.value)
	        	}
	        	
	        	/* supprimer les xmlns */ 
//	        	node.attr('fbtype', null).removeClass('fbitem fbcontainer ' + css.selected);
//	        	if (node.attr('class') === '') node.removeAttr('class');
	        	
	        	to.append(node);
	        	copyItems($(this).children(), node);
    		// idée : copier les noeuds de type texte $(':text') qui ne contiennent que des blancs
        	// permettrait peut-être de garder la mise en forme du code source html.
    		} else {
	        	copyItems($(this).children(), to);
    		}
    	});
    }
    
    // Les items peuvent être triés
/*    
    $('.fbcontainer').sortable({
    	connectWith: '.fbcontainer',	// Un item peut aller dans un autre container
    	placeholder: css.placeholder, 			  	// Preview du drop
    	opacity: 0.4,							  	// Permet de mieux voir où on va dropper
    	items: '.fbitem', 						  	// Un legend dans un fieldset, par ex. ne peut pas être déplacé
    	distance: 10, 							  	// Evite de commencer un drag'n drop quand on veut juste sélectionner un item
    	grid: [1, 10], 								// Améliore l'insertion au début ou à la fin d'un fieldset.
//    	start: function(event, ui) { 				// Sélectionne l'item quand on commence un drag'n drop
//    		selectItem(ui.item);
//    	},
//		stop: function(event, ui) {
//    		console.log(event, ui, ui.item.attr('style'));
//    		ui.item.attr('style', '');
//    	}
    });
*/
    
    $('.fbcontainer').sortable({
    	items: '.fbitem', 						  	// Un legend dans un fieldset, par ex. ne peut pas être déplacé
    	connectWith: '.fbcontainer',				// Un item peut aller dans un autre container
//    	placeholder: css.placeholder, 			  	// Preview du drop
//    	forcePlaceholderSize: true,					// Fait en sorte que le placeholder ait la même taille que l'item
//    	distance: 10, 							  	// Evite de commencer un drag'n drop quand on veut juste sélectionner un item
//    	containment: '#fb-editor',					// Les items en cours de drag'n drop ne peuvent pas sortir de l'éditeur
    	cursor: 'hand',
//    	cursorAt: {top: 0, left: 0},
////    	forceHelperSize: true,
    	grid: [1, 5], 								// Améliore l'insertion au début ou à la fin d'un fieldset.
//    	helper: 'clone',
    	//tolerance: 'pointer',
    	helper: function(event, item){
    		//return item.clone();
    		console.log(item.width())
    		return $('<div class="fbitem ui-state-active" style="width: '+item.width()+'px; height: '+item.height()+'px" />')
    	},
//    	revert: true,								// Smooth animation quand on lache l'item à sa nouvelle position
    	delay: 100,
    	cancel: '[contenteditable="true"]',
    	start: function(event, ui) { 				// Sélectionne l'item quand on commence un drag'n drop
			selectItem(ui.item);
		},
    }).disableSelection();

    
    // Suppression d'un item
    $('#deleteItem').click(deleteItem);

    $('#fbsave').click(function(){
//    	console.log(getCode($('#fb-editor')));
//    	alert(path);
    	var input=$('<textarea name="source" type="text" value="aaa"></textarea>');
    	input.val(getCode($('#fb-editor').children()));
    	//input.val(getCode($('#fb-editor')));
    	var form=$('<form method="GET" action="Save" />')
    	.append(input)
    	.append('<input type="hidden" name="template" value="' + template + '" />')
    	.submit();
//    	console.log(form);
    });

    // Affiche ou masque les noms des tags
    $('#fb-show-tags').click(function(){
    	$('#fb-editor').toggleClass('fb-show-tags');
    });
    $('#fb-show-blocks').click(function(){
    	$('#fb-editor').toggleClass('fb-show-blocks');
    });
    
/*    
    // Navigation au clavier
    //$('.ussi-formbuilder-editor').keydown(function(event){
	jQuery(document.documentElement).keydown(function(event){    	
        var special  = 
            event.shiftKey  || 
            event.ctrlKey   || 
            event.altKey    || 
            (event.metaKey ? event.metaKey : false); // meta génère undefined sous ie
        
        var nav = {
            33: 'first',     // Page Up
            36: 'first',     // Home
            34: 'last',      // Page Down
            35: 'last',      // End
            40: 'next',      // Key Down
            38: 'previous',  // Key Up
            27: 'none',      // Esc
            13: 'current',   // Entrée
            9:  'current'    // Tab
        };

        var selected=getSelected();
        var next = null;
        if (event.keyCode === 38) // Key Up
        {
        }
        else if (event.keyCode === 40) // Key Down
        {
    		var next = selected.next();
    		//if (next.length===0) next = selected.prev();
    		if (next.length===0) selected.parent();
    		next.trigger('click');
        }
        return false;
//        event.preventDefault(); 
    });
*/    
    
    
});