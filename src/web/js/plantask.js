jQuery(document).ready(
function()
{
    /* 
        quand on clique sur "maintenant", d�s�lectionne le bouton "date 
        ult�rieure" et cache les contr�les associ�s
    */
    jQuery('#taskRunNow').click(function(){
        jQuery('#taskRunLater').get(0).checked=false;
        jQuery('#taskDateTime').hide('normal');
    }).click();

    /* 
        quand on clique sur "date ult�rieure", d�s�lectionne le bouton 
        "maintenant" et affiche les contr�les permettant de choisir la 
        date d'ex�cution.
    */
    jQuery('#taskRunLater').click(function(){
        jQuery('#taskRunNow').get(0).checked=false;
        jQuery('#taskDateTime').show('normal');
    });

    /*
        Affiche ou masque les contr�les permettant de choisir le filtre
        selon que le bouton "t�che p�riodique" est coch� ou non.
    */
    var f=function(){
        if (jQuery(this).is(':checked'))
            jQuery('#taskRepeatDetails').show('normal');
        else
            jQuery('#taskRepeatDetails').hide('normal');
    };
    f(); // ex�cute la fonction au d�marrage
    jQuery('#taskRepeat').click(f); // puis chaque fois qu'on clique
    
    /*
        Pour chaque p�riodicit� (heures, jours, mois), filters donne l'id de
        la div correspondante.
    */
    var filters=[];
    filters['min.']='#taskMinutes';
    filters['h.']='#taskHours';
    filters['j.']='#taskDays';
    filters['mois']='#taskMonthes';
    
    /*
        Affiche le bon filtre (choix des heures, choix des jours, etc.) en 
        fonction de la valeur choisie dans le select "p�riodicit�".
    */
    f=function(){
        for (var filter in filters) jQuery(filters[filter]).hide('normal');
        jQuery(filters[jQuery('#taskUnits').val()]).show('normal');
    }
    f(); // ex�cute la fonction au d�marrage
    jQuery('#taskUnits').change(f); // puis chaque fois que le select change

    /*
        Avant que le formulaire ne soit envoy� au serveur, initialise les
        deux champs hidden taskTime et taskRepeat en fonction de ce que
        l'utilisateur a choisit. 
    */
    jQuery('#taskRunNow').parents().find('form').eq(0).submit(function(){
        var timestamp=new Date();
        var repeat=null;
        
        if (jQuery('#taskRunLater').is(':checked'))
        {
            var date=jQuery('#taskDate').datepicker('getDate');
            if (date === null) date=new Date();
            date.setHours(0);
            date.setMinutes(0);
            date.setSeconds(0);
            date.setMilliseconds(0);
            
            timestamp=new Date(date.getTime() + jQuery('#taskTime').val()*1000);
        }

        if (jQuery('#taskRepeat').is(':checked'))
        {
            var unit=jQuery('#taskUnits').val();
            repeat='1 ' + unit;
            var filter=[];
            jQuery(filters[unit] + ' input[type="checkbox"]:checked').each(function(){
                filter.push(jQuery(this).val());
            });
            if (filter.length)
                repeat += '/' + filter.join(',');
        }

        // js travaille en millisecondes, php en secondes : on divise par 1000 
        timestamp=Math.floor(timestamp.getTime()/1000);
        
        jQuery('#taskTimeResult').val(timestamp);
        jQuery('#taskRepeatResult').val(repeat);
        return true;
    });
});