<?php
/**
 * @package     fab
 * @subpackage  helpers
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une table de r�f�rence.
 *
 * Une table de r�f�rence (�galement appell�e liste d'autorit�, table de correspondance, etc.)
 * est une structure de donn�es qui permet d'associer des valeurs entre elles. Par exemple,
 * une table des codes pays permettra d'associer un code ISO (FRA, DEU...) � un libell�
 * (France, Allemagne...).
 *
 * Une table de r�f�rence peut comporter une, deux ou plusieurs colonnes. Une table contenant
 * une seule colonne permet de d�finir un ensemble de valeurs autoris�es. Une table a deux
 * colonnes se comporte comme un tableau associatif qui pour chaque entr�e associe une valeur
 * � une cl�. Une table avec plus de deux colonnes permet de stocker des informations
 * suppl�mentaires (pour la table des pays, par exemple, on pourrait stocker le code ISO sur
 * 3 lettres, le code ISO sur deux lettres, le nom du pays en fran�ais et en anglais, le nom
 * de la capitale, etc.)
 *
 * La classe <code>ReferenceTable</code> peut aussi �tre utilis�e pour stocker d'autres types
 * d'informations : nombre de hits sur une page donn�e, comptes utilisateurs, logs, etc.
 *
 * Il existe deux types de tables de r�f�rence : les tables au format texte et les tables au
 * format {@link http://www.sqlite.org/ SQLite}.
 *
 * Les tables au format texte sont de simples fichier texte (extension .txt) dans lesquels les
 * donn�es sont stock�es au {@link http://fr.wikipedia.org/wiki/Format_TSV format texte tabul�}.
 * Les tables de ce type sont des tables d'autorit� ferm�es qui ne peuvent pas �tre modifi�es
 * (sauf en modifiant le fichier texte d'origine).
 *
 * Le fichier texte contient une ligne d'ent�tes qui d�signe les noms des diff�rentes colonnes
 * (ou champs) de la table. Les donn�es viennent ensuite, � raison d'une entr�e par ligne,
 * chaque champ apparaissant dans le m�me ordre que la ligne d'ent�te et s�par� des autres
 * champs par un caract�re tabulation.
 *
 * La ligne d'ent�te indique pour chaque colonne le nom du champ associ� et peut indiquer entre
 * parenth�ses un ou plusieurs param�tres s�par�s par une virgule.
 *
 * Exemple :
 * <code>
 * Code (TEXT, PRIMARY KEY) Label (TEXT)
 * FRA                      France
 * DEU                      Allemagne
 * </code>.
 *
 * Les {@link http://www.sqlite.org/lang_createtable.html param�tres} permettent d'indiquer :
 * - Le {@link http://www.sqlite.org/datatype3.html type des donn�es} stock�es dans le champ :
 *   <code>INTEGER</code>, <code>FLOAT</code>, <code>REAL</code>, <code>NUMERIC</code>,
 *   <code>BOOLEAN</code>, <code>TIME</code>, <code>DATE</code>, <code>TIMESTAMP</code>,
 *   <code>VARCHAR</code>, <code>NVARCHAR</code>, <code>TEXT</code> ou <code>BLOB</code> ;
 * - Le type d'indexation a appliquer � ce champ : <code>INDEX</code>, <code>PRIMARY KEY</code>,
 *   <code>UNIQUE</code> ;
 * - L'acceptation ou non des valeurs NULL : <code>NOT NULL</code> ;
 * - Une valeur par d�faut : <code>DEFAULT "xxx"</code> ;
 * - Une s�quence de collation : <code>COLLATE BINARY</code>, <code>COLLATE NOCASE</code>,
 *   <code>COLLATE RTRIM</code> ;
 *
 * Si vous n'indiquez aucun param�tre pour un champ, celui-ci est cr�� avec les options
 * <code>TEXT, INDEX, COLLATE NOCASE</code> : le type par d�faut d'un champ est
 * <code>TEXT</code>, par d�faut, il est index�, et les comparaisons se font sans tenir compte
 * de la casse des caract�res.
 *
 * Les tables au format {@link http://www.sqite.org/ SQLite} (extension .db) repr�sentent des
 * tables d'autorit� ouvertes et peuvent �tre mises � jour directement par ajout, modification
 * ou suppression d'entr�es.
 *
 * Ces tables peuvent �tre cr��es en important une table existante au format texte ou par
 * programme en indiquant les champs composant la table (m�me syntaxe que pour les ent�tes
 * des tables au format texte).
 *
 * En interne, c'est d'ailleurs ce que fait la classe ReferenceTable pour les tables au format
 * texte. Lors du premier appel, la classe cr��e dans le r�pertoire temporaire de l'application
 * une copie au format SQLite de la table au format texte puis utilise directement cette copie
 * lors des appels suivants. Si le fichier texte d'origine est modifi�e, la copie en cache est
 * automatiquement mise � jour.
 *
 * La classe ReferenceTable offre des m�thodes permettant :
 * - de cr�er une table de ref�rence (au format texte ou au format SQLite) :
 *   {@link ReferenceTable::create()},
 * - d'ouvrir une table existante : {@link __construct() new ReferenceTable()},
 *   {@link ReferenceTable::open()} ;
 * - d'obtenir des informations sur la table : {@link getPath()}, {@link getFields()},
 *   {@link isReadOnly()} ;
 * - de rechercher des entr�es dans la table : {@link search()}, {@link lookup()} ;
 * - d'ajouter, de modifier ou de supprimer des entr�es (uniquement pour les tables au format
 *   SQLite) : {@link add()}, {@link update()}, {@link delete()} ;
 * - d'exporter tout ou partie de la table : {@link export()}.
 *
 * La modification d'une table (SQLite uniquement) peut poser des probl�mes de concurrence
 * d'acc�s : � tout moment, il ne peut y avoir qu'un seul process en train de modifier la table.
 *
 * @package     fab
 * @subpackage  helpers
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 */
class ReferenceTable
{
    /**
     * Cache utilis� par {@link create()}, {@link open()} et {@link close()} pour maintenir la
     * liste des tables d�j� ouvertes.
     *
     * @var array(ReferenceTable)
     */
    private static $opened = array();


