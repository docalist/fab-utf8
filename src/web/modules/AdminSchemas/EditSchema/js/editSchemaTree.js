(function ($) {
	$.fn.schemaEditor = function (options) {
		
		/**
		 * Configuration du jstree.
		 * 
		 * Une partie de la config est définie ici. Le reste (les types de noeuds, notamment)
		 * sont passés en paramètre par l'appellant.
		 */
		var settings = {
	        // Liste des plugins jstree utilisés
	        plugins: 
	    	[
	    	 	"html_data",	// pour pouvoir transformer un ul existant en tree 
	    	 	"themes", 		// pour avoir els icones, les lignes, etc.
	    	 	"ui",			// pour pouvoir sélectionner des éléments, avoir des hovers, etc.
	    	 	"crrm",			// création/renommage/suppression et déplacements des noeuds
	    	 	"hotkeys",		// Raccourcis clavier
//		    	 	"contextmenu",	// Menu contextuel
	    	 	"dnd", 			// Drag'n drop
	    	 	"types",		// Pour pouvoir définir des types de noeuds
		 	],

		 	ui:
	 		{
		 		select_limit: 1, // One ne peut sélectionner qu'un seul noeud à la fois
		 		initially_select: ['#root'], // Initiallement, on affiche les propriétés de la base
	 		},
	 		
		 	core: 
	        {
	        	initially_open : ["#root", 'fields', 'indices', 'aliases', 'lookuptables','sortkeys'], //, "fields", "indices"],
				strings:
				{
					loading : "Chargement en cours...", 
					new_node : "Entrez un nom", 
				},
				animation: 0
	        },

//			crrm:
//			{ 
//				move:
//				{
//					/**
//					 * Quand on déplace un item, il doit rester dans son container (i.e, on
//					 * ne peut pas déplacer un champ dans la liste des index, par exemple)
//					 * Code copié depuis la doc : dnd/reorder only
//					 */
//					check_move: function (m) 
//					{
//						var p = this._get_parent(m.o);
//						if(!p) return false;
//						p = p == -1 ? this.get_container() : p;
//						if(p === m.np) return true;
//						if(p[0] && m.np[0] && p[0] === m.np[0]) return true;
//						return false;
//					}
//				}
//			},
/*
			themes:
			{
				theme: "classic",
				icons: true,
				dots : false,
			},
*/
/*	        
			contextmenu:
			{
				select_node: true,
				items:
				{
					create:
					{
						label: "Nouveau",
					},
					rename: 
					{
						label: "Renommer",
					},
					remove: 
					{
						label: "Supprimer",
					},
					ccp:null,
					cut: 
					{
						separator_before: true,
						label: "Couper",
						action: function(obj) { this.cut(obj); }
					},
					copy: 
					{
						label: "Copier",
						action: function(obj) { this.copy(obj); }
					},
					paste: 
					{
						label: "Coller",
						action: function(obj) { this.paste(obj); }
					}
				}
			},
*/			
			types:
			{
				valid_children: ['schema'],
				types: {}
			}
		};
		
		/**
		 * Crée le contrôle tree qui représente la hiérarchie du schéma
		 */
		if ( options ) { $.extend(settings, options); }
		var tree = this.jstree(settings);
		
		/**
		 * Expression régulière utilisée pour masquer certaines propriétés.
		 * 
		 * Par défaut, les propriétés qui commencent par un underscore ne sont pas affichées. 
		 */
		var hiddenProperties = /^_/;
		
		/**
		 * Quand un noeud est sélectionné, on charge toutes ses données dans le formulaire
		 */
		tree.bind("select_node.jstree", function (e, data) 
		{
			load(data.inst, data.rslt.obj); // L'instance de jstree, le noeud qui a été sélectionné
		});
		
		/**
		 * Quand un noeud est désélectionné, on sauvegarde les données du formulaire
		 */
//		tree.bind("deselect_all.jstree", function (e, data) 
//		{
//			save(data.inst, data.rslt.obj[0]); // L'instance de jstree, le noeud qui a été désélectionné
//		});
		tree.bind("before.jstree", function (e, data) 
		{
			if (data.func === 'deselect_all')
				save(data.inst, data.inst.get_selected()[0]); // L'instance de jstree, le noeud qui a été désélectionné
		});


		/**
		 * Affiche le titre du formulaire pour un noeud donné
		 */
		function setTitle(tree, node)
		{
			var label = settings.types.types[tree._get_type(node)].label.main;
			var name = tree.get_text(node);
			var title = label;
			
			if (name !== label) title += ' ' + name;
			$('#form-title').text(title);
		}
		
		/**
		 * Crée et affiche le formulaire de saisie à partir des données qui figurent dans le noeud 
		 * passé en paramètre.
		 */
	    function load(tree, node)
	    {
	    	// Crée la barre d'outils
			loadToolbar(tree, node);
			
			// Le type de noeud
			var type = tree._get_type(node);
			
			// Les propriétés du noeud
			var properties = node.data();
			
			var types = settings.types.types;
			
			// Change le titre du formulaire
//			$('#form-title').text(types[type].label.main + ' ' + tree.get_text(node));
			setTitle(tree, node);
			
			// Ajoute toutes les propriétés du noeud dans le formulaire
			var form=$('#form table tbody').empty();
			for(var name in properties)
			{
				var addAutoheight = false;
				
				// Teste si c'est une propriété ignorée (non affichée, exemple : _id, _type, etc.)
//				if (name.match(hiddenProperties)) continue;
				
				// Teste si c'est une propriété par défaut
				var def = ''; 
				if (types[type] && (typeof(types[type].defaults[name]) !== undefined))
				{
					def = types[type].defaults[name];
				};
				
				// Le contrôle de saisie qui sera créé pour cette propriété
				var ctl = null;
				
				// null : textarea en lecture seule
				if (def === null)
				{
					ctl = $('<textarea rows="1" disabled="disabled" />').val(properties[name]);
					addAutoheight = true;
				}

				// boolean : case à cocher
				else if(typeof(def)=='boolean')
				{
					ctl = $('<input type="checkbox" value="1" />');
					if (properties[name] === true || properties[name] === 'true') ctl.attr('checked', true);
				}
				
				// int : input type=number
				else if(typeof(def)=='number')
				{
					ctl = $('<input type="number" min="0" size="5" />').val(properties[name]);
				}
				
				// array (ou objet) : select avec la liste des valeurs
				else if(typeof(def)=='object')
				{
					ctl = $('<select />');
					for (var i in def)
					{
						var option = $('<option />').text(def[i]);
						if (properties[name] == def[i]) option.attr('selected', true);
						ctl.append(option);
					}
				}
				
				// référence
				else if(typeof(def)=='string' && def.charAt(0)==='@')
				{
					ctl = $('<select />');
					$('[rel=' + def.substr(1) +']').each(function(){
						var refname = $(this).data('name');
						var option = $('<option />').text(refname);
						if (properties[name] == refname) option.attr('selected', true);
						ctl.append(option);
					});
					
					// quand l'option du select change, on change le libellé du noeud dans l'arbre
					if (name === 'name') {
						ctl.change(function(){
							tree.rename_node(tree.get_selected()[0], $(this).val());
						});
					};
				}
				
				// string, type par défaut : textarea
				else
				{
					ctl = $('<textarea rows="1" />').val(properties[name]);
					addAutoheight = true;
					// on ne peut pas faire le autoheight directement car à ce stade, la textarea
					// n'a pas encore de style car elle n'est pas dans le dom.
					// du coup, si on le faisait maintenant le shadow créé par autoheight n'aurait
					// pas les bons styles.
					
					// quand la propriété "name" change, modifie le libellé du noeud dans l'arbre
					// ainsi que tous les libellés des noeuds qui ont une référence vers le noeud 
					// modifié.
					if (name === 'name') {
						ctl.addClass('rename');
					};
				}

				// Ajoute au contrôle créé le nom de la propriété, l'id, la classe
				ctl.attr('name', name);
				ctl.attr('id', type + '_' + name);
				ctl.addClass(type + '_property'); // utilisé par save()
				
				// Ajoute une ligne dans le formulaire
				var th=$('<th />').append('<label for="' + type + '_' + name + '">'+name+' : </label>');
				var td=$('<td />').append(ctl);
				var tr=$('<tr />').append(th).append(td);
				form.append(tr);
				
				// Ajoute l'autoheight
				if (addAutoheight)
				{
					ctl.addClass('autoheight').change(autoheight).keyup(autoheight).keydown(autoheight).trigger('change');
				}
			}
			
			// au moment où le contrôle est ajouté dans le formulaire, le navigateur n'a pas encore fait 
			// les calculs précis (dans l'exemple que j'ai, il a déterminé que la textarea fera 1024px
			// de large mais au final elle ne fera en réalité que 1007px).
			// du coup, on fait le autoheight en deux étapes, une première fois avec l'approximation (on
			// a presque toujours le bon résultat), puis une seconde fois, après rendu (setimeout) pour
			// corriger les erreurs éventuelles.
			window.setTimeout(function(){$('.autoheight',form).trigger('change')}, 1 );
			
			window.setTimeout(function() {
				$('.rename').change(function(){
					var node = tree.get_selected()[0];
					var oldname = tree.get_text(node);
					var newname = $(this).val();
					if (oldname === newname) return;
					
					tree.rename_node(node, newname);
					
					// recherche tous les types de noeuds qui ont une référence vers 
					// ce noeud
					for (var i in types)
					{
						for (var name in types[i].defaults)
						{
							var value = types[i].defaults[name];
							if (value === '@' + type)
							{
								$('li[rel=' + i +']').each(function(){
									 if ($(this).data(name) === oldname) {
										$(this).data(name, newname);
										if (name === 'name')
										{
											console.log ('+ modif dans le tree');
											tree.rename_node(this, newname);
										}
									 }
								});
							}
						}
					};
				});
			}, 2);
	    }

	    
	    /**
	     * Sauvegarde les données du formulaire dans le noeud passé en paramètre
	     */
		function save(tree, node)
		{
			// Le type de noeud
			var type = tree._get_type(node);
			$('.rename').trigger('change');
			var data = {};
			$('.' + type + '_property').each(function(){
				var $this = $(this);
				data[$this.attr('name')] = $this.is(':checkbox') ? (this.checked ? true : false) : $this.val();
			});
			$(node).data(data);
		};
	    

		/**
	     * Crée la barre d'outils correspondant au noeud passé en paramètre
	     */
	    function loadToolbar(tree, node)
	    {
			// Crée la toolbar
			var toolbar=$('<div id="schema-toolbar" />');
			toolbar.add = function(icon, label, click)
			{
				return $('<a style="background-image:url(%1)">%2</a>'.format(icon, label))
					.click(click)
					.appendTo(this);
			};
			
	    	// Type du noeud actuellement sélectionné
	    	var nodeType = tree._get_type(node);

	    	// Crée un tableau contenant tous les types de noeuds de la racine jusqu'au noeud sélectionné
	    	var nodesTypes = [nodeType];
	    	var nodes = [node];
			node.parentsUntil(".jstree", "li").each(function () {
				nodesTypes.unshift(tree._get_type(this));
				nodes.unshift(this);
			});
			
			toolbar.add('database_save.png', 'Enregistrer le schéma', saveSchema);
			
			/*
				Algorithme :
		 		- on prend tous les parents du noeud sélectionné
		 		- pour chaque parent, on prend la liste des valid_children
		 		- pour chaque valid_children, on ajoute un bouton "AJOUTER valid_children"
		 		- pour le noeud sélectionné, on ajoute "SUPPRIMER ce noeud", sauf pour les noeuds racines.
		 		- les boutons "enregistrer le schéma" et "ajouter une propriété" sont toujours ajoutés.
			*/
			
			var config = tree.get_settings();
			var types = config.types.types;
			for(var i in nodesTypes)
			{
				if (i < 1) continue; // les fils de premier niveau ne sont pas modifiables
				var parent = nodesTypes[i];
				var node = nodes[i];
				var validChildren = types[parent].valid_children;
				for (var j in validChildren)
				{
					var child=validChildren[j]; 
					var button = toolbar.add(types[child].icon.add, types[child].label.add.format(child), addNode);
					button.data('type', child);
					button.data('node', node);
				}
			}
			if (nodesTypes.length>2)
			{
				toolbar.add(types[nodeType].icon.remove, types[nodeType].label.remove.format(nodeType, tree.get_text(node)), removeCurrentNode);
			};
			toolbar.add(config.types.types['schema'].icon.add, config.types.types['schema'].label.add.format(child), addProperty);
			$('#schema-toolbar').replaceWith(toolbar);
	    }
	    
	    function addNode(e)
	    {
	    	var tree = jQuery.jstree._reference('#schema');
	    	var type = $(e.target).data('type');
	    	var node = $(e.target).data('node');
	    	console.log('ajouter un fils de type ', type, ' dans ', node);
	    	var js =
			{
	    		attr : {rel : type}	
			};
	    	
			var defaults = settings.types.types[type].defaults;
			
	    	for (var name in defaults)
    		{
	    		var value = defaults[name];
	    		
	    		if (typeof(value) == 'object' && value instanceof Array) 
	    			value = value[0];
	    		else if (typeof(value) == 'string' && value.charAt(0)==='@') // référence
	    			value = '';
	    		
	    		js.attr['data-' + name] = value;
    		}

	    	var newnode = tree.create_node(node, 'inside', js);
			tree.deselect_all();
	    	tree.select_node(newnode);
	    }
	    
	    function saveSchema()
	    {
	    	var tree = jQuery.jstree._reference('#schema');
	    	tree.deselect_all(); // force la sauvegarde du noeud en cours
	    	var schema = saveNode(tree,tree._get_children(-1)[0], 1)

	    	console.log(schema);
	    	$('textarea', '#saveform').val(jQuery.toJSON(schema));
	    	$('#saveform').submit();
	    	return;
	    	//console.log(jQuery.toJSON(schema));
	    	var xml = $.json2xml(schema, { formatOutput: true });
	    	console.log(xml);
	    };
	    
	    function saveNode(tree, node, level)
	    {
	    	// Les propriétés du noeud sont stockées dans l'objet lui-même
			var data = 
				(level < 3) ?
				{
					_nodetype: tree._get_type(node),
					name: tree._get_type(node),
				}
				:
				{
					_nodetype: tree._get_type(node),
					name: tree.get_text(node)
				};
				
			if (data.nodetype==='field' && data.name==='REF') data.nodetype = 'groupfield';
			if (data.nodetype==='field' && data.name==='DS') data.nodetype = 'groupfield';
			var properties = $(node).data();
			$.extend(data, properties);
			
			// Les noeuds fils sont stockés sous forme de tableau dans la clé children
			var children = tree._get_children(node);
			if (children.length)
			{
				data.children = [];
		    	children.each(function(){
		    		data.children.push(saveNode(tree, this, level+1));
		    	});
			};
			return data;
	    }
	    
	    function removeCurrentNode()
	    {
			var tree = jQuery.jstree._reference('#schema');
			var node = tree.get_selected()[0];
			tree.delete_node(node);
		}

	    function addProperty()
	    {
			var name;
			while(true)
			{
				name = prompt("Indiquez le nom de la propriété à créer :", name);
				if (name === null) break; // cancel
				
				var tree = jQuery.jstree._reference('#schema');
				var node = tree.get_selected()[0];
				if (typeof($(node).data(name)) !== 'undefined')
				{
					alert("Il y a déjà une propriété existante qui s'appelle '" + name + "'.");
					continue;
				}
				
				$(node).data(name, "zzz");
				tree.deselect_all();
				tree.select_node(node);
				var type = tree._get_type(node);
				$('#' + type + '_' + name).focus().select();
				break;
			};
			return false;
		}

		// Formatte une chaine avec des arguments.
		// exemple : "%1 (%2)".format('doe','john')
		String.prototype.format = function() {
		    var formatted = this;
		    for (var i = arguments.length-1; i >= 0; i--) {
		        formatted = formatted.replace(new RegExp('%'+(i+1), 'g'), arguments[i]);
		    }
		    return formatted;
		};
		
		// autoheight personnalisé
	    var shadow = null, last = null;
		
		// source : http://upshots.org/javascript/jquery-copy-style-copycss
		$.fn.getStyleObject = function(){
		    var dom = this.get(0);
		    var style;
		    var returns = {};
		    if(window.getComputedStyle){
		        var camelize = function(a,b){
		            return b.toUpperCase();
		        };
		        style = window.getComputedStyle(dom, null);
		        for(var i = 0, l = style.length; i < l; i++){
		            var prop = style[i];
		            var camel = prop.replace(/\-([a-z])/g, camelize);
		            var val = style.getPropertyValue(prop);
		            returns[camel] = val;
		        };
		        return returns;
		    };
		    if(style = dom.currentStyle){
		        for(var prop in style){
		            returns[prop] = style[prop];
		        };
		        return returns;
		    };
		    if(style = dom.style){
		      for(var prop in style){
		        if(typeof style[prop] != 'function'){
		          returns[prop] = style[prop];
		        };
		      };
		      return returns;
		    };
		    return returns;
		};

		function autoheight()
	    {
	        var $this = $(this)
	        
	        if (shadow === null)
	    	{
	            var style = $this.getStyleObject();
	            style.height=null;
	            style.position='absolute';
	            style.overflowY= 'hidden';
	            style.top=-10000;
	            //style.wordWrap = 'break-word';
	            shadow = $('<div />').css(style).appendTo(document.body);
	    	};
	    	shadow.css('width', $this.css('width'));
	        if (this.value === last) return;
	    	last = this.value;
//	        var val = this.value.replace(/</g, '&lt;')
//	                            .replace(/>/g, '&gt;')
//	                            .replace(/&/g, '&amp;')
//	                            .replace(/\n/g, '<br/>');

//	        val += 'X'; // force le dernier BR éventuel a être pris en compte, sinon il est ignoré
	        shadow.text(this.value + 'X');
	        $this.css('height', shadow.height());
	    }
		// fin autoheight

		
	};
})(jQuery);