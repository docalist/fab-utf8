<?php
/**
 * @package     fab
 * @subpackage  helpers
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente une table de référence.
 *
 * Une table de référence (également appellée liste d'autorité, table de correspondance, etc.)
 * est une structure de données qui permet d'associer des valeurs entre elles. Par exemple,
 * une table des codes pays permettra d'associer un code ISO (FRA, DEU...) à un libellé
 * (France, Allemagne...).
 *
 * Une table de référence peut comporter une, deux ou plusieurs colonnes. Une table contenant
 * une seule colonne permet de définir un ensemble de valeurs autorisées. Une table a deux
 * colonnes se comporte comme un tableau associatif qui pour chaque entrée associe une valeur
 * à une clé. Une table avec plus de deux colonnes permet de stocker des informations
 * supplémentaires (pour la table des pays, par exemple, on pourrait stocker le code ISO sur
 * 3 lettres, le code ISO sur deux lettres, le nom du pays en français et en anglais, le nom
 * de la capitale, etc.)
 *
 * La classe <code>ReferenceTable</code> peut aussi être utilisée pour stocker d'autres types
 * d'informations : nombre de hits sur une page donnée, comptes utilisateurs, logs, etc.
 *
 * Il existe deux types de tables de référence : les tables au format texte et les tables au
 * format {@link http://www.sqlite.org/ SQLite}.
 *
 * Les tables au format texte sont de simples fichier texte (extension .txt) dans lesquels les
 * données sont stockées au {@link http://fr.wikipedia.org/wiki/Format_TSV format texte tabulé}.
 * Les tables de ce type sont des tables d'autorité fermées qui ne peuvent pas être modifiées
 * (sauf en modifiant le fichier texte d'origine).
 *
 * Le fichier texte contient une ligne d'entêtes qui désigne les noms des différentes colonnes
 * (ou champs) de la table. Les données viennent ensuite, à raison d'une entrée par ligne,
 * chaque champ apparaissant dans le même ordre que la ligne d'entête et séparé des autres
 * champs par un caractère tabulation.
 *
 * La ligne d'entête indique pour chaque colonne le nom du champ associé et peut indiquer entre
 * parenthèses un ou plusieurs paramètres séparés par une virgule.
 *
 * Exemple :
 * <code>
 * Code (TEXT, PRIMARY KEY) Label (TEXT)
 * FRA                      France
 * DEU                      Allemagne
 * </code>.
 *
 * Les {@link http://www.sqlite.org/lang_createtable.html paramètres} permettent d'indiquer :
 * - Le {@link http://www.sqlite.org/datatype3.html type des données} stockées dans le champ :
 *   <code>INTEGER</code>, <code>FLOAT</code>, <code>REAL</code>, <code>NUMERIC</code>,
 *   <code>BOOLEAN</code>, <code>TIME</code>, <code>DATE</code>, <code>TIMESTAMP</code>,
 *   <code>VARCHAR</code>, <code>NVARCHAR</code>, <code>TEXT</code> ou <code>BLOB</code> ;
 * - Le type d'indexation a appliquer à ce champ : <code>INDEX</code>, <code>PRIMARY KEY</code>,
 *   <code>UNIQUE</code> ;
 * - L'acceptation ou non des valeurs NULL : <code>NOT NULL</code> ;
 * - Une valeur par défaut : <code>DEFAULT "xxx"</code> ;
 * - Une séquence de collation : <code>COLLATE BINARY</code>, <code>COLLATE NOCASE</code>,
 *   <code>COLLATE RTRIM</code> ;
 *
 * Si vous n'indiquez aucun paramètre pour un champ, celui-ci est créé avec les options
 * <code>TEXT, INDEX, COLLATE NOCASE</code> : le type par défaut d'un champ est
 * <code>TEXT</code>, par défaut, il est indexé, et les comparaisons se font sans tenir compte
 * de la casse des caractères.
 *
 * Les tables au format {@link http://www.sqite.org/ SQLite} (extension .db) représentent des
 * tables d'autorité ouvertes et peuvent être mises à jour directement par ajout, modification
 * ou suppression d'entrées.
 *
 * Ces tables peuvent être créées en important une table existante au format texte ou par
 * programme en indiquant les champs composant la table (même syntaxe que pour les entêtes
 * des tables au format texte).
 *
 * En interne, c'est d'ailleurs ce que fait la classe ReferenceTable pour les tables au format
 * texte. Lors du premier appel, la classe créée dans le répertoire temporaire de l'application
 * une copie au format SQLite de la table au format texte puis utilise directement cette copie
 * lors des appels suivants. Si le fichier texte d'origine est modifiée, la copie en cache est
 * automatiquement mise à jour.
 *
 * La classe ReferenceTable offre des méthodes permettant :
 * - de créer une table de reférence (au format texte ou au format SQLite) :
 *   {@link ReferenceTable::create()},
 * - d'ouvrir une table existante : {@link __construct() new ReferenceTable()},
 *   {@link ReferenceTable::open()} ;
 * - d'obtenir des informations sur la table : {@link getPath()}, {@link getFields()},
 *   {@link isReadOnly()} ;
 * - de rechercher des entrées dans la table : {@link search()}, {@link lookup()} ;
 * - d'ajouter, de modifier ou de supprimer des entrées (uniquement pour les tables au format
 *   SQLite) : {@link add()}, {@link update()}, {@link delete()} ;
 * - d'exporter tout ou partie de la table : {@link export()}.
 *
 * La modification d'une table (SQLite uniquement) peut poser des problèmes de concurrence
 * d'accès : à tout moment, il ne peut y avoir qu'un seul process en train de modifier la table.
 *
 * @package     fab
 * @subpackage  helpers
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>
 */