    /**
     * Objet {@link http://php.net/PDO PDO} permettant d'acc�der � la base de donn�es SQLite de
     * la table en cours.
     *
     * @var PDO
     */
    private $db = null;


    /**
     * Tableau contenant les noms des champs de la table.
     *
     * @var array(string)
     */
    private $fields=null;


    /**
     * Indique si la table peut �tre mise � jour ou non.
     *
     * Seules les tables ouvertes directement � partir de la base SQLite (extension .db) peuvent
     * �tre mises � jour. Les tables au format texte (.txt) qui sont compil�es ne peuvent pas
     * �tre modifi�es.
     *
     * @var boolean
     */
    private $readonly=null;


    /**
     * Indique si on a une transaction en cours.
     *
     * @var boolean
     */
    private $commit = null;


    /**
     * Le path de la table de r�f�rence.
     *
     * @var string
     */
    private $path = null;


    /**
     * S�parateur utilis� dans les champs articles
     *
     * @var string
     */
    const SEP='�';


    /**
     * M�thode statique permettant d'ouvrir une table de r�f�rence existante.
     *
     * La m�thode <code>open()</code> ouvre la table dont le chemin est pass� en param�tre et
     * retourne un objet {@link ReferenceTable} permettant d'y acc�der.
     *
     * Une exception est g�n�r�e si la table indiqu�e n'existe pas.
     *
     * La m�thode <code>open()</code> utilise un cache pour stocker les tables d�j� ouvertes :
     * lors du premier appel, la table est ouverte et l'objet <code>ReferenceTable</code> obtenu
     * est mis en cache.
     *
     * Lors d'un appel ult�rieur � <code>open()</code> portant sur la m�me table, l'objet en
     * cache est directement retourn� � l'appellant.
     *
     * @param string $path le path de la table de r�f�rence � charger. Il peut s'agir d'un chemin
     * absolu (commen�ant par '/' ou par 'X:') ou d'un chemin relatif � la racine de l'application.
     *
     * @return ReferenceTable un objet <code>ReferenceTable</code> permettant de manipuler la table.
     *
     * @throw Exception si la table indiqu�e n'existe pas.
     */
    public static function open($path)
    {
        // Le path de la table est relatif � la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // V�rifie que la table demand�e existe
        if (! file_exists($path))
            throw new Exception("La table de r�f�rence $path n'existe pas.");

        // Normalise le path avant de tester le cache
        $path = realpath($path);

        // Si la table est d�j� ouverte, retourne l'objet PDO existant
        if (isset(self::$opened[$path]))
            return self::$opened[$path];

        // Sinon, ouvre la table, la met en cache et retourne le r�sultat
        $table = new self($path);
        self::$opened[$path] = $table;
        return $table;
    }


    /**
     * Supprime la base indiqu�e par <code>$path</code> du cache utilis� par la m�thode
     * {@link open()}.
     *
     * Cette m�thode est rarement utilis�e car la table est automatiquement ferm�e lorsque la
     * derni�re r�f�rence existante sur l'objet ReferenceTable est supprim�e.
     *
     * N�anmoins, la m�thode close() est utile, par exemple, si vous voulez supprimer une table
     * temporaire que vous avez cr�� (comme le cache a une r�f�rence sur l'objet, la base n'est pas
     * ferm�e et donc vous ne pouvez pas la supprimer).
     *
     * @param string $path le path de la table de r�f�rence � fermer. Il peut s'agir d'un chemin
     * absolu (commen�ant par '/' ou par 'X:') ou d'un chemin relatif � la racine de l'application.
     */
    public static function close($path)
    {
        // Le path de la table est relatif � la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // Normalise le path avant de tester le cache
        $path = realpath($path);

        // Supprime la table du cache (si elle existe).
        unset(self::$opened[$path]);
    }


