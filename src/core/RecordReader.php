<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour la lecture de fichiers contenant des notices.
 *
 * <h2>Vue d'ensemble :</h2>
 *
 * Un objet <code>RecordReader</code> permet de {@link read() lire} des
 * {@link Record enregistrements} à partir d'un fichier.
 *
 * Chaque classe {@link RecordReader} fonctionne avec une classe {@link Record}
 * associée : la méthode {@link read()} charge le prochain enregistrement et le
 * retourne sous la forme d'un objet <code>Record</code> du type correspondant.
 *
 * Par convention, le nom de la classe <code>Record</code> à utiliser est déterminé
 * automatiquement à partir du nom de la classe <code>Reader</code>. Par exemple, la classe
 * {@link RamisReader} fonctionne avec la classe {@link RamisRecord} (le suffixe "Reader"
 * est supprimé et la chaine "Record" est ajoutée).
 *
 * Pour des cas particuliers, vous pouvez également indiquer explicitement le nom de la
 * classe <code>Record</code> à utiliser lors de la {@link __construct() construction}
 * de l'objet Reader.
 *
 * Un objet <code>Reader</code> peut charger successivement plusieurs fichiers. Si vous
 * avez un fichier unique à lire, vous pouvez le passer directement au
 * {@link __construct() constructeur} de la classe, sinon, vous pouvez appeller les méthodes
 * {@link open()} et {@link close()} pour chacun des fichiers à charger.
 *
 * <h2>Exemple d'utilisation : </h2>
 * <code>
 * // Chargement d'un fichier unique
 * $reader = new RamisReader('ramis.ajp');
 * while (false !== $record = $reader->read()) echo $record;
 *
 * // Chargement de plusieurs fichiers
 * $reader = new RamisReader();
 * foreach(array('ramis1.ajp', 'ramis2.ajp') as $file)
 * {
 *     $reader->open($file);
 *     while (false !== $record = $reader->read()) echo $record;
 *     $reader->close();
 * }
 * </code>
 *
 * <h2>Validité des enregistrements :</h2>
 * La méthode {@link read()} ne fait aucun test sur la validité de l'enregistrement
 * lu et retourne tous les enregistrements qui figurent dans le fichier source.
 *
 * Il appartient à l'appellant de vérifier la validité de l'enregistrement, ce qui peut
 * être fait en utilisant la méthode {@link Record::isAcceptable()} de l'objet
 * {@link Record} :
 *
 * <code>
 * // Ignore les enregistrements invalides
 * $reader = new RamisReader('ramis.ajp');
 * while (false !== $record = $reader->read())
 * {
 *     if ($record->isAcceptable()) echo $record;
 * }
 * </code>
 *
 * <h2>Itération :</h2>
 * La classe <code>Record</code> implémente l'interface
 * {@link http://php.net/Iterator Iterator}, ce qui vous permet de l'utiliser, par exemple,
 * dans une boucle foreach() :
 *
 * <code>
 * foreach(new RamisReader('ramis.ajp') as $record)
 *     if ($record->isAcceptable()) echo $record;
 * </code>
 *
 * <code>
 * // Charge tout le contenu du fichier en mémoire
 * $records = iterator_to_array(new RamisReader('ramis.ajp'));
 * </code>
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class RecordReader implements Iterator
{
    /**
     * Path du fichier à charger.
     *
     * @var string
     */
    protected $path = '';


    /**
     * Handle du fichier en cours.
     *
     * @var resource
     */
    protected $file = null;


    /**
     * L'enregistrement lu par getRecord().
     *
     * La valeur <code>false</false> indique eof.
     *
     * @var Record
     */
    protected $record = false;


    /**
     * Numéro d'ordre de l'enregistrement en cours.
     *
     * @var int
     */
    protected $recordNumber = null;


    /**
     * Nom de la classe {@link Record} associée.
     *
     * @var string
     */
    protected $recordClassName = null;


    /**
     * Crée un nouveau reader.
     *
     * @param string $path (optionnel) path absolu du fichier à ouvrir.
     * Si <code>$path</code> est fourni, le fichier correspondant est {@link open() ouvert}.
     */
    public function __construct($path = null, $recordClassName = null)
    {
        if (is_null($recordClassName))
        {
            $recordClassName = get_class($this);
            if (substr($recordClassName, -6) !== 'Reader')
                throw new Exception('Le nom de la classe ne finit pas par "Reader", impossible de déterminer le nom de la classe Record correspondante.');

            $recordClassName = substr($recordClassName, 0, -6) . 'Record';
        }
        $this->recordClassName = $recordClassName;
        if (! is_null($path)) $this->open($path);
    }


    /**
     * Destructeur.
     *
     * Ferme le fichier en cours.
     */
    public function __destruct()
    {
        $this->close();
        unset($this->record);
    }


    /**
     * Ouvre le fichier indiqué.
     *
     * @param string $path
     */
    public function open($path)
    {
        // Crée l'enregistrement record
        if ($this->record === false) $this->record = new $this->recordClassName();

        // Ferme l'éventuel fichier en cours
        $this->close();

        // Vérifie que le fichier indiqué existe
        if (! file_exists($path))
            throw new Exception("$path : fichier non trouvé");

        // Stocke son path
        $this->path = $path;

        // Ouvre le fichier texte en lecture
        $this->file = fopen($path, 'rt');

        // Ré-initialise le numéro d'enregistrement
        $this->recordNumber = 0;
    }


    /**
     * Ferme le fichier en cours
     */
    public function close()
    {
        if ($this->file)
        {
            fclose($this->file);
            $this->file = null;
        }
    }


    /**
     * Vérifie qu'on a un fichier ouvert, génère une exception si ce n'est pas le cas.
     *
     * @throws Exception
     */
    public function checkFile()
    {
        if (is_null($this->file))
            throw new Exception('Pas de fichier en cours');
    }


    /**
     * Retourne l'enregistrement en cours.
     *
     * @return false|Record un objet {@link Record} contenant l'enregistrement en cours ou
     * <code>false</code> s'il n'y a pas d'enregistrement courant (fin de fichier).
     */
    public function getRecord()
    {
        return $this->record;
    }


    /**
     * Lit le prochain enregistrement du fichier.
     *
     * @return false|Record un objet {@link Record} contenant l'enregistrement lu ou
     * <code>false</code> si la fin de fichier est atteinte.
     */
    abstract public function read();


    // ---------------------------------------------------------------------------------------------
    // Interface Iterator
    // ---------------------------------------------------------------------------------------------

    public function rewind()
    {
        $this->checkFile();
        fseek($this->file, 0);
        if ($this->record === false) $this->record = new $this->recordClassName();
        $this->read();
    }

    public function current()
    {
        return $this->record->copy(); // le copy() est nécessaire pour iterator_to_array(), par exemple.
    }

    public function key()
    {
        return $this->recordNumber;
    }

    public function next()
    {
        $this->read();
    }

    public function valid()
    {
        return $this->record !== false;
    }
}