class ReferenceTable
{
    /**
     * Cache utilisé par {@link create()}, {@link open()} et {@link close()} pour maintenir la
     * liste des tables déjà ouvertes.
     *
     * @var array(ReferenceTable)
     */
    private static $opened = array();


    /**
     * Objet {@link http://php.net/PDO PDO} permettant d'accèder à la base de données SQLite de
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
     * Indique si la table peut être mise à jour ou non.
     *
     * Seules les tables ouvertes directement à partir de la base SQLite (extension .db) peuvent
     * être mises à jour. Les tables au format texte (.txt) qui sont compilées ne peuvent pas
     * être modifiées.
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
     * Le path de la table de référence.
     *
     * @var string
     */
    private $path = null;


    /**
     * Séparateur utilisé dans les champs articles
     *
     * @var string
     */
    const SEP='·';


    /**
     * Méthode statique permettant d'ouvrir une table de référence existante.
     *
     * La méthode <code>open()</code> ouvre la table dont le chemin est passé en paramètre et
     * retourne un objet {@link ReferenceTable} permettant d'y accéder.
     *
     * Une exception est générée si la table indiquée n'existe pas.
     *
     * La méthode <code>open()</code> utilise un cache pour stocker les tables déjà ouvertes :
     * lors du premier appel, la table est ouverte et l'objet <code>ReferenceTable</code> obtenu
     * est mis en cache.
     *
     * Lors d'un appel ultérieur à <code>open()</code> portant sur la même table, l'objet en
     * cache est directement retourné à l'appellant.
     *
     * @param string $path le path de la table de référence à charger. Il peut s'agir d'un chemin
     * absolu (commençant par '/' ou par 'X:') ou d'un chemin relatif à la racine de l'application.
     *
     * @return ReferenceTable un objet <code>ReferenceTable</code> permettant de manipuler la table.
     *
     * @throw Exception si la table indiquée n'existe pas.
     */
    public static function open($path)
    {
        // Le path de la table est relatif à la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // Vérifie que la table demandée existe
        if (! file_exists($path))
            throw new Exception("La table de référence $path n'existe pas.");

        // Normalise le path avant de tester le cache
        $path = realpath($path);

        // Si la table est déjà ouverte, retourne l'objet PDO existant
        if (isset(self::$opened[$path]))
            return self::$opened[$path];

        // Sinon, ouvre la table, la met en cache et retourne le résultat
        $table = new self($path);
        self::$opened[$path] = $table;
        return $table;
    }