    /**
     * M�thode statique permettant de cr�er une nouvelle table de r�f�rence.
     *
     * Une exception est g�n�r�e si la table indiqu�e existe d�j�.
     *
     * @param string $path le path de la table de r�f�rence � cr�er. Il peut s'agir d'un chemin
     * absolu (commen�ant par '/' ou par 'X:') ou d'un chemin relatif � la racine de l'application.
     *
     * @param array $fields un tableau indiquant le nom et les param�tres de chacun des champs de
     * la table.
     *
     * Exemple :
     * <code>
     * ReferenceTable::create
     * (
     *     'pays.txt',
     *     array
     *     (
     *         'Code (TEXT, PRIMARY KEY)',
     *         'Label (TEXT)'
     *     )
     * );
     * </code>
     *
     * @return ReferenceTable un objet <code>ReferenceTable</code> permettant de manipuler la
     * table nouvellement cr��e.
     */
    public static function create($path, array $fields)
    {
        // Le path de la table est relatif � la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // V�rifie que la table demand�e n'existe pas d�j�
        if (file_exists($path))
            throw new Exception("La table de r�f�rence $path existe d�j�.");

        // Normalise le path de la table
        $path = realpath($path);

        // Teste s'il s'agit d'un fichier texte ou d'une base SQLite
        switch (strtolower(Utils::getExtension($path)))
        {
            case '.txt':
                self::parseFields($test=$fields); // juste pour v�rifier que les param�tres sont ok
                $file = fopen($path, 'w');
                fputcsv($file, $fields, "\t");
                fclose($file);
                break;

            case '.db':
                self::createSQLiteDatabase($path, self::parseFields($fields))->commit();
                break;

            default :
                throw new Exception("Le type de la table de r�f�rence $path n'est pas reconnu.");
        }

        // Ouvre et retourne la table de r�f�rence cr��e
        return self::open($path);
    }


    /**
     * Analyse la ligne d'ent�te d'une table au format texte et retourne la requete sql permettant
     * de cr�er la table des donn�es et les index indiqu�s dans les ent�tes.
     *
     * @param array(string) $fields un tableau d�crivant les champs de la table. Chaque champ peut
     * �tre sous la forme "nom (type contraintes)".
     * En sortie, <code>$fields</code> est modifi� pour ne contenir que le nom des champs.
     *
     * @return string la requ�te sql permettant de cr�er la table et les index indiqu�s dans
     * $fields.
     *
     * @throw Exception si la syntaxe de la ligne d'ent�te est incorrecte.
     */
    public static function parseFields(& $fields)
    {
        // Examine tous les champs
        $_names=$names=$_defs=$defs=$index=array();
        foreach($fields as $field)
        {
            // S�pare le nom du champ de ses param�tres
            $pt = strpos($field, '(');
            if ($pt)
            {
                $name = substr($field, 0, $pt-1);
                $parms = trim(substr($field, $pt+1), ' )');
            }
            else
            {
                $name = $field;
                $parms = 'INDEX, COLLATE NOCASE';
            }

            // Enl�ve les �toiles �ventuelles � la fin des noms de champ (signifiait "no index" avant)
            $name = trim($name, '* ');

            // Analyse les param�tres indiqu�s
            $parms = explode(',', $parms);
            $type = '';
            $_constraints = $constraints = array();
            foreach ($parms as $parm)
            {
                $parm = trim($parm);
                switch (strtolower($parm))
                {
                    case '':
                        break;

                    case 'integer':
                    case 'float':
                    case 'real':
                    case 'numeric':
                    case 'boolean':
                    case 'time':
                    case 'date':
                    case 'timestamp':
                    case 'varchar':
                    case 'nvarchar':
                    case 'text': // valeur par d�faut
                    case 'blob':
                        if ($type)
                            throw new Exception("Vous ne pouvez pas sp�cifier � la fois les types $type et $parm pour le champ $name");
                        $type = strtoupper($parm);
                        break;

                    case 'primary key':
                    case 'not null':
                    case 'unique':
                        $_constraints[] = strtoupper($parm);
                        break;

                    case 'index':
                        $index[] = sprintf('CREATE INDEX "%s" ON "data" ("_%s" ASC);', $name, $name);
                        break;

                    default:
                        if (strncasecmp($parm, 'default ', 8) === 0)
                            $constraints[] = 'DEFAULT ' . substr($parm, 8);
                        elseif (strncasecmp($parm, 'collate ', 8) === 0)
                            $constraints[] = strtoupper($parm);
                        else
                            throw new Exception("Param�tre non reconnu pour le champ $name : $parm");

                }
            }

            // Stocke la d�finition du champ
            if (empty($type)) $type='TEXT';

            $names[]=$name;
            $def = "\"$name\" $type";
            if ($constraints)
                $def .= ' ' . implode(' ', $constraints);
            $defs[]=$def;

            $_names[]="_$name";
            $def = "\"_$name\" $type";
            if ($_constraints)
                $def .= ' ' . implode(' ', $_constraints);
            $_defs[]=$def;
        }

        // Cr�e la requ�te sql permettant de cr�er la table et ses index
        $defs = array_merge($defs, $_defs);
        $names = array_merge($names, $_names);
        $sql = 'CREATE TABLE "data"(' . implode(', ', $defs) . ');';
        if ($index)
            $sql .= "\n" . implode("\n", $index);

        $fields = $names;
        return $sql;
    }


