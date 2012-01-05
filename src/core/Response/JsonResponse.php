<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une r�ponse de type JSon.
 *
 * @package     fab
 * @subpackage  response
 */
class JsonResponse extends TextResponse
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'application/json';


    /**
     * @inheritdoc
     */
    protected $charset = 'UTF-8';


    /**
     * Constructeur.
     *
     * Construit la r�ponse JSON et ajoute automatiquement les ent�tes http requis pour
     * emp�cher que les r�sultats de la requ�te ne soient mis en cache par IE.
     */
    public function __construct()
    {
       parent::__construct();

       $this->setHeader('Pragma', 'No-cache');
       $this->setHeader('Cache-Control', 'No-cache, Must-revalidate');
       $this->setHeader('Expires', gmdate('D, d M Y H:i:s').' GMT');
    }


    /**
     * D�finit la valeur qui sera g�n�r�e dans la r�ponse Json.
     *
     * @param mixed $value la valeur � retourner dans la r�ponse
     *
     * @return Response $this
     */
    public function setContent($value = null)
    {
        $this->content = json_encode(Utils::utf8Encode($value));

        return $this;
    }


    /**
     * G�n�re une exception si vous essayez de modifier le content JSon de la r�ponse.
     *
     * @param mixed $content ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function prependContent($content)
    {
        $this->illegal();
    }


    /**
     * G�n�re une exception si vous essayez de modifier le content JSon de la r�ponse.
     *
     * @param mixed $content ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function appendContent($content)
    {
        $this->illegal();
    }
}
