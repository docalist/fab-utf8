<?php

/*
 * TextTable objet d'acc�s � une table au format csv
 *
 * A partir d'un fichier format CSV , cr�er un objet qu'on peut parcourir comme un tableau :
 * impl�mente les interfaces Iterator
 *
 * @param $filePath pass� au constructeur est le chemin d'acc�s au fichier CVS � lire
 */
 class TextTable implements Iterator
 {

    private $file=false;        // Descripteur du fichier CVS pass� utilis� comme source de donn�es
    private $eof=true;          // True si eof atteint
    private $delimiter=null;    // d�limiteur du fichier csv
    private $enclosure=null;    // TODO: faire doc
    private $ignoreEmpty;       // true si on ignore les enregistrements "vides" (contenant seulement $delimiter, espaces ou $enclosure)
    private $header=null;       // La ligne d'ent�te
    private $record=null;       // L'enreg en cours
    private $line=0;            // Num�ro de la ligne en cours

    /*
     * @param $filePath string chemin d'acc�s au fichier
     * @param $delimiter char d�limiteur d'enregistrements (tabulation par d�faut)
     * @param $ignoreEmptyRecord bool indiquant si les enregistrements vides sont ignor�s (false par d�faut)
     */
    public function __construct($filePath, $ignoreEmptyRecord=true, $delimiter="\t", $enclosure='"')
    {
        $this->delimiter=$delimiter;
        $this->enclosure=$enclosure;
        $this->ignoreEmpty = $ignoreEmptyRecord;

        // todo : traiter les chemins relatifs

        if(! is_file($filePath))
            throw new Exception('Fichier non trouv�e : ' . $filePath);

        $this->file = @fopen($filePath, 'r'); // @ = pas de warning si file not found
        if ($this->file===false)
            throw new Exception('Table non trouv�e : '.$filePath);

        // Charge la ligne d'ent�te contenant les noms de champs
        $this->header=$this->getLine();
    }

    public function __destruct()
    {
        if($this->file)
        {
            fclose($this->file);
            $this->file=false;
            $this->line=0;
            $this->delimiter="\t";
            $this->enclosure='"';
            $this->ignoreEmpty=true;
        }
    }

    // Interface Iterator
    public function rewind()
    {
        $this->next();
    }

    public function current()
    {
        return $this->record;
    }

    public function key()
    {
        // return le num�ro de la ligne en cours
        return $this->line;
    }

    public function next()
    {
        // Charge la ligne suivante
        if (false === $this->record=$this->getLine()) return;
        $this->record=array_combine($this->header, $this->record);
        ++$this->line;
        if (!isset($this->header['line']))
            $this->record['line']=$this->line;
    }

    public function valid()
    {
        return !$this->eof;
    }

    /*
     * Lit une ligne dans le fichier en passant �ventuellement les lignes vides
     *
     * @return mixed false si eof ou fichier non ouvert, un tableau contenant la
     * ligne lue sinon
     */
    private function getLine()
    {
        $this->eof=false;
        for(;;)
        {
            if ((! $this->file) or (feof($this->file))) break;
            $t=fgetcsv($this->file, 1024, $this->delimiter, $this->enclosure);
            if ($t===false) break;
            if (! $this->ignoreEmpty) return $t;
            if (trim(implode('', $t), ' ')!=='') return $t;
        }
        $this->eof=true;
        return false;
    }


    /**
     * Charge le fichier texte indiqu� en param�tre et retourne un tableau repr�sentant le contenu
     * de la table.
     *
     * Les tables sont compil�es � la vol�e et stock�es dans le cache de l'application. Lors du
     * tout premier appel, le fichier texte est charg� puis est stock� sous la forme d'un script
     * php dans le cache. Lors des appels suivants, le fichier php est simplement inclus.
     *
     * Si le fichier d'origine est modifi�, la table est recompil�e automatiquement pour mettre �
     * jour la version stock�e en cache.
     *
     * Un cache de second niveau est �galement utilis� pour stocker les tables ouvertes. Lors du
     * premier appel, le fichier php en cache est charg�. Lors des appels suivants, les donn�es
     * d�j� charg�es sont simplement retourn�es � l'appellant.
     *
     * @param array $table le nom de la table � charger. Il peut s'agir du path exact de la table
     * relatif � la racine de l'application (Runtime::$root) ou d'un nom symbolique d�finit dans la
     * section <code><alias></code> de la configuration.
     *
     * @return array un tableau contenant les entr�es de la table.
     *
     * Les cl�s du tableau correspondent � la premi�re colonne de la table. Les valeurs contiennent
     * les colonnes restantes. Si la table ne contient que deux colonnes, la valeur est une chaine
     * de caract�res contenant la deuxi�me colonne. Sinon, la valeur est un tableau qui contient
     * toutes les colonnes sauf la premi�re.
     *
     * La cl� sp�ciale 'Table_Headers' est un tableau contenant les noms des colonnes de la table.
     */
    private static function getLookupTable($table)
    {
        // Liste des tables d�j� ouvertes
        static $tables = array();

        // Cas d'une table d�j� ouverte
        if (isset($tables[$table]))
            return $tables[$table];

        // Si la table indiqu�e est un alias, on le convertit
        $key = $table;
        $alias = Config::get('alias');
        if (isset($alias[$table])) $table = $alias[$table];
        $table = Utils::makePath(Runtime::$root, $table);

        // V�rifie que la table demand�e existe
        if (! file_exists($table))
            throw new Exception("Impossible de trouver la table '$table'");

        // Retourne la table depuis le cache si elle est � jour
        if ( Cache::has($table, filemtime($table)) )
            return $tables[$key] = require(Cache::getPath($table));

        // Ouvre le fichier
        $file = fopen($table, 'r');

        // Charge les ent�tes de colonne
        $data = fgetcsv($file, 1024, "\t");
        $result = array('Table_Headers' => $data);

        // Lit les donn�es
        while (false !== $data = fgetcsv($file, 1024, "\t"))
        {
            $code = array_shift($data);
            if (count($data)===1) $data = array_shift($data);
            $result[$code] = $data;
        }

        // Ferme le fichier
        fclose($file);

        // Stocke la table en cache (dans les deux)
        Cache::set
        (
            $table,
            sprintf
            (
                "<?php\n".
                "// Fichier g�n�r� automatiquement � partir de '%s'\n".
                "// Ne pas modifier.\n".
                "//\n".
                "// Date : %s\n\n".
                "return %s;\n",
                $table,
                @date('d/m/Y H:i:s'),
                var_export($result, true)
            )
        );
        $tables[$key] = $result;

        // retourne la table
        return $result;
    }


    /**
     * Recherche une entr�e dans un fichier texte repr�sentant une table de Lookup.
     *
     * @param string $table Nom de la table � utiliser.
     * Il peut s'agir du path exact de la table (relatif � Runtime::$root, la racine de
     * l'application), par exemple "/tables/pays.txt" ou d'un nom symbolique d�finit dans la
     * section <code><alias></code> de la configuration (exemple : "Pays").
     *
     * @param string $key La valeur recherch�e (exemple : "FRA").
     *
     * Remarque :
     * La valeur est toujours recherch�e dans la premi�re colonne de la table
     * (nomm�e "code", en g�n�ral) et la recherche est effectu�e en tenant compte de la casse des
     * caract�res.
     *
     * @param string $return Indique ce que la m�thode doit retourner si
     * elle trouve la valeur recherch�e.
     *
     * Par d�faut, lorsque $return vaut null, la m�thode retourne le contenu de la deuxi�me
     * colonne de la table (nomm� "label", en g�n�ral).
     *
     * Pour obtenir une autre colonne, indiquez son nom dans $return. Une erreur sera g�n�r�e si
     * la colonne indiqu�e n'existe pas dans la table (le nom est sensible � la casse).
     *
     * Vous pouvez �galement indiquer dans $return la valeur sp�ciale "*". Dans ce cas, la totalit�
     * de l'entr�e sera retourn�e sous la forme d'un tableau dont les cl�s correspondent aux ent�tes
     * de la table et dont les valeurs correspondent � l'entr�e trouv�e.
     *
     * @return string|array Si la valeur recherch�e a �t� trouv�e la m�thode retourne le contenu
     * du champ indiqu� dans $return. Sinon, elle retourne la valeur recherch�e ($key).
     */
    public static function lookup($table, $key, $return = null)
    {
        // Charge la table indiqu�e
        $table = self::getLookupTable($table);

        // Si la valeur recherche n'existe pas dans la table, retourne la valeur inchang�e
        if (! isset($table[$key])) return $key;
        $data = $table[$key];

        // Retourne le contenu de la seconde colonne si $return est null
        if (is_null($return))
        {
            if (is_scalar($data)) return $data;
            return $data[0];
        }

        // Reconstruit l'entr�e en utilisant les ent�tes de colonnes comme cl�s
        if (is_scalar($data))
            $data = array($key, $data);
        else
            array_unshift($data, $key);

        $data = array_combine($table['Table_Headers'], $data);

        // Retourne tous les champs si $return vaut '*'
        if ($return === '*')
                return $data;

        // G�n�re une erreur si la colonne demand�e n'existe pas
        if (! isset($data[$return]))
            throw new Exception("La colonne $return n'existe pas dans la table");

        // Retourne la colonne demand�e
        return $data[$return];
    }
}