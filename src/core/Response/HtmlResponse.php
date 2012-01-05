<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une r�ponse Html.
 *
 * @package     fab
 * @subpackage  response
 */
class HtmlResponse extends LayoutResponse
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/html';

    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';

}