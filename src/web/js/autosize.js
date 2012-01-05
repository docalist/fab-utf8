/**
 * Script permettant aux textareas d'avoir une hauteur qui s'adapte automatiquement
 * au contenu.
 * 
 * Le script s'applique à toutes les textarea qui ont une classe "autoheight" ou qui
 * ont un ancêtre ayant la classe autoheight. 
 * 
 * jQuery est requis.
 * 
 * Adapté de http://javascriptly.com/examples/jquery-grab-bag/autogrow-textarea.html
 * 
 */
jQuery(document).ready
(
    function()
    {
        jQuery('textarea.autoheight, .autoheight textarea').each
        (
            function()
            {
                var $this       = $(this),
                    minHeight   = $this.height(),
                    lineHeight  = $this.css('lineHeight');
                
                var shadow = $('<div></div>').css
                (
            		{
	                    position:   'absolute',
	                    top:        -10000,
	                    left:       -10000,
	                    width:      $(this).width(),
	                    fontSize:   $this.css('fontSize'),
	                    fontFamily: $this.css('fontFamily'),
	                    lineHeight: $this.css('lineHeight'),
	                    resize:     'none'
            		}
        		).appendTo(document.body);
                
                var last=null;
                var update = function()
                {
                	if (this.value === last) return;
                	last = this.value;
                    var val = this.value.replace(/</g, '&lt;')
                                        .replace(/>/g, '&gt;')
                                        .replace(/&/g, '&amp;')
                                        .replace(/\n/g, '<br/>');

                    val += 'X'; // force le dernier BR éventuel a être pris en compte, sinon il est ignoré
                    shadow.html(val);
                    $(this).css('height', Math.max(shadow.height(), minHeight));
                }
                
                $(this)/*.css({overflow: 'hidden'})*/.change(update).keyup(update).keydown(update);
                
                update.apply(this);
            }
        );
    }
);