/**
   Url � appeller pour r�cup�rer les valeurs de lookup.
   
   Par d�faut, appelle l'action ThesoLookup du module en cours. Marche
   bien si le module en cours est un descendant de ThesaurusModule mais ne
   fonctionne pas si on appelle ThesoLookup par exemple � partir de /Base.
   La variable ThesoLookupUrl permet dans ce cas de d�finir une url absolue
   Exemple : ThesoLookupUrl='{Routing::linkFor("/ThesaurusModule/ThesoLookup")}'
*/ 
var ThesoLookupUrl='ThesoLookup';

// fonction appell�e par rpc quand on re�oit les donn�es envoy�es par thesolookup
function ThesoLookup(popup)
{
    jQuery('li', popup).each(function(){
        jQuery(this)
        .click(function(event){
            jQuery.AutoCompleteHandler.set(jQuery(this).attr('term'));
        })
        .attr('title', 'Utiliser "' + jQuery(this).attr('term') + '"');
    });

    jQuery('a', popup).each(function(){
        jQuery(this)
        .click(function(event){
            var term=jQuery(this).text();
            term=term.replace(/[\[\]]/g, '');
            term = '[' + term + ']';
            
            popup.load(ThesoLookupUrl+'?Fre='+escape(term), null, jQuery.AutoCompleteHandler.gotResult);
            event.stopPropagation();
            event.preventDefault();
            jQuery.AutoCompleteHandler.target.focus();
        })
        .attr('title', 'Afficher "' + jQuery(this).text() + '"')
        .attr('href','#');
    });
}