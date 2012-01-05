<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour la lecture de fichiers contenant des notices.
 *
 * <h2>Vue d'ensemble :</h2>
 *
 * Un objet <code>RecordReader</code> permet de {@link read() lire} des
 * {@link Record enregistrements} � partir d'un fichier.
 *
 * Chaque classe {@link RecordReader} fonctionne avec une classe {@link Record}
 * associ�e : la m�thode {@link read()} charge le prochain enregistrement et le
 * retourne sous la forme d'un objet <code>Record</code> du type correspondant.
 *
 * Par convention, le nom de la classe <code>Record</code> � utiliser est d�termin�
 * automatiquement � partir du nom de la classe <code>Reader</code>. Par exemple, la classe
 * {@link RamisReader} fonctionne avec la classe {@link RamisRecord} (le suffixe "Reader"
 * est supprim� et la chaine "Record" est ajout�e).
 *
 * Pour des cas particuliers, vous pouvez �galement indiquer explicitement le nom de la
 * classe <code>Record</code> � utiliser lors de la {@link __construct() construction}
 * de l'objet Reader.
 *
 * Un objet <code>Reader</code> peut charger successivement plusieurs fichiers. Si vous
 * avez un fichier unique � lire, vous pouvez le passer directement au
 * {@link __construct() constructeur} de la classe, sinon, vous pouvez appeller les m�thodes
 * {@link open()} et {@link close()} pour chacun des fichiers � charger.
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
 * <h2>Validit� des enregistrements :</h2>
 * La m�thode {@link read()} ne fait aucun test sur la validit� de l'enregistrement
 * lu et retourne tous les enregistrements qui figurent dans le fichier source.
 *
 * Il appartient � l'appellant de v�rifier la validit� de l'enregistrement, ce qui peut
 * �tre fait en utilisant la m�thode {@link Record::isAcceptable()} de l'objet
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
 * <h2>It�ration :</h2>
 * La classe <code>Record</code> impl�mente l'interface
 * {@link http://php.net/Iterator Iterator}, ce qui vous permet de l'utiliser, par exemple,
 * dans une boucle foreach() :
 *
 * <code>
 * foreach(new RamisReader('ramis.ajp') as $record)
 *     if ($record->isAcceptable()) echo $record;
 * </code>
 *
 * <code>
 * // Charge tout le contenu du fichier en m�moire
 * $records = iterator_to_array(new RamisReader('ramis.ajp'));
 * </code>
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class RecordReader implements Iterator
{
    /**
     * Path du fichier � charger.
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
     * Num�ro d'ordre de l'enregistrement en cours.
     *
     * @var int
     */
    protected $recordNumber = null;


    /**
     * Nom de la classe {@link Record} associ�e.
     *
     * @var string
     */
    protected $recordClassName = null;


    /**
     * Cr�e un nouveau reader.
     *
     * @param string $path (optionnel) path absolu du fichier � ouvrir.
     * Si <code>$path</code> est fourni, le fichier correspondant est {@link open() ouvert}.
     */
    public function __construct($path = null, $recordClassName = null)
    {
        if (is_null($recordClassName))
        {
            $recordClassName = get_class($this);
            if (substr($recordClassName, -6) !== 'Reader')
                throw new Exception('Le nom de la classe ne finit pas par "Reader", impossible de d�terminer le nom de la classe Record correspondante.');

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
     * Ouvre le fichier indiqu�.
     *
     * @param string $path
     */
    public function open($path)
    {
        // Cr�e l'enregistrement record
        if ($this->record === false) $this->record = new $this->recordClassName();

        // Ferme l'�ventuel fichier en cours
        $this->close();

        // V�rifie que le fichier indiqu� existe
        if (! file_exists($path))
            throw new Exception("$path : fichier non trouv�");

        // Stocke son path
        $this->path = $path;

        // Ouvre le fichier texte en lecture
        $this->file = fopen($path, 'rt');

        // R�-initialise le num�ro d'enregistrement
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
     * V�rifie qu'on a un fichier ouvert, g�n�re une exception si ce n'est pas le cas.
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
        return $this->record->copy(); // le copy() est n�cessaire pour iterator_to_array(), par exemple.
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