    /**
     * Cr�e et initialise une base de donn�es SQLite en cr�ant la table et les index requis.
     *
     * @param string $path le path de la base de donn�es � cr�er.
     *
     * @param string $sql une chaine contenant les requ�tes sql permettant de cr�er la table
     * de donn�es et les index requis telle que retourn�e par {@link parseFields()}.
     *
     * @return PDO l'objet PDO repr�sentant la base cr��e.
     */
    private static function createSQLiteDatabase($path, $sql)
    {
        // Cr�e le r�pertoire de la base de donn�es si n�cessaire
        $dir = dirname($path);
        if (! is_dir($dir))
            if (! Utils::makeDirectory($dir))
                throw new Exception ("Impossible de cr�er le r�pertoire $dir.");

        // Supprime la base de donn�es existante si n�cessaire
        if (file_exists($path))
            if (! unlink($path))
                throw new Exception("Impossible de supprimer le fichier $path)");

        // Cr�e la base de donn�es SQLite
        $db = new PDO("sqlite:$path");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        // Cr�e la table contenant les donn�es et les index indiqu�s dans la requ�te sql
        $db->exec($sql);

        // Retourne la table cr��e
        return $db;
    }


    /**
     * Ouvre la table de r�f�rence dont le chemin est pass� en param�tre.
     *
     * Les tables au format texte (extension .txt) sont compil�es � la vol�e. Lors du tout
     * premier appel, le fichier texte est charg� puis est charg� dans une bases de donn�es
     * {@link http://www.sqlite.org/ SQLite} stock�e dans un r�pertoire temporaire.
     *
     * Lors des appels suivants, la base SQLite est ouverte directement. Si le fichier
     * d'origine a �t� modifi�, la table est recompil�e automatiquement pour mettre � jour
     * la base de donn�es.
     *
     * Les tables au format SQLite sont ouvertes directement.
     *
     * @param string $path le path du fichier texte de la table de r�f�rence � charger. Il peut
     * s'agir d'un chemin absolu (commen�ant par '/' ou par 'X:') ou d'un chemin relatif � la
     * racine de l'application.
     */
    public function __construct($path)
    {
        // Indique s'il s'agit d'une table au format texte (.txt) ou au format sqlite (.db)
        $compile = null;

        // Le path de la table est relatif � la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // V�rifie que la table demand�e existe
        if (! file_exists($path))
            throw new Exception("La table de r�f�rence $path n'existe pas.");

        // Normalise le path de la table
        $path = realpath($path);

        // Teste s'il s'agit d'un fichier texte ou d'une base SQLite
        switch (strtolower(Utils::getExtension($path)))
        {
            case '.txt':
                $compile = true;
                $this->readonly = true;
                break;

            case '.db':
                $compile = false;
                $this->readonly = false;
                break;

            default :
                throw new Exception("Le type de la table de r�f�rence $path n'est pas reconnu.");
        }

        // Stocke le path de la table
        $this->path = $path;

        // S'il s'agit d'un fichier texte, teste s'il faut le recompiler
        if ($compile)
        {
            if (! Cache::has($path, filemtime($path)))
                return $this->compile($path, Cache::getPath($path));
            else
                $path = Cache::getPath($path);
        }

        // Ouvre la base
        $this->db = new PDO("sqlite:$path");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (! $this->readonly)
            $this->commit = $this->db->beginTransaction();

        // R�cup�re les noms des champs de la table
        $this->fields = $this->db->query('PRAGMA table_info(data)')->fetchAll(PDO::FETCH_NUM | PDO::FETCH_COLUMN, 1);
    }


    /**
     * Destructeur. Committe les �ventuelles modifications apport�es � la table et ferme
     * la base de donn�es.
     */
    public function __destruct()
    {
        // Si la table a �t� ouverte en �criture, committe les �venutelles modifications apport�es
        if ($this->commit)
            $this->db->commit();

        // Ferme la connexion
        unset($this->db);
    }


    /**
     * Charge le fichier texte indiqu� par <code>$path</code> dans la base de donn�es SQLite
     * indiqu�e par <code>$cache</code>.
     *
     * Si la base de donn�es existe d�j�, elle est �cras�e. Le fichier texte doit exister (aucune
     * v�rification n'est faite).
     *
     * @param string $path le chemin du fichier texte � charger.
     * @param string $cache le chemin de la base de donn�es SQLite � cr�er.
     */
    private function compile($path, $cache)
    {
        // Ouvre le fichier texte
        $file = fopen($path, 'r');

        // Charge les ent�tes de colonne
        $this->fields = fgetcsv($file, 1024, "\t");
        $sql = self::parseFields($this->fields);

        $this->db = self::createSQLiteDatabase($cache, $sql);
        $this->commit = true;

        // Pr�pare le statement utilis� pour charger les donn�es
        $sql = sprintf
        (
            'INSERT INTO "data"("%s") VALUES (%s);',
             implode('","', $this->fields),
             rtrim(str_repeat('?,', count($this->fields)), ',)')
        );
        $statement = $this->db->prepare($sql);

        // Charge les donn�es
        $index = array_flip($this->fields);
        while (false !== $values = fgetcsv($file, 1024, "\t"))
        {
            $allvalues=$values;
            foreach($values as $i=>$value)
            {
                if (isset($index['_' . $this->fields[$i]]))
                    $allvalues[] = implode(' ', Utils::tokenize($value));
            }
            $statement->execute($allvalues);
        }
        // Ferme le curseur
        $statement->closeCursor();

        // Ferme le fichier texte
        fclose($file);
    }


