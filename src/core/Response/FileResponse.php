<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une r�ponse dont le contenu provient d'un fichier existant.
 *
 * @package     fab
 * @subpackage  response
 */
class FileResponse extends Response
{
    /**
     * @inheritdoc
     */
    protected $contentType = 'application/octet-stream';


    protected $name = null;
    protected $download = false;

    /**
     * G�n�re une exception si vous essayez de d�finir un contenu pour une requ�te de ce type.
     *
     * @param mixed $content ignor�
     *
     * @throw Exception syst�matiquement.
     */
    public function setContent($content = null/* , $name=null, $download=false */)
    {
//        $this->name=$name;
//        $this->download = $download;
        //$this->setHeader(sprintf('Content-Disposition: %s; filename="%s"', $download ? 'attachment' : 'inline', $name));

        return parent::setContent($content);
        // Un path
//        if (is_string($content))
//        elseif(is_resource(content))
//        elseif($content instanceof Response)
    }

    /**
     * Envoie le contenu du fichier.
     *
     * @return Response $this
     */
    public function outputContent()
    {
        // Content contient le path d'un fichier
        if (is_string($this->content))
        {
            readfile($this->content);
        }

        // Content contient un handle de fichier d�j� ouvert (ou un stream)
        elseif(is_resource($this->content))
        {
            fpassthru($this->content);
        }

        // Content contient une r�ponse
        elseif($content instanceof Response)
        {
            $content->outputContent();
        }

        // Done
        return $this;
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
        $this->illegal(__FUNCTION__);
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