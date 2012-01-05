<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une redirection temporaire ou permanente.
 *
 * @package     fab
 * @subpackage  response
 */
class RedirectResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'text/html';


    /**
     * @inheritdoc
     */
    protected $charset = 'ISO-8859-1';


    /**
     * @inheritdoc
     */
    protected $status = 302; // 'Moved Temporarily' en HTTP 1.0


    /**
     * Url vers laquelle cette r�ponse redirgera l'utilisateur.
     *
     * @var string
     */
    protected $location = null;


    /**
     * Construit la redirection.
     *
     * @param string|Request|null $location l'adresse vers laquelle l'utilisateur sera redirig�e.
     * Il peut s'agir d'une url relative ou absolue, d'une fab-url ou d'un objet Request.
     * Si vous n'indiquez pas d'adresse, le contenu de la vairable d'environnement HTTP_REFERER
     * est utilis�e mais si celle-ci est vide, une exception est g�n�r�e.
     *
     * @param bool $permanent par d�faut la requ�te g�n�re une redirection temporaire (302). Si vous
     * indiquez true pour ce param�tre, une redirection permanente (301) sera g�n�r�e � la place.
     *
     * @throw Exception si aucune url n'a �t� indiqu�e et que HTTP_REFERER est vide.
     */
    public function __construct($location = null, $permanent = false)
    {
        // Laisse les anc�tres faire leur boulot
        parent::__construct();

        // Si aucune url n'a �t� indiqu�e, utilise le referrer
        if (! $location)
        {
            if (isset($_SERVER['HTTP_REFERER']))
                $location = $_SERVER['HTTP_REFERER'];
            else
                throw new Exception('Impossible de d�terminer l\'url de redirection');
        }

        // Route l'adresse en url absolue
        else
            $location = Routing::linkFor($location, true);

        // Change le statut par d�faut si c'est une redirection permanente
        if ($permanent)
            $this->status = 301;

        // Ajoute l'ent�te de redirection
        $this->setHeader('Location', $location);

        // D�finit le template qui sera utilis� pour g�n�rer la r�ponse
        parent::setTemplate
        (
            $this,
            dirname(__FILE__) . '/RedirectResponse.html',
            array
            (
                'location'=>$location,
                'status' => $this->status,
                'reason' => self::$httpStatus[$this->status]
            )
        );
    }


    /**
     * G�n�re une exception si vous essayez de d�finir un contenu pour une requ�te de ce type.
     *
     * @param mixed $content ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function setContent($content = null)
    {
        $this->illegal();
    }


    /**
     * G�n�re une exception si vous essayez de d�finir un contenu pour une requ�te de ce type.
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
     * G�n�re une exception si vous essayez de d�finir un contenu pour une requ�te de ce type.
     *
     * @param mixed $content ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function appendContent($content)
    {
        $this->illegal();
    }

    /**
     * G�n�re une exception si vous essayez de d�finir un contenu pour une requ�te de ce type
     *
     * @param object $context ignor�
     * @param string $path ignor�
     * @param mixed $data ... ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function setTemplate($context, $path, $data=null)
    {
        $this->illegal();
    }
}