<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base abstraite pour toutes les interfaces d'import basée sur un format décrivant
 * les champs du fichier source.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class FormatBasedImport extends AbstractImport
{
    /**
     * Format de l'interface : liste des champs présents dans le fichier source.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * Vous pouvez aussi surcharger la méthode {@link loadFormat()} pour que celle-ci initialise
     * $format.
     *
     * La clé désigne le nom du champ dans le format source, la valeur désigne le nom du champ
     * dans le format destination. Si seule la valeur est indiquée, le même nom de champ est
     * utilisé pour les deux.
     *
     * Utilisez une étoile à la fin des noms de champ pour indiquer les champs articles. Ils seront
     * automatiquement convertis en tableaux.
     *
     * @var array
     */
    protected $format = array();

    /**
     * Pour les champs articles (marqués avec une étoile dans {@link $format}), séparateur utilisé
     * pour séparer les articles.
     *
     * @var string
     */
    protected $sep = ',';


    /**
     * Tableau listant les champs articles. Initialisé par loadFormat().
     *
     * @var array
     */
    protected $multiple=array();


    /**
     * Constructeur.
     *
     * Vérifie le format indiqué dans l'interface puis ouvre le fichier passé en paramètre.
     *
     * @param string $path path absolu du fichier à ouvrir.
     */
    public function __construct($path)
    {
        $this->loadFormat();
        parent::__construct($path);
    }


    /**
     * Charge et vérifie le format.
     */
    protected function loadFormat()
    {
        $result = array();

        foreach($this->format as $source=>$destination)
        {
            if (is_int($source)) $source = $destination;

            if (substr($source, -1) === '*' || substr($destination, -1) === '*' || isset($this->multiple[rtrim($destination, ' *')]))
            {
                $destination = rtrim($destination, ' *');
                $source = rtrim($source, ' *');

                $this->multiple[$source] = true;
                if ($destination) $this->multiple[$destination] = true;
            }

            if (isset($result[$source]))
                throw new Exception(__CLASS__ . " : Champ $source dupliqué dans le format");

            $result[$source] = $destination;
        }

        if (empty($result))
            throw new Exception(__CLASS__ . ' : Aucun format défini');

        $this->format = $result;
    }


    /**
     * Indique si le champ indiqué est multivalué ou non
     *
     * @param string $field
     */
    public function isMultiple($field)
    {
        return isset($this->multiple[$field]);
    }
}