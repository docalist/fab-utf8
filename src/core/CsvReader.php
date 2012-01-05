<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les Reader qui travaillent sur des fichiers au format CSV.
 *
 * Par d�faut, ce reader lit des fichiers CSV dans lequel les champs sont s�par�s par
 * des tabulations et encadr�s par des guillemets. Dans les classes descendantes, vous
 * pouvez modifier ce comportement en surchargeant les propri�t�s {@link $delimiter}
 * et {@link $enclosure}.
 *
 * Le fichier CSV doit contenir une ligne d'ent�te indiquant les noms des colonnes.
 *
 * Une exception sera g�n�r�e si le fichier CSV contient des noms de colonnes qui ne sont
 * pas reconnues par l'objet {@link Reader} associ�. Pour ne pas g�n�rer d'exception,
 * surchargez la propri�t� {@link $ignoreUnknownFields} et initialisez-la �
 * <code>true</code>.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */
class CsvReader extends TextFileRecordReader
{
    /**
     * D�limiteur utilis� entre les diff�rentes colonnes (tabulation par d�faut).
     *
     * @var string
     */
    protected $delimiter = "\t";


    /**
     * Caract�re utilis� pour d�limiter le contenu de chaque champ (guillemet
     * double par d�faut).
     *
     * @var string
     */
    protected $enclosure = '"';


    /**
     * Liste des champs d�finis dans la ligne d'ent�te du fichier.
     *
     * <code>$headers</code> ne contient que les champs pour lesquels la m�thode
     * {@link Record::isField() isField()} de l'objet {@link Record} associ�
     * retourne <code>true</code>.
     *
     * <code>$headers</code> sert �galement de tableau d'indirection : pour chaque
     * num�ro de colonne, la valeur associ�e contient le nom du champ de l'objet
     * {@link Record}  associ� qui recevra les donn�es pr�sentes dans cette colonne.
     *
     * @var array
     */
    protected $headers = null;


    /**
     * Indique s'il faut g�n�rer une erreur si la ligne d'ent�te du fichier contient
     * des noms de champs qui n'existent pas dans l'objet {@link Record} associ�.
     *
     * Lorsque <code>$ignoreUnknownFields</code> est � <code>true</code>, une Exception
     * est g�n�r�e si le fichier CSV contient des champs inconnus.
     *
     * @var bool
     */
    protected $ignoreUnknownFields = false;


    /**
     * V�rifie de fa�on strict le nombre de colonnes (de valeurs) obtenues pour chaque
     * ligne.
     *
     * Quand ce flag est � true, si une ligne contient plus de colonnes ou moins de
     * colonnes que la ligne d'ent�te, une exception est g�n�r�e.
     * @var unknown_type
     */
    protected $strictColumnsCount = true;


    /**
     * Lors de l'ouverture d'un fichier, charge la ligne d'ent�te contenant
     * les noms des champs.
     *
     * @param string $path
     */
    public function open($path)
    {
        parent::open($path);

        $this->headers = $this->getLine();

        $bad = array();
        foreach($this->headers as & $field)
        {
            if (! $this->record->isField($field))
            {
                $bad[] = $field;
                $field = false;
            }
        }

        if ($bad && ! $this->ignoreUnknownFields)
            $this->fileError("Champs inconnus : %s", implode(', ', $bad));
    }

    /**
     * Surcharge la m�thode rewind() h�rit�e de TextFileRecordReader pour passer
     * la ligne d'ent�te contenant les noms des champs � chaque fois que le
     * fichier est "rembobin�".
     */
    public function rewind()
    {
        $this->checkFile();
        fseek($this->file, 0);
        $this->getLine();
        if ($this->record === false) $this->record = new $this->recordClassName();
        $this->read();
    }


    /**
     * Lit la ligne suivante du fichier.
     *
     * @return array|false
     */
    protected function getLine($ignoreEmpty = true)
    {
        for(;;)
        {
            if (feof($this->file)) return false;
            ++$this->line;
            $line = fgetcsv($this->file, 20*1024, $this->delimiter, $this->enclosure);
            if ($line !== array(null) || ! $ignoreEmpty)
            {
                $empty = true;
                foreach($line as &$field)
                {
                    $field = trim($field);
                    if (empty($field)) continue;
                    $empty = false;
                    if ($this->charset !== 'ISO-8859-1')
                        $field = $this->convertCharset($field, $this->charset);
                }
                if (! $empty || ! $ignoreEmpty) return $line;
            }
        }
    }


    /**
     * Lit le prochain enregistrement du fichier.
     *
     * @return false|Record un tableau contenant l'enregistrement lu ou <code>false</code>
     * si la fin de fichier est atteinte.
     */
    public function read()
    {
        // V�rifications
        $this->checkFile();
        if (! $this->record) return false;

        // Lit la ligne suivante du fichier CSV
        if( false === $line = $this->getLine()) return $this->record = false;

        if (count($line) !== count($this->headers))
        {
            if (! $this->strictColumnsCount)
                return $this->fileError('nombre de colonnes incorrect (%d trouv�es, %d attendues)', count($line), count($this->headers));

            if (count($line) !== count($this->headers))
                $line = array_pad($line, count($this->headers), '');
        }

        // Initialise l'enregistrement
        $this->record->clear();
        foreach($line as $col => $content)
            $this->record->store($this->headers[$col], $content);

        // Met � jour le num�ro d'enreg
        ++$this->recordNumber;

        // Termin�.
        return $this->record;
    }
}