    /**
     * Retourne le chemin de la table de r�f�rence.
     *
     * Pour une table au format SQLite, le path retourn� correspond au path indiqu� lors de
     * l'ouverture de la table. Pour une table au format texte, le path retourn� correspond au
     * chemin du fichier texte de la table (et non pas la version compil�e stock�e en cache).
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }


    /**
     * Retourne un tableau contenant les noms des champs pr�sents dans la table.
     *
     * @return array(string)
     */
    public function getFields()
    {
        $t=array();
        foreach ($this->fields as $field)
            if ($field[0] !== '_') $t[]=$field;
        return $t;
    }


    /**
     * Indique si la table peut �tre mise � jour ou non.
     *
     * Seules les tables au format SQLite (extension .db) peuvent �tre mises � jour.
     * Les tables au format texte (.txt) ne peuvent pas �tre modifi�es.
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->readonly;
    }


    /**
     * Lance une recherche dans la table.
     *
     * La m�thode search ex�cute une requ�te SQL de la forme
     *
     * <code>SELECT $what FROM data WHERE $where $options;</code>
     *
     * dans laquelle <code>$what</code> repr�sente les champs que vous voulez r�cup�rer,
     * <code>$where</code> repr�sente les crit�res de recherche que vous passez en param�tre et
     * <code>$options</code> repr�sente des clauses SQL additionelles telles que
     * <code>ORDER BY xxx</code> ou <code>LIMIT 0,10</code> (consultez la
     * {@link http://www.sqlite.org/lang_select.html documentation SQLite} pour conna�tre les
     * options disponibles).
     *
     * Exemples d'utilisation :
     * <code>
     * $table->search();            // retourne tous les enregistrements de la table
     * $table->search('Code="FRA");
     * $table->search('Code LIKE "F%"', 'ORDER BY Label');
     * </code>
     *
     * @param string $where les crit�res de recherche qui seront inclus dans la partie
     * <code>WHERE</code> de la requ�te sql. Exemple : <code>$table->search('Code="FRA"')</code>.
     * L'argument <code>$where</code> est optionnel. Si vous ne le pr�cisez pas, la m�thode
     * retourne tous les enregistrements pr�sents dans la table.
     *
     * @param string $options optionnel, des clauses sql suppl�mentaires � ajouter � la requ�te
     * (par exemple <code>ORDER BY Code</code>).
     *
     * @param string $what optionnel, les champs que vous souhaitez r�cup�rer (* et ROWID par
     * d�faut).
     *
     * @return array La m�thode retourne un tableau vide si aucun enregistrement de la table ne
     * correspond aux crit�res indiqu�s. Dans le cas contraire, elle retourne un tableau de tableaux
     * associatifs contenant les enregistrements trouv�s.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     0 => array('Code'=>'FRA', 'Label'=>'Fran�ais', 'ROWID' =>12),
     *     1 => array('Code'=>'ENG', 'Label'=>'Anglais' , 'ROWID' =>54),
     *     ...
     * )
     * </code>
     *
     * Par d�faut, les enregistrements retourn�s contiennent tous les champs d�finis dans la table
     * plus un champ sp�cifique � SQLite, <code>ROWID</code> qui contient la cl� primaire de
     * l'enregistrement.
     *
     * <code>ROWID</code> est utile pour {@link update() mettre � jour} et pour
     * {@link delete() supprimer} les enregistrements obtenus.
     *
     * Vous pouvez changer les champs retourn�s en passant une valeur � l'argument
     * <code>$what</code>.
     */
    public function search($where='', $options='', $what = '*, ROWID')
    {
        // Construit la requ�te sql
        $sql = "SELECT $what FROM data";
        if ($where) $sql .= " WHERE $where";
        if ($options) $sql .= " $options";

        // Pr�pare et ex�cute la requ�te
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // R�cup�re les r�ponses
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Ferme la requ�te et retourne le r�sultat
        $statement->closeCursor();
        return $result;
    }


    /**
     * Recherche un enregistrement unique dans la table.
     *
     * La m�thode <code>find()</code>fonctionne exactement comme la m�thode {@link search()} si ce
     * n'est qu'elle retourne le premier enregistrement trouv�.
     *
     * Cette m�thode est utile quand on sait qu'il y a au plus une r�ponse : au lieu d'avoir � g�rer
     * un tableau de tableaux en r�sultat, la m�thode <code>find()</code> retourne directement
     * l'enregistrement trouv�.
     *
     * @param string $where les crit�res de recherche.
     *
     * @param string $options optionnel, des clauses sql suppl�mentaires � ajouter � la requ�te
     * (par exemple <code>ORDER BY Code</code>).
     *
     * @param string $what optionnel, les champs que vous souhaitez r�cup�rer (* et ROWID par
     * d�faut).
     *
     * @return false|array La m�thode retourne false si aucun enregistrement de la table ne
     * correspond aux crit�res indiqu�s. Dans le cas contraire, elle retourne le premier
     * enregistrement trouv� sous la forme d'un tableau associatif.
     *
     * Exemple :
     * <code>
     * array('Code'=>'FRA', 'Label'=>'Fran�ais', 'ROWID' =>12),
     * </code>
     *
     * Par d�faut, les enregistrements retourn�s contiennent tous les champs d�finis dans la table
     * plus un champ sp�cifique � SQLite, <code>ROWID</code> qui contient la cl� primaire de
     * l'enregistrement.
     *
     * <code>ROWID</code> est utile pour {@link update() mettre � jour} et pour
     * {@link delete() supprimer} les enregistrements obtenus.
     *
     * Vous pouvez changer les champs retourn�s en passant une valeur � l'argument
     * <code>$what</code>.
     */
    public function find($where='', $options='', $what = '*, ROWID')
    {
        // Construit la requ�te sql
        $sql = "SELECT $what FROM data";
        if ($where) $sql .= " WHERE $where";
        if (false === stripos($options, 'LIMIT')) $options .= ' LIMIT 1';
        $sql .= " $options";

        // Pr�pare et ex�cute la requ�te
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // R�cup�re les r�ponses
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // Ferme la requ�te et retourne le r�sultat
        $statement->closeCursor();
        return $result;
    }


