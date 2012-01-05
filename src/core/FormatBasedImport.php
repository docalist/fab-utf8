<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base abstraite pour toutes les interfaces d'import bas�e sur un format d�crivant
 * les champs du fichier source.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class FormatBasedImport extends AbstractImport
{
    /**
     * Format de l'interface : liste des champs pr�sents dans le fichier source.
     *
     * Cette propri�t� est destin�e � �tre surcharg�e par les classes descendantes.
     *
     * Vous pouvez aussi surcharger la m�thode {@link loadFormat()} pour que celle-ci initialise
     * $format.
     *
     * La cl� d�signe le nom du champ dans le format source, la valeur d�signe le nom du champ
     * dans le format destination. Si seule la valeur est indiqu�e, le m�me nom de champ est
     * utilis� pour les deux.
     *
     * Utilisez une �toile � la fin des noms de champ pour indiquer les champs articles. Ils seront
     * automatiquement convertis en tableaux.
     *
     * @var array
     */
    protected $format = array();

    /**
     * Pour les champs articles (marqu�s avec une �toile dans {@link $format}), s�parateur utilis�
     * pour s�parer les articles.
     *
     * @var string
     */
    protected $sep = ',';


    /**
     * Tableau listant les champs articles. Initialis� par loadFormat().
     *
     * @var array
     */
    protected $multiple=array();


    /**
     * Constructeur.
     *
     * V�rifie le format indiqu� dans l'interface puis ouvre le fichier pass� en param�tre.
     *
     * @param string $path path absolu du fichier � ouvrir.
     */
    public function __construct($path)
    {
        $this->loadFormat();
        parent::__construct($path);
    }


    /**
     * Charge et v�rifie le format.
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
                throw new Exception(__CLASS__ . " : Champ $source dupliqu� dans le format");

            $result[$source] = $destination;
        }

        if (empty($result))
            throw new Exception(__CLASS__ . ' : Aucun format d�fini');

        $this->format = $result;
    }


    /**
     * Indique si le champ indiqu� est multivalu� ou non
     *
     * @param string $field
     */
    public function isMultiple($field)
    {
        return isset($this->multiple[$field]);
    }
}