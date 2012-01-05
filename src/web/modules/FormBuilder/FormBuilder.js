$(document).ready(function(){
    /**
     * Noms des classes css utilis�es dans l'�diteur.
     * 
     * Un ou plusieurs nom de classe s�par�s par un espace.
     */
    var css = {
		selected	: 'ui-state-highlight', // El�ment s�lectionn�
		//placeholder	: 'ui-state-active ui-formbuilder-placeholder' // Pour voir o� on va dropper
		placeholder	: 'fb-placeholder' // Pour voir o� on va dropper
    };

    /**
     * Liste des attributs qui sont prot�g�s.
     * 
     * Ces attributs sont utilis�s par l'�diteur et ne peuvent pas �tre directement utilis�s
     * dans les propri�t�s. Ils sont stock�s dans le tag avec un pr�fixe "_". 
     */
    var protectedAttributes = ['class', 'style'];
    		
    // Un item est s�lectionn� quand on clique dessus
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
     * Stocke l'item actuellement s�lectionn�
     */
    var currentItem = null;
    
    loadTools();

    /**
     * Charge les contr�les disponibles
     */
    function loadTools() {
        var temp = {};
        var first = true;
        for (var groupName in tools)
        {
        	var group = tools[groupName];
        	
    		// Ent�te du groupe
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
     * Cr�e un nouvel item. 
     * 
     * @param object tool l'objet outil d�crivant le type d'item � cr�er.
     */
    function createItem(tool) {
    	// R�cup�re la valeur par d�faut de chacune des propri�t�s du contr�le
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

    	// Cr�e le placeholder qui sera remplac� par le code retourn� par la requ�te ajax
    	var placeholder = $('<span class="fbitem fbcontainer" fbtype="' + tool.widget + '"></span>');

    	// Lance la requ�te ajax
    	jQuery.ajax({
            type: 'GET',
            url: 'Render',
            data: data,
            success: function(item) {
    			item = $(item);
    			placeholder.replaceWith(item);	// Remplace le placeholder par le widget
    	    	selectItem(item); 				// S�lectionne l'item ins�r�
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                var msg = textStatus ? textStatus : errorThrown;
                placeholder.html('Une erreur est survenue lors de la cr�ation du contr�le : ' + msg);
            },
            dataType: 'html',
            timeout: 10 * 1000 // timeout des requ�tes en millisecondes
        });
    	
    	// D�termine l'�l�ment actuellement s�lectionn� dans l'�diteur
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
    	
    	// Sinon, on ajoute le nouvel item apr�s l'�l�ment s�lectionn�
    	} else {
    		selected.after(placeholder);
    	}
    }
    
    
    /**
     * S�lectionne un item, le met en surbrillance, affiche ses propri�t�s et son path.
     */
    function selectItem(item) {
    	// D�s�lectionne l'�l�ment actuel
    	if (currentItem)
    		currentItem.removeClass(css.selected);
    	
    	// S�lectionne l'�l�ment
    	item.addClass(css.selected);
    	currentItem = item;
    	item.focus();
    	
    	// Ins�re le bouton "supprimer"
		if (item.is('.ui-first'))
			$('#deleteItem').hide();
		else
			item.append($('#deleteItem').show());
		
		// Affiche les propri�t�s de l'item
    	loadProperties();
    	
		// Affiche le path de l'item dans la ligne de statut
    	$('#fb-status').html(getPath(item));
    	
    	// Affiche le code de l'�diteur
    	var code = getCode(currentItem, true);
    	$('#fb-code').text(code); // todo: remove
    }

    
    /**
     * Supprime l'item actuellement s�lectionn� (et son contenu).
     */
    function deleteItem() {
		// D�termine quel sera l'�l�ment s�lectionn� apr�s la suppression 
		var next = currentItem.next();
		if (next.length===0) next = currentItem.prev();
		if (next.length===0) currentItem.parent();
		
		// Met la croix "supprimer" ailleurs
		$('#deleteItem').hide().appendTo('body');
		
		// Supprime l'�l�ment
		currentItem.remove();
		
		// S�lectionne l'�l�ment suivant
		selectItem(next);
    }
    
    
    /**
     * Charge les propri�t�s de l'�l�ment actuellement s�lectionn� pour permettre 
     * � l'utilisateur de les modifier.
     */
    function loadProperties() {
		// Enl�ve le focus de la propri�t� en cours de modification pour qu'elle soit sauvegard�e
    	$(':focus').trigger('blur');
    	
    	// Cr�e la liste des propri�t�s � afficher
    	var tool = tools[currentItem.attr('fbtype')];
    	if (tool === undefined)
    	{
    		//alert("Impossible de modifier cet item : l'outil '" + currentItem.attr('fbtype') + "' indiqu� dans l'attribut 'fbtype' n'existe pas");
    		return;
    	}
    	
    	var h='';
    	for (var groupName in tool.attributes) {
    		var group = tool.attributes[groupName];
    		
    		// Ent�te du groupe
    		h += '<h3><a href="#">' + groupName + '</a></h3>';
    		
    		// Liste des propri�t�s de ce groupe
    		h += '<div>';
    		for (var attrName in group) {
    			var attribute = group[attrName];
    			
    			// D�termine le libell� et le type de la propri�t�
    			var attrLabel = attrName;
    			var attrType  = 'text';
    			if (attribute !== null) {
    				attrLabel =  attribute.label || attrLabel;
    				attrType  = attribute.type || attrType;
    			}

				// G�re les attributs prot�g�s
    			for (var i in protectedAttributes) {
    				if (attrName === protectedAttributes[i]) {
    					attrName = '_' + protectedAttributes[i];
    					break;
    				}
    			}
    			
    			// R�cup�re la valeur actuelle de l'attribut
    			var value = currentItem.get(0).getAttribute(attrName) || '';
    			
				// Encode les guillemets
    			value = value.replace(/"/g, '&quot;');
    				
    			// Cr�e le contr�le pour cette propri�t�
				h += '<div class="holder textbox">';
    			h += '<label for="property-' + attrName + '">' + attrLabel + '</label>';
    			h += '<input type="text" name="' + attrName + '" value="' + value + '" id="property-' + attrName + '" class="fbproperty" />';
    			h += '</div>';
    		}
    		h += '</div>';
    	}
    	
    	// Affiche les propri�t�s
    	$('#fb-properties-list').html(h); //.accordion('destroy').accordion({icons: false, autoHeight: false});
    	
    	// Quand une propri�t� est modifi�e, on met � jour les attributs du contr�le
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
    			
				// G�re les attributs prot�g�s
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

        	// Lance la requ�te ajax
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
                    currentItem.html('Une erreur est survenue lors de la mise � jour du contr�le : ' + msg);
                },
                dataType: 'html',
                timeout: 10 * 1000 // timeout des requ�tes en millisecondes
            });
    	});
    }
    

    /**
     * Retourne le dom-path de l'�l�ment pass� en param�tre tel qu'il est affich� 
     * dans la ligne de statut lorsqu'on s�lectionne un item.
     * 
     * @param node e
     * @return string
     */
    function getPath(e) {
    	var path = '';

    	$(e).parents('.fbitem,.fbcontainer not(#fb-editor)').each(function(i, e){
    		path = getPathPart($(e)) + ' � ' + path;
    	});
		return path + getPathPart($(e));
    }
    
    
    /**
     * Fonction interne utilis�e par {@link getPath()}.
     */
    function getPathPart(e) {
		var h = e.attr('name') || e.attr('label') || '';
		if (h) h=' "<i>' + h + '</i>"';
		return '<b>' + e.attr('fbtype') + '</b>' + h;
    }
    
    
    /**
     * Retourne le code html correspondant aux items actuellement pr�sents dans le noeud pass�
     * en param�tre.
     * 
     * Le code html est g�n�r� en cr�ant tous les items dans une div temporaire puis en demandant
     * � la div temporaire de nous fournir son code html.
     * 
     * Le code obtenu est ensuite indent� pour am�liorer sa lisibilit�.
     */
    function getCode(e, prettify)
    {
    	// Cr�e une div temporaire
    	var result = $('<div />');
    	
    	// Recopie/cr�e tous les items dans la div temporaire
    	copyItems($(e), result);

    	// R�cup�re le code html obtenu
    	var code = result.html();
    	
    	// Supprime les attributs xmlns
    	code = code.replace(/ xmlns="http:\/\/www\.w3\.org\/1999\/xhtml"/g, '');

    	// Mise en forme et indentation du code
    	if (prettify) {
	    	code = style_html(code, {
		      'indent_size': 4,
		      'indent_char': '�',
		      'max_char': 100,
		      'brace_style': 'expand'
		    });    	
    	}
    	
    	// Supprime la div temporaire
    	result.remove();
    	
    	// Retourne le r�sultat
    	return code;
    }

    
    /**
     * M�thode r�cursive utilis�e par getCode().
     * 
     * Prend tous les �l�ments de type ".fbitem" pr�sents dans from et cr�e des noeuds 
     * du type indiqu� dans "fbtype" dans "to".
     * 
     * Par exemple, si on a <div class="fbitem" fbtype="textbox"></div> dans "from", on 
     * va cr�er un noeud <textbox /> dans to.
     * 
     * Le noeud est cr�� en reprenant tout les attributs qui figurent dans l'item d'origine.
     * 
     * Les classes css utilis�es par l'�diteur sont supprim�es avant la copie. 
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
    		    		// Cr�e un noeud du type indiqu� dans l'attribut fbitem
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
            		
    				// G�re les attributs prot�g�s
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
    		// id�e : copier les noeuds de type texte $(':text') qui ne contiennent que des blancs
        	// permettrait peut-�tre de garder la mise en forme du code source html.
    		} else {
	        	copyItems($(this).children(), to);
    		}
    	});
    }
    
    // Les items peuvent �tre tri�s
/*    
    $('.fbcontainer').sortable({
    	connectWith: '.fbcontainer',	// Un item peut aller dans un autre container
    	placeholder: css.placeholder, 			  	// Preview du drop
    	opacity: 0.4,							  	// Permet de mieux voir o� on va dropper
    	items: '.fbitem', 						  	// Un legend dans un fieldset, par ex. ne peut pas �tre d�plac�
    	distance: 10, 							  	// Evite de commencer un drag'n drop quand on veut juste s�lectionner un item
    	grid: [1, 10], 								// Am�liore l'insertion au d�but ou � la fin d'un fieldset.
//    	start: function(event, ui) { 				// S�lectionne l'item quand on commence un drag'n drop
//    		selectItem(ui.item);
//    	},
//		stop: function(event, ui) {
//    		console.log(event, ui, ui.item.attr('style'));
//    		ui.item.attr('style', '');
//    	}
    });
*/
    
    $('.fbcontainer').sortable({
    	items: '.fbitem', 						  	// Un legend dans un fieldset, par ex. ne peut pas �tre d�plac�
    	connectWith: '.fbcontainer',				// Un item peut aller dans un autre container
//    	placeholder: css.placeholder, 			  	// Preview du drop
//    	forcePlaceholderSize: true,					// Fait en sorte que le placeholder ait la m�me taille que l'item
//    	distance: 10, 							  	// Evite de commencer un drag'n drop quand on veut juste s�lectionner un item
//    	containment: '#fb-editor',					// Les items en cours de drag'n drop ne peuvent pas sortir de l'�diteur
    	cursor: 'hand',
//    	cursorAt: {top: 0, left: 0},
////    	forceHelperSize: true,
    	grid: [1, 5], 								// Am�liore l'insertion au d�but ou � la fin d'un fieldset.
//    	helper: 'clone',
    	//tolerance: 'pointer',
    	helper: function(event, item){
    		//return item.clone();
    		console.log(item.width())
    		return $('<div class="fbitem ui-state-active" style="width: '+item.width()+'px; height: '+item.height()+'px" />')
    	},
//    	revert: true,								// Smooth animation quand on lache l'item � sa nouvelle position
    	delay: 100,
    	cancel: '[contenteditable="true"]',
    	start: function(event, ui) { 				// S�lectionne l'item quand on commence un drag'n drop
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
            (event.metaKey ? event.metaKey : false); // meta g�n�re undefined sous ie
        
        var nav = {
            33: 'first',     // Page Up
            36: 'first',     // Home
            34: 'last',      // Page Down
            35: 'last',      // End
            40: 'next',      // Key Down
            38: 'previous',  // Key Up
            27: 'none',      // Esc
            13: 'current',   // Entr�e
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