    /**
     * Ajoute un ou plusieurs enregistrements dans la table.
     *
     * Exemples d'utilisation :
     * <code>
     * // Ajout d'un enregistrement unique :
     * $table = ReferenceTable::open('pays.txt')->add
     * (
     *     array('Code'=>'MRS', 'Label'=>'Mars')
     * ); // result : 1
     *
     * // Ajout de plusieurs enregistrements :
     * $table = ReferenceTable::open('pays.txt')->add
     * (
     *     array
     *     (
     *         array('Code'=>'VNS', 'Label'=>'Venus'  ),
     *         array('Code'=>'JPT', 'Label'=>'Jupiter'),
     *         array('Code'=>'STN', 'Label'=>'Saturne'),
     *     )
     * ); // result : 3
     *
     * // Duplique tous les enregistrements existants
     * $table->add($table->search());
     * </code>
     *
     * @param array $records un ou plusieurs enregistrements � ajouter. Chacun des
     * enregistrements doit un ou plusieurs des champs d�finis dans la table. Le champ
     * <code>ROWID</code>, s'il est pr�sent dans l'enregistrement, est ignor�.
     *
     * Pour ajouter un enregistrement unique, passez directement un tableau associatif contenant
     * les champs de l'enregistrement.
     *
     * Exemple :
     * <code>
     * $table->add
     * (
     *     array('Code'=>'FRA', 'Label'=>'Fran�ais2')
     * );
     * </code>
     *
     * Pour ajouter plusieurs enregistrements en une seule �tape, passez en param�tre un
     * tableau de tableaux associatifs.
     *
     * Exemple :
     * <code>
     * $table->add
     * (
     *     array
     *     (
     *         0 => array('Code'=>'FRA', 'Label'=>'French' ),
     *         1 => array('Code'=>'ENG', 'Label'=>'English', 'ROWID' => 54), // ROWID : ignor�
     *         2 => array('Code'=>'INC'),                                    // Label : null
     *         3 => array(),                                                 // vide  : ignor�
     *         ...
     *     )
     * );
     * </code>
     *
     * Les enregistrements compl�tement vides sont ignor�s.
     *
     * @return int le nombre d'enregistrements ajout�s.
     */
    public function add($records)
    {
        // V�rifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, cr�e un tableau pour n'avoir qu'un seul cas � g�rer
        if (! is_array(reset($records))) $records = array($records);

        // Ins�re tous les enregistrements
        $sql = '';
        $count = 0;
        $index = array_flip($this->fields);
        foreach($records as $record)
        {
            // Ignore les enregistrementes compl�tement vides
            if (empty($record)) continue;

            // Cr�e la requ�te sql pour ajouter l'enregistrement
            $fields = $values = '';
            foreach($record as $field=>$value)
            {
                // Ignore le champ ROWID s'il est pr�sent
                if (0 === strcasecmp($field,'ROWID')) continue;

                if ($fields)
                {
                    $fields .= ', ';
                    $values .= ', ';
                }

                $fields .= $this->db->quote($field);
                $values .= $this->db->quote($value);
            }

            foreach($record as $field=>$value)
            {
                $field = "_$field";
                if (! isset($index[$field])) continue;
                $value = implode(' ', Utils::tokenize($value));
                if ($fields)
                {
                    $fields .= ', ';
                    $values .= ', ';
                }

                $fields .= $this->db->quote($field);
                $values .= $this->db->quote($value);
            }
            $sql = "INSERT INTO data($fields) VALUES($values)";

            // Ex�cute la requ�te
            $count += $this->db->exec($sql);
        }

        // Retourne le nombre d'enregistrements ajout�s
        return $count;
    }