    /**
     * Supprime la base indiquée par <code>$path</code> du cache utilisé par la méthode
     * {@link open()}.
     *
     * Cette méthode est rarement utilisée car la table est automatiquement fermée lorsque la
     * dernière référence existante sur l'objet ReferenceTable est supprimée.
     *
     * Néanmoins, la méthode close() est utile, par exemple, si vous voulez supprimer une table
     * temporaire que vous avez créé (comme le cache a une référence sur l'objet, la base n'est pas
     * fermée et donc vous ne pouvez pas la supprimer).
     *
     * @param string $path le path de la table de référence à fermer. Il peut s'agir d'un chemin
     * absolu (commençant par '/' ou par 'X:') ou d'un chemin relatif à la racine de l'application.
     */
    public static function close($path)
    {
        // Le path de la table est relatif à la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // Normalise le path avant de tester le cache
        $path = realpath($path);

        // Supprime la table du cache (si elle existe).
        unset(self::$opened[$path]);
    }


    /**
     * Méthode statique permettant de créer une nouvelle table de référence.
     *
     * Une exception est générée si la table indiquée existe déjà.
     *
     * @param string $path le path de la table de référence à créer. Il peut s'agir d'un chemin
     * absolu (commençant par '/' ou par 'X:') ou d'un chemin relatif à la racine de l'application.
     *
     * @param array $fields un tableau indiquant le nom et les paramètres de chacun des champs de
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
     * table nouvellement créée.
     */
    public static function create($path, array $fields)
    {
        // Le path de la table est relatif à la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // Vérifie que la table demandée n'existe pas déjà
        if (file_exists($path))
            throw new Exception("La table de référence $path existe déjà.");

        // Normalise le path de la table
        $path = realpath($path);

        // Teste s'il s'agit d'un fichier texte ou d'une base SQLite
        switch (strtolower(Utils::getExtension($path)))
        {
            case '.txt':
                self::parseFields($test=$fields); // juste pour vérifier que les paramètres sont ok
                $file = fopen($path, 'w');
                fputcsv($file, $fields, "\t");
                fclose($file);
                break;

            case '.db':
                self::createSQLiteDatabase($path, self::parseFields($fields))->commit();
                break;

            default :
                throw new Exception("Le type de la table de référence $path n'est pas reconnu.");
        }

        // Ouvre et retourne la table de référence créée
        return self::open($path);
    }


    /**
     * Analyse la ligne d'entête d'une table au format texte et retourne la requete sql permettant
     * de créer la table des données et les index indiqués dans les entêtes.
     *
     * @param array(string) $fields un tableau décrivant les champs de la table. Chaque champ peut
     * être sous la forme "nom (type contraintes)".
     * En sortie, <code>$fields</code> est modifié pour ne contenir que le nom des champs.
     *
     * @return string la requête sql permettant de créer la table et les index indiqués dans
     * $fields.
     *
     * @throw Exception si la syntaxe de la ligne d'entête est incorrecte.
     */
    public static function parseFields(& $fields)
    {
        // Examine tous les champs
        $_names=$names=$_defs=$defs=$index=array();
        foreach($fields as $field)
        {
            // Sépare le nom du champ de ses paramètres
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

            // Enlève les étoiles éventuelles à la fin des noms de champ (signifiait "no index" avant)
            $name = trim($name, '* ');

            // Analyse les paramètres indiqués
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
                    case 'text': // valeur par défaut
                    case 'blob':
                        if ($type)
                            throw new Exception("Vous ne pouvez pas spécifier à la fois les types $type et $parm pour le champ $name");
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
                            throw new Exception("Paramètre non reconnu pour le champ $name : $parm");

                }
            }

