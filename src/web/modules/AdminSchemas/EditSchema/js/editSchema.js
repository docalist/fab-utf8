/**
 * Editeur de schéma.
 * 
 * Ce script fonctionne de paire avec editSchemaTree.html. 
 * Il utilise le composant jsTree : http://www.jstree.com/
 */
(function ($) {
    $.fn.schemaEditor = function (config) {

        var widgetList = {
            'textbox': 'Textbox (zone de texte sur une seule ligne)', 
            'textarea': 'TextArea (zone de texte sur plusieurs lignes)', 
            'checklist': 'CheckList (liste de cases à cocher)', 
            'radiolist': 'RadioList (liste de boutons radio)', 
            'select': 'Select (menu déroulant)' 
        };
        
        /**
         * Définition des différents types de noeuds présent dans le schéma / dans l'arbre.
         * 
         * La structure est celle utilisée par le plugin types de jstree, mais à laquelle
         * on a ajouté plusieurs clés (defaults, form_title, toolbar...)
         * 
         * cf http://www.jstree.com/documentation/types
         * 
         * Liste des clés ajoutées :
         * - defaults : valeurs par défaut utilisées pour créer un nouveau noeud (codage).
         * - form_title : titre à afficher dans le formulaire pour ce type de noeud (format %1 = nom du noeud).
         * - toolbar : liste des boutons (fonctions button*) à afficher pour ce type de noeud.
         */
        var types = {
            Schema : {
                valid_children : ['Fields', 'Indices', 'Aliases'],
                defaults : {
                    format: null,
                    label: "",
                    description: "",
                    stopwords: "",
                    indexstopwords: true,
                    creation: null,
                    lastupdate: null,
                    version: "1",
                    document: '\\Fooltext\\Document\\Document',
                    docid: '',
                    notes:''
                },
                form_title : 'Propriétés du schéma',
                toolbar : [buttonSave, buttonAddProperty]
            },
            Fields : {
                valid_children : ['Field', 'Group'],
                defaults : {},
                form_title : 'Liste des champs du schéma',
                toolbar : [buttonSave, buttonAddField, buttonAddGroup]
            },
            Field : {
                valid_children : [],
                defaults : {
                    name: 'Nouveau champ',
                    type: [ 'text', 'int', 'autonumber', 'bool', 'date'],
                    repeatable: false,
                    label: '',
                    description: '',
                    widget: widgetList,
                    datasource: '',
                    notes: '',
                    _id: null
                },
                form_title : 'Propriétés du champ %1',
                toolbar : [buttonSave, buttonAddField, buttonAddGroup, buttonAddProperty, buttonRemoveField]
            },
            Group : {
                valid_children : ['Field'],
                defaults : {
                    name : 'Nouveau groupe de champs',
                    repeatable: false,
                    label : '',
                    description : '',
                    notes: '',
                    _id : null
                },
                form_title : 'Propriétés du groupe de champs %1',
                toolbar : [buttonSave, buttonAddField, buttonAddGroup, buttonAddProperty, buttonRemoveGroup]
            },
            Indices : {
                valid_children : ['Index'],
                defaults : {
                },
                form_title : 'Liste des index du schéma',
                toolbar : [buttonSave, buttonAddIndex]
            },
            Index : {
                valid_children : [],
                defaults : {
                    name: 'Nouvel index',
                    label: '',
                    description: '',
                    analyzer: analyzerWidget,
                    weight: 1,
                    widget: widgetList,
                    datasource: '',
                    notes: '',
                    fields: fieldsWidget,
                    _id: null,
                    _slot: null
                },
                form_title : "Propriétés de l'index %1",
                toolbar : [buttonSave, buttonAddIndex, buttonAddProperty, buttonRemoveIndex]
            },
            Aliases : {
                valid_children : ['Alias'],
                defaults : {},
                form_title : 'Liste des alias du schéma',
                toolbar : [buttonSave, buttonAddAlias]
            },
            Alias : {
                valid_children : [],
                defaults : {
                    name: 'Nouvel Alias',
                    label: '',
                    description: '',
                    notes: '',
                    indices: indicesWidget
                },
                form_title : "Propriétés de l'alias %1",
                toolbar : [buttonSave, buttonAddAlias, buttonAddProperty, buttonRemoveAlias]
            },
        };
        
        /**
         * Configuration du composant jstree.
         * 
         * Une partie de la config est définie ici. Le reste (les types de
         * noeuds, notamment) sont passés en paramètre par l'appellant.
         */
        var settings = {
            // Liste des plugins jstree utilisés
            plugins: [
                "html_data",    // pour pouvoir transformer un ul existant en tree 
                "themes",       // pour avoir les icones, les lignes, etc.
                "ui",           // pour pouvoir sélectionner des éléments, avoir des hovers, etc.
                "crrm",         // création/renommage/suppression et déplacements des noeuds
                "hotkeys",      // Raccourcis clavier
                "dnd",          // Drag'n drop
                "types",        // Pour pouvoir définir des types de noeuds
            ],

            ui: {
                select_limit: 1, // One ne peut sélectionner qu'un seul noeud à la fois
                initially_select: ['#root'], // Initiallement, on affiche les propriétés du schéma
            },

            core: {
                initially_open : ["#root", "#fields", "#indices", "#aliases"],
                strings: {
                    loading : "Chargement en cours...", 
                    new_node : "Entrez un nom", 
                },
                animation: 0
            },

            themes: {
                theme: "classic",
                icons: true,
                dots : false,
            },

            types: {
                valid_children: 'Schema',
                types: types,
            },
            
            crrm: {
                move: {
                    check_move: function(m){
                        /*                        
                            On veut controller les déplacements de noeuds : les champs au début,
                            les alias à la fin. Pour cela, il faut empêcher qu'un champ se retrouve
                            dans la liste des alias et vice versa.
                            On le fait en comparant le noeud en cours de déplacement (m.o) et le
                            noeud qui figurait auparavant à cette position (m.or).
                            S'ils ne sont pas du même type, on interdit le déplacement.
                            Ca fonctionne bien, sauf qu'on ne peut pas mettre un noeud en 
                            dernière position (il faut remonter ensuite le dernier noeud).
                        */
                        if (tree._get_type(m.o) !== tree._get_type(m.or)) {
                            return false;
                        }

                        return true;
                    }
                }
            },
            
            hotkeys: {
                // Raccourcis modifiés
                "up" : function () { 
                    var n = tree._get_prev(tree.get_selected()[0] || -1)
                    if (n) {
                        tree.deselect_all();
                        tree.select_node(n);
                    }
                    return false; 
                },
                "down" : function () { 
                    var n = tree._get_next(tree.get_selected()[0] || -1)
                    tree.deselect_all();
                    tree.select_node(n && n.length ? n : '#root');
                    return false;
                },
                "left" : function () { 
                    var n = tree.get_selected();
                    if (n && n.hasClass("jstree-open")) { 
                        tree.close_node(n[0]); 
                    }
                    return false;
                },
                "right" : function () { 
                    var n = tree.get_selected();
                    if (n && n.hasClass("jstree-closed")) { 
                        tree.open_node(n[0]); 
                    }
                    return false;
                },
                "del" : function () { 
                    var n = tree.get_selected()[0];
                    if (n) {
                        var type = tree._get_type(n);
                        if (type !== 'Schema') {
                            removeNode(n);
                        }
                    }
                    return false;
                },
                
                // Raccourcis désactivés
                "ctrl+up" : false,
                "shift+up" : false,
                "ctrl+down" : false,
                "shift+down" : false,
                "ctrl+left" : false,
                "shift+left" : false,
                "ctrl+right" : false,
                "shift+right" : false,
                "space" : false,
                "ctrl+space" : false,
                "shift+space" : false,
                "f2" : false
            }
        };

        /**
         * Expression régulière utilisée pour masquer certaines propriétés.
         */
        var hiddenProperties = /format|_stopwords|_type/; // /^_/;

        /**
         * Crée le composant jstree qui représente la hiérarchie du schéma
         */
        var tree = $.jstree._reference(this.jstree(settings));

        /**
         * Quand un noeud est sélectionné, on charge toutes ses données dans le formulaire
         */
        this.bind("select_node.jstree", function (e, data) {
            load(data.rslt.obj); // Le noeud qui a été sélectionné
        });

        /**
         * Quand un noeud est désélectionné, on sauvegarde les données du formulaire
         */
        this.bind("before.jstree", function (e, data) {
            if (data.func === 'deselect_all') {
                save(data.inst.get_selected()[0]); // Le noeud qui a été désélectionné
            }
        });

        /**
         * Crée et affiche le formulaire de saisie à partir des données 
         * qui figurent dans le noeud passé en paramètre.
         */
        function load(node) {
            // Le type de noeud
            var type = tree._get_type(node);

            // Les propriétés du noeud
            var properties = node.data('properties');

            // Crée la barre d'outils
            setTitle(node);
            loadToolbar(node);

            // Ajoute toutes les propriétés du noeud dans le formulaire
            var form=$('#form table tbody').empty();
            var nbProps = 0;
            for(var name in properties) {
                var addAutoheight = false;

                // Teste si c'est une propriété ignorée (non affichée, exemple : _id, _type, etc.)
                if (name.match(hiddenProperties)) continue;

                // Teste si c'est une propriété par défaut
                var def = ''; 
                if (types[type] && (typeof(types[type].defaults[name]) !== undefined)) {
                    def = types[type].defaults[name];
                };

                // Le contrôle de saisie qui sera créé pour cette propriété
                var ctl = null;

                // null : textarea en lecture seule
                if (def === null) {
                    ctl = $('<textarea rows="1" disabled="disabled" />').val(properties[name]);
                    addAutoheight = true;
                }
                else if (typeof(def) === 'function') {
                    ctl = def('load', name, properties[name]);
                }

                // boolean : case à cocher
                else if(typeof(def) === 'boolean') {
                    ctl = $('<input type="checkbox" value="1" />');
                    if (properties[name] === true || properties[name] === 'true') ctl.attr('checked', true);
                }

                // int : input type=number
                else if(typeof(def) === 'number') {
                    ctl = $('<input type="number" min="0" size="5" />').val(properties[name]);
                }

                // array (ou objet) : select avec la liste des valeurs
                else if(typeof(def) === 'object') {
                    ctl = $('<select />');
                    for (var i in def) {
                        var option = $('<option />').text(def[i]);
                        if (isNaN(parseInt(i))) {
                            option.attr('value', i);
                            if (properties[name] == i) option.attr('selected', true);
                        } else {
                            if (properties[name] == def[i]) option.attr('selected', true);
                        }
                    
                        ctl.append(option);
                    }
                }

                // référence
                else if(typeof(def) === 'string' && def.charAt(0)==='@') {
                    ctl = $('<select />');
                    $('[rel=' + def.substr(1) +']').each(function() {
                        var refname = $(this).data('name');
                        var option = $('<option />').text(refname);
                        if (properties[name] == refname) option.attr('selected', true);
                        ctl.append(option);
                    });
                    
                    // quand l'option du select change, on change le libellé du noeud dans l'arbre
                    if (name === 'name') {
                        ctl.change(function() {
                            tree.rename_node(tree.get_selected()[0], $(this).val());
                        });
                    };
                }

                // string, type par défaut : textarea
                else {
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
                ++nbProps;
                
                // Ajoute l'autoheight
                if (addAutoheight) {
                    ctl.addClass('autoheight').change(autoheight).keyup(autoheight).keydown(autoheight).trigger('change');
                }
            }
            if (nbProps === 0) {
                $('#form table').hide();
                $("<p id='noprops'>Cet élément n'a pas de propriétés.</p>").insertBefore('#form table');
            } else {
                $('#form table').show();
                $('#noprops').remove();
            }
            
            // au moment où le contrôle est ajouté dans le formulaire, le navigateur n'a pas encore fait 
            // les calculs précis (dans l'exemple que j'ai, il a déterminé que la textarea fera 1024px
            // de large mais au final elle ne fera en réalité que 1007px).
            // du coup, on fait le autoheight en deux étapes, une première fois avec l'approximation (on
            // a presque toujours le bon résultat), puis une seconde fois, après rendu (setimeout) pour
            // corriger les erreurs éventuelles.
            window.setTimeout(function(){$('.autoheight',form).trigger('change')}, 1 );
            
            window.setTimeout(function() {
                $('.rename').change(function() {
                    var node = tree.get_selected()[0];
                    var type = tree._get_type(node);
                    var oldname = tree.get_text(node);
                    var newname = $(this).val();
                    
                    if (oldname === newname) {
                        return;
                    }

                    tree.rename_node(node, newname);
                    
                    var selector, prop;
                    if (type === 'Field') { // on a renommé un champ, il faut mettre à jour les index
                        selector = 'li[rel=Index]';
                        prop = 'fields';
                    } else if (type === 'Index') { // on a renommé un index, il faut mettre à jour les alias
                        selector = 'li[rel=Alias]';
                        prop = 'indices';
                    } else {
                        return;
                    }
                    
                    $(selector).each(function() {
                        var data = $(this).data('properties');
                        var fields = data[prop];
                        for (var i in fields){
                            if (fields[i] === oldname) {
                                fields[i] = newname;
                            }
                        }
                        $(this).data('properties', data);
                   });
                });
            }, 2);
        }
        
        function setTitle(node)
        {
            var type = $(node).attr('rel');
            var title = types[type].form_title + ' :';
            var name = tree.get_text(node);
            
            $('#form-title').text(title.format(name));
        }

        /**
         * Sauvegarde les données du formulaire dans le noeud passé en paramètre
         */
        function save(node) {
            // Le type de noeud
            var type = tree._get_type(node);
            $('.rename').trigger('change');
            var data = {};
            $('.' + type + '_property').each(function() {
                var $this = $(this);
                var name = $this.attr('name'); // nom de la propriété

                var def = types[type].defaults[name]; // valeur par défaut
                var value;
                
                if (typeof(def) === 'function') {
                    value = def('save', name);
                }
                else if ($this.is(':checkbox')) {
                    value = this.checked ? true : false;
                }
                else {
                    value = $this.val();
                }
                data[name] = value;
            });
            $(node).data('properties', data);
        };

        // Générateurs de boutons
        function buttonSave(toolbar, node) {
            toolbar.add('save-schema', 'Enregistrer le schéma...', saveSchema);
        }

        function buttonAddProperty(toolbar, node) {
            toolbar.add('add-property', 'Nouvelle propriété...', addProperty);
        }

        function buttonAddField(toolbar, node) {
            // Fields:last, Field:after, Group:last, Field (dans un groupe):after
            var position = (tree._get_type(node) === 'Field') ? "after" : 'last';
            toolbar.add('add-field', 'Nouveau champ', function() {
                addNode(node, 'Field', position)
            });
        }
        
        function buttonRemoveField(toolbar, node) {
            toolbar.add('remove-field', 'Supprimer le champ...', function() {
                removeNode(node)
            });
        }

        function buttonAddGroup(toolbar, node) {
            var type = tree._get_type(node);
            var parent = tree._get_parent(node);
            var position;
            
            if (type === 'Field' && tree._get_type(parent) === 'Group') {
                node = parent;
                position = 'after';
            }
            else {
                position = (type === 'Fields') ? 'last' : 'after';
            }
            
            toolbar.add('add-group', 'Nouveau groupe', function() {
                addNode(node, 'Group', position)
            });
        }

        function buttonRemoveGroup(toolbar, node) {
            toolbar.add('remove-group', 'Supprimer le groupe...', function() {
                removeNode(node)
            });
        }
        
        function buttonAddIndex(toolbar, node) {
            var position = (tree._get_type(node) === 'Index') ? "after" : 'last';
            toolbar.add('add-index', 'Nouvel index', function() {
                addNode(node, 'Index', position)
            });
        }

        function buttonRemoveIndex(toolbar, node) {
            toolbar.add('remove-index', "Supprimer l'index...", function() {
                removeNode(node)
            });
        }

        function buttonAddAlias(toolbar, node) {
            var position = (tree._get_type(node) === 'Alias') ? "after" : 'last';
            toolbar.add('add-alias', 'Nouvel alias', function() {
                addNode(node, 'Alias', position)
            });
        }

        function buttonRemoveAlias(toolbar, node) {
            toolbar.add('remove-alias', "Supprimer l'alias...", function() {
                removeNode(node)
            });
        }

        /**
         * Crée la barre d'outils correspondant au noeud passé en paramètre
         */
        function loadToolbar(node) {
            // Crée la toolbar
            var toolbar=$('<div id="schema-toolbar" />');
            toolbar.add = function(classname, label, click) {
                return $('<a class="%1">%2</a>'.format(classname, label))
                    .click(click)
                    .appendTo(this);
            };

            // Type du noeud actuellement sélectionné
            var type = tree._get_type(node);
            $(types[type].toolbar).each(function(i, f) {
                f(toolbar, node);
            });
            $('#schema-toolbar').replaceWith(toolbar);
        }

        /**
         * Ajoute un noeud de type "type" à la fin de l'objet "parent" passé en paramètre.
         */
        function addNode(parent, type, position) {
            var newnode = tree.create_node(parent, position || 'last', {attr : {rel : type}});
            
            var properties = {};
            var defaults = types[type].defaults;
            for (var name in defaults) {
                var value = defaults[name];
                
                if (typeof(value) == 'object' && value instanceof Array) {
                    value = value[0];
                }
                else if (typeof(value) == 'string' && value.charAt(0)==='@') { // référence
                    value = '';
                }
                else if (typeof(value) == 'function') {
                    value = '';
                }
                properties[name] = value;
            }

            newnode.data('properties', properties);
            
            tree.deselect_all();
            tree.select_node(newnode);
        }

        /**
         * Supprime le noeud passé en paramètre.
         */
        function removeNode(node) {
            if (confirm("Supprimer " + tree.get_text(node) + ' ?')) {
                tree.delete_node(node);
            }
        }

        function saveChildren(node) {
            var result = [];
            tree._get_children(node).each(function(){
                var data = $(this).data('properties');
                if ('Group' === $(this).attr('rel')) {
                    data.fields = saveChildren(this);
                }
                result.push(data);
            });
            return result;
        }
        
        function saveSchema() {
            tree.deselect_all(); // force la sauvegarde du noeud en cours
            
            var schema = $('#root').data('properties');
            schema.fields = saveChildren('#fields');
            schema.indices = saveChildren('#indices');
            schema.aliases = saveChildren('#aliases');

            console.log(schema);

            var form = $('#saveform');
            $('textarea', form).val($.toJSON(schema));
            
            jQuery.ajax({
                type: 'POST',
                url: form.attr('action'),
                data: form.serialize(),
                dataType: 'json',
                success: function (data) {
                    if (typeof(data) === 'string') {
                        var message = 'Aucune erreur détectée, votre schéma a été enregistré.\n\n'
                            + 'Voulez vous quitter l\'éditeur ?';
                        if (confirm(message)) {
                            window.location = data;
                        }
                    } else {
                        var message = 'Votre schéma contient une ou plusieurs erreurs :\n\n' 
                            + '- ' + data.join('\n- ') + '\n\n'
                            + 'Pour enregistrer votre schéma, corrigez ces erreurs.';
                        alert(message);
                    }
                }
            });
        };

        function addProperty() {
            var node = tree.get_selected()[0];
            var properties = $(node).data('properties');
            var name;
            
            while(true) {
                name = prompt("Indiquez le nom de la propriété à créer :", name);
                if (name === null) break; // cancel

                if (typeof(properties[name]) !== 'undefined') {
                    alert("Il y a déjà une propriété existante qui s'appelle '" + name + "'.");
                    continue;
                }

                tree.deselect_all();
                
                properties[name] = null;
                $(node).data('properties', properties);
                
                tree.select_node(node);
                var type = tree._get_type(node);
                $('#' + type + '_' + name).focus().select();
                break;
            };
            return false;
        }

        function fieldsWidget(op, name, values) {
            if (op === 'load') {
                var div = $('<div />');
                for (var i = 0; i < values.length; i++) {
                    createSelectForFields(i < values.length ? values[i] : '').appendTo(div);
                }

                $('<a class="button add-field">Ajouter un champ</a>').appendTo(div).click(function() {
                    createSelectForFields('').insertBefore(this);
                    return false;
                });

                return div;
            }
            else if(op === 'save') {
                var values = [];
                $('.chooseField').each(function() {
                    var value = $(this).val();
                    if (value !== '') values.push(value);
                });
                return values;
            }
        }
        
        function createSelectForFields(value)
        {
            var select = $ ('<select />').addClass('chooseField');
            
            var option = $('<option />').attr('value', '').text('...').appendTo(select);
            if (value === '') option.attr('selected', 'selected');

            $(tree._get_children('#fields')).each(function(){
                var type = tree._get_type(this);
                var node = $(this).data('properties');
           
                if (type==='Field') {
                    var option = $('<option />').text(node.name).attr('title', node.label).appendTo(select);
                    if (node.name.toLowerCase() === value.toLowerCase()) {
                        option.attr('selected', 'selected');
                    }
                }
                else { // groupe
                    var group = $('<optgroup />')
                        .attr('label', node.name)
                        .attr('title', node.label)
                        .appendTo(select);
                    
                    $(tree._get_children(this)).each(function(){
                        var child = $(this).data('properties');
                        var childName = node.name + '.' + child.name;
                        var option = $('<option />')
                            .text(childName)
                            .attr('value', childName)
                            .attr('title', child.label)
                            .appendTo(group);
                        
                        if (childName.toLowerCase() === value.toLowerCase()) {
                            option.attr('selected', 'selected');
                        }
                    });
                }
            });

            return select;
        }

        function indicesWidget(op, name, values) {
            if (op === 'load') {
                var div = $('<div />');
                for (var i = 0; i < values.length; i++) {
                    createSelectForIndices(i < values.length ? values[i] : '').appendTo(div);
                }

                $('<a class="button add-index">Ajouter un index</a>').appendTo(div).click(function() {
                    createSelectForIndices('').insertBefore(this);
                    return false;
                });

                return div;
            }
            else if(op === 'save') {
                var values = [];
                $('.chooseIndex').each(function() {
                    var value = $(this).val();
                    if (value !== '') values.push(value);
                });
                return values;
            }
        }

        function createSelectForIndices(value)
        {
            var select = $ ('<select />').addClass('chooseIndex');
            
            var option = $('<option />').attr('value', '').text('...').appendTo(select);
            if (value === '') option.attr('selected', 'selected');

            $(tree._get_children('#indices')).each(function(){
                var node = $(this).data('properties');
                
                var option = $('<option />').text(node.name).attr('title', node.label).appendTo(select);
                if (node.name.toLowerCase() === value.toLowerCase()) {
                    option.attr('selected', 'selected');
                }
            });

            return select;
        }
        
        function analyzerWidget(op, name, values) {
            if (op === 'load') {
                var div = $('<div />');
                for (var i = 0; i < values.length; i++) {
                    createSelectForAnalyzers(i < values.length ? values[i] : '').appendTo(div);
                }

                $('<a class="button add-index">Ajouter un analyseur</a>').appendTo(div).click(function() {
                    createSelectForAnalyzers('').insertBefore(this);
                    return false;
                });

                return div;
            }
            else if(op === 'save') {
                var values = [];
                $('.analyzer').each(function() {
                    var value = $(this).val();
                    if (value !== '') values.push(value);
                });
                return values;
            }
        }

        function createSelectForAnalyzers(value)
        {
            var select = $ ('<select />').addClass('analyzer');
            
            var option = $('<option />').attr('value', '').text('...').appendTo(select);
            if (value === '') option.attr('selected', 'selected');
            
            $(config.analyzer).each(function(index, item) {
                var group = $('<optgroup />').attr('label', item.label).appendTo(select);
                $(item.items).each(function(index, item){
                    var option = $('<option />').attr('value', item.class).text(item.name).attr('title', item.doc).appendTo(group);
                    if (item.class.toLowerCase() === value.toLowerCase()) {
                        option.attr('selected', 'selected');
                    }
                });
            });

            return select;
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

        function autoheight() {
            var $this = $(this)
            
            if (shadow === null) {
                var style = $this.getStyleObject();
                style.height=null;
                style.position='absolute';
                style.overflowY= 'hidden';
                style.top=-10000;
                shadow = $('<div />').css(style).appendTo(document.body);
            };
            shadow.css('width', $this.css('width'));
            if (this.value === last) return;
            last = this.value;
            shadow.text(this.value + 'X');
            $this.css('height', shadow.height());
        }
        // fin autoheight
    };
})(jQuery);