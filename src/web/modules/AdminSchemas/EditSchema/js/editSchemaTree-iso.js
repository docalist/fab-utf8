$(function () {

	/**
	 * Expression r�guli�re utilis�e pour masquer certaines propri�t�s.
	 * 
	 * Par d�faut, les propri�t�s qui commencent par un underscore ne sont pas affich�es. 
	 */
	var hiddenProperties = /^_/;

	
	/**
	 * Liste des propri�t�s connues.
	 * 
	 * La config permet d'indiquer, pour chaque type de noeud, la liste des propri�t�s que
	 * l'�diteur "connait" et de d�finir le contr�le qui sera utilis� pour la saisie.
	 * 
	 * Pour chaque propri�t�, on indique son type en utilisant le codage suivant :
	 * - une chaine (type par d�faut) : une textarea fullwidth autoheight sera utilis�e. 
	 * - null: propri�t� ignor�e, ne sera pas affich�e.
	 * - un entier : une zone de texte de type "number" (html5) de 5 caract�res maxi sera utilis�e
	 * - true : la propri�t� sera repr�sent�e par une case � cocher
	 * - un tableau de valeur (['v1','v2'..]) : la propri�t� sera repr�sent�e par un select dans
	 *   lequel l'utilisateur peut s�lectionner l'une des valeurs qui figurent dans le tableau.
	 * - false : propri�t� en lecture seule. Une textarea "disabled" sera utilis�e pour afficher
	 *   la propri�t�. L'utilisateur peut voir la valeur de la propri�t� mais ne peut pas la 
	 *   modifier.  
	 */
	var knownProperties =
	{
		properties:
		{	
			// Propri�t�s en lecture seule
			version: false,
			creation: false,
			lastupdate: false
		},
		field:
		{
			type: ['text','bool','int','autonumber'],
			_id: 1,
			defaultstopwords: true
		},
		indexfield:
		{
			words: true,
			phrases: true,
			values: true,
			count: true,
			global: null, // ignor�e
			start: '',
			end: '',
			weight: 1,
		}
	};
	
	
	/**
	 * Path des icones utilis�es pour repr�senter les noeuds
	 */
	var iconpath = '../../index.php/FabWeb/modules/AdminSchemas/images/';

	
	/**
	 * Configuration du contr�le tree.
	 * 
	 * C'est ici qu'on indique tous le sparam�tres pour jstree et qu'on d�finit tous les types
	 * de noeuds utilis�s dans l'arborescence.
	 */
	var treeConfig = 
	{
        // Liste des plugins jstree utilis�s
        plugins: 
    	[
    	 	"html_data",	// pour pouvoir transformer un ul existant en tree 
    	 	"themes", 		// pour avoir els icones, les lignes, etc.
    	 	"ui",			// pour pouvoir s�lectionner des �l�ments, avoir des hovers, etc.
    	 	"crrm",			// cr�ation/renommage/suppression et d�placements des noeuds
    	 	"hotkeys",		// Raccourcis clavier
//    	 	"contextmenu",	// Menu contextuel
    	 	"dnd", 			// Drag'n drop
    	 	"types",		// Pour pouvoir d�finir des types de noeuds
	 	],

	 	ui:
 		{
	 		select_limit: 1, // One ne peut s�lectionner qu'un seul noeud � la fois
	 		initially_select: ['#schema'], // Initiallement, on affiche les propri�t�s de la base
 		},
 		
	 	core: 
        {
        	initially_open : ["schema"], //, "fields", "indices"],
			strings:
			{
				loading : "Chargement en cours...", 
				new_node : "Entrez un nom", 
			},
			animation: 0
        },

		crrm:
		{ 
			move:
			{
				/**
				 * Quand on d�place un item, il doit rester dans son container (i.e, on
				 * ne peut pas d�placer un champ dans la liste des index, par exemple)
				 * Code copi� depuis la doc : dnd/reorder only
				 */
				check_move: function (m) 
				{
					var p = this._get_parent(m.o);
					if(!p) return false;
					p = p == -1 ? this.get_container() : p;
					if(p === m.np) return true;
					if(p[0] && m.np[0] && p[0] === m.np[0]) return true;
					return false;
				}
			}
		},
        
		themes:
		{
			theme: "classic",
			icons: true,
			dots : false,
		},
		
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
		
		types : 
		{
			valid_children: ["root"],
			types:
			{
				"default":
				{
				},
				
				// Niveau 1
				schema:
				{
//					valid_children: ['fields','indices','aliases','lookuptables','sortkeys'],
					valid_children: [],
					delete_node : false,
					rename: false,
					icon:
					{
						image: iconpath + 'gear.png',
						add: iconpath + 'gear--plus.png',
						remove: iconpath + 'gear--minus.png',
							// gear.png, gear--plus, gear--minus
					},
					label:
					{
						add: 'Nouvelle propri�t�',
						remove:'Supprimer la propri�t� %1', // %1=name, %2=type
					}
				},
				fields:
				{
					max_depth: 2,
					valid_children: ["group", "field"],
					delete_node : false,
					rename: false,
					icon:
					{
						image: iconpath + 'zone--arrow.png',
							// document--plus, layer--plus
							// notebook--plus pour les groupes, report--plus
							// ui-toolbar--plus
							// zone--plus
					},
				},
					field:
					{
						max_depth: 2,
						valid_children: [],
						icon:
						{
							image: iconpath + 'zone.png',
							add: iconpath + 'zone--plus.png',
							remove: iconpath + 'zone--minus.png',
						},
						label:
						{
							add: 'Nouveau champ',
							remove:'Supprimer le champ %1 (%2)', // %1=name, %2=type
						}
					},
					group:
					{
						max_depth: 3,
						valid_children: ["groupfield"],
						icon:
						{
							image: iconpath + 'folder-open-document-text.png'
								// changer icones
						},
						label:
						{
							add: 'Nouveau groupe de champs',
							remove:'Supprimer le groupe de champs %1 (%2)', // %1=name, %2=type
						}
					},
						groupfield:
						{
							max_depth: 2,
							valid_children: [],
							icon:
							{
								image: iconpath + 'zone.png',
								add: iconpath + 'zone--plus.png',
								remove: iconpath + 'zone--minus.png',
							},
							label:
							{
								add: 'Ajouter un champ au groupe',
								remove:'Supprimer le champ %1 du groupe', // %1=name, %2=type
							}
						},
					
				indices:
				{
//						max_depth: 2,
					valid_children: ["index"],
					icon:
					{
						image: iconpath + 'lightning--arrow.png',
							// key.png, key--plus, key--minus, key-solid
							// lightning--plus
					},
					delete_node : false,
					rename: false,
				},
					index:
					{
//							max_depth: 2,
						valid_children: ["indexfield"],
						icon:
						{
							image: iconpath + 'lightning.png',
							add: iconpath + 'lightning--plus.png',
							remove: iconpath + 'lightning--minus.png',
						},
						label:
						{
							add: 'Nouvel index',
							remove:"Supprimer l'index %1", // %1=name, %2=type
						}
					},
						indexfield:
						{
//								max_depth: 2,
							valid_children: [],
							icon:
							{
								image: iconpath + 'zone.png',
								add: iconpath + 'zone--plus.png',
								remove: iconpath + 'zone--minus.png',
							},
							label:
							{
								add: "Ajouter un champ � l'index",
								remove:"Enlever le champ %1 de l'index", // %1=name, %2=type
							}
					},
				
				aliases:
				{
					max_depth: 2,
					valid_children: ["alias"],
					icon:
					{
						image: iconpath + 'key--arrow.png'
							// bookmark--plus
					},
					delete_node : false,
					rename: false,
				},
					alias:
					{
						max_depth: 2,
						valid_children: ["aliasindex"],
						icon:
						{
							image: iconpath + 'key.png',
							add: iconpath + 'key--plus.png',
							remove: iconpath + 'key--minus.png',
						},
						label:
						{
							add: 'Nouvel alias',
							remove:"Supprimer l'alias %1 (%2)", // %1=name, %2=type
						}
					},
						aliasindex:
						{
							max_depth: 2,
							valid_children: [],
							icon:
							{
								image: iconpath + 'lightning.png',
								add: iconpath + 'lightning--plus.png',
								remove: iconpath + 'lightning--minus.png',
							},
							label:
							{
								add: "Ajouter un index � l'alias",
								remove:"Enlever l'index %1 de l'alias", // %1=name, %2=type
							}
						},

				lookuptables:
				{
					max_depth: 2,
					valid_children: ["lookuptable"],
					icon:
					{
						image: iconpath + 'magnifier--arrow.png'
							// magnifier--plus
							// table--plus
					},
					delete_node : false,
					rename: false,
				},
					lookuptable:
					{
						max_depth: 2,
						valid_children: ["lookuptablefield"],
						icon:
						{
							image: iconpath + 'magnifier.png',
							add: iconpath + 'magnifier--plus.png',
							remove: iconpath + 'magnifier--minus.png',
						},
						label:
						{
							add: 'Nouvelle table de lookup',
							remove:'Supprimer la table de lookup %1', // %1=name, %2=type
						}
					},
						lookuptablefield:
						{
							max_depth: 2,
							valid_children: [],
							icon:
							{
								image: iconpath + 'zone.png',
								add: iconpath + 'zone--plus.png',
								remove: iconpath + 'zone--minus.png',
							},
							label:
							{
								add: 'Ajouter un champ � la table de lookup',
								remove:'Enlever le champ %1 de la table de lookup', // %1=name, %2=type
							}
						},

				sortkeys:
				{
					max_depth: 2,
					valid_children: ["sortkey"],
					icon:
					{
						image: iconpath + 'sort--arrow.png',
							// sort--plus
							// task--plus
					},
					delete_node : false,
					rename: false,
				},
					sortkey:
					{
						max_depth: 2,
						valid_children: ["sortkeyfield"],
						icon:
						{
							image: iconpath + 'sort.png',
							add: iconpath + 'sort--plus.png',
							remove: iconpath + 'sort--minus.png'
						},
						label:
						{
							add: 'Nouvelle cl� de tri',
							remove:'Supprimer la cl� de tri %1 (%2)', // %1=name, %2=type
						}
					},
						sortkeyfield:
						{
							max_depth: 2,
							valid_children: [],
							icon:
							{
								image: iconpath + 'zone.png',
								add: iconpath + 'zone--plus.png',
								remove: iconpath + 'zone--minus.png',
							},
							label:
							{
								add: 'Ajouter un champ � la cl� de tri',
								remove:'Supprimer le champ %1 de la cl� de tri', // %1=name, %2=type
							}
						},
			}
		}
	};
	
	/**
	 * Cr�e le contr�le tree qui repr�sente la hi�rarchie du sch�ma
	 */
	var tree = $("#schema").jstree(treeConfig);

	/**
	 * Quand un noeud est s�lectionn�, on charge toutes ses donn�es dans le formulaire
	 */
	tree.bind("select_node.jstree", function (e, data) 
	{
		load(data.inst, data.rslt.obj); // L'instance de jstree, le noeud qui a �t� s�lectionn�
	});
	
	/**
	 * Quand un noeud est d�s�lectionn�, on sauvegarde les donn�es du formulaire
	 */
	tree.bind("deselect_all.jstree", function (e, data) 
	{
		save(data.inst, data.rslt.obj[0]); // L'instance de jstree, le noeud qui a �t� d�s�lectionn�
	});

/*	
	tree.bind("NOTUSEDhover_node.jstree", function (e, data) 
	{
		// L'instance de jstree
		var tree = data.inst;

		// Le noeud qui a �t� s�lectionn�
		var node = data.rslt.obj;

		tree.deselect_all();
		tree.select_node(node);
	});
*/
	
	
	/**
	 * Cr�e et affiche le formulaire de saisie � partir des donn�es qui figurent dans le noeud 
	 * pass� en param�tre.
	 */
    function load(tree, node)
    {
    	// Cr�e la barre d'outils
		loadToolbar(tree, node);
		
		// Le type de noeud
		var type = tree._get_type(node);
		
		// Les propri�t�s du noeud
		var properties = node.data();
		
		// Change le titre du formulaire
		$('#form-title').text(tree.get_text(node) + ' (' + tree._get_type(node) + ')�:');
		
		// Ajoute toutes les propri�t�s du noeud dans le formulaire
		var form=$('#form table tbody').empty();
		var addAutoheight = false;
		for(var name in properties)
		{
			// Teste si c'est une propri�t� ignor�e (non affich�e, exemple : _id, _type, etc.)
			if (name.match(hiddenProperties)) continue;
			
			// Teste si cette propri�t� est d�finie knownProperties
			var def = ''; 
			if (knownProperties[type] && (typeof(knownProperties[type][name]) !== undefined))
			{
				def = knownProperties[type][name];
			};
			
			// La propri�t� est connue mais est d�finie � null, on l'ignore
			if (def === null) continue;
			
			// Le contr�le de saisie qui sera cr�� pour cette propri�t�
			var ctl = null;
			
			// type = false : textarea en lecture seule
			if(def === false)
			{
				ctl = $('<textarea rows="1" disabled="disabled" />').val(properties[name]);
				addAutoheight = true;
			}

			// type = true : case � cocher
			else if (def===true)
			{
				ctl = $('<input type="checkbox" value="1" />').attr('checked', properties[name]);
			}
			
			// type = entier : input type=number
			else if(typeof(def)=='number')
			{
				ctl = $('<input type="number" min="0" size="5" />').val(properties[name]);
			}
			
			// type = tableau (ou objet) : select avec la liste des valeurs
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
			
			// type=string, type par d�faut : textarea
			else
			{
				ctl = $('<textarea rows="1" />').val(properties[name]);
				addAutoheight = true;
				// on ne peut pas faire le autoheight directement car � ce stade, la textarea
				// n'a pas encore de style car elle n'est pas dans le dom.
				// du coup, si on le faisait maintenant le shadow cr�� par autoheight n'aurait
				// pas les bons styles.
			}

			// Ajoute au contr�le cr�� le nom de la propri�t�, l'id, la classe
			ctl.attr('name', name);
			ctl.attr('id', type + '_' + name);
			ctl.attr('class', type + '_property'); // utilis� par save()
			
			// Ajoute une ligne dans le formulaire
			var th=$('<th />').append('<label for="'+name+'">'+name+'�:�</label>');
			var td=$('<td />').append(ctl);
			var tr=$('<tr />').append(th).append(td);
			form.append(tr);
			
			// Ajoute l'autoheight
			if (addAutoheight)
			{
				ctl.change(autoheight).keyup(autoheight).keydown(autoheight).trigger('change');
			}
		}
		
		// au moment o� le contr�le est ajout� dans le formulaire, le navigateur n'a pas encore fait 
		// les calculs pr�cis (dans l'exemple que j'ai, il a d�termin� que la textarea fera 1024px
		// de large mais au final elle ne fera en r�alit� que 1007px).
		// du coup, on fait le autoheight en deux �tapes, une premi�re fois avec l'approximation (on
		// a presque toujours le bon r�sultat), puis une seconde fois, apr�s rendu (setimeout) pour
		// corriger les erreurs �ventuelles.
		window.setTimeout(function(){$('textarea',form).trigger('change')}, 1 );
    }

    
    /**
     * Sauvegarde les donn�es du formulaire dans le noeud pass� en param�tre
     */
	function save(tree, node)
	{
		// Le type de noeud
		var type = tree._get_type(node);
		
		var data = {};
		$('.' + type + '_property').each(function(){
			var $this=$(this);
			data[$this.attr('name')] = $this.val();
		});
		$(node).data(data);
	};
    

	/**
     * Cr�e la barre d'outils correspondant au noeud pass� en param�tre
     */
    function loadToolbar(tree, node)
    {
		// Cr�e la toolbar
		var toolbar=$('<div id="schema-toolbar" />');
		toolbar.add = function(icon, label, click)
		{
			$('<a style="background-image:url(%1)">%2</a>'.format(icon, label))
				.click(click)
				.appendTo(this);
		};
		
    	// Type du noeud actuellement s�lectionn�
    	var nodeType = tree._get_type(node);

    	// Cr�e un tableau contenant tous les types de noeuds de la racine jusqu'au noeud s�lectionn�
    	var nodes = [nodeType];
		node.parentsUntil(".jstree", "li").each(function () {
			nodes.unshift(tree._get_type(this));
		});
		
		console.log("Liste des noeuds : ", nodes);
		
		//toolbar.append($('<a><img src="' + iconpath + 'database_save.png" /> Enregistrer le sch�ma</a> '));
		toolbar.add(iconpath + 'database_save.png', 'Enregistrer le sch�ma', saveSchema);
		
		/*
			Algorithme :
	 		- on prend tous les parents du noeud s�lectionn�
	 		- pour chaque parent, on prend la liste des valid_children
	 		- pour chaque valid_children, on ajoute un bouton "AJOUTER valid_children"
	 		- pour le noeud s�lectionn�, on ajoute "SUPPRIMER ce noeud", sauf pour les noeuds racines.
	 		- les boutons "enregistrer le sch�ma" et "ajouter une propri�t�" sont toujours ajout�s.
		*/
		
		var config = tree.get_settings();
		var types = config.types.types;
		for(var i in nodes)
		{
			var parent = nodes[i];
			var validChildren = types[parent].valid_children;
			for (var j in validChildren)
			{
				var child=validChildren[j]; 
				toolbar.add(types[child].icon.add, types[child].label.add.format(child));
			}
		}
		if (nodes.length>2)
		{
			toolbar.add(types[nodeType].icon.remove, types[nodeType].label.remove.format(tree.get_text(node), nodeType));
		};
		toolbar.add(config.types.types['schema'].icon.add, config.types.types['schema'].label.add.format(child));
		$('#schema-toolbar').replaceWith(toolbar);
    }
    
    function saveSchema()
    {
    	var tree = jQuery.jstree._reference('#schema');
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
    	// Les propri�t�s du noeud sont stock�es dans l'objet lui-m�me
		var data = 
			(level < 3) ?
			{
				nodetype: tree._get_type(node),
				name: tree._get_type(node),
			}
			:
			{
				nodetype: tree._get_type(node),
				name: tree.get_text(node)
			};
			
		if (data.nodetype==='field' && data.name==='REF') data.nodetype = 'groupfield';
		if (data.nodetype==='field' && data.name==='DS') data.nodetype = 'groupfield';
		var properties = $(node).data();
		$.extend(data, properties);
		
		// Les noeuds fils sont stock�s sous forme de tableau dans la cl� children
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
    
	$('#add-property').click(function(){
		var name;
		while(true)
		{
			name = prompt("Indiquez le nom de la propri�t� � cr�er :", name);
			if (name === null) break; // cancel
			
			var tree = jQuery.jstree._reference('#schema');
			var node = tree.get_selected()[0];
			if (typeof($(node).data(name)) !== 'undefined')
			{
				alert("Il y a d�j� une propri�t� existante qui s'appelle '" + name + "'.");
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
	});

	// Formatte une chaine avec des arguments.
	// exemple : "%1 (%2)".format('doe','john')
	String.prototype.format = function() {
	    var formatted = this;
	    for (var i = arguments.length-1; i >= 0; i--) {
	        formatted = formatted.replace(new RegExp('%'+(i+1), 'g'), arguments[i]);
	    }
	    return formatted;
	};
	
	// autoheight personnalis�
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
//        var val = this.value.replace(/</g, '&lt;')
//                            .replace(/>/g, '&gt;')
//                            .replace(/&/g, '&amp;')
//                            .replace(/\n/g, '<br/>');

//        val += 'X'; // force le dernier BR �ventuel a �tre pris en compte, sinon il est ignor�
        shadow.text(this.value + 'X');
        $this.css('height', shadow.height());
    }
	// fin autoheight

	
});

/**
 * JSON to XML jQuery plugin. Provides quick way to convert JSON object to XML 
 * string. To some extent, allows control over XML output.
 * Just as jQuery itself, this plugin is released under both MIT & GPL licences.
 * 
 * @version 1.02
 * @author Micha� Korecki, www.michalkorecki.com
 */
(function($) {
	/**
	 * Converts JSON object to XML string.
	 * 
	 * @param json object to convert
	 * @param options additional parameters 
	 * @return XML string 
	 */
	$.json2xml = function(json, options) {
		settings = {};
		settings = $.extend(true, settings, defaultSettings, options || { });
		return convertToXml(json, settings.rootTagName, '', 0);
	};
	
	var defaultSettings = {
		formatOutput: false,
		formatTextNodes: false,
		indentString: '  ',
		rootTagName: 'root',
		ignore: [],
		replace: [],
		nodes: [],
		///TODO: exceptions system
		exceptions: []
	};
	
	/**
	 * This is actual settings object used throught plugin, default settings
	 * are stored separately to prevent overriding when using multiple times.
	 */
	var settings = {};
	
	/**
	 * Core function parsing JSON to XML. It iterates over object properties and
	 * creates XML attributes appended to main tag, if property is primitive 
	 * value (eg. string, number).
	 * Otherwise, if it's array or object, new node is created and appened to
	 * parent tag. 
	 * You can alter this behaviour by providing values in settings.ignore, 
	 * settings.replace and settings.nodes arrays. 
	 * 
	 * @param json object to parse
	 * @param tagName name of tag created for parsed object
	 * @param parentPath path to properly identify elements in ignore, replace 
	 * 	      and nodes arrays
	 * @param depth current element's depth 
	 * @return XML string
	 */
	var convertToXml = function(json, tagName, parentPath, depth) {
		var suffix = (settings.formatOutput) ? '\r\n' : '';
		var indent = (settings.formatOutput) ? getIndent(depth) : '';
		var xmlTag = indent + '<' + tagName;
		var children = '';
		
		for (var key in json) {
			if (json.hasOwnProperty(key)) {
				var propertyPath = parentPath + key;
				var propertyName = getPropertyName(parentPath, key);
				// element not in ignore array, process
				if ($.inArray(propertyPath, settings.ignore) == -1) {
					// array, create new child element
					if ($.isArray(json[key])) {
						children += createNodeFromArray(json[key], propertyName, 
								propertyPath + '.', depth + 1, suffix);
					}
					// object, new child element aswell
					else if (typeof(json[key]) === 'object') {
						children += convertToXml(json[key], propertyName, 
								propertyPath + '.', depth + 1);
					}
					// primitive value property as attribute
					else {
						// unless it's explicitly defined it should be node
						if (true || $.inArray(propertyPath, settings.nodes) != -1) {
							children += createTextNode(propertyName, json[key], 
									depth, suffix);
						}
						else {
							xmlTag += ' ' + propertyName + '="' +  json[key] + '"';
						}
					}
				}
			}
		}
		// close tag properly
		if (children !== '') {
			xmlTag += '>' + suffix + children + indent + '</' + tagName + '>' + suffix;
		}
		else {
			xmlTag += '/>' + suffix;
		}
		return xmlTag;		
	};
	
	
	/**
	 * Creates indent string for provided depth value. See settings for details.
	 * 
	 * @param depth
	 * @return indent string 
	 */
	var getIndent = function(depth) {
		var output = '';
		for (var i = 0; i < depth; i++) {
			output += settings.indentString;
		}
		return output;	
	};
	
	
	/**
	 * Checks settings.replace array for provided name, if it exists returns
	 * replacement name. Else, original name is returned.
	 * 
	 * @param parentPath path to this element's parent
	 * @param name name of element to look up
	 * @return element's final name
	 */
	var getPropertyName = function(parentPath, name) {
		var index = settings.replace.length;
		var searchName = parentPath + name;
		while (index--) {
			// settings.replace array consists of {original : replacement} 
			// objects 
			if (settings.replace[index].hasOwnProperty(searchName)) {
				return settings.replace[index][searchName];
			}
		}
		return name;
	};
	
	/**
	 * Creates XML node from javascript array object.
	 * 
	 * @param source 
	 * @param name XML element name
	 * @param path parent element path string
	 * @param depth
	 * @param suffix node suffix (whether to format output or not)
	 * @return XML tag string for provided array
	 */
	var createNodeFromArray = function(source, name, path, depth, suffix) {
		var xmlNode = '';
		if (source.length > 0) {
			for (var index in source) {
				// array's element isn't object - it's primitive value, which
				// means array might need to be converted to text nodes
	            if (typeof(source[index]) !== 'object') {
	            	// empty strings will be converted to empty nodes
	                if (source[index] === "") {
	                	xmlNode += getIndent(depth) + '<' + name + '/>' + suffix;                    
	                }
	                else {
	            		var textPrefix = (settings.formatTextNodes) 
                    ? suffix + getIndent(depth + 1) : '';
        				var textSuffix = (settings.formatTextNodes)
        					? suffix + getIndent(depth) : '';	        				
	                	xmlNode += getIndent(depth) + '<' + name + '>' 
	                			+ textPrefix + source[index] + textSuffix 
	                			+ '</' + name + '>' + suffix;                                              
	                }
	            }
	            // else regular conversion applies
	            else {
	            	xmlNode += convertToXml(source[index], name, path, depth);
	            }					
			}
		}
		// array is empty, also creating empty XML node		
		else {
			xmlNode += getIndent(depth) + '<' + name + '/>' + suffix;
		}
		return xmlNode;
	};	
	
	/**
	 * Creates node containing text only.
	 * 
	 * @param name node's name
	 * @param text node text string
	 * @param parentDepth this node's parent element depth
	 * @param suffix node suffix (whether to format output or not)
	 * @return XML tag string
	 */
	var createTextNode = function(name, text, parentDepth, suffix) {
		// unformatted text node: <node>value</node>
		// formatting includes value indentation and new lines
		var textPrefix = (settings.formatTextNodes) 
			? suffix + getIndent(parentDepth + 2) : ''; 
		var textSuffix = (settings.formatTextNodes)
			? suffix + getIndent(parentDepth + 1) : '';
		var xmlNode = getIndent(parentDepth + 1) + '<' + name + '>'
					+ textPrefix + text + textSuffix 
					+ '</' + name + '>' + suffix;
		return xmlNode;
	};
})(jQuery);