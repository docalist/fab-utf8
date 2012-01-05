<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base abstraite pour toutes les classes <code>Record</code> de
 * la chaine de traitement.
 *
 * Un objet <code>Record</code> représente un enregistrement dans un format
 * donné. C'est essentiellement une {@link Multimap collection de champs}
 * contenant une valeur ou un tableau d'articles.
 *
 * Chaque objet <code>Record</code> définit un {@link $format format} qui
 * indique les noms des champs existants dans le format d'origine et,
 * éventuellement, le ou les champs correspondants en format
 * {@link BdspRecord BDSP}.
 *
 * La méthode {@link isField()} permet de savoir si une chaine donnée
 * représente un nom de champ existant.
 *
 * Bien que le format indique la liste "officielle" des champs sources,
 * rien n'empêche de créer dans l'enregistrement des champs qui ne
 * figurent pas dans cette liste. En fait, lors de l'ajout d'un champ,
 * aucun test n'est fait pour savoir si le champ indiqué est défini ou
 * non dans le format. C'est pratique, notamment lors de la conversion,
 * pour créer des champs temporaires qui seront ensuite supprimés ou
 * renommés.
 *
 * Les objets <code>Record</code> peuvent être créés de manière indépendante,
 * mais en général, ils sont créés par un objet {@link RecordReader} associé
 * qui se charge de décrypter le fichier et de créer les enregistrements en
 * appellant la méthode {@link store()} au fur et à mesure de la lecture.
 *
 * La classe <code>Record</code> possède une méthode importante,
 * {@link isAcceptable()} qui permet de savoir si un enregistrement est
 * acceptable.
 *
 * La méthode par défaut rejette les enregistrements complètement vides, mais
 * les classes descendantes peuvent ajouter des conditions supplémentaires
 * (rejet des notices confidentielles, rejet des doublons, rejet des notices
 * qui contiennent trop d'erreurs, etc.) en surchargeant la méthode
 * {@link isAcceptable()}.
 *
 * L'une des finalités des enregistrements est d'être convertis au format
 * {@link BdspRecord BDSP}. Pour cela, la classe <code>Record</code> définit
 * la méthode {@link toBdsp()} qui permet de
 * {@link convertToBdsp() convertir l'enregistrement} au format
 * {@link BdspRecord BDSP}.
 *
 * La classe <code>Record</code> dispose également de plusieurs méthodes
 * utilitaires qui enrichissent les méthodes héritées de {@link Multimap}
 * et simplifient la manipulation des enregistrements : {@link prefix()},
 * {@link suffix()}, {@link explode()}, {@link implode()}.
 *
 * Enfin, elle fournit également des callbacks utilisables avec la
 * méthode {@link Multimap::apply() apply()} pour plusieurs fonctions
 * standard de php pour lesquelles l'ordre de passage des paramètres
 * n'est pas celui attendu par la méthode <code>apply()</code> :
 * {@link str_replace()} et {@link preg_replace()}.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class AbstractRecord extends Multimap
{

    /**
     * Format de l'enregistrement : liste des champs présents dans le format
     * d'origine et correspondance éventuelle avec le ou les champs BDSP.
     *
     * Cette propriété est destinée à être surchargée par les classes descendantes.
     *
     * Vous pouvez aussi surcharger la méthode {@link loadFormat()} pour que
     * celle-ci initialise <code>$format</code>.
     *
     * Initialement, <code>$format</code> est définit comme un simple tableau
     * php puis est converti en objet {@link Multimap} par la méthode
     * {@link loadFormat()}.
     *
     * Les clés du tableau désignent les noms des champs dans le format source.
     * Les valeurs désignent les noms des champs dans le format destination
     * (le format {@link BdspRecord BDSP} normallement).
     *
     * Si seule la valeur est indiquée, le même nom de champ est utilisé comme
     * champ source et comme champ destination.
     *
     * Vous pouvez indiquer qu'un champ source n'a pas de correspondance dans
     * le format destination en indiquant la valeur <code>false</code> comme
     * valeur. Lors de la {@link toBdsp() conversion au format Bdsp}, le champ
     * sera ignoré.
     *
     * Utilisez une étoile à la fin des noms de champ pour indiquer les champs
     * articles et pour que les contenus associés soient automatiquement convertis
     * en tableaux.
     *
     * L'ordre dans lequel vous indiquez les champs est important : si plusieurs
     * champs source ont le même champ destination, lors de la conversion, les
     * articles provenant des champs sources seront stockés dans l'ordre dans
     * lequel les champs sources apparaissent dans le format.
     *
     * @var array|Multimap Initialement définit comme tableau, le format est
     * convertit en {@link Multimap} dès que l'objet <code>Record</code> est créé.
     */
    protected $format = array();


    /**
     * Pour les champs articles (marqués avec une étoile dans {@link $format}),
     * séparateur utilisé pour séparer les articles.
     *
     * Un <code>trim()</code> est systématiquement appliqué au séparateur lors
     * du découpage d'une chaine en articles.
     *
     * Le séparateur indiqué est également utilisé par la méthode
     * {@link __toString()} pour afficher les articles.
     *
     * @var string
     */
    protected $sep = ', ';


    /**
     * Tableau listant les champs articles.
     *
     * Le tableau est de la forme "nom de champ" => true. Il permet de savoir
     * rapidement si un champ donné est un champ article ou un champ texte.
     *
     * Il est initialisé à partir du format par la méthode {@link loadFormat()}.
     *
     * @var array
     */
    protected $multiple = array();



    /**
     * Crée un nouvel enregistrement, ajoute les éventuelles données transmises
     * au constructeur puis {@link loadFormat()} charge le format.
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets itérables)
     * représentant les données initiales de l'enregistrement.
     */
    public function __construct($data = null)
    {
        $args = func_get_args();
        call_user_func_array(array($this, 'parent::__construct'), $args);
        $this->loadFormat();
    }


    /**
     * Charge le {@link $format format} de l'enregistrement, le vérifie et le
     * transforme en objet {@link Multimap} pour faciliter sa manipulation.
     *
     * Le tableau {@link $multiple()} qui référence les champs articles est
     * également initialisé.
     */
    protected function loadFormat()
    {
        $result = Multimap::create()->compareMode(Multimap::CMP_IDENTICAL)->keyDelimiter("n0n3\0");

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

            if ($result->has($source))
                throw new Exception(__CLASS__ . " : Champ $source dupliqué dans le format");

            $result->set($source, $destination);
        }

        if ($result->isEmpty())
            throw new Exception(__CLASS__ . ' : Aucun format défini');

        $this->format = $result;
    }


    /**
     * Indique si le nom passé en paramètre est un nom de champ défini dans
     * le format de l'enregistrement.
     *
     * @param string $field le nom de champ à tester
     *
     * @return bool <code>true</true> si <code>$field</code> existe dans la liste
     * des champs sources indiqués dans le {@link $format format}.
     */
    public function isField($field)
    {
        return $this->format->has($field);
    }


    /**
     * Convertit l'enregistrement au format BDSP.
     *
     * La méthode <code>toBdsp()</code> lance le processus de conversion au format
     * BDSP de l'enregistrement en cours et retourne le résultat sous la forme
     * d'un nouvel objet {@link BdspRecord}.
     *
     * Cette méthode n'est pas destinée à être surchargée. Les classes descendantes
     * doivent enrichir la méthode {@link convertToBdsp()} en ajoutant les traitements
     * nécessaires à la conversion.
     *
     * La conversion s'effectue "sur place" : <code>toBdsp()</code> commence par
     * faire une copie de l'enregistrement source, lance la conversion, crée un objet
     * BdspRecord avec le résultat obtenu puis restaure la copie réalisée au début.
     *
     * @return BdspRecord un enregistrement au format BDSP
     */
    public function toBdsp()
    {
        // Conserve une copie de l'enregistrement en cours
        $sav = $this->toArray();

        // Lance la conversion
        $this->convertToBdsp();

        // Crée l'enregistrement BDSP
        $bdsp = new BdspRecord($this);

        // Restore la copie et retourne le résultat
        $this->clear()->addMany($sav);
        return $bdsp;
    }


    /**
     * Fonction de débogage : génère un var_export() des données internes de
     * l'enregistrement.
     */
    public function dump()
    {
        var_export($this->data);
        echo "\n";
    }


    /**
     * Effectue la conversion sur place de l'enregistrement source en format BDSP.
     *
     * La méthode par défaut utilise les informations présentes dans le
     * {@link format $format} pour convertir les champs.
     *
     * Les classes descendantes doivent surcharger cette méthode en ajoutant leurs
     * propres traitements.
     *
     * Exemple :
     * <code>
     * protected function convertToBdsp()
     * {
     *     return parent::convertToBdsp()->mesTraitements();
     * }
     * </code>
     *
     * @return $this
     */
    protected function convertToBdsp()
    {
        // Transfère les champs du format source dans les champs du format destination
        foreach($this->format as $from => $to)
        {
            if ($from === $to || ! $this->$from) continue;
            if ($to === false)
                $this->clear($from);
            else
                $this->move("$to,$from", $to);
        }

        return $this;
    }


    /**
     * Retourne une représentation au format AJP de l'enregistrement.
     *
     * Les articles sont affichés en utilisant le
     * {@link $sep séparateur d'articles} défini.
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();
        foreach ($this->data as $key => $data)
        {
            echo $key, "\n";
            $i = 0;
            foreach((array) $data as $value)
            {
                if ($i) echo $this->sep;
                echo $value;
                ++$i;
            }
            echo "\n";
        }

        echo "//\n";
        return ob_get_clean();
    }


    /**
     * Indique si l'enregistrement, dans son état actuel, est acceptable ou non.
     *
     * La méthode par défaut rejette les enregistrements complètement vides.
     *
     * Les classes descendantes peuvent surcharger cette méthode et ajouter des
     * conditions supplémentaires : rejet des notices confidentielles,
     * rejet des doublons, rejet des notices qui contiennent trop d'erreurs, etc.
     *
     * @return bool <code>true</code>> si l'enregistrement est acceptable,
     * <code>false</code> sinon.
     */
    public function isAcceptable()
    {
        return ! $this->isEmpty();
    }


    /**
     * Stocke un contenu pour un champ.
     *
     * Cette méthode est appellé par les objets {@link Reader} au fur et à mesure
     * qu'ils lisent le fichier.
     *
     * Si le champ indiqué est un {@link $multiple champ articles}, la valeur
     * est convertie en tableau d'articles en utilisant le {@link $sep séparateur}
     * défini dans la classe et un <code>trim</code> est appliqué à chacun des
     * articles.
     *
     * @param string $field nom du champ.
     * @param string $content contenu à ajouter au champ
     *
     * @return $this
     */
    public function store($field, $content)
    {
        if (! isset($this->multiple[$field]))
            return $this->add($field, $content);

        $content = array_map('trim', explode(trim($this->sep), $content));
        return $this->addMany($field, $content);
    }


    // ---------------------------------------------------------------------------------------------
    // Méthodes utilitaires
    // ---------------------------------------------------------------------------------------------


    /**
     * Filtre sur expression régulière utilisable avec la méthode {@link filter()}.
     *
     * Le filtre fonctionne comme la fonction php {@link http://php.net/preg_match preg_match()},
     * si ce n'est que les paramètres ont été adaptés à {@link filter()}.
     *
     * @param string $value
     * @param string $key
     * @param string $pattern
     * @param int $flags
     * @param array $matches
     * @param int $offset
     *
     * @return int
     */
    protected function like($value, $key, $pattern, $flags = null, & $matches = null, $offset = null)
    {
        return preg_match ($pattern, $value, $matches, $flags, $offset);
    }


    /**
     * Helper permettant de faire un lookup sur une {@link ReferenceTable table de référence}.
     *
     * Cette méthode peut être utilisée directement ou comme callback passé à {@link apply()} :
     *
     * <code>
     * $this->lookup('FRE', 'Tables/Langue.txt', 'Code', 'Label');
     * $this->apply('lookup', 'Tables/Langue.txt', 'Code', 'Label');
     * </code>
     *
     * @param string $value la valeur recherchée
     * @param string|ReferenceTable $table la table à utiliser.
     * @param string $field le champ sur lequel porte la recherche.
     * @param string $return le champ à retourner.
     * @param bool $clearIfNotFound retourne une chaine vide si la valeur n'existe pas.
     */
    protected function lookup($value, $table, $field, $return = null, $clearIfNotFound = false)
    {
        if ($table instanceof ReferenceTable)
            return (string) $table->lookup($field, $value, $return, $clearIfNotFound);
        else
            return (string) ReferenceTable::open($table)->lookup($field, $value, $return, $clearIfNotFound);
    }


    /**
     * Version modifiée de la fonction php {@link http://php.net/preg_replace preg_replace()}
     * utilisable comme callback lors d'un appel à {@link apply()}.
     *
     * La méthode <code>apply()</code> appelle le callback en lui passant comme premier
     * argument la valeur à modifier mais la fonction <code>preg_replace()</code> de php
     * attend en premier paramètre l'expression régulière à utiliser, la valeur à
     * modifier étant en seconde position.
     *
     * Ce callback se contente de changer l'ordre des paramètres.
     *
     * @param string $subject
     * @param string $pattern
     * @param string $replacement
     * @param int $limit
     * @param int $count
     *
     * @return string
     */
    protected function preg_replace($subject, $pattern, $replacement = '', $limit = -1, & $count=null)
    {
        return preg_replace($pattern, $replacement, $subject, $limit, $count);
    }


    /**
     * Version modifiée de la fonction php {@link http://php.net/str_replace str_replace()}
     * utilisable comme callback lors d'un appel à {@link apply()}.
     *
     * La méthode <code>apply()</code> appelle le callback en lui passant comme premier
     * argument la valeur à modifier, ce qui n'est pas l'ordre attendu par
     * <code>str_replace()</code>.
     *
     * Ce callback se contente de changer l'ordre des paramètres.
     *
     * @param string $subject
     * @param string|array $search
     * @param string|array $replace
     * @param int $count
     *
     * @return string
     */
    protected function str_replace($subject, $search, $replace = '', & $count=null)
    {
        return str_replace($search, $replace, $subject, $count);
    }


    /**
     * Ajoute un préfixe à tous les articles présents dans le ou les champs indiqués.
     *
     * Exemple :
     * <code>
     * $record->prefix('Ident', 'AED-BDSP : ');
     * </code>
     *
     * @param mixed $fields le ou les champs auxquels le préfixe sera ajouté.
     * @param string $prefix le préfixe à ajouter.
     *
     * @return $this
     */
    protected function prefix($fields, $prefix)
    {
        return $this->apply('addPrefix', $fields, $prefix);
    }


    /**
     * Callback utilisé par {@link prefix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le préfixe à ajouter.
     * @return string
     */
    protected function addPrefix($value, $prefix)
    {
        return $prefix . $value;
    }


    /**
     * Ajoute un suffixe à tous les articles présents dans le ou les champs indiqués.
     *
     * Exemple :
     * <code>
     * $record->suffix('ORGCOM1', '/ com.');
     * </code>
     *
     * @param mixed $fields le ou les champs auxquels le suffixe sera ajouté.
     * @param string $suffix le suffixe à ajouter.
     *
     * @return $this
     */
    protected function suffix($fields, $suffix)
    {
        return $this->apply('addSuffix', $fields, $suffix);
    }


    /**
     * Callback utilisé par {@link suffix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le préfixe à ajouter.
     *
     * @return string
     */
    protected function addSuffix($value, $suffix)
    {
        return "$value$suffix";
    }


    /**
     * Concatène les données présentes dans une ou plusieurs clés avec le séparateur indiqué.
     *
     * @param string $sep le séparateur à utiliser.
     * @param mixed $key la ou les clés pour lesquelles il faut concaténer les valeurs.
     *
     * @return $this
     */
    protected function implode($sep='', $key)
    {
        foreach($this->parseKey($key) as $key)
            if (isset($this->data[$key]))
                $this->data[$key] = array(implode($sep, $this->data[$key]));

        return $this;
    }


    /**
     * Découpe un champ en articles.
     *
     * la méthode <code>explode</code> permet de convertir en articles le contenu du ou
     * des champs indiqués en utilisant le délimiteur <code>$sep</code> comme séparateur.
     *
     * Si le champ indiqué contenait plusieurs valeurs, celles-ci sont tout d'abord réunies
     * (implode) puis découpées (explode).
     *
     * Si <code>$key</code> désigne plusieurs champs, chaque champ est traité séparément.
     *
     * Exemples :
     * <code>
     * $map->set('item', 'A,B,C')->explode(',', 'item'); // array('a','b','c')
     * $map->set('item', array('A,B', 'C,D')->explode(',', 'item'); // array('a','b','c', 'd');
     * </code>
     *
     * @param string $sep le délimiteur à utiliser.
     * @param mixed $key la ou les clés à découper.
     *
     * @return $this
     */
    protected function explode($sep='', $key)
    {
        $trimsep = trim($sep);

        foreach($this->parseKey($key) as $key)
        {
            if (! isset($this->data[$key])) continue;
            $t = array_map('trim', explode($trimsep, implode($trimsep, $this->data[$key])));
            $this->data[$key] = array();
            $this->addMany($key, $t);
        }
        return $this;
    }
}