    /**
     * Modifie un ou plusieurs enregistrements existants de la table.
     *
     * Exemple d'utilisation :
     * <code>
     * $table = ReferenceTable::open('pays.txt');
     * $records = $t->search('Code="FRA"');
     * $records[0]['Label'] = 'France m�tropolitaine';
     * $table->update($records);
     * </code>
     *
     * @param array $records un ou plusieurs enregistrements � mettre � jour. Chacun des
     * enregistrements doit obligatoirement contenir le champ <code>ROWID</code> (obtenu � partir
     * d'un appel pr�alable � {@link search()}, par exemple).
     *
     * Pour mettre � jour un enregistrement unique, passez directement un tableau associatif
     * contenant les champs de l'enregistrement :
     *
     * <code>
     * $table->update
     * (
     *     array('Code'=>'FRA', 'Label'=>'Fran�ais2', 'ROWID'=>12)
     * );
     * </code>
     *
     * Pour mettre � jour plusieurs enregistrements en une seule �tape, passez en param�tre un
     * tableau de tableaux :
     *
     * <code>
     * $table->update
     * (
     *     array
     *     (
     *         0 => array('Code'=>'FRA', 'Label'=>'French' , 'ROWID'=>12),
     *         1 => array('Code'=>'ENG', 'Label'=>'English', 'ROWID'=>54),
     *         ...
     *     )
     * );
     * </code>
     *
     * @return int le nombre d'enregistrements modifi�s.
     */
    public function update($records)
    {
        // V�rifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, cr�e un tableau pour n'avoir qu'un cas � g�rer
        if (! is_array(reset($records))) $records = array($records);

        // Modifie tous les enregistrements
        $count = 0;
        foreach($records as $record)
        {
            // Ignore les enregistrementes compl�tement vides
            if (empty($record)) continue;

            // Cr�e la requ�te sql pour ajouter l'enregistrement
            $rowid = null;
            $set = '';
            $index = array_flip($this->fields);
            foreach($record as $field=>$value)
            {
                // Cas particulier du champ ROWID
                if (0 === strcasecmp($field, 'ROWID'))
                {
                    $rowid = $value;
                    continue;
                }

                if ($set) $set .= ', ';
                $set .= $this->db->quote($field) . '=' . $this->db->quote($value);
            }
            foreach($record as $field=>$value)
            {
                $field = "_$field";
                if (! isset($index[$field])) continue;
                $value = implode(' ', Utils::tokenize($value));
                if ($set) $set .= ', ';
                $set .= $this->db->quote($field) . '=' . $this->db->quote($value);
            }

            if (is_null($rowid))
                throw new Exception("Pour modifier un enregistrement, vous devez fournir le champ ROWID");

            $sql = "UPDATE data SET $set WHERE ROWID=$rowid";

            // Ex�cute la requ�te
            $count += $this->db->exec($sql);
        }

        // Retourne le nombre d'enregistrements ajout�s
        return $count;
    }


    /**
     * Supprime un ou plusieurs enregistrements existants de la table.
     *
     * Exemple d'utilisation :
     * <code>
     * // Suppression de quelques enregistrements
     * $table = ReferenceTable::open('pays.txt');
     * $records = $t->search('Code="FRA"');
     * $table->delete($records);
     *
     * // Vide la table
     * $table->delete($table->search());
     * </code>
     *
     * @param array $records un ou plusieurs enregistrements � supprimer. Chacun des
     * enregistrements doit obligatoirement contenir le champ <code>ROWID</code> (obtenu � partir
     * d'un appel pr�alable � {@link search()}, par exemple). Les autres champs pr�sents dans
     * l'enregistrement sont ignor�s.
     *
     * Pour supprimer un enregistrement unique, passez directement un tableau associatif
     * contenant les champs de l'enregistrement :
     *
     * <code>
     * $table->delete
     * (
     *     array('ROWID'=>12)
     * );
     * </code>
     *
     * Pour supprimer plusieurs enregistrements en une seule �tape, passez en param�tre un
     * tableau de tableaux :
     *
     * <code>
     * $table->update
     * (
     *     array
     *     (
     *         0 => array('ROWID'=>12),
     *         1 => array('Code'=>'ENG', 'Label'=>'English', 'ROWID'=>54), // Code, Label : ignor�s
     *         ...
     *     )
     * );
     * </code>
     *
     * @return int le nombre d'enregistrements supprim�s.
     */
    public function delete($records)
    {
        // V�rifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, cr�e un tableau pour n'avoir qu'un cas � g�rer
        if (! is_array(reset($records))) $records = array($records);

        // Supprime tous les enregistrements
        $id = array();
        foreach($records as $record)
        {
            // Ignore les enregistrementes compl�tement vides
            if (empty($record)) continue;

            // Stocke les ROWID de tous les enregistrements
            foreach($record as $field=>$value)
            {
                if (0 === strcasecmp($field,'ROWID'))
                {
                    $id[] = $value;
                    continue 2;
                }
            }

            // Aucun rowid trouv�
            throw new Exception("Pour supprimer un enregistrement, vous devez fournir le champ ROWID");
        }

        // Ex�cute la requ�te et retourne le nombre d'enregistrements supprim�s
        if (empty($id)) return 0;
        $sql = 'DELETE FROM data WHERE ROWID IN (' . implode(',', $id) . ')';
        return $this->db->exec($sql);
    }


