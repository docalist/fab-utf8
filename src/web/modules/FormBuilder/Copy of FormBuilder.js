$(document).ready(function(){
    var controls = 
    {
		textbox: 
		{
    		label: 'textbox'
		},
		p:
		{
    		label: 'p',
    		value: 'paragraphe'
		},
		div:
		{
    		label: 'div',
    		value: 'div'
		},
		span:
		{
    		label: 'span',
    		value: 'span'
		},
		textarea: 
		{
    		label: 'Zone de texte multiligne',
    		code: '<div class="item"><label>%label :</label><textarea></textarea></div>'
		},
		checkbox: 
		{
    		label: 'Case à cocher',
    		code: '<span class="item"><input type="checkbox" /><label>%label</label></span>'
		},
		radio: 
		{
    		label: 'Bouton radio',
    		code: '<input type="radio" /><label>%label</label>'
		},
/*		
		checklist: 
		{
    		label: 'liste de cases à cocher',
		},
		radiolist: 
		{
    		label: 'liste de boutons radio',
		},
*/		
		select: 
		{
    		label: 'Menu déroulant',
    		code: '<label>%label : </label><select><option>Option 1</option><option>Option 2</option></select>'
		}
    };
    
    // Noms des classes css utilisées : un ou plusieurs nom de classe séparés par un espace
    var css = {
		selected	: 'ui-state-highlight', // Elément sélectionné
		placeholder	: 'ui-state-active ui-formbuilder-placeholder' // Pour voir où on va dropper
    };
    
    // Charge les contrôles disponibles
    for (var name in controls)
    {
    	var item=controls[name];
    	item.widget = name;
    	
    	var button = $('<button>' + (item.label || name) + '</button>');
    	button.data('control', item);
    	button.appendTo('.ui-formbuilder-controls');
    }
    
    // Un item est sélectionné quand on clique dessus
    $('.fbcontainer, .fbcontainer .fbitem').live('click', function(event) {
    	// Désélectionne l'élément actuel
    	$('.ui-formbuilder-editor *').removeClass(css.selected);
    	
    	// Sélectionne l'élément
    	$(this).addClass(css.selected);
    	
    	// Insère le bouton "supprimer"
		if($(this).is('.ui-first'))
			$('#deleteItem').hide();
		else
			$(this).append($('#deleteItem').show());
		
		// Affiche les propriétés de l'item
    	showProperties($(this));
    	
    	// Empêche la propagation du click aux parents de l'item
    	return false;
    });

//    $('.fbcontainer, .fbcontainer .fbitem').attr('tabIndex', '0');
//    $('.fbcontainer input, .fbcontainer textarea').attr('tabIndex', '-1');
    
//    .focusin(function(){ // ne marche pas : sélectionne récursivement tous les parents
//    	$(this).triggerHandler('click');
//    });    

    // Initialement, c'est le 1er containeur trouvé qui est sélectionné (i.e. le formulaire)
    $('.fbcontainer:first').trigger('click');
    
    // Maque le message d'intro si le formulaire n'est pas vide
    if ($('.ui-first .fbitem').length)
    	$('.ui-formbuilder-intro').hide();
    
    // Les items peuvent être triés
    $('.fbcontainer').sortable({
    	connectWith: '.fbcontainer',	// Un item peut aller dans un autre container
    	placeholder: css.placeholder, 			  	// Preview du drop
    	opacity: 0.4,							  	// Permet de mieux voir où on va dropper
    	items: '.fbitem', 						  	// Un legend dans un fieldset, par ex. ne peut pas être déplacé
    	distance: 10, 							  	// Evite de commencer un drag'n drop quand on veut juste sélectionner un item
    	grid: [1, 10], 								// Améliore l'insertion au début ou à la fin d'un fieldset.
    	start: function(event, ui) { 				// Sélectionne l'item quand on commence un drag'n drop
    		ui.item.trigger('click');
    	}
    });
    
    // Ajout d'un nouvel item
    $('.ui-formbuilder-sidebar button').live('click', function(event){
		// Crée l'item à insérer
    	var item = $(createItem($(this)));
    	
    	// Détermine l'élément actuellement sélectionné
    	var selected = getSelected();
    	
    	// Si c'est un containeur, on ajoute le nouvel item à la fin
    	if (selected.is('.fbcontainer'))
    		selected.append(item);
    	
    	// Sinon, on ajoute le nouvel item après l'élément sélectionné
    	else
    		selected.after(item);
    	
        // Masque le message d'intro
    	$('.ui-formbuilder-intro').hide();
    });

    // Suppression d'un item
    $('#deleteItem').hover(
		function() { $(this).addClass('ui-state-hover'); },
		function() { $(this).removeClass('ui-state-hover'); }
	).click(function() {
		// Détermine l'élément en cours
		var selected = getSelected();
		
		// Détermine quel sera l'élément sélectionné après la suppression 
		var next = selected.next();
		if (next.length===0) next = selected.prev();
		if (next.length===0) selected.parent();
		
		// Met la crois "supprimer" ailleurs
		$('#deleteItem').hide().appendTo('body');
		
		// Supprime l'élément
		selected.remove();
		
		// Sélectionne l'élément suivant
		next.trigger('click');
		
	    // Affiche le message d'intro si le formulaire est vide
		if ($('.ui-first .fbitem').length===0)
			$('.ui-formbuilder-intro').slideDown('slow');
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
    // Retourne l'élément actuellement sélectionné
    function getSelected() {
    	return $('.ui-formbuilder-editor .' + css.selected.replace(/ /g, '.'));
    }
    
    function showProperties(e) {
    	$('#propFor').text(e.text());
    }
    
    function createItem(button){
    	var control = button.data('control');
    	
    	var placeholder = $('<fbitem><span>Chargement en cours...</span></fbitem>');
    	
    	jQuery.ajax({
            type: 'GET',
            url: 'Render',
            data: control,
            success: function(data){
    			data=$(data);
    			placeholder.html/*replaceWith*/(data); // Remplace le placeholder par le widget
    	    	data.trigger('click'); // Sélectionne l'item inséré
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                var msg = textStatus ? textStatus : errorThrown;
                alert('Une erreur est survenue lors de la création du contrôle : ' + msg);
            },
            dataType: 'html',
            timeout: 10 * 1000 // timeout des requêtes en millisecondes
        });
    	return placeholder;
/*    	
    	var control = button.data('control');
    	var code = control.code;
    	var matches=code.match(/%[a-z]+/gi);
    	for (var i in matches)
    		code = code.replace(matches[i], control[matches[i].substr(1)] || '');
    	
    	$(code).render=function(){console.log(this);};
    	$(code).render();
    	return code;
*/    	
    } 
});
