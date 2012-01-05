<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base pour les Reader qui travaillent sur des fichiers au format CSV.
 *
 * Par défaut, ce reader lit des fichiers CSV dans lequel les champs sont séparés par
 * des tabulations et encadrés par des guillemets. Dans les classes descendantes, vous
 * pouvez modifier ce comportement en surchargeant les propriétés {@link $delimiter}
 * et {@link $enclosure}.
 *
 * Le fichier CSV doit contenir une ligne d'entête indiquant les noms des colonnes.
 *
 * Une exception sera générée si le fichier CSV contient des noms de colonnes qui ne sont
 * pas reconnues par l'objet {@link Reader} associé. Pour ne pas générer d'exception,
 * surchargez la propriété {@link $ignoreUnknownFields} et initialisez-la à
 * <code>true</code>.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */
class CsvReader extends TextFileRecordReader
{
    /**
     * Délimiteur utilisé entre les différentes colonnes (tabulation par défaut).
     *
     * @var string
     */
    protected $delimiter = "\t";


    /**
     * Caractère utilisé pour délimiter le contenu de chaque champ (guillemet
     * double par défaut).
     *
     * @var string
     */
    protected $enclosure = '"';


    /**
     * Liste des champs définis dans la ligne d'entête du fichier.
     *
     * <code>$headers</code> ne contient que les champs pour lesquels la méthode
     * {@link Record::isField() isField()} de l'objet {@link Record} associé
     * retourne <code>true</code>.
     *
     * <code>$headers</code> sert également de tableau d'indirection : pour chaque
     * numéro de colonne, la valeur associée contient le nom du champ de l'objet
     * {@link Record}  associé qui recevra les données présentes dans cette colonne.
     *
     * @var array
     */
    protected $headers = null;


    /**
     * Indique s'il faut générer une erreur si la ligne d'entête du fichier contient
     * des noms de champs qui n'existent pas dans l'objet {@link Record} associé.
     *
     * Lorsque <code>$ignoreUnknownFields</code> est à <code>true</code>, une Exception
     * est générée si le fichier CSV contient des champs inconnus.
     *
     * @var bool
     */
    protected $ignoreUnknownFields = false;


    /**
     * Vérifie de façon strict le nombre de colonnes (de valeurs) obtenues pour chaque
     * ligne.
     *
     * Quand ce flag est à true, si une ligne contient plus de colonnes ou moins de
     * colonnes que la ligne d'entête, une exception est générée.
     * @var unknown_type
     */
    protected $strictColumnsCount = true;


    /**
     * Lors de l'ouverture d'un fichier, charge la ligne d'entête contenant
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
     * Surcharge la méthode rewind() héritée de TextFileRecordReader pour passer
     * la ligne d'entête contenant les noms des champs à chaque fois que le
     * fichier est "rembobiné".
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
        // Vérifications
        $this->checkFile();
        if (! $this->record) return false;

        // Lit la ligne suivante du fichier CSV
        if( false === $line = $this->getLine()) return $this->record = false;

        if (count($line) !== count($this->headers))
        {
            if (! $this->strictColumnsCount)
                return $this->fileError('nombre de colonnes incorrect (%d trouvées, %d attendues)', count($line), count($this->headers));

            if (count($line) !== count($this->headers))
                $line = array_pad($line, count($this->headers), '');
        }

        // Initialise l'enregistrement
        $this->record->clear();
        foreach($line as $col => $content)
            $this->record->store($this->headers[$col], $content);

        // Met à jour le numéro d'enreg
        ++$this->recordNumber;

        // Terminé.
        return $this->record;
    }
}