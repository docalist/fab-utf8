<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une r�ponse de type 'text/plain'.
 *
 * @package     fab
 * @subpackage  response
 */
class TextResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/plain';

    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';

    /**
     * Constructeur.
     *
     * Construit la r�ponse et ajoute un ent�te sp�cifique � IE8 pour le forcer � respecter
     * le Content-Type indiqu� ({@link
     * http://blogs.msdn.com/ie/archive/2008/07/02/ie8-security-part-v-comprehensive-protection.aspx
     * X-Content-Type-Options: nosniff}).
     */
    public function __construct()
    {
       parent::__construct();

       $this->setHeader('X-Content-Type-Options', 'nosniff'); // ne fonctionne qu'avec IE8+
       // Source : http://blogs.msdn.com/ie/archive/2008/07/02/ie8-security-part-v-comprehensive-protection.aspx
    }

}
