<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base abstraite pour toutes les classes <code>Record</code> de
 * la chaine de traitement.
 *
 * Un objet <code>Record</code> repr�sente un enregistrement dans un format
 * donn�. C'est essentiellement une {@link Multimap collection de champs}
 * contenant une valeur ou un tableau d'articles.
 *
 * Chaque objet <code>Record</code> d�finit un {@link $format format} qui
 * indique les noms des champs existants dans le format d'origine et,
 * �ventuellement, le ou les champs correspondants en format
 * {@link BdspRecord BDSP}.
 *
 * La m�thode {@link isField()} permet de savoir si une chaine donn�e
 * repr�sente un nom de champ existant.
 *
 * Bien que le format indique la liste "officielle" des champs sources,
 * rien n'emp�che de cr�er dans l'enregistrement des champs qui ne
 * figurent pas dans cette liste. En fait, lors de l'ajout d'un champ,
 * aucun test n'est fait pour savoir si le champ indiqu� est d�fini ou
 * non dans le format. C'est pratique, notamment lors de la conversion,
 * pour cr�er des champs temporaires qui seront ensuite supprim�s ou
 * renomm�s.
 *
 * Les objets <code>Record</code> peuvent �tre cr��s de mani�re ind�pendante,
 * mais en g�n�ral, ils sont cr��s par un objet {@link RecordReader} associ�
 * qui se charge de d�crypter le fichier et de cr�er les enregistrements en
 * appellant la m�thode {@link store()} au fur et � mesure de la lecture.
 *
 * La classe <code>Record</code> poss�de une m�thode importante,
 * {@link isAcceptable()} qui permet de savoir si un enregistrement est
 * acceptable.
 *
 * La m�thode par d�faut rejette les enregistrements compl�tement vides, mais
 * les classes descendantes peuvent ajouter des conditions suppl�mentaires
 * (rejet des notices confidentielles, rejet des doublons, rejet des notices
 * qui contiennent trop d'erreurs, etc.) en surchargeant la m�thode
 * {@link isAcceptable()}.
 *
 * L'une des finalit�s des enregistrements est d'�tre convertis au format
 * {@link BdspRecord BDSP}. Pour cela, la classe <code>Record</code> d�finit
 * la m�thode {@link toBdsp()} qui permet de
 * {@link convertToBdsp() convertir l'enregistrement} au format
 * {@link BdspRecord BDSP}.
 *
 * La classe <code>Record</code> dispose �galement de plusieurs m�thodes
 * utilitaires qui enrichissent les m�thodes h�rit�es de {@link Multimap}
 * et simplifient la manipulation des enregistrements : {@link prefix()},
 * {@link suffix()}, {@link explode()}, {@link implode()}.
 *
 * Enfin, elle fournit �galement des callbacks utilisables avec la
 * m�thode {@link Multimap::apply() apply()} pour plusieurs fonctions
 * standard de php pour lesquelles l'ordre de passage des param�tres
 * n'est pas celui attendu par la m�thode <code>apply()</code> :
 * {@link str_replace()} et {@link preg_replace()}.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class AbstractRecord extends Multimap
{

    /**
     * Format de l'enregistrement : liste des champs pr�sents dans le format
     * d'origine et correspondance �ventuelle avec le ou les champs BDSP.
     *
     * Cette propri�t� est destin�e � �tre surcharg�e par les classes descendantes.
     *
     * Vous pouvez aussi surcharger la m�thode {@link loadFormat()} pour que
     * celle-ci initialise <code>$format</code>.
     *
     * Initialement, <code>$format</code> est d�finit comme un simple tableau
     * php puis est converti en objet {@link Multimap} par la m�thode
     * {@link loadFormat()}.
     *
     * Les cl�s du tableau d�signent les noms des champs dans le format source.
     * Les valeurs d�signent les noms des champs dans le format destination
     * (le format {@link BdspRecord BDSP} normallement).
     *
     * Si seule la valeur est indiqu�e, le m�me nom de champ est utilis� comme
     * champ source et comme champ destination.
     *
     * Vous pouvez indiquer qu'un champ source n'a pas de correspondance dans
     * le format destination en indiquant la valeur <code>false</code> comme
     * valeur. Lors de la {@link toBdsp() conversion au format Bdsp}, le champ
     * sera ignor�.
     *
     * Utilisez une �toile � la fin des noms de champ pour indiquer les champs
     * articles et pour que les contenus associ�s soient automatiquement convertis
     * en tableaux.
     *
     * L'ordre dans lequel vous indiquez les champs est important : si plusieurs
     * champs source ont le m�me champ destination, lors de la conversion, les
     * articles provenant des champs sources seront stock�s dans l'ordre dans
     * lequel les champs sources apparaissent dans le format.
     *
     * @var array|Multimap Initialement d�finit comme tableau, le format est
     * convertit en {@link Multimap} d�s que l'objet <code>Record</code> est cr��.
     */
    protected $format = array();


    /**
     * Pour les champs articles (marqu�s avec une �toile dans {@link $format}),
     * s�parateur utilis� pour s�parer les articles.
     *
     * Un <code>trim()</code> est syst�matiquement appliqu� au s�parateur lors
     * du d�coupage d'une chaine en articles.
     *
     * Le s�parateur indiqu� est �galement utilis� par la m�thode
     * {@link __toString()} pour afficher les articles.
     *
     * @var string
     */
    protected $sep = ', ';


    /**
     * Tableau listant les champs articles.
     *
     * Le tableau est de la forme "nom de champ" => true. Il permet de savoir
     * rapidement si un champ donn� est un champ article ou un champ texte.
     *
     * Il est initialis� � partir du format par la m�thode {@link loadFormat()}.
     *
     * @var array
     */
    protected $multiple = array();



    /**
     * Cr�e un nouvel enregistrement, ajoute les �ventuelles donn�es transmises
     * au constructeur puis {@link loadFormat()} charge le format.
     *
     * @param mixed $data ... optionnel, un ou plusieurs tableaux (ou objets it�rables)
     * repr�sentant les donn�es initiales de l'enregistrement.
     */
    public function __construct($data = null)
    {
        $args = func_get_args();
        call_user_func_array(array($this, 'parent::__construct'), $args);
        $this->loadFormat();
    }


    /**
     * Charge le {@link $format format} de l'enregistrement, le v�rifie et le
     * transforme en objet {@link Multimap} pour faciliter sa manipulation.
     *
     * Le tableau {@link $multiple()} qui r�f�rence les champs articles est
     * �galement initialis�.
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
                throw new Exception(__CLASS__ . " : Champ $source dupliqu� dans le format");

            $result->set($source, $destination);
        }

        if ($result->isEmpty())
            throw new Exception(__CLASS__ . ' : Aucun format d�fini');

        $this->format = $result;
    }


    /**
     * Indique si le nom pass� en param�tre est un nom de champ d�fini dans
     * le format de l'enregistrement.
     *
     * @param string $field le nom de champ � tester
     *
     * @return bool <code>true</true> si <code>$field</code> existe dans la liste
     * des champs sources indiqu�s dans le {@link $format format}.
     */
    public function isField($field)
    {
        return $this->format->has($field);
    }


    /**
     * Convertit l'enregistrement au format BDSP.
     *
     * La m�thode <code>toBdsp()</code> lance le processus de conversion au format
     * BDSP de l'enregistrement en cours et retourne le r�sultat sous la forme
     * d'un nouvel objet {@link BdspRecord}.
     *
     * Cette m�thode n'est pas destin�e � �tre surcharg�e. Les classes descendantes
     * doivent enrichir la m�thode {@link convertToBdsp()} en ajoutant les traitements
     * n�cessaires � la conversion.
     *
     * La conversion s'effectue "sur place" : <code>toBdsp()</code> commence par
     * faire une copie de l'enregistrement source, lance la conversion, cr�e un objet
     * BdspRecord avec le r�sultat obtenu puis restaure la copie r�alis�e au d�but.
     *
     * @return BdspRecord un enregistrement au format BDSP
     */
    public function toBdsp()
    {
        // Conserve une copie de l'enregistrement en cours
        $sav = $this->toArray();

        // Lance la conversion
        $this->convertToBdsp();

        // Cr�e l'enregistrement BDSP
        $bdsp = new BdspRecord($this);

        // Restore la copie et retourne le r�sultat
        $this->clear()->addMany($sav);
        return $bdsp;
    }


    /**
     * Fonction de d�bogage : g�n�re un var_export() des donn�es internes de
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
     * La m�thode par d�faut utilise les informations pr�sentes dans le
     * {@link format $format} pour convertir les champs.
     *
     * Les classes descendantes doivent surcharger cette m�thode en ajoutant leurs
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
        // Transf�re les champs du format source dans les champs du format destination
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
     * Retourne une repr�sentation au format AJP de l'enregistrement.
     *
     * Les articles sont affich�s en utilisant le
     * {@link $sep s�parateur d'articles} d�fini.
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
     * Indique si l'enregistrement, dans son �tat actuel, est acceptable ou non.
     *
     * La m�thode par d�faut rejette les enregistrements compl�tement vides.
     *
     * Les classes descendantes peuvent surcharger cette m�thode et ajouter des
     * conditions suppl�mentaires : rejet des notices confidentielles,
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
     * Cette m�thode est appell� par les objets {@link Reader} au fur et � mesure
     * qu'ils lisent le fichier.
     *
     * Si le champ indiqu� est un {@link $multiple champ articles}, la valeur
     * est convertie en tableau d'articles en utilisant le {@link $sep s�parateur}
     * d�fini dans la classe et un <code>trim</code> est appliqu� � chacun des
     * articles.
     *
     * @param string $field nom du champ.
     * @param string $content contenu � ajouter au champ
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
    // M�thodes utilitaires
    // ---------------------------------------------------------------------------------------------


    /**
     * Filtre sur expression r�guli�re utilisable avec la m�thode {@link filter()}.
     *
     * Le filtre fonctionne comme la fonction php {@link http://php.net/preg_match preg_match()},
     * si ce n'est que les param�tres ont �t� adapt�s � {@link filter()}.
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
     * Helper permettant de faire un lookup sur une {@link ReferenceTable table de r�f�rence}.
     *
     * Cette m�thode peut �tre utilis�e directement ou comme callback pass� � {@link apply()} :
     *
     * <code>
     * $this->lookup('FRE', 'Tables/Langue.txt', 'Code', 'Label');
     * $this->apply('lookup', 'Tables/Langue.txt', 'Code', 'Label');
     * </code>
     *
     * @param string $value la valeur recherch�e
     * @param string|ReferenceTable $table la table � utiliser.
     * @param string $field le champ sur lequel porte la recherche.
     * @param string $return le champ � retourner.
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
     * Version modifi�e de la fonction php {@link http://php.net/preg_replace preg_replace()}
     * utilisable comme callback lors d'un appel � {@link apply()}.
     *
     * La m�thode <code>apply()</code> appelle le callback en lui passant comme premier
     * argument la valeur � modifier mais la fonction <code>preg_replace()</code> de php
     * attend en premier param�tre l'expression r�guli�re � utiliser, la valeur �
     * modifier �tant en seconde position.
     *
     * Ce callback se contente de changer l'ordre des param�tres.
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
     * Version modifi�e de la fonction php {@link http://php.net/str_replace str_replace()}
     * utilisable comme callback lors d'un appel � {@link apply()}.
     *
     * La m�thode <code>apply()</code> appelle le callback en lui passant comme premier
     * argument la valeur � modifier, ce qui n'est pas l'ordre attendu par
     * <code>str_replace()</code>.
     *
     * Ce callback se contente de changer l'ordre des param�tres.
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
     * Ajoute un pr�fixe � tous les articles pr�sents dans le ou les champs indiqu�s.
     *
     * Exemple :
     * <code>
     * $record->prefix('Ident', 'AED-BDSP : ');
     * </code>
     *
     * @param mixed $fields le ou les champs auxquels le pr�fixe sera ajout�.
     * @param string $prefix le pr�fixe � ajouter.
     *
     * @return $this
     */
    protected function prefix($fields, $prefix)
    {
        return $this->apply('addPrefix', $fields, $prefix);
    }


    /**
     * Callback utilis� par {@link prefix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le pr�fixe � ajouter.
     * @return string
     */
    protected function addPrefix($value, $prefix)
    {
        return $prefix . $value;
    }


    /**
     * Ajoute un suffixe � tous les articles pr�sents dans le ou les champs indiqu�s.
     *
     * Exemple :
     * <code>
     * $record->suffix('ORGCOM1', '/ com.');
     * </code>
     *
     * @param mixed $fields le ou les champs auxquels le suffixe sera ajout�.
     * @param string $suffix le suffixe � ajouter.
     *
     * @return $this
     */
    protected function suffix($fields, $suffix)
    {
        return $this->apply('addSuffix', $fields, $suffix);
    }


    /**
     * Callback utilis� par {@link suffix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le pr�fixe � ajouter.
     *
     * @return string
     */
    protected function addSuffix($value, $suffix)
    {
        return "$value$suffix";
    }


    /**
     * Concat�ne les donn�es pr�sentes dans une ou plusieurs cl�s avec le s�parateur indiqu�.
     *
     * @param string $sep le s�parateur � utiliser.
     * @param mixed $key la ou les cl�s pour lesquelles il faut concat�ner les valeurs.
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
     * D�coupe un champ en articles.
     *
     * la m�thode <code>explode</code> permet de convertir en articles le contenu du ou
     * des champs indiqu�s en utilisant le d�limiteur <code>$sep</code> comme s�parateur.
     *
     * Si le champ indiqu� contenait plusieurs valeurs, celles-ci sont tout d'abord r�unies
     * (implode) puis d�coup�es (explode).
     *
     * Si <code>$key</code> d�signe plusieurs champs, chaque champ est trait� s�par�ment.
     *
     * Exemples :
     * <code>
     * $map->set('item', 'A,B,C')->explode(',', 'item'); // array('a','b','c')
     * $map->set('item', array('A,B', 'C,D')->explode(',', 'item'); // array('a','b','c', 'd');
     * </code>
     *
     * @param string $sep le d�limiteur � utiliser.
     * @param mixed $key la ou les cl�s � d�couper.
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