            // Stocke la définition du champ
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

        // Crée la requête sql permettant de créer la table et ses index
        $defs = array_merge($defs, $_defs);
        $names = array_merge($names, $_names);
        $sql = 'CREATE TABLE "data"(' . implode(', ', $defs) . ');';
        if ($index)
            $sql .= "\n" . implode("\n", $index);

        $fields = $names;
        return $sql;
    }


    /**
     * Crée et initialise une base de données SQLite en créant la table et les index requis.
     *
     * @param string $path le path de la base de données à créer.
     *
     * @param string $sql une chaine contenant les requêtes sql permettant de créer la table
     * de données et les index requis telle que retournée par {@link parseFields()}.
     *
     * @return PDO l'objet PDO représentant la base créée.
     */
    private static function createSQLiteDatabase($path, $sql)
    {
        // Crée le répertoire de la base de données si nécessaire
        $dir = dirname($path);
        if (! is_dir($dir))
            if (! Utils::makeDirectory($dir))
                throw new Exception ("Impossible de créer le répertoire $dir.");

        // Supprime la base de données existante si nécessaire
        if (file_exists($path))
            if (! unlink($path))
                throw new Exception("Impossible de supprimer le fichier $path)");

        // Crée la base de données SQLite
        $db = new PDO("sqlite:$path");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->beginTransaction();

        // Crée la table contenant les données et les index indiqués dans la requête sql
        $db->exec($sql);

        // Retourne la table créée
        return $db;
    }


    /**
     * Ouvre la table de référence dont le chemin est passé en paramètre.
     *
     * Les tables au format texte (extension .txt) sont compilées à la volée. Lors du tout
     * premier appel, le fichier texte est chargé puis est chargé dans une bases de données
     * {@link http://www.sqlite.org/ SQLite} stockée dans un répertoire temporaire.
     *
     * Lors des appels suivants, la base SQLite est ouverte directement. Si le fichier
     * d'origine a été modifié, la table est recompilée automatiquement pour mettre à jour
     * la base de données.
     *
     * Les tables au format SQLite sont ouvertes directement.
     *
     * @param string $path le path du fichier texte de la table de référence à charger. Il peut
     * s'agir d'un chemin absolu (commençant par '/' ou par 'X:') ou d'un chemin relatif à la
     * racine de l'application.
     */
    public function __construct($path)
    {
        // Indique s'il s'agit d'une table au format texte (.txt) ou au format sqlite (.db)
        $compile = null;

        // Le path de la table est relatif à la racine de l'application
        if (Utils::isRelativePath($path))
            $path = Utils::makePath(Runtime::$root, $path);

        // Vérifie que la table demandée existe
        if (! file_exists($path))
            throw new Exception("La table de référence $path n'existe pas.");

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
                throw new Exception("Le type de la table de référence $path n'est pas reconnu.");
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

        // Récupère les noms des champs de la table
        $this->fields = $this->db->query('PRAGMA table_info(data)')->fetchAll(PDO::FETCH_NUM | PDO::FETCH_COLUMN, 1);
    }


    /**
     * Destructeur. Committe les éventuelles modifications apportées à la table et ferme
     * la base de données.
     */
    public function __destruct()
    {
        // Si la table a été ouverte en écriture, committe les évenutelles modifications apportées
        if ($this->commit)
            $this->db->commit();

        // Ferme la connexion
        unset($this->db);
    }


    /**
     * Charge le fichier texte indiqué par <code>$path</code> dans la base de données SQLite
     * indiquée par <code>$cache</code>.
     *
     * Si la base de données existe déjà, elle est écrasée. Le fichier texte doit exister (aucune
     * vérification n'est faite).
     *
     * @param string $path le chemin du fichier texte à charger.
     * @param string $cache le chemin de la base de données SQLite à créer.
     */
    private function compile($path, $cache)
    {
        // Ouvre le fichier texte
        $file = fopen($path, 'r');

        // Charge les entêtes de colonne
        $this->fields = fgetcsv($file, 1024, "\t");
        $sql = self::parseFields($this->fields);

        $this->db = self::createSQLiteDatabase($cache, $sql);
        $this->commit = true;

        // Prépare le statement utilisé pour charger les données
        $sql = sprintf
        (
            'INSERT INTO "data"("%s") VALUES (%s);',
             implode('","', $this->fields),
             rtrim(str_repeat('?,', count($this->fields)), ',)')
        );
        $statement = $this->db->prepare($sql);

        // Charge les données
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
     * Retourne le chemin de la table de référence.
     *
     * Pour une table au format SQLite, le path retourné correspond au path indiqué lors de
     * l'ouverture de la table. Pour une table au format texte, le path retourné correspond au
     * chemin du fichier texte de la table (et non pas la version compilée stockée en cache).
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }


    /**
     * Retourne un tableau contenant les noms des champs présents dans la table.
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
     * Indique si la table peut être mise à jour ou non.
     *
     * Seules les tables au format SQLite (extension .db) peuvent être mises à jour.
     * Les tables au format texte (.txt) ne peuvent pas être modifiées.
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
     * La méthode search exécute une requête SQL de la forme
     *
     * <code>SELECT $what FROM data WHERE $where $options;</code>
     *
     * dans laquelle <code>$what</code> représente les champs que vous voulez récupérer,
     * <code>$where</code> représente les critères de recherche que vous passez en paramètre et
     * <code>$options</code> représente des clauses SQL additionelles telles que
     * <code>ORDER BY xxx</code> ou <code>LIMIT 0,10</code> (consultez la
     * {@link http://www.sqlite.org/lang_select.html documentation SQLite} pour connaître les
     * options disponibles).
     *
     * Exemples d'utilisation :
     * <code>
     * $table->search();            // retourne tous les enregistrements de la table
     * $table->search('Code="FRA");
     * $table->search('Code LIKE "F%"', 'ORDER BY Label');
     * </code>
     *
     * @param string $where les critères de recherche qui seront inclus dans la partie
     * <code>WHERE</code> de la requête sql. Exemple : <code>$table->search('Code="FRA"')</code>.
     * L'argument <code>$where</code> est optionnel. Si vous ne le précisez pas, la méthode
     * retourne tous les enregistrements présents dans la table.
     *
     * @param string $options optionnel, des clauses sql supplémentaires à ajouter à la requête
     * (par exemple <code>ORDER BY Code</code>).
     *
     * @param string $what optionnel, les champs que vous souhaitez récupérer (* et ROWID par
     * défaut).
     *
     * @return array La méthode retourne un tableau vide si aucun enregistrement de la table ne
     * correspond aux critères indiqués. Dans le cas contraire, elle retourne un tableau de tableaux
     * associatifs contenant les enregistrements trouvés.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     0 => array('Code'=>'FRA', 'Label'=>'Français', 'ROWID' =>12),
     *     1 => array('Code'=>'ENG', 'Label'=>'Anglais' , 'ROWID' =>54),
     *     ...
     * )
     * </code>
     *
     * Par défaut, les enregistrements retournés contiennent tous les champs définis dans la table
     * plus un champ spécifique à SQLite, <code>ROWID</code> qui contient la clé primaire de
     * l'enregistrement.
     *
     * <code>ROWID</code> est utile pour {@link update() mettre à jour} et pour
     * {@link delete() supprimer} les enregistrements obtenus.
     *
     * Vous pouvez changer les champs retournés en passant une valeur à l'argument
     * <code>$what</code>.
     */
    public function search($where='', $options='', $what = '*, ROWID')
    {
        // Construit la requête sql
        $sql = "SELECT $what FROM data";
        if ($where) $sql .= " WHERE $where";
        if ($options) $sql .= " $options";

        // Prépare et exécute la requête
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // Récupère les réponses
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Ferme la requête et retourne le résultat
        $statement->closeCursor();
        return $result;
    }


    /**
     * Recherche un enregistrement unique dans la table.
     *
     * La méthode <code>find()</code>fonctionne exactement comme la méthode {@link search()} si ce
     * n'est qu'elle retourne le premier enregistrement trouvé.
     *
     * Cette méthode est utile quand on sait qu'il y a au plus une réponse : au lieu d'avoir à gérer
     * un tableau de tableaux en résultat, la méthode <code>find()</code> retourne directement
     * l'enregistrement trouvé.
     *
     * @param string $where les critères de recherche.
     *
     * @param string $options optionnel, des clauses sql supplémentaires à ajouter à la requête
     * (par exemple <code>ORDER BY Code</code>).
     *
     * @param string $what optionnel, les champs que vous souhaitez récupérer (* et ROWID par
     * défaut).
     *
     * @return false|array La méthode retourne false si aucun enregistrement de la table ne
     * correspond aux critères indiqués. Dans le cas contraire, elle retourne le premier
     * enregistrement trouvé sous la forme d'un tableau associatif.
     *
     * Exemple :
     * <code>
     * array('Code'=>'FRA', 'Label'=>'Français', 'ROWID' =>12),
     * </code>
     *
     * Par défaut, les enregistrements retournés contiennent tous les champs définis dans la table
     * plus un champ spécifique à SQLite, <code>ROWID</code> qui contient la clé primaire de
     * l'enregistrement.
     *
     * <code>ROWID</code> est utile pour {@link update() mettre à jour} et pour
     * {@link delete() supprimer} les enregistrements obtenus.
     *
     * Vous pouvez changer les champs retournés en passant une valeur à l'argument
     * <code>$what</code>.
     */
    public function find($where='', $options='', $what = '*, ROWID')
    {
        // Construit la requête sql
        $sql = "SELECT $what FROM data";
        if ($where) $sql .= " WHERE $where";
        if (false === stripos($options, 'LIMIT')) $options .= ' LIMIT 1';
        $sql .= " $options";

        // Prépare et exécute la requête
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // Récupère les réponses
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // Ferme la requête et retourne le résultat
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
     * @param array $records un ou plusieurs enregistrements à ajouter. Chacun des
     * enregistrements doit un ou plusieurs des champs définis dans la table. Le champ
     * <code>ROWID</code>, s'il est présent dans l'enregistrement, est ignoré.
     *
     * Pour ajouter un enregistrement unique, passez directement un tableau associatif contenant
     * les champs de l'enregistrement.
     *
     * Exemple :
     * <code>
     * $table->add
     * (
     *     array('Code'=>'FRA', 'Label'=>'Français2')
     * );
     * </code>
     *
     * Pour ajouter plusieurs enregistrements en une seule étape, passez en paramètre un
     * tableau de tableaux associatifs.
     *
     * Exemple :
     * <code>
     * $table->add
     * (
     *     array
     *     (
     *         0 => array('Code'=>'FRA', 'Label'=>'French' ),
     *         1 => array('Code'=>'ENG', 'Label'=>'English', 'ROWID' => 54), // ROWID : ignoré
     *         2 => array('Code'=>'INC'),                                    // Label : null
     *         3 => array(),                                                 // vide  : ignoré
     *         ...
     *     )
     * );
     * </code>
     *
     * Les enregistrements complètement vides sont ignorés.
     *
     * @return int le nombre d'enregistrements ajoutés.
     */
    public function add($records)
    {
        // Vérifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, crée un tableau pour n'avoir qu'un seul cas à gérer
        if (! is_array(reset($records))) $records = array($records);

        // Insère tous les enregistrements
        $sql = '';
        $count = 0;
        $index = array_flip($this->fields);
        foreach($records as $record)
        {
            // Ignore les enregistrementes complètement vides
            if (empty($record)) continue;

            // Crée la requête sql pour ajouter l'enregistrement
            $fields = $values = '';
            foreach($record as $field=>$value)
            {
                // Ignore le champ ROWID s'il est présent
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

            // Exécute la requête
            $count += $this->db->exec($sql);
        }

        // Retourne le nombre d'enregistrements ajoutés
        return $count;
    }


    /**
     * Modifie un ou plusieurs enregistrements existants de la table.
     *
     * Exemple d'utilisation :
     * <code>
     * $table = ReferenceTable::open('pays.txt');
     * $records = $t->search('Code="FRA"');
     * $records[0]['Label'] = 'France métropolitaine';
     * $table->update($records);
     * </code>
     *
     * @param array $records un ou plusieurs enregistrements à mettre à jour. Chacun des
     * enregistrements doit obligatoirement contenir le champ <code>ROWID</code> (obtenu à partir
     * d'un appel préalable à {@link search()}, par exemple).
     *
     * Pour mettre à jour un enregistrement unique, passez directement un tableau associatif
     * contenant les champs de l'enregistrement :
     *
     * <code>
     * $table->update
     * (
     *     array('Code'=>'FRA', 'Label'=>'Français2', 'ROWID'=>12)
     * );
     * </code>
     *
     * Pour mettre à jour plusieurs enregistrements en une seule étape, passez en paramètre un
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
     * @return int le nombre d'enregistrements modifiés.
     */
    public function update($records)
    {
        // Vérifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, crée un tableau pour n'avoir qu'un cas à gérer
        if (! is_array(reset($records))) $records = array($records);

        // Modifie tous les enregistrements
        $count = 0;
        foreach($records as $record)
        {
            // Ignore les enregistrementes complètement vides
            if (empty($record)) continue;

            // Crée la requête sql pour ajouter l'enregistrement
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

            // Exécute la requête
            $count += $this->db->exec($sql);
        }

        // Retourne le nombre d'enregistrements ajoutés
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
     * @param array $records un ou plusieurs enregistrements à supprimer. Chacun des
     * enregistrements doit obligatoirement contenir le champ <code>ROWID</code> (obtenu à partir
     * d'un appel préalable à {@link search()}, par exemple). Les autres champs présents dans
     * l'enregistrement sont ignorés.
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
     * Pour supprimer plusieurs enregistrements en une seule étape, passez en paramètre un
     * tableau de tableaux :
     *
     * <code>
     * $table->update
     * (
     *     array
     *     (
     *         0 => array('ROWID'=>12),
     *         1 => array('Code'=>'ENG', 'Label'=>'English', 'ROWID'=>54), // Code, Label : ignorés
     *         ...
     *     )
     * );
     * </code>
     *
     * @return int le nombre d'enregistrements supprimés.
     */
    public function delete($records)
    {
        // Vérifie qu'on a le droit de modifier la table
        if ($this->readonly)
            throw new Exception('La table "' . $this->path . '" est en lecture seulement.');

        // Si on a un enregistrement unique, crée un tableau pour n'avoir qu'un cas à gérer
        if (! is_array(reset($records))) $records = array($records);

        // Supprime tous les enregistrements
        $id = array();
        foreach($records as $record)
        {
            // Ignore les enregistrementes complètement vides
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

            // Aucun rowid trouvé
            throw new Exception("Pour supprimer un enregistrement, vous devez fournir le champ ROWID");
        }

        // Exécute la requête et retourne le nombre d'enregistrements supprimés
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
     *   exacte trouvée dans le champ Code ('FRA').
     * - lookup('Code', 'FRA', 'Label') : recherche la valeur 'FRA' dans le champ code et retourne
     *   le label associé ('France').
     * - lookup('Label', 'France', 'Code') : recherche la valeur 'France' dans le champ Label et
     *   retourne le Code associé ('FRA').
     * </code>
     *
     * Remarque : pour des recherches plus complexes ou pour retourner autre chose qu'un champ
     * unique, utilisez les méthodes {@link search()} et {@link find()}.
     *
     * @param string $field le nom du champ dans lequel <code>$value</code> est recherchée.
     *
     * @param string $value la valeur recherchée.
     *
     * @param string $return optionel, le nom du champ à retourner. Si vous n'indiquez rien, la
     * méthode retourne le contenu exact du champ retourné.
     *
     * Vous pouvez également indiquer dans $return la valeur spéciale "*". Dans ce cas, la
     * totalité de l'entrée sera retournée sous la forme d'un tableau associatif dont les clés
     * correspondent aux entêtes de la table et dont les valeurs correspondent à l'entrée trouvée.
     *
     * @param boolean $falseIfNotFound indique ce que la méthode doit retourner si la valeur
     * recherchée n'a pas été trouvée dans la table :
     * - Par défaut (<code>$falseIfNotFound == false</code>), la méthode retourne la valeur
     *   recherchée. Cela permet, par exemple, "d'essayer" de traduire un code mais de ne pas
     *   perdre le code si celui-ci n'existe pas :
     *
     *   <code>
     *   echo 'Pays : ', $tbl->lookup('Code', $CodPays, 'Label');
     *   </code>
     *
     * - Si vous passez <code>true</code>, la méthode retournera <code>false</code> si
     *   la valeur demandée n'existe pas. Cela permet de savoir si le code recherché existe ou non :
     *
     *   <code>
     *   if (false !== $pays = $tbl->lookup('Code', $CodPays, 'Label', true)) echo "Pays : $pays";
     *   </code>
     *
     * @return string Retourne la valeur demandée si une réponse a été trouvée.
     * Si aucun enregistrement ne répond au critère de recherche indiqué, la méthode retourne la
     * valeur recherchée (i.e. Lookup('Code', 'XYZ') -> 'XYZ').
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
     * Exporte la totalité ou une partie de la table dans un fichier au format texte tabulé.
     *
     * @param resource|string $to la destination de l'export. Vous pouvez indiquer au choix :
     * - le handle d'un fichier déjà ouvert en écriture et que vous vous chargerez de fermer :
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
     * ReferenceTable::open('pays.txt')->export(); // idem : STDOUT est la valeur par défaut
     * </code>
     *
     * - le path d'un fichier qui sera généré. Il peut s'agir d'un chemin absolu (commençant
     * par '/' ou par 'X:') ou d'un chemin relatif à la racine de l'application.
     *
     * Attention : si le fichier existe déjà, il sera écrasé sans confirmation.
     *
     * Exemple :
     * <code>
     * ReferenceTable::open('pays.txt')->export('c:/backups/pays.sav');
     * </code>
     *
     * @param string $where des critères de recherche qui seront inclus dans la
     * partie <code>WHERE</code> de la requête sql pour les filtrer les enregistrements exportés.
     * Exemple : <code>$table->export(STDOUT, 'Code="FRA"')</code>.
     * L'argument <code>$where</code> est optionnel. Si vous ne le précisez pas, la méthode exporte
     * tous les enregistrements présents dans la table.
     *
     * @param string $options optionnel, des clauses sql supplémentaires qui seront ajoutées à la
     * requête SQL (par exemple <code>ORDER BY xxx</code> ou <code>LIMIT 0,10</code>).
     *
     * @return int le nombre d'enregistrements exportés.
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

        // Ecrit les entêtes
        fputcsv($file, $this->getFields(), "\t");

        // Prépare la requête
        $sql = 'SELECT ' . implode(',', $this->getFields()) . ' FROM data';
        if ($where)   $sql .= " WHERE $where";
        if ($options) $sql .= " $options";

        // Exécute la requête
        $statement = $this->db->prepare($sql);
        $statement->execute();

        // Exporte toutes les réponses
        $count = 0;
        while (false !== $record = $statement->fetch(PDO::FETCH_ASSOC))
        {
            fputcsv($file, $record, "\t");
            ++$count;
        }

        // Ferme la requête
        $statement->closeCursor();

        // Ferme le stream de destination
        if ($file !== $to) fclose($file);

        // Retourne le nombre d'enregistrements exportés
        return $count;
    }
}