    /**
     * Recherche <code>$value</code> dans <code>$field</code> et retourne <code>$return</code>.
     *
     * Exemples :
     * <code>
     * - lookup('Code', 'Fra') : recherche la valeur 'Fra' dans le champ code et retourne la valeur
     *   exacte trouv�e dans le champ Code ('FRA').
     * - lookup('Code', 'FRA', 'Label') : recherche la valeur 'FRA' dans le champ code et retourne
     *   le label associ� ('France').
     * - lookup('Label', 'France', 'Code') : recherche la valeur 'France' dans le champ Label et
     *   retourne le Code associ� ('FRA').
     * </code>
     *
     * Remarque : pour des recherches plus complexes ou pour retourner autre chose qu'un champ
     * unique, utilisez les m�thodes {@link search()} et {@link find()}.
     *
     * @param string $field le nom du champ dans lequel <code>$value</code> est recherch�e.
     *
     * @param string $value la valeur recherch�e.
     *
     * @param string $return optionel, le nom du champ � retourner. Si vous n'indiquez rien, la
     * m�thode retourne le contenu exact du champ retourn�.
     *
     * Vous pouvez �galement indiquer dans $return la valeur sp�ciale "*". Dans ce cas, la
     * totalit� de l'entr�e sera retourn�e sous la forme d'un tableau associatif dont les cl�s
     * correspondent aux ent�tes de la table et dont les valeurs correspondent � l'entr�e trouv�e.
     *
     * @param boolean $falseIfNotFound indique ce que la m�thode doit retourner si la valeur
     * recherch�e n'a pas �t� trouv�e dans la table :
     * - Par d�faut (<code>$falseIfNotFound == false</code>), la m�thode retourne la valeur
     *   recherch�e. Cela permet, par exemple, "d'essayer" de traduire un code mais de ne pas
     *   perdre le code si celui-ci n'existe pas :
     *
     *   <code>
     *   echo 'Pays : ', $tbl->lookup('Code', $CodPays, 'Label');
     *   </code>
     *
     * - Si vous passez <code>true</code>, la m�thode retournera <code>false</code> si
     *   la valeur demand�e n'existe pas. Cela permet de savoir si le code recherch� existe ou non :
     *
     *   <code>
     *   if (false !== $pays = $tbl->lookup('Code', $CodPays, 'Label', true)) echo "Pays : $pays";
     *   </code>
     *
     * @return string Retourne la valeur demand�e si une r�ponse a �t� trouv�e.
     * Si aucun enregistrement ne r�pond au crit�re de recherche indiqu�, la m�thode retourne la
     * valeur recherch�e (i.e. Lookup('Code', 'XYZ') -> 'XYZ').
     */
    public function lookup($field, $value, $return = null, $falseIfNotFound=false)
    {
        if (empty($return)) $return = $field;
        $value=implode(' ', Utils::tokenize($value));
        if (false === $record = $this->find("_$field=\"$value\"", '', $return))
            return $falseIfNotFound ? ($return === '*' ? null : false) : $value;

        return $return === '*' ? $record : $record[$return];
    }


    /**
     * Exporte la totalit� ou une partie de la table dans un fichier au format texte tabul�.
     *
     * @param resource|string $to la destination de l'export. Vous pouvez indiquer au choix :
     * - le handle d'un fichier d�j� ouvert en �criture et que vous vous chargerez de fermer :
     *
     * Exemple :
     * <code>
     * $backup = fopen('pays.sav');
     * ReferenceTable::open('pays.txt')->export($backup);
     * fclose($backup);
     * </code>
     *
     * - un handle php standard toujours ouvert tel que STDOUT :
     *
     * Exemple :
     * <code>
     * ReferenceTable::open('pays.txt')->export(STDOUT);
     * ReferenceTable::open('pays.txt')->export(); // idem : STDOUT est la valeur par d�faut
     * </code>
     *
     * - le path d'un fichier qui sera g�n�r�. Il peut s'agir d'un chemin absolu (commen�ant
     * par '/' ou par 'X:') ou d'un chemin relatif � la racine de l'application.
     *
     * Attention : si le fichier existe d�j�, il sera �cras� sans confirmation.
     *
     * Exemple :
     * <code>
     * ReferenceTable::open('pays.txt')->export('c:/backups/pays.sav');
     * </code>
     *
     * @param string $where des crit�res de recherche qui seront inclus dans la
     * partie <code>WHERE</code> de la requ�te sql pour les filtrer les enregistrements export�s.
     * Exemple : <code>$table->export(STDOUT, 'Code="FRA"')</code>.
     * L'argument <code>$where</code> est optionnel. Si vous ne le pr�cisez pas, la m�thode exporte
     * tous les enregistrements pr�sents dans la table.
     *
     * @param string $options optionnel, des clauses sql suppl�mentaires qui seront ajout�es � la
     * requ�te SQL (par exemple <code>ORDER BY xxx</code> ou <code>LIMIT 0,10</code>).
     *
     * @return int le nombre d'enregistrements export�s.
     */
    public function export($to=STDOUT, $where='', $options='')
    {
        // Ouvre le stream de destination
        if (is_resource($to))
            $file = $to;
        elseif (is_string($to))
        {
            if (Utils::isRelativePath($to))
                $to = Utils::makePath(Runtime::$root, $to);

            $file = fopen($to, 'w');
        }

        // Ecrit les ent�tes
        fputcsv($file, $this->getFields(), "\t");

        // Pr�pare la requ�te
        $sql = 'SELECT ' . implode(',', $this->getFields()) . ' FROM data';
        if ($where)   $sql .= " WHERE $where";
        if ($options) $sql .= " $options";

        // Ex�cute la requ�te
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // Exporte toutes les r�ponses
        $count = 0;
        while (false !== $record = $statement->fetch(PDO::FETCH_ASSOC))
        {
            fputcsv($file, $record, "\t");
            ++$count;
        }

        // Ferme la requ�te
        $statement->closeCursor();

        // Ferme le stream de destination
        if ($file !== $to) fclose($file);

        // Retourne le nombre d'enregistrements export�s
        return $count;
    }
}