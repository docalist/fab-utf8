/*
    Script permettant le suivi quasiment en temps r�el de l'ex�cution
    d'une t�che.
    
    Daniel M�nard, 30/01/2008
*/
jQuery(document).ready(
function()
{
    // L'objet jQuery repr�sentant la barre de progression
    var bar=null;
    
    // L'objet jQuery contenant le #updater �ventuellement pr�sent dans le source
    var updater=null;
    
    // Met � jour la page
    var update = function(data)
    {
        // Lors de l'initialisation, on est appell� sans param�tre
        if (data !== undefined)
        {
            // Remplace le span#updater existant par les donn�es re�ues
            updater=updater.replaceWith(data);
            
            // Si on n'a plus de span#updater, c'est que la t�che est termin�e
            updater=jQuery('#updater');
            if (updater.length!==1) 
            {
                if (bar) bar.hide();
                return;
            } 
        }
        
        // Met � jour la barre de progression
        progress();
        
        // Attend un peu et lance une requ�te ajax pour recommencer
        window.setTimeout(ajax, 1000);
        
    };
    
    // Lance une nouvelle requ�te ajax pour mettre � jour la page
    var ajax = function()
    {
        var url=updater.attr('url');
        if (url=='') return; // aucune url indiqu�e, c'est une erreur
        
        xhr=jQuery.ajax
        (
            {
                type: 'GET',
                url: url,
                success: update
            }
        );
    };
    
    // Met � jour la barre de progression, l'affiche ou la masque selon les cas
    var progress = function()
    {
        var step=parseInt(updater.attr('step'));
        var max=parseInt(updater.attr('max'));
        
        // Pas d'attribut max, max vide ou max �gal � z�ro : cache la barre
        if (max == 0)
        {
            if (bar !== null) bar.hide();
            return;
        }
        
        // Cr�e la barre de progression si ce n'est pas encore fait 
        if (bar === null)
        {
            bar=jQuery('<div id="progressbar"><div></div><span>100%</span></div>');            
            bar.insertAfter(updater);
        }

        
        var percent=Math.floor(100 * step/max);
        bar.find('span').text(step + '/' + max + ' (' + percent + ' %)');
//        bar.find('span').text(percent + ' %');
        bar.find('div').css('width', percent+'%')
        bar.show();
    };

    // Initialisation du bazar
    updater=jQuery('#updater');
    
    // Le source ne contient pas de span#updater : la t�che n'est pas en cours d'ex�cution, termin�
    if (updater.length !== 1) return;
    
    // Lance la mise � jour r�guli�re de la page
    update();
}
);