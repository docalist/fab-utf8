var jQuery;
var SchemaEditor =
{
    /**
     * La structure de base de donn�es en cours d'�dition
     */
    db: null,
    
    /**
     * Propri�t�s et valeurs par d�faut des diff�rents types d'objet manipul�s 
     */
    defaults:
    {
        db_fields:              // La liste des champs de la base
        {
            name: 'Champ',      // Nom du champ
            type: 'text',       // Type du champ (juste � titre d'information, non utilis� pour l'instant)
            label: '',          // Libell� du champ
            description: '',    // Description
            defaultstopwords: true, // Utiliser les mots-vides de la base
            stopwords: ''      // Liste sp�cifique de mots-vides � appliquer � ce champ
        },
        
        db_fields_zones:              // La liste des champs de la base
        {
            name: 'SousChamp',      // Nom du champ
            type: 'text',       // Type du champ (juste � titre d'information, non utilis� pour l'instant)
            label: '',          // Libell� du champ
            description: '',    // Description
            defaultstopwords: true, // Utiliser les mots-vides de la base
            stopwords: ''      // Liste sp�cifique de mots-vides � appliquer � ce champ
        },

        db_indices:             // La liste des index de la base
        {
            name: 'Index',      // Nom de l'index
            label: '',          // Libell� de l'index
            description: '',    // Description de l'index
            fields: []          // Liste des champs qui alimentent cet index
        },
        db_indices_fields:      // La liste des champs d'un index
        {
            name: '',           // Nom du champ
            words: false,       // Indexer les mots
            phrases: false,     // Indexer les phrases
            values: false,      // Indexer les valeurs
            count: false,       // Compter le nombre de valeurs
            global: false,      // Prendre en compte cet index dans l'index 'tous champs'
            start: '',          // Position ou chaine indiquant le d�but du texte � indexer
            end: '',            // Position ou chain indquant la fin du texte � indexer
            weight: 1           // Poids des tokens ajout�s � cet index
        },
        
        db_lookuptables:        // La liste des tables de lookup
        {
            name: 'Table',      // Nom de la table de lookup
            label: '',          // Libell� de la table
            description: '',    // Description de la table
            fields: []          // Liste des champs qui alimentent cette table
        },
        db_lookuptables_fields: // La liste des champs d'une table de lookup
        {
            name: '',           // Nom du champ
            start: '',          // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la table
            end: ''             // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la table
        },
                
        db_aliases:             // La liste des alias de la base
        {
            name: 'Alias',      // Nom de l'alias
            label: '',          // Libell� de l'alias
            description: '',    // Description de l'alias
            indices: []         // Liste des index qui composent cet alias
        },
        db_aliases_indices:     // La liste des index d'un alias
        {
            name: ''            // Nom de l'index
        },
        
        db_sortkeys:            // La liste des cl�s de tri de la base
        {
            name: 'Tri',        // Nom de la cl� de tri
            label: '',          // Libell� du tri
            description: '',    // Description du tri
            type: 'string',     // Type de la cl� de tri (string, number)
            fields: []          // Champs (�tudi�s dans l'ordre, s'arr�te au premier non vide)
        },
        db_sortkeys_fields:     // La liste des champs d'une cl� de tru
        {
            name: '',           // Nom du champ
            start: '',          // Position de d�but ou chaine d�limitant le d�but de la valeur � ajouter � la cl� de tri
            end: '',            // Longueur ou chaine d�limitant la fin de la valeur � ajouter � la cl� de tri
            length: ''          // Longueur totale de la partie de cl� (tronqu�e ou padd�e � cette taille)
        }
    },

    /**
     *  La vitesse souhait�e pour les "effets sp�ciaux"
     */ 
    fxSpeed: 'fast',
    
      
    /**
     * L'url et les param�tres pour sauvegarder la structure de la base
     */
    saveUrl: null,
    saveParams: null,
       

    /**
     * Charge la base de donn�es dans l'�diteur
     * Les propri�t�s simples de la base sont charg�es directement dans le champ
     * correspondant (par exemple la propri�t� db.label sera charg�e dans le 
     * champ input#db_label)
     *
     * Pour les propri�t�s qui sont des tableaux (fields, indices...), le
     * select correspondant est recherch� (par exemple select#db_indices pour
     * le tableau db.indices) et une option est ajout�e au select pour chacun
     * des �l�ments trouv�s dans le tableau. Pour chaque option, un expando
     * (data) contenant l'�l�ment de tableau est cr��.
     *
     * La fonction select() appell�e lorsque l'une des options du select est
     * s�lectionn�e se chargera d'initialiser l'interface pour chacune des 
     * propri�t�s de l'objet ajout� � l'option.
     * 
     * @param object object la structure de base de donn�es � charger
     * @param string id le pr�fixe � appliquer aux propri�t�s trouv�es dans
     * l'objet pour d�terminer l'id du champ correspondant de formulaire
     */
    loadStructure: function ()
    {
        var id='#db_';
        var properties = {};
        
        // Balaye toutes les propri�t�s de la base, on va g�rer nous m�mes les
        // tableaux (initialisation des select) et on va construire la liste
        // des propri�t�s simples (properties) qu'on chargera ensuite en 
        // appellant loadObject(properties) 
        for (var prop in this.db)
        {
            // Si c'est un tableau, on initialise le select correspondant
            if (this.db[prop] instanceof Array)
            {
                // Recherche le select correspondant
                var ul = jQuery(id + prop);
                
                // Si on ne le trouve pas, on le signale 
                if (ul.length === 0)
                {
                    continue;
                }

                // Initialise l'�v�nement onchange du ul
                ul.change(SchemaEditor.select);
                
                // Touche suppr sur un select = supprimer l'option (shift+suppr = pas de confirmation)
                ul.keydown(
                    function (e)
                    {
                        if (e.keyCode === 46)
                        { 
                            SchemaEditor.deleteOption(this, e.shiftKey === true);
                        }
                    }
                );

                // Cr�e un expando 'current' pour le ul
                ul.get(0).current = -1;
                
                var array = this.db[prop];
                
                // Pour chaque �l�ment du tableau, on ajoute une option au ul
                for (var i in array)
                {
                    // Cr�e une nouvelle option
                    var li = jQuery('<li>' + array[i].name + '</li>');
                    
                    // Stocke l'�l�ment en cours du tableau comme expando 'data' de l'option
                    li.data = array[i];

                    // Ajoute l'option dans le ul
                    ul.append(li);
                }
                
                // S�lectionne le premier �l�ment du ul ou masque le right panel si le tableau est vide
                ul.change();
            }

            // Sinon (valeur scalaire ou objet) on charge directement la propri�t�
            else
            {
                properties[prop] = this.db[prop];
            }
        }
        this.loadObject('#db', properties);
    },

    
    /**
     * Fonction appell�e lorsque l'utilisateur change l'�l�ment en cours dans un 
     * select. Affiche les propri�t�s de l'option s�lectionn�e.
     *
     * @param {event} e l'�v�nement qui a d�clench� l'appel de la fonction
     */
    select: function (e)
    {
        SchemaEditor.saveSelect(this);
        
        // Si aucun �l�ment n'est s�lectionn� et qu'il y a des options, s�lectionne automatiquement la premi�re
        if (this.selectedIndex === -1 && this.options.length > 0)
        {
            this.selectedIndex = 0;
        }
        
        // Toujours aucun �l�ment : masque le panel de droite et affiche le message 'liste vide'
        if (this.selectedIndex === -1)
        {
            jQuery('#' + this.id + '_rightpanel').hide();
            jQuery('#' + this.id + '_empty').show();
            return;
        }

        // Sauvegarde l'index de l'�l�ment en cours
        this.current = this.selectedIndex;
                
        // On s'assure que le panneau de droite est visible et que le message 'liste vide' est masqu�
        jQuery('#' + this.id + '_rightpanel').show();
        jQuery('#' + this.id + '_empty').hide();
        
        // Affiche les propri�t�s de l'option s�lectionn�e
        SchemaEditor.loadObject('#' + this.id, this.options[this.selectedIndex].data);
    },


    /**
     * Charge dans l'�diteur une valeur scalaire, les propri�t�s d'un objet ou
     * les �l�ments d'un tableau.
     *
     * Les valeurs scalaires sont charg�es en tenant compte du type du champ.
     * Pour un champ texte, la valeur est simplement inject�e, pour une case � 
     * cocher, la valeur est �valu�e et la case est coch�e si celle-ci s'�value 
     * � true.
     * Pour les objets, on r�cursive sur chacune des propri�t�s.
     * Pour les tableaux, on les charge dans le tableau html correspondant, en
     * ajoutant ou en supprimant des lignes � la table.
     *
     * @param {string} id l'ID du contr�le dans lequel il faut charger
     * la valeur (vous devez indiquer le '#' de d�but)
     *
     * @param {mixed} what la valeur � charger
     *
     * @param {mixed} context (optionnel) un contexte qui est pass� � jQuery
     * pour rechercher l'�l�ment dont l'ID est indiqu� (jQuery(id,context)
     *
     * @return {void}
     */    
    loadObject: function (id, what, context)
    {
        if (id.charAt(0) !== '#')
        {
            return;
        }
            
        for (var prop in what)
        {
            var propid = id + '_' + prop;
            var data = what[prop];
            
            // La propri�t� est un tableau : on va le charger dans la table correspondante
            if (data instanceof Array)
            {
                var table = jQuery(propid);
    
                // Si la table n'existe pas, on le signale
                if (table.length === 0)
                {
                    continue;
                }
                
                // Si c'est le premier appel, sauve la TR qui sert de mod�le aux autres
                if (typeof(table.get(0).tr) === 'undefined')
                {
                    table.get(0).tr = jQuery('tr:nth-child(2)', table).remove();
                }
                
                // Le tableau contient des �l�ments : on affiche la table
                if (data.length)
                {    
                    jQuery('tr:not(:first)', table).remove();
                    for (var i in data)
                    {
                        var tr = table.get(0).tr.clone(true);
                        SchemaEditor.loadObject(propid, data[i], tr);
                        table.append(tr);
                    }
                    
                    jQuery(propid + '_empty').hide();
                    jQuery(propid).show();

                    // Beurk ! le tr.clone(true) ne duplique pas les �v�nements pour les petits-enfants,
                    // donc le onclick du bouton est perdu. On le r�p�te... (jquery 1.2.1)
                    jQuery('.deletetr', table).click(
                        function ()
                        {
                            SchemaEditor.deleteTr(jQuery(this).parents('tr:first').get(0));
                        }
                    );
                    
                }
                
                // Tableau vide : masque la table, affiche la mention 'aucun item'
                else
                {
                    jQuery(propid).hide();
                    jQuery(propid + '_empty').show();
                }
            }
            
            // La propri�t� est un objet : r�cursive pour charger chacune des propri�t�s
            else if (data instanceof Object)
            {
                this.loadObject(propid, data, context);
            }
            
            // La propri�t� est une valeur scalaire
            else
            {
                // Recherche le champ dans le contexte indiqu�
                var control = jQuery(propid, context);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                    
                if (control.is(':checkbox'))
                {
                    control.attr('checked', data ? true : false);
                }
                else
                {
                    if (data===null) data='';
                    control.val(data);
                }
            }
        }
    },


    /**
     * Initialise les propri�t�s d'un objet � partir de la valeur des champs correspondants d'un formulaire.
     * 
     * Pour chaque propri�t� de l'objet, la fonction va rechercher un contr�le ayant
     * pour identifiant l'id indiqu� suivi d'un underscrore et du nom de la propri�t�
     * et va initialiser la propri�t� avec la valeur de ce contr�le.
     * 
     * @param {string} id le d�but de l'identifiant des contr�les � rechercher
     * @param {mixed} what l'objet � sauvegarder
     * @param {mixed} context le contexte �ventuel dans lequel il faut rechercher l'id
     */
    saveObject: function (id, what, context)
    {
        for (var prop in what)
        {
            var propid = id + '_' + prop;
            var data = what[prop];
            
            // La propri�t� est un tableau : on va le charger dans la table correspondante
            if (data instanceof Array)
            {
                var table = jQuery(propid);
    
                // Si la table n'existe pas, on le signale
                if (table.length === 0)
                {
                    continue;
                }
                
                // Appelle saveObject pour chacune des lignes du tableau
                var length = jQuery('tr:not(:first)', table).each(
                    function (i)
                    {
                        SchemaEditor.saveObject(propid, data[i], this);
                    }
                ).length;

                // Supprime du tableau tous les �l�ments qui ne sont pas dans la table
                what[prop] = data.slice(0, length);
            }
            
            // La propri�t� est un objet : r�cursive pour charger chacune des propri�t�s
            else if (data instanceof Object)
            {
                this.saveObject(propid, data[prop], context);
            }
            
            // La propri�t� est une valeur scalaire
            else
            {
                // Recherche le champ dans le contexte indiqu�
                var control = jQuery(propid, context);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                
                // Met � jour la propri�t� en fonction de la valeur du contr�le                    
                if (control.is(':checkbox'))
                {
                    what[prop] = (control.attr('checked') === true);
                }
                else
                {
                    what[prop] = control.val();
                }    
            }        
        }        
    },
    

    
    saveSelect: function (select)
    {
        if (select.current === -1)
        {
            return;
        }
        
        this.saveObject('#' + select.id, select.options[select.current].data);
        
        var name = select.options[select.current].data.name;
        if (name !== select.options[select.current].text)
        {
            select.options[select.current].text = name;
        }
    },

    
    /**
     * Initialise l'�diteur
     * 
     * Cette m�thode est appell�e par jQuery.ready() une fois que la structure de la base a �t� re�ue.
     * On initialise les formulaires � partir des donn�es pr�sentes dans la structure et on s�lectionne
     * le premier champ de la base comme champ en cours.
     * 
     * @param {object} json un tableau contenant la structure de la base
     */
    init: function (db)
    {
        this.db=db;
        
        this.loadStructure();

        // Initialise la boite de dialogue utilis�e pour afficher les erreurs
        //jQuery('#errors').jqm({modal: true, overlay: 20}).jqDrag('.jqDrag').jqResize('.jqResize');
        
        // Boutons monter/descendre en-dessous des select
        jQuery('.moveup').click(
            function ()
            {
                SchemaEditor.moveOption(jQuery('select', jQuery(this).parent().parent()).get(0), -1);
            }
        );
        jQuery('.movedown').click(
            function ()
            {
                SchemaEditor.moveOption(jQuery('select', jQuery(this).parent().parent()).get(0), 1);
            }
        );
        jQuery('.addoption').click(
            function ()
            {
                SchemaEditor.addOption(jQuery('select', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.deleteoption').click(
            function ()
            {
                SchemaEditor.deleteOption(jQuery('select', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.addtr').click(
            function ()
            {
                SchemaEditor.addTr(jQuery('table', jQuery(this).parent().parent()).get(0));
            }
        );
        jQuery('.deletetr').click(
            function ()
            {
                SchemaEditor.deleteTr(jQuery(this).parents('tr:first').get(0));
            }
        );
        

    },

    /**
     * Ajoute une option dans un select 
     * 
     */
    addOption: function (select)
    {
        // Initialise les propri�t�s de l'option avec leurs valeurs par d�faut
        if (typeof SchemaEditor.defaults[select.id] === 'undefined')
        {
            return;
        }
            
        var data = SchemaEditor.clone(SchemaEditor.defaults[select.id]);
        
        // D�termine un nom qui ne soit pas d�j� utilis� pour l'option
        for (var i = 1, name = data.name + '1'; ; i++, name = data.name + i)
        {
            if (!jQuery('option[value=' + name + ']', select).length) 
            {
                break;
            }
        }
        data.name = name;

        // Cr�e une option, ajoute field comme expando de cette option et ajoute l'option au select
        var option = document.createElement('option');
        option.text = data.name;
        option.data = data;
        if (select.options.length) jQuery(option).hide();

        try // source : http://www.w3schools.com/htmldom/met_select_add.asp
        {
            select.add(option, null); // standards compliant
        }
        catch (exception)
        {
            select.add(option); // IE only
        }        

        // S�lectionne et affiche la nouvelle option
        option.selected = true;
        jQuery(select).change();
/*
le fait de faire un fadeIn sur l'option (pour que l'utilisateur voit l'option
ajout�e) pose probl�me lorsque la liste est vide et que c'est la premi�re option 
ajout�e (le select est r�duit � une ligne, le libell� de l'option n'appara�t pas
dans le select) donc on ne le fait que s'il existe d�j� des options dans le select
*/        
        jQuery(option).fadeIn(
            SchemaEditor.fxSpeed, 
            function ()
            {
                select.scrollTop = select.scrollHeight;
            }
        );

        jQuery('#' + select.id + '_rightpanel input:first').select().focus();
    },


    /**
     * Supprime une option d'un select
     */
    deleteOption: function (select, noconfirm)
    {
        var index = select.selectedIndex;

        // Sanity check
        if (index === -1)
        {
            return alert('Aucun �l�ment s�lectionn�');
        }
            
        // Demande confirmation
        if (noconfirm !== true)
        {
            if (!confirm('Supprimer ' + select.options[index].text + ' ?')) 
            {
                return;
            }
        }

        // Supprime l'option en cours
        var scrollTop = select.scrollTop;
        jQuery(select.options[index]).fadeOut(
            SchemaEditor.fxSpeed,
            function ()
            {
                select.remove(index);
                select.current = -1; // emp�che saveField de sauvegarder un champ qui n'existe plus
                
                // Si le champ supprim� n'�tait pas le dernier, on garde le m�me index
                // Sinon, on remonte d'un cran
                select.selectedIndex = index < select.options.length ? index : index - 1;
                jQuery(select).change();
                select.scrollTop = scrollTop;
            }
        );
    },

    
    /**
     * Monte ou descend un �l�ment dans une liste
     *
     * @param select select la liste sur laquelle il faut intervenir
     * @param int delta le nombre de positions (positif=le champ descend, n�gatif=le champ monte)
     */
    moveOption: function (select, delta)
    {
        // R�cup�re l'option en cours, exit si aucune
        var index = select.selectedIndex;
        if (index === -1) 
        {
            return;
        }

        // Calcule le nouvel index, exit si hors limites
        var newIndex = index + delta;
        if (newIndex < 0 || newIndex >= select.options.length) 
        {
            return;
        }
        
        // Supprime l'option et la r�ins�re � la nouvelle position
        var option = select.options[index];
        select.remove(index);
        select.add(option, select.options[newIndex]);

        // Met � jour current pour qu'il reste synchronis� 
        select.current = select.selectedIndex;
    },
    

    /**
     * Ajoute une ligne dans une table
     */
    addTr: function (table)
    {
        // l'id de la table est de la forme db_indices_fields
        // on va d�couper cet id pour obtenir :
        // - l'id du select (id) auquel cette table est rattach�e (db_indices)
        // - le nom de la propri�t� (prop) que cette table repr�sente (fields)
        var i = table.id.lastIndexOf('_');
        var id = table.id.substring(0, i);
        var prop = table.id.substring(i + 1);
        
        // On r�cup�re ensuite les donn�es associ�es � l'option en cours au sein
        // du select (data)
        var select = jQuery('#' + id).get(0);
        var data = select.options[select.current].data;

        // On sauvegarde toutes les zones �ventuellement modifi�es
        SchemaEditor.saveObject('#' + id, data);
        
        //  et on ajoute un �l�ment au tableau data[prop]
        if (typeof SchemaEditor.defaults[table.id] === 'undefined')
        {
            return;
        }
        
        data[prop].push(SchemaEditor.clone(SchemaEditor.defaults[table.id]));
        
        // Ensuite on demande un r�affichage de l'option
        SchemaEditor.loadObject('#' + id, data);
        jQuery('tr:last', table).hide().fadeIn(SchemaEditor.fxSpeed);
        jQuery('tr:last input:first', table).focus();
    },
    
    /**
     * Ajoute une ligne dans une table
     */
    deleteTr: function (tr)
    {
        // R�cup�re la table qui contient le tr � supprimer
        var table = jQuery(tr).parents('table:first').get(0);
        if (! table) 
        {
            return; 
            // bizarre... li� au fait qu'on est oblig� de r�appliquer le click() 
            // sur les .deletetr, du coup on est appell� deux fois, 
            // mais la 2nde fois, le tr n'a plus de parent
        }
        
        // R�cup�re l'id du select auquel est rattach�e cette table
        var id = table.id.substring(0, table.id.lastIndexOf('_'));
        
        // Supprime la tr
        jQuery(tr).remove();

        // Sauvegarde puis r�affiche tout
        jQuery('#' + id).change();
        
        // impossible de faire un fadeOut : on veut animer l'�l�ment qu'on
        // supprime et �a fait planter jQuery
    },
    
    /**
     * Change le nom du champ en cours
     */
    updateFieldName: function ()
    {
//        this.option.field.name = /*this.option.text=*/jQuery('#field_name').val();
    },
    
    setFieldClass: function (field, domOption)
    {
        var option = jQuery(domOption);
        option.removeClass();
        if (field.index.length)
        {
            option.addClass('index');
        }
        
        if (field.lookuptables.length)
        {
            option.addClass('entries');
        }
    },
    
    saveTo : function (url, params)
    {
        this.saveUrl = url;
        this.saveParams = params;
    },
     
    
    save : function ()
    {

        jQuery('select').change();
        var id='#db_';
        
        //
        for (var prop in this.db)
        {
            // Si c'est un tableau, on initialise le select correspondant
            if (this.db[prop] instanceof Array)
            {
                // Recherche le select correspondant
                var select = jQuery('select' + id + prop);
                
                // Si on ne le trouve pas, on le signale 
                if (select.length === 0)
                {
                    continue;
                }
                select=select.get(0);
                
                this.db[prop]=[];
                                
                // Pour chaque option du select, on ajoute un �l�ment au tableau
                for (var i=0; i<select.options.length; i++)
                {
                    this.db[prop][i]=select.options[i].data;
                }
            }

            // Sinon (valeur scalaire ou objet) on charge directement la propri�t�
            else
            {
                var propid = id + prop;
                
                // Recherche le champ dans le contexte indiqu�
                var control = jQuery(propid);
                
                // On signale si on ne le trouve pas
                if (control.length === 0)
                {
                    continue;
                }
                
                if (control.is(':checkbox'))
                {
                    this.db[prop] = (control.attr('checked') === true);
                }
                else
                {
                    this.db[prop] = control.val();
                }    
                
            }
        }

        // Masque la zone d'erreurs si elle est affich�e
        jQuery('#errors').hide();

        jQuery.ajax(
            {
                type: 'POST',
                url: this.saveUrl,
                data: jQuery.extend({schema: jQuery.toJSON(this.db)}, this.saveParams), /* clone(); */
                dataType: 'json',
                success: function (data)
                {
                    if (typeof(data) === 'string')
                    {
                        if (confirm("Aucune erreur d�tect�e, la structure de la base a �t� enregistr�e dans le fichier " + SchemaEditor.saveParams.file))
                        {
                            window.location = data;
                        }
                    }
                    else
                    {
                        var errors = '';
                        jQuery(data).each(function (i)
                        {
                            errors += '<li>' + this + '</li>';
                        });
                        jQuery('#errors ul').html(errors);
                        jQuery('#errors').show();
                    }
                }
            }
        );
        
    },
    
    load: function (data)
    {
        switch (typeof(data))
        {
        // Une chaine : on consid�re que c'est une url � partir de laquelle on va charger la structure 
        case 'string':
            jQuery.getJSON(
                data, 
                function (json) 
                {
                    SchemaEditor.init(json); 
                    jQuery('#SchemaEditor').fadeIn(SchemaEditor.fxSpeed);
                }
            );
            break;
            
        // Un objet : on nous a pass� directement la structure en param�tre
        case 'object':
            SchemaEditor.init(data);
            jQuery('#SchemaEditor').fadeIn(SchemaEditor.fxSpeed);
            break;
        default:
            alert('Impossible de d�terminer le type de donn�es pass� � SchemaEditor.load()');
        }
    },
    
    /**
     * Retourne un clone de l'objet pass� en param�tre.
     *
     * Si le param�tre est un scalaire, il est retourn� tel quel.
     * S'il s'agit d'un tableau, un nouveau tableau contenant des clones des
     * �l�ments du tableau d'origine est retourn�.
     * S'il s'agit d'un objet, un nouvel objet contenant des clones des 
     * propri�t�s de l'objet initial est retourn�.
     *
     * @param mixed what la valeur, le tableau ou l'objet � cloner
     * @return mixed
     */
    clone: function (what)
    {
        var result;
        
        if (what instanceof Array)
        {
            result = [];
        }
        else if (what instanceof Object)
        {
            result = {};
        }
        else
        {
            return what;
        }
        
        for (var i in what)
        {
            result[i] = this.clone(what[i]);
        }
    
        return result;
    }
};